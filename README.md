# hCaptcha for WordPress

![Build Status](https://github.com/hCaptcha/hcaptcha-wordpress-plugin/actions/workflows/ci.yml/badge.svg?branch=master)

Contributors: intercrypt, hCaptcha team, phpwebdev11, faysalhaque, plexusllc-admin, thinhbuzz, kaggdesign, publicarray

Maintainers: hCaptcha team  

License: GPLv2 or later, at your option  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  
 
Enables hCaptcha integration with WordPress.

## Description

**NOTE: This is a community-developed plugin. All integrations were submitted by developers like you. If you see an integration that doesn't work, or one that's missing, please open a pull request!**

Note that PRs should meet [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/). This is automatically enforced by CI. 

hCaptcha is a drop-in replacement for reCAPTCHA that earns website owners money and helps companies get their data labeled, without giving your user data to an ad company. 

## Installation

```
cd /wp-content/plugins
git clone https://github.com/hCaptcha/hcaptcha-wordpress-plugin.git
cd hcaptcha-wordpress-plugin
composer install
yarn
yarn run build:dev
```
1. Sign up at [hCaptcha.com](https://www.hcaptcha.com/) to get a site key and secret.
2. Activate the plugin through the 'Plugins' menu in WordPress  
3. Enter your Site Key and SECRET in the Settings -> hCaptcha menu in WordPress  
4. Enable desired Integrations  
 
## Run code sniffer to respect WordPress coding standards

```
composer phpcs
```

## Run integration tests

```
composer integration
```

## Run unit tests

```
composer unit
```

## Frequently Asked Questions

Q: Where can I get more information about hCaptcha?  
A: Please see our website at: https://www.hcaptcha.com/
 
## Screenshots

See our blog post at: https://medium.com/hcaptcha-blog/hcaptcha-plugin-for-wordpress-now-available-49765536c69f

## Credits

* Initial version: Alex V. + intercrypt team
* Major upgrade + 100% test coverage: kaggdesign
* Current version and maintainer: hCaptcha team + community

## Support

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
* Otter Blocks Forms
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

**Please note**

Some plugins listed have been superseded by native support, and are included only for legacy compatibility purposes.

You should always use native hCaptcha support if available for your plugin.
Please check with your plugin author if native support is not yet available.

Instructions for native integrations are below:

* [WPForms native integration: instructions to enable hCaptcha](https://wpforms.com/docs/how-to-set-up-and-use-hcaptcha-in-wpforms/)

