<?php
/**
 * CF7 form class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\CF7;

use HCaptcha\Helpers\HCaptcha;
use WPCF7_FormTag;
use WPCF7_Submission;
use WPCF7_TagGenerator;
use WPCF7_Validation;

/**
 * Class CF7.
 */
class CF7 {
	const HANDLE    = 'hcaptcha-cf7';
	const SHORTCODE = 'cf7-hcaptcha';
	const DATA_NAME = 'hcap-cf7';

	/**
	 * CF7 constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		add_filter( 'do_shortcode_tag', [ $this, 'wpcf7_shortcode' ], 20, 4 );
		add_shortcode( self::SHORTCODE, [ $this, 'cf7_hcaptcha_shortcode' ] );
		add_filter( 'wpcf7_validate', [ $this, 'verify_hcaptcha' ], 20, 2 );
		add_action( 'wp_print_footer_scripts', [ $this, 'enqueue_scripts' ], 9 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
		add_action( 'wpcf7_admin_init', [ $this, 'add_tag_generator_hcaptcha' ], 54 );
	}

	/**
	 * Add hCaptcha to CF7 form.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function wpcf7_shortcode( $output, string $tag, $attr, array $m ) {
		if ( 'contact-form-7' !== $tag ) {
			return $output;
		}

		remove_filter( 'do_shortcode_tag', [ $this, 'wpcf7_shortcode' ], 20 );

		$output  = (string) $output;
		$form_id = isset( $attr['id'] ) ? (int) $attr['id'] : 0;

		if ( has_shortcode( $output, self::SHORTCODE ) ) {
			$output = do_shortcode( $this->add_form_id_to_cf7_hcap_shortcode( $output, $form_id ) );

			add_filter( 'do_shortcode_tag', [ $this, 'wpcf7_shortcode' ], 20, 4 );

			return $output;
		}

		$cf7_hcap_form = do_shortcode( '[' . self::SHORTCODE . " form_id=\"$form_id\"]" );
		$submit_button = '/(<(input|button) .*?type="submit")/';

		add_filter( 'do_shortcode_tag', [ $this, 'wpcf7_shortcode' ], 20, 4 );

		return preg_replace(
			$submit_button,
			$cf7_hcap_form . '$1',
			$output
		);
	}

	/**
	 * CF7 hCaptcha shortcode.
	 *
	 * @param array|string $attr Shortcode attributes.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function cf7_hcaptcha_shortcode( $attr = [] ): string {
		$settings          = hcaptcha()->settings();
		$hcaptcha_site_key = $settings->get_site_key();
		$hcaptcha_theme    = $settings->get_theme();
		$hcaptcha_size     = $settings->get( 'size' );
		$allowed_sizes     = [ 'normal', 'compact', 'invisible' ];

		$args = wp_parse_args(
			(array) $attr,
			/**
			 * CF7 works via REST API, where the current user is set to 0 (not logged in) if nonce is not present.
			 * However, we can add standard nonce for the action 'wp_rest' and rest_cookie_check_errors() provides the check.
			 */
			[
				'action'  => 'wp_rest', // Action name for wp_nonce_field.
				'name'    => '_wpnonce', // Nonce name for wp_nonce_field.
				'auto'    => false, // Whether a form has to be auto-verified.
				'size'    => $hcaptcha_size, // The hCaptcha widget size.
				'id'      => [
					'source'  => HCaptcha::get_class_source( __CLASS__ ),
					'form_id' => $attr['form_id'] ?? 0,
				], // hCaptcha widget id.
				/**
				 * Example of id:
				 * [
				 *   'source' => ['gravityforms/gravityforms.php'],
				 *   $form_id => 23
				 * ]
				 */
				'protect' => true,
			]
		);

		foreach ( $args as $arg ) {
			if ( is_array( $arg ) ) {
				continue;
			}

			if ( preg_match( '/(id|class):([\w-]+)/', $arg, $m ) ) {
				$args[ 'cf7-' . $m[1] ] = $m[2];
			}
		}

		if ( $args['id'] ) {
			$id            = (array) $args['id'];
			$id['source']  = isset( $id['source'] ) ? (array) $id['source'] : [];
			$id['form_id'] = $id['form_id'] ?? 0;

			if (
				! $args['protect'] ||
				! apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] )
			) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				$encoded_id = base64_encode( wp_json_encode( $id ) );
				$widget_id  = $encoded_id . '-' . wp_hash( $encoded_id );

				ob_start();
				?>
				<input
						type="hidden"
						class="<?php echo esc_attr( HCaptcha::HCAPTCHA_WIDGET_ID ); ?>"
						name="<?php echo esc_attr( HCaptcha::HCAPTCHA_WIDGET_ID ); ?>"
						value="<?php echo esc_attr( $widget_id ); ?>">
				<?php

				return ob_get_clean();
			}
		}

		$args['size'] = in_array( $args['size'], $allowed_sizes, true ) ? $args['size'] : $hcaptcha_size;
		$callback     = 'invisible' === $args['size'] ? 'data-callback="hCaptchaSubmit"' : '';

		hcaptcha()->form_shown = true;

		$id    = $args['cf7-id'] ?? uniqid( 'hcap_cf7-', true );
		$class = $args['cf7-class'] ?? '';

		return (
			'<span class="wpcf7-form-control-wrap" data-name="' . self::DATA_NAME . '">' .
			'<span id="' . $id . '"' .
			' class="wpcf7-form-control h-captcha ' . $class . '"' .
			' data-sitekey="' . esc_attr( $hcaptcha_site_key ) . '"' .
			' data-theme="' . esc_attr( $hcaptcha_theme ) . '"' .
			' data-size="' . esc_attr( $args['size'] ) . '"' .
			' ' . wp_kses_post( $callback ) . '>' .
			'</span>' .
			'</span>' .
			wp_nonce_field( $args['action'], $args['name'], true, false )
		);
	}

	/**
	 * Verify CF7 recaptcha.
	 *
	 * @param WPCF7_Validation|mixed $result Result.
	 * @param WPCF7_FormTag[]|mixed  $tag    Tag.
	 *
	 * @return WPCF7_Validation|mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function verify_hcaptcha( $result, $tag ) {
		$submission = WPCF7_Submission::get_instance();

		if ( null === $submission ) {
			return $this->get_invalidated_result( $result );
		}

		$data           = $submission->get_posted_data();
		$response       = $data['h-captcha-response'] ?? '';
		$captcha_result = hcaptcha_request_verify( $response );

		if ( null !== $captcha_result ) {
			return $this->get_invalidated_result( $result, $captcha_result );
		}

		return $result;
	}

	/**
	 * Get invalidated result.
	 *
	 * @param WPCF7_Validation|mixed $result         Result.
	 * @param string|null            $captcha_result hCaptcha result.
	 *
	 * @return WPCF7_Validation|mixed
	 * @noinspection PhpMissingParamTypeInspection
	 */
	private function get_invalidated_result( $result, $captcha_result = '' ) {
		if ( '' === $captcha_result ) {
			$captcha_result = hcap_get_error_messages()['empty'];
		}

		$result->invalidate(
			[
				'type' => 'hcaptcha',
				'name' => self::DATA_NAME,
			],
			$captcha_result
		);

		return $result;
	}

	/**
	 * Enqueue CF7 scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! hcaptcha()->form_shown ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-cf7$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles() {
		$css = <<<CSS
	span[data-name="hcap-cf7"] .h-captcha {
		margin-bottom: 0;
	}

	span[data-name="hcap-cf7"] ~ input[type="submit"],
	span[data-name="hcap-cf7"] ~ button[type="submit"] {
		margin-top: 2rem;
	}
CSS;

		HCaptcha::css_display( $css );
	}

	/**
	 * Add tag generator to admin editor.
	 *
	 * @return void
	 */
	public function add_tag_generator_hcaptcha() {
		$tag_generator = WPCF7_TagGenerator::get_instance();

		$tag_generator->add(
			'cf7-hcaptcha',
			__( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
			[ $this, 'tag_generator_hcaptcha' ]
		);
	}

	/**
	 * Show tag generator.
	 *
	 * @param mixed        $contact_form Contact form.
	 * @param array|string $args         Arguments.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function tag_generator_hcaptcha( $contact_form, $args = '' ) {
		$args        = wp_parse_args( $args );
		$type        = $args['id'];
		$description = __( 'Generate a form-tag for a hCaptcha field.', 'hcaptcha-for-forms-and-more' );

		?>
		<div class="control-box">
			<fieldset>
				<legend><?php echo esc_html( $description ); ?></legend>

				<table class="form-table">
					<tbody>

					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>">
								<?php echo esc_html( __( 'Id attribute', 'hcaptcha-for-forms-and-more' ) ); ?>
							</label>
						</th>
						<td>
							<input
									type="text" name="id" class="idvalue oneline option"
									id="<?php echo esc_attr( $args['content'] . '-id' ); ?>"/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>">
								<?php echo esc_html( __( 'Class attribute', 'hcaptcha-for-forms-and-more' ) ); ?>
							</label>
						</th>
						<td>
							<input
									type="text" name="class" class="classvalue oneline option"
									id="<?php echo esc_attr( $args['content'] . '-class' ); ?>"/>
						</td>
					</tr>

					</tbody>
				</table>
			</fieldset>
		</div>

		<div class="insert-box">
			<label>
				<input
						type="text" name="<?php echo esc_attr( $type ); ?>" class="tag code" readonly="readonly"
						onfocus="this.select()"/>
			</label>

			<div class="submitbox">
				<input
						type="button" class="button button-primary insert-tag"
						value="<?php echo esc_attr( __( 'Insert Tag', 'hcaptcha-for-forms-and-more' ) ); ?>"/>
			</div>
		</div>
		<?php
	}

	/**
	 * Add form_id to cf7_hcaptcha shortcode if it does not exist.
	 * Replace to proper form_id if needed.
	 *
	 * @param string $output  CF7 form output.
	 * @param int    $form_id CF7 form id.
	 *
	 * @return string
	 */
	private function add_form_id_to_cf7_hcap_shortcode( string $output, int $form_id ): string {
		$cf7_hcap_sc_regex = get_shortcode_regex( [ self::SHORTCODE ] );

		// The preg_match should always be true, because $output has shortcode.
		if ( ! preg_match( "/$cf7_hcap_sc_regex/", $output, $matches ) ) {
			// @codeCoverageIgnoreStart
			return $output;
			// @codeCoverageIgnoreEnd
		}

		$cf7_hcap_sc = $matches[0];
		$cf7_hcap_sc = preg_replace( '/\s*\[|]\s*/', '', $cf7_hcap_sc );
		$cf7_hcap_sc = preg_replace( '/(id|class)\s*:\s*([\w-]+)/', '$1=$2', $cf7_hcap_sc );
		$atts        = shortcode_parse_atts( $cf7_hcap_sc );

		unset( $atts[0] );

		if ( isset( $atts['form_id'] ) && (int) $atts['form_id'] === $form_id ) {
			return $output;
		}

		$atts['form_id'] = $form_id;

		array_walk(
			$atts,
			static function ( &$value, $key ) {
				$value = "$key=\"$value\"";
			}
		);

		$updated_cf_hcap_sc = self::SHORTCODE . ' ' . implode( ' ', $atts );

		return str_replace( $cf7_hcap_sc, $updated_cf_hcap_sc, $output );
	}
}
