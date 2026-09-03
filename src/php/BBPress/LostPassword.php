<?php
/**
 * Lost Password class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Abstracts\FormOwnerBase;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class LostPassword.
 */
class LostPassword extends FormOwnerBase {

	/**
	 * Request owner filter.
	 */
	protected const OWNER_FILTER = 'hcap_lost_password_request_owner';

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
		$this->init_owner_hooks();

		add_filter( 'do_shortcode_tag', [ $this, 'add_captcha' ], 10, 4 );
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
	 * Get expected hCaptcha widget ID.
	 *
	 * @return array
	 */
	protected function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'lost_password',
		];
	}

	/**
	 * Whether the current request has the lost-password action.
	 *
	 * @return bool
	 */
	protected function is_owner_action(): bool {
		return $this->is_wp_login_action( 'lostpassword' );
	}
}
