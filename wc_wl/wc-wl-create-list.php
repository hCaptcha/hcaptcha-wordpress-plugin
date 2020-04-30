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
  if (isset($_POST['hcaptcha_wc_create_wishlist_nonce']) && wp_verify_nonce($_POST['hcaptcha_wc_create_wishlist_nonce'], 'hcaptcha_wc_create_wishlist') && isset($_POST['h-captcha-response'])) {
    $get_hcaptcha_response = htmlspecialchars(sanitize_text_field($_POST['h-captcha-response']));
    $hcaptcha_secret_key = get_option('hcaptcha_secret_key');
    $response = wp_remote_get('https://hcaptcha.com/siteverify?secret=' . $hcaptcha_secret_key . '&response=' . $get_hcaptcha_response);
    $response = json_decode($response["body"], true);
    if (true == $response["success"]) {
      return $valid_captcha;
    } else {
      $valid_captcha = false;
      $error_message = __('The Captcha is invalid.', 'hcaptcha-wp');
      wc_add_notice($error_message, 'error');
      return $valid_captcha;
    }
  } else {
    $valid_captcha = false;
    $error_message = __('Please complete the captcha.', 'hcaptcha-wp');
    wc_add_notice($error_message, 'error');
    return $valid_captcha;
  } 
}
add_filter("woocommerce_validate_wishlist_create", "hcap_verify_wc_wl_create_list_captcha");