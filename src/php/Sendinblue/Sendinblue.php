<?php
/**
 * Sendinblue class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Sendinblue;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use SIB_Forms;
use SIB_Widget_Subscribe;
use WP_Widget;

/**
 * Class Sendinblue.
 */
class Sendinblue {
	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_sendinblue';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_sendinblue_nonce';

	/**
	 * Script handle.
	 */
	public const HANDLE = 'hcaptcha-sendinblue';

	/**
	 * Sendinblue constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'init', [ $this, 'verify_signup' ], 9 );
		add_filter( 'do_shortcode_tag', [ $this, 'add_hcaptcha' ], 10, 4 );
		add_filter( 'widget_display_callback', [ $this, 'add_hcaptcha_widget' ], PHP_INT_MAX, 3 );
		add_filter( 'hcap_auto_verify_entry', [ $this, 'adapt_auto_entry' ] );
		add_filter( 'hcap_auto_verify_unmatched_form', [ $this, 'adapt_unmatched_form' ] );
		add_filter( 'hcap_verify_request', [ $this, 'verify_request' ], 10, 2 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
	}

	/**
	 * Filters the output created by a shortcode callback and adds hcaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function add_hcaptcha( $output, string $tag, $attr, array $m ) {
		if ( 'sibwp_form' !== $tag ) {
			return $output;
		}

		// The native renderer resolves legacy and translated selectors before publishing this field.
		$form_id = (int) ( ( (array) $attr )['id'] ?? 0 );
		if ( preg_match( '/<input\b[^>]*\bname=["\']sib_form_id["\'][^>]*\bvalue=["\']([^"\']*)["\']/i', (string) $output, $match ) ) {
			$form_id = (int) html_entity_decode( $match[1], ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'auto'   => true,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $form_id,
			],
		];

		$hcaptcha = HCaptcha::form( $args );

		$output = (string) preg_replace(
			'/(<input type="submit"|<button .*?type="submit".*?>)/',
			$hcaptcha . '$1',
			(string) $output
		);

		/**
		 * Register a form containing hCaptcha for auto-verification purposes.
		 *
		 * @param string $output HTML content.
		 */
		do_action( 'hcap_auto_verify_register', $output );

		return $output;
	}

	/**
	 * Add the same challenge to Brevo widgets, which bypass the shortcode filter.
	 *
	 * @param array|false $instance Widget settings, or a previously suppressed widget.
	 * @param WP_Widget   $widget   Native widget.
	 * @param array       $args     Sidebar arguments.
	 *
	 * @return array|false
	 */
	public function add_hcaptcha_widget( $instance, WP_Widget $widget, array $args ) {
		if ( false === $instance || ! $widget instanceof SIB_Widget_Subscribe ) {
			return $instance;
		}

		$is_preview                   = $widget->is_preview();
		$was_cache_addition_suspended = wp_suspend_cache_addition();
		if ( $is_preview && ! $was_cache_addition_suspended ) {
			wp_suspend_cache_addition( true );
		}

		ob_start();
		try {
			$widget->widget( $args, $instance );
		} finally {
			$output = (string) ob_get_clean();
			if ( $is_preview ) {
				wp_suspend_cache_addition( $was_cache_addition_suspended );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Native widget markup with generated hCaptcha fields.
		echo $this->add_hcaptcha( $output, 'sibwp_form', [], [] );

		return false;
	}

	/**
	 * Verify the native signup before Brevo processes it, independently of URL registration.
	 *
	 * @return void
	 */
	public function verify_signup(): void {
		$entry = $this->get_signup_entry();
		if ( null === $entry ) {
			return;
		}

		$this->verify_request( API::verify( $entry ), [] );
	}

	/**
	 * Match Brevo's native dispatch before its later POST unslashing.
	 *
	 * @return bool
	 */
	public static function is_signup_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$action = isset( $_POST['sib_form_action'] ) ? sanitize_text_field( $_POST['sib_form_action'] ) : '';

		return 'subscribe_form_submit' === $action;
	}

	/**
	 * Bind an earlier automatic check to the same native signup identity and email.
	 *
	 * @param array $entry Automatic verification entry.
	 *
	 * @return array
	 */
	public function adapt_auto_entry( array $entry ): array {
		return $this->get_signup_entry() ?? $entry;
	}

	/**
	 * Defer unmatched route metadata to the unconditional native signup guard.
	 *
	 * @param array|null|mixed $registered_form Previously selected automatic form.
	 *
	 * @return array|null
	 */
	public function adapt_unmatched_form( $registered_form ): ?array {
		if ( ! is_array( $registered_form ) && ! is_null( $registered_form ) ) {
			$registered_form = null;
		}

		return null !== $this->get_signup_entry() ? null : $registered_form;
	}

	/**
	 * Resolve the form and email exactly as the native signup will consume them.
	 *
	 * @return array|null
	 */
	private function get_signup_entry(): ?array {
		if ( ! class_exists( '\SIB_Forms' ) || ! self::is_signup_request() ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Match native nonce verification without altering its value.
		$nonce = isset( $_POST['sib_security'] ) && is_string( $_POST['sib_security'] ) ? wp_unslash( $_POST['sib_security'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sib_front_ajax_nonce' ) ) {
			return null;
		}

		// Brevo validates this same nonce before reading its native form and fields.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$form_id = $this->get_signup_form_id();
		if ( null === $form_id ) {
			return null;
		}

		$email = isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			return null;
		}

		$data = EntryData::without_sensitive_fields( wp_unslash( $_POST ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return EntryData::with_emails(
			[
				'nonce_name'   => self::NONCE,
				'nonce_action' => self::ACTION,
				'expected_id'  => [
					'source'  => HCaptcha::get_class_source( __CLASS__ ),
					'form_id' => $form_id,
				],
				'data'         => $data,
			],
			[ $email ]
		);
	}

	/**
	 * Resolve the native selector to the stored form identity.
	 *
	 * @return int|null
	 */
	private function get_signup_form_id(): ?int {
		// The caller has verified the native signup nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sib_form_id'] ) && ! is_scalar( $_POST['sib_form_id'] ) ) {
			return null;
		}
		$selector = isset( $_POST['sib_form_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['sib_form_id'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( 'oldForm' === $selector ) {
			$selector = get_option( 'sib_old_form_id' );
		}

		// The native synthetic default has no stored ID; avoid its API-backed list lookup here.
		if ( 'new' === $selector ) {
			return 0;
		}

		$form = SIB_Forms::getForm( $selector );

		return $form ? (int) $form['id'] : null;
	}

	/**
	 * Verify the request filter.
	 *
	 * @param string|null $result      Result of the hCaptcha verification.
	 * @param array       $error_codes Error codes.
	 *
	 * @return string|null
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function verify_request( $result, array $error_codes ): ?string {
		// Nonce is checked in the \HCaptcha\Helpers\API::verify_post().

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['sib_form_action'] ) ) {
			// We are not in the Sendinblue submit request.
			return $result;
		}

		if ( null !== $result ) {
			wp_send_json(
				[
					'status' => 'failure',
					'msg'    => [ 'errorMsg' => $result ],
				]
			);
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $result;
	}

	/**
	 * Enqueue scripts.
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
			HCAPTCHA_URL . "/assets/js/hcaptcha-sendinblue$min.js",
			[ 'jquery', 'hcaptcha' ],
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

		if ( static::HANDLE !== $handle ) {
			return $tag;
		}

		return HCaptcha::add_type_module( $tag );
	}
}
