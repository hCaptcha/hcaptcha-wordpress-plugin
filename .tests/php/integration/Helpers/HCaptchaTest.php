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

/**
 * Test HCaptcha class.
 *
 * @group hcaptcha
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

		self::assertSame( $this->get_hcap_form( $action, $name, $auto ), HCaptcha::form( $args ) );
	}

	/**
	 * Test HCaptcha::form_display().
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_form_display() {
		self::assertFalse( hcaptcha()->form_shown );

		ob_start();
		hCaptcha::form_display();
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
		hCaptcha::form_display( $args );
		self::assertSame( $this->get_hcap_form( $action, $name, $auto ), ob_get_clean() );

		update_option( 'hcaptcha_settings', [ 'size' => 'invisible' ] );

		hcaptcha()->init_hooks();

		ob_start();
		hCaptcha::form_display( $args );
		self::assertSame( $this->get_hcap_form( $action, $name, $auto, true ), ob_get_clean() );
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
}
