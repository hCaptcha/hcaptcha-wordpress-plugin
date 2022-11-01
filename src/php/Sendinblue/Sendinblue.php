<?php
/**
 * Sendinblue class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Sendinblue;

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
	public function init_hooks() {
		add_action( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'hcap_verify_request', [ $this, 'verify_request' ], 10, 2 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hcaptcha.
	 *
	 * @param string       $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, $tag, $attr, $m ) {
		if ( 'sibwp_form' !== $tag ) {
			return $output;
		}

		$hcaptcha = hcap_form( HCAPTCHA_ACTION, HCAPTCHA_NONCE, true );

		return preg_replace( '/(<input type="submit")/', $hcaptcha . '$1', $output );
	}

	/**
	 * Verify request filter.
	 *
	 * @param string|null $result      Result of the hCaptcha verification.
	 * @param array       $error_codes Error codes.
	 *
	 * @return string|null
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_request( $result, $error_codes ) {
		// Nonce is checked in the hcaptcha_verify_post().

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['sib_form_action'] ) ) {
			// We are not in the Sendinblue submit.
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
