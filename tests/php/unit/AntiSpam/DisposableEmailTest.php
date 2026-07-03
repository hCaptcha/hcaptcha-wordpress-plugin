<?php
/**
 * DisposableEmailTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\AntiSpam;

use HCaptcha\AntiSpam\DisposableEmail;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use ReflectionException;
use stdClass;
use tad\FunctionMocker\FunctionMocker;
use Mockery;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;

/**
 * Test DisposableEmail class.
 *
 * @group antispam
 * @group disposable-email
 */
class DisposableEmailTest extends HCaptchaTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wp_filesystem'] );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_hooks(): void {
		WP_Mock::expectActionAdded( 'admin_notices', [ DisposableEmail::class, 'show_download_failed_notice' ] );
		WP_Mock::expectActionAdded(
			DisposableEmail::UPDATE_ACTION,
			[ new AnyInstance( DisposableEmail::class ), 'update_blocklist' ]
		);

		new DisposableEmail();
	}

	/**
	 * Test get_blocklist() returns a cached transient.
	 */
	public function test_get_blocklist_returns_cached(): void {
		$cached = [
			'mailinator.com'    => true,
			'guerrillamail.com' => true,
		];

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		WP_Mock::userFunction( 'get_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_blocklist' )
			->andReturn( $cached );

		WP_Mock::onFilter( 'hcap_disposable_email_domains' )
			->with( $cached )
			->reply( $cached );

		self::assertSame( $cached, $subject->get_blocklist() );
	}

	/**
	 * Test get_blocklist() reads a file from uploads when no transient.
	 */
	public function test_get_blocklist_reads_file_from_uploads(): void {
		$file_content = "mailinator.com\nguerrillamail.com\n";
		$expected     = [
			'mailinator.com'    => true,
			'guerrillamail.com' => true,
		];

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'read_file' )->once()->andReturn( $file_content );

		WP_Mock::userFunction( 'get_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_blocklist' )
			->andReturn( false );

		WP_Mock::userFunction( 'wp_upload_dir' )
			->once()
			->andReturn( [ 'basedir' => '/tmp/uploads' ] );

		WP_Mock::userFunction( 'trailingslashit' )
			->once()
			->with( '/tmp/uploads' )
			->andReturn( '/tmp/uploads/' );

		WP_Mock::userFunction( 'set_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_blocklist', $expected, 86400 );

		WP_Mock::onFilter( 'hcap_disposable_email_domains' )
			->with( $expected )
			->reply( $expected );

		self::assertSame( $expected, $subject->get_blocklist() );
	}

	/**
	 * Test get_blocklist() returns an empty array when a file doesn't exist (graceful degradation).
	 */
	public function test_get_blocklist_returns_empty_when_file_missing(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'read_file' )->once()->andReturn( false );

		WP_Mock::userFunction( 'get_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_blocklist' )
			->andReturn( false );

		WP_Mock::userFunction( 'wp_upload_dir' )
			->once()
			->andReturn( [ 'basedir' => '/tmp/uploads' ] );

		WP_Mock::userFunction( 'trailingslashit' )
			->once()
			->with( '/tmp/uploads' )
			->andReturn( '/tmp/uploads/' );

		WP_Mock::userFunction( 'set_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_blocklist', [], 86400 );

		WP_Mock::onFilter( 'hcap_disposable_email_domains' )
			->with( [] )
			->reply( [] );

		self::assertSame( [], $subject->get_blocklist() );
	}

	/**
	 * Test get_blocklist_path() returns uploads a path.
	 */
	public function test_get_blocklist_path(): void {
		WP_Mock::userFunction( 'wp_upload_dir' )
			->once()
			->andReturn( [ 'basedir' => '/var/www/html/wp-content/uploads' ] );

		WP_Mock::userFunction( 'trailingslashit' )
			->once()
			->with( '/var/www/html/wp-content/uploads' )
			->andReturn( '/var/www/html/wp-content/uploads/' );

		self::assertSame(
			'/var/www/html/wp-content/uploads/hcaptcha/disposable-email-blocklist.conf',
			DisposableEmail::get_blocklist_path()
		);
	}

	/**
	 * Test read_file() when WP_Filesystem init fails.
	 */
	public function test_read_file_when_filesystem_init_fails(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( false );

		self::assertFalse( $subject->read_file( '/tmp/blocklist.conf' ) );
	}

	/**
	 * Test read_file() when the file does not exist.
	 */
	public function test_read_file_when_file_does_not_exist(): void {
		$file          = '/tmp/blocklist.conf';
		$wp_filesystem = $this->mock_wp_filesystem();
		$wp_filesystem->shouldReceive( 'exists' )->with( $file )->once()->andReturn( false );
		$wp_filesystem->shouldReceive( 'get_contents' )->never();

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( true );

		self::assertFalse( $subject->read_file( $file ) );
	}

	/**
	 * Test read_file() returns contents.
	 */
	public function test_read_file_returns_contents(): void {
		$file          = '/tmp/blocklist.conf';
		$contents      = "mailinator.com\n";
		$wp_filesystem = $this->mock_wp_filesystem();
		$wp_filesystem->shouldReceive( 'exists' )->with( $file )->once()->andReturn( true );
		$wp_filesystem->shouldReceive( 'get_contents' )->with( $file )->once()->andReturn( $contents );

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( true );

		self::assertSame( $contents, $subject->read_file( $file ) );
	}

	/**
	 * Test download_blocklist() when WP_Filesystem init fails.
	 */
	public function test_download_blocklist_when_filesystem_init_fails(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( false );

		self::assertFalse( $subject->download_blocklist() );
	}

	/**
	 * Test download_blocklist() when a remote request fails.
	 */
	public function test_download_blocklist_when_request_fails(): void {
		$response = new stdClass();
		$this->mock_wp_filesystem();
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( true );

		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->with( $response )->once()->andReturn( true );

		self::assertFalse( $subject->download_blocklist() );
	}

	/**
	 * Test download_blocklist() when the response code is not OK.
	 */
	public function test_download_blocklist_when_response_code_is_not_ok(): void {
		$response = new stdClass();
		$this->mock_wp_filesystem();
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( true );

		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->with( $response )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->with( $response )->once()->andReturn( 500 );

		self::assertFalse( $subject->download_blocklist() );
	}

	/**
	 * Test download_blocklist() when the response body is empty.
	 */
	public function test_download_blocklist_when_body_is_empty(): void {
		$response = new stdClass();
		$this->mock_wp_filesystem();
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( true );

		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->with( $response )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->with( $response )->once()->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->with( $response )->once()->andReturn( '' );

		self::assertFalse( $subject->download_blocklist() );
	}

	/**
	 * Test download_blocklist() when directory creation fails.
	 */
	public function test_download_blocklist_when_directory_creation_fails(): void {
		$base_dir      = str_replace( '\\', '/', sys_get_temp_dir() ) . '/hcaptcha-antispam-uploads';
		$path          = $this->mock_blocklist_path( $base_dir );
		$dir           = dirname( $path );
		$wp_filesystem = $this->mock_wp_filesystem();
		$subject       = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->mock_wp_filesystem_init( true );
		$this->mock_successful_remote_blocklist_response( "mailinator.com\n" );

		$wp_filesystem->shouldReceive( 'is_dir' )->with( $dir )->once()->andReturn( false );
		$wp_filesystem->shouldReceive( 'put_contents' )->never();

		WP_Mock::userFunction( 'wp_mkdir_p' )->with( $dir )->once()->andReturn( false );

		self::assertFalse( $subject->download_blocklist() );
	}

	/**
	 * Test download_blocklist() success.
	 */
	public function test_download_blocklist_success(): void {
		$base_dir      = str_replace( '\\', '/', sys_get_temp_dir() ) . '/hcaptcha-antispam-uploads';
		$body          = "mailinator.com\n";
		$path          = $this->mock_blocklist_path( $base_dir );
		$dir           = dirname( $path );
		$wp_filesystem = $this->mock_wp_filesystem();
		$subject       = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$this->define_fs_chmod_file();
		$this->mock_wp_filesystem_init( true );
		$this->mock_successful_remote_blocklist_response( $body );

		$wp_filesystem->shouldReceive( 'is_dir' )->with( $dir )->once()->andReturn( false );
		$wp_filesystem->shouldReceive( 'put_contents' )
			->with( $path, $body, constant( 'HCaptcha\\AntiSpam\\FS_CHMOD_FILE' ) )
			->once()
			->andReturn( true );

		WP_Mock::userFunction( 'wp_mkdir_p' )->with( $dir )->once()->andReturn( true );

		self::assertTrue( $subject->download_blocklist() );
	}

	/**
	 * Test schedule_update() when Action Scheduler is unavailable.
	 */
	public function test_schedule_update_without_action_scheduler(): void {
		FunctionMocker::replace( 'function_exists', false );

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->schedule_update();
	}

	/**
	 * Test unschedule_update() when Action Scheduler is unavailable.
	 */
	public function test_unschedule_update_without_action_scheduler(): void {
		FunctionMocker::replace( 'function_exists', false );

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->unschedule_update();
	}

	/**
	 * Test is_disposable_email().
	 *
	 * @dataProvider dp_test_is_disposable_email
	 *
	 * @param string $email    Email address to test.
	 * @param array  $domains  Blocklist domains.
	 * @param bool   $expected Expected result.
	 */
	public function test_is_disposable_email( string $email, array $domains, bool $expected ): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldReceive( 'get_blocklist' )->andReturn( $domains );

		self::assertSame( $expected, $subject->is_disposable_email( $email ) );
	}

	/**
	 * Data provider for test_is_disposable_email().
	 *
	 * @return array
	 */
	public function dp_test_is_disposable_email(): array {
		$blocklist = [
			'mailinator.com'    => true,
			'guerrillamail.com' => true,
			'tempmail.org'      => true,
		];

		return [
			'disposable email'     => [ 'test@mailinator.com', $blocklist, true ],
			'another disposable'   => [ 'user@guerrillamail.com', $blocklist, true ],
			'legitimate email'     => [ 'user@gmail.com', $blocklist, false ],
			'subdomain disposable' => [ 'user@sub.mailinator.com', $blocklist, true ],
			'empty email'          => [ '', $blocklist, false ],
			'no @ sign'            => [ 'noemail', $blocklist, false ],
			'@ only'               => [ '@', $blocklist, false ],
			'no domain'            => [ 'user@', $blocklist, false ],
			'uppercase disposable' => [ 'TEST@MAILINATOR.COM', $blocklist, true ],
			'mixed case'           => [ 'Test@Mailinator.Com', $blocklist, true ],
		];
	}

	/**
	 * Test verify() returns true (passes) when the email is empty.
	 */
	public function test_verify_empty_email(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$entry   = [ 'data' => [ 'email' => '' ] ];

		WP_Mock::userFunction( 'is_email' )
			->once()
			->with( '' )
			->andReturn( false );

		self::assertNotEmpty( $subject->verify( $entry ) );
	}

	/**
	 * Test verify() returns true (passes) when an email key is missing.
	 */
	public function test_verify_missing_email_key(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$entry   = [ 'data' => [] ];

		WP_Mock::userFunction( 'is_email' )
			->once()
			->with( '' )
			->andReturn( false );

		self::assertNotEmpty( $subject->verify( $entry ) );
	}

	/**
	 * Test verify() returns false for disposable email.
	 */
	public function test_verify_disposable_email(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldReceive( 'is_disposable_email' )
			->with( 'test@mailinator.com' )
			->once()
			->andReturn( true );

		WP_Mock::userFunction( 'is_email' )
			->once()
			->with( 'test@mailinator.com' )
			->andReturn( true );

		$entry = [ 'data' => [ 'email' => 'test@mailinator.com' ] ];

		WP_Mock::onFilter( 'hcap_is_disposable_email' )
			->with( true, 'test@mailinator.com' )
			->reply( true );

		// verify() returns ! $disposable, so falsy for disposable email.
		self::assertEmpty( $subject->verify( $entry ) );
	}

	/**
	 * Test verify() returns true for legitimate email.
	 */
	public function test_verify_legitimate_email(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldReceive( 'is_disposable_email' )
			->with( 'user@gmail.com' )
			->once()
			->andReturn( false );

		WP_Mock::userFunction( 'is_email' )
			->once()
			->with( 'user@gmail.com' )
			->andReturn( true );

		$entry = [ 'data' => [ 'email' => 'user@gmail.com' ] ];

		WP_Mock::onFilter( 'hcap_is_disposable_email' )
			->with( false, 'user@gmail.com' )
			->reply( false );

		// verify() returns ! $disposable, so truthy for legitimate email.
		self::assertNotEmpty( $subject->verify( $entry ) );
	}

	/**
	 * Test verify() respects hcap_is_disposable_email filter.
	 */
	public function test_verify_filter_overrides(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldReceive( 'is_disposable_email' )
			->with( 'user@gmail.com' )
			->once()
			->andReturn( false );

		WP_Mock::userFunction( 'is_email' )
			->once()
			->with( 'user@gmail.com' )
			->andReturn( true );

		$entry = [ 'data' => [ 'email' => 'user@gmail.com' ] ];

		// Filter overrides is_disposable_email to true.
		WP_Mock::onFilter( 'hcap_is_disposable_email' )
			->with( false, 'user@gmail.com' )
			->reply( true );

		// verify() returns ! $disposable, so falsy when filter marks as disposable.
		self::assertEmpty( $subject->verify( $entry ) );
	}

	/**
	 * Test update_blocklist() clears transient on success.
	 */
	public function test_update_blocklist_clears_transient_on_success(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'download_blocklist' )->once()->andReturn( true );

		WP_Mock::userFunction( 'delete_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_blocklist' );

		$subject->update_blocklist();
	}

	/**
	 * Test update_blocklist() does nothing on failure.
	 */
	public function test_update_blocklist_does_nothing_on_failure(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'download_blocklist' )->once()->andReturn( false );

		WP_Mock::userFunction( 'delete_transient' )->never();

		$subject->update_blocklist();
	}

	/**
	 * Test schedule_update() schedules recurring action.
	 */
	public function test_schedule_update(): void {
		if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
			define( 'WEEK_IN_SECONDS', 7 * 24 * 60 * 60 );
		}

		WP_Mock::userFunction( 'as_schedule_recurring_action' )
			->once()
			->with(
				Mockery::type( 'int' ),
				WEEK_IN_SECONDS,
				'hcap_update_disposable_email_blocklist',
				[],
				'hcaptcha',
				true
			);

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->schedule_update();
	}

	/**
	 * Test unschedule_update() unschedules all actions.
	 */
	public function test_unschedule_update(): void {
		WP_Mock::userFunction( 'as_unschedule_all_actions' )
			->once()
			->with( 'hcap_update_disposable_email_blocklist', [], 'hcaptcha' );

		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->unschedule_update();
	}

	/**
	 * Test activate() downloads blocklist and schedules update when the file doesn't exist.
	 */
	public function test_activate_downloads_and_schedules(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'download_blocklist' )->once()->andReturn( true );
		$subject->shouldReceive( 'schedule_update' )->once();

		WP_Mock::userFunction( 'wp_upload_dir' )
			->once()
			->andReturn( [ 'basedir' => '/tmp/uploads' ] );

		WP_Mock::userFunction( 'trailingslashit' )
			->once()
			->with( '/tmp/uploads' )
			->andReturn( '/tmp/uploads/' );

		$subject->activate();
	}

	/**
	 * Test activate() sets notice transient on download failure.
	 */
	public function test_activate_sets_notice_on_download_failure(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'download_blocklist' )->once()->andReturn( false );
		$subject->shouldReceive( 'schedule_update' )->once();

		WP_Mock::userFunction( 'wp_upload_dir' )
			->once()
			->andReturn( [ 'basedir' => '/tmp/uploads' ] );

		WP_Mock::userFunction( 'trailingslashit' )
			->once()
			->with( '/tmp/uploads' )
			->andReturn( '/tmp/uploads/' );

		WP_Mock::userFunction( 'set_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_download_failed', true, 60 );

		$subject->activate();
	}

	/**
	 * Test deactivate() unschedules update.
	 */
	public function test_deactivate_unschedules(): void {
		$subject = Mockery::mock( DisposableEmail::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'unschedule_update' )->once();

		$subject->deactivate();
	}

	/**
	 * Test show_download_failed_notice() shows notice when transient is set.
	 */
	public function test_show_download_failed_notice(): void {
		WP_Mock::userFunction( 'get_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_download_failed' )
			->andReturn( true );

		WP_Mock::userFunction( 'delete_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_download_failed' );

		WP_Mock::userFunction( '__' )
			->once()
			->andReturnArg( 0 );

		WP_Mock::userFunction( 'esc_html' )
			->once()
			->andReturnArg( 0 );

		ob_start();
		DisposableEmail::show_download_failed_notice();
		$output = ob_get_clean();

		self::assertStringContainsString( 'notice notice-warning', $output );
		self::assertStringContainsString( 'Disposable email blocklist could not be downloaded', $output );
	}

	/**
	 * Test show_download_failed_notice() does nothing when transient is not set.
	 */
	public function test_show_download_failed_notice_no_transient(): void {
		WP_Mock::userFunction( 'get_transient' )
			->once()
			->with( 'hcaptcha_disposable_email_download_failed' )
			->andReturn( false );

		WP_Mock::userFunction( 'delete_transient' )->never();

		ob_start();
		DisposableEmail::show_download_failed_notice();
		$output = ob_get_clean();

		self::assertEmpty( $output );
	}

	/**
	 * Mock WP_Filesystem global.
	 *
	 * @return Mockery\LegacyMockInterface
	 */
	private function mock_wp_filesystem(): Mockery\LegacyMockInterface {
		global $wp_filesystem;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = Mockery::mock();

		return $wp_filesystem;
	}

	/**
	 * Mock WP_Filesystem init.
	 *
	 * @param bool $result WP_Filesystem result.
	 *
	 * @return void
	 */
	private function mock_wp_filesystem_init( bool $result ): void {
		$this->define_namespaced_abspath();

		FunctionMocker::replace( 'HCaptcha\AntiSpam\WP_Filesystem', $result );
	}

	/**
	 * Define namespaced ABSPATH for AntiSpam filesystem tests.
	 *
	 * @return void
	 */
	private function define_namespaced_abspath(): void {
		$base_dir = str_replace( '\\', '/', sys_get_temp_dir() ) . '/hcaptcha-antispam-wp';
		$file     = $base_dir . '/wp-admin/includes/file.php';

		if ( ! is_dir( dirname( $file ) ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			mkdir( dirname( $file ), 0777, true );
		}

		if ( ! file_exists( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, "<?php\n" );
		}

		if ( ! defined( 'HCaptcha\AntiSpam\ABSPATH' ) ) {
			define( 'HCaptcha\AntiSpam\ABSPATH', $base_dir . '/' );
		}
	}

	/**
	 * Define namespaced FS_CHMOD_FILE for AntiSpam filesystem tests.
	 *
	 * @return void
	 */
	private function define_fs_chmod_file(): void {
		if ( ! defined( 'HCaptcha\AntiSpam\FS_CHMOD_FILE' ) ) {
			define( 'HCaptcha\AntiSpam\FS_CHMOD_FILE', 0644 );
		}
	}

	/**
	 * Mock a successful remote blocklist response.
	 *
	 * @param string $body Response body.
	 *
	 * @return void
	 */
	private function mock_successful_remote_blocklist_response( string $body ): void {
		$response = new stdClass();

		WP_Mock::userFunction( 'wp_remote_get' )->once()->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->with( $response )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->with( $response )->once()->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->with( $response )->once()->andReturn( $body );
	}

	/**
	 * Mock blocklist path helpers.
	 *
	 * @param string $base_dir Upload base dir.
	 *
	 * @return string
	 */
	private function mock_blocklist_path( string $base_dir ): string {
		$path = $base_dir . '/hcaptcha/disposable-email-blocklist.conf';

		WP_Mock::userFunction( 'wp_upload_dir' )->once()->andReturn( [ 'basedir' => $base_dir ] );
		WP_Mock::userFunction( 'trailingslashit' )->with( $base_dir )->once()->andReturn( $base_dir . '/' );

		return $path;
	}
}
