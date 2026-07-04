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
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Error;

/**
 * Class Base.
 */
abstract class Base {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-give-wp';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaGiveWPObject';

	/**
	 * Form ID.
	 *
	 * @var int
	 */
	private int $form_id;

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

		add_filter( 'hcap_print_hcaptcha_scripts', '__return_true', 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'print_footer_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
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

		$error_message = $this->verify_entry();

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

		$error_message = $this->verify_entry( false );

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
	 * Verify entry.
	 *
	 * @param bool $check_nonce Whether to check nonce.
	 *
	 * @return string|null
	 */
	private function verify_entry( bool $check_nonce = true ): ?string {
		return API::verify( $this->get_entry( $check_nonce ) );
	}

	/**
	 * Get entry.
	 *
	 * @param bool $check_nonce Whether to check nonce.
	 *
	 * @return array
	 */
	private function get_entry( bool $check_nonce = true ): array {
		return [
			'nonce_name'         => $check_nonce ? static::NAME : null,
			'nonce_action'       => $check_nonce ? static::ACTION : null,
			'h-captcha-response' => Request::filter_input( INPUT_POST, 'h-captcha-response' ),
			'expected_id'        => $this->get_expected_id( $this->get_form_id() ),
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array
	 */
	private function get_expected_id( int $form_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => $form_id,
		];
	}

	/**
	 * Get form id.
	 *
	 * @return int
	 */
	private function get_form_id(): int {
		$form_id = absint( Request::filter_input( INPUT_POST, 'give-form-id' ) );

		if ( $form_id ) {
			return $form_id;
		}

		$form_id = absint( Request::filter_input( INPUT_POST, 'formId' ) );

		if ( $form_id ) {
			return $form_id;
		}

		return absint( Request::filter_input( INPUT_POST, 'form-id' ) );
	}

	/**
	 * Print footer scripts.
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
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
			self::HANDLE,
			self::OBJECT,
			[
				'hcaptchaForm' => wp_json_encode( HCaptcha::form( $args ) ),
			]
		);
	}

	/**
	 * Add the type="module" attribute to the script tag.
	 *
	 * @param string|mixed $tag    Script tag.
	 * @param string       $handle Script handle.
	 * @param string       $src    Script source.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_type_module( $tag, string $handle, string $src ): string {
		$tag = (string) $tag;

		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
