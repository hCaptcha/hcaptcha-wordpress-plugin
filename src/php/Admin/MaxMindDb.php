<?php
/**
 * MaxMindDb class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use Exception;
use PharData;

/**
 * Class MaxMindDb.
 */
class MaxMindDb {
	/**
	 * MaxMind download API URL.
	 */
	private const DOWNLOAD_API = 'https://download.maxmind.com/app/geoip_download';

	/**
	 * MaxMind database name.
	 */
	private const DATABASE = 'GeoLite2-Country';

	/**
	 * MaxMind database extension.
	 */
	public const DATABASE_EXTENSION = '.mmdb';

	/**
	 * MaxMind DB file name.
	 */
	private const DB_FILE = self::DATABASE . self::DATABASE_EXTENSION;

	/**
	 * MaxMind response archive suffix.
	 */
	private const ARCHIVE_SUFFIX = 'tar.gz';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	private function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'hcap_load_maxmind_db', [ $this, 'load_db' ] );
		add_action( 'hcap_update_maxmind_db', [ $this, 'update_db' ] );
	}

	/**
	 * Load MaxMind DB if needed.
	 *
	 * @param string $key Maxmind key.
	 *
	 * @return void
	 */
	public function load_db( string $key = '' ): void {
		if ( ! $key ) {
			return;
		}

		if ( $this->has_maxmind_db() ) {
			return;
		}

		$this->download_db( $key );
	}

	/**
	 * Update MaxMind DB.
	 *
	 * @return void
	 */
	public function update_db(): void {
		$settings = hcaptcha()->settings();
		$key      = $settings ? $settings->get( 'maxmind_key' ) : '';

		if ( ! $key ) {
			return;
		}

		$this->download_db( $key );
	}

	/**
	 * Check whether MaxMind DB exists.
	 *
	 * @return bool
	 */
	private function has_maxmind_db(): bool {
		return '' !== $this->get_existing_db_path();
	}

	/**
	 * Get an existing MaxMind DB path.
	 *
	 * @return string
	 */
	private function get_existing_db_path(): string {
		/**
		 * Filters MaxMind country DB path.
		 *
		 * @param string $path Path to *.mmdb file.
		 */
		$path = (string) apply_filters( 'hcap_maxmind_db_path', '' );

		if ( $path && is_readable( $path ) ) {
			return $path;
		}

		foreach ( $this->get_default_db_paths() as $default_path ) {
			if ( is_readable( $default_path ) ) {
				return $default_path;
			}
		}

		return '';
	}

	/**
	 * Get default MaxMind DB paths.
	 *
	 * @return string[]
	 */
	private function get_default_db_paths(): array {
		return [
			WP_CONTENT_DIR . '/uploads/hcaptcha/' . self::DB_FILE,
		];
	}

	/**
	 * Get the target MaxMind DB path for download.
	 *
	 * @return string
	 */
	private function get_target_db_path(): string {
		/**
		 * Filters MaxMind country DB path.
		 *
		 * @param string $path Path to *.mmdb file.
		 */
		$path = (string) apply_filters( 'hcap_maxmind_db_path', '' );

		if ( $path ) {
			return $path;
		}

		return $this->get_default_db_paths()[0];
	}

	/**
	 * Download MaxMind DB and place it into the target path.
	 *
	 * @param string $license_key MaxMind license key.
	 *
	 * @return void
	 */
	private function download_db( string $license_key ): void {
		$download_uri = add_query_arg(
			array(
				'edition_id'  => self::DATABASE,
				'license_key' => rawurlencode( sanitize_text_field( $license_key ) ),
				'suffix'      => self::ARCHIVE_SUFFIX,
			),
			self::DOWNLOAD_API
		);

		// Needed for the download_url call right below.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_archive_path = download_url( esc_url_raw( $download_uri ) );

		if ( is_wp_error( $tmp_archive_path ) ) {
			return;
		}

		$this->extract_db_from_archive( $tmp_archive_path, $this->get_target_db_path() );
	}

	/**
	 * Extract MaxMind DB from the archive.
	 *
	 * @param string $tmp_archive_path Archive path.
	 * @param string $target_path      Target DB path.
	 *
	 * @return void
	 */
	private function extract_db_from_archive( string $tmp_archive_path, string $target_path ): void {
		global $wp_filesystem;

		if ( ! $this->init_filesystem() ) {
			return;
		}

		// Extract the database from the archive.
		try {
			$file = new PharData( $tmp_archive_path );

			$tmp_db_filename = trailingslashit( $file->current()->getFilename() ) . self::DATABASE . self::DATABASE_EXTENSION;
			$tmp_db_path     = trailingslashit( dirname( $tmp_archive_path ) ) . $tmp_db_filename;

			$file->extractTo( dirname( $tmp_archive_path ), $tmp_db_filename, true );

			if ( ! is_file( $tmp_db_path ) ) {
				return;
			}

			if ( ! $this->mkdir_p( dirname( $target_path ) ) ) {
				return;
			}

			$wp_filesystem->move( $tmp_db_path, $target_path, true );
			$wp_filesystem->rmdir( dirname( $tmp_db_path ), true );
		} catch ( Exception $exception ) {
			return;
		} finally {
			// Remove the archive since we only care about a single file in it.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $tmp_archive_path );
		}
	}

	/**
	 * Recursively create a directory path using WP_Filesystem.
	 *
	 * @param string $target_dir Target directory path.
	 *
	 * @return bool
	 */
	private function mkdir_p( string $target_dir ): bool {
		global $wp_filesystem;

		$target_dir = untrailingslashit( wp_normalize_path( $target_dir ) );

		if ( '' === $target_dir || $wp_filesystem->is_dir( $target_dir ) ) {
			return true;
		}

		$segments = explode( '/', $target_dir );
		$current  = '';

		if ( preg_match( '/^[A-Za-z]:$/', $segments[0] ?? '' ) ) {
			$current = array_shift( $segments ) . '/';
		} elseif ( '' === ( $segments[0] ?? '' ) ) {
			$current = '/';

			array_shift( $segments );
		}

		foreach ( $segments as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			$current = rtrim( $current, '/' ) . '/' . $segment;

			if ( $wp_filesystem->is_dir( $current ) ) {
				continue;
			}

			if ( ! $wp_filesystem->mkdir( $current, FS_CHMOD_DIR ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Init WP filesystem.
	 *
	 * @return bool
	 */
	private function init_filesystem(): bool {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return false;
		}

		return (bool) $wp_filesystem;
	}
}
