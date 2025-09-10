=== hCaptcha for WP ===
Contributors: hcaptcha, kaggdesign
Tags: captcha, hcaptcha, antispam, abuse, protect
Requires at least: 5.3
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 4.17.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The strongest CAPTCHA. Switch from reCAPTCHA, Turnstile, etc. for free.
Integrates with 60+ popular plugins and themes.

== Description ==

The strongest CAPTCHA. Switch from reCAPTCHA, Turnstile, etc. for free.

[hCaptcha](https://www.hcaptcha.com/) is a drop-in replacement for reCAPTCHA that puts user privacy first.

Need to keep out bots? hCaptcha protects privacy while offering better protection against spam and abuse. Help build a better web.

hCaptcha for WP [makes security easy](https://www.hcaptcha.com/integration-hcaptcha-for-wp) with broad integration support, detailed analytics, and strong protection. Start protecting logins, forms, and more in minutes.

== Benefits ==

* **Privacy First:** hCaptcha is designed to protect user privacy. It doesn't retain or sell personal data, unlike platforms that **g**ather, **o**wn, and m**o**netize **gl**obal b**e**havior.
* **Better Security:** hCaptcha offers better protection against bots and abuse than other anti-abuse systems.
* **Easy to Use:** hCaptcha is easy to install and use with WordPress and popular plugins.
* **Broad Integration:** hCaptcha works with WordPress Core, WooCommerce, Contact Form 7, Elementor, and over 50 other plugins and themes.

== Features ==

**Highlights**

* **Detailed Analytics:** Get detailed analytics on hCaptcha events and form submissions.
* **Pro and Enterprise:** Supports Pro and Enterprise versions of hCaptcha.
* **No Challenge Modes:** 99.9% passive and passive modes in Pro and Enterprise versions reduce user friction.
* **Protect Site Content:** Protects selected site URLs from bots with hCaptcha. Works best with Pro 99.9% passive mode.
* **Logged-in Users:** Optionally turn off hCaptcha for logged-in users.
* **Delayed API Loading:** Load the hCaptcha API instantly or on user interaction for zero page loading impact.
* **Allowlist IPs:** Allowlist certain IPs to skip hCaptcha verification.
* **Multisite Support:** Sync hCaptcha settings across a Multisite Network.

**Customization**

* **Language Support:** Supports multiple languages.
* **Custom Themes:** Customize the appearance of hCaptcha to match your site.
* **Custom Themes Editor:** Edit custom themes directly in the plugin.
* **Login Compatibility:** Compatible with all major hide login, custom login, and 2FA login plugins.
* **Login Attempts:** Protect your site from brute force attacks.

**Ease of Use**

* **Test Modes:** Use hCaptcha in live and Pro/Enterprise test modes.
* **Activation and Deactivation:** Activate and deactivate plugins and themes with hCaptcha in one click.
* **Forced Verification:** Optionally force hCaptcha verification before form submission.
* **Check Config:** Check hCaptcha configuration before saving keys and settings.
* **Auto-Verification:** Automatically verify custom forms.
* **Standard Sizes and Themes:** Choose the size and theme of the hCaptcha widget.

== How hCaptcha Works ==

The purpose of a CAPTCHA is to distinguish between people and machines via a challenge-response test and thus increase the cost of spamming or otherwise abusing websites by keeping out bots.

To use this plugin, install it and enter your sitekey and secret in the Settings → hCaptcha menu after signing up on hCaptcha.com.

[hCaptcha Free](https://www.hcaptcha.com/) lets websites block bots and other forms of abuse via humanity challenges.

[hCaptcha Pro](https://www.hcaptcha.com/pro) goes beyond the free hCaptcha service with advanced machine learning to reduce the challenge rate, delivering high security and low friction along with more features like UI customization.

[hCaptcha Enterprise](https://www.hcaptcha.com/) delivers a complete advanced security platform, including site-specific risk scores, fraud protection, and more to address both human and automated abuse.

== Screenshots ==

1. Login page with hCaptcha widget.
2. Login page with hCaptcha challenge.
3. Protected content.
4. WooCommerce Login/Register page.
5. Contact Form 7 with hCaptcha.
6. Contact Form 7 live form in the admin editor.
7. Elementor Pro Form.
8. Elementor Pro Form in admin editor.
9. General settings page.
10. Integrations' settings page.
11. Activating plugin from the Integration settings page.
12. (Optional) Local Forms statistics.
13. (Optional) Local Events statistics.

== Installation ==

Sign up at [hCaptcha.com](https://www.hcaptcha.com/) to get your sitekey and secret, then:

1. Install hCaptcha either via the WordPress.org plugin repository (best) or by uploading the files to your server. ([Upload instructions](https://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/))
2. Activate the hCaptcha plugin on the Plugins admin page
3. Enter your site key and secret on the Settings→hCaptcha→General page
4. Enable desired Integrations on the Settings→hCaptcha→Integrations page

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
[hcaptcha action="my_hcap_action" name="my_hcap_name" auto="true|false" ajax="true|false" force="true|false" theme="light|dark|auto" size="normal|compact|invisible"]
`

The shortcode adds not only the hCaptcha div to the form but also a nonce field. You can set your own nonce action and name. For this, use arguments in the shortcode:

`
[hcaptcha action="my_hcap_action" name="my_hcap_name"]
`

and in the verification:

`
$result = \HCaptcha\Helpers\API::verify_post( 'my_hcap_name', 'my_hcap_action' );
`

For the explanation of the auto="true|false" argument, see the section *"How to automatically verify an arbitrary form"*. By default, `auto="false"`.

The argument force="true|false" allows forcing verification of hCaptcha widget before submitting the form. By default, `force="false"`.

The argument size="normal|compact|invisible" allows setting the size of hCaptcha widget. By default, `size="normal"`.

= How to add hCaptcha to an arbitrary form =

First, add the hCaptcha snippet to the form.

If you create the form as an HTML block in the post content, insert the shortcode `[hcaptcha]` inside it. It may look like this:

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
$result = \HCaptcha\Helpers\API::verify_request();

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

Auto-verification works with forms sent by POST on frontend only. It works with forms in the post content and in widgets.

You can add also `force="true"` or `force="1"` argument to prevent sending a form without checking the hCaptcha.

`
[hcaptcha auto="true" force="true"]
`

Arbitrary forms can also be verified in ajax via the `ajax` argument. There is no need to specify `auto="true"` in this case, as `ajax` implies `auto="true"`.

`
[hcaptcha ajax="true"]
`

= How to block hCaptcha entirely on a specific page? =

hCaptcha starts early, so you cannot use standard WP functions to determine the page. For instance, to block it on `my-account` page, add the following code to your plugin's (or mu-plugin's) main file. This code won't work being added to a theme's functions.php file.

`
/**
 * Filter hCaptcha activation flag.
 *
 * @param bool|mixed $activate The activate flag.
 *
 * @return bool
 */
function my_hcap_activate( $activate ): bool {
  $status = (bool) $status;

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

= How do I block hCaptcha scripts everywhere except on a specific page? =

As an example, to block hCaptcha scripts everywhere except on the `contact` page:

`
/**
 * Filter hCaptcha print hCaptcha scripts status.
 *
 * @param bool|mixed $status Current print status.
 *
 * @return bool
 */
function my_hcap_print_hcaptcha_scripts( $status ): bool {
  if ( is_page( 'contact' ) ) {
    return (bool) $status;
  }

  return false;
}

add_filter( 'hcap_print_hcaptcha_scripts', 'my_hcap_print_hcaptcha_scripts' );
`

= How do I block hCaptcha scripts everywhere except on a specific page? =

As an example, to block hCaptcha scripts everywhere except on the `contact` page:

`
/**
 * Block inline styles.
 *
 * @return void
 */
function hcap_block_inline_styles() {
	if ( is_page( 'contact' ) ) {
		return;
	}

	$hcaptcha = hcaptcha();

	remove_action( 'wp_head', [ $hcaptcha, 'print_inline_styles' ] );
	remove_filter( 'wp_resource_hints', [ $hcaptcha, 'prefetch_hcaptcha_dns' ] );
}

add_action( 'wp_head', 'hcap_block_inline_styles', 0 );
`

= Skipping hCaptcha verification on a specific form =

The plugin has a filter to skip adding and verifying hCaptcha on a specific form. The filter receives three parameters: current protection status ('true' by default), source and form_id.

The source is the plugin's slug (like 'directory/main-plugin-file.php'), the theme name (like 'Avada') or the WordPress core (like 'WordPress').

The form_id is the form_id for plugins like Gravity Forms or WPForms, the post id for comments, or a general name of the form when the form does not have an id (like WordPress core login form).

Filter arguments for some plugins/forms are listed below.

Affiliates
`$source: 'affiliates/affiliates.php'`
`$form_id: 'login' or 'register'`

Back In Stock Notifier
`$source: 'back-in-stock-notifier-for-woocommerce/cwginstocknotifier.php'`
`$form_id: product_id`

BBPress
`$source: 'bbpress/bbpress.php'`
`$form_id: 'new_topic', 'reply', 'login', 'register' or 'lost_password'`

Beaver Builder
`$source: 'bb-plugin/fl-builder.php'`
`$form_id: 'contact' or 'login'`

Blocksy
`$source: 'blocksy'`
`$form_id: 'newsletter-subscribe', '$layer["__id"]', or 'product_id`

Brizy
`$source: 'brizy/brizy.php'`
`$form_id: 'form'`

BuddyPress
`$source: 'buddypress/bp-loader.php'`
`$form_id: 'create_group' or 'register'`

Classified Listing
`$source: 'classified-listing/classified-listing.php'`
`$form_id: 'contact', 'login', 'lost_password' or 'register'`

Divi
`$source: 'Divi'`
`$form_id: post_id for comment form, 'contact', 'email_optin', or 'login'`

Download Manager
`$source: 'download-manager/download-manager.php'`
`$form_id: post_id of download item in the admin`

Easy Digital Downloads
`$source: 'easy-digital-downloads/easy-digital-downloads.php'`
`$form_id: 'checkout', 'login', 'lost_password' or 'register'`

Elementor Pro
`$source: 'elementor-pro/elementor-pro.php'`
`$form_id: Form ID set for the form Content->Additional Options or 'login'`

Events Manager
`$source: 'events-manager/events-manager.php'`
`$form_id: event_id`

Icegram Express
`$source: 'email-subscribers/email-subscribers.php'`
`$form_id: form_id`

Customer Reviews for WooCommerce
`$source: 'customer-reviews-woocommerce/ivole.php'`
`$form_id: review or q&a`

Jetpack
`$source: 'jetpack/jetpack.php'`
`$form_id: 'contact_$form_hash'`

Kadence Form
`$source: 'kadence-blocks/kadence-blocks.php'`
`$form_id: post_id`

Kadence Advanced Form
`$source: 'kadence-blocks/kadence-blocks.php'`
`$form_id: form_id`

LearnDash
`$source: 'sfwd-lms/sfwd_lms.php'`
`$form_id: 'login', 'lost_password' or 'register'`

LearnPress
`$source: 'learnpress/learnpress.php'`
`$form_id: 'checkout', ''login', or 'register'`

Login/Signup Popup
`$source: 'easy-login-woocommerce/xoo-el-main.php'`
`$form_id: 'login', or 'register'`

MemberPress
`$source: 'memberpress/memberpress.php'`
`$form_id: 'login' or 'register'`

Paid Memberships Pro
`$source: 'paid-memberships-pro/paid-memberships-pro.php'`
`$form_id: 'checkout' or 'login'`

Passster
`$source: 'content-protector/content-protector.php'`
`$form_id: area_id`

Password Protected
`$source: 'password-protected/password-protected.php'`
`$form_id: 'protect'`

Profile Builder
`$source: 'profile-builder/index.php'`
`$form_id: 'login', 'lost_password' or 'register'`

Simple Membership
`$source: 'simple-membership/simple-wp-membership.php'`
`$form_id: 'login', 'lost_password' or 'register'`

Subscriber
`$source: 'subscriber/subscriber.php'`
`$form_id: 'form'`

Support Candy
`$source: 'supportcandy/supportcandy.php'`
`$form_id: 'form'`

Theme My Login
`$source: 'theme-my-login/theme-my-login.php'`
`$form_id: 'login', 'lost_password' or 'register'`

Tutor LMS
`$source: 'tutor/tutor.php'`
`$form_id: 'checkout', ''login', 'lost_password' or 'register'`

Ultimate Addons
`$source: 'ultimate-elementor/ultimate-elementor.php'`
`$form_id: 'login' or 'register'`

Ultimate Member
`$source: 'ultimate-member/ultimate-member.php'`
`$form_id: form_id or 'password'`

UsersWP
`$source: 'userswp/userswp.php'`
`$form_id: 'forgot', 'login' or 'register'`

WooCommerce Germanized
`$source: 'woocommerce-germanized/woocommerce-germanized.php'`
`$form_id: 'return_request'`

WooCommerce Wishlist
`$source: 'woocommerce-wishlists/woocommerce-wishlists.php'`
`$form_id: 'form'`

wpDiscuz
`$source: 'wpdiscuz/class.WpdiscuzCore.php'`
`$form_id: post_id`

WPForms
`$source: 'wpforms-lite/wpforms.php' or 'wpforms/wpforms.php'`
`$form_id: form_id`

wpForo
`$source: 'wpforo/wpforo.php'`
`$form_id: 'new_topic' for a new topic form and topicid for a reply form. Topicid can be found in HTML code searching for 'data-topicid' in Elements.`

Wordfence Login Security
`$source: 'wordfence-login-security/wordfence-login-security.php'`
`$form_id: 'login'`

Wordfence Security
`$source: 'wordfence/wordfence.php'`
`$form_id: 'login'`

WordPress Core
`$source: 'WordPress'`
`$form_id: post_id for comment form, 'login', 'lost_password', 'password_protected', or 'register'`

WooCommerce
`$source: 'woocommerce/woocommerce.php'`
`$form_id: 'checkout', 'login', 'lost_password', 'order_tracking', or 'register'`

Below is an example of how to skip the hCaptcha widget on a Gravity Form with id = 1.

`
/**
 * Filters the protection status of a form.
 *
 * @param string|mixed $value   The protection status of a form.
 * @param string[]     $source  Plugin(s) serving the form.
 * @param int|string   $form_id Form id.
 *
 * @return bool
 */
function hcap_protect_form_filter( $value, $source, $form_id ): bool {
  $value = (bool) $value;

  if ( ! in_array( 'gravityforms/gravityforms.php', $source, true ) ) {
    // The form is not sourced by Gravity Forms plugin.
    return $value;
  }

  if ( 1 !== (int) $form_id ) {
    // The form has id !== 1.
    return $value;
  }

  // Turn off protection for a Gravity form with id = 1.
  return false;
}

add_filter( 'hcap_protect_form', 'hcap_protect_form_filter', 10, 3 );
`

= How can I show the hCaptcha widget instantly? =

The plugin loads the hCaptcha script with a delay until user interaction: mouseenter, click, scroll, or touch. This significantly improves Google Pagespeed Insights score.

To load the hCaptcha widget instantly, you can use the following filter:

`
/**
 * Filters delay time for hCaptcha API script.
 *
 * Any negative value will prevent the API script from loading at all,
 * until user interaction: mouseenter, click, scroll, or touch.
 * This significantly improves Google Pagespeed Insights score.
 *
 * @param int|mixed $delay Number of milliseconds to delay hCaptcha API script.
 *                         Any negative value means delay until user interaction.
 */
function my_hcap_delay_api( $delay ): int {
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
 * @param string|mixed $language Language.
 */
function my_hcap_language( $language ): string {
  $language = (string) $language;

  // Detect page language and return it.
  $page_language = 'some lang'; // Detection depends on the multilingual plugin used.

  return $page_language;
}

add_filter( 'hcap_language', 'my_hcap_language' );
`

= How to denylist certain IPs =

You can use the following filter. It should be added to your plugin's (or mu-plugin's) main file. This filter won't work being added to a theme's functions.php file.

`
/**
 * Filter the user IP to check if it is denylisted.
 * For denylisted IPs, any form submission fails.
 *
 * @param bool|mixed $denylisted Whether IP is denylisted.
 * @param string     $ip         IP.
 *
 * @return bool
 */
function my_hcap_denylist_ip( $denylisted, $ip ): bool {
  $denylisted = (bool) $denylisted;

  // Denylist some IPs.
  if ( '8.8.8.8' === $ip ) {
    return true;
  }

  return $denylisted;
}

add_filter( 'hcap_blacklist_ip', 'my_hcap_denylist_ip', 10, 2 );
`

= How to allowlist certain IPs =

You can use the following filter. It should be added to your plugin's (or mu-plugin's) main file. This filter won't work being added to a theme's functions.php file.

`
/**
 * Filter user IP to check if it is allowlisted.
 * For allowlisted IPs, hCaptcha will not be shown.
 *
 * @param bool|mixed $allowlisted Whether IP is allowlisted.
 * @param string     $ip          IP.
 *
 * @return bool
 */
function my_hcap_allowlist_ip( $allowlisted, $ip ): bool {
  $allowlisted = (bool) $allowlisted;

  // Allowlist local IPs.
  if ( false === $ip ) {
    return true;
  }

  // Allowlist some other IPs.
  if ( '1.1.1.1' === $ip ) {
    return true;
  }

  return $allowlisted;
}

add_filter( 'hcap_whitelist_ip', 'my_hcap_allowlist_ip', 10, 2 );
`

= How do I change the appearance of the admin menu? =

Starting from 4.1.0, the admin menu was moved to the top level with subpages.

You can customize this by returning it to the previous location in the admin Settings section or tweaking its appearance.

To do this, use the following filter to your plugin's (or mu-plugin's) main file. This code won't work being added to a theme's functions.php file.

`
/**
 * Filter the settings system initialization arguments.
 *
 * @param array|mixed $args Settings system initialization arguments.
 */
function hcap_settings_init_args_filter( $args ): array {
  $args = (array) $args;

  $args['mode'] = 'tabs';

  return $args;
}

add_filter( 'hcap_settings_init_args', 'hcap_settings_init_args_filter' );
`

`$args` array has the following fields:

`mode`: 'pages' or 'tabs' (default 'pages') — the appearance of the admin menu;
`parent`: a string — the parent menu item. Default '' for 'pages' mode and 'options-general.php' for 'tabs' mode;
`position`: a number — the position of the menu item. Default 58.990225 for 'pages' mode. It Has no effect on 'tabs' mode;

= Where can I get more information about hCaptcha? =

Please see our [website](https://hcaptcha.com/).

== Privacy Notices ==

hCaptcha is designed to comply with privacy laws in every country, including GDPR, LGPD, CCPA, and more.

For example, hCaptcha has been certified under ISO 27001 and 27701 and is enrolled in the EU-US, UK-US, and Swiss-US Data Privacy Framework for GDPR compliance.

Details are available at [www.hcaptcha.com/certifications](https://www.hcaptcha.com/certifications) and [www.hcaptcha.com/gdpr](https://www.hcaptcha.com/gdpr).

With the default configuration, this plugin does not:

* track users by stealth;
* write any user's personal data to the database;
* send any data to external servers;
* use cookies.

Once you activate this plugin, the hCaptcha-answering user's IP address and browser data may be sent to the hCaptcha service on pages where you have activated hCaptcha protection. However, hCaptcha is designed to minimize data used, process it very close to the user, and rapidly discard it after analysis.
For more details, please see the hCaptcha privacy policy at:

* [hCaptcha.com](https://hCaptcha.com/privacy)

If you enable the optional plugin-local statistics feature, the following additional data will be recorded in your database:

* counts of challenge verifications per form
* **only if you enable this optional feature: **the IP address challenged on each form
* **only if you enable this optional feature: **the USer Agent challenged on each form

You can collect data anonymously but still distinguish sources. The hashed IP address and User Agent will be saved.

We recommend leaving IP and User Agent recording off, which will make these statistics fully anonymous.

If this feature is enabled, anonymized statistics on your plugin configuration, not including any end user data, will also be sent to us. This lets us see which modules and features are being used and prioritize development for them accordingly.

=== Plugins, Themes and Forms Supported ==

* WordPress Login, Register, Lost Password, Comment, and Post/Page Password Forms
* ACF Extended Form
* Affiliates Login and Register Forms
* Asgaros Forum New Topic and Reply Form
* Avada standard and multistep Forms
* Back In Stock Notifier Form
* bbPress New Topic, Reply, Login, Register, and Lost Password Forms
* Beaver Builder Contact and Login Forms
* Blocksy Companion Newsletter Subscribe, Waitlist, and Product Review Forms
* BuddyPress — Create Group and Registration Forms
* Classified Listing Contact, Login, Lost Password, and Listing Register Forms
* CoBlocks Form
* Colorlib Customizer Login, Lost Password, and Customizer Register Forms
* Contact Form 7
* Cookies and Content Security Policy
* Customer Reviews for WooCommerce Review and Q&A Forms
* Divi Comment, Contact, Email Optin, and Login Forms
* Divi Builder Comment, Contact, Email Optin, and Login Forms
* Download Manager Form
* Droit Dark Mode
* Easy Digital Downloads Checkout, Login, Lost Password, and Register Forms
* Elementor Pro Form and Login Form
* Essential Addons for Elementor Login and Register Forms
* Essential Blocks Form
* Events Manager Booking Form
* Extra Comment, Contact, Email Optin, and Login Forms
* Fluent Forms, including Conversational, Multi-Step, and Login Forms
* Forminator Forms
* Formidable Forms
* GiveWP Form
* Gravity Forms
* Gravity Perks Nested Forms
* Icegram Express Form
* Jetpack Forms
* Kadence Form and Advanced Form
* LearnDash Login, Lost Password, and Register Forms
* Login/Signup Popup Login and Register Forms
* Mailchimp for WP Form
* MailPoet Form
* Maintenance Login Form
* MemberPress Login and Register Forms
* Ninja Forms
* Otter Blocks Forms
* Paid Memberships Pro Checkout and Login Forms
* Passster Protection Form
* Password Protected Form
* Profile Builder Login, Recover Password, and Register Forms
* Really Simple CAPTCHA
* Quform Forms
* Sendinblue Form
* Simple Download Monitor Form
* Simple Membership Login, Lost Password, and Register Forms
* Simple Basic Contact Form
* Spectra — WordPress Gutenberg Blocks Form
* Subscriber Form
* Support Candy New Ticket Form
* Theme My Login — Login, Lost Password, and Register Form
* Tutor LMS — Checkout, Login, Lost Password, and Register Form
* Ultimate Addons for Elementor Login and Register Forms
* Ultimate Member Login, Lost Password, and Member Register Forms
* UsersWP Forgot Password, Login, and Register Forms
* WooCommerce Login, Registration, Lost Password, Checkout, and Order Tracking Forms
* WooCommerce Germanized Return Request Form
* WooCommerce Wishlist Form
* Wordfence Security Login Form
* Wordfence Login Security Login Form
* WP Dark Mode
* WP Job Openings Form
* WPForms Form
* wpDiscuz Comment and Support Forms
* wpForo New Topic and Reply Forms

=== Please note ===

**NOTE:** This is a community-developed plugin. Your PRs are welcome.

For feature requests and issue reports, please
[open a pull request](https://github.com/hCaptcha/hcaptcha-wordpress-plugin).

We also suggest emailing the authors of plugins you'd like to support hCaptcha: it will usually take them only an hour or two to add native support. This will simplify your use of hCaptcha and is the best solution in the long run.

You may use native hCaptcha support if available for your plugin. Please check with your plugin author if native support is not yet available.

However, the hCaptcha plugin provides a broader set of options and features so that you can use it with any form on your site.

Instructions for popular native integrations are below:

* [WPForms native integration: instructions to enable hCaptcha](https://wpforms.com/docs/how-to-set-up-and-use-hcaptcha-in-wpforms)

== Changelog ==

= 4.17.0 =
* Added a hidden honeypot field and minimum submit time for bot detection before processing hCaptcha. Currently supported for WordPress Core, Avada theme, Contact Form 7, Divi theme, Divi Builder, Essential Addons for Elementor, Extra theme, Elementor, Jetpack, Mailchimp, Ninja Forms, Spectra, WooCommerce, WPForms, Protect Content feature.
* Added a notification message on the placeholder when hCaptcha failed to load.
* Added hCaptcha error messages to the Divi optin email form.
* Added hCaptcha error messages to the JetPack form.
* Fix conflict with reCaptcha in Divi Contact form.
* Fixed running migrations on a fresh installation.
* Fixed updating the migration option on each admin request.
* Fixed a fatal error on saving the Integration settings with active plugins having all switches off.
* Fixed an issue with several Divi optin email forms on the page.
* Fixed activation of Blocksy plugins with any theme.
* Fixed refreshing hCaptcha upon failed submission in Jetpack.
* Fixed Ninja Forms uncaught type error in JS appeared with some field types.
* Fixed Ninja Forms integration when form duplicates are on the same page.

= 4.16.0 =
* Added integration with Germanized for WooCommerce.
* Added integration with Icegram Express, including popup and widget forms.
* Added integration with Fluent Forms Multi-Step forms.
* Added integration with Customer Reviews for WooCommerce.
* Added integration with the Blocksy Companion Newsletter Subscribe, Waitlist, and Product Review forms.
* Fixed showing hCaptcha in Jetpack Form admin.
* Fixed PHP warning on installing the integration plugin.
* Fixed PHP warning when processing Advanced Kadence Form.
* Fixed activation of Pro and Lite integrations when both are needed.
* Fixed Fluent Conversational Form with embedded hCaptcha.
* Fixed edge case bugs with saving settings on a single site, multi-site.
* Fixed network-wide setting functionality with per-site and network plugin activation.
* Fixed a fatal error when attempting to activate Blocksy Companion Pro and Free plugins together.
* Fixed IP CIDR range detection.
* Improved redirect when turning off the network-wide setting.

= 4.15.0 =
* Added hCaptcha error messages to CoBlocks.
* Added hCaptcha error messages to Spectra.
* Added compatibility with Contact Form 7 v6.1.
* Fixed PHP 8.1 deprecated error.
* Fixed the layout of the General page on mobile.
* Fixed the layout of Notifications on the General page.
* Fixed admin page colors respecting Admin Color Scheme.

= 4.14.0 =
* Added Ultimate Addons for Elementor integration.
* Added compatibility with the ActivityPub plugin.
* Added denylisted IP addresses on the General page.
* Added validation of IP addresses on the General page.
* Fixed the conflict with Contact Form 7 plugin reCaptcha integration.
* Fixed fatal error with the wpDiscuz 7.6.30.

= 4.13.0 =
* Added site content protection.
* Added the "Remove Data on Uninstall" option to improve user privacy.
* Added the "What's New" popup on admin pages.
* Added Events Manager integration.
* Added Password Protected integration.
* Added compatibility with Formidable Forms Pro.
* Added support for Avada multistep forms.
* Improved support of the device color scheme.
* Fixed enqueuing hCaptcha scripts on every page when Fluent Forms integration is on.
* Fixed warning in with auto-verify forms, including Brevo.
* Fixed enqueuing script with Fluent Conversational Form.
* Fixed showing hCaptcha with the latest Fluent Forms version.
* Fixed Conversational forms support with the latest Fluent Forms version.
* Fixed the race condition when highlighting admin elements.
* Tested with WordPress 6.7.
* Tested with WooCommerce 9.8.

= 4.12.0 =
* Added 'hcap_print_hcaptcha_scripts' filter.
* Added the ability to filter printing of dsn-prefetch link and inline styles.
* Added auto-forcing and prevent delaying of hCaptcha on login forms for 1Password compatibility.
* Added auto-forcing and prevent delaying of hCaptcha on login forms for LastPass compatibility.
* Added Privacy Policy to WordPress admin Privacy > Policy Guide page.
* Improved API script delay behavior. Now, scripts are loaded after a delay interval or any user interaction, whichever happens first.
* Improved scrolling behavior to highlight elements in admin.
* Fixed the broken 'submit' button with ACF, Gravity Forms, and input to button snippet.
* Fixed printing hCaptcha scripts on the Essential Addons preview page.
* Fixed hCaptcha layout on wpDiscuz forms.
* Fixed the race condition with Pro invisible hCaptcha.
* Fixed the scroll on a page loading with a Kadence form.
* Fixed scroll on a page load with a Kadence Advanced form.
* Fixed scrolling and focusing after submitting with CF7 form.
* Fixed scrolling and focusing after submitting with a Forminator form.
* Fixed scrolling and focusing after submitting with a Quform form.
* Fixed scrolling and focusing after submitting with an Elementor form.
* Fixed scrolling and focusing after submitting with Autoverify in Ajax.
* Fixed scrolling and focusing before checking the Site Config on the General page.
* Fixed the fatal error on claiming action during migration to 4.11.0.
* Fixed fatal error when migrating to 4.0.0 via cron.
* Fixed the WordPress database error on migrating to 4.11.0 in a rare case.

= 4.11.0 =
* Added Really Simple CAPTCHA plugin integration.
* Added compatibility with the UsersWP plugin v1.2.28.
* Added compatibility with the Perfmatters plugin.
* Added support for the Fluent Login form.
* Added confirmation messages upon deletion of events on the Forms and Events pages.
* Added asynchronous migrations for large databases.
* Added hCaptcha error messages to the Contact Form 7 when JavaScript is disabled.
* Optimized Forms page performance for large databases with millions of entries.
* Fixed processing wpDiscuz comment form with wpDiscuz custom ajax.
* Fixed adding hCaptcha internal fields to Avada from submission.
* Fixed ASC ordering by date on the Events page.
* Fixed selection of a time interval on the Events page when site local time was not GMT.
* Fixed losing options during plugin update in rare cases.
* Fixed the live hCaptcha tag on the Contact Form 7 edit page after insertion but before saving the form.
* Fixed shortcode processing in the Contact Form 7 form when Auto-Add was off.
* Fixed the error on theme installation.
* Tested with WooCommerce 9.7.

= 4.10.0 =
* Added support for the wp_login_form () function and LoginOut block.
* Added support for hCaptcha in HTML Gravity Forms fields.
* Added support for custom nonce action and name in the [hcaptcha] shortcode.
* Added compatibility with Cookies and Content Security Policy plugin.
* Added auto-verification of arbitrary forms in ajax.
* Added deletion of events on the Forms page.
* Added deletion of events on the Events page.
* Improved error messaging for hCaptcha verification.
* Fixed IP detection in the WordPress core via filter. Now syncs with hCaptcha event information when an IP collection is activated.
* Fixed a fatal error with the WPForms plugin in rare cases.
* Fixed the error message at the first entry to the login page when Hide Login Errors in on.
* Fixed scrolling to the message on the General page.
* Fixed a fatal error during integration installation in some cases.
* Fixed the Integrations page when the active plugin was deleted.
* Fixed the error when hCaptcha is disabled for standard login but enabled for LearnPress login.
* Fixed the error when hCaptcha is disabled for standard login but enabled for Tutor login.
* Fixed the layout for Forms and Events pages on small screens.

= 4.9.0 =
* Added LearnPress integration.
* Added Tutor LMS integration.
* Added compatibility with Ninja Forms v3.8.22.
* Added the ability to install plugins and themes from the Integrations page.
* Added the ability to hide the login errors.
* Added an anonymous collection of IP and User Agent data in locally stored analytics to simplify GDPR compliance.
* Added extended info about the IP address on the Events page on hover.
* Added selecting any page on Forms and Events.
* Optimized Events page performance for large databases with millions of entries.
* Fixed the layout of a modern Jetpack form in outlined and animated styles.
* Fixed a fatal error as a consequence of a bug in the TutorLMS.
* Fixed the help text box layout on the General page.
* Fixed the dismiss and reset Notifications actions.
* Fixed duplication of entries in the Events table.

= 4.8.0 =
* Added instant updating of the Contact Form 7 live form.
* Added hCaptcha display on the Mailchimp form preview.
* Added Maintenance Login Form integration.
* Added Extra theme integration.
* Added Divi Builder plugin integration.
* Added theme argument to the [hcaptcha] shortcode.
* Added a 'theme' badge to themes on the Integrations page.
* Updated hCaptcha API error codes.
* Fixed processing of a Divi form with diacritical marks.
* Fixed deactivating of all themes by Ctrl+Click on the Integrations page.
* Fixed the theme name display upon activation.
* Fixed the display of the hCaptcha shortcode with individual parameters.
* Fixed the usage of theme in shortcode and form args.
* Fixed instant update upon theme selection on the General admin page.
* Fixed custom themes on the frontend.
* Fixed custom themes on the General page.
* Fixed switching from custom themes to a standard and back on the General page.
* Fixed switching from live to test mode and back on the General page.
* Tested with PHP 8.4.1.

= 4.7.1 =
* Fixed _load_textdomain_just_in_time notice with WordPress 6.7.
* Some translations were empty with WordPress 6.5+.

= 4.7.0 =
* Added compatibility with WordPress Recovery Mode.
* Added compatibility with Contact Form 7 v6.0.
* Added compatibility with the Akismet tag in Contact Form 7.
* Added compatibility with Elementor Element Caching.
* Added activation and deactivation of the plugin network wide if hCaptcha is set network wide.
* Added the ability to use shortcode in the Jetpack Classic form.
* Added the ability to use shortcode in the Mailchimp for WP form.
* Fixed the race condition when loading hCaptcha API.
* Fixed sending a Ninja form with solved hCaptcha.
* Fixed non-active hCaptcha when editing a page containing a Forminator form.
* Fixed launching a notification script on every admin page.
* Fixed missing hCaptcha in Formidable forms.
* Fixed non-blocking of reCaptcha scripts with Kadence Forms.
* Fixed showing hCaptcha in Elementor admin in some cases.
* Fixed the inability to sort by Source column on Forms and Events admin pages.
* Fixed the inability to deactivate the Avada theme right after activation.
* Fixed the inability to deactivate the Divi theme right after activation.
* Fixed the error on plugin activation when the plugin makes redirect on activation.
* Fixed the open_basedir restriction warning in Query Monitor.
* Tested with WordPress 6.7.
* Tested with WooCommerce 9.3.

= 4.6.0 =
* Added support for Simple Membership Login, Register and Lost Password forms.
* Added an option to show Live Form in CF7 admin.
* Added hCaptcha tab on the Gravity Forms settings page.
* Added uninstallation code to delete plugin data.
* Improved compatibility with hCaptcha API.
* Fixed the appearance of hCaptcha in the Ninja Form admin editor after form saving only.
* Fixed no rendering of hCaptcha in the Gravity Forms admin editor after adding the hCaptcha field.
* Fixed no rendering of hCaptcha in the Essential Addons admin editor.
* Fixed switching between Security Settings on the Fluent Forms Global Settings page.
* Fixed the layout for settings pages with RTL languages.
* Fixed the layout for Contact Form 7 with RTL languages.

= 4.5.0 =
* Added support for Jetpack forms in block theme templates.
* Added support for bbPress Login, Register and Lost Password forms.
* Added the second argument $atts to the 'hcap_hcaptcha_content' filter.
* Added support for MailPoet forms at any placement.
* Added the ability to have multiple MailPoet forms on the same page.
* Improved UX of the Integrations page.
* Fixed error messaging when there are several Jetpack forms on the same page.
* Fixed unconditional forcing hCaptcha in Jetpack forms.
* Fixed the appearance of the Beaver Builder editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of the Contact Form 7 editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of the Essential Addons editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of the Gravity Forms editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of the Fluent Forms editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of the Forminator editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of Formidable Forms with the "Turn Off When Logged In" setting.
* Fixed the appearance of the Ninja Forms editor with the "Turn Off When Logged In" setting.
* Fixed the appearance of the WPForms editor with the "Turn Off When Logged In" setting.
* Fixed a fatal error on the Gravity Forms Entries page.
* Fixed Elementor preview.
* Fixed Ninja Forms preview.
* Fixed hCaptcha nonce error on MailPoet admin pages.
* Fixed the frontend error when recaptcha was activated in wpDiscuz.

= 4.4.0 =
* Added compatibility with Contact Form 7 Stripe integration.
* Added compatibility with the WPS Hide Login plugin.
* Added compatibility with the LoginPress plugin.
* Improved compatibility with the Wordfence Login Security plugin.
* Updated MailPoet integration.
* Fixed the conflict with the Ninja Forms Upload field.
* Fixed Ninja Forms Ajax processing.
* Fixed the error in cron with Matomo Analytics.
* Fixed the error with the Elementor Checkout Element.
* Fixed ignorance of Pro params in the Elementor admin editor.
* Fixed the inability to activate the Elementor Pro plugin when Elementor plugin is activated.
* Fixed sending replies to wpDiscuz comments.
* Fixed replies in the WP Dashboard with wpDiscuz active.
* Fixed sending several wpDiscuz comments without a page reload.

= 4.3.1 =
* Added a live form in the Contact Form 7 admin form view.
* Fixed warnings and deprecation messages in admin when Contact Form 7 is active.
* Fixed the tag generator with the live form for Contact Form 7.
* Fixed a fatal error with Gravity Forms.

= 4.3.0 =
* NOTE: the plugin has been renamed from "hCaptcha for WordPress" to "hCaptcha for WP"
* Dropped support for PHP 7.0 and 7.1. The minimum required PHP version is now 7.2.
* Added a live form in the Contact Form 7 admin editor.
* Added support for Contact Form 7 embedded forms.
* Added support for the WooCommerce Checkout block.
* Added support for GiveWP block forms created via Form Builder.
* Added check if a plugin or theme is installed before activation.
* Added activation of dependent plugins with a theme.
* Fixed missing sitekey error processing on the General page.
* Fixed the naming of the first submenu item.
* Fixed the storing of check config events to the database.
* Fixed notifications links in menu pages mode.
* Fixed Firefox issue with not showing hCaptcha when the API script was delayed until user interaction.
* Fixed the error on activation/deactivation of a theme.
* Fixed error on activating Brizy plugin.
* Fixed issue with updated Brizy plugin.
* Fixed the issue with the updated Divi EmailOptin module.
* Tested with WordPress 6.6.
* Tested with WooCommerce 9.0.

= 4.2.1 =
* Fixed the message layout on the General and Integrations pages.
* Fixed processing of the WooCommerce Register form.

= 4.2.0 =
* The minimum required WordPress version is now 5.3.
* Added support for Multisite Network Admin synced with network-wide plugin options.
* Added selection by date range on Forms and Events pages.
* Added automatic activation of dependent plugins on the Integrations page.
* Added scrolling on the Integrations page during the search.
* Fixed color flickering of hCaptcha placeholder with custom themes.
* Fixed the JS error on the Lost Password page.
* Fixed the missing site key notification on the General page.
* Fixed a fatal error on some sites during migration to 4.0.0.

= 4.1.2 =
* Added an option to have the hCaptcha admin menu under Settings.
* Fixed the General admin page on the mobile.
* Fixed Forms and Events admin pages on the mobile.

= 4.1.1 =
* Added updating of the Custom Themes properties on the General page upon manual editing of the Config Params JSON.
* Fixed a possible fatal error with third-party plugins using a Jetpack library.

= 4.1.0 =
* Added Essential Blocks integration.
* Added hideable columns to Forms and Events tables.
* Admin menu moved to the top level with subpages.
* Added a filter to change the admin menu appearance.
* Add a modern dialog to the System Info admin page.
* Add a modern dialog to the Gravity Forms edit page.
* Add a modern dialog to the Ninja Forms edit page.
* Tested with WooCommerce 8.8.

= 4.0.1 =
* Added pagination to the Forms and Events pages.
* Fixed the PHP notice on the Forms page.

= 4.0.0 =
* This major release adds a new Statistics feature and many admin improvements.
* Added hCaptcha events statistics and Forms admin page.
* Added Events admin page for Pro users.
* Added Custom Theme Editor for Pro users.
* Added a Force option to show hCaptcha challenge before submit.
* Added integration with Essential Addons for Elementor — the Login/Register form.
* Added filter `hcap_form_args` to allow modifying form arguments.
* Reworked Otter integration to follow Force and all other hCaptcha settings.
* Fixed the issue with Divi Contact Form Helper plugin and File Upload field.
* Fixed showing an internal console message on the General page when reCaptcha compatibility was disabled.
* Fixed the racing condition with hCaptcha script loading.
* Fixed checking nonce in CF7 for not logged-in users.
* Tested with WooCommerce 8.7.

[See changelog for all versions](https://plugins.svn.wordpress.org/hcaptcha-for-forms-and-more/trunk/changelog.txt).
