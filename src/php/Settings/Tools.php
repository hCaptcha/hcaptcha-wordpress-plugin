<?php
/**
 * Tools class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Helpers\Request;

/**
 * Class Tools
 *
 * Settings page "Tools".
 */
class Tools extends PluginSettingsBase {

	/**
	 * Admin script and style handle.
	 */
	public const HANDLE = 'hcaptcha-tools';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaToolsObject';

	/**
	 * Export settings ajax action.
	 */
	public const EXPORT_ACTION = 'hcaptcha-export';

	/**
	 * Import settings ajax action.
	 */
	public const IMPORT_ACTION = 'hcaptcha-import';

	/**
	 * User settings meta key.
	 */
	public const USER_SETTINGS_META = 'hcaptcha_user_settings';

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Tools', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	protected function menu_title(): string {
		return __( 'Tools', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'tools';
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_ajax_' . self::EXPORT_ACTION, [ $this, 'ajax_handle_export' ] );
		add_action( 'wp_ajax_' . self::IMPORT_ACTION, [ $this, 'ajax_handle_import' ] );
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$min = defined( 'SCRIPT_DEBUG' ) && constant( 'SCRIPT_DEBUG' ) ? '' : '.min';

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/tools$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/tools$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'exportAction'   => self::EXPORT_ACTION,
				'exportNonce'    => wp_create_nonce( self::EXPORT_ACTION ),
				'importAction'   => self::IMPORT_ACTION,
				'importNonce'    => wp_create_nonce( self::IMPORT_ACTION ),
				'exportFailed'   => __( 'Export failed.', 'hcaptcha-for-forms-and-more' ),
				'importFailed'   => __( 'Import failed.', 'hcaptcha-for-forms-and-more' ),
				'selectJsonFile' => __( 'Please select a JSON file.', 'hcaptcha-for-forms-and-more' ),
			]
		);
	}

	/**
	 * Handle Export.
	 *
	 * @return void
	 */
	public function ajax_handle_export(): void {
		$this->run_checks( self::EXPORT_ACTION );

		$include_keys = 'on' === Request::filter_input( INPUT_POST, 'include_keys' );

		$transfer = new SettingsTransfer();
		$data     = $transfer->build_export_payload( $include_keys );

		wp_send_json( $data );
	}

	/**
	 * Handle Import.
	 *
	 * @return void
	 */
	public function ajax_handle_import(): void {
		$this->run_checks( self::IMPORT_ACTION );

		// Nonce is checked in run_checks().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$filename = isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['import_file']['tmp_name'] ) : '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $filename );
		$data = json_decode( $json, true );

		// Admin UI import keeps keys if present.
		$transfer = new SettingsTransfer();
		$result   = $transfer->apply_import_payload( $data, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$message = null === $result
				? __( 'hCaptcha settings were successfully imported.', 'hcaptcha-for-forms-and-more' )
				: $result;

		wp_send_json_success( [ 'message' => $message ] );
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 *
	 * @return void
	 */
	public function section_callback( array $arguments ): void {
		$this->print_header();

		?>
		<div id="hcaptcha-message"></div>
		<p>
			<?php esc_html_e( 'Manage the export and import of hCaptcha plugin settings.', 'hcaptcha-for-forms-and-more' ); ?>
		</p>
		<?php

		$this->print_section_header( 'export', __( 'Export', 'hcaptcha-for-forms-and-more' ) );

		?>
		<div id="hcaptcha-section-export">
			<p><?php esc_html_e( 'Export your hCaptcha settings to a JSON file.', 'hcaptcha-for-forms-and-more' ); ?></p>
			<fieldset>
				<label for="include_keys">
					<input id="include_keys" name="include_keys" type="checkbox" value="on">
					<?php esc_html_e( 'Include site and secret keys', 'hcaptcha-for-forms-and-more' ); ?>
				</label>
			</fieldset>
			<p class="import-notice">
				<span>
					<?php esc_html_e( 'Including keys will export sensitive data. Only use this file in a trusted environment.', 'hcaptcha-for-forms-and-more' ); ?>
				</span>
			</p>
			<p>
				<input
						type="submit" name="hcaptcha_export" id="hcaptcha-export-btn" class="button button-primary"
						value="<?php esc_attr_e( 'Export', 'hcaptcha-for-forms-and-more' ); ?>">
			</p>
		</div>

		<?php $this->print_section_header( 'import', __( 'Import', 'hcaptcha-for-forms-and-more' ) ); ?>

		<div class="hcaptcha-section-import">
			<p><?php esc_html_e( 'Import your hCaptcha settings from a JSON file. This will replace your current settings.', 'hcaptcha-for-forms-and-more' ); ?></p>
			<div class="hcaptcha-file-upload">
				<label for="hcaptcha-import-file" class="button button-secondary">
					<?php esc_html_e( 'Select JSON file', 'hcaptcha-for-forms-and-more' ); ?>
				</label>
				<span class="hcaptcha-file-name" data-empty="<?php esc_attr_e( 'No file selected', 'hcaptcha-for-forms-and-more' ); ?>">
					<?php esc_html_e( 'No file selected', 'hcaptcha-for-forms-and-more' ); ?>
				</span>
				<input type="file" id="hcaptcha-import-file" name="import_file" accept=".json,application/json"/>
			</div>
			<p>
				<input
						type="submit" name="hcaptcha_import" id="hcaptcha-import-btn" class="button button-primary"
						value="<?php esc_attr_e( 'Import', 'hcaptcha-for-forms-and-more' ); ?>">
			</p>
		</div>
		<?php
	}

	/**
	 * Print section header.
	 *
	 * @param string $id    Section id.
	 * @param string $title Section title.
	 *
	 * @return void
	 */
	private function print_section_header( string $id, string $title ): void {
		$user                   = wp_get_current_user();
		$hcaptcha_user_settings = [];

		if ( $user ) {
			$hcaptcha_user_settings = (array) get_user_meta( $user->ID, self::USER_SETTINGS_META, true );
		}

		$open  = $hcaptcha_user_settings['sections'][ $id ] ?? true;
		$class = $open ? '' : ' closed';

		?>
		<h3 class="hcaptcha-section-<?php echo esc_attr( $id ); ?><?php echo esc_attr( $class ); ?>">
			<span class="hcaptcha-section-header-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<span class="hcaptcha-section-header-toggle"></span>
		</h3>
		<?php
	}
}
