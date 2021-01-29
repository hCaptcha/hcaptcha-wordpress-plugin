<?php
/**
 * CF7Test class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\CF7;

use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WPCF7_Submission;
use WPCF7_Validation;

/**
 * Test cf7 files.
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
		unset( $GLOBALS['hcap_cf7'] );

		wp_deregister_script( 'hcaptcha-script' );
		wp_dequeue_script( 'hcaptcha-script' );

		parent::tearDown();
	}

	/**
	 * Test enqueue_hcap_cf7_script().
	 */
	public function test_enqueue_hcap_cf7_script() {
		global $hcap_cf7;

		$hcap_cf7 = true;

		$hcaptcha_api_key = 'some key';
		update_option( 'hcaptcha_api_key', $hcaptcha_api_key );

		$expected = "var widgetIds = [];
        var hcap_cf7LoadCallback = function() {
        var hcap_cf7Widgets = document.querySelectorAll('.hcap_cf7-h-captcha');
        for (var i = 0; i < hcap_cf7Widgets.length; ++i) {
            var hcap_cf7Widget = hcap_cf7Widgets[i];
            var widgetId = hcaptcha.render(hcap_cf7Widget.id, {
                'sitekey' : '" . $hcaptcha_api_key . "'
                });
                widgetIds.push(widgetId);
            }
        };
        (function($) {
            $('.wpcf7').on('invalid.wpcf7 mailsent.wpcf7', function() {
                for (var i = 0; i < widgetIds.length; i++) {
                    hcaptcha.reset(widgetIds[i]);
                }
            });
        })(jQuery);";

		hcap_captcha_script();
		enqueue_hcap_cf7_script();

		self::assertTrue( wp_script_is( 'hcaptcha-script', 'enqueued' ) );
		self::assertSame( $expected, $GLOBALS['wp_scripts']->registered['hcaptcha-script']->extra['after'][1] );
	}

	/**
	 * Test enqueue_hcap_cf7_script().
	 */
	public function test_enqueue_hcap_cf7_script_without_cf7() {
		enqueue_hcap_cf7_script();

		self::assertFalse( wp_script_is( 'hcaptcha-script', 'enqueued' ) );
	}

	/**
	 * Test hcap_cf7_wpcf7_form_elements().
	 */
	public function test_hcap_cf7_wpcf7_form_elements() {
		$form =
			'<form>' .
			'<input type="submit" value="Send">' .
			'</form>';

		$uniqid = 'hcap_cf7-6004092a854114.24546665';

		$nonce = wp_nonce_field( 'hcaptcha_contact_form7', 'hcaptcha_contact_form7', true, false );

		$hcaptcha_api_key = 'some api key';
		$hcaptcha_theme   = 'some theme';
		$hcaptcha_size    = 'some size';

		update_option( 'hcaptcha_api_key', $hcaptcha_api_key );
		update_option( 'hcaptcha_theme', $hcaptcha_theme );
		update_option( 'hcaptcha_size', $hcaptcha_size );

		FunctionMocker::replace(
			'uniqid',
			function ( $prefix, $more_entropy ) use ( $uniqid ) {
				if ( 'hcap_cf7-' === $prefix && $more_entropy ) {
					return $uniqid;
				}

				return null;
			}
		);

		$expected =
			'<form>' .
			'<div id="' . $uniqid . '"' .
			' class="h-captcha hcap_cf7-h-captcha" data-sitekey="' . $hcaptcha_api_key . '"' .
			' data-theme="' . $hcaptcha_theme . '"' .
			' data-size="' . $hcaptcha_size . '"></div>' .
			'<span class="wpcf7-form-control-wrap hcap_cf7-h-captcha-invalid"></span>' .
			$nonce .
			'<br><input type="submit" value="Send">' .
			'</form>';

		self::assertSame( $expected, hcap_cf7_wpcf7_form_elements( $form ) );

		$form     = str_replace( '<input', '[cf7-hcaptcha]<input', $form );
		$expected = str_replace( '<br>', '', $expected );

		self::assertSame( $expected, hcap_cf7_wpcf7_form_elements( $form ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha().
	 */
	public function test_hcap_cf7_verify_recaptcha() {
		$data             = [ 'h-captcha-response' => 'some response' ];
		$wpcf7_id         = 23;
		$hcaptcha_api_key = 'some api key';
		$cf7_text         =
			'<form>' .
			'<input type="submit" value="Send">' .
			$hcaptcha_api_key .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		$_POST['_wpcf7'] = 23;

		update_option( 'hcaptcha_api_key', $hcaptcha_api_key );

		$this->prepare_hcaptcha_request_verify( $data['h-captcha-response'] );

		$result = Mockery::mock( WPCF7_Validation::class );

		self::assertSame( $result, hcap_cf7_verify_recaptcha( $result ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without submission.
	 */
	public function test_hcap_cf7_verify_recaptcha_without_submission() {
		$result = Mockery::mock( WPCF7_Validation::class );

		self::assertSame( $result, hcap_cf7_verify_recaptcha( $result ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without posted data.
	 */
	public function test_hcap_cf7_verify_recaptcha_without_posted_data() {
		$data       = [];
		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		$result = Mockery::mock( WPCF7_Validation::class );

		self::assertSame( $result, hcap_cf7_verify_recaptcha( $result ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without api key.
	 */
	public function test_hcap_cf7_verify_recaptcha_without_api_key() {
		$data     = [];
		$wpcf7_id = 23;
		$cf7_text =
			'<form>' .
			'<input type="submit" value="Send">' .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		$_POST['_wpcf7'] = 23;

		$result = Mockery::mock( WPCF7_Validation::class );

		self::assertSame( $result, hcap_cf7_verify_recaptcha( $result ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() without response.
	 */
	public function test_hcap_cf7_verify_recaptcha_without_response() {
		$data             = [];
		$wpcf7_id         = 23;
		$hcaptcha_api_key = 'some api key';
		$cf7_text         =
			'<form>' .
			'<input type="submit" value="Send">' .
			$hcaptcha_api_key .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		$_POST['_wpcf7'] = 23;

		update_option( 'hcaptcha_api_key', $hcaptcha_api_key );

		$result = Mockery::mock( WPCF7_Validation::class );
		$result
			->shouldReceive( 'invalidate' )
			->with(
				[
					'type' => 'captcha',
					'name' => 'hcap_cf7-h-captcha-invalid',
				],
				'Please complete the captcha.'
			)
			->once();

		self::assertSame( $result, hcap_cf7_verify_recaptcha( $result ) );
	}

	/**
	 * Test hcap_cf7_verify_recaptcha() not verified.
	 */
	public function test_hcap_cf7_verify_recaptcha_not_verified() {
		$data             = [ 'h-captcha-response' => 'some response' ];
		$wpcf7_id         = 23;
		$hcaptcha_api_key = 'some api key';
		$cf7_text         =
			'<form>' .
			'<input type="submit" value="Send">' .
			$hcaptcha_api_key .
			'</form>';

		$submission = Mockery::mock( WPCF7_Submission::class );
		$submission->shouldReceive( 'get_posted_data' )->andReturn( $data );
		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		add_shortcode(
			'contact-form-7',
			function ( $content ) use ( $wpcf7_id, $cf7_text ) {
				if ( $wpcf7_id === (int) $content['id'] ) {
					return $cf7_text;
				}

				return '';
			}
		);

		$_POST['_wpcf7'] = 23;

		update_option( 'hcaptcha_api_key', $hcaptcha_api_key );

		$this->prepare_hcaptcha_request_verify( $data['h-captcha-response'], false );

		$result = Mockery::mock( WPCF7_Validation::class );
		$result
			->shouldReceive( 'invalidate' )
			->with(
				[
					'type' => 'captcha',
					'name' => 'hcap_cf7-h-captcha-invalid',
				],
				'The Captcha is invalid.'
			)
			->once();

		self::assertSame( $result, hcap_cf7_verify_recaptcha( $result ) );
	}
}
