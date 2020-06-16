=== hCaptcha for WordPress ===
Contributors: hcaptcha, kaggdesign
Tags: captcha, hcaptcha, make money with captcha, recaptcha, human captcha  
Requires at least: 4.4
Tested up to: 5.4
Requires PHP: 5.6  
Stable tag: 1.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html  
 
Enables hCaptcha.com integration with WordPress.

== Description ==
 
hCaptcha is a drop-in replacement for reCAPTCHA that pays website owners while preserving user privacy.

Do you use a captcha to keep out bots? hCaptcha protects user privacy, rewards websites, and helps companies get their data labeled. Help build a better web. 

**NOTE:** This is a community-developed plugin. All integrations were submitted by developers who didn't want to wait for a particular plugin to add native hCaptcha support. 

If you see an integration that doesn't work, or one that's missing, please open a pull request:
https://github.com/hCaptcha/hcaptcha-wordpress-plugin

However, you may wish to email the authors of plugins you'd like to support hCaptcha: it will usually take them only an hour or two to add native support if they choose to do so. This will simplify your use of hCaptcha, and is the best solution in the long run.

== How hCaptcha Works ==

The purpose of a CAPTCHA is to distinguish between people and machines via a challenge-response test, and thus increase the cost of spamming or otherwise abusing websites by keeping out bots. 

hCaptcha takes this idea and extends it by attempting to use those challenge answers for annotation, in an attempt to avoid simply wasting that effort. It is designed to solve the most labor intensive problem in machine learning: labeling massive amounts of data in a timely, affordable, and reliable way.

More data generally produces better results in training machine learning models. The recent success of deep models has led to increasingly large datasets, almost always with some human review. However, creating large human-reviewed datasets via Mechanical Turk, Figure Eight, etc. is both slow and expensive.

hCaptcha allows websites to earn rewards while serving this demand while blocking bots and other forms of abuse when a user needs to prove their humanity.
 
=== Installation ===
 
1. Upload `hcaptcha-wp` folder to the `/wp-content/plugins/` directory  
2. Activate the plugin through the 'Plugins' menu in WordPress  
3. Enter your site key and SECRET in the Settings -> hCaptcha menu in WordPress  
4. Enable desired Integrations  
 
=== Frequently Asked Questions ===

Q: Where can I get more information about hCaptcha?  
A: Please see our website at: https://hcaptcha.com/

=== Privacy Notices ===

With the default configuration, this plugin does not:

* track users by stealth;
* write any user personal data to the database;
* send any data to external servers;
* use cookies.

Once you activate this plugin, the hCaptcha-answering user's personal data, including their IP address, may be sent to the hCaptcha service.

Please see the hCaptcha privacy policy at: 

* ([hCaptcha.com](https://hCaptcha.com/privacy))
 
== Changelog ==
 
= 1.0 =

* Plugin Created

= 1.1 =

* Minor bugfixes

= 1.2 =

* Update to Contact Form 7 support. Adds compatibility for version 5.1.3.

= 1.3 =

* Automatic addition of hCaptcha button to Contact Form 7 forms when enabled.

= 1.4.1 =

* Updated testing information, improve docs.

= 1.4.2 = 

* Fixed comment issue, added WooCommerce Wishlists

= 1.5.0 =

* Refactor to improve code hygiene, fixes for latest Ninja Forms.

= 1.5.1 =

* Update docs


=== Forms and Plugins Supported ==

* Ninja Forms Addon
* Contact Form 7 Addon
* Login Form
* Register Form
* Comment Form
* Lost Password Form
* WooCommerce Login Form
* WooCommerce Registration Form
* WooCommerce Lost Password Form
* WooCommerce Checkout Form
* WooCommerce Wishlists (see notes in wc_wl/wc-wl-create-list.php)
* Buddypress Registration Form
* BuddyPress Create Group Form
* bbpress new topic Form
* bbpress reply Form
* WPForo new topic Form
* WPForo Reply Form
* Mailchimp for WP Form
* Jetpack Contact Form
* Subscribers Form

