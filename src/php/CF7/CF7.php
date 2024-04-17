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
		add_filter( 'rest_authentication_errors', [ $this, 'check_rest_nonce' ] );
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
		$attr = (array) $attr;

		foreach ( $attr as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			if ( preg_match( '/(^id|^class):([\w-]+)/', $value, $m ) ) {
				$attr[ 'cf7-' . $m[1] ] = $m[2];
				unset( $attr[ $key ] );
			}
		}

		$attr['action'] = 'wp_rest';
		$attr['name']   = '_wpnonce';
		$attr['id']     = [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => (int) ( $attr['form_id'] ?? 0 ),
		];

		$hcap_form = hcap_shortcode( $attr );

		$id        = $attr['cf7-id'] ?? uniqid( 'hcap_cf7-', true );
		$class     = $attr['cf7-class'] ?? '';
		$hcap_form = preg_replace(
			[ '/(<div\s+?class="h-captcha")/', '#</div>#' ],
			[ '<span id="' . esc_attr( $id ) . '" class="wpcf7-form-control h-captcha ' . esc_attr( $class ) . '"', '</span>' ],
			$hcap_form
		);

		return (
			'<span class="wpcf7-form-control-wrap" data-name="' . self::DATA_NAME . '">' .
			$hcap_form .
			'</span>'
		);
	}

	/**
	 * Check rest nonce and remove it for not logged-in users.
	 *
	 * @param WP_Error|mixed $result Error from another authentication handler,
	 *                               null if we should handle it, or another value if not.
	 *
	 * @return WP_Error|mixed
	 */
	public function check_rest_nonce( $result ) {
		if ( is_user_logged_in() ) {
			return $result;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_id        = isset( $_POST['_wpcf7'] ) ? (int) $_POST['_wpcf7'] : 0;
		$cf7_submit_uri = '/' . rest_get_url_prefix() . '/contact-form-7/v1/contact-forms/' . $form_id . '/feedback';
		$uri            = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( $cf7_submit_uri !== $uri ) {
			return $result;
		}

		unset( $_REQUEST['_wpnonce'] );

		return $result;
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

		$cf7_hcap_shortcode = $matches[0];
		$cf7_hcap_sc        = preg_replace(
			[ '/\s*\[|]\s*/', '/(id|class)\s*:\s*([\w-]+)/' ],
			[ '', '$1=$2' ],
			$cf7_hcap_shortcode
		);
		$atts               = shortcode_parse_atts( $cf7_hcap_sc );

		unset( $atts[0] );

		if ( isset( $atts['form_id'] ) && (int) $atts['form_id'] === $form_id ) {
			return $output;
		}

		$atts['form_id'] = $form_id;

		array_walk(
			$atts,
			static function ( &$value, $key ) {
				if ( in_array( $key, [ 'id', 'class' ], true ) ) {
					$value = "$key:$value";

					return;
				}

				$value = "$key=\"$value\"";
			}
		);

		$updated_cf_hcap_sc = self::SHORTCODE . ' ' . implode( ' ', $atts );

		return str_replace( $cf7_hcap_shortcode, "[$updated_cf_hcap_sc]", $output );
	}
}
