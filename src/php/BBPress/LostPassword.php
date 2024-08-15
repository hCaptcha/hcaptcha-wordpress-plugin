<?php
/**
 * Lost Password class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class LostPassword.
 */
class LostPassword {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_filter( 'do_shortcode_tag', [ $this, 'add_captcha' ], 10, 4 );

		if ( ! hcaptcha()->settings()->is( 'bbp_status', 'lost_pass' ) ) {
			add_filter( 'hcap_protect_form', [ $this, 'hcap_protect_form' ], 10, 3 );
		}
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $output, string $tag, $attr, array $m ) {
		if ( 'bbp-lost-pass' !== $tag || is_user_logged_in() ) {
			return $output;
		}

		$args = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'auto'   => true,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$pattern     = '/(<button [\s\S]*?type="submit")/';
		$replacement = $hcaptcha . "\n$1";
		$output      = (string) preg_replace( $pattern, $replacement, $output );

		/** This action is documented in src/php/Sendinblue/Sendinblue.php */
		do_action( 'hcap_auto_verify_register', $output );

		// Insert hCaptcha.
		return $output;
	}

	/**
	 * Protect form filter.
	 * We need it to ignore auto verification of the Lost Password form when its option is off.
	 *
	 * @param bool|mixed $value   The protection status of a form.
	 * @param string[]   $source  The source of the form (plugin, theme, WordPress Core).
	 * @param int|string $form_id Form id.
	 *
	 * @return bool
	 */
	public function hcap_protect_form( $value, array $source, $form_id ): bool {
		if (
			'lost_password' === $form_id &&
			HCaptcha::get_class_source( __CLASS__ ) === $source
		) {
			return false;
		}

		return (bool) $value;
	}
}
