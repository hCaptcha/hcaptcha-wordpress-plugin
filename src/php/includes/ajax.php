<?php
/**
 * Ajax action with SHORTINIT.
 *
 * @package hcaptcha-wp
 */

// We cannot use DOCUMENT_ROOT because it does not set in many cases.
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
$abs_path  = $_POST['absPath'] ?? '';
$ajax_path = $_POST['ajaxPath'] ?? '';
// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

// Security check.
if (
	0 !== strpos( $ajax_path, $abs_path ) ||
	kagg_normalize_path( realpath( __FILE__ ) ) !== kagg_normalize_path( realpath( $ajax_path ) )
) {
	die();
}

// Load WordPress core.
kagg_load_wp_core( $abs_path );

// Load this plugin.
$plugin_root = dirname( __DIR__, 3 );

require $plugin_root . '/hcaptcha.php';

$fq_action   = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
$ajax_action = 'wp_ajax_nopriv_' . $fq_action;

if ( has_action( $ajax_action ) ) {
	do_action( $ajax_action );
} else {
	wp_send_json_error( [ 'message' => __( 'No such action.' ) ] );
}

/**
 * Load WordPress core.
 *
 * @param string $abs_path Absolute path to WordPress.
 *
 * @return void
 */
function kagg_load_wp_core( string $abs_path ) {
	/**
	 * Load WordPress core with SHORTINIT.
	 *
	 * @package hcaptcha-wp
	 */

	/**
	 * Set short WordPress init.
	 */
	define( 'SHORTINIT', true );

	require $abs_path . '/wp-load.php';

	// Components needed for i18n to work properly.
	require ABSPATH . WPINC . '/l10n.php';
	require ABSPATH . WPINC . '/class-wp-textdomain-registry.php';
	require ABSPATH . WPINC . '/class-wp-locale.php';

	// Components needed for check_ajax_referer() to work.
	require ABSPATH . WPINC . '/capabilities.php';
	require ABSPATH . WPINC . '/class-wp-roles.php';
	require ABSPATH . WPINC . '/class-wp-role.php';
	require ABSPATH . WPINC . '/class-wp-user.php';
	require ABSPATH . WPINC . '/user.php';
	require ABSPATH . WPINC . '/class-wp-session-tokens.php';
	require ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php';
	require ABSPATH . WPINC . '/theme.php';
	require ABSPATH . WPINC . '/general-template.php';
	require ABSPATH . WPINC . '/link-template.php';
	require ABSPATH . WPINC . '/kses.php';
	require ABSPATH . WPINC . '/shortcodes.php';
	require ABSPATH . WPINC . '/http.php';
	require ABSPATH . WPINC . '/class-wp-http.php';
	require ABSPATH . WPINC . '/class-wp-http-streams.php';
	require ABSPATH . WPINC . '/class-wp-http-curl.php';
	require ABSPATH . WPINC . '/class-wp-http-proxy.php';
	require ABSPATH . WPINC . '/class-wp-http-response.php';
	require ABSPATH . WPINC . '/class-wp-http-requests-response.php';
	require ABSPATH . WPINC . '/class-wp-http-requests-hooks.php';
	require ABSPATH . WPINC . '/rest-api.php';
	require ABSPATH . WPINC . '/class-wp-block-parser.php';
	require ABSPATH . WPINC . '/blocks.php';

	// Define constants that rely on the API to get the default value.
	// Define must-use plugin directory constants, which may be overridden in the sunrise.php drop-in.
	wp_plugin_directory_constants();
	wp_cookie_constants();

	// Create common globals.
	require ABSPATH . WPINC . '/vars.php';

	// Load pluggable functions.
	require ABSPATH . WPINC . '/pluggable.php';
	require ABSPATH . 'wp-admin/includes/file.php';

	/**
	 * WordPress Textdomain Registry object.
	 * Used to support just-in-time translations for manually loaded text domains.
	 *
	 * @global WP_Textdomain_Registry $wp_textdomain_registry WordPress Textdomain Registry.
	 */
	$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
	$GLOBALS['wp_textdomain_registry']->init();

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$GLOBALS['wp_plugin_paths'] = [];

	// WP Core constants for SHORTINIT mode.
	if ( ! defined( 'FS_CHMOD_DIR' ) ) {
		define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
	}

	if ( ! defined( 'FS_CHMOD_FILE' ) ) {
		define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
	}
}

/**
 * Normalizes a given file path by standardizing slashes, reducing multiple slashes,
 * and ensuring drive letters are uppercase on Windows.
 *
 * @param string $path The file path to be normalized.
 *
 * @return string The normalized file path.
 */
function kagg_normalize_path( string $path ): string {
	$wrapper = '';

	// Standardize all paths to use '/'.
	$path = str_replace( '\\', '/', $path );

	// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
	$path = preg_replace( '|(?<=.)/+|', '/', $path );

	// Windows paths should uppercase the drive letter.
	if ( ':' === $path[1] ) {
		$path = ucfirst( $path );
	}

	return $wrapper . $path;
}
