<?php
/**
 * 'Protect' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Passster;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Protect
 */
class Protect {

	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-passster';

	/**
	 * Verify action.
	 */
	private const ACTION = 'hcaptcha_passster';

	/**
	 * Verify nonce.
	 */
	private const NONCE = 'hcaptcha_passster_nonce';

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
		add_filter( 'do_shortcode_tag', [ $this, 'do_shortcode_tag' ], 10, 4 );
		add_action( 'wp_ajax_validate_input', [ $this, 'verify' ], 9 );
		add_action( 'wp_ajax_nopriv_validate_input', [ $this, 'verify' ], 9 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function do_shortcode_tag( $output, string $tag, $attr, array $m ) {
		if ( 'passster' !== $tag ) {
			return $output;
		}

		$form_id = 0;

		if ( preg_match( '/data-area="(.+)"/i', $output, $m ) ) {
			$form_id = $m[1];
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'auto'   => true,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $form_id,
			],
		];

		$search  = '<button name="submit"';
		$replace = HCaptcha::form( $args ) . $search;

		$output = (string) str_replace(
			$search,
			$replace,
			(string) $output
		);

		/** This action is documented in src/php/Sendinblue/Sendinblue.php */
		do_action( 'hcap_auto_verify_register', $output );

		return $output;
	}

	/**
	 * Verify captcha.
	 *
	 * @param string $input Password input.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( string $input ): void {
		$error_message = hcaptcha_verify_post( self::NONCE, self::ACTION );

		if ( null === $error_message ) {
			return;
		}

		echo wp_json_encode( [ 'error' => $error_message ] );
		exit;
	}

	/**
	 * Enqueue script.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-passster$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Add type="module" attribute to script tag.
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

		if ( static::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	.passster-form .h-captcha {
		margin-bottom: 5px;
	}
';

		HCaptcha::css_display( $css );
	}
}
