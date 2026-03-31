<?php
/**
 * MigrationWizard class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard;

use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\PluginSettingsBase;
use WP_Error;

/**
 * Class MigrationWizard.
 *
 * Main controller for the Migration Wizard section on the Tools page and AJAX handlers.
 */
class MigrationWizard {

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'hcaptcha-migration-wizard';

	/**
	 * Kagg Dialog script handle.
	 */
	public const DIALOG_HANDLE = 'kagg-dialog';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaMigrationWizardObject';

	/**
	 * AJAX actions.
	 */
	public const SCAN_ACTION  = 'hcaptcha_migration_scan';
	public const APPLY_ACTION = 'hcaptcha_migration_apply';

	/**
	 * Transient key for wizard state.
	 */
	public const STATE_TRANSIENT = 'hcaptcha_migration_wizard_state';

	/**
	 * Transient expiration in seconds (1 hour).
	 */
	public const STATE_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * Required capability.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Nonce action for scan.
	 */
	private const SCAN_NONCE = 'hcaptcha_migration_scan_nonce';

	/**
	 * Nonce action for applying.
	 */
	private const APPLY_NONCE = 'hcaptcha_migration_apply_nonce';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_' . self::SCAN_ACTION, [ $this, 'ajax_scan' ] );
		add_action( 'wp_ajax_' . self::APPLY_ACTION, [ $this, 'ajax_apply' ] );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$min.js",
			[],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::DIALOG_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/migration-wizard$min.css",
			[ self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/migration-wizard$min.js",
			[ self::DIALOG_HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'scanAction'  => self::SCAN_ACTION,
				'scanNonce'   => wp_create_nonce( self::SCAN_NONCE ),
				'applyAction' => self::APPLY_ACTION,
				'applyNonce'  => wp_create_nonce( self::APPLY_NONCE ),
				'i18n'        => $this->get_i18n_strings(),
			]
		);
	}

	/**
	 * Render the wizard section.
	 *
	 * @return void
	 */
	public function render_section(): void {
		$settings = hcaptcha()->settings();

		// @codeCoverageIgnoreStart
		if ( ! $settings ) {
			return;
		}
		// @codeCoverageIgnoreEnd

		$site_key         = $settings->get( 'site_key' );
		$secret_key       = $settings->get( 'secret_key' );
		$has_keys         = ! empty( $site_key ) && ! empty( $secret_key );
		$general_url      = $settings->tab_url( General::class );
		$integrations_url = $settings->tab_url( Integrations::class );
		$saved_state      = get_transient( self::STATE_TRANSIENT );

		?>
			<div id="hcaptcha-migration-wizard" class="hcaptcha-migration-wizard"
				data-has-keys="<?php echo $has_keys ? '1' : '0'; ?>"
				data-settings-url="<?php echo esc_url( $general_url ); ?>"
				data-saved-state="<?php echo esc_attr( $saved_state ? wp_json_encode( $saved_state ) : '' ); ?>">

				<!-- Step 1: Welcome -->
				<div class="wizard-step" data-step="welcome" style="display:none;">
					<div class="wizard-step-content">
						<p>
							<?php esc_html_e( 'This wizard will scan your site for existing reCAPTCHA or Cloudflare Turnstile protection and help you migrate supported surfaces to hCaptcha.', 'hcaptcha-for-forms-and-more' ); ?>
						</p>
						<ul class="wizard-info-list">
							<li><?php esc_html_e( 'We will detect active CAPTCHA plugins and their settings.', 'hcaptcha-for-forms-and-more' ); ?></li>
							<li><?php esc_html_e( 'We will identify which surfaces can be protected by hCaptcha.', 'hcaptcha-for-forms-and-more' ); ?></li>
							<li><?php esc_html_e( 'Your existing CAPTCHA settings will remain unchanged until you confirm.', 'hcaptcha-for-forms-and-more' ); ?></li>
						</ul>
						<div class="wizard-actions">
							<button type="button" class="button button-primary" id="wizard-scan-btn">
								<?php esc_html_e( 'Scan Site', 'hcaptcha-for-forms-and-more' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Step 2: Scanning -->
				<div class="wizard-step" data-step="scanning" style="display:none;">
					<div class="wizard-step-content">
						<h3><?php esc_html_e( 'Scanning...', 'hcaptcha-for-forms-and-more' ); ?></h3>
						<p><?php esc_html_e( 'Checking your site for existing CAPTCHA configurations...', 'hcaptcha-for-forms-and-more' ); ?></p>
						<div class="wizard-spinner"><span class="spinner is-active"></span></div>
					</div>
				</div>

				<!-- Step 3: Results -->
				<div class="wizard-step" data-step="results" style="display:none;">
					<div class="wizard-step-content">
						<h3><?php esc_html_e( 'Scan Results', 'hcaptcha-for-forms-and-more' ); ?></h3>
						<div id="wizard-no-results" style="display:none;">
							<p><?php esc_html_e( 'No existing reCAPTCHA or Turnstile configuration was detected on your site.', 'hcaptcha-for-forms-and-more' ); ?></p>
							<p>
								<?php

								printf(
									/* translators: %s: link to the integrations page. */
									esc_html__( 'You can manually configure hCaptcha integrations on the %s page.', 'hcaptcha-for-forms-and-more' ),
									'<a href="' . esc_url( $integrations_url ) . '">' . esc_html__( 'Integrations', 'hcaptcha-for-forms-and-more' ) . '</a>'
								);

								?>
							</p>
						</div>

						<div id="wizard-has-results" style="display:none;">
							<div id="wizard-summary-message"></div>

							<!-- Supported -->
							<div id="wizard-supported-section" style="display:none;">
								<h4><?php esc_html_e( 'Ready to Migrate', 'hcaptcha-for-forms-and-more' ); ?></h4>
								<p class="description"><?php esc_html_e( 'These surfaces can be migrated to hCaptcha.', 'hcaptcha-for-forms-and-more' ); ?></p>
								<table class="widefat striped" id="wizard-supported-table">
									<thead>
										<tr>
											<th class="check-column">
												<label for="wizard-select-all"></label><input type="checkbox" id="wizard-select-all" checked>
											</th>
											<th><?php esc_html_e( 'Surface', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Provider', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Source', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Confidence', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Notes', 'hcaptcha-for-forms-and-more' ); ?></th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>

							<!-- Unsupported -->
							<div id="wizard-unsupported-section" style="display:none;">
								<h4><?php esc_html_e( 'Cannot Migrate Automatically', 'hcaptcha-for-forms-and-more' ); ?></h4>
								<p class="description"><?php esc_html_e( 'These surfaces were detected but cannot be migrated automatically.', 'hcaptcha-for-forms-and-more' ); ?></p>
								<table class="widefat striped" id="wizard-unsupported-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Surface', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Provider', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Source', 'hcaptcha-for-forms-and-more' ); ?></th>
											<th><?php esc_html_e( 'Notes', 'hcaptcha-for-forms-and-more' ); ?></th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>

							<!-- Keys check -->
							<div id="wizard-keys-warning" style="display:none;">
								<div class="notice notice-warning inline">
									<p>
										<?php
										printf(
											/* translators: %s: link to settings page. */
											esc_html__( 'hCaptcha site key and secret key are required before migration. Please configure them on the %s page first.', 'hcaptcha-for-forms-and-more' ),
											'<a href="' . esc_url( $general_url ) . '">' . esc_html__( 'General settings', 'hcaptcha-for-forms-and-more' ) . '</a>'
										);
										?>
									</p>
								</div>
							</div>
						</div>

						<!-- Apply actions -->
						<div id="wizard-apply-section" style="display:none;">
							<div class="wizard-actions">
								<button type="button" class="button button-primary" id="wizard-apply-btn">
									<?php esc_html_e( 'Apply Migration', 'hcaptcha-for-forms-and-more' ); ?>
								</button>
								<button type="button" class="button button-secondary" id="wizard-rescan-btn">
									<?php esc_html_e( 'Rescan', 'hcaptcha-for-forms-and-more' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Step 4: Applying -->
				<div class="wizard-step" data-step="applying" style="display:none;">
					<div class="wizard-step-content">
						<h3><?php esc_html_e( 'Applying Migration...', 'hcaptcha-for-forms-and-more' ); ?></h3>
						<p><?php esc_html_e( 'Enabling hCaptcha on selected surfaces...', 'hcaptcha-for-forms-and-more' ); ?></p>
						<div class="wizard-spinner"><span class="spinner is-active"></span></div>
					</div>
				</div>

				<!-- Step 5: Complete -->
				<div class="wizard-step" data-step="complete" style="display:none;">
					<div class="wizard-step-content">
						<h3><?php esc_html_e( 'Migration Complete', 'hcaptcha-for-forms-and-more' ); ?></h3>
						<div id="wizard-complete-summary"></div>

						<div class="wizard-checklist">
							<h4><?php esc_html_e( 'Post-Migration Checklist', 'hcaptcha-for-forms-and-more' ); ?></h4>
							<ul>
								<li><?php esc_html_e( 'Test your login form.', 'hcaptcha-for-forms-and-more' ); ?></li>
								<li><?php esc_html_e( 'Test your registration form.', 'hcaptcha-for-forms-and-more' ); ?></li>
								<li><?php esc_html_e( 'Test your checkout page (if using WooCommerce).', 'hcaptcha-for-forms-and-more' ); ?></li>
								<li><?php esc_html_e( 'Test your key contact forms.', 'hcaptcha-for-forms-and-more' ); ?></li>
								<li><?php esc_html_e( 'Disable or remove old CAPTCHA plugins after verification.', 'hcaptcha-for-forms-and-more' ); ?></li>
							</ul>
						</div>

						<div class="notice notice-warning inline">
							<p><?php esc_html_e( 'Your old CAPTCHA protection is still active. Running two CAPTCHA providers on the same page may cause conflicts or prevent form submissions. Please disable the old provider.', 'hcaptcha-for-forms-and-more' ); ?></p>
						</div>

						<div class="wizard-actions">
							<a href="<?php echo esc_url( $integrations_url ); ?>" class="button button-primary">
								<?php esc_html_e( 'View Integrations', 'hcaptcha-for-forms-and-more' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		<?php
	}

	/**
	 * AJAX handler for scan.
	 *
	 * @return void
	 */
	public function ajax_scan(): void {
		if ( ! check_ajax_referer( self::SCAN_NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'hcaptcha-for-forms-and-more' ) ] );
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'hcaptcha-for-forms-and-more' ) ] );
		}

		wp_send_json_success( $this->scan() );
	}

	/**
	 * Scan migratable surfaces and persist the scan state.
	 *
	 * @return array
	 */
	public function scan(): array {
		$scan_result = $this->create_scanner()->scan();
		$data        = $scan_result->to_array();

		// Check for already-enabled hCaptcha surfaces.
		$data['already_enabled'] = $this->get_already_enabled_surfaces( $scan_result );

		// Save state.
		$state = [
			'scan_timestamp' => time(),
			'scan_data'      => $data,
		];

		set_transient( self::STATE_TRANSIENT, $state, self::STATE_EXPIRATION );

		return $data;
	}

	/**
	 * AJAX handler for applying migration.
	 *
	 * @return void
	 */
	public function ajax_apply(): void {
		if ( ! check_ajax_referer( self::APPLY_NONCE, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'hcaptcha-for-forms-and-more' ) ] );
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'hcaptcha-for-forms-and-more' ) ] );
		}

		$surfaces = $this->get_validated_surfaces_from_request();

		if ( is_wp_error( $surfaces ) ) {
			wp_send_json_error( [ 'message' => $surfaces->get_error_message() ] );

			return;
		}

		$result = $this->apply( $surfaces );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );

			return;
		}

		wp_send_json_success(
			[
				'enabled' => $result['enabled'],
				'failed'  => $result['failed'],
				'message' => sprintf(
					/* translators: %d: number of enabled surfaces. */
					_n(
						'Successfully enabled hCaptcha on %d surface.',
						'Successfully enabled hCaptcha on %d surfaces.',
						count( $result['enabled'] ),
						'hcaptcha-for-forms-and-more'
					),
					count( $result['enabled'] )
				),
			]
		);
	}

	/**
	 * Apply selected surfaces programmatically.
	 *
	 * @param array $surfaces Validated surfaces data.
	 *
	 * @return array{enabled: string[], failed: string[]}|WP_Error
	 */
	public function apply( array $surfaces ) {
		if ( empty( $surfaces ) ) {
			return new WP_Error( 'no_surfaces_selected', __( 'No surfaces selected.', 'hcaptcha-for-forms-and-more' ) );
		}

		$keys_error = $this->get_keys_error();

		if ( is_wp_error( $keys_error ) ) {
			return $keys_error;
		}

		$result = $this->apply_surfaces( $surfaces );

		$this->save_apply_state( $result );

		return $result;
	}

	/**
	 * Validate and parse surfaces from the request.
	 *
	 * @return array|WP_Error Parsed surfaces array or null on failure.
	 */
	private function get_validated_surfaces_from_request() {
		$surfaces = Request::filter_input( INPUT_POST, 'surfaces' );

		if ( empty( $surfaces ) ) {
			return new WP_Error( 'no_surfaces_selected', __( 'No surfaces selected.', 'hcaptcha-for-forms-and-more' ) );
		}

		$surfaces = Utils::json_decode_arr( wp_unslash( $surfaces ) );

		if ( empty( $surfaces ) ) {
			return new WP_Error( 'invalid_surfaces_data', __( 'Invalid surfaces data.', 'hcaptcha-for-forms-and-more' ) );
		}

		return $surfaces;
	}

	/**
	 * Verify hCaptcha keys are configured.
	 *
	 * @return WP_Error|null
	 */
	private function get_keys_error(): ?WP_Error {
		$settings   = hcaptcha()->settings();
		$site_key   = $settings->get( 'site_key' );
		$secret_key = $settings->get( 'secret_key' );

		if ( ! $site_key || ! $secret_key ) {
			return new WP_Error( 'keys_not_configured', __( 'hCaptcha keys are not configured.', 'hcaptcha-for-forms-and-more' ) );
		}

		return null;
	}

	/**
	 * Create scanner instance.
	 *
	 * @return Scanner
	 */
	protected function create_scanner(): Scanner {
		// @codeCoverageIgnoreStart
		return new Scanner();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Apply selected surfaces to hCaptcha settings.
	 *
	 * @param array $surfaces Validated surfaces data.
	 *
	 * @return array{enabled: string[], failed: string[]}
	 */
	private function apply_surfaces( array $surfaces ): array {
		$enabled  = [];
		$failed   = [];
		$settings = hcaptcha()->settings();

		if ( ! $settings ) {
			// @codeCoverageIgnoreStart
			return [
				'enabled' => [],
				'failed'  => [],
			];
			// @codeCoverageIgnoreEnd
		}

		foreach ( $surfaces as $surface ) {
			$surface_id = sanitize_text_field( $surface['surface'] ?? '' );
			$option_key = sanitize_text_field( $surface['hcaptcha_option_key'] ?? '' );
			$option_val = sanitize_text_field( $surface['hcaptcha_option_value'] ?? '' );

			if ( ! $this->is_valid_surface( $surface_id, $option_key, $option_val ) ) {
				$failed[] = $surface_id ?: 'unknown';

				continue;
			}

			$current = $settings->get( $option_key );

			if ( ! in_array( $option_val, $current, true ) ) {
				$current[] = $option_val;

				$settings->update( $option_key, $current );
			}

			$enabled[] = $surface_id;
		}

		return [
			'enabled' => $enabled,
			'failed'  => $failed,
		];
	}

	/**
	 * Validate a single surface mapping.
	 *
	 * @param string $surface_id Surface identifier.
	 * @param string $option_key hCaptcha option key.
	 * @param string $option_val hCaptcha option value.
	 *
	 * @return bool
	 */
	private function is_valid_surface( string $surface_id, string $option_key, string $option_val ): bool {
		if ( empty( $surface_id ) || empty( $option_key ) || empty( $option_val ) ) {
			return false;
		}

		if ( ! SurfaceMapping::is_supported( $surface_id ) ) {
			return false;
		}

		$mapping = SurfaceMapping::get( $surface_id );

		return $mapping && $mapping[0] === $option_key && $mapping[1] === $option_val;
	}

	/**
	 * Save applied result to the wizard state.
	 *
	 * @param array $result Apply a result with 'enabled' and 'failed' keys.
	 *
	 * @return void
	 */
	private function save_apply_state( array $result ): void {
		$state = get_transient( self::STATE_TRANSIENT );

		if ( ! is_array( $state ) ) {
			return;
		}

		$state['apply_result'] = $result;

		set_transient( self::STATE_TRANSIENT, $state, self::STATE_EXPIRATION );
	}

	/**
	 * Get surfaces already enabled in hCaptcha.
	 *
	 * @param ScanResult $scan_result Scan result.
	 *
	 * @return string[]
	 */
	private function get_already_enabled_surfaces( ScanResult $scan_result ): array {
		$hcaptcha_settings = hcaptcha()->settings()->get_raw_settings();

		if ( ! is_array( $hcaptcha_settings ) ) {
			return [];
		}

		$already_enabled = [];

		foreach ( $scan_result->get_results() as $result ) {
			$option_key = $result->get_hcaptcha_option_key();
			$option_val = $result->get_hcaptcha_option_value();

			if ( empty( $option_key ) || empty( $option_val ) ) {
				continue;
			}

			$current = isset( $hcaptcha_settings[ $option_key ] ) ? (array) $hcaptcha_settings[ $option_key ] : [];

			if ( in_array( $option_val, $current, true ) ) {
				$already_enabled[] = $result->get_surface();
			}
		}

		return $already_enabled;
	}

	/**
	 * Get i18n strings for JavaScript.
	 *
	 * @return array
	 */
	private function get_i18n_strings(): array {
		return [
			'scanError'          => __( 'An error occurred during scanning. Please try again.', 'hcaptcha-for-forms-and-more' ),
			'applyError'         => __( 'An error occurred while applying migration. Please try again.', 'hcaptcha-for-forms-and-more' ),
			'noSurfacesSelected' => __( 'Please select at least one surface to migrate.', 'hcaptcha-for-forms-and-more' ),
			'okBtnText'          => __( 'OK', 'hcaptcha-for-forms-and-more' ),
			'foundSurfaces'      => [
				/* translators: %d: number of detected surfaces. */
				__( 'We found existing CAPTCHA protection on %d surface.', 'hcaptcha-for-forms-and-more' ),
				/* translators: %d: number of detected surfaces. */
				__( 'We found existing CAPTCHA protection on %d surfaces.', 'hcaptcha-for-forms-and-more' ),
			],
			'migratableCount'    => [
				/* translators: %d: number of migratable surfaces. */
				__( '%d surface can be migrated to hCaptcha.', 'hcaptcha-for-forms-and-more' ),
				/* translators: %d: number of migratable surfaces. */
				__( '%d surfaces can be migrated to hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			],
			'alreadyEnabled'     => __( 'Already enabled in hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			'providerRecaptcha'  => __( 'reCAPTCHA', 'hcaptcha-for-forms-and-more' ),
			'providerTurnstile'  => __( 'Turnstile', 'hcaptcha-for-forms-and-more' ),
			'confidenceHigh'     => __( 'High', 'hcaptcha-for-forms-and-more' ),
			'confidenceMedium'   => __( 'Medium', 'hcaptcha-for-forms-and-more' ),
			'confidenceLow'      => __( 'Low', 'hcaptcha-for-forms-and-more' ),
			'enabledSuccess'     => __( 'Enabled successfully', 'hcaptcha-for-forms-and-more' ),
			'enabledFailed'      => __( 'Failed to enable', 'hcaptcha-for-forms-and-more' ),
		];
	}
}
