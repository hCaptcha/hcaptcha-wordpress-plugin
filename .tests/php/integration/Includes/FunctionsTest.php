<?php
/**
 * FunctionsTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Includes;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test functions file.
 *
 * @group functions
 */
class FunctionsTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
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
	 * Test hcap_shortcode().
	 *
	 * @param string $action Action name for wp_nonce_field.
	 * @param string $name   Nonce name for wp_nonce_field.
	 * @param string $auto   Auto argument.
	 *
	 * @dataProvider dp_test_hcap_shortcode
	 */
	public function test_hcap_shortcode( string $action, string $name, string $auto ) {
		$filtered = ' filtered ';

		$form_action = empty( $action ) ? 'hcaptcha_action' : $action;
		$form_name   = empty( $name ) ? 'hcaptcha_nonce' : $name;
		$form_auto   = filter_var( $auto, FILTER_VALIDATE_BOOLEAN );

		$expected = $filtered . $this->get_hcap_form( $form_action, $form_name, $form_auto );

		hcaptcha()->init_hooks();

		add_filter(
			'hcap_hcaptcha_content',
			static function ( $hcaptcha_content ) use ( $filtered ) {
				return $filtered . $hcaptcha_content;
			}
		);

		$shortcode = '[hcaptcha';

		$shortcode .= empty( $action ) ? '' : ' action="' . $action . '"';
		$shortcode .= empty( $name ) ? '' : ' name="' . $name . '"';
		$shortcode .= empty( $auto ) ? '' : ' auto="' . $auto . '"';

		$shortcode .= ']';

		self::assertSame( $expected, do_shortcode( $shortcode ) );
	}

	/**
	 * Data provider for test_hcap_shortcode().
	 *
	 * @return array
	 */
	public function dp_test_hcap_shortcode(): array {
		return [
			'no arguments'   => [ '', '', '' ],
			'action only'    => [ 'some_action', '', '' ],
			'name only'      => [ '', 'some_name', '' ],
			'with arguments' => [ 'some_action', 'some_name', '' ],
			'auto false'     => [ 'some_action', 'some_name', 'false' ],
			'auto 0'         => [ 'some_action', 'some_name', 'false' ],
			'auto wrong'     => [ 'some_action', 'some_name', 'false' ],
			'auto true'      => [ 'some_action', 'some_name', 'true' ],
			'auto 1'         => [ 'some_action', 'some_name', '1' ],
		];
	}
}
