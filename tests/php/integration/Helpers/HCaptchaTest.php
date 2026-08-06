<?php
/**
 * HCaptchaTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\CF7\CF7;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Test HCaptcha class.
 *
 * @group helpers
 * @group helpers-hcaptcha
 */
class HCaptchaTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ], $_POST['hcaptcha-widget-id'], $GLOBALS['wp_filters'] );

		hcaptcha()->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test HCaptcha::form().
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_form(): void {
		hcaptcha()->init_hooks();

		self::assertSame( $this->get_hcap_form(), HCaptcha::form() );

		$action = 'some_action';
		$name   = 'some_name';
		$auto   = true;
		$args   = [
			'action' => $action,
			'name'   => $name,
			'auto'   => $auto,
		];
		$id     = [
			'source'  => [ AutoVerify::class ],
			'form_id' => 0,
		];

		self::assertSame(
			$this->get_hcap_form(
				[
					'action' => $action,
					'name'   => $name,
					'auto'   => $auto,
					'id'     => $id,
				]
			),
			HCaptcha::form( $args )
		);
	}

	/**
	 * Test HCaptcha::form_display().
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_form_display(): void {
		self::assertFalse( hcaptcha()->form_shown );

		ob_start();
		HCaptcha::form_display();
		self::assertSame( $this->get_hcap_form(), ob_get_clean() );
		self::assertTrue( hcaptcha()->form_shown );

		$action = 'some_action';
		$name   = 'some_name';
		$auto   = true;
		$args   = [
			'action' => $action,
			'name'   => $name,
			'auto'   => $auto,
		];
		$id     = [
			'source'  => [ AutoVerify::class ],
			'form_id' => 0,
		];

		ob_start();
		HCaptcha::form_display( $args );
		self::assertSame(
			$this->get_hcap_form(
				[
					'action' => $action,
					'name'   => $name,
					'auto'   => $auto,
					'id'     => $id,
				]
			),
			ob_get_clean()
		);

		update_option( 'hcaptcha_settings', [ 'size' => 'invisible' ] );

		hcaptcha()->init_hooks();

		ob_start();
		HCaptcha::form_display( $args );
		self::assertSame(
			$this->get_hcap_form(
				[
					'action' => $action,
					'name'   => $name,
					'auto'   => $auto,
					'size'   => 'invisible',
					'id'     => $id,
				]
			),
			ob_get_clean()
		);

		$args['protect'] = false;

		ob_start();
		HCaptcha::form_display( $args );
		self::assertFalse( strpos( ob_get_clean(), '<h-captcha' ) );
	}

	/**
	 * Test HCaptcha::form_display() with a delay API event.
	 *
	 * @return void
	 */
	public function test_form_display_with_delay_api_event(): void {
		$delay_api_event_filter = static function () {
			return 'hcap-load-api';
		};

		add_filter( 'hcap_delay_api_event', $delay_api_event_filter );

		try {
			ob_start();
			HCaptcha::form_display();

			self::assertSame(
				$this->get_hcap_form(
					[
						'hcaptcha_class' => 'h-captcha hcaptcha-api-delayed',
					]
				),
				ob_get_clean()
			);
		} finally {
			remove_filter( 'hcap_delay_api_event', $delay_api_event_filter );
		}
	}

	/**
	 * Test HCaptcha::get_widget().
	 *
	 * @return void
	 */
	public function test_get_widget(): void {
		$hcaptcha_widget_id = 'hcaptcha-widget-id';
		$id                 = [
			'source'  => [ 'some source' ],
			'form_id' => 123,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_id = base64_encode( wp_json_encode( $id ) );
		$widget_id  = $encoded_id . '-' . wp_hash( HCaptcha::HCAPTCHA_WIDGET_ID . '|' . $encoded_id );
		$expected   = <<<HTML
		<input
				type="hidden"
				class="$hcaptcha_widget_id"
				name="$hcaptcha_widget_id"
				value="$widget_id">\n\t\t
HTML;

		self::assertSame( $expected, HCaptcha::get_widget( $id ) );
	}

	/**
	 * Test HCaptcha::get_signature().
	 *
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_get_signature(): void {
		$class_name         = CF7::class;
		$form_id            = 123;
		$hcaptcha_shown     = true;
		$source             = [ 'contact-form-7/wp-contact-form-7.php' ];
		$hcaptcha_signature = 'hcaptcha-signature';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name              = $hcaptcha_signature . '-' . base64_encode( $class_name );
		$encoded_signature = $this->get_encoded_signature( $class_name, $source, $form_id, $hcaptcha_shown );
		$expected          = <<<HTML
		<input
				type="hidden"
				class="$hcaptcha_signature"
				name="$name"
				value="$encoded_signature">\n\t\t
HTML;

		self::assertSame( $expected, HCaptcha::get_signature( $class_name, $form_id, $hcaptcha_shown ) );
	}

	/**
	 * Test check_signature().
	 *
	 * @return void
	 */
	public function test_check_signature(): void {
		$const      = HCaptcha::HCAPTCHA_SIGNATURE;
		$class_name = 'SomeClass';
		$form_id    = 'some_id';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = $const . '-' . base64_encode( $class_name );

		// False when no signature.
		self::assertFalse( HCaptcha::check_signature( $class_name, $form_id ) );

		$_POST[ $name ] = $this->get_encoded_signature( $class_name, [], $form_id, true );

		// False when wrong form_id.
		self::assertFalse( HCaptcha::check_signature( $class_name, 'wrong_form_id' ) );

		// Null when hCaptcha shown.
		self::assertNull( HCaptcha::check_signature( $class_name, $form_id ) );

		$_POST[ $name ] = $this->get_encoded_signature( $class_name, [], $form_id, false );

		// True when hCaptcha not shown.
		self::assertTrue( HCaptcha::check_signature( $class_name, $form_id ) );

		unset( $_POST[ $name ] );
	}

	/**
	 * Test check_signature() rejects a widget id replayed as a signature.
	 *
	 * @return void
	 */
	public function test_check_signature_rejects_widget_id(): void {
		$class_name = 'SomeClass';
		$form_id    = 'some_id';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( $class_name );

		$_POST[ $name ] = HCaptcha::widget_id_value(
			[
				'source'  => [],
				'form_id' => $form_id,
			]
		);

		self::assertFalse( HCaptcha::check_signature( $class_name, $form_id ) );
	}

	/**
	 * Test check_signature() rejects a signature issued for another class.
	 *
	 * @return void
	 */
	public function test_check_signature_rejects_other_class(): void {
		$class_name       = 'SomeClass';
		$other_class_name = 'OtherClass';
		$form_id          = 'some_id';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( $class_name );

		$_POST[ $name ] = $this->get_encoded_signature( $other_class_name, [], $form_id, false );

		self::assertFalse( HCaptcha::check_signature( $class_name, $form_id ) );
	}

	/**
	 * Test check_signature() rejects a signature without the shown claim.
	 *
	 * @return void
	 */
	public function test_check_signature_rejects_missing_shown_claim(): void {
		$class_name = 'SomeClass';
		$form_id    = 'some_id';
		$id         = [
			'source'  => [],
			'form_id' => $form_id,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( $class_name );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_id     = base64_encode( wp_json_encode( $id ) );
		$_POST[ $name ] = $encoded_id . '-' . wp_hash( $name . '|' . $encoded_id );

		self::assertFalse( HCaptcha::check_signature( $class_name, $form_id ) );
	}

	/**
	 * Test is_protection_enabled().
	 *
	 * @param bool $hash_ok  Hash is not corrupted.
	 * @param bool $filter   Value return by hcap_protect_form filter.
	 * @param bool $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_is_protection_enabled
	 */
	public function test_is_protection_enabled( bool $hash_ok, bool $filter, bool $expected ): void {
		$id = [
			'source'  => [ 'some source' ],
			'form_id' => 123,
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_id = base64_encode( wp_json_encode( $id ) );
		$hash       = wp_hash( HCaptcha::HCAPTCHA_WIDGET_ID . '|' . $encoded_id );

		if ( ! $hash_ok ) {
			$hash = 'broken hash';
		}

		add_filter(
			'hcap_protect_form',
			static function () use ( $filter ) {
				return $filter;
			}
		);

		$_POST['hcaptcha-widget-id'] = $encoded_id . '-' . $hash;

		self::assertSame( $expected, HCaptcha::is_protection_enabled() );
	}

	/**
	 * Data provider for test_is_protection_enabled().
	 *
	 * @return array
	 */
	public function dp_test_is_protection_enabled(): array {
		return [
			[ true, true, true ],
			[ true, false, false ],
			[ false, true, true ],
			[ false, false, true ],
		];
	}

	/**
	 * Test get_widget_id().
	 *
	 * @return void
	 */
	public function test_get_widget_id(): void {
		$default_id = [
			'source'  => [],
			'form_id' => 0,
		];
		$id         = [
			'source' => [ 'some source' ],
		];
		$expected   = [
			'source'  => [ 'some source' ],
			'form_id' => 0,
		];
		$hash       = 'some hash';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_id = base64_encode( wp_json_encode( $id ) );

		self::assertSame( $default_id, HCaptcha::get_widget_id() );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $encoded_id . '-' . $hash;

		self::assertSame( $default_id, HCaptcha::get_widget_id() );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );

		self::assertSame( $expected, HCaptcha::get_widget_id() );
	}

	/**
	 * Test get_hcaptcha_plugin_notice().
	 *
	 * @return void
	 */
	public function test_get_hcaptcha_plugin_notice(): void {
		$expected = [
			'label'       => 'hCaptcha plugin is active',
			'description' => 'When hCaptcha plugin is active and integration is on, hCaptcha settings must be modified on the <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&#038;tab=general" target="_blank">General settings page</a>.',
		];

		if ( version_compare( $GLOBALS['wp_version'], '7.0-RC1', '>=' ) ) {
			$expected['description'] = str_replace( '&#038;', '&amp;', $expected['description'] );
		}

		self::assertSame( $expected, HCaptcha::get_hcaptcha_plugin_notice() );
	}

	/**
	 * Test did_filter().
	 *
	 * @return void
	 */
	public function test_did_filter(): void {
		global $wp_filters;

		$hook_name = 'some-hook';

		self::assertSame( 0, HCaptcha::did_filter( $hook_name ) );

		$number = 5;

		$wp_filters[ $hook_name ] = $number;

		self::assertSame( $number, HCaptcha::did_filter( $hook_name ) );
	}

	/**
	 * Test get_status_source().
	 *
	 * @return void
	 */
	public function test_get_status_source(): void {
		$main             = hcaptcha();
		$original_modules = $main->modules;

		$main->modules = [
			'WP Core feature' => [
				[ 'wp_status', 'anything' ],
				'',
				[],
			],
			'Some plugin'     => [
				[ 'plugin_status', null ],
				'some-plugin/some-plugin.php',
				[],
			],
			'Multi plugin'    => [
				[ 'multi_status', null ],
				[ 'first/first.php', 'second/second.php' ],
				[],
			],
		];

		self::assertSame( [ 'WordPress' ], HCaptcha::get_status_source( 'wp_status' ) );
		self::assertSame( [ 'some-plugin/some-plugin.php' ], HCaptcha::get_status_source( 'plugin_status' ) );
		self::assertSame( [ 'first/first.php', 'second/second.php' ], HCaptcha::get_status_source( 'multi_status' ) );
		self::assertSame( [], HCaptcha::get_status_source( 'unknown_status' ) );

		$main->modules = $original_modules;
	}

	/**
	 * Test get_source_name().
	 *
	 * @return void
	 */
	public function test_get_source_name(): void {
		$main             = hcaptcha();
		$original_modules = $main->modules;

		$main->init_hooks();

		$integrations = $main->settings()->get_tab( Integrations::class );
		self::assertInstanceOf( Integrations::class, $integrations );
		$integrations->init_form_fields();

		$main->modules = [
			'WP Core feature' => [
				[ 'wp_status', 'anything' ],
				'',
				[],
			],
			'bbPress'         => [
				[ 'bbp_status', null ],
				'bbpress/bbpress.php',
				[],
			],
			'ACF Extended'    => [
				[ 'acfe_status', 'form' ],
				[ 'acf-extended-pro/acf-extended.php', 'acf-extended/acf-extended.php' ],
				[],
			],
		];

		self::assertSame( '', HCaptcha::get_source_name( '' ) );
		self::assertSame( '', HCaptcha::get_source_name( 'not-a-json' ) );
		self::assertSame( 'WP Core', HCaptcha::get_source_name( wp_json_encode( [ 'WordPress' ] ) ) );
		self::assertSame( 'bbPress', HCaptcha::get_source_name( wp_json_encode( [ 'bbpress/bbpress.php' ] ) ) );
		self::assertSame( 'ACF Extended', HCaptcha::get_source_name( wp_json_encode( [ 'acf-extended/acf-extended.php' ] ) ) );
		self::assertSame(
			'unknown/unknown.php,another.php',
			HCaptcha::get_source_name( wp_json_encode( [ 'unknown/unknown.php', 'another.php' ] ) )
		);

		$main->modules = $original_modules;
	}

	/**
	 * Test add_error_message().
	 *
	 * @return void
	 */
	public function test_add_error_message(): void {
		self::AssertEquals( new WP_Error(), HCaptcha::add_error_message( 'some', null ) );

		$errors   = new WP_Error( 'some-code', 'Some message' );
		$expected = clone $errors;

		$expected->add( 'bad-nonce', 'Bad hCaptcha nonce!' );

		self::AssertEquals( $expected, HCaptcha::add_error_message( $errors, 'Bad hCaptcha nonce!' ) );

		$expected = clone $errors;

		$expected->add( 'fail', 'Not a registered hCaptcha error message.' );

		self::AssertEquals( $expected, HCaptcha::add_error_message( $errors, 'Not a registered hCaptcha error message.' ) );
	}

	/**
	 * Test css_display().
	 *
	 * @return void
	 */
	public function test_css_display(): void {
		$css      = '.h-captcha { display: inline-block; }';
		$expected = "<style>\n" . '.h-captcha{display:inline-block}' . "\n</style>\n";

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);
		FunctionMocker::replace(
			'constant',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		ob_start();

		HCaptcha::css_display( $css, false );

		$css_display = ob_get_clean();

		self::assertSame( $css . "\n", $css_display );

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' !== $constant_name;
			}
		);
		FunctionMocker::replace(
			'constant',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' !== $constant_name;
			}
		);

		ob_start();

		HCaptcha::css_display( $css );

		$css_display = ob_get_clean();

		self::assertSame( $expected, $css_display );
	}

	/**
	 * Test js_display().
	 *
	 * @return void
	 */
	public function test_js_display(): void {
		$js       = <<<'JS'
	const a = 1;
	console.log( a );
JS;
		$expected = "const a=1;console.log(a)\n";

		// Not wrapped.
		ob_start();
		HCaptcha::js_display( $js, false );
		self::assertSame( $expected, ob_get_clean() );

		$expected_wrapped = "<script>\n" . $expected . "</script>\n";

		// Wrapped.
		ob_start();
		HCaptcha::js_display( $js );
		self::assertSame( $expected_wrapped, ob_get_clean() );

		// Not minified.
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);
		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		ob_start();
		HCaptcha::js_display( $js, false );
		self::assertSame( $js . "\n", ob_get_clean() );
	}

	/**
	 * Test get_hcap_locale().
	 *
	 * @param string $locale   Locale.
	 * @param string $expected Expected value.
	 *
	 * @return void
	 * @dataProvider dp_test_get_hcap_locale
	 */
	public function test_get_hcap_locale( string $locale, string $expected ): void {
		add_filter(
			'locale',
			static function () use ( $locale ) {
				return $locale;
			}
		);

		self::assertSame( $expected, HCaptcha::get_hcap_locale() );
	}

	/**
	 * Data provider for test_get_hcap_locale().
	 *
	 * @return array
	 */
	public function dp_test_get_hcap_locale(): array {
		return [
			[ 'en', 'en' ],
			[ 'en_US', 'en' ],
			[ 'en_UK', 'en' ],
			[ 'zh_CN', 'zh-CN' ],
			[ 'zh_SG', 'zh' ],
			[ 'bal', 'ca' ],
			[ 'hau', 'ha' ],
			[ 'some', '' ],
		];
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 */
	public function test_add_type_module(): void {
		self::assertSame(
			'<script type="module" id="some-id">...</script>',
			HCaptcha::add_type_module( '<script id="some-id">...</script>' )
		);
		self::assertSame(
			'<script type="module" id="some-id">...</script>',
			HCaptcha::add_type_module( '<script type="text/javascript" id="some-id">...</script>' )
		);
	}
	/**
	 * Test form_display() signature hook.
	 *
	 * @return void
	 */
	public function test_form_display_signature_hook(): void {
		$received_args = null;
		$callback      = static function ( array $args ) use ( &$received_args ) {
			$received_args = $args;
		};

		add_action( 'hcap_signature_test', $callback );

		try {
			ob_start();
			HCaptcha::form_display(
				[
					'sign'    => 'test',
					'protect' => false,
				]
			);
			ob_get_clean();
		} finally {
			remove_action( 'hcap_signature_test', $callback );
		}

		self::assertIsArray( $received_args );
		self::assertSame( 'test', $received_args['sign'] );
	}

	/**
	 * Test form_display() falls back to saved theme and size.
	 *
	 * @return void
	 */
	public function test_form_display_invalid_theme_and_size_fallback(): void {
		update_option(
			'hcaptcha_settings',
			[
				'theme' => 'dark',
				'size'  => 'compact',
			]
		);
		hcaptcha()->init_hooks();

		ob_start();
		HCaptcha::form_display(
			[
				'theme' => 'wrong-theme',
				'size'  => 'wrong-size',
			]
		);
		$form = ob_get_clean();

		self::assertStringContainsString( 'data-theme="dark"', $form );
		self::assertStringContainsString( 'data-size="compact"', $form );
	}

	/**
	 * Test original protected get_hp_name().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_hp_name(): void {
		$method = new ReflectionMethod( HCaptcha::class, 'get_hp_name' );

		FunctionMocker::tearDown();
		$method->setAccessible( true );

		try {
			$name = $method->invoke( null );
		} finally {
			$method->setAccessible( false );
			FunctionMocker::setUp();
			FunctionMocker::replace( '\HCaptcha\Helpers\HCaptcha::get_hp_name', 'hcap_hp_test' );
		}

		self::assertStringStartsWith( 'hcap_hp_', $name );
	}

	/**
	 * Test check_signature() with a mismatched source.
	 *
	 * @return void
	 */
	public function test_check_signature_source_mismatch(): void {
		$class_name = CF7::class;
		$form_id    = 123;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( $class_name );

		$_POST[ $name ] = $this->get_encoded_signature( $class_name, [ 'wrong/source.php' ], $form_id, true );

		self::assertFalse( HCaptcha::check_signature( $class_name, $form_id ) );
	}

	/**
	 * Test get_source_name() when the integrations' tab is unavailable.
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_source_name_without_integrations(): void {
		$main              = hcaptcha();
		$original_modules  = $main->modules;
		$original_settings = $this->get_protected_property( $main, 'settings' );

		$main->modules = [
			'bbPress' => [
				[ 'bbp_status', null ],
				'bbpress/bbpress.php',
				[],
			],
		];

		$this->set_protected_property( $main, 'settings', null );

		try {
			self::assertSame(
				'bbpress/bbpress.php',
				HCaptcha::get_source_name( wp_json_encode( [ 'bbpress/bbpress.php' ] ) )
			);
		} finally {
			$this->set_protected_property( $main, 'settings', $original_settings );
			$main->modules = $original_modules;
		}
	}

	/**
	 * Test save_license_level().
	 *
	 * @return void
	 */
	public function test_save_license_level(): void {
		update_option( PluginSettingsBase::OPTION_NAME, [ 'license' => 'old' ] );

		$this->save_license_level_with_response( new WP_Error( 'remote-error', 'Remote failed.' ) );

		self::assertSame( 'old', get_option( PluginSettingsBase::OPTION_NAME )['license'] );

		$this->save_license_level_with_response(
			[
				'body' => wp_json_encode(
					[
						'pass'     => true,
						'features' => [ 'custom_theme' => false ],
					]
				),
			]
		);

		self::assertSame( 'free', get_option( PluginSettingsBase::OPTION_NAME )['license'] );

		$this->save_license_level_with_response(
			[
				'body' => wp_json_encode(
					[
						'pass'     => true,
						'features' => [ 'custom_theme' => true ],
					]
				),
			]
		);

		self::assertSame( 'pro', get_option( PluginSettingsBase::OPTION_NAME )['license'] );
	}

	/**
	 * Save license level with a mocked HTTP response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 *
	 * @return void
	 */
	private function save_license_level_with_response( $response ): void {
		$filter = static function () use ( $response ) {
			return $response;
		};

		add_filter( 'pre_http_request', $filter );

		try {
			HCaptcha::save_license_level();
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}
	}
}
