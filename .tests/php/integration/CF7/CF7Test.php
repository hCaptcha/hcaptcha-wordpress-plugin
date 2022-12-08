<?php
/**
 * CF7Test class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\CF7;

use HCaptcha\CF7\CF7;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WPCF7_Submission;
use WPCF7_Validation;

/**
 * Test CF7 class.
 *
 * @group cf7
 */
class CF7Test extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'contact-form-7/wp-contact-form-7.php';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		hcaptcha()->form_shown = false;

		wp_deregister_script( 'hcaptcha-script' );
		wp_dequeue_script( 'hcaptcha-script' );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 */
	public function test_init_hooks() {
		$subject = new CF7();

		self::assertSame( 10, has_filter( 'wpcf7_form_elements', [ $subject, 'wpcf7_form_elements' ] ) );
		self::assertTrue( shortcode_exists( 'cf7-hcaptcha' ) );
		self::assertSame( 20, has_filter( 'wpcf7_validate', [ $subject, 'verify_hcaptcha' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test hcap_cf7_wpcf7_form_elements().
	 *
	 * @param string $hcaptcha_size Widget size/visibility.
	 *
	 * @dataProvider dp_test_hcap_cf7_wpcf7_form_elements
	 */
	public function test_hcap_cf7_wpcf7_form_elements( $hcaptcha_size ) {
		$form =
			'<form>' .
			'<input type="submit" value="Send">' .
			'</form>';

		$uniqid = 'hcap_cf7-6004092a854114.24546665';

		$nonce = wp_nonce_field( 'wp_rest', '_wpnonce', true, false );

		$hcaptcha_site_key = 'some site key';
		$hcaptcha_theme    = 'some theme';

		update_option(
			'hcaptcha_settings',
			[
				'site_key' => $hcaptcha_site_key,
				'theme'    => $hcaptcha_theme,
				'size'     => $hcaptcha_size,
			]
		);

		hcaptcha()->init_hooks();

		FunctionMocker::replace(
			'uniqid',
			static function ( $prefix, $more_entropy ) use ( $uniqid ) {
				if ( 'hcap_cf7-' === $prefix && $more_entropy ) {
					return $uniqid;
				}

				return null;
			}
		);

		$callback = 'invisible' === $hcaptcha_size ? '" data-callback="hCaptchaSubmit' : '';

		$expected =
			'<form>' .
			'<span class="wpcf7-form-control-wrap" data-name="hcap-cf7">' .
			'<span id="' . $uniqid .
			'" class="wpcf7-form-control h-captcha" data-sitekey="' . $hcaptcha_site_key .
			'" data-theme="' . $hcaptcha_theme .
			$callback .
			'" data-size="' . $hcaptcha_size . '">' .
			'</span>' .
			'</span>' .
			$nonce .
			'<input type="submit" value="Send">' .
			'</form>';

		$subject = new CF7();

		self::assertSame( $expected, $subject->wpcf7_form_elements( $form ) );

		$form     = str_replace( '<input', '[cf7-hcaptcha]<input', $form );
		$expected = str_replace( '<br>', '', $expected );

		self::assertSame( $expected, $subject->wpcf7_form_elements( $form ) );
	}

	/**
	 * Data provide for test_hcap_cf7_wpcf7_form_elements().
	 *
	 * @return array
	 */
	public function dp_test_hcap_cf7_wpcf7_form_elements() {
		return [
			'visible'   => [ 'normal' ],
			'invisible' => [ 'invisible' ],
		];
	}

	/**
	 * Test hcap_cf7_verify_recaptcha().
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcap_cf7_verify_recaptcha() {
		$data              = [ 'h-captcha-response' => 'some response' ];
		$wpcf7_id          = 23;
		$hcaptcha_site_key = 'some site key';
		$cf7_text          =
			'<form>' .
			'<input type="submit" value="Send">' .
			$hcaptcha_site_key .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			static function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		update_option( 'hcaptcha_settings', [ 'site_key' => $hcaptcha_site_key ] );

		hcaptcha()->init_hooks();

		$this->prepare_hcaptcha_request_verify( $data['h-captcha-response'] );

		$result = Mockery::mock( WPCF7_Validation::class );
		$tag    = Mockery::mock( WPCF7_FormTag::class );

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without submission.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcap_cf7_verify_recaptcha_without_submission() {
		$result = Mockery::mock( WPCF7_Validation::class );
		$result->shouldReceive( 'invalidate' )->with(
			[
				'type' => 'hcaptcha',
				'name' => 'hcap-cf7',
			],
			'Please complete the hCaptcha.'
		);

		$tag = Mockery::mock( WPCF7_FormTag::class );

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without posted data.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcap_cf7_verify_recaptcha_without_posted_data() {
		$data       = [];
		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		$result = Mockery::mock( WPCF7_Validation::class );
		$result->shouldReceive( 'invalidate' )->with(
			[
				'type' => 'hcaptcha',
				'name' => 'hcap-cf7',
			],
			'Please complete the hCaptcha.'
		);

		$tag = Mockery::mock( WPCF7_FormTag::class );

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without site key.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcap_cf7_verify_recaptcha_without_site_key() {
		$data = [];

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		$result = Mockery::mock( WPCF7_Validation::class );
		$result->shouldReceive( 'invalidate' )->with(
			[
				'type' => 'hcaptcha',
				'name' => 'hcap-cf7',
			],
			'Please complete the hCaptcha.'
		);

		$tag = Mockery::mock( WPCF7_FormTag::class );

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without response.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcap_cf7_verify_recaptcha_without_response() {
		$data              = [];
		$wpcf7_id          = 23;
		$hcaptcha_site_key = 'some site key';
		$cf7_text          =
			'<form>' .
			'<input type="submit" value="Send">' .
			$hcaptcha_site_key .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			static function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		update_option( 'hcaptcha_settings', [ 'site_key' => $hcaptcha_site_key ] );

		hcaptcha()->init_hooks();

		$result = Mockery::mock( WPCF7_Validation::class );
		$tag    = Mockery::mock( WPCF7_FormTag::class );

		$result
			->shouldReceive( 'invalidate' )
			->with(
				[
					'type' => 'hcaptcha',
					'name' => 'hcap-cf7',
				],
				'Please complete the hCaptcha.'
			)
			->once();

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() not verified.
	 *
	 * @noinspection PhpParamsInspection
	 */
	public function test_hcap_cf7_verify_recaptcha_not_verified() {
		$data              = [ 'h-captcha-response' => 'some response' ];
		$wpcf7_id          = 23;
		$hcaptcha_site_key = 'some site key';
		$cf7_text          =
			'<form>' .
			'<input type="submit" value="Send">' .
			$hcaptcha_site_key .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			static function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		update_option( 'hcaptcha_settings', [ 'site_key' => $hcaptcha_site_key ] );

		hcaptcha()->init_hooks();

		$this->prepare_hcaptcha_request_verify( $data['h-captcha-response'], false );

		$result = Mockery::mock( WPCF7_Validation::class );
		$tag    = Mockery::mock( WPCF7_FormTag::class );

		$result
			->shouldReceive( 'invalidate' )
			->with(
				[
					'type' => 'hcaptcha',
					'name' => 'hcap-cf7',
				],
				'The hCaptcha is invalid.'
			)
			->once();

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test hcap_cf7_enqueue_scripts().
	 */
	public function test_hcap_cf7_enqueue_scripts() {
		$hcaptcha_size = 'normal';

		$subject = new CF7();

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( CF7::HANDLE ) );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_end_clean();

		self::assertFalse( wp_script_is( CF7::HANDLE ) );

		update_option( 'hcaptcha_settings', [ 'size' => $hcaptcha_size ] );

		hcaptcha()->init_hooks();

		do_shortcode( '[cf7-hcaptcha]' );

		ob_start();
		do_action( 'wp_print_footer_scripts' );
		ob_end_clean();

		self::assertTrue( wp_script_is( CF7::HANDLE ) );
	}
}
