<?php
/**
 * EntryDataTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Helpers;

use HCaptcha\Helpers\EntryData;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use stdClass;

/**
 * Test safe entry field selection.
 */
class EntryDataTest extends HCaptchaWPTestCase {
	/**
	 * Test removal of credentials, verification tokens, and payment data.
	 *
	 * @return void
	 */
	public function test_without_sensitive_fields(): void {
		self::assertSame(
			[
				'email'           => 'person@example.com',
				'preferences'     => [ 'newsletter' => true ],
				'billing_name'    => 'Jane Doe',
				'contact_details' => [ 'message' => 'Hello' ],
				'post_data'       => [ 'address' => 'Riga' ],
			],
			EntryData::without_sensitive_fields(
				[
					'email'           => 'person@example.com',
					'preferences'     => [ 'newsletter' => true ],
					'billing_name'    => 'Jane Doe',
					'user_password'   => 'password-value',
					'checkout_nonce'  => 'nonce-value',
					'payment_method'  => 'payment-value',
					'cardNumber'      => 'card-value',
					'contact_details' => [
						'message'       => 'Hello',
						'api_token'     => 'token-value',
						'security_code' => 'code-value',
						'cvv'           => 'cvv-value',
					],
					'post_data'       => [
						'address'       => 'Riga',
						'user_password' => 'password-value',
					],
					'custom_object'   => new stdClass(),
				]
			)
		);
	}

	/**
	 * Test harmless field names and duplicate labels.
	 *
	 * @return void
	 */
	public function test_preserves_custom_fields(): void {
		$data = [];

		EntryData::add_field( $data, 'Ваше мнение', 'First answer', 'field_1' );
		EntryData::add_field( $data, 'Ваше мнение', 'Second answer', 'field_2' );
		EntryData::add_name( $data, [] );

		self::assertSame(
			[
				'Ваше мнение'           => 'First answer',
				'Ваше мнение [field_2]' => 'Second answer',
			],
			EntryData::without_sensitive_fields( $data )
		);
		self::assertFalse( EntryData::is_sensitive_field( 'postcard_message' ) );
		self::assertFalse( EntryData::is_sensitive_field( 'bankruptcy_opinion' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'cardNumber' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'CCNumber' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'accessToken' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'hcaptcha' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'pass1' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'pass2' ) );
		self::assertTrue( EntryData::is_sensitive_field( 'account_number' ) );
		self::assertTrue( EntryData::is_content_field_type( 'select' ) );
		self::assertFalse( EntryData::is_content_field_type( 'password' ) );
		self::assertSame(
			[
				'Ваше мнение' => [ 'Хорошо', 'Отлично' ],
			],
			EntryData::sanitize_value(
				[
					'Ваше мнение'  => [ 'Хорошо', 'Отлично' ],
					'access_token' => 'secret',
				]
			)
		);
	}

	/**
	 * Test anti-spam entry excludes transport data but keeps custom top-level fields.
	 *
	 * @return void
	 */
	public function test_for_antispam(): void {
		self::assertSame(
			[
				'data'        => [ 'Ваше мнение' => 'Useful feedback' ],
				'preferences' => [ 'newsletter' => true ],
			],
			EntryData::for_antispam(
				[
					'data'               => [
						'Ваше мнение'   => 'Useful feedback',
						'user_password' => 'secret',
					],
					'preferences'        => [ 'newsletter' => true ],
					'post_data'          => [ 'field_42' => 'unclassified secret' ],
					'h-captcha-response' => 'response-token',
				]
			)
		);
	}

	/**
	 * Test aliases, sanitization, name composition, and secret exclusion.
	 *
	 * @return void
	 */
	public function test_from_array(): void {
		$submission = [
			'EMAIL'            => ' donor@example.com ',
			'FNAME'            => '<b>Jane</b>',
			'LNAME'            => 'Doe',
			'Ваше мнение'      => ' Useful <b>feedback</b> ',
			'choices'          => [ 'one', 'two' ],
			'group'            => [ 'A', 'B' ],
			'postcard_message' => 'Hello',
			'password'         => 'do-not-copy',
			'card'             => 'do-not-copy',
			'token'            => 'do-not-copy',
		];

		self::assertSame(
			[
				'email'            => 'donor@example.com',
				'first_name'       => 'Jane',
				'last_name'        => 'Doe',
				'group'            => [ 'A', 'B' ],
				'name'             => 'Jane Doe',
				'Ваше мнение'      => 'Useful feedback',
				'choices'          => [ 'one', 'two' ],
				'postcard_message' => 'Hello',
			],
			EntryData::from_array(
				$submission,
				[
					'email'      => [ 'email', 'EMAIL' ],
					'first_name' => 'FNAME',
					'last_name'  => 'LNAME',
					'group'      => 'group',
				]
			)
		);
	}

	/**
	 * Test that arrays and objects cannot become entry values.
	 *
	 * @return void
	 */
	public function test_from_array_ignores_non_scalar_values(): void {
		self::assertSame(
			[ 'email' => 'valid@example.com' ],
			EntryData::from_array(
				[
					'email'     => [ 'invalid@example.com' ],
					'alt_email' => 'valid@example.com',
					'username'  => new stdClass(),
				],
				[
					'email'    => [ 'email', 'alt_email' ],
					'username' => 'username',
				]
			)
		);
	}

	/**
	 * Test POST input is unslashed before mapping.
	 *
	 * @return void
	 */
	public function test_from_post(): void {
		$_POST['first_name']     = wp_slash( "O'Neil" );
		$_POST['Ваше мнение']    = wp_slash( 'It works' );
		$_POST['preferences']    = [ 'red', 'blue' ];
		$_POST['password']       = 'do-not-copy';
		$_POST['pass1']          = 'do-not-copy';
		$_POST['pass2']          = 'do-not-copy';
		$_POST['payment_method'] = 'do-not-copy';

		self::assertSame(
			[
				'first_name'  => "O'Neil",
				'name'        => "O'Neil",
				'Ваше мнение' => 'It works',
				'preferences' => [ 'red', 'blue' ],
			],
			EntryData::from_post( [ 'first_name' => 'first_name' ] )
		);

		unset( $_POST['first_name'], $_POST['Ваше мнение'], $_POST['preferences'], $_POST['password'], $_POST['pass1'], $_POST['pass2'], $_POST['payment_method'] );
	}
}
