<?php
/**
 * Migrations class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Migrations;

/**
 * Migrations class.
 */
class Migrations {

	/**
	 * Migrated versions options name.
	 */
	const MIGRATED_VERSIONS_OPTION_NAME = 'hcaptcha_versions';

	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = HCAPTCHA_VERSION;

	/**
	 * Migration started status.
	 */
	const STARTED = - 1;

	/**
	 * Migration failed status.
	 */
	const FAILED = - 2;

	/**
	 * Plugin name.
	 */
	const PLUGIN_NAME = 'hCaptcha Plugin';

	/**
	 * Migration constructor.
	 */
	public function __construct() {
		if ( ! $this->is_allowed() ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'migrate' ] );
	}

	/**
	 * Migrate.
	 *
	 * @return void
	 */
	public function migrate() {
		$migrated = get_option( self::MIGRATED_VERSIONS_OPTION_NAME, [] );

		$migrations = array_filter(
			get_class_methods( $this ),
			static function ( $migration ) {
				return false !== strpos( $migration, 'migrate_' );
			}
		);

		foreach ( $migrations as $migration ) {
			$upgrade_version = $this->get_upgrade_version( $migration );

			if (
				( isset( $migrated[ $upgrade_version ] ) && $migrated[ $upgrade_version ] >= 0 ) ||
				version_compare( $upgrade_version, self::PLUGIN_VERSION, '>' )
			) {
				continue;
			}

			if ( ! isset( $migrated[ $upgrade_version ] ) ) {
				$migrated[ $upgrade_version ] = static::STARTED;

				$this->log( sprintf( 'Migration of %1$s to %2$s started.', self::PLUGIN_NAME, $upgrade_version ) );
			}

			// Run migration.
			$result = $this->{$migration}();

			// Some migration methods can be called several times to support AS action,
			// so do not log their completion here.
			if ( null === $result ) {
				continue;
			}

			$migrated[ $upgrade_version ] = $result ? time() : static::FAILED;

			$message = $result ?
				sprintf( 'Migration of %1$s to %2$s completed.', self::PLUGIN_NAME, $upgrade_version ) :
				sprintf( 'Migration of %1$s to %2$s failed.', self::PLUGIN_NAME, $upgrade_version );

			$this->log( $message );
		}

		update_option( self::MIGRATED_VERSIONS_OPTION_NAME, $migrated );
	}

	/**
	 * Determine if migration is allowed.
	 */
	private function is_allowed() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['service-worker'] ) ) {
			return false;
		}

		return ( defined( 'DOING_CRON' ) && DOING_CRON ) || is_admin();
	}

	/**
	 * Get upgrade version from the method name.
	 *
	 * @param string $method Method name.
	 *
	 * @return string
	 */
	private function get_upgrade_version( $method ) {
		// Find only the digits to get version number.
		if ( ! preg_match( '/\d+/', $method, $matches ) ) {
			return '';
		}

		return implode( '.', str_split( $matches[0] ) );
	}

	/**
	 * Output message into log file.
	 *
	 * @param string $message Message to log.
	 * @param mixed  $item    Item.
	 *
	 * @return void
	 * @noinspection ForgottenDebugOutputInspection
	 */
	private function log( $message, $item = null ) {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		if ( null !== $item ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$message .= ' ' . print_r( $item, true );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::PLUGIN_NAME . ':  ' . $message );
	}

	/**
	 * Migrate to 2.0.0
	 *
	 * @return bool|null
	 * @noinspection PhpUnusedPrivateMethodInspection
	 */
	private function migrate_200() {

		return true;
	}
}
