<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

use HCaptcha\Helpers\HCaptcha;
use WP_Error;

/**
 * Class Base
 */
abstract class Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_jetpack';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_jetpack_nonce';

	/**
	 * Admin script handle.
	 */
	private const ADMIN_HANDLE = 'admin-jetpack';

	/**
	 * Admin script object.
	 */
	private const OBJECT = 'HCaptchaJetpackObject';

	/**
	 * Error message.
	 *
	 * @var string|null
	 */
	protected $error_message;

	/**
	 * Errored form hash.
	 *
	 * @var string|null
	 */
	protected $error_form_hash;

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
		// This filter works for a Jetpack classic and block form on a page or in a template.
		add_filter( 'jetpack_contact_form_html', [ $this, 'add_hcaptcha' ] );

		// This filter works for a Jetpack form in a classic widget.
		add_filter( 'widget_text', [ $this, 'add_hcaptcha' ], 0 );

		add_filter( 'widget_text', 'shortcode_unautop' );
		add_filter( 'widget_text', 'do_shortcode' );

		add_filter( 'jetpack_contact_form_is_spam', [ $this, 'verify' ], 100, 2 );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );

		add_filter( 'the_content', [ $this, 'the_content_filter' ] );

		if ( ! $this->is_editing_jetpack_form_post() ) {
			return;
		}

		add_action( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Add hCaptcha to a Jetpack form.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	abstract public function add_hcaptcha( $content ): string;

	/**
	 * Verify hCaptcha answer from the Jetpack Contact Form.
	 *
	 * @param bool|mixed $is_spam Is spam.
	 *
	 * @return bool|WP_Error|mixed
	 */
	public function verify( $is_spam = false ) {
		$this->error_message = hcaptcha_get_verify_message(
			static::NAME,
			static::ACTION
		);

		if ( null === $this->error_message ) {
			return $is_spam;
		}

		$this->error_form_hash = $this->get_submitted_form_hash();

		$error = new WP_Error();

		$error->add( 'invalid_hcaptcha', $this->error_message );
		add_filter( 'hcap_hcaptcha_content', [ $this, 'error_message' ], 10, 2 );

		return $error;
	}

	/**
	 * Print error message.
	 *
	 * @param string|mixed $hcaptcha The hCaptcha form.
	 * @param array        $atts     The hCaptcha shortcode attributes.
	 *
	 * @return string|mixed
	 */
	public function error_message( $hcaptcha = '', array $atts = [] ) {
		if ( null === $this->error_message ) {
			return $hcaptcha;
		}

		$form_id  = $atts['id']['form_id'] ?? '';
		$hash     = str_replace( 'contact_', '', $form_id );
		$has_hash = $form_id !== $hash;

		if ( $has_hash && $hash !== $this->error_form_hash ) {
			return $hcaptcha;
		}

		$message = <<< HTML
<div class="contact-form__input-error">
	<span class="contact-form__warning-icon">
		<span class="visually-hidden">Warning.</span>
		<i aria-hidden="true"></i>
	</span>
	<span>$this->error_message</span>
</div>
HTML;

		return $hcaptcha . $message;
	}

	/**
	 * Print hCaptcha script when editing a page with Jetpack form.
	 *
	 * @param bool|mixed $status Current print status.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function print_hcaptcha_scripts( $status ): bool {
		return true;
	}

	/**
	 * Enqueue script in admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/admin-jetpack$min.js",
			[ 'hcaptcha' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				// We do not verify forms in admin, so no form hash is needed.
				'hCaptcha' => $this->get_hcaptcha( $this->get_args() ),
			]
		);
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol CssUnusedSymbol.
	 */
	public function print_inline_styles(): void {
		$css = <<<CSS
	form.contact-form .grunion-field-hcaptcha-wrap.grunion-field-wrap {
		flex-direction: row !important;
	}

	form.contact-form .grunion-field-hcaptcha-wrap.grunion-field-wrap .h-captcha,
	form.wp-block-jetpack-contact-form .grunion-field-wrap .h-captcha {
		margin-bottom: 0;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * The content filter.
	 *
	 * @param string|mixed $content Content.
	 *
	 * @return string
	 */
	public function the_content_filter( $content ): string {
		$contact_form_shortcode = $this->get_shortcode( $content, 'contact-form' );

		if ( ! $contact_form_shortcode ) {
			return $content;
		}

		$hcaptcha_shortcode = $this->get_shortcode( $contact_form_shortcode, 'hcaptcha' );

		if ( ! $hcaptcha_shortcode ) {
			return $content;
		}

		$hcaptcha_sc = preg_replace(
			'/\s*\[|]\s*/',
			'',
			$hcaptcha_shortcode
		);

		$atts = shortcode_parse_atts( $hcaptcha_sc );

		unset( $atts[0] );

		$settings       = hcaptcha()->settings();
		$hcaptcha_force = $settings->is_on( 'force' );
		$hcaptcha_size  = $settings->get( 'size' );

		$atts = shortcode_atts(
			[
				'force'   => $hcaptcha_force,
				'size'    => $hcaptcha_size,
				'id'      =>
					[
						'source'  => HCaptcha::get_class_source( static::class ),
						'form_id' => $GLOBALS['post']->ID ?? 0,
					],
				'protect' => true,
			],
			$atts
		);

		$atts['action'] = self::ACTION;
		$atts['name']   = self::NAME;
		$atts['auto']   = false;

		$atts = HCaptcha::flatten_array( $atts, '--' );

		array_walk(
			$atts,
			static function ( &$value, $key ) {
				$value = "$key=\"$value\"";
			}
		);

		$updated_hcaptcha_sc = 'hcaptcha ' . implode( ' ', $atts );

		return str_replace( $hcaptcha_shortcode, "[$updated_hcaptcha_sc]", $content );
	}

	/**
	 * Get shortcode in content.
	 *
	 * @param string $content   Content.
	 * @param string $shortcode Shortcode.
	 *
	 * @return string
	 */
	private function get_shortcode( string $content, string $shortcode ): string {
		$regex = get_shortcode_regex( [ $shortcode ] );

		return preg_match( "/$regex/", $content, $matches )
			? $matches[0]
			: '';
	}

	/**
	 * Get hCaptcha arguments.
	 *
	 * @param string $hash Form hash.
	 *
	 * @return array
	 */
	protected function get_args( string $hash = '' ): array {
		return [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => 'contact' . $hash,
			],
		];
	}

	/**
	 * Get form hash.
	 *
	 * @param string $form Jetpack form.
	 *
	 * @return string
	 */
	protected function get_form_hash( string $form ): string {
		return preg_match( "/name='contact-form-hash' value='(.+)'/", $form, $m )
			? '_' . $m[1]
			: '';
	}

	/**
	 * Get hCaptcha.
	 *
	 * @param array $args The hCaptcha arguments.
	 *
	 * @return string
	 */
	protected function get_hcaptcha( array $args ): string {
		return '<div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . HCaptcha::form( $args ) . '</div>';
	}

	/**
	 * Get form hash.
	 *
	 * @return string|null
	 */
	private function get_submitted_form_hash(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		return isset( $_POST['contact-form-hash'] )
			? sanitize_text_field( wp_unslash( $_POST['contact-form-hash'] ) )
			: null;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Check if currently editing post contains a Jetpack form.
	 *
	 * @return bool
	 */
	protected function is_editing_jetpack_form_post(): bool {
		$pagenow = $GLOBALS['pagenow'] ?? '';

		if ( 'post.php' !== $pagenow ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id || 'edit' !== $action ) {
			return false;
		}

		$post    = get_post( $post_id );
		$content = $post->post_content ?? '';

		return false !== strpos( html_entity_decode( $content ), '<!-- wp:jetpack/contact-form' );
	}
}
