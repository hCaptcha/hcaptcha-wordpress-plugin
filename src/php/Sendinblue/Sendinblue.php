<?php
/**
 * Sendinblue class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Sendinblue;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Sendinblue.
 */
class Sendinblue {

	/**
	 * Sendinblue constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_filter( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'hcap_verify_request', [ $this, 'verify_request' ], 10, 2 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hcaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, string $tag, $attr, array $m ) {
		if ( 'sibwp_form' !== $tag ) {
			return $output;
		}

		$args = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => true,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => (int) $attr['id'],
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$output = (string) preg_replace(
			'/(<input type="submit"|<button .*?type="submit".*?>)/',
			$hcaptcha . '$1',
			(string) $output
		);

		/**
		 * Register a form containing hCaptcha for auto-verification purposes.
		 *
		 * @param string $output HTML content.
		 */
		do_action( 'hcap_auto_verify_register', $output );

		return $output;
	}

	/**
	 * Verify request filter.
	 *
	 * @param string|null $result      Result of the hCaptcha verification.
	 * @param array       $error_codes Error codes.
	 *
	 * @return string|null
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function verify_request( $result, array $error_codes ): ?string {
		// Nonce is checked in the hcaptcha_verify_post().

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['sib_form_action'] ) ) {
			// We are not in the Sendinblue submit request.
			return $result;
		}

		if ( null !== $result ) {
			wp_send_json(
				[
					'status' => 'failure',
					'msg'    => [ 'errorMsg' => $result ],
				]
			);
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $result;
	}
}
