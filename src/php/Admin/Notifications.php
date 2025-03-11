<?php
/**
 * Notifications class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Settings\EventsPage;
use HCaptcha\Settings\FormsPage;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;

/**
 * Class Notifications.
 *
 * Show notifications in the admin.
 */
class Notifications {

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'hcaptcha-notifications';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaNotificationsObject';

	/**
	 * Dismiss notification ajax action.
	 */
	public const DISMISS_NOTIFICATION_ACTION = 'hcaptcha-dismiss-notification';

	/**
	 * Reset notifications ajax action.
	 */
	public const RESET_NOTIFICATIONS_ACTION = 'hcaptcha-reset-notifications';

	/**
	 * Dismissed user meta.
	 */
	public const HCAPTCHA_DISMISSED_META_KEY = 'hcaptcha_dismissed';

	/**
	 * Notifications.
	 *
	 * @var array
	 */
	protected $notifications = [];

	/**
	 * Shuffle notifications.
	 *
	 * @var bool
	 */
	protected $shuffle = true;

	/**
	 * Init class.
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
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::DISMISS_NOTIFICATION_ACTION, [ $this, 'dismiss_notification' ] );
		add_action( 'wp_ajax_' . self::RESET_NOTIFICATIONS_ACTION, [ $this, 'reset_notifications' ] );
	}

	/**
	 * Get tab url.
	 *
	 * @param string $classname Tab class name.
	 *
	 * @return string
	 */
	private function tab_url( string $classname ): string {
		$tab = hcaptcha()->settings()->get_tab( $classname );

		return $tab ? $tab->tab_url( $tab ) : '';
	}

	/**
	 * Get notifications.
	 *
	 * @return array
	 * @noinspection HtmlUnknownTarget
	 */
	protected function get_notifications(): array {
		$general_url             = $this->tab_url( General::class );
		$integrations_url        = $this->tab_url( Integrations::class );
		$forms_url               = $this->tab_url( FormsPage::class );
		$events_url              = $this->tab_url( EventsPage::class );
		$utm                     = '/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=';
		$utm_sk                  = $utm . 'sk';
		$utm_not                 = $utm . 'not';
		$hcaptcha_url            = 'https://www.hcaptcha.com' . $utm_sk;
		$register_url            = 'https://www.hcaptcha.com/signup-interstitial' . $utm_sk;
		$pro_url                 = 'https://www.hcaptcha.com/pro' . $utm_not;
		$dashboard_url           = 'https://dashboard.hcaptcha.com' . $utm_not;
		$post_leadership_url     = 'https://www.hcaptcha.com/post/hcaptcha-named-a-technology-leader-in-bot-management' . $utm_not;
		$rate_url                = 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post';
		$search_integrations_url = $integrations_url . '#hcaptcha-integrations-search';
		$enterprise_features_url = 'https://www.hcaptcha.com/#enterprise-features' . $utm_not;
		$statistics_url          = $general_url . '#statistics_1';
		$force_url               = $general_url . '#force_1';
		$elementor_edit_form_url = HCAPTCHA_URL . '/assets/images/elementor-edit-form.png';
		$size_url                = $general_url . '#size';

		$notifications = [
			'register'            => [
				'title'   => __( 'Get your hCaptcha site keys', 'hcaptcha-for-forms-and-more' ),
				'message' => sprintf(
				/* translators: 1: hCaptcha link, 2: register link. */
					__( 'To use %1$s, please register %2$s to get your site and secret keys.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$hcaptcha_url,
						__( 'hCaptcha', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$register_url,
						__( 'here', 'hcaptcha-for-forms-and-more' )
					)
				),
				'button'  => [
					'url'  => $register_url,
					'text' => __( 'Get site keys', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'pro-free-trial'      => [
				'title'   => __( 'Try Pro for free', 'hcaptcha-for-forms-and-more' ),
				'message' => sprintf(
				/* translators: 1: hCaptcha Pro link, 2: dashboard link. */
					__( 'Want low friction and custom themes? %1$s is for you. %2$s, no credit card required.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$pro_url,
						__( 'hCaptcha Pro', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$dashboard_url,
						__( 'Start a free trial in your dashboard', 'hcaptcha-for-forms-and-more' )
					)
				),
				'button'  => [
					'url'  => $pro_url,
					'text' => __( 'Try Pro', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'post-leadership'     => [
				'title'   => __( 'hCaptcha\'s Leadership', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'hCaptcha Named a Technology Leader in Bot Management: 2023 SPARK Matrix™', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $post_leadership_url,
					'text' => __( 'Read post', 'hcaptcha-for-forms-and-more' ),
				],
			],
			'please-rate'         => [
				'title'   => __( 'Rate hCaptcha plugin', 'hcaptcha-for-forms-and-more' ),
				'message' => sprintf(
				/* translators: 1: plugin name, 2: wp.org review link with stars, 3: wp.org review link with text. */
					__( 'Please rate %1$s %2$s on %3$s. Thank you!', 'hcaptcha-for-forms-and-more' ),
					'<strong>hCaptcha for WP</strong>',
					sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">★★★★★</a>',
						$rate_url
					),
					sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">WordPress.org</a>',
						$rate_url
					)
				),
				'button'  => [
					'url'  => $rate_url,
					'text' => __( 'Rate', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 3.8.0.
			'search-integrations' => [
				'title'   => __( 'Search on Integrations page', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'You can search for plugin an themes on the Integrations page.', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $search_integrations_url,
					'text' => __( 'Start search', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 3.9.0.
			'enterprise-support'  => [
				'title'   => __( 'Support for Enterprise features', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'The hCaptcha plugin commenced support for Enterprise features. Solve your fraud and abuse problem today.', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $enterprise_features_url,
					'text' => __( 'Get started', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 4.0.0.
			'statistics'          => [
				'title'   => __( 'Events statistics and Forms admin page', 'hcaptcha-for-forms-and-more' ),
				'message' => sprintf(
				/* translators: 1: statistics switch link, 2: the 'forms' page link. */
					__( '%1$s events statistics and %2$s how your forms are used.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$statistics_url,
						__( 'Turn on', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$forms_url,
						__( 'see', 'hcaptcha-for-forms-and-more' )
					)
				),
				'button'  => [
					'url'  => $statistics_url,
					'text' => __( 'Turn on stats', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 4.0.0.
			'events_page'         => [
				'title'   => __( 'Events admin page', 'hcaptcha-for-forms-and-more' ),
				'message' => sprintf(
				/* translators: 1: statistics switch link, 2: Pro link, 3: the 'forms' page link. */
					__( '%1$s events statistics and %2$s to %3$s complete statistics on form events.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$statistics_url,
						__( 'Turn on', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$dashboard_url,
						__( 'upgrade to Pro', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$events_url,
						__( 'see', 'hcaptcha-for-forms-and-more' )
					)
				),
				'button'  => [
					'url'  => $statistics_url,
					'text' => __( 'Turn on stats', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 4.0.0.
			'force'               => [
				'title'   => __( 'Force hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'Force hCaptcha check before submitting the form and simplify the user experience.', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $force_url,
					'text' => __( 'Turn on force', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 4.2.0.
			'auto-activation'     => [
				'title'   => __( 'Activation of dependent plugins', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'Automatic activation of dependent plugins on the Integrations page. Try to activate Elementor or Woo Wishlists.', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $integrations_url,
					'text' => __( 'Try auto-activation', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 4.4.0.
			'admin-elementor'     => [
				'title'   => __( 'Add hCaptcha to Elementor Pro Form', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'Add hCaptcha to Elementor Pro Form in the Elementor admin editor.', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $elementor_edit_form_url,
					'text' => __( 'See an example', 'hcaptcha-for-forms-and-more' ),
				],
			],
			// Added in 4.12.0.
			'passive-mode'        => [
				'title'   => __( 'Friction-free “No CAPTCHA” & 99.9% passive modes', 'hcaptcha-for-forms-and-more' ),
				'message' => sprintf(
				/* translators: 1: Pro link, 2: size select link. */
					__( '%1$s and use %2$s. The hCaptcha widget will not appear, and the Challenge popup will be shown only to bots.', 'hcaptcha-for-forms-and-more' ),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$dashboard_url,
						__( 'Upgrade to Pro', 'hcaptcha-for-forms-and-more' )
					),
					sprintf(
						'<a href="%1$s" target="_blank">%2$s</a>',
						$size_url,
						__( 'Invisible Size', 'hcaptcha-for-forms-and-more' )
					)
				),
				'button'  => [
					'url'  => $elementor_edit_form_url,
					'text' => __( 'See an example', 'hcaptcha-for-forms-and-more' ),
				],
			],
		];

		$settings = hcaptcha()->settings();

		if ( ! empty( $settings->get_site_key() ) && ! empty( $settings->get_secret_key() ) ) {
			unset( $notifications['register'] );
		}

		if ( $settings->is_pro() ) {
			unset( $notifications['pro-free-trial'] );
		}

		if ( $settings->is_on( 'statistics' ) ) {
			unset( $notifications['statistics'] );
		}

		if ( $settings->is_on( 'statistics' ) && $settings->is_pro() ) {
			unset( $notifications['events_page'] );
		}

		if ( $settings->is_on( 'force' ) ) {
			unset( $notifications['force'] );
		}

		if ( ! class_exists( '\ElementorPro\Plugin', false ) ) {
			unset( $notifications['admin-elementor'] );
		}

		if ( $settings->is_pro() && $settings->is( 'size', 'invisible' ) ) {
			unset( $notifications['passive-mode'] );
		}

		// Added in 4.4.0.
		return array_merge( $notifications, $this->cf7_admin_notification() );
	}

	/**
	 * Contact Form 7 admin notification.
	 *
	 * @return array
	 */
	private function cf7_admin_notification(): array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return [];
		}

		// Get the latest CF7 form.
		$args      = [
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		$cf7_forms = get_posts( $args );

		if ( empty( $cf7_forms ) ) {
			return [];
		}

		$form_id  = $cf7_forms[0]->ID;
		$edit_url = admin_url( "?page=wpcf7&post=$form_id&action=edit#postbox-container-live" );

		return [
			'admin-cf7' => [
				'title'   => __( 'Live form in Contact Form 7 admin', 'hcaptcha-for-forms-and-more' ),
				'message' => __( 'With the hCaptcha plugin, you can see a live form on the form edit admin page.', 'hcaptcha-for-forms-and-more' ),
				'button'  => [
					'url'  => $edit_url,
					'text' => __( 'Use live form', 'hcaptcha-for-forms-and-more' ),
				],
			],
		];
	}

	/**
	 * Show notifications.
	 *
	 * @return void
	 */
	public function show(): void {
		$notifications = $this->get_notifications();

		$user    = wp_get_current_user();
		$user_id = $user->ID ?? 0;

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		$dismissed     = get_user_meta( $user_id, self::HCAPTCHA_DISMISSED_META_KEY, true ) ?: [];
		$notifications = array_diff_key( $notifications, array_flip( $dismissed ) );

		if ( ! $notifications ) {
			return;
		}

		?>
		<div id="hcaptcha-notifications">
			<div id="hcaptcha-notifications-header">
				<?php esc_html_e( 'Notifications', 'hcaptcha-for-forms-and-more' ); ?>
			</div>
			<?php

			if ( $this->shuffle ) {
				$notifications = $this->shuffle_assoc( $notifications );
				$notifications = $this->make_key_first( $notifications, 'register' );
			}

			foreach ( $notifications as $id => $notification ) {
				$title       = $notification['title'] ?: '';
				$message     = $notification['message'] ?? '';
				$button_url  = $notification['button']['url'] ?? '';
				$button_text = $notification['button']['text'] ?? '';
				$button      = '';

				if ( $button_url && $button_text ) {
					ob_start();
					?>
					<div class="hcaptcha-notification-buttons hidden">
						<a href="<?php echo esc_url( $button_url ); ?>" class="button button-primary" target="_blank">
							<?php echo esc_html( $button_text ); ?>
						</a>
					</div>
					<?php
					$button = ob_get_clean();
				}

				// We need 'inline' class below to prevent moving the 'notice' div after h2 by common.js script in WP Core.
				?>
				<div
						class="hcaptcha-notification notice notice-info is-dismissible inline"
						data-id="<?php echo esc_attr( $id ); ?>">
					<div class="hcaptcha-notification-title">
						<?php echo esc_html( $title ); ?>
					</div>
					<p><?php echo wp_kses_post( $message ); ?></p>
					<?php echo wp_kses_post( $button ); ?>
				</div>
				<?php
			}

			$next_disabled = count( $notifications ) === 1 ? 'disabled' : '';

			?>
			<div id="hcaptcha-notifications-footer">
				<div id="hcaptcha-navigation">
					<span>
						<span id="hcaptcha-navigation-page">1</span>
						<?php esc_html_e( 'of', 'hcaptcha-for-forms-and-more' ); ?>
						<span id="hcaptcha-navigation-pages"><?php echo count( $notifications ); ?></span>
					</span>
					<a class="prev disabled"></a>
					<a class="next <?php echo esc_attr( $next_disabled ); ?>"></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/notifications$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
				'dismissNotificationAction' => self::DISMISS_NOTIFICATION_ACTION,
				'dismissNotificationNonce'  => wp_create_nonce( self::DISMISS_NOTIFICATION_ACTION ),
				'resetNotificationAction'   => self::RESET_NOTIFICATIONS_ACTION,
				'resetNotificationNonce'    => wp_create_nonce( self::RESET_NOTIFICATIONS_ACTION ),
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/notifications$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Ajax action to dismiss notification.
	 *
	 * @return void
	 */
	public function dismiss_notification(): void {
		// Run a security check.
		if ( ! check_ajax_referer( self::DISMISS_NOTIFICATION_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

		if ( ! $this->update_dismissed( $id ) ) {
			wp_send_json_error( esc_html__( 'Error dismissing notification.', 'hcaptcha-for-forms-and-more' ) );
		}

		wp_send_json_success();
	}

	/**
	 * Update dismissed notifications.
	 *
	 * @param string $id Notification id.
	 *
	 * @return bool
	 */
	private function update_dismissed( string $id ): bool {
		if ( ! $id ) {
			return false;
		}

		$user    = wp_get_current_user();
		$user_id = $user->ID ?? 0;

		$dismissed = get_user_meta( $user_id, self::HCAPTCHA_DISMISSED_META_KEY, true ) ?: [];

		if ( in_array( $id, $dismissed, true ) ) {
			return false;
		}

		$dismissed[] = $id;

		return (bool) update_user_meta( $user_id, self::HCAPTCHA_DISMISSED_META_KEY, $dismissed );
	}

	/**
	 * Ajax action to reset notifications.
	 *
	 * @return void
	 */
	public function reset_notifications(): void {
		// Run a security check.
		if ( ! check_ajax_referer( self::RESET_NOTIFICATIONS_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( ! $this->remove_dismissed() ) {
			wp_send_json_error( esc_html__( 'Error removing dismissed notifications.', 'hcaptcha-for-forms-and-more' ) );
		}

		ob_start();
		$this->show();

		wp_send_json_success( wp_kses_post( ob_get_clean() ) );
	}

	/**
	 * Remove dismissed status for all notifications.
	 *
	 * @return bool
	 */
	private function remove_dismissed(): bool {
		$user    = wp_get_current_user();
		$user_id = $user->ID ?? 0;

		return delete_user_meta( $user_id, self::HCAPTCHA_DISMISSED_META_KEY );
	}

	/**
	 * Shuffle array retaining its keys.
	 *
	 * @param array $arr Array.
	 *
	 * @return array
	 * @noinspection NonSecureShuffleUsageInspection
	 */
	private function shuffle_assoc( array $arr ): array {
		$new_arr = [];
		$keys    = array_keys( $arr );

		shuffle( $keys );

		foreach ( $keys as $key ) {
			$new_arr[ $key ] = $arr[ $key ];
		}

		return $new_arr;
	}

	/**
	 * Make a key the first element in an associative array.
	 *
	 * @param array  $arr An array.
	 * @param string $key Key.
	 *
	 * @return array
	 */
	protected function make_key_first( array $arr, string $key ): array {
		if ( ! array_key_exists( $key, $arr ) ) {
			return $arr;
		}

		// Remove the key-value pair from the original array.
		$value = $arr[ $key ];
		unset( $arr[ $key ] );

		// Merge the key-value pair back into the array at the beginning.
		return array_merge( [ $key => $value ], $arr );
	}
}
