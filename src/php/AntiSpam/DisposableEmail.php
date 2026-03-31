<?php
/**
 * DisposableEmail class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\AntiSpam;

/**
 * Class DisposableEmail.
 */
class DisposableEmail {

	/**
	 * Transient key for cached blocklist.
	 */
	private const TRANSIENT_KEY = 'hcaptcha_disposable_email_blocklist';

	/**
	 * Transient TTL in seconds (24 hours).
	 */
	private const TRANSIENT_TTL = 86400;

	/**
	 * Blocklist file name.
	 */
	private const BLOCKLIST_FILE = 'disposable-email-blocklist.conf';

	/**
	 * Blocklist subdirectory in uploads.
	 */
	private const UPLOAD_SUB_DIR = 'hcaptcha';

	/**
	 * Remote blocklist URL.
	 */
	private const BLOCKLIST_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/refs/heads/main/disposable_email_blocklist.conf';

	/**
	 * Action Scheduler hook name.
	 */
	public const UPDATE_ACTION = 'hcap_update_disposable_email_blocklist';

	/**
	 * Admin notice transient key.
	 */
	private const NOTICE_TRANSIENT = 'hcaptcha_disposable_email_download_failed';

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
		add_action( 'admin_notices', [ self::class, 'show_download_failed_notice' ] );
		add_action( self::UPDATE_ACTION, [ $this, 'update_blocklist' ] );
	}

	/**
	 * Verify that email is NOT disposable.
	 *
	 * @param array $entry Entry data.
	 *
	 * @return string|null
	 */
	public function verify( array $entry ): ?string {
		$email = $entry['data']['email'] ?? '';

		if ( ! is_email( $email ) ) {
			return true;
		}

		/**
		 * Filters the disposable email status.
		 *
		 * @param bool $is_disposable Whether the email is disposable.
		 * @param string $email   Email address.
		 */
		$disposable = (bool) apply_filters( 'hcap_is_disposable_email', $this->is_disposable_email( $email ), $email );

		return ! $disposable;
	}

	/**
	 * Get the blocklist as an associative array for O(1) lookups.
	 *
	 * @return array
	 */
	public function get_blocklist(): array {
		$domains = get_transient( self::TRANSIENT_KEY );

		if ( false === $domains ) {
			$contents = (string) $this->read_file( self::get_blocklist_path() );
			$lines    = array_filter( array_map( 'trim', explode( "\n", $contents ) ) );
			$domains  = array_fill_keys( $lines, true );

			set_transient( self::TRANSIENT_KEY, $domains, self::TRANSIENT_TTL );
		}

		/**
		 * Filters the disposable email domains blocklist.
		 *
		 * @param array $domains Associative array of blocked domains.
		 */
		return (array) apply_filters( 'hcap_disposable_email_domains', $domains );
	}

	/**
	 * Get the full path to the blocklist file in uploads.
	 *
	 * @return string
	 */
	public static function get_blocklist_path(): string {
		$upload_dir = wp_upload_dir();

		return trailingslashit( $upload_dir['basedir'] ) . self::UPLOAD_SUB_DIR . '/' . self::BLOCKLIST_FILE;
	}

	/**
	 * Read file contents using WP_Filesystem.
	 *
	 * @param string $file File path.
	 *
	 * @return string|false
	 */
	protected function read_file( string $file ) {
		global $wp_filesystem;

		if ( ! self::init_filesystem() ) {
			return false;
		}

		if ( ! $wp_filesystem->exists( $file ) ) {
			return false;
		}

		return $wp_filesystem->get_contents( $file );
	}

	/**
	 * Check if an email address uses a disposable domain.
	 *
	 * @param string $email Email address to check.
	 *
	 * @return bool
	 */
	public function is_disposable_email( string $email ): bool {
		if ( false === strpos( $email, '@' ) ) {
			return false;
		}

		$parts = explode( '@', trim( $email ) );

		if ( count( $parts ) < 2 || '' === $parts[1] ) {
			return false;
		}

		$to_lower     = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		$domain       = $to_lower( $parts[1] );
		$domain_parts = explode( '.', $domain );
		$blocklist    = $this->get_blocklist();
		$count        = count( $domain_parts );

		for ( $i = 0; $i < $count - 1; $i++ ) {
			$check = implode( '.', array_slice( $domain_parts, $i ) );

			if ( isset( $blocklist[ $check ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Download the blocklist file and save to uploads.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function download_blocklist(): bool {
		global $wp_filesystem;

		if ( ! self::init_filesystem() ) {
			return false;
		}

		$response = wp_remote_get( self::BLOCKLIST_URL );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return false;
		}

		$path = self::get_blocklist_path();
		$dir  = dirname( $path );

		if ( ! $wp_filesystem->is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		return (bool) $wp_filesystem->put_contents( $path, $body, FS_CHMOD_FILE );
	}

	/**
	 * Handle toggle activation: download the blocklist if needed, schedule updates.
	 *
	 * @return void
	 */
	public function activate(): void {
		$path = self::get_blocklist_path();

		if ( ! file_exists( $path ) && ! $this->download_blocklist() ) {
			set_transient( self::NOTICE_TRANSIENT, true, 60 );
		}

		$this->schedule_update();
	}

	/**
	 * Handle toggle deactivation: unschedule updates.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		$this->unschedule_update();
	}

	/**
	 * Action Scheduler callback: update the blocklist file.
	 *
	 * @return void
	 */
	public function update_blocklist(): void {
		if ( $this->download_blocklist() ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Schedule the recurring blocklist update via Action Scheduler.
	 *
	 * @return void
	 */
	protected function schedule_update(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		as_schedule_recurring_action(
			time() + WEEK_IN_SECONDS,
			WEEK_IN_SECONDS,
			self::UPDATE_ACTION,
			[],
			'hcaptcha',
			true
		);
	}

	/**
	 * Unschedule the recurring blocklist update.
	 *
	 * @return void
	 */
	protected function unschedule_update(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::UPDATE_ACTION, [], 'hcaptcha' );
	}

	/**
	 * Show an admin notice if blocklist download failed.
	 *
	 * @return void
	 */
	public static function show_download_failed_notice(): void {
		if ( ! get_transient( self::NOTICE_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		$message = __(
			'Disposable email blocklist could not be downloaded. The feature will activate once the list is available.',
			'hcaptcha-for-forms-and-more'
		);

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Init WP filesystem.
	 *
	 * @return bool
	 */
	protected static function init_filesystem(): bool {
		global $wp_filesystem;

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return false;
		}

		return (bool) $wp_filesystem;
	}
}
