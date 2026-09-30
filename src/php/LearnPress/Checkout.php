<?php
/**
 * The Checkout class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\LearnPress;

use Exception;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Checkout
 */
class Checkout {
	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-learnpress';

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_learn_press_checkout';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_learn_press_checkout_nonce';

	/**
	 * Whether the checkout form was shown.
	 *
	 * @var bool
	 */
	private bool $form_shown = false;

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
		add_action( 'learn-press/payment-form', [ $this, 'add_hcaptcha' ] );
		add_action( 'learn-press/before-checkout', [ $this, 'verify' ], 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Add hCaptcha.
	 */
	public function add_hcaptcha(): void {
		$this->form_shown = true;

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'checkout',
			],
		];

		HCaptcha::form_display( $args );
	}

	/**
	 * Enqueue LearnPress script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-learnpress$min.js",
			[],
			HCAPTCHA_VERSION,
			true
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

	/**
	 * Verify the checkout form.
	 *
	 * @return void
	 * @throws Exception When hCaptcha verification fails.
	 * @noinspection ThrowRawExceptionInspection
	 */
	public function verify(): void {
		$error_message = API::verify_post( self::NONCE, self::ACTION );

		if ( null !== $error_message ) {
			throw new Exception( esc_html( $error_message ) );
		}
	}
}
