<?php
/**
 * BuddyPress create group form file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

/**
 * BuddyPress group form.
 */
function hcap_bp_group_form() {
	echo '<div class="hcap_buddypress_group_form">';

	hcap_form_display();
	wp_nonce_field( 'hcaptcha_bp_create_group', 'hcaptcha_bp_create_group_nonce' );

	echo '</div>';
}

add_action( 'bp_after_group_details_creation_step', 'hcap_bp_group_form' );

if ( ! function_exists( 'hcap_hcaptcha_bp_group_verify' ) ) {

	/**
	 * Verify BuddyPress group form captcha.
	 *
	 * @param mixed $bp_group BuddyPress group.
	 *
	 * @return bool
	 */
	function hcap_hcaptcha_bp_group_verify( $bp_group ) {
		if ( ! bp_is_group_creation_step( 'group-details' ) ) {
			return false;
		}

		$error_message = hcaptcha_get_verify_message(
			'hcaptcha_bp_create_group_nonce',
			'hcaptcha_bp_create_group'
		);

		if ( null === $error_message ) {
			return true;
		}

		bp_core_add_message( $error_message, 'error' );
		bp_core_redirect( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create/step/group-details/' );

		return false;
	}
}

add_action( 'groups_group_before_save', 'hcap_hcaptcha_bp_group_verify' );
