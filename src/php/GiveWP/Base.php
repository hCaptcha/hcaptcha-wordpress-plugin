<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\GiveWP;

use Give\DonationForms\ValueObjects\DonationFormErrorTypes;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Error;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Block script handle.
	 */
	private const BLOCK_HANDLE = 'hcaptcha-wc-block-checkout';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaGiveWPObject';

	/**
	 * Form ID.
	 *
	 * @var int
	 */
	private $form_id;

	/**
	 * Base constructor.
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
		add_action( static::ADD_CAPTCHA_HOOK, [ $this, 'add_captcha' ] );
		add_action( static::VERIFY_HOOK, [ $this, 'verify' ] );

		add_action( 'template_redirect', [ $this, 'verify_block' ], 9 );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$givewp_route = isset( $_GET['givewp-route'] )
			? sanitize_text_field( wp_unslash( $_GET['givewp-route'] ) )
			: '';
		$form_id      = isset( $_GET['form-id'] )
			? absint( wp_unslash( $_GET['form-id'] ) )
			: 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'donation-form-view' !== $givewp_route || ! $form_id ) {
			return;
		}

		$this->form_id = $form_id;

		add_filter( 'hcap_print_hcaptcha_scripts', '__return_true' );
		add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 9 );
	}

	/**
	 * Add captcha to the form.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	public function add_captcha( int $form_id ): void {
		$args = [
			'action' => static::ACTION,
			'name'   => static::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Verify captcha.
	 *
	 * @param bool|array $valid_data Validate fields.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid_data ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

		if ( 'give_process_donation' !== $action ) {
			return;
		}

		$error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null !== $error_message ) {
			give_set_error( 'invalid_hcaptcha', $error_message );
		}
	}

	/**
	 * Verify hCaptcha in the GiveWP block.
	 *
	 * @return void
	 */
	public function verify_block(): void {
		if ( ! Request::is_post() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$givewp_route = isset( $_GET['givewp-route'] )
			? sanitize_text_field( wp_unslash( $_GET['givewp-route'] ) )
			: '';

		$givewp_route_signature_id = isset( $_GET['givewp-route-signature-id'] )
			? sanitize_text_field( wp_unslash( $_GET['givewp-route-signature-id'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		if ( 'donate' !== $givewp_route || 'givewp-donate' !== $givewp_route_signature_id ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$error_message = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $error_message ) {
			return;
		}

		wp_send_json_error(
			[
				'type'   => DonationFormErrorTypes::VALIDATION,
				'errors' => new WP_Error( DonationFormErrorTypes::GATEWAY, $error_message ),
			]
		);
	}

	/**
	 * Print footer scripts.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::BLOCK_HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-givewp$min.js",
			[ 'wp-blocks', 'hcaptcha' ],
			HCAPTCHA_VERSION,
			true
		);

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $this->form_id,
			],
		];

		wp_localize_script(
			self::BLOCK_HANDLE,
			self::OBJECT,
			[
				'hcaptchaForm' => wp_json_encode( HCaptcha::form( $args ) ),
			]
		);
	}
}
