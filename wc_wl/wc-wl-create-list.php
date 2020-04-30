<?php

// add integration for WooCommerce Wishlists plugin
// see: https://woocommerce.com/products/woocommerce-wishlists/

// If this file is called directly, abort.
if (!defined('ABSPATH')) exit;

/*
This integration requires the creation of a template file override as follows
1. copy woocommerce-wishlists/templates/create-a-list.php to <your-theme>/woocommerce
2. find the following lines near line number 143:

  <?php if ( function_exists( 'gglcptch_display' ) ) {
    echo gglcptch_display();
  };  ?>

3. add the following code just below that

  <?php if ( function_exists('hcap_display_hcaptcha' ) ) {
    add_filter('hcap_hcaptcha_content', function ($content){
      $content .= wp_nonce_field('hcaptcha_wc_create_wishlist', 'hcaptcha_wc_create_wishlist_nonce', true, false);
      return $content;
    });
    echo hcap_display_hcaptcha();
  }; ?>

*/

function hcap_verify_wc_wl_create_list_captcha($valid_captcha) {
	$errorMessage = hcaptcha_get_verify_message( 'hcaptcha_wc_create_wishlist_nonce', 'hcaptcha_wc_create_wishlist' );
	if ( $errorMessage === null ) {
		return $valid_captcha;
	}
	wc_add_notice($errorMessage, 'error');
	return false;
}
add_filter("woocommerce_validate_wishlist_create", "hcap_verify_wc_wl_create_list_captcha");