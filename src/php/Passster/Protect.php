<?php
/**
 * The 'Protect' class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Passster;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_REST_Request;
use WP_REST_Response;

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
		add_filter( 'rest_pre_dispatch', [ $this, 'verify_rest' ], 9, 3 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_filter( 'script_loader_tag', [ $this, 'add_type_module' ], 10, 3 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Verify a modern Passster REST unlock request.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param mixed           $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_rest( $result, $server, WP_REST_Request $request ) {
		if (
			null !== $result ||
			'POST' !== $request->get_method() ||
			1 !== preg_match( '@^/passster/v1/unlock$@i', $request->get_route() )
		) {
			return $result;
		}

		$post_data     = $request->get_json_params();
		$post_data     = is_array( $post_data ) ? $post_data : [];
		$expected_id   = $this->get_id( $this->get_area_id( $post_data, 'area_id' ) );
		$error_message = API::verify_post_data(
			self::NONCE,
			self::ACTION,
			$post_data,
			$expected_id,
			$this->get_entry( $post_data )
		);

		if ( null === $error_message ) {
			return null;
		}

		return new WP_REST_Response(
			[
				'success' => false,
				'error'   => $error_message,
			]
		);
	}

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attribute array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function do_shortcode_tag( $output, string $tag, $attr, array $m ) {
		if ( 'passster' !== $tag ) {
			return $output;
		}

		$area_id = 0;

		if ( preg_match( '/data-area="([^"]*)"/i', $output, $m ) ) {
			$area_id = absint( $m[1] );
		}

		$args = [
			'mandatory' => true,
			'action'    => self::ACTION,
			'name'      => self::NONCE,
			'auto'      => true,
			'id'        => $this->get_id( $area_id ),
		];

		$search  = '<button name="submit"';
		$replace = HCaptcha::form( $args ) . $search;

		$output = str_replace(
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The API verifies the nonce below.
		$entry       = $this->get_entry( wp_unslash( $_POST ) );
		$expected_id = $this->get_id( $this->get_area_id( $_POST, 'area' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$error_message = API::verify_post( self::NONCE, self::ACTION, $expected_id, $entry );

		if ( null === $error_message ) {
			return;
		}

		echo wp_json_encode( [ 'error' => $error_message ] );
		exit;
	}

	/**
	 * Keep password and unlock input out of the anti-spam entry.
	 *
	 * @param array $submission Native unlock request.
	 *
	 * @return array
	 */
	private function get_entry( array $submission ): array {
		$context = array_intersect_key( $submission, array_flip( [ 'post_id', 'area', 'type' ] ) );

		return [
			'data' => array_replace( [ 'form' => 'protect' ], EntryData::from_array( $context, [] ) ),
		];
	}

	/**
	 * Get a native Passster area ID from a submitted field.
	 *
	 * @param array  $submission Submitted data.
	 * @param string $key        Native area field.
	 *
	 * @return int
	 */
	private function get_area_id( array $submission, string $key ): int {
		$value = $submission[ $key ] ?? 0;

		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Get the widget identity for a Passster area.
	 *
	 * @param int $area_id Area ID.
	 *
	 * @return array
	 */
	private function get_id( int $area_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			// Existing Passster pages sign positive area IDs as strings.
			'form_id' => $area_id > 0 ? (string) $area_id : 0,
		];
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
