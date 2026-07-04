<?php
/**
 * AntiSpamTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\AntiSpam;

use HCaptcha\AntiSpam\AntiSpam;
use HCaptcha\AntiSpam\ProviderBase;
use HCaptcha\Main;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use Mockery;
use WP_Mock;

/**
 * Test AntiSpam class.
 *
 * @group antispam
 */
class AntiSpamTest extends HCaptchaTestCase {

	/**
	 * Test constructor fills missing entry keys.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_constructor_adds_defaults(): void {
		$entry = [
			'data'  => [ 'email' => 'person@example.test' ],
			'email' => 'person@example.test',
		];

		$subject = $this->make_subject( $entry );

		self::assertSame(
			[
				'data'          => [ 'email' => 'person@example.test' ],
				'name'          => null,
				'email'         => 'person@example.test',
				'form_date_gmt' => null,
			],
			$this->get_protected_property( $subject, 'entry' )
		);
	}

	/**
	 * Test init() exits when anti-spam is off.
	 */
	public function test_init_when_antispam_is_off(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'antispam' )->once()->andReturn( false );
		$settings->shouldReceive( 'get' )->never();

		$this->mock_main_settings( $settings );

		$subject = $this->make_subject();

		$subject->init();
	}

	/**
	 * Test init() exits when the selected provider is unsupported or unconfigured.
	 */
	public function test_init_when_provider_is_not_configured(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'antispam' )->once()->andReturn( true );
		$settings->shouldReceive( 'get' )->with( 'antispam_provider' )->once()->andReturn( 'native' );

		$this->mock_main_settings( $settings );

		$subject = $this->make_subject();

		$subject->init();
	}

	/**
	 * Test init() with a configured provider.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_init_when_provider_is_configured(): void {
		$this->alias_native_provider();

		FunctionMocker::replace( '\HCaptcha\AntiSpam\AntiSpam::is_provider_configured', true );

		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'antispam' )->twice()->andReturn( true );
		$settings->shouldReceive( 'get' )->with( 'antispam_provider' )->twice()->andReturn( 'native' );

		$this->mock_main_settings( $settings );

		$subject = $this->make_subject();

		WP_Mock::expectFilterAdded(
			'hcap_verify_request',
			[ $subject, 'verify_request_filter' ],
			AntiSpam::VERIFY_REQUEST_PRIORITY,
			3
		);

		$subject->init();

		self::assertInstanceOf( 'HCaptcha\AntiSpam\Native', $this->get_protected_property( $subject, 'provider' ) );
		self::assertSame( [], AntiSpam::get_protected_forms() );
	}

	/**
	 * Test init_hooks().
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_init_hooks(): void {
		$subject = $this->make_subject();
		$method  = $this->set_method_accessibility( $subject, 'init_hooks' );

		WP_Mock::expectFilterAdded(
			'hcap_verify_request',
			[ $subject, 'verify_request_filter' ],
			AntiSpam::VERIFY_REQUEST_PRIORITY,
			3
		);

		$method->invoke( $subject );
	}

	/**
	 * Test verify_request_filter().
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_verify_request_filter(): void {
		$entry = [
			'data'          => [ 'email' => 'bot@example.test' ],
			'name'          => null,
			'email'         => null,
			'form_date_gmt' => null,
		];

		$subject  = $this->make_subject( $entry );
		$provider = Mockery::mock( ProviderBase::class );
		$provider->shouldReceive( 'verify' )->with( $entry )->once()->andReturn( 'Spam detected.' );

		$this->set_protected_property( $subject, 'provider', $provider );

		$error_info = (object) [ 'codes' => [] ];

		self::assertSame( 'already failed', $subject->verify_request_filter( 'already failed', [], $error_info ) );
		self::assertSame( [], $error_info->codes );

		self::assertSame( 'Spam detected.', $subject->verify_request_filter( null, [], $error_info ) );
		self::assertSame( [ 'spam' ], $error_info->codes );

		$cached_error_info = (object) [ 'codes' => [] ];

		self::assertSame( 'Spam detected.', $subject->verify_request_filter( null, [], $cached_error_info ) );
		self::assertSame( [], $cached_error_info->codes );
	}

	/**
	 * Test get_protected_forms() when anti-spam is off.
	 */
	public function test_get_protected_forms_when_antispam_is_off(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'antispam' )->once()->andReturn( false );
		$settings->shouldReceive( 'get' )->never();

		$this->mock_main_settings( $settings );

		self::assertSame( [], AntiSpam::get_protected_forms() );
	}

	/**
	 * Test get_protected_forms() when the provider is unsupported or unconfigured.
	 */
	public function test_get_protected_forms_when_provider_is_not_configured(): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'antispam' )->once()->andReturn( true );
		$settings->shouldReceive( 'get' )->with( 'antispam_provider' )->once()->andReturn( 'native' );

		$this->mock_main_settings( $settings );

		self::assertSame( [], AntiSpam::get_protected_forms() );
	}

	/**
	 * Test provider list methods.
	 */
	public function test_provider_list_methods(): void {
		self::assertSame( [], AntiSpam::get_supported_providers() );
		self::assertSame( [], AntiSpam::get_configured_providers() );
	}

	/**
	 * Test private provider helpers.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_private_provider_helpers(): void {
		$subject                = $this->make_subject();
		$get_provider_classname = $this->set_method_accessibility( $subject, 'get_provider_classname' );
		$is_provider_supported  = $this->set_method_accessibility( $subject, 'is_provider_supported' );
		$is_provider_configured = $this->set_method_accessibility( $subject, 'is_provider_configured' );

		self::assertSame( 'HCaptcha\\AntiSpam\\Native', $get_provider_classname->invoke( null, 'native' ) );
		self::assertFalse( $is_provider_supported->invoke( null, 'native' ) );
		self::assertFalse( $is_provider_configured->invoke( null, 'native' ) );
	}

	/**
	 * Alias test provider class to the provider name used by AntiSpam.
	 *
	 * @return void
	 */
	private function alias_native_provider(): void {
		if ( class_exists( 'HCaptcha\AntiSpam\Native', false ) ) {
			return;
		}

		$provider = new class() extends ProviderBase {
			/**
			 * Has the provider been configured with a valid API key?
			 *
			 * @return bool
			 */
			public static function is_configured(): bool {
				return true;
			}

			/**
			 * Verify entry.
			 *
			 * @param array $entry Entry data.
			 *
			 * @return string|null
			 */
			public function verify( array $entry ): ?string {
				return null;
			}
		};

		class_alias( get_class( $provider ), 'HCaptcha\AntiSpam\Native' );
	}
	/**
	 * Make AntiSpam subject.
	 *
	 * @param array $entry Entry.
	 *
	 * @return AntiSpam
	 */
	private function make_subject( array $entry = [] ): AntiSpam {
		$this->mock_wp_parse_args();

		return new AntiSpam( $entry );
	}

	/**
	 * Mock wp_parse_args().
	 *
	 * @return void
	 */
	private function mock_wp_parse_args(): void {
		WP_Mock::userFunction( 'wp_parse_args' )->andReturnUsing(
			static function ( array $args, array $defaults = [] ): array {
				return array_merge( $defaults, $args );
			}
		);
	}

	/**
	 * Mock hcaptcha()->settings().
	 *
	 * @param Settings $settings Settings.
	 *
	 * @return void
	 */
	private function mock_main_settings( Settings $settings ): void {
		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );
	}
}
