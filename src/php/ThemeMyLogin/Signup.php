<?php
/**
 * 'Signup' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\ThemeMyLogin;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use Theme_My_Login_Form_Field;
use WP_Error;

/**
 * Class Signup
 */
class Signup {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_theme_my_login_signup';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_theme_my_login_signup_nonce';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	private ?string $error_message = null;

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
		add_filter( 'tml_before_form_field', [ $this, 'add_captcha' ], 10, 4 );
		add_filter( 'wpmu_validate_user_signup', [ $this, 'verify' ], 0 );
		add_filter( 'wpmu_validate_blog_signup', [ $this, 'verify' ], 0 );
	}

	/**
	 * Add hCaptcha.
	 *
	 * @param string|mixed              $output     The output.
	 * @param string                    $form_name  The form name.
	 * @param string                    $field_name The field name.
	 * @param Theme_My_Login_Form_Field $field      The form object.
	 *
	 * @return string
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $output, string $form_name, string $field_name, $field ): string {
		$output = (string) $output;

		if (
			'submit' !== $field_name ||
			! in_array( $form_name, [ 'user_signup', 'blog_signup' ], true ) ||
			! did_action( 'tml_render_form' )
		) {
			return $output;
		}

		$form = '';

		if ( hcaptcha()->settings()->is( 'theme_my_login_status', 'signup' ) ) {
			$args = [
				'action' => self::ACTION,
				'name'   => self::NONCE,
				'id'     => [
					'source'  => HCaptcha::get_class_source( __CLASS__ ),
					'form_id' => 'register',
				],
			];

			$form = HCaptcha::form( $args );
		}

		return $this->get_error_html() . $form . $output;
	}

	/**
	 * Verify signup hCaptcha.
	 *
	 * @param array|mixed $result Signup validation result.
	 *
	 * @return array
	 */
	public function verify( $result ): array {
		$result = (array) $result;

		if ( ! did_action( 'tml_action_signup' ) && ! did_action( 'tml_action_ajax_signup' ) ) {
			return $result;
		}

		$stage = Request::filter_input( INPUT_POST, 'stage' );

		if ( strpos( current_filter(), str_replace( '-', '_', $stage ) ) === false ) {
			return $result;
		}

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

		return '<span class="tml-error" id="wp-signup-hcaptcha-error">' . $this->error_message . '</span>';
	}
}
