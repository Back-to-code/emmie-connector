=== WP to Emmie Connector ===
Contributors: Script
Tags: api, Emmie, integration, form, recaptcha, Laravel
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A secure integration plugin that connects your WordPress site to Emmie, allowing users to submit personal information through a configurable form with reCAPTCHA.

== Description ==

**WP to Emmie Connector** enables your WordPress site to securely send form data to Emmie.

Key Features:
- Setup wizard to configure API token, Emmie endpoint, and reCAPTCHA keys
- Encrypted storage of sensitive data (API token and reCAPTCHA keys)
- Automatically creates a public-facing `/kennismaken` page with a shortcode-based form
- Form includes name, email, and phone number fields
- Optionally delete plugin settings and created page on deactivation
- Built-in token validation and reCAPTCHA v3 support

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin via the “Plugins” menu in WordPress
3. Follow the configuration wizard to set your API token, URL, and reCAPTCHA keys
4. Visit `/kennismaken` to test the form

== Usage ==

- Use the shortcode `[wp2Emmie_form]` in any post or page to render the user form.
- Use the shortcode `[emmie_kennismaking]` to show Emmie's booking form for introductory meetings. Add `opmaak="basis"` or `opmaak="geen"` to override the base style setting for one page.
- The form will only display if the API token is valid.
- The plugin currently only supports reCAPTCHA v3 keys.
- Submissions are sent to your configured Emmie endpoint.

== Security ==

- reCAPTCHA protects the form from bot abuse.
- Only validated API tokens allow form submissions.

== Frequently Asked Questions ==

= What happens when I deactivate the plugin? =

You will be prompted to delete all plugin settings and the generated page. If you choose not to, your data will be preserved.

= Can I change the `/kennismaken` URL? =

Yes, you can rename or move the page created by the plugin just like any normal WordPress page.

= What if the token becomes invalid? =

The form will automatically be hidden from public view until a valid token is provided.

== Logging ==

For developers:

To enable logging in WordPress, add the following configuration:

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

to your wp-config.php file. The log file can be accessed through `wp-content/debug.log`.

== Changelog ==

= 1.0.0 =
* Initial release: API integration, reCAPTCHA support, encrypted settings, form creation, and uninstall prompt.

== Upgrade Notice ==

= 1.0.1 =
First stable release of WP to Emmie Connector.

= 1.1.0 =
Bugfixes:
- A function caused unexpected behavior in the API Token field, which no longer worked correctly.
- Form mistakenly gives a 'Successful' message when entering an email address that is already in use.
- Other various small fixes

Added validation for the phone number field in the form.

Improved security in the intake form.

Improved translations.

Added logging support.

== License ==

This plugin is licensed under the GPLv2 or later.

The reCAPTCHA widget is invisible, and should only appear when the user is suspected to be a bot.
