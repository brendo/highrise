<?php

	class Extension_Highrise extends Extension {
		private $params = array();

		public function about() {
			return array(
				'name'			=> 'Highrise',
				'version'		=> '0.1',
				'release-date'	=> '2011-10-20',
				'author'		=> array(
					array(
						'name' => 'Brendan Abbott',
						'website' => 'http://www.bloodbone.ws',
						'email' => 'brendan@bloodbone.ws'
					),
				),
				'description'	=> 'A simple Event Filter to add Contacts to your Highrise account via Symphony events.'
	 		);
		}

		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilterDocumentation',
					'callback'	=> 'appendDocumentation'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilterDocumentation',
					'callback'	=> 'appendDocumentation'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'preProcessData'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'postProcessData'
				)
			);
		}

	/*-------------------------------------------------------------------------
		Installation:
	-------------------------------------------------------------------------*/

		public function uninstall() {
			Symphony::Configuration()->remove('highrise');
			Administration::instance()->saveConfig();
		}

	/*-------------------------------------------------------------------------
		Delegate Callbacks:
	-------------------------------------------------------------------------*/

		public function appendFilter($context) {
			$context['options'][] = array(
				'highrise',
				@in_array(
					'highrise', $context['selected']
				),
				'Highrise'
			);
		}

		public function appendDocumentation($context) {
			if (!in_array('highrise', $context['selected'])) return;

			$context['documentation'][] = new XMLElement('h3', 'Highrise Filter');

			$context['documentation'][] = new XMLElement('p', '
				To use the Highrise filter, add the following field to your form:
			');

			$context['documentation'][] = contentBlueprintsEvents::processDocumentationCode('
<input name="highrise[field][first-name]" value="$field-first-name" type="hidden" />
<input name="highrise[field][email-address]" value="$field-email-address" type="hidden" />
			');
		}

		public function appendPreferences($context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(
				new XMLElement('legend', 'Highrise')
			);

			$group = new XMLElement('div', null, array('class' => 'group'));

			$endpoint = Widget::Label('Location');
			$endpoint->appendChild(Widget::Input(
				'settings[highrise][apiendpoint]', Extension_Highrise::getAPIEndpoint()
			));
			$endpoint->appendChild(
				new XMLElement('p', __('Your full Highrise domain, eg. <code>https://example.highrisehq.com</code>.'), array('class' => 'help', 'style' => 'margin-top: 0'))
			);
			$group->appendChild($endpoint);

			$APIToken = Widget::Label('API Token');
			$APIToken->appendChild(Widget::Input(
				'settings[highrise][apitoken]', Extension_Highrise::getAPIToken()
			));
			$APIToken->appendChild(
				new XMLElement('p', __('This is your API Token which can be found under "My info" in your Highrise account.'), array('class' => 'help', 'style' => 'margin-top: 0'))
			);
			$group->appendChild($APIToken);

			$fieldset->appendChild($group);

			$context['wrapper']->appendChild($fieldset);
		}

		public function preProcessData($context) {
			if (!in_array('highrise', $context['event']->eParamFILTERS)) return;

			if (
				!isset($_POST['highrise']['field']['first-name'])
				and !isset($_POST['highrise']['field']['name'])
			) {
				$context['messages'][] = array(
					'highrise',
					false,
					'Required field missing, see event documentation.'
				);
			}
		}

		public function postProcessData($context) {
			if (!in_array('highrise', $context['event']->eParamFILTERS)) return;

			if (Extension_Highrise::getAPIEndpoint() == "") {
				$context['messages'][] = array('highrise', false, 'No API Endpoint set.');
				return;
			}

			// Create params:
			$this->params = $this->prepareFields('field', $_POST['fields']);

			// Create XML
			$request = new SimpleXMLElement('<person></person>');
			$contact_data = $request->addChild('contact-data');

			// Parse values:
			$values = $this->parseFields($_POST['highrise']['field']);

			// Add fields:
			foreach ($values as $name => $value) {
				if (in_array($name, array('first-name', 'last-name'))) {
					$request->addChild($name, $value);
				}

				// If `name` is passed, automatically split on the first space
				// to get first-name/last-name. If a single word is given then
				// last-name will be filled with ' '.
				else if ($name == 'name') {
					$name_parts = explode(' ', $value, 2);
					$request->addChild('first-name', $name_parts[0]);

					if(isset($name_parts[1])) {
						$request->addChild('last-name', $name_parts[1]);
					}
				}

				// Support for a single email address (no location)
				else if ($name == 'email-address') {
					$email_addresses = $contact_data->addChild('email-addresses');
					$email_address = $email_addresses->addChild('email-address');
					$email_address->addChild('address', $value);
					$email_address->addChild('location', 'Other');
				}

				// Support for a phone number (no location)
				else if ($name == 'phone-number') {
					$phone_numbers = $contact_data->addChild('phone-numbers');
					$phone_number = $phone_numbers->addChild('phone-number');
					$phone_number->addChild('number', $value);
					$phone_number->addChild('location', 'Other');
				}

				// Support tags
				else if ($name == 'tags') {
					$tag_requests = array();
					$tag_values = explode(',', $value);

					foreach($tag_values as $tag) {
						$tag_requests[] = new SimpleXMLElement('<name>' . $tag . '</name>');
					}
				}
			}

			$api = sprintf(
				"%s/people.xml", trim(Extension_Highrise::getAPIEndpoint(), " /")
			);

			$ch = curl_init($api);
			Extension_Highrise::setCurlOptions($ch, $request);

			$response = curl_exec($ch);
			$info = curl_getinfo($ch);

			if(in_array($info['http_code'], array(200, 201))) {
				$context['messages'][] = array('highrise', true, 'Person created successfully');

				// If the person was created, and tags were sent, well.. lets do that
				if(isset($tag_requests)) {
					$person = new SimpleXMLElement($response);
					$tag_api = sprintf(
						"%s/people/%d/tags.xml", trim(Extension_Highrise::getAPIEndpoint(), " /"), $person->id
					);

					// We have to add the tags one by one by one.
					foreach($tag_requests as $request) {
						$tag = curl_init($tag_api);
						Extension_Highrise::setCurlOptions($tag, $request);

						$t = curl_exec($tag);
						$t_info = curl_getinfo($tag);

						if(!in_array($t_info['http_code'], array(200, 201))) {
							$context['messages'][] = array('highrise', true, 'Tag, ' . $request->name . ', failed to add to ' . $person->first_name . '.');
						}
					}
				}
			}
			else if($info['http_code'] == 507) {
				$context['messages'][] = array('highrise', false, 'Your Highrise account does\'t allow for any more People to be created');
			}
			else {
				$context['messages'][] = array('highrise', false, 'Something went wrong, and we\'re really not sure where. Sorry!');
			}

			curl_close($ch);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function getAPIToken() {
			return Symphony::Configuration()->get('apitoken', 'highrise');
		}

		public static function getAPIEndpoint() {
			return Symphony::Configuration()->get('apiendpoint', 'highrise');
		}

		public static function setCurlOptions(&$ch, $data) {
			curl_setopt_array($tag, array(
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_USERPWD => Extension_Highrise::getAPIToken() . ":X",
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $data->asXML(),
				CURLOPT_HTTPHEADER => array("Content-type: application/xml; charset=utf-8"),
				CURLOPT_RETURNTRANSFER => true
			));
		}

		/**
		 * Takes the `$_POST['fields']` and generates a flat array of
		 * all the fields.
		 *
		 * @param string $path
		 *  The default string to use when a field needs to be flattened
		 * @param array $fields
		 *  The `$_POST['fields']` array
		 * @return array
		 */
		public function prepareFields($path, $fields) {
			$output = array();

			foreach($fields as $key => $value) {
				if (!is_numeric($key)) {
					$key = "{$path}-{$key}";

					if (is_array($value)) {
						$temp = $this->prepareFields($key, $value);
						$output = array_merge($output, $temp);
					}
					else {
						$output[$key] = $value;
					}
				}
				else {
					$key = $path;

					$output[$key][] = $value;
				}
			}

			return $output;
		}

		/**
		 * Given an associative array of values, this will strip the
		 * dollar notation from the value, and then will map the fields
		 * to their Highrise equivalents.
		 *
		 * @param array $values
		 * @return array
		 */
		public function parseFields($values) {
			foreach($values as $key => $value) {
				$value = preg_replace('/^\$/', null, $value);

				if(isset($this->params[$value])) {
					$values[$key] = $this->params[$value];
				}
			}

			return $values;
		}
	}
