=== hCaptcha for WordPress ===
Contributors: hcaptcha, kaggdesign
Tags: captcha, hcaptcha, recaptcha, spam, abuse
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0.0
Stable tag: 3.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enables hCaptcha.com integration with WordPress.

== Description ==

[hCaptcha](https://www.hcaptcha.com/) is a drop-in replacement for reCAPTCHA that puts user privacy first.

Need to keep out bots? hCaptcha protects privacy while offering better protection against spam and abuse. Help build a better web.

== How hCaptcha Works ==

The purpose of a CAPTCHA is to distinguish between people and machines via a challenge-response test, and thus increase the cost of spamming or otherwise abusing websites by keeping out bots.

To use this plugin, just install it and enter your sitekey and secret in the Settings -> hCaptcha menu after signing up on hCaptcha.com.

[hCaptcha Free](https://www.hcaptcha.com/) lets websites block bots and other forms of abuse via humanity challenges.

[hCaptcha Pro](https://www.hcaptcha.com/pro) goes beyond the free hCaptcha service with advanced machine learning to reduce the challenge rate, delivering high security and low friction along with more features like UI customization.

== Screenshots ==

1. Login page with hCaptcha widget
2. Login page with hCaptcha challenge
3. WooCommerce Login/Register page
4. Contact From 7 with hCaptcha
5. General settings page
6. Integrations settings page
7. Activating plugin from the Integration settings page

== Installation ==

Sign up at [hCaptcha.com](https://www.hcaptcha.com/) to get your sitekey and secret, then:

1. Install hCaptcha either via the WordPress.org plugin repository (best) or by uploading the files to your server. ([Upload instructions](https://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/))
2. Activate the hCaptcha plugin on the 'Plugins' admin page
3. Enter your site key and secret on the Settings->hCaptcha->General page
4. Enable desired Integrations on the Settings->hCaptcha->Integrations page

== Frequently Asked Questions ==

= How do I use the hCaptcha plugin? =

The hCaptcha plugin supports WordPress core and many plugins with forms automatically. You should select the supported forms on the hCaptcha Integrations settings page.

For non-standard cases, you can use the `[hcaptcha]` shortcode provided by the plugin.

For example, we support Contact Forms 7 automatically. However, sometimes a theme can modify the form. In this case, you can manually add the `[cf7-hcaptcha]` shortcode to the CF7 form.

To make hCaptcha work, the shortcode must be inside the <form ...> ... </form> tag.

= You don't support plugin X. How can I get support for it added? =

[Open a PR on GitHub](https://github.com/hCaptcha/hcaptcha-wordpress-plugin): or just email the authors of plugin X. Adding hCaptcha support is typically quite a quick task for most plugins.

= Does the [hcaptcha] shortcode have arguments? =

Full list of arguments:

`
[hcaptcha action="my_hcap_action" name="my_hcap_name" auto="true|false" size="normal|compact|invisible"]
`

The shortcode adds not only the hCaptcha div to the form, but also a nonce field. You can set your own nonce action and name. For this, use arguments in the shortcode:

`
[hcaptcha action="my_hcap_action" name="my_hcap_name"]
`

and in the verification:

`
$result = hcaptcha_request_verify( 'my_hcap_action', 'my_hcap_name' );
`

For the explanation of the auto="true|false" argument, see the section *"How to automatically verify an arbitrary form"*. By default, auto="false".

The argument size="normal|compact|invisible" allows to set the size of hCaptcha widget. size="normal" by default.

= How to add hCaptcha to an arbitrary form =

First, add the hCaptcha snippet to the form.

If you create the form as an HTML block in the post content, just insert the shortcode `[hcaptcha]` inside it. It may look like this:

`
<form method="post">
	<input type="text" name="test_input">
	<input type="submit" value="Send">
    [hcaptcha]
</form>
`

If you create the form programmatically, insert the following statement inside it:

`
?>
<form method="post">
	<input type="text" name="test_input">
	<input type="submit" value="Send">
    <?php echo do_shortcode( '[hcaptcha]' ); ?>
</form>
<?php
`

Secondly, verify the result of hCaptcha challenge.

`
$result = hcaptcha_verify_post();

if ( null !== $result ) {
    echo esc_html( $result );
    // Block processing of the form.
}
`

= How to automatically verify an arbitrary form =

Arbitrary user forms can be verified easily. Just add `auto="true"` or `auto="1"` to the shortcode:

`
[hcaptcha auto="true"]
`

and insert this shortcode into your form.

Auto-verification works with forms sent by POST on frontend only. Also, it works only with forms in the post content, but we have plans to extend the functionality.

= How to block hCaptcha on specific page? =

hCaptcha starts early, so you cannot use standard WP functions to determine the page. For instance, to block it on `my-account` page, add this code to your theme's `functions.php` file:

`
/**
* Filter hCaptcha activation flag.
*
* @param bool $activate Activate flag.
*
* @return bool
*/
function my_hcap_activate( $activate ) {
  $url = isset( $_SERVER['REQUEST_URI'] ) ?
  filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
  '';

  if ( '/my-account/' === $url ) {
    return false;
  }

  return $activate;
}

add_filter( 'hcap_activate', 'my_hcap_activate' );
`

= Skipping hCaptcha verification on a specific form =

The plugin has a filter to skip adding and verifying hCaptcha on a specific form. The filter receives three parameters: current protection status ('true' by default), source and form_id.

The source is the plugin's slug (like 'directory/main-plugin-file.php'), the theme name (like 'Avada') or the WordPress core (like 'WordPress').

The form_id is the form_id for plugins like Gravity Forms or WPForms, the post id for comments or a general name of the form when the form does not have an id (like WordPress core login form).

Filter arguments for some plugins/forms are listed below.

Back In Stock Notifier
$source: 'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php'
$form_id: product_id

BBPress
$source: 'bbpress/bbpress.php'
$form_id: 'new_topic' or 'reply'

Beaver Builder
$source: 'bb-plugin/fl-builder.php'
$form_id: 'contact' or 'login'

Brizy
$source: 'brizy/brizy.php'
$form_id: 'form'

BuddyPress
$source: 'buddypress/bp-loader.php'
$form_id: 'create_group' or 'register'

Classified Listing
$source: 'classified-listing/classified-listing.php'
$form_id: 'contact', 'login', 'lost_password' or 'register'

Divi
$source: 'Divi'
$form_id: post_id for comment form, 'contact', 'email_optin', or 'login'

Download Manager
$source: 'download-manager/download-manager.php'
$form_id: post_id of download item in the admin.

Easy Digital Downloads
$source: 'easy-digital-downloads/easy-digital-downloads.php'
$form_id: 'checkout', 'login', 'lost_password' or 'register'

Elementor Pro
$source: 'elementor-pro/elementor-pro.php'
$form_id: Form ID set for the form Content->Additional Options.

Jetpack
$source: 'jetpack/jetpack.php'
$form_id: 'contact'

Kadence Form
$source: 'kadence-blocks/kadence-blocks.php'
$form_id: post_id

Kadence Advanced Form
$source: 'kadence-blocks/kadence-blocks.php'
$form_id: form_id

LearnDash
$source: 'sfwd-lms/sfwd_lms.php'
$form_id: 'login', 'lost_password' or 'register'

MemberPress
$source: 'memberpress/memberpress.php'
$form_id: 'login' or 'register'

Paid Memberships Pro
$source: 'paid-memberships-pro/paid-memberships-pro.php'
$form_id: 'checkout' or 'login'

Passster
$source: 'content-protector/content-protector.php'
$form_id: area_id

Profile Builder
$source: 'profile-builder/index.php'
$form_id: 'login', 'lost_password' or 'register'

Subscriber
$source: 'subscriber/subscriber.php'
$form_id: 'form'

Support Candy
$source: 'supportcandy/supportcandy.php'
$form_id: 'form'

Theme My Login
$source: 'theme-my-login/theme-my-login.php'
$form_id: 'login', 'lost_password' or 'register'

Ultimate Member
$source: 'ultimate-member/ultimate-member.php'
$form_id: form_id or 'password'

UsersWP
$source: 'userswp/userswp.php'
$form_id: 'forgot', 'login' or 'register'

WooCommerce Wishlist
$source: 'woocommerce-wishlists/woocommerce-wishlists.php'
$form_id: 'form'

wpDiscuz
$source: 'wpdiscuz/class.WpdiscuzCore.php'
$form_id: post_id

WPForms
$source: 'wpforms-lite/wpforms.php' or 'wpforms/wpforms.php'
$form_id: form_id

wpForo
$source: 'wpforo/wpforo.php'
$form_id: 'new_topic' for new topic form and topicid for reply form. Topicid can be found in HTML code searching for 'data-topicid' in Elements.

Wordfence Login Security
$source: 'wordfence-login-security/wordfence-login-security.php'
$form_id: 'login'

Wordfence Security
$source: 'wordfence/wordfence.php'
$form_id: 'login'

WordPress Core
$source: 'WordPress'
$form_id: post_id for comment form, 'login', 'lost_password', 'password_protected', or 'register'

WooCommerce
$source: 'woocommerce/woocommerce.php'
$form_id: 'checkout', 'login', 'lost_password', 'order_tracking', or 'register'

Below is an example of how to skip the hCaptcha widget on a Gravity Form with id = 1.

`
/**
 * Filters the protection status of a form.
 *
 * @param string     $value   The protection status of a form.
 * @param string[]   $source  Plugin(s) serving the form.
 * @param int|string $form_id Form id.
 *
 * @return bool
 */
function hcap_protect_form_filter( $value, $source, $form_id ) {
	if ( ! in_array( 'gravityforms/gravityforms.php', $source, true ) ) {
		// The form is not sourced by Gravity Forms plugin.
		return $value;
	}

	if ( 1 !== (int) $form_id ) {
		// The form has id !== 1.
		return $value;
	}

	// Turn off protection for Gravity form with id = 1.
	return false;
}

add_filter( 'hcap_protect_form', 'hcap_protect_form_filter', 10, 3 );
`

= How to show hCaptcha widget instantly? =

The plugin loads the hCaptcha script with a delay until user interaction: mouseenter, click, scroll or touch. This significantly improves Google Pagespeed Insights score.

To load the hCaptcha widget instantly, you can use the following filter:
`
/**
* Filters delay time for hCaptcha API script.
*
* Any negative value will prevent API script from loading at all,
* until user interaction: mouseenter, click, scroll or touch.
* This significantly improves Google Pagespeed Insights score.
*
* @param int $delay Number of milliseconds to delay hCaptcha API script.
*                   Any negative value means delay until user interaction.
*/
function my_hcap_delay_api( $delay ) {
  return 0;
}

add_filter( 'hcap_delay_api', 'my_hcap_delay_api' );
`

= How to set hCaptcha language programmatically? =

hCaptcha defaults to using the user's language as reported by the browser. However, on multilingual sites you can override this to set the hCaptcha language to match the current page language. For this, you can use the following filter:

`
/**
* Filters hCaptcha language.
*
* @param string $language Language.
*/
function my_hcap_language( $language ) {
  // Detect page language and return it.
  $page_language = 'some lang'; // Detection depends on the multilingual plugin used.

  return $page_language;
}

add_filter( 'hcap_language', 'my_hcap_language' );
`

= How to whitelist certain IPs =

You can use the following filter:

`
/**
 * Filter user IP to check if it is whitelisted.
 * For whitelisted IPs, hCaptcha will not be shown.
 *
 * @param bool   $whitelisted Whether IP is whitelisted.
 * @param string $ip          IP.
 *
 * @return bool
 */
function my_hcap_whitelist_ip( $whitelisted, $ip ) {

  // Whitelist local IPs.
  if ( false === $ip ) {
    return true;
  }

  // Whitelist some other IPs.
  if ( '1.1.1.1' === $ip ) {
    return true;
  }

  return $whitelisted;
}

add_filter( 'hcap_whitelist_ip', 'my_hcap_whitelist_ip', 10, 2 );
`

= Why isn't my WPForms Lite installation working? =

Please make sure you have removed the reCAPTCHA keys under WPForms > Settings > reCAPTCHA to avoid a conflict.

= Where can I get more information about hCaptcha? =

Please see our [website](https://hcaptcha.com/).

== Privacy Notices ==

hCaptcha is designed to comply with privacy laws in every country, including GDPR, LGPD, CCPA, and more.

With the default configuration, this plugin does not:

* track users by stealth;
* write any user personal data to the database;
* send any data to external servers;
* use cookies.

Once you activate this plugin, the hCaptcha-answering user's IP address and browser data may be sent to the hCaptcha service on pages where you have activated hCaptcha protection. However, hCaptcha is designed to minimize data used, process it very close to the user, and rapidly discard it after analysis.

For more details, please see the hCaptcha privacy policy at:

* [hCaptcha.com](https://hCaptcha.com/privacy)

=== Forms and Plugins Supported ==

* Login Form
* Register Form
* Lost Password Form
* Comment Form
* Post/Page Password Form
* ACF Extended Form
* Asgaros Forum New Topic Form
* Asgaros Forum Reply Form
* Avada Form
* Back In Stock Notifier
* bbPress New Topic Form
* bbPress Reply Form
* Beaver Builder Contact Form
* Beaver Builder Login Form
* BuddyPress Create Group Form
* Buddypress Registration Form
* Classified Listing Contact Form
* Classified Listing Login Form
* Classified Listing Lost Password Form
* Classified Listing Register Form
* Contact Form 7
* Divi Comment Form
* Divi Contact Form
* Divi Email Optin Form
* Divi Login Form
* Download Manager Button
* Easy Digital Downloads Checkout Form
* Easy Digital Downloads Login Form
* Easy Digital Downloads Lost Password Form
* Easy Digital Downloads Register Form
* Elementor Pro Form
* Fluent Forms
* Forminator
* Formidable Forms
* GiveWP Form
* Gravity Forms
* Gravity Perks Nested Forms
* Jetpack Forms
* Kadence Form
* Kadence Advanced Form
* LearnDash Login Form
* LearnDash Lost Password Form
* LearnDash Register Form
* Mailchimp for WP Form
* MailPoet Form
* MemberPress Login Form
* MemberPress Register Form
* Ninja Forms
* Otter Blocks Forms
* Paid Memberships Pro Checkout Form
* Paid Memberships Pro Login Form
* Passster Protection Form
* Profile Builder Login Form
* Profile Builder Recover Password Form
* Profile Builder Register Form
* Quform Forms
* Sendinblue Form
* Simple Download Monitor Form
* Simple Basic Contact Form
* Subscriber Form
* Support Candy New Ticket Form
* Theme My Login Login Form
* Theme My Login Lost Password Form
* Theme My Login Register Form
* Ultimate Member Login Form
* Ultimate Member Lost Password Form
* Ultimate Member Register Form
* UsersWP Forgot Password Form
* UsersWP Login Form
* UsersWP Register Form
* WooCommerce Login Form
* WooCommerce Registration Form
* WooCommerce Lost Password Form
* WooCommerce Checkout Form
* WooCommerce Order Tracking Form
* WooCommerce Wishlist
* Wordfence Security Login Form
* Wordfence Login Security Login Form
* WP Job Openings Form
* WPForms Form
* wpDiscuz Comment Form
* wpDiscuz Support Form
* wpForo New Topic Form
* wpForo Reply Form

=== Please note ===

**NOTE:** This is a community-developed plugin. Your PRs are welcome.

For feature requests and issue reports, please
[open a pull request](https://github.com/hCaptcha/hcaptcha-wordpress-plugin).

We also suggest emailing the authors of plugins you'd like to support hCaptcha: it will usually take them only an hour or two to add native support. This will simplify your use of hCaptcha, and is the best solution in the long run.

Some plugins listed have been superseded by native support, and are included only for legacy purposes.

You should always use native hCaptcha support if available for your plugin.
Please check with your plugin author if native support is not yet available.

Instructions for popular native integrations are below:

* [WPForms native integration: instructions to enable hCaptcha](https://wpforms.com/docs/how-to-set-up-and-use-hcaptcha-in-wpforms)

== Changelog ==

= 3.4.0 =
* Tested with WordPress 6.4.
* Tested with WooCommerce 8.2.
* Added MailPoet integration.
* Added Simple Download Monitor integration.
* Added WP Job Openings integration.
* Added Simple Basic Contact Form integration.
* Added Easy Digital Downloads Login Form integration.
* Added Easy Digital Downloads Lost Password Form integration.
* Added Easy Digital Downloads Register Form integration.
* Added purging of old failed login data to keep the `hcaptcha_login_data` option size small.
* Fixed compatibility with HPOS in WooCommerce.
* Fixed fatal error caused by broken backward compatibility in the Ultimate Member 2.7.0.
* Fixed SystemInfo on multisite.
* Fixed missing dependency of WooCommerce checkout script.
* Fixed fatal error occurring during login under some conditions.
* Fixed the inability to send the Divi Contact Form when Divi Email Optin was active.

= 3.3.3 =
* Added compatibility with LearnDash.
* Added requirement to check the site config after changes in credentials.
* Added filter `hcap_login_limit_exceeded`, allowing to filter the login limit exceeded status.
* Changed Brevo (formerly Sendinblue) logo.
* Fixed activation of hCaptcha with empty keys.
* Fixed autocomplete of the Site Key field by LastPass.
* Fixed form detection for Auto-Verify.
* Fixed Brevo form working in the post content only.
* Fixed hCaptcha not loading correctly for Brevo form.
* Fixed Passster form working in the post content only.
* Fixed LearnDash form working in the post content only.
* Fixed auto-verify form not working on the homepage.

= 3.3.2 =
* Improved Beaver Builder login sequence.
* Improved Classified Listing login sequence.
* Improved Divi login sequence.
* Improved MemberPress login sequence.
* Improved Paid Membership Pro login sequence.
* Improved Profile Builder login sequence.
* Improved Ultimate Member login sequence.
* Improved Wordfence login sequence.
* Improved native WordPress login sequence.
* Fixed login error when WP Login form option was 'on' and WC Login form option was 'off'.
* Fixed compatibility with WPS Hide Login.
* Fixed compatibility with All-In-One Security.
* Fixed compatibility with Rename wp-admin Login.

= 3.3.0 =
* Color scheme in admin UI has been updated.
* Added compatibility with Passster.
* Added compatibility with Theme My Login.
* Added compatibility with Gravity Perks Nested Forms.
* Added compatibility with Wordfence Login Security.
* Added compatibility with Wordfence Security.
* Added compatibility with UsersWP.
* Added compatibility with Kadence Advanced Form.
* Improved support for Kadence simple form.
* Replaced deprecated ajaxStop events.
* Fixed error on Classified Listing Login form.
* Fixed admin page title.

= 3.2.0 =
* Tested with WooCommerce 8.0.
* Added ability to use hCaptcha field provided by the Fluent Forms plugin.
* Added ability to use hCaptcha field provided by the Forminator plugin.
* Added ability to use hCaptcha field provided by the Quform plugin.
* Added hCaptcha reset to allow sending Elementor form several times without reloading the page.
* Added hCaptcha reset to allow sending Forminator form several times without reloading the page.
* Added hCaptcha reset to allow sending Quform form several times without reloading the page.
* Blocked hCaptcha settings on Fluent Forms admin pages with a notice having a link to the hCaptcha plugin General settings page.
* Blocked hCaptcha settings on Forminator admin pages with a notice having a link to the hCaptcha plugin General settings page.
* Blocked hCaptcha settings on Quform admin pages with a notice having a link to the hCaptcha plugin General settings page.
* Fixed Fluent Forms submit error.
* Fixed positioning of hCaptcha in Fluent Form.
* Fixed deprecation errors in debug.log that occurred with Fluent Forms.
* Fixed Forminator form display error.
* Fixed dynamic display of settings in sample hCaptcha.

= 3.1.0 =
* Added notification system.
* Fixed mode selection for sample hCaptcha on the General settings page.

= 3.0.1 =
* Fixed error on Contact Form 7 validation.
* Fixed checkboxes disabled status after activation of a plugin on the Integrations page.

= 3.0.0 =
* Dropped support for PHP 5.6. Minimum required PHP version is now 7.0.
* Tested with WordPress 6.3.
* Tested with WooCommerce 7.9.
* Added hCaptcha config check to the General settings page.
* Added dynamic display of settings in sample hCaptcha.
* Added compatibility with Ajax Gravity Forms.
* Added compatibility with Profile Builder.
* Added compatibility with Easy Digital Downloads Checkout form.

= 2.10.0 =
* Added compatibility with Paid Memberships Pro.
* Added compatibility with Classified Listing.
* Added compatibility with Formidable Forms.
* Added compatibility with wpDiscuz Subscribe Form.
* Added System Info tab.
* Added Back In Stock Notifier support in the popup window.
* Added support to turn off hCaptcha on a specific Jetpack form.
* Added support to turn off hCaptcha on a specific Kadence form.
* Added support to turn off hCaptcha on a specific Mailchimp form.
* Added support to turn off hCaptcha on a specific MemberPress form.
* Added support to turn off hCaptcha on a specific Ninja form.
* Added support to turn off hCaptcha on a specific Quform form.
* Added support to turn off hCaptcha on a specific Sendinblue form.
* Added support to turn off hCaptcha on a specific Subscriber form.
* Added support to turn off hCaptcha on a specific Support Candy form.
* Added support to turn off hCaptcha on a specific Ultimate Member form.
* Added support to turn off hCaptcha on a specific WooCommerce Wishlist form.
* Added support to turn off hCaptcha on a specific wpDiscuz form.
* Added support to turn off hCaptcha on a specific wpForms form.
* Fixed messages dynamic styling on Integrations page.

= 2.9.0 =
* Added compatibility with Back In Stock Notifier.
* Added compatibility with Colorlib Login Customizer.
* Added compatibility with Divi Email Optin Form.
* Added visualisation of plugins available for activation and deactivation from Integrations page.
* Added support to turn off hCaptcha on a specific ACF Extended form.
* Added support to turn off hCaptcha on a specific Asgaros forum form.
* Added support to turn off hCaptcha on a specific Avada form.
* Added support to turn off hCaptcha on a specific BBPress form.
* Added support to turn off hCaptcha on a specific Beaver Builder form.
* Added support to turn off hCaptcha on a specific Brizy form.
* Added support to turn off hCaptcha on a specific Buddy Press form.
* Added support to turn off hCaptcha on a specific Contact 7 form.
* Added support to turn off hCaptcha on a specific Download Manager form.
* Added support to turn off hCaptcha on a specific Elementor form.
* Added support to turn off hCaptcha on a specific Fluent form.
* Added support to turn off hCaptcha on a specific Forminator form.
* Added support to turn off hCaptcha on a specific GiveWP form.
* Fixed ignoring hCaptcha on comments with Akismet.
* Fixed interfering of WordPress and WooCommerce lost password settings.
* Fixed Divi login issue.
* Fixed Download Manager issue caused by the plugin update.
* Fixed Elementor issue caused by the plugin update.
* Fixed GiveWP issue caused by the plugin update.

= 2.8.0 =
* Tested with WooCommerce 7.7.
* Added general ability to turn off hCaptcha on a specific form.
* Added filter `hcap_protect_form`, allowing to filter the protection status of a specific form.
* Added support to turn off hCaptcha on a specific WordPress Core form.
* Added support to turn off hCaptcha on a specific WooCommerce form.
* Added support to turn off hCaptcha on a specific Gravity Form.
* Added support to turn off hCaptcha on a specific Divi form.
* Fixed error processing during plugin activation.
* Fixed issue with invisible hCaptcha in Fluent Forms.
* Fixed multiple issues related to Fluent Forms.
* Fixed login issue with invisible hCaptcha on WooCommerce /my-account page.
* Fixed Divi login form.

= 2.7.0 =
* Tested with WooCommerce 7.5.
* Added size argument to the shortcode.
* Added compatibility with 3rd-party login plugins.
* Added autocomplete="off" attribute to the Secret Key field to prevent its autocompleting by the browser.
* Added 'hcap_error_messages' filter allowing to modify hCaptcha error messages.
* Changed position of hCaptcha widget on WooCommerce Place Order button.
* Fixed uncaught type error during the login with PHP 8.0.

= 2.6.0 =
* Tested with WordPress 6.2.
* Tested with WooCommerce 7.4.
* Added compatibility with Asgaros Forum.
* Added compatibility with Support Candy.
* Added Login Form support for MemberPress.
* Added compatibility with GiveWP.
* Added compatibility with Brizy.
* Added activation and deactivation of plugins from the Integrations admin page.
* Fixed error during login with WordPress < 5.4.

= 2.5.1 =
* Fixed fatal error with WordPress < 6.1.

= 2.5.0 =
* Tested with WooCommerce 7.3.
* Added ability to use the HTMl tag '<button type="submit">Submit</button>' in the Contact Form 7.
* Added compatibility with ACF Extended Pro Form.
* Added login attempts limit to Beaver Builder login form.
* Added login attempts limit to Divi login form.
* Added login attempts limit to Ultimate Member login form.
* Added login attempts limit to WooCommerce login form.
* Added optimisation of autoloading to boost performance.
* Added block of launching recaptcha scripts by wpDiscuz.
* Fixed showing the hCaptcha widget on wpForo community page.
* Fixed PHP notice on the General settings page.
* Fixed bug with number of login attempts before showing the hCaptcha.

= 2.4.0 =
* Tested with PHP 8.2.
* Plugin now requires WP 5.0.
* Added script loading delay time setting.
* Added compatibility with Otter Blocks Forms.
* Added compatibility with ACF Extended Form.
* Added compatibility with Kadence Form.
* Added compatibility with wpDiscuz.
* Added ability to show hCaptcha after certain number of failed logins.
* Fixed hCaptcha placement in Avada form.

= 2.3.0 =
* Tested with WooCommerce 7.2.
* Added compatibility with WC High-Performance order storage (COT) feature.
* Added compatibility with Contact Form 7 v5.7.

= 2.2.0 =
* Added Avada theme support.
* Added Beaver Builder support.
* Added compatibility with Wordfence login security.
* Improved spam protection with Contact Form 7.
* Fixed fatal error in standard login form with Ultimate Member active.
* Fixed fatal error with Jetpack sync.

= 2.1.0 =
* Tested with WooCommerce 7.1.
* Added Forminator support.
* Added Quform support.
* Added Sendinblue support.
* Added Download Manager support.
* Added support for password protected post/page.
* Added actual messages from hcaptcha.com.
* Added support for Multipage Gravity Form.
* Fixed error messaging in Ninja Forms.
* Fixed 'hcaptcha is not defined' issue with Elementor.

= 2.0.0 =
* Tested with WordPress 6.1.
* Tested with WooCommerce 7.0.
* Added Settings page with multiple tabs.
* Added setting for whitelisted IPs.
* Added ability to set options network-wide on multisite.
* Fixed Divi contact form bug related to recaptcha compat.
* Fixed bug with WC Wishlist create list form.
* Fixed styles on WordPress Register page.
* Fixed shifting of hCaptcha layout during load.
* Fixed Contact Form hcaptcha invalidation messages.

= 1.19.0 =
* Fixed grey left sidebar issue on Elementor edit page.

= 1.18.0 =
* Tested with WooCommerce 6.8.
* Added Divi Comment Form support.
* Fixed WPForms Login form support.
* Fixed not valid CSS to prevent a black box issue.
* Fixed invalid hCaptcha error after correction of wrong input on Checkout page.
* Fixed hCaptcha functionality on Elementor Pro edit page when hCaptcha is off for logged-in users.

= 1.17.0 =
* Tested with WooCommerce 6.6.
* Added support for Ultimate Member plugin (Login, Register, LostPassword forms).
* Fixed weird black bordered rectangle to the left of hCaptcha challenge.

= 1.16.0 =
* Tested with WordPress 6.0.
* Tested with WooCommerce 6.5.

= 1.15.0 =
* Tested with WooCommerce 6.4.
* Added Gravity Forms support.
* Added filter to whitelist IPs.
* Added support for multiple Ninja forms on a single page.

= 1.14.0 =
* Tested with WooCommerce 6.2.
* Added support for PHP 8.1.
* Added support for Divi Login form.
* Added hCaptcha language filter.
* Changed nonce verification. Now nonce is verified for logged-in users only.

= 1.13.4 =
* Tested with WooCommerce 6.1.
* Added support for hCaptcha in Elementor Popup.
* Fixed WooCommerce login when hCaptcha for WP login is active.
* Fixed issue with Safari version < 14.

= 1.13.3 =
* Tested with WodPress 5.9 and WooCommerce 6.0.
* Added support for WP Fluent Forms.
* Fixed regex for non-standard Order Tracking form.

= 1.13.2 =
* Added support for non-standard WC Order Tracking form.
* Fixed fatal error with Elementor Pro 3.5.

= 1.13.1 =
* Fixed Divi Contact form in frontend builder.
* Fixed WooCommerce login form.
* Fixed css and js to pass W3C validation.
* Fixed issue with Safari and invisible hCaptcha on auto-verify form.
* Fixed issue with login via XML-RPC.

= 1.13.0 =
* Added support for Divi Contact form.
* Added support for Elementor Pro form.
* Added support for MemberPress Register form.
* Added support for WooCommerce Order Tracking form.
* Fixed layout on the WP login form.
* Fixed issue with insertion of hCaptcha not only to Jetpack forms.
* Fixed regex bug in auto verify feature, which prevented registering of forms.

= 1.12.0 =
* Added Invisible hCaptcha feature.
* Added delayed rendering of hCaptcha to improve Google PageSpeed Insights score.
* hCaptcha moved inside of Jetpack block form, before submit button.
* Fixed fatal error with Divi theme.
* Fixed - only 1 Contact Form 7 was working on the page.
* Nonce is now checked with Contact Form 7.

= 1.11.0 =
* Added auto-verification of an arbitrary form.

= 1.10.3 =
* Fixed issue with Ninja Forms - hCaptcha is not shown.
* Tested with WordPress 5.8 and WooCommerce 5.5

= 1.10.2 =
* Fixed issue with CF7 - hCaptcha is not shown.

= 1.10.0 =
* Fixed issue with WC login form when WP login form option is on.
* Added feature to turn off the plugin for logged-in users.
* Added hook to disable the plugin on specific pages.
* Added feature to run hCaptcha script and styles on pages where it is used only.

= 1.9.2 =
* Fixed issue with WooCommerce on my-account page - hCaptcha was requested even if solved properly.

= 1.9.1 =
* Fixed issue with Contact Form 7 - reset hCaptcha widget when form is not validated.

= 1.9.0 =
* Tested with WordPress 5.7 and WooCommerce 5.0

= 1.8.0 =
* Added option to disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)

= 1.7.0 =
* 100% covered by WordPress integration tests.
* Tests run on CI with PHP 5.6 - 8.0, the latest WordPress core and latest related plugins.

= 1.6.4 =
* Make any Jetpack contact form working with Block Editor
* Tested with WooCommerce 4.7

= 1.6.3 =
* Don't require challenge for admin comment reply

= 1.6.2 =
* WPForms Pro support

= 1.6.1 =
* WPCS coding standards and docs update

= 1.6.0 =
* Tested with WordPress 5.5 and WooCommerce 4.4

= 1.5.4 =
* Added WPForms Lite support

= 1.5.3 =
* WooCommerce Wishlists bug fix
* text domain updated: better i18n support

= 1.5.2 =
* CF7 bug fix: enforce validation

= 1.5.1 =
* Update docs

= 1.5.0 =
* Refactor to improve code hygiene, fixes for latest Ninja Forms.

= 1.4.2 =
* Fixed comment issue, added WooCommerce Wishlists

= 1.4.1 =
* Updated testing information, improve docs.

= 1.3 =
* Automatic addition of hCaptcha button to Contact Form 7 forms when enabled.

= 1.2 =
* Update to Contact Form 7 support. Adds compatibility for version 5.1.3.

= 1.1 =
* Minor bugfixes

= 1.0 =
* Plugin Created
