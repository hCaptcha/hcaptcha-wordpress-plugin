<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tutor;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class LostPassword.
 */
class LostPassword {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_tutor_lost_password';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_tutor_lost_password_nonce';

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
		add_action( 'tutor_lostpassword_form', [ $this, 'add_hcaptcha' ] );
		add_filter( 'tutor_before_retrieve_password_form_process', [ $this, 'verify' ] );
	}

	/**
	 * Add hCaptcha to the Lost Password form.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		$args = [
			'action' => HCAPTCHA_ACTION,
			'name'   => HCAPTCHA_NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'lost_password',
			],
		];

		?>
		<div class="tutor-form-row">
			<div class="tutor-form-col-12">
				<div class="tutor-form-group">
					<?php HCaptcha::form_display( $args ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param WP_Error|null|mixed $errors A WP_Error object containing any errors encountered during registration.
	 * @return WP_Error|null|mixed
	 */
	public function verify( $errors ) {
		$error_message = hcaptcha_verify_post(
			self::NONCE,
			self::ACTION
		);

		if ( ! $error_message ) {
			return $errors;
		}

		return HCaptcha::add_error_message( $errors, $error_message );
	}
}
