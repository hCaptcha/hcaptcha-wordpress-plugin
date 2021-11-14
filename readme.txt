=== hCaptcha for WordPress ===
Contributors: hcaptcha, kaggdesign
Tags: captcha, hcaptcha, make money with captcha, recaptcha, human captcha  
Requires at least: 4.4
Tested up to: 5.8
Requires PHP: 5.6  
Stable tag: 1.13.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html  
 
Enables hCaptcha.com integration with WordPress.

== Description ==
 
hCaptcha is a drop-in replacement for reCAPTCHA that pays website owners while preserving user privacy.

Do you use a captcha to keep out bots? hCaptcha protects user privacy, rewards websites, and helps companies get their data labeled. Help build a better web. 

**NOTE:** This is a community-developed plugin. All integrations were submitted by developers who didn't want to wait for a particular plugin to add native hCaptcha support. 

If you see an integration that doesn't work, or one that's missing, please
[open a pull request](https://github.com/hCaptcha/hcaptcha-wordpress-plugin):

However, you may wish to email the authors of plugins you'd like to support hCaptcha: it will usually take them only an hour or two to add native support if they choose to do so. This will simplify your use of hCaptcha, and is the best solution in the long run.

== How hCaptcha Works ==

The purpose of a CAPTCHA is to distinguish between people and machines via a challenge-response test, and thus increase the cost of spamming or otherwise abusing websites by keeping out bots. 

hCaptcha takes this idea and extends it by attempting to use those challenge answers for annotation, in an attempt to avoid simply wasting that effort. It is designed to solve the most labor-intensive problem in machine learning: labeling massive amounts of data in a timely, affordable, and reliable way.

More data generally produces better results in training machine learning models. The recent success of deep models has led to increasingly large datasets, almost always with some human review. However, creating large human-reviewed datasets via Mechanical Turk, Figure Eight, etc. is both slow and expensive.

hCaptcha allows websites to earn rewards while serving this demand while blocking bots and other forms of abuse when a user needs to prove their humanity.
 
== Installation ==
 
1. Upload `hcaptcha-wp` folder to the `/wp-content/plugins/` directory  
2. Activate the plugin through the 'Plugins' menu in WordPress  
3. Enter your site key and SECRET in the Settings -> hCaptcha menu in WordPress  
4. Enable desired Integrations  
 
== Frequently Asked Questions ==

= How to use the hCaptcha plugin? =

The hCaptcha plugin supports WordPress core and many plugins with forms automatically. You should select the supported forms on the hCaptcha plugin settings page.

For non-standard cases, you can use the `[hcaptcha]` shortcode provided by the plugin.

We support Contact Forms 7 automatically. Sometimes, however, a theme can modify the form. In this case, you can manually add the `[cf7-hcaptcha]` shortcode to the CF7 form.

= You don't support plugin X. How can I get support for it added? =

[Open a PR on GitHub](https://github.com/hCaptcha/hcaptcha-wordpress-plugin): or just email the authors of plugin X. Adding hCaptcha support is typically quite a quick task for most plugins.

= Does the [hcaptcha] shortcode have arguments? =

The shortcode adds not only the hCaptcha div to the form, but also a nonce field. You can set your own nonce action and name. For this, use arguments in the shortcode:

`
[hcaptcha action="my_hcap_action" name="my_hcap_name"]
`

and in the verification:

`
$result = hcaptcha_request_verify( 'my_hcap_action', 'my_hcap_name' );
`

See also the section *"How to automatically verify an arbitrary form"*

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
do_shortcode( 'hcaptcha' );
`

Secondly, verify the result of hcaptcha challenge.

`
$result = hcaptcha_request_verify();

if ( 'success' !== $result ) {
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

= How to block hcaptcha on specific page? =

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
  filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_STRING ) :
  '';

  if ( '/my-account/' === $url ) {
  return false;
  }

  return $activate;
  }

add_filter( 'hcap_activate', 'my_hcap_activate' );
`

= Why isn't my WPForms Lite installation working? =

Please make sure you have removed the reCAPTCHA keys under WPForms > Settings > reCAPTCHA to avoid a conflict.

= Where can I get more information about hCaptcha? =

Please see our [website](https://hcaptcha.com/).

== Privacy Notices ==

With the default configuration, this plugin does not:

* track users by stealth;
* write any user personal data to the database;
* send any data to external servers;
* use cookies.

Once you activate this plugin, the hCaptcha-answering user's personal data, including their IP address, may be sent to the hCaptcha service.

Please see the hCaptcha privacy policy at: 

* [hCaptcha.com](https://hCaptcha.com/privacy)

=== Forms and Plugins Supported ==

* Login Form
* Register Form
* Lost Password Form
* Comment Form
* bbPress New Topic Form
* bbPress Reply Form
* BuddyPress Create Group Form
* Buddypress Registration Form
* Contact Form 7
* Divi Contact Form
* Elementor Pro Form
* Jetpack Forms
* Mailchimp for WP Form
* MemberPress Register Form
* Ninja Forms
* Subscriber Form
* WooCommerce Login Form
* WooCommerce Registration Form
* WooCommerce Lost Password Form
* WooCommerce Checkout Form
* WooCommerce Order Tracking Form
* WooCommerce Wishlist
* WPForms Lite
* wpForo New Topic Form
* wpForo Reply Form

=== Please note ===

Some plugins listed have been superseded by native support, and are included only for legacy purposes.

You should always use native hCaptcha support if available for your plugin.
Please check with your plugin author if native support is not yet available.

Instructions for native integrations are below:

* [WPForms native integration: instructions to enable hCaptcha](https://wpforms.com/docs/how-to-set-up-and-use-hcaptcha-in-wpforms)
 
== Changelog ==

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
* Fixed issue with insertion of hcaptcha not only to Jetpack forms.
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
* Added feature to turn off the plugin for logged in users.
* Added hook to disable the plugin on specific pages.
* Added feature to run hcaptcha script and styles on pages where it is used only.

= 1.9.2 =
* Fixed issue with WooCommerce on my-account page - captcha was requested even if solved properly.

= 1.9.1 =
* Fixed issue with Contact Form 7 - reset hcaptcha widget when form is not validated.

= 1.9.0 =
* Tested with WordPress 5.7 and WooCommerce 5.0

= 1.8.0 =
* Added option to disable reCAPTCHA Compatibility (use if including both hCaptcha and reCAPTCHA on the same page)

= 1.7.0 =
* 100% covered by WordPress integration tests.
* Tests run on CI with PHP 5.6 - 8.0, latest WordPress core and latest related plugins.

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
