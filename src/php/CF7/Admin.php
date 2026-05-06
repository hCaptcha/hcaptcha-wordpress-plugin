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
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use HCaptcha\Main;
use WPCF7_ContactForm;
use WPCF7_TagGenerator;
use WPCF7_TagGeneratorGenerator;

/**
 * Class Admin.
 *
 * Show the CF7 form in admin.
 */
class Admin extends Base {
	/**
	 * Script handle.
	 */
	public const HANDLE = 'hcaptcha-cf7';

	/**
	 * Admin script handle.
	 */
	public const ADMIN_HANDLE = 'admin-cf7';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaCF7Object';

	/**
	 * Update form action.
	 */
	public const UPDATE_FORM_ACTION = 'hcaptcha-cf7-update-form';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		parent::init_hooks();

		if ( $this->mode_live ) {
			add_action( 'wp_ajax_' . self::UPDATE_FORM_ACTION, [ $this, 'update_form' ] );
		}

		if ( ! Pages::is_cf7_edit_page() ) {
			return;
		}

		if ( $this->mode_embed ) {
			add_action( 'wpcf7_admin_init', [ $this, 'add_tag_generator_hcaptcha' ], 54 );
		}

		if ( $this->mode_live ) {
			add_action( 'current_screen', [ $this, 'current_screen' ] );
		}

		add_filter( 'hcap_print_hcaptcha_scripts', '__return_true', 0 );
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

		add_action( 'admin_print_footer_scripts', [ $this, 'action_admin_print_footer_scripts' ], 9 );
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
	 * Insert live form into the CF7 editor.
	 *
	 * @param string $output CF7 editor output.
	 *
	 * @return string
	 */
	private function insert_live_form( string $output ): string {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'wpcf7_edit_contact_form' ) ) {
			return $output;
		}

		$form_shortcode = '';

		if ( preg_match( '/<input type="text" id="wpcf7-shortcode" .+ value="(.+)"/', $output, $m ) ) {
			$form_shortcode = htmlspecialchars_decode( $m[1] );
		}

		if ( ! preg_match( '~(<form method="post".+?)<div id="poststuff">.+?(</form>)~s', $output, $m ) ) {
			return $output;
		}

		[ $form, $form_start, $form_end ] = $m;

		if ( ! preg_match( '~<div id="post-body".+?>~s', $output, $m ) ) {
			return $output;
		}

		$post_body_start = $m[0];

		if ( ! preg_match( '~(</div>(?:<!-- #post-body -->)*\s*<br class="clear" />\s*</div>.*?)</form>~s', $output, $m ) ) {
			return $output;
		}

		$post_body_end = $m[1];

		if ( $form_shortcode ) {
			$live_form = do_shortcode( $form_shortcode );
		} else {
			preg_match( '#<textarea id="wpcf7-form".+?>(.*?)</textarea>#s', $form, $m );

			$live_form = $this->new_form( html_entity_decode( $m[1] ?? '' ) );
		}

		$stripe_message = '';

		if ( $this->has_stripe_element( $live_form ) ) {
			$stripe_message = sprintf(
				'<h4><em>%1$s</em></h4>',
				__( 'The Stripe payment element already contains an invisible hCaptcha. No need to add it to the form.', 'hcaptcha-for-forms-and-more' )
			);
		}

		$live_container          = sprintf(
			"\n" .
			'<div id="postbox-container-live" class="postbox-container">' .
			'<div id="form-live"><h3>%1$s</h3>%2$s%3$s</div>' .
			'</div>' .
			"\n",
			__( 'Live Form', 'hcaptcha-for-forms-and-more' ),
			$stripe_message,
			$live_form
		);
		$post_body_end_with_live = str_replace( '<br class="clear" />', $live_container . '<br class="clear" />', $post_body_end );

		// Extract form content.
		$form_content = str_replace( [ $form_start, $form_end ], '', $form );

		// Leave form content only.
		// Add form inside div#post-body.
		return str_replace(
			[ $form, $post_body_start, $post_body_end ],
			[ $form_content, $post_body_start . $form_start, $form_end . $post_body_end_with_live ],
			$output
		);
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
			[ $this, 'tag_generator_hcaptcha' ],
			[ 'version' => '2' ]
		);
	}

	/**
	 * Show tag generator.
	 *
	 * @param mixed        $contact_form Contact form.
	 * @param array|string $options      Options.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function tag_generator_hcaptcha( $contact_form, $options = '' ): void {
		$field = [
			'display_name' => __( 'hCaptcha field', 'hcaptcha-for-forms-and-more' ),
			'heading'      => __( 'hCaptcha field form-tag generator', 'hcaptcha-for-forms-and-more' ),
			'description'  => __( 'Generate a form-tag for a hCaptcha field.', 'hcaptcha-for-forms-and-more' ),
		];

		$tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );

		?>
		<header class="description-box">
			<h3><?php echo esc_html( $field['heading'] ); ?></h3>
			<p><?php echo esc_html( $field['description'] ); ?></p>
		</header>

		<div class="control-box">
			<?php
			$tgg->print(
				'field_type',
				[
					'with_required'  => true,
					'select_options' => [
						'cf7-hcaptcha' => $field['display_name'],
					],
				]
			);
			$tgg->print( 'field_name' );
			$tgg->print( 'class_attr' );
			?>
		</div>

		<footer class="insert-box">
			<?php
			$tgg->print( 'insert_box_content' );
			$tgg->print( 'mail_tag_tip' );
			?>
		</footer>
		<?php
	}

	/**
	 * Enqueue admin scripts before CF7 admin scripts.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function enqueue_admin_scripts_before_cf7(): void {
		// CF7 scripts.
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

		// The hCaptcha admin script and style.
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::ADMIN_HANDLE,
			HCAPTCHA_URL . "/assets/js/admin-cf7$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::ADMIN_HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'updateFormAction' => self::UPDATE_FORM_ACTION,
				'updateFormNonce'  => wp_create_nonce( self::UPDATE_FORM_ACTION ),
			]
		);

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

		// CF7 scripts.
		$wpcf7 = [
			'api' => [
				'root'      => sanitize_url( get_rest_url() ),
				'namespace' => 'contact-form-7/v1',
			],
		];

		$data = $wp_scripts->registered['wpcf7-admin']->extra['before'][1];

		if ( preg_match( '/var wpcf7 = ({.+});/s', $data, $m ) ) {
			$wpcf7_json = Utils::json_decode_arr( $m[1] );

			$wpcf7 = array_merge( $wpcf7, $wpcf7_json );

			$wp_scripts->registered['wpcf7-admin']->extra['before'][1] = 'var wpcf7 = ' . wp_json_encode( $wpcf7 ) . ';';
		}
	}

	/**
	 * Admin print footer scripts action.
	 *
	 * @return void
	 */
	public function action_admin_print_footer_scripts(): void {
		$min = hcap_min_suffix();

		// The hCaptcha frontend script.
		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-cf7$min.js",
			[ Main::HANDLE ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Update form.
	 *
	 * @return void
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function update_form(): void {
		if ( ! check_ajax_referer( self::UPDATE_FORM_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );

			return; // For testing purposes.
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown
		if ( ! current_user_can( 'wpcf7_edit_contact_form' ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to update the form.', 'hcaptcha-for-forms-and-more' ) );

			return; // For testing purposes.
		}

		$cf7_tag   = 'contact-form-7';
		$shortcode = Request::filter_input( INPUT_POST, 'shortcode' );
		$form      = isset( $_POST['form'] ) ? wp_kses_post( wp_unslash( $_POST['form'] ) ) : '';

		if ( $shortcode ) {
			// Saved form.
			preg_match( '/' . get_shortcode_regex() . '/s', $shortcode, $m );

			// Validate CF7 shortcode.
			$shortcode = $cf7_tag === $m[2] ? "[$cf7_tag $m[3]]" : '';

			add_action(
				'wpcf7_contact_form',
				static function ( $contact_form ) use ( $form ) {
					/**
					 * Contact Form instance.
					 *
					 * @var WPCF7_ContactForm $contact_form
					 */
					$properties         = $contact_form->get_properties();
					$properties['form'] = $form;

					$contact_form->set_properties( $properties );

					return $form;
				}
			);

			$live_form = do_shortcode( $shortcode );
		} else {
			$live_form = $this->new_form( $form );
		}

		$live_form = sprintf(
			'<h3>%s</h3>%s',
			__( 'Live Form', 'hcaptcha-for-forms-and-more' ),
			$live_form
		);

		wp_send_json_success( $live_form );
	}

	/**
	 * New form.
	 *
	 * @param string $form Form.
	 *
	 * @return mixed
	 */
	private function new_form( string $form ) {
		// New form.
		$contact_form = WPCF7_ContactForm::get_template();

		$atts               = [];
		$properties         = $contact_form->get_properties();
		$properties['form'] = $form;

		$contact_form->set_properties( $properties );

		$callback = static function ( $contact_form, $atts ) {
			return $contact_form->form_html( $atts );
		};

		$live_form = wpcf7_switch_locale(
			$contact_form->locale(),
			$callback,
			$contact_form,
			$atts
		);

		do_action( 'wpcf7_shortcode_callback', $contact_form, $atts );

		// Add hCaptcha to the form.
		return apply_filters( 'do_shortcode_tag', $live_form, 'contact-form-7', [], [] );
	}
}
