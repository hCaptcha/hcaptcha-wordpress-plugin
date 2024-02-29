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

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test HCaptcha class.
 *
 * @group helpers
 * @group helpers-hcaptcha
 */
class HCaptchaTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		hcaptcha()->form_shown = false;

		parent::tearDown();
	}

	/**
	 * Test HCaptcha::form().
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_form() {
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

		self::assertSame(
			$this->get_hcap_form(
				[
					'action' => $action,
					'name'   => $name,
					'auto'   => $auto,
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
	public function test_form_display() {
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

		ob_start();
		HCaptcha::form_display( $args );
		self::assertSame(
			$this->get_hcap_form(
				[
					'action' => $action,
					'name'   => $name,
					'auto'   => $auto,
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
				]
			),
			ob_get_clean()
		);
	}

	/**
	 * Test check_signature().
	 *
	 * @return void
	 */
	public function test_check_signature() {
		$const      = HCaptcha::HCAPTCHA_SIGNATURE;
		$class_name = 'SomeClass';
		$form_id    = 'some_id';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = $const . '-' . base64_encode( $class_name );

		// False when no signature.
		self::assertFalse( HCaptcha::check_signature( $class_name, $form_id ) );

		$_POST[ $name ] = $this->get_encoded_signature( [], $form_id, true );

		// False when wrong form_id.
		self::assertFalse( HCaptcha::check_signature( $class_name, 'wrong_form_id' ) );

		// Null when hCaptcha shown.
		self::assertNull( HCaptcha::check_signature( $class_name, $form_id ) );

		$_POST[ $name ] = $this->get_encoded_signature( [], $form_id, false );

		// True when hCaptcha not shown.
		self::assertTrue( HCaptcha::check_signature( $class_name, $form_id ) );

		unset( $_POST[ $name ] );
	}

	/**
	 * Test get_widget_id().
	 *
	 * @return void
	 */
	public function test_get_widget_id() {
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

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$encoded_id = base64_encode( json_encode( $id ) );

		self::assertSame( $default_id, HCaptcha::get_widget_id() );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $encoded_id . '-' . $hash;

		self::assertSame( $expected, HCaptcha::get_widget_id() );
	}

	/**
	 * Test get_hcaptcha_plugin_notice().
	 *
	 * @return void
	 */
	public function test_get_hcaptcha_plugin_notice() {
		$expected = [
			'label'       => 'hCaptcha plugin is active',
			'description' => 'When hCaptcha plugin is active and integration is on, hCaptcha settings must be modified on the <a href="http://test.test/wp-admin/options-general.php?page=hcaptcha&#038;tab=general" target="_blank">General settings page</a>.',
		];

		self::assertSame( $expected, HCaptcha::get_hcaptcha_plugin_notice() );
	}

	/**
	 * Test js_display().
	 *
	 * @return void
	 */
	public function test_js_display() {
		$js       = <<<JS
	var a = 1;
	console.log( a );
JS;
		$expected = "var a=1;console.log(a)\n";

		// Not wrapped.
		ob_start();
		HCaptcha::js_display( $js, false );
		self::assertSame( $expected, ob_get_clean() );

		$expected_wrapped = "<script>\n" . $expected . "</script>\n";

		// Wrapped.
		ob_start();
		HCaptcha::js_display( $js, true );
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
	public function test_get_hcap_locale( string $locale, string $expected ) {
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
}
