<?php
/**
 * MigrationsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Migrations;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test MigrationsTest class.
 *
 * @group migrations
 */
class MigrationsTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_GET['service-worker'], $GLOBALS['current_screen'] );

		parent::tearDown();
	}
	/**
	 * Test init() and init_hooks().
	 *
	 * @param bool     $worker   The service-worker is set.
	 * @param bool     $admin    In admin.
	 * @param bool|int $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_init_and_init_hooks
	 */
	public function test_init_and_init_hooks( bool $worker, bool $admin, $expected ) {
		if ( $worker ) {
			$_GET['service-worker'] = 'some';
		}

		if ( $admin ) {
			set_current_screen( 'some-screen' );
		}

		$subject = new Migrations();

		self::assertSame( $expected, has_action( 'plugins_loaded', [ $subject, 'migrate' ] ) );
	}

	/**
	 * Data provider for test_init_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_and_init_hooks(): array {
		return [
			[ false, false, false ],
			[ true, false, false ],
			[ false, true, - PHP_INT_MAX ],
			[ true, true, false ],
		];
	}

	/**
	 * Test migrate().
	 *
	 * @return void
	 */
	public function test_migrate() {
		FunctionMocker::replace( 'time', time() );

		$time              = time();
		$size              = 'normal';
		$expected_option   = [
			'2.0.0'          => $time,
			'3.6.0'          => $time,
			'4.0.0'          => $time,
			HCAPTCHA_VERSION => $time,
		];
		$expected_settings = [
			'site_key'                     => '',
			'secret_key'                   => '',
			'theme'                        => '',
			'size'                         => $size,
			'language'                     => '',
			'off_when_logged_in'           => [],
			'recaptcha_compat_off'         => [],
			'wp_status'                    => [],
			'bbp_status'                   => [],
			'bp_status'                    => [],
			'cf7_status'                   => [],
			'divi_status'                  => [],
			'elementor_pro_status'         => [],
			'fluent_status'                => [],
			'gravity_status'               => [],
			'jetpack_status'               => [],
			'mailchimp_status'             => [],
			'memberpress_status'           => [],
			'ninja_status'                 => [],
			'subscriber_status'            => [],
			'ultimate_member_status'       => [],
			'woocommerce_status'           => [],
			'woocommerce_wishlists_status' => [],
			'wpforms_status'               => [ 'form' ],
			'wpforo_status'                => [],
		];

		update_option( 'hcaptcha_size', $size );
		update_option( 'hcaptcha_wpforms_status', 'on' );

		self::assertSame( [], get_option( 'hcaptcha_settings', [] ) );

		$subject = new Migrations();

		self::assertSame( [], get_option( $subject::MIGRATED_VERSIONS_OPTION_NAME, [] ) );

		$subject->migrate();

		self::assertTrue( $this->compare_migrated( $expected_option, get_option( $subject::MIGRATED_VERSIONS_OPTION_NAME, [] ) ) );
		self::assertSame( $expected_settings, get_option( 'hcaptcha_settings', [] ) );
		self::assertFalse( get_option( 'hcaptcha_size' ) );
		self::assertFalse( get_option( 'hcaptcha_wpforms_status' ) );

		// No migrations on the second run.
		$subject = new Migrations();

		$subject->migrate();

		self::assertTrue( $this->compare_migrated( $expected_option, get_option( $subject::MIGRATED_VERSIONS_OPTION_NAME, [] ) ) );
	}

	/**
	 * Compare migrated option data.
	 *
	 * @param array $expected_option Expected option.
	 * @param array $option          Actual option.
	 *
	 * @return bool
	 */
	private function compare_migrated( array $expected_option, array $option ): bool {
		if ( array_keys( $expected_option ) !== array_keys( $option ) ) {
			return false;
		}

		foreach ( $expected_option as $version => $time ) {
			// Due to the glitch with mocking time(), let us allow 5 seconds time difference.
			if ( $option[ $version ] - $time > 5 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Test migrate_360() when WPForms status not set.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_migrate_360_when_wpforms_status_not_set() {
		$method  = 'migrate_360';
		$subject = Mockery::mock( Migrations::class )->makePartial();

		$this->set_method_accessibility( $subject, $method );

		$option = get_option( 'hcaptcha_settings', [] );

		$subject->$method();

		self::assertSame( $option, get_option( 'hcaptcha_settings', [] ) );
	}

	/**
	 * Test migrate_4_0_0().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_migrate_4_0_0() {
		global $wpdb;

		$method          = 'migrate_4_0_0';
		$subject         = Mockery::mock( Migrations::class )->makePartial();
		$table_name      = Events::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();
		$actual_query    = '';
		$expected_query  = "CREATE TABLE $wpdb->prefix$table_name (
		    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		    source      VARCHAR(256)    NOT NULL,
		    form_id     VARCHAR(20)     NOT NULL,
		    ip          VARCHAR(39)     NOT NULL,
		    user_agent  VARCHAR(256)    NOT NULL,
		    uuid        VARCHAR(36)     NOT NULL,
		    error_codes VARCHAR(256)    NOT NULL,
		    date_gmt    DATETIME        NOT NULL,
		    PRIMARY KEY (id),
		    KEY source (source),
		    KEY form_id (form_id),
		    KEY hcaptcha_id (source, form_id),
		    KEY ip (ip),
		    KEY uuid (uuid),
		    KEY date_gmt (date_gmt)
		) $charset_collate;";

		add_filter(
			'dbdelta_queries',
			static function ( $queries ) use ( &$actual_query ) {
				$actual_query = $queries;

				return $queries;
			}
		);

		$this->set_method_accessibility( $subject, $method );

		$subject->$method();

		self::assertSame( array_filter( explode( ';', $expected_query ) ), $actual_query );
	}
}
