<?php
/**
 * TutorLMSDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\Detectors\TutorLMSDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use WP_Mock;

/**
 * Test TutorLMSDetector class.
 *
 * @group migration-wizard
 */
class TutorLMSDetectorTest extends HCaptchaTestCase {
	/**
	 * Test malformed Tutor settings and locations.
	 */
	public function test_detect_ignores_non_array_options_and_locations(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'tutor_option', [] )
			->andReturn(
				'bad settings',
				[
					'enable_spam_protection'   => 'on',
					'spam_protection_method'   => 'recaptcha_v2',
					'recaptcha_v2_site_key'    => 'site-key',
					'recaptcha_v2_secret_key'  => 'secret-key',
					'spam_protection_location' => 'tutor_login',
				]
			);

		$detector = new TutorLMSDetector();

		self::assertSame( [], $detector->detect() );
		self::assertSame( [], $detector->detect() );
	}


	/**
	 * Test source details and Pro-only applicability.
	 *
	 * @return void
	 */
	public function test_source_and_applicability(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'tutor/tutor.php', 'tutor-pro/tutor-pro.php' ], [ 'tutor/tutor.php' ] );

		$detector = new TutorLMSDetector();

		self::assertSame( 'tutor-pro/tutor-pro.php', $detector->get_source_plugin() );
		self::assertSame( 'Tutor LMS Pro', $detector->get_source_name() );
		self::assertTrue( $detector->is_applicable() );
		self::assertFalse( $detector->is_applicable() );
	}

	/**
	 * Test migration of selected Tutor and WordPress forms.
	 *
	 * @return void
	 */
	public function test_detect_selected_locations(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'tutor_option', [] )
			->andReturn(
				[
					'enable_spam_protection'   => 'on',
					'spam_protection_method'   => 'recaptcha_v3',
					'recaptcha_v3_site_key'    => 'site-key',
					'recaptcha_v3_secret_key'  => 'secret-key',
					'spam_protection_location' => [
						'tutor_login' => 'tutor_login',
						'wp_login'    => 'wp_login',
					],
				]
			);

		$results = ( new TutorLMSDetector() )->detect();

		self::assertCount( 3, $results );
		self::assertSame( 'tutor_login', $results[0]->get_surface() );
		self::assertSame( 'tutor_status', $results[0]->get_hcaptcha_option_key() );
		self::assertSame( 'login', $results[0]->get_hcaptcha_option_value() );
		self::assertTrue( $results[0]->is_migratable() );
		self::assertSame( 'tutor_lost_password', $results[1]->get_surface() );
		self::assertSame( 'lost_pass', $results[1]->get_hcaptcha_option_value() );
		self::assertSame( 'wp_login', $results[2]->get_surface() );
		self::assertSame( 'wp_status', $results[2]->get_hcaptcha_option_key() );
	}

	/**
	 * Test reCAPTCHA v2 and a list of selected forms.
	 *
	 * @return void
	 */
	public function test_detect_recaptcha_v2(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'tutor_option', [] )
			->andReturn(
				[
					'enable_spam_protection'   => true,
					'spam_protection_method'   => 'recaptcha_v2',
					'recaptcha_v2_site_key'    => 'site-key',
					'recaptcha_v2_secret_key'  => 'secret-key',
					'spam_protection_location' => [ 'tutor_registration', 'wp_registration' ],
				]
			);

		$results = ( new TutorLMSDetector() )->detect();

		self::assertSame(
			[ 'tutor_register', 'wp_register' ],
			array_map(
				static function ( $result ) {
					return $result->get_surface();
				},
				$results
			)
		);
	}

	/**
	 * Test disabled fraud protection and Honeypot are ignored.
	 *
	 * @return void
	 */
	public function test_detect_ignores_disabled_and_honeypot(): void {
		$settings = [
			'enable_spam_protection'   => 'off',
			'spam_protection_method'   => 'recaptcha_v2',
			'recaptcha_v2_site_key'    => 'site-key',
			'recaptcha_v2_secret_key'  => 'secret-key',
			'spam_protection_location' => [ 'tutor_login' ],
		];

		WP_Mock::userFunction( 'get_option' )
			->with( 'tutor_option', [] )
			->andReturn(
				$settings,
				array_merge(
					$settings,
					[
						'enable_spam_protection' => 'on',
						'spam_protection_method' => 'honeypot',
					]
				)
			);

		$detector = new TutorLMSDetector();

		self::assertSame( [], $detector->detect() );
		self::assertSame( [], $detector->detect() );
	}

	/**
	 * Test unconfigured keys and locations are ignored.
	 *
	 * @return void
	 */
	public function test_detect_ignores_incomplete_settings(): void {
		$settings = [
			'enable_spam_protection'   => 'on',
			'spam_protection_method'   => 'recaptcha_v2',
			'recaptcha_v2_site_key'    => 'site-key',
			'recaptcha_v2_secret_key'  => '',
			'recaptcha_v3_site_key'    => 'other-site-key',
			'recaptcha_v3_secret_key'  => 'other-secret-key',
			'spam_protection_location' => [ 'tutor_login' ],
		];

		WP_Mock::userFunction( 'get_option' )
			->with( 'tutor_option', [] )
			->andReturn(
				$settings,
				array_merge(
					$settings,
					[
						'recaptcha_v2_secret_key'  => 'secret-key',
						'spam_protection_location' => [],
					]
				)
			);

		$detector = new TutorLMSDetector();

		self::assertSame( [], $detector->detect() );
		self::assertSame( [], $detector->detect() );
	}
}
