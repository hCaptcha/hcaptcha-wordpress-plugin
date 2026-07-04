<?php
/**
 * SupportModal class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Helpers\Request;
use HCaptcha\Settings\SystemInfo;

/**
 * Class SupportModal.
 *
 * Shows a support/report modal on hCaptcha admin pages.
 */
class SupportModal {

	/**
	 * Handle for assets.
	 */
	private const HANDLE = 'hcaptcha-support-modal';

	/**
	 * Script localization object.
	 */
	private const OBJECT = 'HCaptchaSupportModalObject';

	/**
	 * GitHub issue URL.
	 */
	private const GITHUB_ISSUE_URL = 'https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues/new';

	/**
	 * WordPress.org support URL.
	 */
	private const WORDPRESS_SUPPORT_URL = 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/#new-topic-0';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'kagg_settings_header', [ $this, 'render_actions_start' ], 0 );
		add_action( 'kagg_settings_header', [ $this, 'render_button' ], 20 );
		add_action( 'kagg_settings_header', [ $this, 'render_actions_end' ], PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_footer', [ $this, 'render_modal' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_hcaptcha_admin_page() ) {
			return;
		}

		$min = hcap_min_suffix();

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/support-modal$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/support-modal$min.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'githubIssueUrl'      => self::GITHUB_ISSUE_URL,
				'wordpressSupportUrl' => self::WORDPRESS_SUPPORT_URL,
				'systemInfo'          => $this->get_system_info(),
				'strings'             => $this->get_script_strings(),
			]
		);
	}


	/**
	 * Render header actions start.
	 *
	 * @return void
	 */
	public function render_actions_start(): void {
		if ( ! $this->is_hcaptcha_admin_page() ) {
			return;
		}

		?>
		<div class="hcaptcha-header-actions">
		<?php
	}

	/**
	 * Render Help button.
	 *
	 * @return void
	 */
	public function render_button(): void {
		if ( ! $this->is_hcaptcha_admin_page() ) {
			return;
		}

		?>
		<button
				type="button"
				class="button button-secondary hcaptcha-help-button"
				aria-haspopup="dialog"
				aria-controls="hcaptcha-support-modal">
			<?php esc_html_e( 'Help', 'hcaptcha-for-forms-and-more' ); ?>
		</button>
		<?php
	}

	/**
	 * Render header actions end.
	 *
	 * @return void
	 */
	public function render_actions_end(): void {
		if ( ! $this->is_hcaptcha_admin_page() ) {
			return;
		}

		?>
		</div>
		<?php
	}

	/**
	 * Render modal.
	 *
	 * @return void
	 */
	public function render_modal(): void {
		if ( ! $this->is_hcaptcha_admin_page() ) {
			return;
		}

		?>
		<div
				id="hcaptcha-support-modal"
				class="hcaptcha-support-modal"
				hidden
				role="dialog"
				aria-modal="true"
				aria-labelledby="hcaptcha-support-title"
				aria-describedby="hcaptcha-support-privacy hcaptcha-support-status">
			<div class="hcaptcha-support-modal-bg" data-hcaptcha-support-close></div>
			<div class="hcaptcha-support-modal-box" role="document">
				<button
						type="button"
						class="hcaptcha-support-close"
						data-hcaptcha-support-close
						aria-label="<?php esc_attr_e( 'Close support dialog', 'hcaptcha-for-forms-and-more' ); ?>">
					<span class="screen-reader-text">
						<?php esc_html_e( 'Close', 'hcaptcha-for-forms-and-more' ); ?>
					</span>
				</button>

				<div class="hcaptcha-support-modal-header">
					<h2 id="hcaptcha-support-title">
						<?php esc_html_e( 'Need help with hCaptcha?', 'hcaptcha-for-forms-and-more' ); ?>
					</h2>
				</div>

				<div class="hcaptcha-support-modal-content">
					<div class="hcaptcha-support-form">
						<fieldset class="hcaptcha-support-request-types">
							<legend><?php esc_html_e( 'Request type', 'hcaptcha-for-forms-and-more' ); ?></legend>
							<?php $this->render_request_type_radios(); ?>
						</fieldset>

						<p class="hcaptcha-support-field">
							<label for="hcaptcha-support-summary">
								<?php esc_html_e( 'Summary / title', 'hcaptcha-for-forms-and-more' ); ?>
								<span class="required">*</span>
							</label>
							<input type="text" id="hcaptcha-support-summary" class="regular-text" required>
						</p>

						<p class="hcaptcha-support-field">
							<label for="hcaptcha-support-area">
								<?php esc_html_e( 'Affected area / integration', 'hcaptcha-for-forms-and-more' ); ?>
							</label>
							<select id="hcaptcha-support-area">
								<?php $this->render_area_options(); ?>
							</select>
						</p>

						<div class="hcaptcha-support-type-fields" data-hcaptcha-support-fields="bug">
							<?php
							$this->render_textarea_field( 'steps', __( 'Steps to reproduce', 'hcaptcha-for-forms-and-more' ) );
							$this->render_textarea_field( 'expected', __( 'Expected behavior', 'hcaptcha-for-forms-and-more' ) );
							$this->render_textarea_field( 'actual', __( 'Actual behavior', 'hcaptcha-for-forms-and-more' ) );
							?>
						</div>

						<div class="hcaptcha-support-type-fields" data-hcaptcha-support-fields="feature" hidden>
							<?php
							$this->render_textarea_field( 'problem', __( 'Problem / use case', 'hcaptcha-for-forms-and-more' ) );
							$this->render_textarea_field( 'solution', __( 'Proposed solution', 'hcaptcha-for-forms-and-more' ) );
							$this->render_textarea_field( 'alternatives', __( 'Alternative considered / notes', 'hcaptcha-for-forms-and-more' ) );
							?>
						</div>

						<div class="hcaptcha-support-type-fields" data-hcaptcha-support-fields="support" hidden>
							<?php
							$this->render_textarea_field( 'configure', __( 'What are you trying to configure?', 'hcaptcha-for-forms-and-more' ) );
							$this->render_textarea_field( 'tried', __( 'What did you already try?', 'hcaptcha-for-forms-and-more' ) );
							?>
						</div>

						<p class="hcaptcha-support-field">
							<label for="hcaptcha-support-details">
								<?php esc_html_e( 'Details', 'hcaptcha-for-forms-and-more' ); ?>
							</label>
							<textarea id="hcaptcha-support-details" rows="4"></textarea>
						</p>

						<div class="hcaptcha-support-diagnostics">
							<h3><?php esc_html_e( 'Diagnostic information', 'hcaptcha-for-forms-and-more' ); ?></h3>
							<p id="hcaptcha-support-privacy" class="description">
								<?php esc_html_e( 'No information is sent automatically. Please review the report before posting. Secret keys, tokens, cookies, and personal data are never included.', 'hcaptcha-for-forms-and-more' ); ?>
							</p>
							<label class="hcaptcha-support-include-system-info" for="hcaptcha-support-include-system-info">
								<input type="checkbox" id="hcaptcha-support-include-system-info" checked>
								<?php esc_html_e( 'Add system information to the report', 'hcaptcha-for-forms-and-more' ); ?>
							</label>
						</div>
					</div>

					<div class="hcaptcha-support-preview-wrap">
						<label for="hcaptcha-support-report">
							<?php esc_html_e( 'Generated report', 'hcaptcha-for-forms-and-more' ); ?>
						</label>
						<textarea id="hcaptcha-support-report" readonly rows="16"></textarea>
					</div>
				</div>

				<div class="hcaptcha-support-modal-footer">
					<div class="hcaptcha-support-actions">
						<div class="hcaptcha-support-action" data-hcaptcha-support-action="github">
							<button type="button" class="button button-primary" data-hcaptcha-support-continue="github">
								<?php esc_html_e( 'Continue on GitHub', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
							<span class="hcaptcha-support-recommended" hidden>
								<?php esc_html_e( 'Recommended', 'hcaptcha-for-forms-and-more' ); ?>
							</span>
							<button type="button" class="hcaptcha-support-action-help" aria-label="<?php esc_attr_e( 'Show GitHub recommendation details', 'hcaptcha-for-forms-and-more' ); ?>" aria-describedby="hcaptcha-support-github-description" aria-expanded="false">
								<span aria-hidden="true">?</span>
							</button>
							<div class="hcaptcha-support-action-description" role="tooltip">
								<p id="hcaptcha-support-github-description" class="description">
									<?php esc_html_e( 'Best for bugs, reproducible issues, integration problems, and feature requests. It helps us track fixes and link them to releases.', 'hcaptcha-for-forms-and-more' ); ?>
								</p>
							</div>
						</div>
						<div class="hcaptcha-support-action" data-hcaptcha-support-action="wordpress">
							<button type="button" class="button button-secondary" data-hcaptcha-support-continue="wordpress" aria-describedby="hcaptcha-support-wordpress-description hcaptcha-support-wordpress-copy-description" disabled>
								<?php esc_html_e( 'Continue on WordPress.org', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
							<span class="hcaptcha-support-recommended" hidden>
								<?php esc_html_e( 'Recommended', 'hcaptcha-for-forms-and-more' ); ?>
							</span>
							<button type="button" class="hcaptcha-support-action-help" aria-label="<?php esc_attr_e( 'Show WordPress.org recommendation details', 'hcaptcha-for-forms-and-more' ); ?>" aria-describedby="hcaptcha-support-wordpress-description hcaptcha-support-wordpress-copy-description" aria-expanded="false">
								<span aria-hidden="true">?</span>
							</button>
							<div class="hcaptcha-support-action-description" role="tooltip">
								<p id="hcaptcha-support-wordpress-description" class="description">
									<?php esc_html_e( 'Best for general setup questions and public community support.', 'hcaptcha-for-forms-and-more' ); ?>
								</p>
								<p id="hcaptcha-support-wordpress-copy-description" class="description">
									<?php esc_html_e( 'First copy the report, then open the WordPress.org new topic form and paste it into the topic description.', 'hcaptcha-for-forms-and-more' ); ?>
								</p>
							</div>
						</div>
						<div class="hcaptcha-support-action hcaptcha-support-copy-action" data-hcaptcha-support-action="copy">
							<button type="button" class="button button-secondary" data-hcaptcha-support-copy>
								<?php esc_html_e( 'Copy report', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
							<div id="hcaptcha-support-status" class="hcaptcha-support-status" role="status" aria-live="polite"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether the current admin page is hCaptcha admin page.
	 *
	 * @return bool
	 */
	private function is_hcaptcha_admin_page(): bool {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$page = (string) Request::filter_input( INPUT_GET, 'page' );

		if ( 0 === strpos( $page, 'hcaptcha' ) ) {
			return true;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return false;
		}

		$current_suffix = preg_replace( '/.+_page_/', '', $current_screen->id );
		$current_suffix = preg_replace( '/-network$/', '', (string) $current_suffix );

		return 0 === strpos( (string) $current_suffix, 'hcaptcha' );
	}

	/**
	 * Render request type radios.
	 *
	 * @return void
	 */
	private function render_request_type_radios(): void {
		$types = [
			'bug'     => __( 'Report a bug', 'hcaptcha-for-forms-and-more' ),
			'feature' => __( 'Request a feature', 'hcaptcha-for-forms-and-more' ),
			'support' => __( 'Ask a setup question', 'hcaptcha-for-forms-and-more' ),
		];

		foreach ( $types as $value => $label ) {
			$id = 'hcaptcha-support-type-' . $value;

			?>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input
						type="radio"
						id="<?php echo esc_attr( $id ); ?>"
						name="hcaptcha-support-type"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( 'bug', $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</label>
			<?php
		}
	}

	/**
	 * Render affected area options.
	 *
	 * @return void
	 */
	private function render_area_options(): void {
		$options = [
			'Login'                       => __( 'Login', 'hcaptcha-for-forms-and-more' ),
			'Registration'                => __( 'Registration', 'hcaptcha-for-forms-and-more' ),
			'Comments'                    => __( 'Comments', 'hcaptcha-for-forms-and-more' ),
			'WooCommerce'                 => __( 'WooCommerce', 'hcaptcha-for-forms-and-more' ),
			'WooCommerce PayPal Payments' => __( 'WooCommerce PayPal Payments', 'hcaptcha-for-forms-and-more' ),
			'WPForms'                     => __( 'WPForms', 'hcaptcha-for-forms-and-more' ),
			'Gravity Forms'               => __( 'Gravity Forms', 'hcaptcha-for-forms-and-more' ),
			'Elementor'                   => __( 'Elementor', 'hcaptcha-for-forms-and-more' ),
			'Contact Form 7'              => __( 'Contact Form 7', 'hcaptcha-for-forms-and-more' ),
			'Forminator'                  => __( 'Forminator', 'hcaptcha-for-forms-and-more' ),
			'Fluent Forms'                => __( 'Fluent Forms', 'hcaptcha-for-forms-and-more' ),
			'Formidable Forms'            => __( 'Formidable Forms', 'hcaptcha-for-forms-and-more' ),
			'Kadence Forms'               => __( 'Kadence Forms', 'hcaptcha-for-forms-and-more' ),
			'Other'                       => __( 'Other', 'hcaptcha-for-forms-and-more' ),
		];

		foreach ( $options as $value => $label ) {
			?>
			<option value="<?php echo esc_attr( $value ); ?>">
				<?php echo esc_html( $label ); ?>
			</option>
			<?php
		}
	}

	/**
	 * Render a textarea field.
	 *
	 * @param string $id    Field id suffix.
	 * @param string $label Field label.
	 *
	 * @return void
	 */
	private function render_textarea_field( string $id, string $label ): void {
		$field_id = 'hcaptcha-support-' . $id;

		?>
		<p class="hcaptcha-support-field">
			<label for="<?php echo esc_attr( $field_id ); ?>">
				<?php echo esc_html( $label ); ?>
			</label>
			<textarea id="<?php echo esc_attr( $field_id ); ?>" rows="3"></textarea>
		</p>
		<?php
	}

	/**
	 * Get system info.
	 *
	 * @return string
	 */
	private function get_system_info(): string {
		$settings = hcaptcha()->settings();

		if ( ! $settings ) {
			return '';
		}

		$system_info = $settings->get_tab( SystemInfo::class );

		if ( ! $system_info instanceof SystemInfo ) {
			return '';
		}

		return $system_info->get_system_info();
	}

	/**
	 * Get script strings.
	 *
	 * @return array
	 */
	private function get_script_strings(): array {
		return [
			'copySuccess'     => __( 'Report copied to clipboard.', 'hcaptcha-for-forms-and-more' ),
			'copyError'       => __( 'Cannot copy the report automatically. Please copy it from the report field.', 'hcaptcha-for-forms-and-more' ),
			'summaryRequired' => __( 'Please enter a summary before continuing.', 'hcaptcha-for-forms-and-more' ),
			'openFailed'      => __( 'Your browser blocked the new tab. Please allow popups and try again.', 'hcaptcha-for-forms-and-more' ),
			'emptyValue'      => __( 'Not provided', 'hcaptcha-for-forms-and-more' ),
			'report'          => [
				'summary'      => __( 'Summary', 'hcaptcha-for-forms-and-more' ),
				'affectedArea' => __( 'Affected area', 'hcaptcha-for-forms-and-more' ),
				'steps'        => __( 'Steps to reproduce', 'hcaptcha-for-forms-and-more' ),
				'expected'     => __( 'Expected behavior', 'hcaptcha-for-forms-and-more' ),
				'actual'       => __( 'Actual behavior', 'hcaptcha-for-forms-and-more' ),
				'additional'   => __( 'Additional details', 'hcaptcha-for-forms-and-more' ),
				'diagnostics'  => __( 'Diagnostic information', 'hcaptcha-for-forms-and-more' ),
				'feature'      => __( 'Feature request', 'hcaptcha-for-forms-and-more' ),
				'problem'      => __( 'Problem / use case', 'hcaptcha-for-forms-and-more' ),
				'solution'     => __( 'Proposed solution', 'hcaptcha-for-forms-and-more' ),
				'alternatives' => __( 'Alternative considered / notes', 'hcaptcha-for-forms-and-more' ),
				'question'     => __( 'Question', 'hcaptcha-for-forms-and-more' ),
				'configure'    => __( 'What I am trying to configure', 'hcaptcha-for-forms-and-more' ),
				'tried'        => __( 'What I already tried', 'hcaptcha-for-forms-and-more' ),
			],
		];
	}
}
