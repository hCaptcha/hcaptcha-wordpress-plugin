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

namespace HCaptcha\Jetpack;

use Automattic\Jetpack\Forms\ContactForm\Contact_Form;
use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use HCaptcha\Main;
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
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-jetpack';

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
	protected ?string $error_message = null;

	/**
	 * Errored hash of the form.
	 *
	 * @var string|null
	 */
	protected ?string $error_form_hash = null;

	/**
	 * Form hashes authenticated by Jetpack JWTs in this request.
	 *
	 * @var array<string, string>
	 */
	private array $jwt_form_hashes = [];

	/**
	 * Forms selected by Jetpack's verified JWT route in this request.
	 *
	 * @var array<string, Contact_Form>
	 */
	private array $jwt_forms = [];

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
		add_filter( 'jetpack_forms_extra_webhooks', [ $this, 'capture_form_hash' ], PHP_INT_MAX, 2 );

		add_action( 'wp_head', [ $this, 'print_inline_styles' ] );

		add_filter( 'the_content', [ $this, 'the_content_filter' ] );

		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );

		if ( ! $this->is_editing_jetpack_form_post() ) {
			return;
		}

		add_filter( 'hcap_print_hcaptcha_scripts', [ $this, 'print_hcaptcha_scripts' ], 0 );
		add_action( 'wp_print_footer_scripts', [ $this, 'editor_enqueue_scripts' ], 9 );
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
	 * @param bool|mixed  $is_spam   Is spam.
	 * @param array|mixed $form_data Jetpack's computed spam data.
	 *
	 * @return bool|WP_Error|mixed
	 */
	public function verify( $is_spam = false, $form_data = [] ) {
		$submitted_hash = $this->get_submitted_form_hash();
		$verified_hash  = $this->get_verified_jwt_form_hash();

		if ( null !== $verified_hash && ( '' === $verified_hash || ! hash_equals( $verified_hash, $submitted_hash ?? '' ) ) ) {
			$this->error_message = hcap_get_error_messages()['bad-signature'];
		} else {
			$this->error_message = API::verify( $this->get_entry( is_array( $form_data ) ? $form_data : [] ) );
		}

		if ( null === $this->error_message ) {
			return $is_spam;
		}

		$this->error_form_hash = $verified_hash ?: $submitted_hash;

		$error = new WP_Error();

		$error->add( 'invalid_hcaptcha', $this->error_message );
		add_filter( 'hcap_hcaptcha_content', [ $this, 'error_message' ], 10, 2 );

		return $error;
	}

	/**
	 * Retain the hash of the form selected by Jetpack's verified JWT route.
	 *
	 * @param mixed $webhooks Extra webhook configurations.
	 * @param mixed $form     Jetpack form selected for this submission.
	 *
	 * @return mixed
	 */
	public function capture_form_hash( $webhooks, $form ) {
		$jwt = Request::filter_input( INPUT_POST, 'jetpack_contact_form_jwt' );

		if (
			$form instanceof Contact_Form &&
			$form->has_verified_jwt &&
			is_string( $form->hash ) &&
			'' !== $form->hash &&
			is_string( $jwt ) &&
			'' !== $jwt
		) {
			$jwt_key                           = hash( 'sha256', $jwt );
			$this->jwt_form_hashes[ $jwt_key ] = $form->hash;
			$this->jwt_forms[ $jwt_key ]       = $form;
		}

		return $webhooks;
	}

	/**
	 * Get the hash Jetpack authenticated for the submitted JWT.
	 *
	 * Null means a legacy submission without a JWT; an empty string fails closed.
	 *
	 * @return string|null
	 */
	private function get_verified_jwt_form_hash(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['jetpack_contact_form_jwt'] ) ) {
			return null;
		}

		$jwt = Request::filter_input( INPUT_POST, 'jetpack_contact_form_jwt' );

		return is_string( $jwt ) && '' !== $jwt
			? $this->jwt_form_hashes[ hash( 'sha256', $jwt ) ] ?? ''
			: '';
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

		$message = '
<div class="contact-form__input-error">
	<span class="contact-form__warning-icon">
		<span class="visually-hidden">Warning.</span>
		<i aria-hidden="true"></i>
	</span>
	<span>' . $this->error_message . '</span>
</div>
';

		return $hcaptcha . $message;
	}

	/**
	 * Print hCaptcha script when editing a page with a Jetpack form.
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
	 * Enqueue script on the frontend.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_register_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/hcaptcha-jetpack$min.js",
			[ Main::HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		DelayedScript::enqueue( self::HANDLE );
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
	 * Enqueue script in the block editor.
	 *
	 * @return void
	 */
	public function editor_enqueue_scripts(): void {
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
		/* language=CSS */
		$css = '
	form.contact-form .grunion-field-hcaptcha-wrap.grunion-field-wrap {
		flex-direction: row !important;
	}

	form.contact-form .grunion-field-hcaptcha-wrap.grunion-field-wrap .h-captcha,
	form.wp-block-jetpack-contact-form .grunion-field-wrap .h-captcha {
		margin-bottom: 0;
	}
';

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

		$atts = Utils::flatten_array( $atts, '--' );

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
			'id'     => $this->get_expected_id( $hash ),
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
	 * Check if the currently editing post contains a Jetpack form.
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

	/**
	 * Get entry.
	 *
	 * @param array $form_data Jetpack's computed spam data.
	 *
	 * @return array
	 */
	private function get_entry( array $form_data = [] ): array {
		$contact_form_id = Request::filter_input( INPUT_POST, 'contact-form-id' );
		$form_id         = 'g' . $contact_form_id;
		$post_id         = (int) $contact_form_id;
		$post            = get_post( $post_id );
		$entry           = [
			'nonce_name'    => self::NAME,
			'nonce_action'  => self::ACTION,
			'form_date_gmt' => $post->post_modified_gmt ?? null,
			'expected_id'   => $this->get_expected_id( (string) $this->get_submitted_form_hash() ),
			'data'          => [],
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $_POST as $key => $value ) {
			if ( strpos( $key, $form_id ) !== 0 ) {
				continue;
			}

			$value    = Request::filter_input( INPUT_POST, $key );
			$data_key = ltrim( str_replace( $form_id, '', $key ), '-' );

			if ( 'name' === $data_key ) {
				$entry['name'] = $value;
			}

			if ( 'email' === $data_key ) {
				$entry['email'] = $value;
			}

			$entry['data'][ $data_key ] = $value;
		}

		$form = $this->get_native_form();

		if ( null === $form && ! array_key_exists( 'comment_author_email', $form_data ) ) {
			return $entry;
		}

		$entry = EntryData::with_emails( $entry, $this->get_native_emails( $form, $form_data ) );
		unset( $entry['email'] );

		if ( $entry['email_addresses'] ) {
			$entry['email'] = $entry['email_addresses'][0];
		}

		return $entry;
	}

	/**
	 * Get the same form instance Jetpack selected for this submission.
	 *
	 * @return Contact_Form|null
	 * @noinspection UnnecessaryCastingInspection
	 */
	private function get_native_form(): ?Contact_Form {
		$verified_hash = $this->get_verified_jwt_form_hash();

		if ( null !== $verified_hash ) {
			$jwt = Request::filter_input( INPUT_POST, 'jetpack_contact_form_jwt' );

			return is_string( $jwt ) ? $this->jwt_forms[ hash( 'sha256', $jwt ) ] ?? null : null;
		}

		$hash = $this->get_submitted_form_hash();
		$hash = null !== $hash ? (string) preg_replace( '/[^\da-f]/i', '', $hash ) : null;
		$form = null !== $hash ? Contact_Form::$forms[ $hash ] ?? null : null;

		// Jetpack can rebuild a legacy form from post meta and then select $last
		// even when the rebuilt hash differs from the submitted hash.
		if ( ! $form && Contact_Form::$last instanceof Contact_Form ) {
			$form = Contact_Form::$last;
		}

		return $form instanceof Contact_Form ? $form : null;
	}

	/**
	 * Read every visible email field from Jetpack's selected form schema.
	 *
	 * @param Contact_Form|null $form      Native form, if available.
	 * @param array             $form_data Jetpack's computed spam data.
	 *
	 * @return string[]
	 */
	private function get_native_emails( ?Contact_Form $form, array $form_data ): array {
		$author_email = $form_data['comment_author_email'] ?? '';

		if ( null === $form ) {
			return is_string( $author_email ) ? [ $author_email ] : [];
		}

		$field_ids  = $form->get_field_ids();
		$visibility = method_exists( $form, 'get_resolved_field_visibility' ) ? $form->get_resolved_field_visibility() : [];
		$emails     = [];

		foreach ( $field_ids['all'] as $field_id ) {
			if ( false === ( $visibility[ $field_id ] ?? true ) || 'email' !== $form->fields[ $field_id ]->get_attribute( 'type' ) ) {
				continue;
			}

			$this->append_native_email_values( $emails, (string) $field_id, $field_ids['email'] ?? null, $author_email );
		}

		return $emails;
	}

	/**
	 * Include both Jetpack's filtered author email and its stored field value.
	 *
	 * @param array       $emails          Native email list.
	 * @param string      $field_id        Native field ID.
	 * @param string|null $author_field_id Native author email field ID.
	 * @param mixed       $author_email    Jetpack's computed author email.
	 *
	 * @return void
	 */
	private function append_native_email_values( array &$emails, string $field_id, ?string $author_field_id, $author_email ): void {
		if ( $author_field_id === $field_id && is_string( $author_email ) ) {
			$emails[] = $author_email;
		}

		$value = Request::filter_input( INPUT_POST, $field_id );

		if ( is_string( $value ) ) {
			$emails[] = $value;
		}
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param string $hash Form hash.
	 *
	 * @return array
	 */
	private function get_expected_id( string $hash = '' ): array {
		if ( $hash && '_' !== $hash[0] ) {
			$hash = '_' . $hash;
		}

		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => 'contact' . $hash,
		];
	}
}
