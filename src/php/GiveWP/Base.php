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
use Give\Helpers\Form\Utils as FormUtils;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
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
		add_action( 'wp_ajax_give_paypal_commerce_create_order', [ $this, 'verify_paypal_order' ], 0 );
		add_action( 'wp_ajax_nopriv_give_paypal_commerce_create_order', [ $this, 'verify_paypal_order' ], 0 );
		add_action( 'hcap_givewp_paypal_cleanup', [ PayPalGrant::class, 'cleanup' ] );

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
		add_action( 'givewp_donation_form_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
	 * @param bool|array|null $valid_data Validate fields on legacy callbacks.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $valid_data = null ): void {
		$error_message = $this->verify_entry();

		if ( null !== $error_message ) {
			give_set_error( 'invalid_hcaptcha', $error_message );
		}
	}

	/**
	 * Verify the native GiveWP nonce and hCaptcha before PayPal creates an order.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify_paypal_order(): void {
		$form_id   = absint( Request::filter_input( INPUT_POST, 'give-form-id' ) );
		$form_hash = Request::filter_input( INPUT_POST, 'give-form-hash' );

		if (
			! $form_id ||
			! is_string( $form_hash ) ||
			! function_exists( 'give_verify_donation_form_nonce' ) ||
			! give_verify_donation_form_nonce( $form_hash, $form_id )
		) {
			wp_die();
		}

		$block = class_exists( FormUtils::class ) && method_exists( FormUtils::class, 'isV3Form' ) && FormUtils::isV3Form( $form_id );
		$entry = $this->get_entry();

		if ( ! HCaptcha::is_protection_enabled( $entry['expected_id'] ) ) {
			return;
		}

		$binding = $this->get_paypal_binding( $form_id, $block, true );

		if ( null === $binding ) {
			$this->reject_paypal_order( hcap_get_error_messages()['fail'] );
		}

		$error = API::verify( $entry );

		if ( null !== $error ) {
			$this->reject_paypal_order( $error );
		}

		$siteverify = API::get_siteverify_response();

		if (
			true !== ( $siteverify['success'] ?? false ) ||
			! PayPalGrant::issue( $binding['response'], $binding, $siteverify )
		) {
			$this->reject_paypal_order( hcap_get_error_messages()['fail'] );
		}
	}

	/**
	 * Verify hCaptcha in the GiveWP block.
	 *
	 * @return void
	 */
	public function verify_block(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$givewp_route = isset( $_GET['givewp-route'] )
			? sanitize_text_field( wp_unslash( $_GET['givewp-route'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		if ( 'donate' !== $givewp_route ) {
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
		$entry = $this->get_entry( $check_nonce );
		$block = ! $check_nonce;

		if ( $this->is_paypal_order_donation( $block ) && HCaptcha::is_protection_enabled( $entry['expected_id'] ) ) {
			$form_id = $this->get_form_id( $block );
			$binding = $this->get_paypal_binding( $form_id, $block, false );
			$proof   = null === $binding ? null : PayPalGrant::redeem( $binding['response'], $binding );

			return null === $proof
				? hcap_get_error_messages()['fail']
				: API::verify_preverified( $entry, $proof );
		}

		return API::verify( $entry );
	}

	/**
	 * Identify the final, one-time PayPal order flow.
	 *
	 * @param bool $block Whether this is a block donation.
	 *
	 * @return bool
	 */
	private function is_paypal_order_donation( bool $block ): bool {
		$gateway = $block ? $this->request_value( 'gatewayId' ) : Request::filter_input( INPUT_POST, 'give-gateway' );

		if ( 'paypal-commerce' !== $gateway ) {
			return false;
		}

		if ( $block ) {
			$gateway_data = $this->request_value( 'gatewayData' );

			return is_array( $gateway_data ) && ! empty( $gateway_data['payPalOrderId'] );
		}

		return '' !== Request::filter_input( INPUT_POST, 'payPalOrderId' );
	}

	/**
	 * Bind one CAPTCHA response to the same donor, amount and signed form.
	 *
	 * @param int  $form_id   Native form ID.
	 * @param bool $block     Whether the form is a v3 block.
	 * @param bool $preflight Whether this is the createOrder request.
	 *
	 * @return array|null Canonical binding or null on malformed input.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_paypal_binding( int $form_id, bool $block, bool $preflight ): ?array {
		$email    = $preflight || ! $block ? Request::filter_input( INPUT_POST, 'give_email' ) : $this->request_value( 'email' );
		$amount   = $preflight ? Request::filter_input( INPUT_POST, 'amount' ) : $this->request_value( 'amount' );
		$amount   = $block
			? $amount
			: Request::filter_input( INPUT_POST, 'give-amount' );
		$response = Request::filter_input( INPUT_POST, 'h-captcha-response' );
		$widget   = Request::filter_input( INPUT_POST, HCaptcha::HCAPTCHA_WIDGET_ID );
		$fst      = Request::filter_input( INPUT_POST, 'hcap_fst_token' );
		$mode     = Request::filter_input( INPUT_POST, 'give_payment_mode' );

		if (
			! $form_id ||
			! is_string( $email ) || '' === $email ||
			! is_string( $amount ) || '' === $amount ||
			! is_string( $response ) || '' === $response ||
			! is_string( $widget ) || '' === $widget ||
			! is_string( $fst ) ||
			( $preflight && '' !== $mode && 'paypal-commerce' !== $mode )
		) {
			return null;
		}

		if ( $preflight && ! $this->has_paypal_preflight_identity( $form_id, $email, $block ) ) {
			return null;
		}

		$amount = give_maybe_sanitize_amount( $amount, [ 'currency' => give_get_currency( $form_id ) ] );

		if ( ! is_numeric( $amount ) || (float) $amount <= 0 ) {
			return null;
		}

		$session  = is_user_logged_in() && function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
		$settings = hcaptcha()->settings();
		$fst_on   = $settings->is_on( 'set_min_submit_time' );

		return [
			'form_id'  => $form_id,
			'email'    => $email,
			'amount'   => (string) $amount,
			'gateway'  => 'paypal-commerce',
			'widget'   => $widget,
			'response' => $response,
			'fst'      => $fst,
			'fst_on'   => $fst_on,
			'fst_min'  => $fst_on ? $settings->get( 'min_submit_time' ) : '',
			'user_id'  => get_current_user_id(),
			'session'  => $session,
		];
	}

	/**
	 * Match GiveWP's native identity and gateway before PayPal order creation.
	 *
	 * @param int    $form_id Native form ID.
	 * @param string $email   Donor email.
	 * @param bool   $block   Whether the form is a v3 block.
	 *
	 * @return bool Whether native PayPal fields match the signed form.
	 */
	private function has_paypal_preflight_identity( int $form_id, string $email, bool $block ): bool {
		if ( ! $block ) {
			return $this->has_legacy_paypal_gateway();
		}

		$native_email = Request::filter_input( INPUT_POST, 'email' );
		$native_form  = absint( Request::filter_input( INPUT_POST, 'formId' ) );
		$gateway      = Request::filter_input( INPUT_POST, 'gatewayId' );

		return $email === $native_email && $form_id === $native_form && 'paypal-commerce' === $gateway;
	}

	/**
	 * Match GiveWP's native legacy gateway fields at PayPal order creation.
	 *
	 * @return bool Whether the native gateway is PayPal Commerce.
	 */
	private function has_legacy_paypal_gateway(): bool {
		$gateway = Request::filter_input( INPUT_POST, 'give-gateway' );
		$mode    = Request::filter_input( INPUT_POST, 'payment-mode' );

		return 'paypal-commerce' === $gateway && ( '' === $mode || 'paypal-commerce' === $mode );
	}

	/**
	 * Read GiveWP's merged v3 request field.
	 *
	 * @param string $name Field name.
	 *
	 * @return array|string
	 */
	private function request_value( string $name ) {
		// GiveWP merges form fields into this request before routing donations.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$value = $_REQUEST[ $name ] ?? '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		if ( is_array( $value ) ) {
			return map_deep( wp_unslash( $value ), 'sanitize_text_field' );
		}

		return is_scalar( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
	}

	/**
	 * Return a native-compatible AJAX error before GiveWP reaches PayPal.
	 *
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	private function reject_paypal_order( string $message ): void {
		wp_send_json_error( [ 'error' => $message ], 403 );
	}

	/**
	 * Get entry.
	 *
	 * @param bool $check_nonce Whether to check nonce.
	 *
	 * @return array
	 */
	private function get_entry( bool $check_nonce = true ): array {
		$data = EntryData::from_post(
			[
				'email'      => $check_nonce ? [ 'give_email', 'email' ] : [ 'email', 'give_email' ],
				'first_name' => [ 'give_first', 'firstName' ],
				'last_name'  => [ 'give_last', 'lastName' ],
			]
		);

		// The block route validates the canonical email from GiveWP's merged request data.
		// The nonce is checked by API::verify() after this data is collected.
		$emails = [];
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		if ( ! $check_nonce && array_key_exists( 'email', $_REQUEST ) ) {
			$emails[] = is_scalar( $_REQUEST['email'] )
				? sanitize_text_field( wp_unslash( $_REQUEST['email'] ) )
				: '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		$entry = [
			'nonce_name'         => $check_nonce ? static::NAME : null,
			'nonce_action'       => $check_nonce ? static::ACTION : null,
			'h-captcha-response' => Request::filter_input( INPUT_POST, 'h-captcha-response' ),
			'data'               => $data,
			'expected_id'        => $this->get_expected_id( $this->get_form_id( ! $check_nonce ) ),
		];

		return $check_nonce ? $entry : EntryData::with_emails( $entry, $emails );
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
	 * Get form id. The block donation route uses formId as its canonical form identity.
	 *
	 * @param bool $block Whether this is a block donation request.
	 *
	 * @return int
	 */
	private function get_form_id( bool $block = false ): int {
		if ( $block ) {
			// GiveWP reads merged request data, where POST formId overrides GET formId.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			return isset( $_POST['formId'] )
				? absint( Request::filter_input( INPUT_POST, 'formId' ) )
				: absint( Request::filter_input( INPUT_GET, 'formId' ) );
		}

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
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$min          = hcap_min_suffix();
		$dependencies = [ 'wp-blocks', 'hcaptcha' ];
		$settings     = hcaptcha()->settings();

		// GiveWP pins its script queue before footer hooks run, so expose the optional FST script as a dependency.
		if ( $settings && $settings->is_on( 'set_min_submit_time' ) ) {
			$dependencies[] = 'hcaptcha-fst';
		}

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-givewp$min.js",
			$dependencies,
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
