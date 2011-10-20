# Highrise

The Highrise extension allows you to add Contacts to your Highrise account via Symphony events.

- Version: 0.1
- Date: 20th October 2011
- Requirements: Symphony 2.0.6 or newer, <http://github.com/symphonycms/symphony-2/>
- Author: Brendan Abbott [brendan@bloodbone.ws]
- GitHub Repository: <http://github.com/brendo/highrise>

## Installation

1. Upload the 'highrise' folder in this archive to your Symphony 'extensions' folder.
2. Enable it by selecting the "Highrise" extension from System > Extensions, choose Enable from the With Selected menu, then click Apply.
3. You can now add the "Highrise" filter to your Events.

## Usage

1. Go to your Highrise account and get your API Token (My Info > API Token). Add this to the preferences page.
2. Create an event and attach the Highrise filter to the Event
3. Create your form in the XSLT and add `highrise[field][first-name]` (as this is the minimum Highrise requires to create a new Person).

The `$field-first-name` syntax will get the value of the <input name='fields[first-name]' /> when posting to Highrise.

	<input name="highrise[field][first-name]" value="$field-first-name" type="hidden" />

If your form only captures Name as a single field, you can pass 'name' and the extension will automatically split the value on the first space. eg. Bob Jones Smith will be entered in Highrise as First Name: Bob, Last Name: Jones Smith.

	<input name="highrise[field][name]" value="$field-name" type="hidden" />

Generally speaking, this filter will map your form fields according to the [Highrise Data Reference](http://developer.37signals.com/highrise/reference). There is a bit of magic available for you though to make this a bit easier.

If you want to add an email address to a Highrise record, the API wants:

	<contact-data>
	  <email-addresses>
	    <email-address>
	      <id type="integer">1</id>
	      <address>john.doe@example.com</address>
	      <location>#{ Work || Home || Other }</location>
	    </email-address>
	  </email-addresses>
	</contact-data>

That's pretty verbose, so just pass `highrise[field][email-address]` which will produce:

	<contact-data>
	  <email-addresses>
	    <email-address>
	      <address>john.doe@example.com</address>
		  <location>Other</location>
	    </email-address>
	  </email-addresses>
	</contact-data>

You can add multiple tags to a record by separating them with a comma:

	<input name="highrise[field][tags]" value="tag 1, tag 2" type="hidden" />

There is no support for custom fields yet (aka, `subject_datas`). If you need it, fork it, add it and submit a pull request :)

## Changelog

*0.1* (20th October 2011)

- Initial release