<?php
/**
 * WP-CLI commands for hCaptcha settings import/export.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\CLI;

use HCaptcha\Settings\SettingsTransfer;
use WP_CLI;
use function WP_CLI\Utils\get_flag_value;

/**
 * Register subcommands under `wp hcaptcha`.
 */
class Commands {
	/**
	 * Export hCaptcha settings as JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--include-keys]
	 * : Include site and secret keys.
	 *
	 * [--pretty]
	 * : Pretty-print JSON.
	 *
	 * [--file=<path>]
	 * : Write JSON to a file instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hcaptcha export --pretty > hcaptcha-settings.json
	 *     wp hcaptcha export --include-keys --file=./hcaptcha-settings.json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function export( array $args, array $assoc_args ): void {
		$include_keys = (bool) get_flag_value( $assoc_args, 'include-keys', false );
		$pretty       = (bool) get_flag_value( $assoc_args, 'pretty', false );
		$file         = $assoc_args['file'] ?? '';

		$transfer = new SettingsTransfer();
		$payload  = $transfer->build_export_payload( $include_keys );

		$json_opts = JSON_UNESCAPED_SLASHES | ( $pretty ? JSON_PRETTY_PRINT : 0 );
		$json      = wp_json_encode( $payload, $json_opts );

		if ( ! $json ) {
			WP_CLI::error( 'Failed to encode JSON.' );
		}

		if ( $file ) {
			$dir = dirname( $file );
			if ( ! is_dir( $dir ) ) {
				// Try to create the directory tree.
				if ( ! @wp_mkdir_p( $dir ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					WP_CLI::error( sprintf( 'Cannot create directory: %s', $dir ) );
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			$result = @file_put_contents( $file, $json . "\n" );

			if ( false === $result ) {
				WP_CLI::error( sprintf( 'Cannot write file: %s', $file ) );
			}

			WP_CLI::success( sprintf( 'Exported settings to %s', $file ) );

			return;
		}

		// Print JSON to STDOUT without extra noise.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json . "\n";
	}

	/**
	 * Import hCaptcha settings from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to JSON file.
	 *
	 * [--dry-run]
	 * : Validate and report without writing settings.
	 *
	 * [--allow-keys]
	 * : Allow importing keys block if present.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hcaptcha import ./hcaptcha-settings.json
	 *     wp hcaptcha import ./hcaptcha-settings.json --dry-run
	 *     wp hcaptcha import ./hcaptcha-settings.json --allow-keys
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public function import( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Missing <file> argument.' );
		}

		$file       = $args[0];
		$dry_run    = (bool) get_flag_value( $assoc_args, 'dry-run', false );
		$allow_keys = (bool) get_flag_value( $assoc_args, 'allow-keys', false );

		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'File not found or unreadable: %s', $file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::error( 'Invalid JSON.' );
		}

		$this->apply_import_payload( $data, $allow_keys, $dry_run );
	}

	/**
	 * Apply import payload.
	 *
	 * @param array $data       Payload data.
	 * @param bool  $allow_keys Allow importing keys block if present.
	 * @param bool  $dry_run    Dry run.
	 *
	 * @return void
	 */
	private function apply_import_payload( array $data, bool $allow_keys, bool $dry_run ): void {
		$transfer = new SettingsTransfer();

		if ( $dry_run ) {
			$result = $transfer->validate_import_payload( $data );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			$has_keys       = isset( $data['keys'] );
			$settings_count = is_array( $data['settings'] ?? null ) ? count( (array) $data['settings'] ) : 0;

			WP_CLI::success(
				sprintf(
					'Dry run completed. Detected %d settings fields. Keys present: %s. Keys would be applied: %s.',
					$settings_count,
					$has_keys ? 'yes' : 'no',
					( $has_keys && $allow_keys ) ? 'yes' : 'no'
				)
			);

			return;
		}

		$result = $transfer->apply_import_payload( $data, $allow_keys, $dry_run );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( isset( $data['keys'] ) && ! $allow_keys ) {
			WP_CLI::warning( 'Keys present in JSON were skipped. Use --allow-keys to import.' );
		}

		WP_CLI::success( __( 'hCaptcha settings were successfully imported.', 'hcaptcha-for-forms-and-more' ) );
	}
}
