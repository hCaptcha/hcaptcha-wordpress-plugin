<?php
/**
 * The 'Register' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Affiliates;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Register
 */
class Register {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_registration';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_registration_nonce';

	/**
	 * Affiliates dashboard registration section key.
	 */
	private const SECTION_KEY = 'registration';

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
		add_action( 'affiliates_dashboard_before_section', [ $this, 'before_section' ] );
		add_action( 'affiliates_dashboard_after_section', [ $this, 'after_section' ] );
		add_filter( 'affiliates_registration_error_validate', [ $this, 'verify' ] );
	}

	/**
	 * Before the dashboard section.
	 *
	 * @param string $current_section_key Current section key.
	 *
	 * @return void
	 */
	public function before_section( string $current_section_key ): void {
		if ( self::SECTION_KEY !== $current_section_key ) {
			return;
		}

		ob_start();
	}

	/**
	 * After dashboard section.
	 *
	 * @param string $current_section_key Current section key.
	 *
	 * @return void
	 */
	public function after_section( string $current_section_key ): void {
		if ( self::SECTION_KEY !== $current_section_key ) {
			return;
		}

		$this->show_error();

		$content = ob_get_clean();
		$args    = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_expected_id(),
		];
		$search  = '<input type="submit"';
		$replace = HCaptcha::form( $args ) . $search;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo str_replace( $search, $replace, $content );
	}

	/**
	 * Verify register captcha.
	 *
	 * @param bool|mixed $error Error status.
	 *
	 * @return bool
	 */
	public function verify( $error ): bool {
		$error = (bool) $error;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['affiliates-registration-submit'] ) ) {
			return $error;
		}

		// A logged-in affiliate registers with the stored address, not the readonly POST field.
		$email = '';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The API verifies the nonce below.
		if ( is_user_logged_in() ) {
			$email = wp_get_current_user()->user_email;
		} elseif ( isset( $_POST['user_email'] ) && is_scalar( $_POST['user_email'] ) ) {
			$email = sanitize_email( wp_unslash( (string) $_POST['user_email'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$this->get_expected_id(),
			EntryData::with_emails( [], [ $email ] )
		);

		return null !== $this->error_message;
	}

	/**
	 * Show hCaptcha error.
	 *
	 * @return void
	 */
	private function show_error(): void {
		if ( null === $this->error_message ) {
			return;
		}

		?>
		<div class="error">
			<strong>
				<?php echo esc_html__( 'ERROR', 'hcaptcha-for-forms-and-more' ) . ' : '; ?>
			</strong>
			<?php echo esc_html( $this->error_message ); ?>
		</div>
		<?php
	}

	/**
	 * Get the expected form identity.
	 *
	 * @return array
	 */
	private function get_expected_id(): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => 'register',
		];
	}
}
