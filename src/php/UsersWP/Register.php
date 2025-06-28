<?php
/**
 * 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UsersWP;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

/**
 * Class Register
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_users_wp_register';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_users_wp_register_nonce';

	/**
	 * UsersWP action.
	 */
	private const USERS_WP_ACTION = 'register';

	/**
	 * Class constructor.
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
		add_action( 'uwp_template_before', [ $this, 'uwp_template_before' ] );
		add_action( 'uwp_template_after', [ $this, 'uwp_template_after' ] );
		add_filter( 'uwp_validate_result', [ $this, 'verify' ], 10, 3 );
		add_action( 'wp_enqueue_scripts', [ Common::class, 'enqueue_scripts' ] );
	}

	/**
	 * Start the output buffer at the beginning of the template.
	 *
	 * @param string $name Template name.
	 *
	 * @return void
	 */
	public function uwp_template_before( string $name ): void {
		if ( self::USERS_WP_ACTION !== $name ) {
			return;
		}

		ob_start();
	}

	/**
	 * Get the output buffer at the end of the template and add captcha.
	 *
	 * @param string $name Template name.
	 *
	 * @return void
	 */
	public function uwp_template_after( string $name ): void {
		if ( self::USERS_WP_ACTION !== $name ) {
			return;
		}

		$template = (string) ob_get_clean();

		$args     = [
			'action' => static::ACTION,
			'name'   => static::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'register',
			],
		];
		$hcaptcha = HCaptcha::form( $args );
		$search   = '/(<(?:input|button) type="submit")/';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo preg_replace( $search, $hcaptcha . '$1', $template );
	}

	/**
	 * Verify register form.
	 *
	 * @param array|WP_Error|mixed $result Validation result.
	 * @param string               $action Action name.
	 * @param array|mixed          $data   POST data.
	 *
	 * @return WP_User|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $result, string $action, $data ) {
		if ( self::USERS_WP_ACTION !== $action ) {
			return $result;
		}

		$error_message = API::verify_post_html( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return $result;
		}

		return new WP_Error( 'invalid_hcaptcha', $error_message, 400 );
	}
}
