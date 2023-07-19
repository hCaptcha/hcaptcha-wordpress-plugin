<?php
/**
 * Subscribe class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Subscribe.
 */
class Subscribe extends Base {

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'wpdiscuz_after_subscription_form', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_action( 'wp_ajax_wpdAddSubscription', [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_wpdAddSubscription', [ $this, 'verify' ], 9 );
	}

	/**
	 * Replaces reCaptcha field by hCaptcha in wpDiscuz form.
	 *
	 * @return void
	 */
	public function add_hcaptcha() {
		global $post;

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $post->ID ?? 0,
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify request.
	 *
	 * @return void
	 */
	public function verify() {
		// Nonce is checked by wpDiscuz.

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$result = hcaptcha_request_verify( $hcaptcha_response );

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( null === $result ) {
			return;
		}

		wp_send_json_error( $result );
	}
}
