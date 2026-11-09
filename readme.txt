=== Emmie Connector ===
Tags: intake form, booking, care, healthcare, emmie
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects your website to Emmie: an intake form that adds a new client to your Emmie, and Emmie's booking form for introductory meetings.

== Description ==

[Emmie](https://emmie.nl) is a care-management platform for care providers in the Netherlands. Emmie Connector brings two parts of it to your WordPress site.

**The intake form.** A visitor who wants to get to know your organisation fills in a form on your site. Their answers arrive in your Emmie as a new client, with the documents they uploaded.

* Name, e-mail address, phone number and address
* The location the visitor is interested in, and the income situation and contact preference, with the options your Emmie offers
* An optional contact person, such as a parent or a referrer
* How the visitor found you, and a comment
* Documents: doc, docx, rtf, xls, xlsx, txt, jpg, jpeg, png or pdf
* Consent to your privacy statement, linked from the form
* Optional protection against spam with Google reCAPTCHA

**The booking form.** With the shortcode `[emmie_kennismaking]` a visitor plans an introductory meeting straight in your Emmie agenda. After the intake form is sent, the visitor is offered the same.

You need an organisation on Emmie to use this plugin.

== Installation ==

1. Install and activate the plugin. The settings page opens on its own.
2. In Emmie, go to Instellingen › Instroom. Switch on the intake form (Kennismakingsformulier), copy the API address and make an API token.
3. Paste both on the settings page of the plugin (Settings › Emmie Connector) and save. The plugin checks the token with Emmie.
4. Once the token checks out, the plugin creates the page `/kennismaken` with the intake form.

To use reCAPTCHA, create reCAPTCHA v3 keys in the Google reCAPTCHA admin console and enter both on the settings page.

== Frequently Asked Questions ==

= Which shortcodes are there? =

* `[wp2emmie_form]` shows the intake form.
* `[emmie_kennismaking]` shows the booking form for introductory meetings. Add `opmaak="basis"` for Emmie's neutral base style or `opmaak="geen"` for your theme's style only; without it, the setting on the settings page decides.

= Why does the form not show? =

Both forms show only after the plugin checked the API address and token with Emmie, and Emmie accepted them. Emmie refuses the check while the intake form is switched off under Instroom. Until the check succeeds, the intake form says it is not available, and the booking form shows nothing to visitors and a notice to administrators. Saving the settings repeats the check.

= Can I change the /kennismaken address? =

Yes. It is an ordinary WordPress page that you can rename or move.

= What happens when I deactivate the plugin? =

The plugin asks whether to delete its settings and the page with the intake form. Choose Cancel to keep both.

= I have the plugin from Emmie as a zip. How do I switch? =

1. Install Emmie Connector, but do not activate it yet.
2. Deactivate the old plugin. When it asks whether to delete its settings, choose Cancel.
3. Activate Emmie Connector. It uses the same settings, so the form keeps working.
4. Delete the old plugin.

= Which privacy statement does the form link to? =

The privacy statement of your site, as set under Settings › Privacy. The personal data from the form are yours. Only while your site has none, the form links to Emmie's privacy statement.

= Where is the API token kept? =

In the WordPress database, encrypted with the keys from your `wp-config.php`.

== External services ==

This plugin connects your site to two external services. It contacts them only after you have entered their details on the settings page.

= Emmie =

Emmie is the care-management platform of your organisation; the plugin sends the intake form to it. The plugin calls the API address of your own Emmie, which you enter on the settings page.

* When you save the settings, the plugin sends the API token to Emmie to check it.
* When the intake form is shown, the plugin asks Emmie for the options of its lists (locations, income situations, contact preferences and how visitors found you), with the API token. It keeps them for an hour.
* When a visitor sends the intake form, the plugin sends what the visitor entered to Emmie: name, e-mail address, phone number, address, the chosen options, the contact person if one was given, the comment and any uploaded documents.
* On a page with the shortcode `[emmie_kennismaking]`, the visitor's browser loads Emmie's booking form (`/kennismaking/widget.js`) from your Emmie, and the visitor's choices go straight to Emmie.

Emmie is provided by Emmie B.V.: [terms of use](https://emmie.nl/algemene-voorwaarden), [privacy statement](https://emmie.nl/privacy).

= Google reCAPTCHA =

Only when you enter reCAPTCHA keys, the intake form uses Google reCAPTCHA against spam.

* On the page with the intake form, the visitor's browser loads `https://www.google.com/recaptcha/api.js`. Google then receives data about the visitor's browser and use of the page.
* When the form is sent, the plugin sends the reCAPTCHA answer and the visitor's IP address to `https://www.google.com/recaptcha/api/siteverify`.

Google reCAPTCHA is provided by Google: [terms of service](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy).

== Changelog ==

= 1.4.0 =
* The plugin is now called Emmie Connector, and comes from the WordPress.org plugin directory.
* The API address works with and without `/api` at the end, as Emmie shows it.
* Adding a WordPress user no longer ends in a critical error.

= 1.3.0 =
* The booking form for introductory meetings, with the shortcode `[emmie_kennismaking]`.
* The intake form links to the privacy statement of your site.

= 1.2.0 =
* The intake form asks for location, income situation, contact preference, a contact person and how the visitor found you, with the options from Emmie.
* Visitors can upload documents.

= 1.1.0 =
* reCAPTCHA v3 and validation of the phone number.

= 1.0.0 =
* First release: the intake form and the settings page.
