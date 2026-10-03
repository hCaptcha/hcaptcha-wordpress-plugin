<?php
/**
 * LoginBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Abstracts;

use HCaptcha\Abstracts\LoginBase;
use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\LoginAttempts;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\Wordfence\General as WordfenceGeneral;
use HCaptcha\WP\Login as WPLogin;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;
use WP_User;
use HCaptcha\Affiliates\Login;
use HCaptcha\WP\LoginOut;
use HCaptcha\FluentForm\Form;

/**
 * Test LoginBase class.
 */
class LoginBaseTest extends HCaptchaWPTestCase {

	/**
	 * Clear the shared login state after each case.
	 */
	public function tearDown(): void {
		LoginAttempts::delete_all();
		delete_transient( AutoVerify::TRANSIENT );

		parent::tearDown();
	}

	/**
	 * Test a real same-class signature against the current server-side state.
	 *
	 * @param string $class_name Integration class name.
	 *
	 * @dataProvider dp_test_signature_verifier_skip_policy
	 *
	 * @return void
	 */
	public function test_replayed_signature_uses_current_state( string $class_name ): void {
		$this->prepare_login_request();

		$subject = new $class_name();
		$user    = new WP_User( 1 );
		$ip      = '203.0.113.80';
		$store   = new LoginAttempts();
		$this->set_protected_property( $subject, 'ip', $ip );
		$this->submit_signature( $subject );

		self::assertTrue( HCaptcha::check_signature( $class_name, 'login' ) );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$subject->login_failed( 'test-user' );
		self::assertSame( 1, $store->read( $ip, time() ) );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		// An old routing signature cannot restore a threshold-dependent exemption.
		$future = time() + 2 * DAY_IN_SECONDS;
		FunctionMocker::replace( 'time', $future );
		$store->increment( $ip, $future, 15 * MINUTE_IN_SECONDS );
		self::assertTrue( HCaptcha::check_signature( $class_name, 'login' ) );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		// The same form in another requester context uses that requester's state.
		$other_ip = '203.0.113.81';
		$this->set_protected_property( $subject, 'ip', $other_ip );
		$store->increment( $other_ip, $future, 15 * MINUTE_IN_SECONDS );
		$this->set_protected_property(
			$subject,
			'login_attempts_reset_token',
			$store->get_reset_token( $other_ip, $future )
		);
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$subject->login( 'test-user', $user );
		self::assertSame( 0, $store->read( $other_ip, $future ) );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$store->increment( $other_ip, $future - 16 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$settings                = get_option( 'hcaptcha_settings' );
		$settings['login_limit'] = 0;
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Test signed sibling handlers defer to the owner without suppressing its verification.
	 *
	 * @param string $class_name Integration class name.
	 *
	 * @dataProvider dp_test_signature_verifier_skip_policy
	 *
	 * @return void
	 */
	public function test_sibling_handlers_require_owner_verification( string $class_name ): void {
		$this->prepare_login_request( 0 );

		$sibling = new $class_name();
		$owner   = WPLogin::class === $class_name ? new \HCaptcha\ElementorPro\Login() : new WPLogin();
		$user    = new WP_User( 1 );
		$this->submit_signature( $sibling );
		$this->set_protected_property( $owner, 'hcaptcha_shown', true );
		$this->submit_signature( $owner );

		// A signed owner claim with no challenge still has to fail in the filter chain.
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => HCaptcha::get_class_source( get_class( $owner ) ),
				'form_id' => 'login',
			]
		);
		$this->verify_auto_delegation();

		$verification_count = 0;
		add_filter(
			'hcap_verify_request',
			static function ( $result ) use ( &$verification_count ) {
				++$verification_count;

				return $result;
			}
		);

		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
		self::assertSame( 1, $verification_count );

		// Reversing callback order must preserve the owner's error and success.
		remove_filter( 'wp_authenticate_user', [ $sibling, 'check_signature' ], PHP_INT_MAX );
		add_filter( 'wp_authenticate_user', [ $sibling, 'check_signature' ], PHP_INT_MAX, 2 );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
		unset( $_POST['h-captcha-response'] );
		hcaptcha()->has_result = false;
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Test invalid signatures fail even below the adaptive threshold.
	 *
	 * @param string $variant Invalid signature case.
	 *
	 * @dataProvider dp_test_invalid_signature
	 *
	 * @return void
	 */
	public function test_invalid_signature( string $variant ): void {
		$this->prepare_login_request();

		$subject = new \HCaptcha\ElementorPro\Login();
		$name    = $this->submit_signature( $subject );
		$id      = HCaptcha::decode_id_info( $name )['id'];

		switch ( $variant ) {
			case 'missing':
				unset( $_POST[ $name ] );
				break;
			case 'malformed':
				$_POST[ $name ] = 'malformed';
				break;
			case 'tampered':
				$_POST[ $name ] .= 'tampered';
				break;
			case 'wrong-class':
				$_POST[ $name ] = $this->get_encoded_signature( WPLogin::class, [ 'WordPress' ], 'login', false );
				break;
			default:
				$id[ $variant ] = 'hcaptcha_shown' === $variant ? 0 : 'wrong';
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				$encoded        = base64_encode( wp_json_encode( $id ) );
				$_POST[ $name ] = $encoded . '-' . wp_hash( $name . '|' . $encoded );
		}

		$result = apply_filters( 'wp_authenticate_user', new WP_User( 1 ), 'password' );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'bad-signature', $result->get_error_code() );
	}

	/**
	 * Test a removed owner cannot authorize sibling or automatic verification skips.
	 *
	 * @return void
	 */
	public function test_removed_owner_cannot_authorize_skip(): void {
		$this->prepare_login_request( 0 );

		$sibling = new \HCaptcha\ElementorPro\Login();
		$owner   = new WPLogin();
		$this->submit_signature( $sibling );
		$this->set_protected_property( $owner, 'hcaptcha_shown', true );
		$this->submit_signature( $owner );
		$id                                    = [
			'source'  => [ 'WordPress' ],
			'form_id' => 'login',
		];
		$widget_id                             = HCaptcha::widget_id_value( $id );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $widget_id;
		remove_filter( 'wp_authenticate_user', [ $owner, 'check_signature' ], PHP_INT_MAX );

		self::assertFalse( $owner->allow_wp_login_skip_verification( false ) );
		self::assertSame(
			[],
			$owner->defer_auto_verification( [], (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ), $widget_id )
		);
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', new WP_User( 1 ), 'password' ) );
	}

	/**
	 * Data provider for test_invalid_signature().
	 *
	 * @return array
	 */
	public function dp_test_invalid_signature(): array {
		return array_map(
			static function ( $variant ) {
				return [ $variant ];
			},
			[ 'missing', 'malformed', 'tampered', 'wrong-class', 'form_id', 'source', 'hcaptcha_shown' ]
		);
	}

	/**
	 * Test integrations with their own verification routes do not register signed delegation.
	 *
	 * @param string $class_name Integration class name.
	 * @param string $hook       Verification hook.
	 *
	 * @dataProvider dp_test_separate_verification_route
	 *
	 * @return void
	 */
	public function test_separate_verification_route( string $class_name, string $hook ): void {
		$subject = new $class_name();

		self::assertFalse( has_filter( 'wp_authenticate_user', [ $subject, 'check_signature' ] ) );
		self::assertFalse( has_filter( 'hcap_wp_login_can_skip_verification', [ $subject, 'allow_wp_login_skip_verification' ] ) );
		self::assertSame( 10, has_filter( $hook, [ $subject, 'verify' ] ) );
	}

	/**
	 * Data provider for test_separate_verification_route().
	 *
	 * These remaining LoginBase login integrations verify through their own
	 * hooks. Their dedicated integration suites cover those routes.
	 *
	 * @return array
	 */
	public function dp_test_separate_verification_route(): array {
		return [
			'beaver-builder'  => [ \HCaptcha\BeaverBuilder\Login::class, 'wp_authenticate_user' ],
			'ultimate-addons' => [ \HCaptcha\UltimateAddons\Login::class, 'wp_authenticate_user' ],
			'ultimate-member' => [ \HCaptcha\UM\Login::class, 'um_submit_form_errors_hook_login' ],
			'fluent-forms'    => [ Form::class, 'fluentform/validation_errors' ],
		];
	}

	/**
	 * Test integrations registering the shared signature verifier use a hardened skip policy.
	 *
	 * @param string $class_name      Integration class name.
	 * @param string $declaring_class Class declaring the skip policy.
	 *
	 * @dataProvider dp_test_signature_verifier_skip_policy
	 *
	 * @return void
	 */
	public function test_signature_verifier_skip_policy( string $class_name, string $declaring_class ): void {
		$subject = new $class_name();
		$method  = new ReflectionMethod( $class_name, 'can_skip_login_verification' );

		self::assertSame( PHP_INT_MAX, has_filter( 'wp_authenticate_user', [ $subject, 'check_signature' ] ) );
		self::assertSame( $declaring_class, $method->getDeclaringClass()->getName() );
	}

	/**
	 * Data provider for test_signature_verifier_skip_policy().
	 *
	 * Enumerates concrete integrations that call LoginBase::init_hooks() and
	 * therefore route wp_authenticate_user through the signed login verifier.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function dp_test_signature_verifier_skip_policy(): array {
		$base = LoginBase::class;

		return [
			'affiliates'             => [ Login::class, $base ],
			'bbpress'                => [ \HCaptcha\BBPress\Login::class, $base ],
			'classified-listing'     => [ \HCaptcha\ClassifiedListing\Login::class, $base ],
			'divi'                   => [ \HCaptcha\Divi\Login::class, $base ],
			'elementor-pro'          => [ \HCaptcha\ElementorPro\Login::class, $base ],
			'essential-addons'       => [ \HCaptcha\EssentialAddons\Login::class, $base ],
			'learndash'              => [ \HCaptcha\LearnDash\Login::class, $base ],
			'learnpress'             => [ \HCaptcha\LearnPress\Login::class, $base ],
			'login-signup-popup'     => [ \HCaptcha\LoginSignupPopup\Login::class, $base ],
			'maintenance'            => [ \HCaptcha\Maintenance\Login::class, $base ],
			'memberpress'            => [ \HCaptcha\MemberPress\Login::class, $base ],
			'profile-builder'        => [ \HCaptcha\ProfileBuilder\Login::class, $base ],
			'simple-membership'      => [ \HCaptcha\SimpleMembership\Login::class, $base ],
			'theme-my-login'         => [ \HCaptcha\ThemeMyLogin\Login::class, $base ],
			'tutor'                  => [ \HCaptcha\Tutor\Login::class, $base ],
			'users-wp'               => [ \HCaptcha\UsersWP\Login::class, $base ],
			'woocommerce'            => [ \HCaptcha\WC\Login::class, $base ],
			'wordpress-login-out'    => [ LoginOut::class, $base ],
			'wordpress-native-login' => [ WPLogin::class, WPLogin::class ],
		];
	}

	/**
	 * Test the native form follows its own setting with other login integrations loaded.
	 *
	 * @param bool      $wp_enabled        Native login protection.
	 * @param bool      $elementor_enabled Elementor login protection.
	 * @param bool      $divi_enabled      Divi login protection.
	 * @param bool      $bbpress_enabled   bbPress login protection.
	 * @param bool      $wc_enabled        WooCommerce login protection.
	 * @param bool|null $wordfence_enabled Wordfence login protection; null when Wordfence is absent.
	 *
	 * @dataProvider dp_test_native_login_settings
	 *
	 * @return void
	 */
	public function test_native_login_settings(
		bool $wp_enabled,
		bool $elementor_enabled,
		bool $divi_enabled,
		bool $bbpress_enabled,
		bool $wc_enabled,
		?bool $wordfence_enabled = null
	): void {
		$this->prepare_login_request( 0 );
		$settings = [
			'login_limit'          => 0,
			'wp_status'            => $wp_enabled ? [ 'login' ] : [],
			'elementor_pro_status' => $elementor_enabled ? [ 'login' ] : [],
			'divi_status'          => $divi_enabled ? [ 'login' ] : [],
			'bbp_status'           => $bbpress_enabled ? [ 'login' ] : [],
			'woocommerce_status'   => $wc_enabled ? [ 'login' ] : [],
			'wordfence_status'     => $wordfence_enabled ? [ 'login' ] : [],
		];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();
		remove_all_filters( 'wp_authenticate_user' );
		remove_all_filters( 'hcap_wp_login_can_skip_verification' );
		remove_all_actions( 'login_form' );

		new \HCaptcha\ElementorPro\Login();
		new \HCaptcha\Divi\Login();
		new \HCaptcha\BBPress\Login();

		if ( $wc_enabled ) {
			new \HCaptcha\WC\Login();
		}

		$wp_login = null;

		if ( $wp_enabled ) {
			$wp_login = new WPLogin();
			new LoginOut();
		}

		$this->configure_wordfence_login_protection( $wp_login, $wordfence_enabled );

		ob_start();
		do_action( 'login_form' );
		$form = (string) ob_get_clean();
		$this->submit_form_signatures( $form );
		$user   = new WP_User( 1 );
		$result = apply_filters( 'wp_authenticate_user', $user, 'password' );

		if ( ! $wp_enabled || false === $wordfence_enabled ) {
			self::assertStringNotContainsString( '<h-captcha', $form );
			self::assertSame( $user, $result );

			return;
		}

		self::assertStringContainsString( '<h-captcha', $form );
		self::assertInstanceOf( WP_Error::class, $result );

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			]
		);
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Data provider for test_native_login_settings().
	 *
	 * @return array
	 */
	public function dp_test_native_login_settings(): array {
		return [
			'all-disabled'                   => [ false, false, false, false, false ],
			'woocommerce-only'               => [ false, false, false, false, true ],
			'elementor-only'                 => [ false, true, false, false, false ],
			'divi-only'                      => [ false, false, true, false, false ],
			'bbpress-only'                   => [ false, false, false, true, false ],
			'all-third-party'                => [ false, true, true, true, true ],
			'native-only'                    => [ true, false, false, false, false ],
			'all-enabled'                    => [ true, true, true, true, true ],
			'native-and-wc'                  => [ true, false, false, false, true ],
			'wordfence-on-native-and-wc'     => [ true, false, false, false, true, true ],
			'wordfence-off-native-only'      => [ true, false, false, false, false, false ],
			'wordfence-off-all-integrations' => [ true, true, true, true, true, false ],
			'wordfence-on-native-disabled'   => [ false, true, true, true, true, true ],
		];
	}

	/**
	 * Test a native signature loses its exemption when Wordfence protection is enabled.
	 *
	 * @return void
	 */
	public function test_wordfence_native_signature_rechecks_protection(): void {
		$this->prepare_login_request();
		remove_all_actions( 'login_form' );
		$wp_login = new WPLogin();
		new LoginOut();
		$sibling = new \HCaptcha\WC\Login();
		$this->configure_wordfence_login_protection( $wp_login, false );
		$user = new WP_User( 1 );

		ob_start();
		do_action( 'login_form' );
		$form = (string) ob_get_clean();
		self::assertStringNotContainsString( '<h-captcha', $form );
		$this->submit_form_signatures( $form );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
		$sibling->login_failed( 'test-user' );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$settings                     = get_option( 'hcaptcha_settings' );
		$settings['wordfence_status'] = [ 'login' ];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->settings()->init();

		// Restore the native hooks as a new request with protection enabled would do.
		add_action( 'login_form', [ $wp_login, 'add_captcha' ] );
		add_filter( 'wp_authenticate_user', [ $wp_login, 'check_signature' ], PHP_INT_MAX, 2 );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		ob_start();
		do_action( 'login_form' );
		$form = (string) ob_get_clean();
		self::assertStringContainsString( '<h-captcha', $form );
		$this->submit_form_signatures( $form );
		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'login',
			]
		);
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Test disabling native protection through Wordfence leaves frontend protection active.
	 *
	 * @return void
	 */
	public function test_wordfence_does_not_disable_frontend_protection(): void {
		$this->prepare_login_request( 0 );
		remove_all_actions( 'hcap_signature' );
		$wp_login = new WPLogin();
		$owner    = new \HCaptcha\ElementorPro\Login();
		$this->configure_wordfence_login_protection( $wp_login, false );
		$user = new WP_User( 1 );

		ob_start();
		$owner->add_captcha();
		do_action( 'hcap_signature', 'elementor-login' );
		$this->submit_form_signatures( (string) ob_get_clean() );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		$this->prepare_verify_post( 'hcaptcha_login_nonce', 'hcaptcha_login' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => HCaptcha::get_class_source( get_class( $owner ) ),
				'form_id' => 'login',
			]
		);
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Test Wordfence cannot exempt requests without a valid signed native context.
	 *
	 * @param string $variant Signature variant.
	 *
	 * @dataProvider dp_test_wordfence_invalid_native_signature
	 *
	 * @return void
	 */
	public function test_wordfence_invalid_native_signature( string $variant ): void {
		$this->prepare_login_request( 0 );
		remove_all_actions( 'login_form' );
		$wp_login = new WPLogin();
		$sibling  = new \HCaptcha\WC\Login();
		$this->configure_wordfence_login_protection( $wp_login, false );

		ob_start();
		do_action( 'login_form' );
		$this->submit_form_signatures( (string) ob_get_clean() );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$name = HCaptcha::HCAPTCHA_SIGNATURE . '-' . base64_encode( get_class( $sibling ) );

		if ( 'missing' === $variant ) {
			unset( $_POST[ $name ] );
		} elseif ( 'tampered' === $variant ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Mutate the signature generated by this test.
			$_POST[ $name ] .= 'tampered';
		} else {
			$this->submit_signature( $sibling );
		}

		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', new WP_User( 1 ), 'password' ) );
	}

	/**
	 * Data provider for test_wordfence_invalid_native_signature().
	 *
	 * @return array
	 */
	public function dp_test_wordfence_invalid_native_signature(): array {
		return [
			'missing'  => [ 'missing' ],
			'tampered' => [ 'tampered' ],
			'legacy'   => [ 'legacy' ],
		];
	}

	/**
	 * Test a signed frontend context uses its current setting even with native protection enabled.
	 *
	 * @param string $context Signed rendering context.
	 * @param string $setting Login protection setting.
	 *
	 * @dataProvider dp_test_frontend_login_context
	 *
	 * @return void
	 */
	public function test_frontend_login_context_rechecks_setting( string $context, string $setting ): void {
		$this->prepare_login_request( 0 );
		$settings             = get_option( 'hcaptcha_settings' );
		$settings[ $setting ] = [];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->settings()->init();
		remove_all_actions( 'hcap_signature' );
		new WPLogin();
		new \HCaptcha\WC\Login();
		new \HCaptcha\ElementorPro\Login();
		new \HCaptcha\Divi\Login();

		ob_start();
		do_action( 'hcap_signature', $context );
		$this->submit_form_signatures( (string) ob_get_clean() );
		$user = new WP_User( 1 );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );

		// A previously disabled form does not retain an exemption after protection is enabled.
		$settings[ $setting ] = [ 'login' ];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->settings()->init();
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Data provider for test_frontend_login_context_rechecks_setting().
	 *
	 * @return array
	 */
	public function dp_test_frontend_login_context(): array {
		return [
			'elementor'    => [ 'elementor-login', 'elementor_pro_status' ],
			'divi'         => [ 'divi-login', 'divi_status' ],
			'divi-builder' => [ 'divi_builder-login', 'divi_builder_status' ],
			'extra'        => [ 'extra-login', 'extra_status' ],
		];
	}

	/**
	 * Test a disabled integration cannot authorize delegation with a harvested signature.
	 *
	 * @return void
	 */
	public function test_disabled_owner_cannot_authorize_skip(): void {
		$this->prepare_login_request( 0 );
		$settings                         = get_option( 'hcaptcha_settings' );
		$settings['elementor_pro_status'] = [];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->settings()->init();
		$owner   = new \HCaptcha\ElementorPro\Login();
		$sibling = new WPLogin();
		$this->set_protected_property( $owner, 'hcaptcha_shown', true );
		$this->submit_signature( $owner );
		$this->submit_signature( $sibling );

		self::assertFalse( $owner->allow_wp_login_skip_verification( false ) );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', new WP_User( 1 ), 'password' ) );
	}

	/**
	 * Test a native signature cannot bypass an enabled native login after crossing the threshold.
	 *
	 * @return void
	 */
	public function test_native_signature_rechecks_current_settings_and_threshold(): void {
		$this->prepare_login_request();
		remove_all_actions( 'login_form' );
		$subject = new WPLogin();
		$user    = new WP_User( 1 );
		ob_start();
		do_action( 'login_form' );
		$this->submit_form_signatures( (string) ob_get_clean() );

		self::assertTrue( HCaptcha::check_signature( WPLogin::class, 'wp-login' ) );
		self::assertSame( $user, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
		$subject->login_failed( 'test-user' );
		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', $user, 'password' ) );
	}

	/**
	 * Test a tampered native context cannot turn a protected frontend signature into an exemption.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_tampered_native_context_is_rejected(): void {
		$this->prepare_login_request( 0 );
		$settings              = get_option( 'hcaptcha_settings' );
		$settings['wp_status'] = [];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->settings()->init();
		$subject = new \HCaptcha\ElementorPro\Login();
		$name    = $this->submit_signature( $subject );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Mutate the signature generated by this test.
		$value = explode( '-', $_POST[ $name ], 2 );
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$id             = json_decode( base64_decode( $value[0] ), true );
		$id['form_id']  = 'wp-login';
		$_POST[ $name ] = base64_encode( wp_json_encode( $id ) ) . '-' . $value[1];
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		self::assertInstanceOf( WP_Error::class, apply_filters( 'wp_authenticate_user', new WP_User( 1 ), 'password' ) );
	}

	/**
	 * Configure Wordfence and apply its real native hook removal when disabled.
	 *
	 * @param WPLogin|null $wp_login          Native login integration.
	 * @param bool|null    $wordfence_enabled Wordfence login protection; null when Wordfence is absent.
	 *
	 * @return void
	 */
	private function configure_wordfence_login_protection( ?WPLogin $wp_login, ?bool $wordfence_enabled ): void {
		if ( null === $wordfence_enabled ) {
			return;
		}

		$wordfence = new WordfenceGeneral();

		if ( $wordfence_enabled || ! $wp_login ) {
			return;
		}

		$main                                 = hcaptcha();
		$loaded_classes                       = $this->get_protected_property( $main, 'loaded_classes' );
		$registered_classes                   = $loaded_classes;
		$registered_classes[ WPLogin::class ] = $wp_login;
		$this->set_protected_property( $main, 'loaded_classes', $registered_classes );
		$wordfence->remove_wp_login_hcaptcha_hooks();
		$this->set_protected_property( $main, 'loaded_classes', $loaded_classes );
	}

	/**
	 * Prepare the real shared authentication hook with a bounded adaptive state.
	 *
	 * @param int $limit Login limit.
	 *
	 * @return void
	 */
	private function prepare_login_request( int $limit = 1 ): void {
		update_option(
			'hcaptcha_settings',
			[
				'login_limit'          => $limit,
				'login_interval'       => 15,
				'wp_status'            => [ 'login' ],
				'elementor_pro_status' => [ 'login' ],
				'divi_status'          => [ 'login' ],
				'bbp_status'           => [ 'login' ],
			]
		);
		add_filter(
			'pre_option_template',
			static function () {
				return 'Divi';
			}
		);
		hcaptcha()->init_hooks();
		remove_all_filters( 'wp_authenticate_user' );
		remove_all_filters( 'hcap_wp_login_can_skip_verification' );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_actions']['login_init']           = 1;
		$GLOBALS['wp_actions']['login_form_login']     = 1;
		$GLOBALS['wp_filters']['login_link_separator'] = 1;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Run AutoVerify on a colliding endpoint and require delegation without verification.
	 *
	 * @return void
	 */
	private function verify_auto_delegation(): void {
		$login_path                = (string) wp_parse_url( wp_login_url(), PHP_URL_PATH );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $login_path;
		$_POST['log']              = 'test-user';
		$_POST['pwd']              = 'password';
		$id                        = [
			'source'  => [ 'bbpress/bbpress.php' ],
			'form_id' => 'lost_password',
		];
		set_transient(
			AutoVerify::TRANSIENT,
			[
				untrailingslashit( $login_path ) => [
					[
						'inputs'    => [ 'user_login' ],
						'args'      => [ 'id' => $id ],
						'widget_id' => HCaptcha::widget_id_value( $id ),
					],
				],
			]
		);
		add_filter(
			'wp_die_handler',
			static function () {
				return static function () {
					self::fail( 'AutoVerify must defer to the signed login owner.' );
				};
			}
		);

		( new AutoVerify() )->verify();

		self::assertFalse( hcaptcha()->has_result );
	}

	/**
	 * Submit the actual rendered signature without replacing signature validation.
	 *
	 * @param LoginBase $subject Login integration.
	 *
	 * @return string Field name.
	 */
	private function submit_signature( LoginBase $subject ): string {
		ob_start();
		$subject->display_signature();
		preg_match( '/name="([^"]+)"\s+value="([^"]+)"/', (string) ob_get_clean(), $matches );
		self::assertCount( 3, $matches );

		$name           = html_entity_decode( $matches[1], ENT_QUOTES );
		$_POST[ $name ] = html_entity_decode( $matches[2], ENT_QUOTES );

		return $name;
	}

	/**
	 * Submit all signatures from a rendered login form.
	 *
	 * @param string $form Form HTML.
	 *
	 * @return void
	 */
	private function submit_form_signatures( string $form ): void {
		preg_match_all( '/name="(hcaptcha-signature-[^"]+)"\s+value="([^"]+)"/', $form, $matches, PREG_SET_ORDER );
		self::assertNotEmpty( $matches );

		foreach ( $matches as $match ) {
			$_POST[ html_entity_decode( $match[1], ENT_QUOTES ) ] = html_entity_decode( $match[2], ENT_QUOTES );
		}
	}
}
