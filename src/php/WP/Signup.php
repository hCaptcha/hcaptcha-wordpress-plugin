<?php
/**
 * 'Signup' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Signup
 */
class Signup {
	use Base;

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_signup';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_signup_nonce';

	/**
	 * WP signup action.
	 */
	private const WP_SIGNUP_ACTION = 'signup';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	private $error_message;

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
	private function init_hooks(): void {
		add_action( 'before_signup_form', [ $this, 'before_signup_form' ] );
		add_action( 'after_signup_form', [ $this, 'add_captcha' ] );
		add_filter( 'wpmu_validate_user_signup', [ $this, 'verify' ] );
		add_filter( 'wpmu_validate_blog_signup', [ $this, 'verify' ] );
	}

	/**
	 * Before signup form.
	 *
	 * @return void
	 */
	public function before_signup_form(): void {
		ob_start();
	}

	/**
	 * Add captcha.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		$content = ob_get_clean();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'signup',
			],
		];

		HCaptcha::form( $args );

		$search  = '<p class="submit">';
		$content = str_replace( $search, $this->get_error_html() . HCaptcha::form( $args ) . $search, $content );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
	}

	/**
	 * Verify signup hCaptcha.
	 *
	 * @param array|mixed $result Signup validation result.
	 *
	 * @return array
	 */
	public function verify( $result ): array {
		$result           = (array) $result;
		$result['errors'] = is_wp_error( $result['errors'] ) ? $result['errors'] : new WP_Error();

		$this->error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null === $this->error_message ) {
			return $result;
		}

		$result['errors'] = HCaptcha::add_error_message( $result['errors'], $this->error_message );

		return $result;
	}

	/**
	 * Get error HTML.
	 *
	 * @return string
	 */
	public function get_error_html(): string {
		if ( null === $this->error_message ) {
			return '';
		}

		return '<p class="error" id="wp-signup-hcaptcha-error"><strong>Error:</strong>' . $this->error_message . '</p>';
	}
}
