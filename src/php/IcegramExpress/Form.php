<?php
/**
 * 'Form' class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\IcegramExpress;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {

	/**
	 * Verify action.
	 */
	private const ACTION = 'hcaptcha_icegram_express';

	/**
	 * Verify nonce.
	 */
	private const NONCE = 'hcaptcha_icegram_express_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'ig_es_validate_subscription', [ $this, 'verify' ], 10, 2 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hCaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, string $tag, $attr, array $m ): string {
		$output = (string) $output;

		if ( 'email-subscribers-form' !== $tag ) {
			return $output;
		}

		$form_id = (int) ( $attr['id'] ?? 0 );

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( self::class ),
				'form_id' => $form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$search = '<input type="submit"';

		return str_replace( $search, "\n$hcaptcha\n" . $search, $output );
	}

	/**
	 * Verify.
	 *
	 * @param array|mixed $result Result.
	 * @param array       $form   Form.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify( $result, array $form ): array {
		$result = (array) $result;

		$status = $result['status'] ?? 'ERROR';

		if ( 'ERROR' === $status ) {
			return $result;
		}

		$error_message = API::verify_request();

		if ( null !== $error_message ) {
			$error_code = 'hcaptcha_error';

			add_filter(
				'ig_es_subscription_messages',
				static function ( $messages ) use ( $error_code, $error_message ) {
					$messages[ $error_code ] = $error_message;

					return $messages;
				}
			);

			return [
				'status'  => 'ERROR',
				'message' => $error_code,
			];
		}

		return $result;
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
	.emaillist .h-captcha {
		margin-bottom: 0.6em;
	}

	.emaillist form[data-form-id="3"] .h-captcha {
		margin: 0 auto 0.6em;;
	}
';

		HCaptcha::css_display( $css );
	}
}
