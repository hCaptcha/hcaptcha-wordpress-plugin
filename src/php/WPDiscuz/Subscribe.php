<?php
/**
 * The 'Subscribe' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Subscribe.
 */
class Subscribe extends Base {
	/**
	 * Native subscription reCAPTCHA flag.
	 */
	protected const RECAPTCHA_OPTIONS = [ 'isShowOnSubscribeForm' ];

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wpdiscuz_after_subscription_form', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_action( 'wp_ajax_wpdAddSubscription', [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_wpdAddSubscription', [ $this, 'verify' ], 9 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Replaces reCaptcha field by hCaptcha in wpDiscuz form.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		global $post;

		$args = [
			'id' => $this->get_id( $post->ID ?? 0 ),
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify request.
	 *
	 * @return void
	 */
	public function verify(): void {
		// Nonce is checked by wpDiscuz.

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$post_id     = isset( $_POST['postId'] ) && is_scalar( $_POST['postId'] ) ?
			(int) filter_var( wp_unslash( $_POST['postId'] ), FILTER_SANITIZE_NUMBER_INT ) : 0;
		$expected_id = $this->get_id( $post_id );
		$user        = wp_get_current_user();
		$email       = $user->ID ? $user->user_email : '';

		if ( ! $user->ID && isset( $_POST['wpdiscuzSubscriptionEmail'] ) && is_string( $_POST['wpdiscuzSubscriptionEmail'] ) ) {
			// wpDiscuz sanitizes first and then unslashes the subscription address.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$email = wp_unslash( sanitize_text_field( $_POST['wpdiscuzSubscriptionEmail'] ) );
		}

		$entry  = EntryData::with_emails(
			[
				'expected_id'        => $expected_id,
				'h-captcha-response' => $hcaptcha_response,
				'data'               => EntryData::from_post( [] ),
			],
			[ $email ]
		);
		$result = API::verify( $entry );

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( null === $result ) {
			return;
		}

		wp_send_json_error( $result );
	}

	/**
	 * Get the widget identity for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private function get_id( int $post_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => $post_id,
		];
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	#wpdiscuz-subscribe-form .h-captcha {
		margin-top: 5px;
		margin-left: auto;
	}
';

		HCaptcha::css_display( $css );
	}
}
