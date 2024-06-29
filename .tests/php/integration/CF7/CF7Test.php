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
use WPCF7_FormTag;
use WPCF7_Submission;
use WPCF7_Validation;

/**
 * Test CF7 class.
 *
 * @requires PHP >= 7.4
 *
 * @group    cf7
 * @group    cf7-cf7
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
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		unset( $_REQUEST['_wpnonce'], $_POST['_wpcf7'], $_SERVER['REQUEST_URI'] );

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

		self::assertSame( 20, has_filter( 'do_shortcode_tag', [ $subject, 'wpcf7_shortcode' ] ) );
		self::assertTrue( shortcode_exists( 'cf7-hcaptcha' ) );
		self::assertSame( 20, has_filter( 'wpcf7_validate', [ $subject, 'verify_hcaptcha' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test wpcf7_shortcode().
	 *
	 * @param bool $mode_auto  Mode auto.
	 * @param bool $mode_embed Mode embed.
	 *
	 * @dataProvider dp_test_wpcf7_shortcode
	 */
	public function test_wpcf7_shortcode( bool $mode_auto, bool $mode_embed ) {
		$output            =
			'<form>' .
			'<input type="submit" value="Send">' .
			'</form>';
		$tag               = 'contact-form-7';
		$form_id           = 177;
		$attr              = [ 'id' => $form_id ];
		$m                 = [
			'[contact-form-7 id="177" title="Contact form 1"]',
			'',
			'contact-form-7',
			'id="177" title="Contact form 1"',
			'',
			'',
			'',
		];
		$uniqid            = 'hcap_cf7-6004092a854114.24546665';
		$nonce             = wp_nonce_field( 'wp_rest', '_wpnonce', true, false );
		$hcaptcha_site_key = 'some site key';
		$hcaptcha_theme    = 'some theme';
		$hcaptcha_size     = 'normal';
		$id                = [
			'source'  => [ 'contact-form-7/wp-contact-form-7.php' ],
			'form_id' => $form_id,
		];
		$cf7_status        = array_filter( [ $mode_auto ? 'form' : '', $mode_embed ? 'embed' : '' ] );

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $hcaptcha_site_key,
				'theme'      => $hcaptcha_theme,
				'size'       => $hcaptcha_size,
				'cf7_status' => $cf7_status,
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

		$expected_form =
			'<form>' .
			'<span class="wpcf7-form-control-wrap" data-name="hcap-cf7">' .
			$this->get_hcap_widget( $id ) . '
				<span id="' . $uniqid . '" class="wpcf7-form-control h-captcha "
				data-sitekey="' . $hcaptcha_site_key . '"
				data-theme="' . $hcaptcha_theme . '"
				data-size="' . $hcaptcha_size . '"
				data-auto="false"
				data-force="false">' . '
		</span>
		' . $nonce .
			'</span><input type="submit" value="Send">' .
			'</form>';

		$expected1 = '';
		$expected2 = '';
		$expected3 = '';

		if ( ( ! $mode_auto && ! $mode_embed ) ) {
			$expected1 = $output;
			$expected2 = $output;
			$expected3 = str_replace( 'h-captcha ', 'h-captcha some-class', $expected2 );
		}

		if ( $mode_auto && ! $mode_embed ) {
			$expected1 = $expected_form;
			$expected2 = $expected_form;
			$expected3 = str_replace( 'h-captcha ', 'h-captcha ', $expected2 );
		}

		if ( ! $mode_auto && $mode_embed ) {
			$expected1 = $output;
			$expected2 = $expected_form;
			$expected3 = str_replace( 'h-captcha ', 'h-captcha some-class', $expected2 );
		}

		if ( $mode_auto && $mode_embed ) {
			$expected1 = $expected_form;
			$expected2 = $expected_form;
			$expected3 = str_replace( 'h-captcha ', 'h-captcha some-class', $expected2 );
		}

		$subject = new CF7();

		self::assertSame( $expected1, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );

		$output = str_replace( '<input', '[cf7-hcaptcha]<input', $output );

		self::assertSame( $expected2, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );

		$output = str_replace( '[cf7-hcaptcha]', '[cf7-hcaptcha form_id=' . $form_id . ' class:some-class]', $output );

		self::assertSame( $expected3, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );
	}

	/**
	 * Data provide for test_wpcf7_shortcode().
	 *
	 * @return array
	 */
	public function dp_test_wpcf7_shortcode(): array {
		return [
			'none'  => [ false, false ],
			'auto'  => [ true, false ],
			'embed' => [ false, true ],
			'all'   => [ true, true ],
		];
	}

	/**
	 * Test wpcf7_shortcode() when NOT active.
	 *
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_wpcf7_shortcode_when_NOT_active() {
		$output            =
			'<form>' .
			'<input type="submit" value="Send">' .
			'</form>';
		$form_id           = 177;
		$tag               = 'contact-form-7';
		$attr              = [ 'id' => $form_id ];
		$m                 = [
			'[contact-form-7 id="' . $form_id . '" title="Contact form 1"]',
			'',
			'contact-form-7',
			'id="177" title="Contact form 1"',
			'',
			'',
			'',
		];
		$uniqid            = 'hcap_cf7-6004092a854114.24546665';
		$hcaptcha_site_key = 'some site key';
		$hcaptcha_theme    = 'some theme';
		$hcaptcha_size     = 'normal';

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $hcaptcha_site_key,
				'theme'      => $hcaptcha_theme,
				'size'       => $hcaptcha_size,
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

		hcaptcha()->init_hooks();

		add_filter(
			'hcap_protect_form',
			static function ( $value, $source, $id ) use ( $form_id ) {
				if ( (int) $id === $form_id && in_array( 'contact-form-7/wp-contact-form-7.php', $source, true ) ) {
					return false;
				}

				return $value;
			},
			10,
			3
		);

		FunctionMocker::replace(
			'uniqid',
			static function ( $prefix, $more_entropy ) use ( $uniqid ) {
				if ( 'hcap_cf7-' === $prefix && $more_entropy ) {
					return $uniqid;
				}

				return null;
			}
		);

		$id       = [
			'source'  => [ 'contact-form-7/wp-contact-form-7.php' ],
			'form_id' => $form_id,
		];
		$expected =
			'<form><span class="wpcf7-form-control-wrap" data-name="hcap-cf7">' .
			$this->get_hcap_widget( $id ) . '
		</span><input type="submit" value="Send"></form>';

		$subject = new CF7();

		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );
	}

	/**
	 * Test check_rest_nonce().
	 *
	 * @return void
	 */
	public function test_check_rest_nonce() {
		$result = 'some result';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$_REQUEST['_wpnonce'] = 'some nonce';

		$subject = new CF7();

		// Logged in.
		wp_set_current_user( 1 );

		self::assertSame( $result, $subject->check_rest_nonce( $result ) );
		self::assertArrayHasKey( '_wpnonce', $_REQUEST );

		// Not logged in.
		wp_set_current_user( 0 );

		self::assertSame( $result, $subject->check_rest_nonce( $result ) );
		self::assertArrayHasKey( '_wpnonce', $_REQUEST );

		// CF7 submit.
		$_POST['_wpcf7']        = '177';
		$_SERVER['REQUEST_URI'] = '/wp-json/contact-form-7/v1/contact-forms/177/feedback';

		self::assertSame( $result, $subject->check_rest_nonce( $result ) );
		self::assertArrayNotHasKey( '_wpnonce', $_REQUEST );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Test verify_hcaptcha().
	 *
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_verify_hcaptcha() {
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

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $hcaptcha_site_key,
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

		hcaptcha()->init_hooks();

		$this->prepare_hcaptcha_request_verify( $data['h-captcha-response'] );

		$result = Mockery::mock( WPCF7_Validation::class );
		$tag    = Mockery::mock( WPCF7_FormTag::class );

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test verify_hcaptcha() without submission.
	 */
	public function test_verify_hcaptcha_without_submission() {
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
	 * Test verify_hcaptcha() without a mode set.
	 */
	public function test_verify_hcaptcha_without_mode_set() {
		$result     = Mockery::mock( WPCF7_Validation::class );
		$tag        = Mockery::mock( WPCF7_FormTag::class );
		$submission = Mockery::mock( WPCF7_Submission::class );

		FunctionMocker::replace( 'WPCF7_Submission::get_instance', $submission );

		update_option(
			'hcaptcha_settings',
			[
				'cf7_status' => [],
			]
		);

		hcaptcha()->init_hooks();

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test verify_hcaptcha() without posted data.
	 */
	public function test_verify_hcaptcha_without_posted_data() {
		$data              = [];
		$hcaptcha_site_key = 'some site key';
		$submission        = Mockery::mock( WPCF7_Submission::class );
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

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $hcaptcha_site_key,
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

		hcaptcha()->init_hooks();

		$tag = Mockery::mock( WPCF7_FormTag::class );

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test verify_hcaptcha() without a site key.
	 */
	public function test_verify_hcaptcha_without_site_key() {
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

		update_option(
			'hcaptcha_settings',
			[
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

		hcaptcha()->init_hooks();

		$subject = new CF7();

		self::assertSame( $result, $subject->verify_hcaptcha( $result, $tag ) );
	}

	/**
	 * Test verify_hcaptcha() without a response.
	 *
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_verify_hcaptcha_without_response() {
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

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $hcaptcha_site_key,
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

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
	 * Test verify_hcaptcha() not verified.
	 *
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_verify_hcaptcha_not_verified() {
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

		update_option(
			'hcaptcha_settings',
			[
				'site_key'   => $hcaptcha_site_key,
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

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
	 * Test has_hcaptcha_field().
	 *
	 * @return void
	 */
	public function test_has_hcaptcha_field() {
		$submission   = Mockery::mock( WPCF7_Submission::class );
		$contact_form = Mockery::mock( WPCF7_ContactForm::class );
		$field        = Mockery::mock( WPCF7_FormTag::class );
		$field->type  = 'hcaptcha';
		$form_fields  = [
			$field,
		];
		$submission->shouldReceive( 'get_contact_form' )->andReturn( $contact_form );
		$contact_form->shouldReceive( 'scan_form_tags' )->andReturn( $form_fields );

		$subject = Mockery::mock( CF7::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertTrue( $subject->has_hcaptcha_field( $submission ) );

		$field->type = 'some';

		self::assertFalse( $subject->has_hcaptcha_field( $submission ) );
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

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles() {
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

		$expected = <<<CSS
	span[data-name="hcap-cf7"] .h-captcha {
		margin-bottom: 0;
	}

	span[data-name="hcap-cf7"] ~ input[type="submit"],
	span[data-name="hcap-cf7"] ~ button[type="submit"] {
		margin-top: 2rem;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new CF7();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_form_id_to_cf7_hcap_shortcode().
	 *
	 * @return void
	 */
	public function test_add_form_id_to_cf7_hcap_shortcode() {
		$subject = Mockery::mock( CF7::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		// No hcap_cf7 shortcode.
		$output = 'some output';

		self::assertSame( $output, $subject->add_form_id_to_cf7_hcap_shortcode( $output, 177 ) );

		// Shortcode has the same form_id.
		$output = '[cf7-hcaptcha form_id="177"]';

		self::assertSame( $output, $subject->add_form_id_to_cf7_hcap_shortcode( $output, 177 ) );

		// Shortcode does not have form_id.
		$output   = '[cf7-hcaptcha class:some-class]';
		$expected = '[cf7-hcaptcha class:some-class form_id="177"]';

		self::assertSame( $expected, $subject->add_form_id_to_cf7_hcap_shortcode( $output, 177 ) );
	}
}
