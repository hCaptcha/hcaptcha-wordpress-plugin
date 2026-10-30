<?php
/**
 * LostPassword class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Tutor;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData as FormEntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_Error;
use WP_User;

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
	 * Whether this request passed the native Tutor pre-recovery verifier.
	 *
	 * @var bool
	 */
	private bool $verified_recovery = false;

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
		add_action( 'lostpassword_post', [ $this, 'verify_recipient' ], 10, 2 );
	}

	/**
	 * Add hCaptcha to the Lost Password form.
	 *
	 * @return void
	 */
	public function add_hcaptcha(): void {
		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
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
		$error_message           = API::verify( $this->get_entry() );
		$this->verified_recovery = null === $error_message;

		if ( ! $error_message ) {
			return $errors;
		}

		return HCaptcha::add_error_message( $errors, $error_message );
	}

	/**
	 * Check the recipient resolved by WordPress after Tutor accepts the request.
	 *
	 * @param WP_Error           $errors Native recovery errors.
	 * @param WP_User|false|null $user   Native recovery recipient.
	 *
	 * @return void
	 */
	public function verify_recipient( WP_Error $errors, $user = null ): void {
		if ( ! $this->verified_recovery || ! $user instanceof WP_User ) {
			return;
		}

		$entry = FormEntryData::with_emails( $this->get_entry(), [ $user->user_email ] );
		HCaptcha::add_error_message( $errors, API::verify( $entry ) );
	}

	/**
	 * Get lost-password data for hCaptcha and anti-spam verification.
	 *
	 * @return array
	 */
	private function get_entry(): array {
		return FormEntryData::with_emails(
			[
				'nonce_name'   => self::NONCE,
				'nonce_action' => self::ACTION,
				'data'         => EntryData::lost_password(),
				'expected_id'  => $this->get_expected_id(),
			],
			[]
		);
	}

	/**
	 * Get the expected widget id.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'lost_password',
		];
	}
}
