=== hCaptcha for WP ===
Contributors: hcaptcha, kaggdesign
Tags: captcha, hcaptcha, recaptcha, antispam, spam
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 5.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The strongest CAPTCHA. Switch from reCAPTCHA and Turnstile for free.
Works with 60+ integrations: Contact Form 7, Elementor, WooCommerce, Divi, etc.

== Description ==

The strongest CAPTCHA. Switch from reCAPTCHA and Turnstile for free.

A built-in Migration Wizard helps you move from Google reCAPTCHA or Cloudflare Turnstile to hCaptcha in just a few clicks.

[hCaptcha](https://www.hcaptcha.com/) is a drop-in replacement for reCAPTCHA that puts user privacy first.

Need to keep out bots? hCaptcha protects privacy while offering better protection against spam and abuse. Help build a better web.

hCaptcha for WP [makes security easy](https://www.hcaptcha.com/integration-hcaptcha-for-wp) with broad integration support, detailed analytics, and strong protection. Start protecting logins, forms, and more in minutes.

== Benefits ==

* **Privacy First:** hCaptcha is designed to protect user privacy. It doesn't retain or sell personal data, unlike platforms that **g**ather, **o**wn, and m**o**netize **gl**obal b**e**havior.
* **Better Security:** hCaptcha offers better protection against bots and abuse than other anti-abuse systems.
* **Easy to Use:** hCaptcha is easy to install and use with WordPress and popular plugins.
* **Broad Integration:** hCaptcha works with WordPress Core, WooCommerce, Contact Form 7, Elementor, and over 60 other plugins and themes.

== Features ==

**Highlights**

* **Migration Wizard:** Migrate from Google reCAPTCHA or Cloudflare Turnstile to hCaptcha in just a few clicks.
* **Built-in Anti-Spam:** Honeypot fields and minimum submit time catch bots before the hCaptcha challenge, reducing friction for real users.
* **Detailed Analytics:** Get detailed analytics on hCaptcha events and form submissions.
* **AI-Ready Security:** Selected security actions are exposed via the WordPress Abilities API for automation and AI-driven workflows.
* **Pro and Enterprise:** Supports Pro and Enterprise versions of hCaptcha.
* **No Challenge Modes:** 99.9% passive and passive modes in Pro and Enterprise versions reduce user friction.
* **Protect Site Content:** Protects selected site URLs from bots with hCaptcha. Works best with Pro 99.9% passive mode.
* **Logged-in Users:** Optionally turn off hCaptcha for logged-in users.
* **Delayed API Loading:** Load the hCaptcha API instantly or on user interaction for zero page loading impact.
* **IP Access Control:** Allowlist trusted IPs to skip hCaptcha and denylist abusive IPs to block form submissions.
* **Country Access Control:** Allowlist or denylist countries to control where hCaptcha protections apply.
* **Multisite Support:** Sync hCaptcha settings across a Multisite Network.

**Anti-Spam**

* **Honeypot Protection:** A hidden field catches bots before they reach the hCaptcha challenge, reducing friction for real users.
* **Minimum Submit Time:** Blocks instant form submissions from automated scripts.
* **IP Denylist:** Block abusive IPs from submitting any protected form.
* **Country Blocking:** Restrict form submissions by country to stop region-specific spam campaigns.

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
10. Anti-Spam settings page.
11. Integrations' settings page.
12. Activating plugin from the Integrations' settings page.
13. Migration Wizard scan results on the Tools settings page.
14. (Optional) Local Forms statistics.
15. (Optional) Local Events statistics.

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

= How do I migrate from reCAPTCHA or Turnstile? =

Go to Settings → hCaptcha → Tools and use the Migration Wizard.

It scans your site for existing CAPTCHA providers, shows what can be migrated, and applies the changes in one click.

= How do I use the new AI / Abilities features? =

hCaptcha exposes selected security actions via the WordPress Abilities API for use with automation tools, WP-CLI, and AI agents, making it suitable for agencies managing multiple WordPress sites. Requires WordPress 6.9 or newer.

The typical workflow consists of two steps: inspect threats and block offenders.

** 1. Inspect recent threat activity **

You can request an aggregated threat snapshot for a given time window.

Using WP-CLI:

`
wp ability run hcaptcha/get-threat-snapshot --input='{"window":"55d"}' --user=admin
`

Using REST API (authenticated):

`
curl --globoff -u "USER:APP_PASSWORD" \
"https://example.com/wp-json/wp-abilities/v1/abilities/hcaptcha/get-threat-snapshot/run?input[window]=55d"
`

The response includes:
* overall metrics (total requests, failure rate)
* confidence and top error vectors
* breakdown by error type and form source
* a list of top offenders (if present)

Example (simplified):
`
{
  "metrics": { "total": 353, "failed": 215, "fail_rate": "0.61" },
  "signals": { "confidence": "high", "top_vectors": ["empty", "spam"] },
  "breakdown": {
    "errors": { "empty": 160, "spam": 16 },
    "offenders": [
      {
        "offender_id": "a1376a016c4156933c4d49b0bc56fa01",
        "type": "ip",
        "count": 2
      }
    ]
  }
}
`

** 2. Block abusive offenders **

If an offender appears suspicious, you can block it using its offender_id.

Using WP-CLI:

`
wp ability run hcaptcha/block-offenders \
--input='{"offender_ids":["a1376a016c4156933c4d49b0bc56fa01"]}' \
--user=admin
`

Using REST API (authenticated):
`
curl --globoff -u "USER:APP_PASSWORD" \
"https://example.com/wp-json/wp-abilities/v1/abilities/hcaptcha/block-offenders/run?input[offender_ids][]=a1376a016c4156933c4d49b0bc56fa01"
`

Example response:

`
{
  "blocked": ["a1376a016c4156933c4d49b0bc56fa01"],
  "effective_until": "2026-01-01T22:22:09Z"
}
`

** What is offender_id? **

`offender_id` is a stable hash of the IP address.
Raw IP addresses are never exposed to automation clients or AI agents.

This allows privacy-safe analysis and blocking, while still enabling deterministic enforcement.

** Can AI agents use this automatically? **

Yes.
You can point an AI agent to a WordPress site with Abilities enabled and instruct it to:
* discover available abilities
* collect threat statistics
* decide whether activity looks abusive
* block the most active offenders

Internally, the agent performs the same commands shown above (`wp ability list`, `get-threat-snapshot`, `block-offenders`).

** 3. Export plugin settings **

You can export current plugin settings as JSON (optionally including keys) for backup or migration.

Using WP-CLI:

`
wp ability run hcaptcha/export-settings --include_keys --user=admin
`

Using REST API (authenticated):

`
curl --globoff -u "USER:APP_PASSWORD" \
"https://example.com/wp-json/wp-abilities/v1/abilities/hcaptcha/export-settings/run?input[include_keys]=1"
`

** 4. Import plugin settings **

Import settings from a JSON file path on the server. Use `allow_keys` to apply the keys block and `dry_run` to validate without saving.

Using WP-CLI:

`
wp ability run hcaptcha/import-settings --allow_keys --dry-run=false --user=igor --input_file=1.json
`

Using REST API (authenticated):

`
curl --globoff -u "USER:APP_PASSWORD" \
"https://example.com/wp-json/wp-abilities/v1/abilities/hcaptcha/import-settings/run?input[input_file]=%2Fpath%2Fto%2Fhcaptcha-settings.json&input[allow_keys]=1&input[dry_run]=0"
`

= WP-CLI commands for exporting and importing settings =

The plugin also adds the `wp hcaptcha export` and `wp hcaptcha import` commands.

**Export settings**

```
wp hcaptcha export --pretty > hcaptcha-settings.json
wp hcaptcha export --include-keys --file=./hcaptcha-settings.json
```

Parameters:
* `--include-keys` — include the `site_key` and `secret_key` values.
* `--pretty` — pretty-print JSON for readability.
* `--file=<path>` — write JSON to a file instead of STDOUT.

**Import settings**

```
wp hcaptcha import ./hcaptcha-settings.json
wp hcaptcha import ./hcaptcha-settings.json --dry-run
wp hcaptcha import ./hcaptcha-settings.json --allow-keys
```

Parameters:
* `--dry-run` — validate the JSON without saving.
* `--allow-keys` — allow importing keys from the `keys` block.

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

The plugin has a filter to skip adding and verifying hCaptcha on a specific form. The filter receives three parameters: current protection status ('true' by default), source, and form_id.

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

= How can I load the hCaptcha API script only when a specific element is visible? =

To load the hCaptcha API script only when a WordPress comment form is visible, you can use the followign filter:

`
/**
 * Filters delay API selector.
 *
 * When set, the hcaptcha.js script will be loaded only when the specified element is visible.
 * This can improve page load performance by deferring the API script until it's necessary.
 *
 * @param string|mixed $delay_api_selector CSS selector of the element to observe.
 */
add_filter( 'hcap_delay_api_selector', static function ( $delay_api_selector ) {
	$delay_api_selector = (string) $delay_api_selector;

	if ( is_admin() || is_login() ) {
		return $delay_api_selector;
	}

	$selectors = [
		'.comment-form', // WP comment form.
		'.elementor-form', // Elementor Pro.
		'.wpcf7-form', // Contact Form 7.
		'.fluentform, .frm-fluent-form', // Fluent Forms.
		'.nf-form-cont', // Ninja Forms.
		'.es_subscription_form, .es-form-field-container, .ig_popup', // Icegram Express Forms.
		'.cr-reviews-ajax-reviews, .cr-qna-block', // Customer Reviews.
	];

	return implode( ', ', $selectors );
} );
`

= How can I delay the hCaptcha API script until a custom event? =

Developers can use the `hcap_delay_api_event` filter to opt into custom event-based API loading for specific integrations.

When the filter returns a non-empty event name, hCaptcha waits for `hCaptchaBeforeAPI`, then listens for that custom event on `document`. The default delay timer and built-in user interaction listeners are skipped. Your integration must dispatch the event when it is ready to load the hCaptcha API.

Scope this filter carefully to only the pages or forms where you also dispatch the event.

`
/**
 * Filters the custom browser event name used to load the hCaptcha API script.
 *
 * @param string|mixed $delay_api_event Custom browser event name.
 */
add_filter(
  'hcap_delay_api_event',
  static function ( $delay_api_event ): string {
    if ( ! is_singular() || ! has_block( 'jetpack/contact-form', get_queried_object() ) ) {
      return (string) $delay_api_event;
    }

    return 'hcap-load-api';
  }
);

// Later, when the form is interacted with:
// document.dispatchEvent( new CustomEvent( 'hcap-load-api' ) );
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

= How does hCaptcha determine the visitor IP address? =

hCaptcha uses `REMOTE_ADDR` by default. If your site is behind a trusted proxy or CDN, go to Settings → hCaptcha → Anti-Spam → Access Control and select only the IP headers your edge service overwrites or strips from direct client requests.

Forwarding headers such as `X-Forwarded-For`, `CF-Connecting-IP`, and `X-Real-IP` can be spoofed when they pass through from the browser unchanged. Do not enable a header unless your hosting stack makes it trustworthy before WordPress receives the request.

On upgrade, custom `hcap_trusted_address_headers` filters are migrated into this setting. Otherwise the setting starts empty and an admin notice asks you to review it.

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

= Where do I report security bugs found in this plugin? =

Please report security vulnerabilities by email to:

**security@hcaptcha.com**

When reporting a vulnerability, please include as much information as possible to help us reproduce and investigate the issue, such as:

- A clear description of the vulnerability
- Steps to reproduce
- Proof-of-concept or exploit code (if available)
- Affected versions

We will review your report and respond as quickly as possible.

= Where can I get more information about hCaptcha? =

Please see our [website](https://hcaptcha.com/).

= How can I rerun the setup wizard? =

Use the plugin-generated "Restart wizard" onboarding link from the admin area. Onboarding setup URLs are nonce-protected and should not be crafted manually.

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
* **only if you enable this optional feature:** the IP address challenged on each form
* **only if you enable this optional feature:** the User Agent challenged on each form

We recommend leaving IP and User Agent recording off, which will make these statistics fully anonymous.

You can collect data anonymously but still distinguish sources. The hashed IP address and User Agent will be saved.

If this feature is enabled, anonymized statistics on your plugin configuration, not including any end user data, will also be sent to us. This lets us see which modules and features are being used and prioritize development for them accordingly.

== Plugins, Themes, and Forms Supported ==

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

= 5.1.0 =
* Added version switching to the What's New popup.
* Added a Help button on hCaptcha admin pages to generate support reports for GitHub or WordPress.org, with optional System Info included.
* Added hcap_delay_api_event filter for delayed loading of the hCaptcha API upon user interaction.
* Hardened form verification for 22 integrations.
* Fixed hcap_delay_api_selector filter for delayed loading of hCaptcha in the Jetpack contact forms.
* Fixed hCaptcha auto-insertion for Jetpack block contact forms that render the Submit button with core Button block markup.
* Fixed hCaptcha auto-insertion for WooCommerce Checkout blocks when the Return to Cart link is enabled.
* Fixed manually added hCaptcha shortcodes inside Jetpack contact forms to use the proper form signature.
* Fixed Events statistics table indexes for MariaDB/MyISAM databases with a 1000-byte key length limit.
* Fixed System Info migration entries to show that older migrations were not required instead of displaying the Unix epoch date.
* Fixed hCaptcha token refresh for Blocksy newsletter and waitlist forms after failed submissions.
* Fixed ACF Extended Forms integration to prevent reCAPTCHA from loading when hCaptcha is used to avoid submission errors.
* Fixed hCaptcha verification for upgraded GiveWP donation forms.
* Fixed FST token replay errors after submitting GiveWP forms without completing hCaptcha.

= 5.0.1 =
* Fixed Elementor Pro Forms validation when the optional Form ID is empty or differs from the Elementor widget ID.
* Fixed Events statistics table handling to avoid runtime table-existence checks and recreate the table during activation or maintenance when needed.

= 5.0.0 =
* Added Trusted IP Headers settings. hCaptcha uses REMOTE_ADDR by default; custom `hcap_trusted_address_headers` filters are migrated into the setting during upgrade.
* Added Cloudflare detection to help identify when CF-Connecting-IP should be selected as a Trusted IP Header.
* Added Trash support for Forms and Events statistics, including restore/permanent delete actions, 30-day Trash cleanup, and migration for existing event tables.
* Added WooCommerce PayPal Payments integration for product, cart, mini-cart, and checkout express flows.
* Added site icon on protected content pages.
* Hardened form verification for some popular forms.
* Fixed returning unexpected results by REST API in some cases.
* Fixed Avada Forms integration so internal hCaptcha fields are excluded from `[all_fields]` notification emails.
* Fixed sending statistics at the plugin update in some rare cases when the switch is off.
* Fixed an issue in some custom Gravity Forms layouts.
* Fixed an issue where a What's New modal action could scroll the current Integrations page before opening the target integration in a new tab.
* Fixed errors when resubmitting Essential Addons login and registration forms.

[See changelog for all versions](https://plugins.svn.wordpress.org/hcaptcha-for-forms-and-more/trunk/changelog.txt).
