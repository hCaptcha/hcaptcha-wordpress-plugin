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

		// The hCaptcha plugin styles.
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
			$wpcf7 = array_merge( $wpcf7, json_decode( $m[1], true ) );

			$wp_scripts->registered['wpcf7-admin']->extra['before'][1] = 'var wpcf7 = ' . wp_json_encode( $wpcf7 ) . ';';
		}
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

		$shortcode = html_entity_decode( filter_input( INPUT_POST, 'shortcode', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$form      = html_entity_decode( filter_input( INPUT_POST, 'form', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );

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

		$live =
			'<h3>' . __( 'Live Form', 'hcaptcha-for-forms-and-more' ) . '</h3>' .
			do_shortcode( $shortcode );

		wp_send_json_success( $live );
	}
}
