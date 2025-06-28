<?php
/**
 * WhatsNew class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Settings\General;

/**
 * Class WhatsNew.
 *
 * Show a What's New popup in the admin.
 */
class WhatsNew extends NotificationsBase {

	/**
	 * Handle for assets.
	 */
	private const HANDLE = 'hcaptcha-whats-new';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaWhatsNewObject';

	/**
	 * Mark shown ajax action.
	 */
	private const MARK_SHOWN_ACTION = 'hcaptcha-mark-shown';

	/**
	 * Settings key for last shown What's New version.
	 */
	private const WHATS_NEW_KEY = 'whats_new_last_shown_version';

	/**
	 * Method prefix.
	 */
	private const PREFIX = 'whats_new_';

	/**
	 * Class is allowed to show the popup.
	 *
	 * @var bool
	 */
	protected $allowed = false;

	/**
	 * Constructor.
	 *
	 * Initializes the class and hooks.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initializes the class by setting up hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'kagg_settings_tab', [ $this, 'action_settings_tab' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'maybe_show_popup' ] );
		add_action( 'wp_ajax_' . self::MARK_SHOWN_ACTION, [ $this, 'mark_shown' ] );
		add_filter( 'update_footer', [ $this, 'update_footer' ], 1010 );
	}

	/**
	 * Settings tab action.
	 *
	 * @return void
	 */
	public function action_settings_tab(): void {
		$this->allowed = true;
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->allowed ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/whats-new$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/whats-new$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);
		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'markShownAction' => self::MARK_SHOWN_ACTION,
				'markShownNonce'  => wp_create_nonce( self::MARK_SHOWN_ACTION ),
			]
		);
	}

	/**
	 * Maybe show popup.
	 *
	 * @return void
	 */
	public function maybe_show_popup(): void {
		if ( ! $this->allowed ) {
			return;
		}

		$prefix   = self::PREFIX;
		$shown    = hcaptcha()->settings()->get( self::WHATS_NEW_KEY );
		$current  = explode( '-', constant( 'HCAPTCHA_VERSION' ) )[0];
		$methods  = array_filter(
			get_class_methods( $this ),
			static function ( $method ) use ( $prefix ) {
				return 0 === strpos( $method, $prefix );
			}
		);
		$versions = array_map(
			function ( $method ) {
				return $this->method_to_version( $method );
			},
			$methods
		);

		usort( $versions, 'version_compare' );

		// Sort versions in descending order.
		$versions = array_reverse( $versions );
		$method   = '';

		foreach ( $versions as $version ) {
			// Find the first news version that is less or equal to the current version.
			if ( version_compare( $version, $current, '<=' ) ) {
				$method = $this->version_to_method( $version );

				break;
			}
		}

		$display = version_compare( $shown, $this->method_to_version( $method ), '<' );

		$this->render_popup( $method, $display );
	}

	/**
	 * Ajax action to mark content as shown.
	 *
	 * @return void
	 */
	public function mark_shown(): void {
		// Run a security check.
		if ( ! check_ajax_referer( self::MARK_SHOWN_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		$version = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';

		$this->update_whats_new( $version );
		wp_send_json_success();
	}

	/**
	 * Show a new features link in the update footer.
	 *
	 * @param string|mixed $content The content that will be printed.
	 *
	 * @return string|mixed
	 */
	public function update_footer( $content ) {
		if ( ! $this->allowed ) {
			return $content;
		}

		$link = sprintf(
			'<a href="#" id="hcaptcha-whats-new-link" rel="noopener noreferrer">%1$s</a>',
			__( 'See the new features!', 'hcaptcha-for-forms-and-more' )
		);

		return $content . ' - ' . $link;
	}

	/**
	 * Render popup.
	 *
	 * @param string $method  Popup method.
	 * @param bool   $display Display popup.
	 *
	 * @return void
	 */
	protected function render_popup( string $method, bool $display ): void {
		if ( ! method_exists( $this, $method ) ) {
			return;
		}

		$display_attr = $display ? 'flex' : 'none';
		$version      = $this->method_to_version( $method );

		?>
		<div
				id="hcaptcha-whats-new-modal" class="hcaptcha-whats-new-modal"
				style="display: <?php echo esc_attr( $display_attr ); ?>;">
			<div class="hcaptcha-whats-new-modal-bg"></div>
			<div class="hcaptcha-whats-new-modal-popup">
				<button id="hcaptcha-whats-new-close" class="hcaptcha-whats-new-close"></button>
				<div class="hcaptcha-whats-new-header">
					<div class="hcaptcha-whats-new-icon">
						<img
								src="<?php echo esc_url( HCAPTCHA_URL . '/assets/images/hcaptcha-icon-animated.svg' ); ?>"
								alt="Icon">
					</div>
					<div class="hcaptcha-whats-new-title">
						<h1>
							<?php esc_html_e( "What's New in hCaptcha", 'hcaptcha-for-forms-and-more' ); ?>
							<span id="hcaptcha-whats-new-version"><?php echo esc_html( $version ); ?></span>
						</h1>
					</div>
				</div>
				<div class="hcaptcha-whats-new-content">
					<?php $this->$method(); ?>
				</div>
			</div>
		</div>
		<div id="hcaptcha-lightbox-modal">
			<img id="hcaptcha-lightbox-img" src="" alt="lightbox-image">
		</div>
		<?php
	}

	/**
	 * What's New 4.13.0 content.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 * @noinspection PhpUnused
	 */
	protected function whats_new_4_13_0(): void {
		$urls = $this->prepare_urls();

		$block1 = [
			'type'    => 'center',
			'badge'   => __( 'New Feature', 'hcaptcha-for-forms-and-more' ),
			'title'   => __( 'Site Content Protection', 'hcaptcha-for-forms-and-more' ),
			'message' => sprintf(
				'<p>%1$s</p><p>%2$s</p>',
				sprintf(
				/* translators: 1: Pro link. */
					__( 'Protect selected site URLs from bots with hCaptcha. Works best with %1$s 99.9%% passive mode.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$urls['dashboard'],
						__( 'Pro', 'hcaptcha-for-forms-and-more' )
					)
				),
				__( 'Set up protected URLs to prevent these pages from being accessed by bots.', 'hcaptcha-for-forms-and-more' )
			),
			'button'  => [
				'url'  => $urls['protect_content'],
				'text' => __( 'Protect Content', 'hcaptcha-for-forms-and-more' ),
			],
			'image'   => [
				'url'      => $urls['protect_content_demo'],
				'lightbox' => true,
			],
		];

		$block2 = [
			'type'    => 'center',
			'badge'   => __( 'New Feature', 'hcaptcha-for-forms-and-more' ),
			'title'   => __( 'Friction-free “No CAPTCHA” & 99.9% passive modes', 'hcaptcha-for-forms-and-more' ),
			'message' =>
				sprintf(
				/* translators: 1: Pro link, 2: size select link. */
					__( '%1$s and use %2$s. The hCaptcha widget will not appear, and the Challenge popup will be shown only to bots.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$urls['dashboard'],
						__( 'Upgrade to Pro', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$urls['size'],
						__( 'Invisible Size', 'hcaptcha-for-forms-and-more' )
					)
				),
			'button'  => [
				'url'  => $urls['dashboard'],
				'text' => __( 'Upgrade to Pro', 'hcaptcha-for-forms-and-more' ),
			],
			'image'   => [
				'url'      => $urls['passive_mode_demo'],
				'lightbox' => true,
			],
		];

		$this->show_block( $block1 );
		$this->show_block( $block2 );
	}

	/**
	 * Show block.
	 *
	 * @param array $block Block.
	 *
	 * @return void
	 */
	private function show_block( array $block ): void {
		$badge = $block['badge'] ?? '';

		if ( $badge ) {
			ob_start();

			?>
			<div class="hcaptcha-whats-new-badge">
				<?php echo esc_html( $badge ); ?>
			</div>
			<?php

			$badge = ob_get_clean();
		}

		?>
		<div class="hcaptcha-whats-new-block <?php echo esc_attr( $block['type'] ); ?>">
			<?php echo wp_kses_post( $badge ); ?>
			<h2>
				<?php echo esc_html( $block['title'] ); ?>
			</h2>
			<div class="hcaptcha-whats-new-message">
				<?php echo wp_kses_post( $block['message'] ); ?>
			</div>
			<div class="hcaptcha-whats-new-button">
				<a
						href="<?php echo esc_url( $block['button']['url'] ); ?>" class="button button-primary"
						target="_blank">
					<?php echo esc_html( $block['button']['text'] ); ?>
				</a>
			</div>
			<div class="hcaptcha-whats-new-image">
				<?php if ( ! empty( $block['image']['url'] ) ) : ?>
					<?php if ( ! empty( $block['image']['lightbox'] ) ) : ?>
						<a href="<?php echo esc_url( $block['image']['url'] ); ?>" class="hcaptcha-lightbox">
							<img src="<?php echo esc_url( $block['image']['url'] ); ?>" alt="What's New block image">
						</a>
					<?php else : // @codeCoverageIgnoreStart ?>
						<img src="<?php echo esc_url( $block['image']['url'] ); ?>" alt="What's New block image">
					<?php endif; // @codeCoverageIgnoreEnd ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Update shown What's New version.
	 *
	 * @param string $version Version.
	 */
	protected function update_whats_new( string $version ): void {
		if ( ! $this->is_valid_version( $version ) ) {
			return;
		}

		$tab = hcaptcha()->settings()->get_tab( General::class );

		if ( ! $tab ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$tab->update_option( self::WHATS_NEW_KEY, $version );
	}

	/**
	 * Check if a version is valid.
	 *
	 * @param string $version Version.
	 *
	 * @return bool
	 */
	private function is_valid_version( string $version ): bool {
		return (bool) preg_match( '/^\d+(\.\d+)*([a-zA-Z0-9\-._]*)?$/', $version );
	}

	/**
	 * Convert method name to version.
	 *
	 * @param string $method Method name.
	 *
	 * @return string
	 */
	private function method_to_version( string $method ): string {
		return str_replace( [ self::PREFIX, '_' ], [ '', '.' ], $method );
	}

	/**
	 * Convert a version to method name.
	 *
	 * @param string $version Version.
	 *
	 * @return string
	 */
	protected function version_to_method( string $version ): string {
		return self::PREFIX . str_replace( '.', '_', $version );
	}
}
