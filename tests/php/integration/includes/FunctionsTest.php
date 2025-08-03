<?php
/**
 * FunctionsTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\includes;

use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test functions file.
 *
 * @group functions
 */
class FunctionsTest extends HCaptchaWPTestCase {

	/**
	 * Test hcap_shortcode().
	 *
	 * @param string $action Action name for wp_nonce_field.
	 * @param string $name   Nonce name for wp_nonce_field.
	 * @param string $auto   Auto argument.
	 *
	 * @dataProvider dp_test_hcap_shortcode
	 */
	public function test_hcap_shortcode( string $action, string $name, string $auto ): void {
		$filtered = ' filtered ';

		$form_action = empty( $action ) ? 'hcaptcha_action' : $action;
		$form_name   = empty( $name ) ? 'hcaptcha_nonce' : $name;
		$form_auto   = filter_var( $auto, FILTER_VALIDATE_BOOLEAN );

		$expected =
			$filtered .
			$this->get_hcap_form(
				[
					'action' => $form_action,
					'name'   => $form_name,
					'auto'   => $form_auto,
				]
			);

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
