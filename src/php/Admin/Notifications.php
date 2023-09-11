<?php
/**
 * Notifications class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

/**
 * Class Notifications.
 *
 * Show notifications in the admin.
 */
class Notifications {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-notifications';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaNotificationsObject';

	/**
	 * Dismiss notification ajax action.
	 */
	const DISMISS_NOTIFICATION_ACTION = 'hcaptcha-dismiss-notification';

	/**
	 * Reset notifications ajax action.
	 */
	const RESET_NOTIFICATIONS_ACTION = 'hcaptcha-reset-notifications';

	/**
	 * Dismissed user meta.
	 */
	const HCAPTCHA_DISMISSED_META_KEY = 'hcaptcha_dismissed';

	/**
	 * Notifications.
	 *
	 * @var array
	 */
	private $notifications = [];

	/**
	 * Init class.
	 */
	public function init() {
		$this->init_notifications();
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::DISMISS_NOTIFICATION_ACTION, [ $this, 'dismiss_notification' ] );
		add_action( 'wp_ajax_' . self::RESET_NOTIFICATIONS_ACTION, [ $this, 'reset_notifications' ] );
	}

	/**
	 * Init notifications.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	private function init_notifications() {
		$hcaptcha_url  = 'https://www.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk';
		$register_url  = 'https://www.hcaptcha.com/signup-interstitial/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk';
		$pro_url       = 'https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not';
		$dashboard_url = 'https://dashboard.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not';

		$this->notifications = [
			'register'       => [
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
			'pro-free-trial' => [
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
		];
	}

	/**
	 * Show notifications.
	 *
	 * @return void
	 */
	public function show() {
		$user = wp_get_current_user();

		if ( null === $user ) {
			return;
		}

		$dismissed     = (array) get_user_meta( $user->ID, self::HCAPTCHA_DISMISSED_META_KEY, true );
		$notifications = array_diff_key( $this->notifications, array_flip( $dismissed ) );

		if ( ! $notifications ) {
			return;
		}

		?>
		<div id="hcaptcha-notifications">
			<div id="hcaptcha-notifications-header">
				<?php esc_html_e( 'Notifications', 'hcaptcha-for-forms-and-more' ); ?>
			</div>
			<?php

			foreach ( $notifications as $id => $notification ) {
				if ( array_key_exists( $id, $dismissed ) ) {
					continue;
				}

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
					<a class="prev disabled"></a>
					<a class="next <?php echo esc_attr( $next_disabled ); ?>"></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
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
	public function dismiss_notification() {
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
			wp_send_json_error();
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

		$user = wp_get_current_user();

		if ( ! $user ) {
			return false;
		}

		$dismissed = get_user_meta( $user->ID, self::HCAPTCHA_DISMISSED_META_KEY, true ) ?: [];

		if ( in_array( $id, $dismissed, true ) ) {
			return false;
		}

		$dismissed[] = $id;

		$result = update_user_meta( $user->ID, self::HCAPTCHA_DISMISSED_META_KEY, $dismissed );

		if ( ! $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Ajax action to reset notifications.
	 *
	 * @return void
	 */
	public function reset_notifications() {
		// Run a security check.
		if ( ! check_ajax_referer( self::RESET_NOTIFICATIONS_ACTION, 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Your session has expired. Please reload the page.', 'hcaptcha-for-forms-and-more' ) );
		}

		// Check for permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'hcaptcha-for-forms-and-more' ) );
		}

		if ( ! $this->remove_dismissed() ) {
			wp_send_json_error();
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
		$user = wp_get_current_user();

		if ( ! $user ) {
			return false;
		}

		return delete_user_meta( $user->ID, self::HCAPTCHA_DISMISSED_META_KEY );
	}
}
