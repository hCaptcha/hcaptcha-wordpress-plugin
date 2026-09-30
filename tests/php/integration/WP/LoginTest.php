<?php
/**
 * LoginTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Login;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_User;

/**
 * Class LoginTest.
 *
 * @group wp-login
 * @group wp
 */
class LoginTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset(
			$_POST['log'],
			$_POST['pwd'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REMOTE_ADDR'],
			$GLOBALS['wp_action']['login_init'],
			$GLOBALS['wp_action']['login_form_login'],
			$GLOBALS['wp_filters']['login_link_separator']
		);
		delete_transient( AutoVerify::TRANSIENT );
		LoginAttempts::delete_all();

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Login();

		self::assertSame( 10, has_action( 'hcap_signature', [ $subject, 'display_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_action( 'login_form', [ $subject, 'display_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'login_form_middle', [ $subject, 'add_signature' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'wp_authenticate_user', [ $subject, 'check_signature' ] ) );
		self::assertSame( 100, has_filter( 'authenticate', [ $subject, 'hide_login_error' ] ) );
		self::assertSame(
			10,
			has_filter( 'hcap_auto_verify_unmatched_form', [ $subject, 'defer_auto_verification' ] )
		);
		self::assertSame(
			10,
			has_filter( 'hcap_wp_login_can_skip_verification', [ $subject, 'allow_wp_login_skip_verification' ] )
		);

		self::assertSame( 10, has_action( 'wp_login', [ $subject, 'login' ] ) );
		self::assertSame( 10, has_action( 'wp_login_failed', [ $subject, 'login_failed' ] ) );

		self::assertSame( 0, has_action( 'hcap_delay_api', [ $subject, 'delay_api' ] ) );

		self::assertSame( 10, has_action( 'login_form', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test display_signature().
	 *
	 * @return void
	 */
	public function test_display_signature(): void {
		$subject = new Login();

		$expected = $this->get_signature( get_class( $subject ) );

		ob_start();
		$subject->display_signature();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_signature().
	 *
	 * @return void
	 */
	public function test_add_signature(): void {
		$content = 'some content';

		$subject = new Login();

		$expected = $content . $this->get_signature( get_class( $subject ) );

		self::assertSame( $expected, $subject->add_signature( $content, [] ) );
	}

	/**
	 * Test check_signature().
	 *
	 * @return void
	 */
	public function test_check_signature(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature' );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$this->prepare_widget_id();

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() when NOT wp login form.
	 *
	 * @return void
	 */
	public function test_check_signature_when_NOT_wp_login_form(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		$subject = new Login();

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() when a good signature.
	 *
	 * @return void
	 */
	public function test_check_signature_when_good_signature(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature', true );
		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() rechecks the login limit before skipping verification.
	 *
	 * @return void
	 */
	public function test_check_signature_when_good_signature_and_login_limit_exceeded(): void {
		$user     = wp_get_current_user();
		$password = 'some password';
		$expected = new WP_Error( 'fail', 'The hCaptcha is invalid.', 400 );

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature', true );
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );
		$this->prepare_widget_id();

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertEquals( $expected, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test check_signature() when another login integration verifies the request.
	 *
	 * @return void
	 */
	public function test_check_signature_when_other_integration_verifies_request(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::check_signature', true );
		add_filter( 'hcap_login_limit_exceeded', '__return_true' );
		add_filter( 'hcap_wp_login_can_skip_verification', '__return_true' );

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		self::assertSame( $user, $subject->check_signature( $user, $password ) );
	}

	/**
	 * Test AutoVerify defers a native login request that owns its signed widget.
	 *
	 * @return void
	 */
	public function test_auto_verify_defers_native_login_request(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-login.php';

		$this->prepare_native_login_owner_post();
		$this->register_bbpress_lost_password_auto_form();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$expected = $_POST;
		$die_arr  = [];

		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		new Login();
		( new AutoVerify() )->verify();

		self::assertSame( [], $die_arr );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( $expected, $_POST );
	}

	/**
	 * Test AutoVerify rejects a native login request without a valid owner signature.
	 *
	 * @return void
	 */
	public function test_auto_verify_rejects_native_login_request_with_bad_owner_signature(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-login.php';

		$this->prepare_native_login_owner_post( false );
		$this->register_bbpress_lost_password_auto_form();

		$die_arr  = [];
		$expected = [
			'Bad hCaptcha signature!',
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 403,
			],
		];

		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		new Login();
		( new AutoVerify() )->verify();

		self::assertSame( $expected, $die_arr );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );
	}

	/**
	 * Test check_signature() when a bad signature.
	 *
	 * @return void
	 */
	public function test_check_signature_when_bad_signature(): void {
		$user     = wp_get_current_user();
		$password = 'some password';

		$subject = new Login();

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$expected = new WP_Error( 'bad-signature', 'Bad hCaptcha signature!', 400 );

		self::assertSame( wp_json_encode( $expected ), wp_json_encode( $subject->check_signature( $user, $password ) ) );
	}

	/**
	 * Test hide_login_error().
	 *
	 * @return void
	 */
	public function test_hide_login_error(): void {
		$user     = new WP_User();
		$username = 'some username';
		$password = 'some password';
		$subject  = new Login();

		// Not a login error.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		$user = new WP_Error();

		// Some login error.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		update_option( 'hcaptcha_settings', [ 'hide_login_errors' => false ] );
		hcaptcha()->init_hooks();

		// The setting 'hide_login_errors' is off.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		update_option( 'hcaptcha_settings', [ 'hide_login_errors' => true ] );
		hcaptcha()->init_hooks();

		$user = new WP_Error( 'empty_username' );

		// Ignore empty_username error code.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		$user = new WP_Error( 'empty_password' );

		// Ignore empty_password error code.
		self::assertSame( $user, $subject->hide_login_error( $user, $username, $password ) );

		$user     = new WP_Error( 'some_error', 'Some error message.', 400 );
		$expected = new WP_Error( 'login_error', 'Login failed.' );

		// Remove non-hCaptcha messages.
		self::assertEquals( $expected, $subject->hide_login_error( $user, $username, $password ) );

		$user = new WP_Error( 'some_error', 'Some error message.', 400 );

		$user->add( 'fail', 'The hCaptcha is invalid.' );

		$expected = new WP_Error( 'fail', 'The hCaptcha is invalid.' );

		// Remove non-hCaptcha messages.
		self::assertEquals( $expected, $subject->hide_login_error( $user, $username, $password ) );
	}

	/**
	 * Test login().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_login(): void {
		$ip         = '1.1.1.1';
		$ip2        = '2.2.2.2';
		$now        = time();
		$user_login = 'test-user';
		$user       = new WP_User();

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 2,
				'login_interval' => 15,
			]
		);
		hcaptcha()->init_hooks();

		$attempts = new LoginAttempts();
		$attempts->increment( $ip, $now, 15 * MINUTE_IN_SECONDS );
		$attempts->increment( $ip2, $now, 15 * MINUTE_IN_SECONDS );

		$subject = new Login();

		$this->set_protected_property( $subject, 'ip', $ip );
		$this->set_protected_property(
			$subject,
			'login_attempts_reset_token',
			$attempts->get_reset_token( $ip, $now )
		);

		$subject->login( $user_login, $user );

		self::assertSame( 0, $attempts->read( $ip, $now ) );
		self::assertSame( 1, $attempts->read( $ip2, $now ) );

		// Check that login attempt options are not autoloading.
		$alloptions = wp_load_alloptions();

		foreach ( array_keys( $alloptions ) as $option_name ) {
			self::assertFalse( 0 === strpos( $option_name, LoginAttempts::OPTION_PREFIX ) );
		}
	}

	/**
	 * Test login_failed().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_login_failed(): void {
		$ip             = '1.1.1.1';
		$time           = time();
		$login_interval = 15;
		$username       = 'test_username';

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 2,
				'login_interval' => $login_interval,
			]
		);
		hcaptcha()->init_hooks();

		$subject  = new Login();
		$subject2 = new Login();

		$this->set_protected_property( $subject, 'ip', $ip );
		$this->set_protected_property( $subject2, 'ip', $ip );

		FunctionMocker::replace( 'time', $time );

		$subject->login_failed( $username );
		$subject2->login_failed( $username );

		$attempts = $this->get_protected_property( $subject, 'login_attempts' );
		$method   = $this->set_method_accessibility( $subject, 'is_login_limit_exceeded' );

		self::assertSame( 2, $attempts->read( $ip, $time ) );
		self::assertTrue( $method->invoke( $subject ) );

		$attempts->increment( $ip, $time + MINUTE_IN_SECONDS, $login_interval * MINUTE_IN_SECONDS );

		self::assertSame( 3, $attempts->read( $ip, $time + $login_interval * MINUTE_IN_SECONDS ) );
		self::assertSame( 0, $attempts->read( $ip, $time + ( $login_interval + 1 ) * MINUTE_IN_SECONDS ) );

		$method->setAccessible( false );
	}

	/**
	 * Test that shared wp_login_failed hooks count one authentication failure once.
	 */
	public function test_login_failed_is_deduplicated_across_integrations(): void {
		$ip                   = '192.0.2.80';
		$wp_login_failed_hook = $GLOBALS['wp_filter']['wp_login_failed'] ?? null;

		remove_all_actions( 'wp_login_failed' );

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 2,
				'login_interval' => 15,
			]
		);
		hcaptcha()->init_hooks();

		$subject  = new Login();
		$subject2 = new Login();

		$this->set_protected_property( $subject, 'ip', $ip );
		$this->set_protected_property( $subject2, 'ip', $ip );

		do_action( 'wp_login_failed', 'test-user' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_filter']['wp_login_failed'] = $wp_login_failed_hook;

		$attempts = $this->get_protected_property( $subject, 'login_attempts' );

		self::assertSame( 1, $attempts->read( $ip, time() ) );
	}

	/**
	 * Test immediate-CAPTCHA mode does not allocate attempt state.
	 */
	public function test_login_limit_zero_does_not_store_failures(): void {
		global $wpdb;

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 0,
				'login_interval' => 15,
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();
		$subject->login_failed( 'test-user' );

		$method = $this->set_method_accessibility( $subject, 'is_login_limit_exceeded' );

		self::assertTrue( $method->invoke( $subject ) );
		self::assertSame(
			0,
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			(int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s",
					$wpdb->esc_like( LoginAttempts::OPTION_PREFIX ) . '%'
				)
			)
		);

		$method->setAccessible( false );
	}

	/**
	 * Test one-time bounded retirement of legacy login data.
	 */
	public function test_legacy_login_data_retirement_is_bounded_and_one_time(): void {
		global $wpdb;

		$legacy_data = [];

		for ( $i = 0; $i < 5000; ++$i ) {
			$legacy_data[ '192.0.2.' . $i ] = 0 === $i % 2 ? [] : [ time() - YEAR_IN_SECONDS ];
		}

		update_option( LoginBase::LOGIN_DATA, $legacy_data, false );

		$queries = [];
		$filter  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;

			return $query;
		};

		add_filter( 'query', $filter );

		new Login();
		new Login();

		remove_filter( 'query', $filter );

		$legacy_selects = array_filter(
			$queries,
			static function ( $query ) {
				return false !== stripos( $query, 'SELECT option_value' ) &&
					false !== stripos( $query, "option_name = 'hcaptcha_login_data'" );
			}
		);
		$legacy_deletes = array_filter(
			$queries,
			static function ( $query ) {
				return false !== stripos( $query, 'DELETE FROM' ) &&
					false !== stripos( $query, "option_name = 'hcaptcha_login_data'" );
			}
		);

		self::assertSame( [], array_values( $legacy_selects ) );
		self::assertCount( 1, $legacy_deletes );
		self::assertFalse( get_option( LoginBase::LOGIN_DATA, false ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		self::assertSame( '1', $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", LoginAttempts::RETIREMENT_OPTION ) ) );
	}

	/**
	 * Test that a full store stays bounded and fails closed at constant cost.
	 */
	public function test_login_attempt_store_is_globally_bounded(): void {
		global $wpdb;

		$now        = time();
		$owner_hash = str_repeat( 'a', 64 );
		$record     = $owner_hash . '|1|' . ( $now + HOUR_IN_SECONDS );
		$values     = [];
		$params     = [];

		for ( $slot = 0; $slot < LoginAttempts::SLOT_COUNT; ++$slot ) {
			$values[] = '(%s, %s, %s)';
			$params[] = LoginAttempts::OPTION_PREFIX . sprintf( '%04d', $slot );
			$params[] = $record;
			$params[] = 'no';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->options (option_name, option_value, autoload) VALUES " . implode( ', ', $values ),
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		update_option(
			'hcaptcha_settings',
			[
				'login_limit'    => 2,
				'login_interval' => 15,
			]
		);
		hcaptcha()->init_hooks();

		$subject = new Login();
		$this->set_protected_property( $subject, 'ip', '198.51.100.25' );

		$operation_queries = [];
		$filter            = static function ( $query ) use ( &$operation_queries ) {
			if ( false !== strpos( $query, LoginAttempts::OPTION_PREFIX ) ) {
				$operation_queries[] = $query;
			}

			return $query;
		};

		add_filter( 'query', $filter );
		$subject->login_failed( 'test-user' );
		remove_filter( 'query', $filter );

		$method = $this->set_method_accessibility( $subject, 'is_login_limit_exceeded' );

		self::assertCount( 2, $operation_queries );
		self::assertTrue( $method->invoke( $subject ) );
		self::assertSame(
			LoginAttempts::SLOT_COUNT,
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			(int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s",
					$wpdb->esc_like( LoginAttempts::OPTION_PREFIX ) . '%'
				)
			)
		);
		self::assertLessThanOrEqual(
			LoginAttempts::MAX_RECORD_BYTES,
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			(int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(OCTET_LENGTH(option_value)) FROM $wpdb->options WHERE option_name LIKE %s",
					$wpdb->esc_like( LoginAttempts::OPTION_PREFIX ) . '%'
				)
			)
		);

		$method->setAccessible( false );
	}

	/**
	 * Test that the per-address counter saturates without growing its record.
	 */
	public function test_login_attempt_count_is_saturated(): void {
		$ip           = '198.51.100.80';
		$now          = time();
		$address_hash = hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
		$slot         = hexdec( substr( $address_hash, 0, 4 ) ) % LoginAttempts::SLOT_COUNT;
		$option_name  = LoginAttempts::OPTION_PREFIX . sprintf( '%04d', $slot );
		$expires      = $now + MINUTE_IN_SECONDS;
		$record       = $address_hash . '|' . LoginAttempts::MAX_FAILURES . '|' . $expires;
		$attempts     = new LoginAttempts();

		update_option( $option_name, $record, false );

		$operation_queries = [];
		$filter            = static function ( $query ) use ( &$operation_queries, $option_name ) {
			if ( false !== strpos( $query, $option_name ) ) {
				$operation_queries[] = $query;
			}

			return $query;
		};

		add_filter( 'query', $filter );
		$count = $attempts->increment( $ip, $now, MINUTE_IN_SECONDS );
		remove_filter( 'query', $filter );

		self::assertSame( LoginAttempts::MAX_FAILURES, $count );
		self::assertCount( 2, $operation_queries );
		self::assertLessThanOrEqual( LoginAttempts::MAX_RECORD_BYTES, strlen( get_option( $option_name ) ) );
	}

	/**
	 * Test add_captcha().
	 */
	public function test_add_captcha(): void {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$args     = [
			'action' => 'hcaptcha_login',
			'name'   => 'hcaptcha_login_nonce',
			'id'     => [
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			],
		];
		$expected = $this->get_hcap_form( $args );

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not WP login form.
	 */
	public function test_add_captcha_when_NOT_wp_login_form(): void {
		$expected = '';

		$subject = new Login();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_captcha() when not login limit exceeded.
	 */
	public function test_add_captcha_when_NOT_login_limit_exceeded(): void {
		$expected = '';

		$subject = new Login();

		add_filter( 'hcap_login_limit_exceeded', '__return_false' );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$user = new WP_User( 1 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$this->prepare_widget_id();

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $user, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Test verify() when the login limit is not exceeded.
	 */
	public function test_verify_NOT_limit_exceeded(): void {
		$user = new WP_User( 1 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		update_option( 'hcaptcha_settings', [ 'login_limit' => 5 ] );
		hcaptcha()->init_hooks();

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $user, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Test verify() not verified.
	 */
	public function test_verify_not_verified(): void {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'fail', 'The hCaptcha is invalid.', 400 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login', false );
		$this->prepare_widget_id();

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$subject = new Login();

		self::assertEquals( $expected, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$user     = new WP_User( 1 );
		$expected = new WP_Error( 'bad-signature', 'Bad hCaptcha signature!', 400 );

		$this->prepare_verify_post_html( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$this->prepare_widget_id(
			[
				'source'  => [ 'bbpress/bbpress.php' ],
				'form_id' => 'login',
			]
		);

		$_POST['log'] = 'some login';
		$_POST['pwd'] = 'some password';

		$subject = new Login();

		self::assertEquals( $expected, $subject->login_base_verify( $user, '' ) );
	}

	/**
	 * Get signature.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return string
	 */
	private function get_signature( string $class_name ): string {
		$const = HCaptcha::HCAPTCHA_SIGNATURE;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = $const . '-' . base64_encode( $class_name );

		return '		<input
				type="hidden"
				class="' . $const . '"
				name="' . $name . '"
				value="' . $this->get_encoded_signature( $class_name, [ 'WordPress' ], 'login', false ) . '">
		';
	}

	/**
	 * Prepare widget id.
	 *
	 * @param array $id The hCaptcha widget id.
	 */
	private function prepare_widget_id( array $id = [] ): void {
		$id = $id ?: [
			'source'  => [ 'WordPress' ],
			'form_id' => 'login',
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Prepare a native login request with its signed widget owner.
	 *
	 * @param bool $valid_signature Whether to use a valid owner signature.
	 *
	 * @return void
	 */
	private function prepare_native_login_owner_post( bool $valid_signature = true ): void {
		$this->prepare_widget_id();

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( Login::class );

		$_POST['log']   = 'some login';
		$_POST['pwd']   = 'some password';
		$_POST[ $name ] = $valid_signature ?
			$this->get_encoded_signature( Login::class, [ 'WordPress' ], 'login', true ) :
			'bad-signature';
	}

	/**
	 * Register a colliding bbPress lost-password form on the login endpoint.
	 *
	 * @return void
	 */
	private function register_bbpress_lost_password_auto_form(): void {
		$id         = [
			'source'  => [ 'bbpress/bbpress.php' ],
			'form_id' => 'lost_password',
		];
		$login_path = untrailingslashit( (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) );

		set_transient(
			AutoVerify::TRANSIENT,
			[
				$login_path => [
					[
						'inputs'    => [ 'user_login' ],
						'args'      => [ 'id' => $id ],
						'widget_id' => HCaptcha::widget_id_value( $id ),
					],
				],
			]
		);
	}
}
