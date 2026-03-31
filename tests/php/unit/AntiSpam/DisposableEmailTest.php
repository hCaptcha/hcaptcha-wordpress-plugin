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
	 * Test constructor and init_hooks().
	 *
	 * @throws \ReflectionException ReflectionException.
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
	 * Test get_blocklist() returns cached transient.
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
	 * Test get_blocklist() reads file from uploads when no transient.
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
	 * Test get_blocklist() returns empty array when file doesn't exist (graceful degradation).
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
	 * Test get_blocklist_path() returns uploads path.
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
	 * Test activate() downloads blocklist and schedules update when file doesn't exist.
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
}
