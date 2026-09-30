<?php
/**
 * HoneypotTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\AntiSpam;

use HCaptcha\AntiSpam\Honeypot;
use HCaptcha\Main;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use WP_Mock;

/**
 * Test Honeypot class.
 *
 * @group antispam
 * @group honeypot
 */
class HoneypotTest extends HCaptchaTestCase {

	/**
	 * Test get_protected_forms() without settings.
	 */
	public function test_get_protected_forms_without_settings(): void {
		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( null );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		self::assertSame(
			[
				'honeypot' => [],
				'fst'      => [],
			],
			Honeypot::get_protected_forms()
		);
	}

	/**
	 * Test get_protected_forms().
	 *
	 * @param bool $honeypot Honeypot status.
	 * @param bool $fst      Form submit time status.
	 *
	 * @dataProvider dp_test_get_protected_forms
	 */
	public function test_get_protected_forms( bool $honeypot, bool $fst ): void {
		$settings = Mockery::mock( Settings::class )->makePartial();
		$settings->shouldReceive( 'is_on' )->with( 'honeypot' )->once()->andReturn( $honeypot );
		$settings->shouldReceive( 'is_on' )->with( 'set_min_submit_time' )->once()->andReturn( $fst );

		$main = Mockery::mock( Main::class )->makePartial();
		$main->shouldReceive( 'settings' )->with()->once()->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		$result = Honeypot::get_protected_forms();

		self::assertSame( $honeypot, [] !== $result['honeypot'] );
		self::assertSame( $fst, [] !== $result['fst'] );
		self::assertSame( $honeypot, isset( $result['honeypot']['wp_status'] ) );
		self::assertSame( $fst, isset( $result['fst']['wp_status'] ) );

		if ( $honeypot ) {
			self::assertContains( 'order_withdrawal', $result['honeypot']['woocommerce_status'] );
			self::assertSame( [ 'login', 'register' ], $result['honeypot']['affiliates_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['asgaros_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['back_in_stock_notifier_status'] );
			self::assertSame( [ 'contact', 'login' ], $result['honeypot']['beaver_builder_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['brizy_status'] );
			self::assertSame(
				[ 'comment', 'contact', 'email_optin', 'login' ],
				$result['honeypot']['divi_builder_status']
			);
			self::assertSame(
				[ 'comment', 'contact', 'email_optin', 'login' ],
				$result['honeypot']['extra_status']
			);
			self::assertSame(
				[ 'contact', 'login', 'lost_pass', 'register' ],
				$result['honeypot']['classified_listing_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['honeypot']['colorlib_customizer_status']
			);
			self::assertSame(
				[ 'checkout', 'login', 'lost_pass', 'register' ],
				$result['honeypot']['easy_digital_downloads_status']
			);
			self::assertSame( [ 'form' ], $result['honeypot']['icegram_express_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['html_forms_status'] );
			self::assertSame( [ 'login', 'register' ], $result['honeypot']['memberpress_status'] );
			self::assertSame(
				[ 'login', 'register' ],
				$result['honeypot']['login_signup_popup_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['honeypot']['profile_builder_status']
			);
			self::assertSame( [ 'protect' ], $result['honeypot']['passster_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['quform_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['simple_download_monitor_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['subscriber_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['supportcandy_status'] );
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['honeypot']['simple_membership_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['honeypot']['learn_dash_status']
			);
			self::assertSame(
				[ 'return_request' ],
				$result['honeypot']['woocommerce_germanized_status']
			);
			self::assertSame(
				[ 'create_list' ],
				$result['honeypot']['woocommerce_wishlists_status']
			);
			self::assertSame(
				[ 'q&a', 'review' ],
				$result['honeypot']['customer_reviews_status']
			);
			self::assertSame( [ 'booking' ], $result['honeypot']['events_manager_status'] );
			self::assertSame(
				[ 'checkout', 'login', 'register' ],
				$result['honeypot']['learn_press_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register', 'signup' ],
				$result['honeypot']['theme_my_login_status']
			);
			self::assertSame(
				[ 'checkout', 'login', 'lost_pass', 'register' ],
				$result['honeypot']['tutor_status']
			);
			self::assertSame(
				[ 'comment_form', 'subscribe_form' ],
				$result['honeypot']['wpdiscuz_status']
			);
			self::assertSame( [ 'new_topic', 'reply' ], $result['honeypot']['wpforo_status'] );
			self::assertSame( [ 'form' ], $result['honeypot']['wp_job_openings_status'] );
			self::assertSame(
				[ 'forgot', 'login', 'register' ],
				$result['honeypot']['users_wp_status']
			);
		}

		if ( $fst ) {
			self::assertContains( 'order_withdrawal', $result['fst']['woocommerce_status'] );
			self::assertSame( [ 'login', 'register' ], $result['fst']['affiliates_status'] );
			self::assertSame( [ 'form' ], $result['fst']['asgaros_status'] );
			self::assertSame( [ 'form' ], $result['fst']['back_in_stock_notifier_status'] );
			self::assertSame( [ 'contact', 'login' ], $result['fst']['beaver_builder_status'] );
			self::assertSame( [ 'form' ], $result['fst']['brizy_status'] );
			self::assertSame(
				[ 'comment', 'contact', 'email_optin', 'login' ],
				$result['fst']['divi_builder_status']
			);
			self::assertSame(
				[ 'comment', 'contact', 'email_optin', 'login' ],
				$result['fst']['extra_status']
			);
			self::assertSame(
				[ 'contact', 'login', 'lost_pass', 'register' ],
				$result['fst']['classified_listing_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['fst']['colorlib_customizer_status']
			);
			self::assertSame(
				[ 'checkout', 'login', 'lost_pass', 'register' ],
				$result['fst']['easy_digital_downloads_status']
			);
			self::assertSame( [ 'form' ], $result['fst']['icegram_express_status'] );
			self::assertSame( [ 'form' ], $result['fst']['html_forms_status'] );
			self::assertSame( [ 'login', 'register' ], $result['fst']['memberpress_status'] );
			self::assertSame(
				[ 'login', 'register' ],
				$result['fst']['login_signup_popup_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['fst']['profile_builder_status']
			);
			self::assertSame( [ 'protect' ], $result['fst']['passster_status'] );
			self::assertSame( [ 'form' ], $result['fst']['quform_status'] );
			self::assertSame( [ 'form' ], $result['fst']['simple_download_monitor_status'] );
			self::assertSame( [ 'form' ], $result['fst']['subscriber_status'] );
			self::assertSame( [ 'form' ], $result['fst']['supportcandy_status'] );
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['fst']['simple_membership_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register' ],
				$result['fst']['learn_dash_status']
			);
			self::assertSame(
				[ 'return_request' ],
				$result['fst']['woocommerce_germanized_status']
			);
			self::assertSame(
				[ 'create_list' ],
				$result['fst']['woocommerce_wishlists_status']
			);
			self::assertSame(
				[ 'q&a', 'review' ],
				$result['fst']['customer_reviews_status']
			);
			self::assertSame( [ 'booking' ], $result['fst']['events_manager_status'] );
			self::assertSame(
				[ 'checkout', 'login', 'register' ],
				$result['fst']['learn_press_status']
			);
			self::assertSame(
				[ 'login', 'lost_pass', 'register', 'signup' ],
				$result['fst']['theme_my_login_status']
			);
			self::assertSame(
				[ 'checkout', 'login', 'lost_pass', 'register' ],
				$result['fst']['tutor_status']
			);
			self::assertSame(
				[ 'comment_form', 'subscribe_form' ],
				$result['fst']['wpdiscuz_status']
			);
			self::assertSame( [ 'new_topic', 'reply' ], $result['fst']['wpforo_status'] );
			self::assertSame( [ 'form' ], $result['fst']['wp_job_openings_status'] );
			self::assertSame(
				[ 'forgot', 'login', 'register' ],
				$result['fst']['users_wp_status']
			);
		}
	}

	/**
	 * Data provider for test_get_protected_forms().
	 *
	 * @return array
	 */
	public function dp_test_get_protected_forms(): array {
		return [
			'both off'      => [ false, false ],
			'honeypot only' => [ true, false ],
			'fst only'      => [ false, true ],
			'both on'       => [ true, true ],
		];
	}
}
