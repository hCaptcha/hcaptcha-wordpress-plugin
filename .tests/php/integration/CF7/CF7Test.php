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
use WPCF7_TagGenerator;
use WPCF7_Validation;

/**
 * Test CF7 class.
 *
 * @requires PHP >= 7.4
 *
 * @group    cf7
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
	 * @param string $hcaptcha_size Widget size/visibility.
	 *
	 * @dataProvider dp_test_wpcf7_shortcode
	 */
	public function test_wpcf7_shortcode( string $hcaptcha_size ) {
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
		$id                = [
			'source'  => [ 'contact-form-7/wp-contact-form-7.php' ],
			'form_id' => $form_id,
		];

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

		$expected =
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

		$subject = new CF7();

		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );

		$output = str_replace( '<input', '[cf7-hcaptcha]<input', $output );

		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );

		$output   = str_replace( '[cf7-hcaptcha]', '[cf7-hcaptcha form_id=' . $form_id . ' class:some-class]', $output );
		$expected = str_replace( 'h-captcha ', 'h-captcha some-class', $expected );

		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, $tag, $attr, $m ) );
	}

	/**
	 * Data provide for test_wpcf7_shortcode().
	 *
	 * @return array
	 */
	public function dp_test_wpcf7_shortcode(): array {
		return [
			'visible'   => [ 'normal' ],
			'invisible' => [ 'invisible' ],
		];
	}

	/**
	 * Test wpcf7_shortcode() when NOT active.
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
				'site_key' => $hcaptcha_site_key,
				'theme'    => $hcaptcha_theme,
				'size'     => $hcaptcha_size,
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
	 * Test hcap_cf7_verify_recaptcha().
	 *
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
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
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
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
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
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
	 * Test add_tag_generator_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_tag_generator_hcaptcha() {
		$subject = new CF7();

		require_once WPCF7_PLUGIN_DIR . '/admin/includes/tag-generator.php';

		$tag_generator = WPCF7_TagGenerator::get_instance();

		ob_start();
		$tag_generator->print_buttons();
		$buttons = ob_get_clean();

		self::assertFalse( strpos( $buttons, 'hcaptcha' ) );

		$subject->add_tag_generator_hcaptcha();

		ob_start();
		$tag_generator->print_buttons();
		$buttons = ob_get_clean();

		self::assertNotFalse( strpos( $buttons, 'hcaptcha' ) );
	}

	/**
	 * Test tag_generator_hcaptcha().
	 *
	 * @return void
	 */
	public function test_tag_generator_hcaptcha() {
		$args     = [
			'id'      => 'cf7-hcaptcha',
			'title'   => 'hCaptcha',
			'content' => 'tag-generator-panel-cf7-hcaptcha',
		];
		$expected = '		<div class="control-box">
			<fieldset>
				<legend>Generate a form-tag for a hCaptcha field.</legend>

				<table class="form-table">
					<tbody>

					<tr>
						<th scope="row">
							<label for="tag-generator-panel-cf7-hcaptcha-id">
								Id attribute							</label>
						</th>
						<td>
							<input
									type="text" name="id" class="idvalue oneline option"
									id="tag-generator-panel-cf7-hcaptcha-id"/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tag-generator-panel-cf7-hcaptcha-class">
								Class attribute							</label>
						</th>
						<td>
							<input
									type="text" name="class" class="classvalue oneline option"
									id="tag-generator-panel-cf7-hcaptcha-class"/>
						</td>
					</tr>

					</tbody>
				</table>
			</fieldset>
		</div>

		<div class="insert-box">
			<label>
				<input
						type="text" name="cf7-hcaptcha" class="tag code" readonly="readonly"
						onfocus="this.select()"/>
			</label>

			<div class="submitbox">
				<input
						type="button" class="button button-primary insert-tag"
						value="Insert Tag"/>
			</div>
		</div>
		';

		$subject = new CF7();

		ob_start();
		$subject->tag_generator_hcaptcha( [], $args );
		self::assertSame( $expected, ob_get_clean() );
	}
}
