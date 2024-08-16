<?php
/**
 * CF7 Admin class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\CF7;

use HCaptcha\Helpers\Pages;
use WPCF7_TagGenerator;

/**
 * Class Admin.
 *
 * Show the CF7 form in admin.
 */
class Admin extends Base {
	/**
	 * Admin script handle.
	 */
	public const ADMIN_HANDLE = 'admin-cf7';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		parent::init_hooks();

		if ( ( ! $this->mode_auto && ! $this->mode_embed ) || ! is_admin() ) {
			return;
		}

		if ( ! Pages::is_cf7_edit_page() ) {
			return;
		}

		add_action( 'wpcf7_admin_init', [ $this, 'add_tag_generator_hcaptcha' ], 54 );
		add_action( 'current_screen', [ $this, 'current_screen' ] );
	}

	/**
	 * Current screen.
	 *
	 * @param WP_Screen|mixed $current_screen Current screen.
	 *
	 * @return void
	 */
	public function current_screen( $current_screen ): void {
		$current_screen_id = $current_screen->id ?? '';

		if ( ! $current_screen_id ) {
			return;
		}

		add_action( $current_screen_id, [ $this, 'before_toplevel_page_wpcf7' ], 0 );
		add_action( $current_screen_id, [ $this, 'after_toplevel_page_wpcf7' ], 20 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts_before_cf7' ], 0 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts_after_cf7' ], 20 );
	}

	/**
	 * Before CF7 admin page.
	 *
	 * @return void
	 */
	public function before_toplevel_page_wpcf7(): void {
		ob_start();
	}

	/**
	 * After CF7 admin page.
	 *
	 * @return void
	 */
	public function after_toplevel_page_wpcf7(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->insert_live_form( (string) ob_get_clean() );
	}

	/**
	 * Insert live form into CF7 editor.
	 *
	 * @param string $output CF7 editor output.
	 *
	 * @return string
	 */
	private function insert_live_form( string $output ): string {
		if ( ! preg_match( '/(<form .+)<div id="poststuff">/s', $output, $m ) ) {
			return $output;
		}

		$form_start = $m[1];

		if ( ! preg_match( '~(</div><!-- #poststuff -->).*?</form>~s', $output, $m ) ) {
			return $output;
		}

		[ $form_end, $stuff_end ] = $m;

		if ( ! preg_match( '/<input type="text" id="wpcf7-shortcode" .+ value="(.+)"/', $output, $m ) ) {
			return $output;
		}

		$form_shortcode = htmlspecialchars_decode( $m[1] );
		$live_form      = do_shortcode( $form_shortcode );
		$stripe_message = '';

		if ( $this->has_stripe_element( $live_form ) ) {
			$stripe_message =
				'<h4><em>' .
				__( 'The Stripe payment element already contains an invisible hCaptcha. No need to add it to the form.', 'hcaptcha-for-forms-and-more' ) .
				'</em></h4>';
		}

		$live_container =
			"\n" .
			'<div id="postbox-container-live" class="postbox-container">' .
			'<div id="form-live">' .
			'<h3>' . __( 'Live Form', 'hcaptcha-for-forms-and-more' ) . '</h3>' .
			$stripe_message .
			$live_form .
			'</div>' .
			'</div>' .
			"\n";

		// Remove form tag.
		$output = str_replace( [ $form_start, $form_end ], [ '', $stuff_end ], $output );

		// Insert form start at the beginning of the id="post-body".
		$search = '<div id="post-body-content">';
		$output = str_replace( $search, $form_start . $search, $output );

		// Insert form end and live container at the end of the id="post-body".
		$search = '/(<\/div>\s*<!-- #post-body -->)/';

		return preg_replace( $search, '</form>$1' . $live_container, $output );
	}

	/**
	 * Add tag generator to admin editor.
	 *
	 * @return void
	 */
	public function add_tag_generator_hcaptcha(): void {
		if ( ! $this->mode_embed ) {
			return;
		}

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
	public function tag_generator_hcaptcha( $contact_form, $args = '' ): void {
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
	 * Enqueue admin scripts before CF7 admin scripts.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function enqueue_admin_scripts_before_cf7(): void {
		wp_enqueue_style(
			'contact-form-7',
			wpcf7_plugin_url( 'includes/css/styles.css' ),
			[],
			constant( 'WPCF7_VERSION' )
		);

		wp_enqueue_script(
			'swv',
			wpcf7_plugin_url( 'includes/swv/js/index.js' ),
			[],
			constant( 'WPCF7_VERSION' ),
			[ 'in_footer' => true ]
		);

		wp_enqueue_script(
			'contact-form-7',
			wpcf7_plugin_url( 'includes/js/index.js' ),
			[ 'swv' ],
			constant( 'WPCF7_VERSION' ),
			[ 'in_footer' => true ]
		);

		$min = hcap_min_suffix();

		wp_enqueue_style(
			self::ADMIN_HANDLE,
			HCAPTCHA_URL . "/assets/css/admin-cf7$min.css",
			[],
			HCAPTCHA_VERSION
		);
	}

	/**
	 * Enqueue admin scripts after CF7 admin scripts.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts_after_cf7(): void {
		global $wp_scripts;

		$wpcf7 = [
			'api' => [
				'root'      => sanitize_url( get_rest_url() ),
				'namespace' => 'contact-form-7/v1',
			],
		];

		$data = $wp_scripts->registered['wpcf7-admin']->extra['data'];

		if ( preg_match( '/var wpcf7 = ({.+});/', $data, $m ) ) {
			$wpcf7 = array_merge( $wpcf7, json_decode( $m[1], true ) );

			$wp_scripts->registered['wpcf7-admin']->extra['data'] = 'var wpcf7 = ' . wp_json_encode( $wpcf7 ) . ';';
		}
	}
}
