<?php
/**
 * ContactTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\BeaverBuilder;

use FLBuilderModule;
use HCaptcha\BeaverBuilder\Contact;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Class ContactTest
 *
 * @group beaver-builder
 * @group beaver-builder-contact
 */
class ContactTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Contact();

		$subject->init_hooks();

		// Base hooks.
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );

		// Contact hooks.
		self::assertSame(
			10,
			has_filter( 'fl_builder_render_module_content', [ $subject, 'add_beaver_builder_captcha' ] )
		);
		self::assertSame( 10, has_action( 'fl_module_contact_form_before_send', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test add_beaver_builder_captcha().
	 *
	 * @return void
	 * @noinspection PhpParamsInspection
	 */
	public function test_add_beaver_builder_captcha(): void {
		$button    = '<div class="fl-button-wrap some"><button class="fl-button">Submit</button></div>';
		$form      = '<form class="fl-contact-form" id="some">' . $button . '</form>';
		$some_out  = 'some output';
		$form_out  = 'some output ' . $form . ' more';
		$args      = [
			'action' => 'hcaptcha_beaver_builder',
			'name'   => 'hcaptcha_beaver_builder_nonce',
			'id'     => [
				'source'  => [ 'bb-plugin/fl-builder.php' ],
				'form_id' => 'contact',
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$hcaptcha  = '<div class="fl-input-group fl-hcaptcha">' . $hcap_form . '</div>';
		$expected  = 'some output <form class="fl-contact-form" id="some">' . $hcaptcha . $button . '</form> more';
		$module    = Mockery::mock( 'alias:' . FLBuilderModule::class );

		$subject = new Contact();

		// Some output.
		self::assertSame( $some_out, $subject->add_beaver_builder_captcha( $some_out, $module ) );

		// Contact form in output.
		self::assertSame( $expected, $subject->add_beaver_builder_captcha( $form_out, $module ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$subject = new Contact();

		$this->prepare_verify_post( 'hcaptcha_beaver_builder_nonce', 'hcaptcha_beaver_builder' );

		$subject->verify( 'a@a.com', 'Subject', 'Message', [], (object) [] );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		$die_arr  = [];
		$expected = [
			'',
			'',
			[ 'response' => null ],
		];

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$this->prepare_verify_post( 'hcaptcha_beaver_builder_nonce', 'hcaptcha_beaver_builder', false );

		$subject = new Contact();

		ob_start();
		$subject->verify( 'a@a.com', 'Subject', 'Message', [], (object) [] );
		$json = ob_get_clean();

		self::assertSame( '{"error":true,"message":"The hCaptcha is invalid."}', $json );
		self::assertSame( $expected, $die_arr );
	}
}
