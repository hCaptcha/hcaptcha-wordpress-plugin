<?php
/**
 * List the live plugins and themes needed by one integration shard.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI diagnostics.

require_once __DIR__ . '/run-parallel.php';

// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh -- Class metadata and dependency validation.
/**
 * Collect the external dependencies declared by integration test classes.
 *
 * @param array<int,array{name: string, files: string[], weight: int}> $units Shard units.
 *
 * @return array<string,string[]>
 * @throws RuntimeException If a test dependency is unknown.
 */
function hcaptcha_ci_shard_dependencies( array $units ): array {
	$public_plugins  = array_fill_keys(
		explode( ' ', 'acf-extended bbpress blocksy-companion buddypress coblocks contact-form-7 download-manager elementor essential-addons-for-elementor-lite essential-blocks events-manager fluentform formidable forminator give jetpack kadence-blocks learnpress mailchimp-for-wp mailin mailpoet maintenance metform ninja-forms otter-blocks password-protected theme-my-login tutor ultimate-addons-for-gutenberg ultimate-member woocommerce wordfence wpforms-lite wpforo' ),
		true
	);
	$private_plugins = array_fill_keys(
		[ 'elementor-pro', 'advanced-custom-fields-pro', 'gravityforms', 'ultimate-elementor', 'fusion-builder', 'bb-plugin' ],
		true
	);
	$dependencies    = [
		'public_plugins'   => [],
		'private_plugins'  => [],
		'public_themes'    => [],
		'private_themes'   => [],
		'optional_plugins' => [],
	];
	$suite_dir       = hcaptcha_normalize_parallel_path( __DIR__ . '/integration' ) . '/';

	foreach ( $units as $unit ) {
		foreach ( $unit['files'] as $file ) {
			$relative = substr( $file, strlen( $suite_dir ), -4 );
			$class    = 'HCaptcha\\Tests\\Integration\\' . str_replace( '/', '\\', $relative );

			if ( ! class_exists( $class ) ) {
				throw new RuntimeException( "Cannot load integration test class: $class" );
			}

			$reflection = new ReflectionClass( $class );

			if ( $reflection->hasProperty( 'plugin' ) ) {
				$property = $reflection->getProperty( 'plugin' );
				$property->setAccessible( true );

				foreach ( (array) $property->getValue() as $plugin ) {
					$slug = strtok( $plugin, '/' );

					if ( isset( $public_plugins[ $slug ] ) ) {
						$dependencies['public_plugins'][ $slug ] = $slug;
					} elseif ( isset( $private_plugins[ $slug ] ) ) {
						$dependencies['private_plugins'][ $slug ] = $slug;
					} elseif ( 'really-simple-captcha' === $slug || 'perfmatters' === $slug ) {
						// These are installed conditionally or skipped by their tests.
						$dependencies['optional_plugins'][ $slug ] = $slug;
					} else {
						throw new RuntimeException( "Unknown integration test plugin: $plugin" );
					}
				}
			}

			if ( $reflection->hasProperty( 'theme' ) ) {
				$property = $reflection->getProperty( 'theme' );
				$property->setAccessible( true );
				$theme = $property->getValue();

				if ( 'blocksy' === $theme ) {
					$dependencies['public_themes'][ $theme ] = $theme;
				} elseif ( 'Avada' === $theme || 'Divi' === $theme ) {
					$dependencies['private_themes'][ $theme ] = $theme;
				} elseif ( '' !== $theme ) {
					throw new RuntimeException( "Unknown integration test theme: $theme" );
				}
			}
		}
	}

	foreach ( $dependencies as &$names ) {
		$names = array_values( $names );
		sort( $names, SORT_STRING );
	}
	unset( $names );

	return $dependencies;
}
// phpcs:enable Generic.Metrics.CyclomaticComplexity.TooHigh

if ( isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$shard = $argv[1] ?? '';

	if ( ! preg_match( '/^([1-9][0-9]*)\/([1-9][0-9]*)$/', $shard, $matches ) || (int) $matches[1] > (int) $matches[2] ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI error output.
		fwrite( STDERR, "Usage: php tests/php/ci-shard-dependencies.php N/M\n" );
		exit( 1 );
	}

	$suite_dir = __DIR__ . '/integration';
	$files     = hcaptcha_find_test_files( $suite_dir );
	$units     = hcaptcha_create_parallel_units( $files, hcaptcha_normalize_parallel_path( $suite_dir ) );
	$shards    = hcaptcha_partition_parallel_units( $units, (int) $matches[2] );
	$result    = hcaptcha_ci_shard_dependencies( $shards[ (int) $matches[1] - 1 ] );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress is not loaded by this CLI script.
	echo json_encode( $result, JSON_THROW_ON_ERROR ) . "\n";
}
