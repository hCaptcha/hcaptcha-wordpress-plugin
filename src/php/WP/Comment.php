<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\Origin;
use WP_Error;

/**
 * Class Comment
 */
class Comment {

	/**
	 * Nonce action.
	 */
	const ACTION = 'hcaptcha_comment';

	/**
	 * Nonce name.
	 */
	const NONCE = 'hcaptcha_comment_nonce';

	/**
	 * Add captcha to the form.
	 *
	 * @var bool
	 */
	private $active;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->active = hcaptcha()->settings()->is( 'wp_status', 'comment' );

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	private function init_hooks() {
		add_filter( 'comment_form_submit_button', [ $this, 'add_origin' ], PHP_INT_MAX, 2 );

		if ( $this->active ) {
			add_filter( 'comment_form_submit_button', [ $this, 'add_captcha' ], 10, 2 );
		}

		add_filter( 'pre_comment_approved', [ $this, 'verify' ], 10, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string $submit_button HTML markup for the submit button.
	 * @param array  $args          Arguments passed to comment_form().
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $submit_button, $args ) {
		return (
			hcap_form( self::ACTION, self::NONCE ) .
			$submit_button
		);
	}

	/**
	 * Add origin.
	 *
	 * @param string $submit_button HTML markup for the submit button.
	 * @param array  $args          Arguments passed to comment_form().
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_origin( $submit_button, $args ) {
		if ( false !== strpos( $submit_button, Origin::NAME ) ) {
			return $submit_button;
		}

		$wp_comment_form = isset( $args['id_submit'] ) && ( 'submit' === $args['id_submit'] );

		$origin = $this->active && $wp_comment_form ?
			Origin::create( self::ACTION, self::NONCE ) :
			Origin::create();

		return $origin . $submit_button;
	}

	/**
	 * Verify comment.
	 *
	 * @param int|string|WP_Error $approved    The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param array               $commentdata Comment data.
	 *
	 * @return int|string|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $approved, $commentdata ) {
		if ( is_admin() ) {
			return $approved;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$origin_id = isset( $_POST[ Origin::NAME ] ) ?
			sanitize_text_field( wp_unslash( $_POST[ Origin::NAME ] ) ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$origin_data = Origin::get_verification_data( $origin_id );

		if ( false === $origin_data ) {
			// A hacking attempt. The comment form must have known origin here.
			return $this->invalid_captcha_error( $approved );
		}

		if ( '' === $origin_data['action'] && '' === $origin_data['nonce'] ) {
			// Reduce transient size.
			Origin::delete( $origin_id );

			// We do not need to verify hCaptcha for this form.
			return $approved;
		}

		$error_message = hcaptcha_get_verify_message_html( $origin_data['nonce'], $origin_data['action'] );

		if ( null !== $error_message ) {
			return $this->invalid_captcha_error( $approved );
		}

		// Reduce transient size.
		Origin::delete( $origin_id );

		return $approved;
	}

	/**
	 * Invalid captcha error.
	 *
	 * @param int|string|WP_Error $approved The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 *
	 * @return WP_Error
	 */
	private function invalid_captcha_error( $approved ) {
		$approved = is_wp_error( $approved ) ? $approved : new WP_Error();

		$approved->add( 'invalid_hcaptcha', __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' ), 400 );

		return $approved;
	}
}
