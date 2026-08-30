<?php
/**
 * ReallySimpleCaptchaTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\CF7;

use HCaptcha\CF7\CF7;
use HCaptcha\CF7\ReallySimpleCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionClass;
use WPCF7_ContactForm;
use WPCF7_Submission;
use function PHPUnit\Framework\assertSame;

/**
 * Test ReallySimpleCaptcha class.
 *
 * @group    cf7
 * @group    cf7-really-simple-captcha
 */
class ReallySimpleCaptchaTest extends HCaptchaPluginWPTestCase {

	/**
	 * Contact Form 7 and Really Simple CAPTCHA entry files.
	 *
	 * @var string[]
	 */
	protected static $plugin = [
		'contact-form-7/wp-contact-form-7.php',
		'really-simple-captcha/really-simple-captcha.php',
	];

	/**
	 * Hooks to replay after loading the plugins.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Enable replacement before Contact Form 7 registers its CAPTCHA tags.
	 *
	 * @return void
	 */
	protected function before_load_test_plugins(): void {
		update_option( 'hcaptcha_settings', [ 'cf7_status' => [ 'replace_rsc', 'embed' ] ] );
		hcaptcha()->init_hooks();
		new ReallySimpleCaptcha();
		new CF7();
	}

	/**
	 * Test a live Contact Form 7 form containing Really Simple CAPTCHA tags.
	 *
	 * @return void
	 */
	public function test_live_really_simple_captcha_form(): void {
		$contact_form         = WPCF7_ContactForm::get_template(
			[
				'locale' => 'en_US',
				'title'  => 'Really Simple CAPTCHA form',
			]
		);
		$properties           = $contact_form->get_properties();
		$properties['form']   = '[text* your-name] [captchac captcha-1] [captchar* captcha-1] [submit "Send"]';
		$properties['mail']   = [];
		$properties['mail_2'] = [];

		$contact_form->set_properties( $properties );
		$form_id = $contact_form->save();

		add_shortcode( 'contact-form-7', 'wpcf7_contact_form_tag_func' );

		$output      = do_shortcode( '[contact-form-7 id="' . $form_id . '"]' );
		$plugin_file = wp_normalize_path( ( new ReflectionClass( \ReallySimpleCaptcha::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) );
		self::assertTrue( is_plugin_active( 'really-simple-captcha/really-simple-captcha.php' ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/really-simple-captcha/' ), $plugin_file );
		self::assertFalse( has_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' ) );
		self::assertStringContainsString( 'class="wpcf7-form', $output );
		self::assertStringContainsString( 'h-captcha', $output );
		self::assertStringNotContainsString( '[captchac', $output );
		self::assertStringNotContainsString( '[captchar', $output );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		update_option( 'hcaptcha_settings', [ 'cf7_status' => [ 'replace_rsc' ] ] );
		hcaptcha()->init_hooks();

		$subject = new ReallySimpleCaptcha();

		self::assertSame( 0, has_action( 'wpcf7_init', [ $subject, 'remove_wpcf7_add_form_tag_captcha_action' ] ) );
		self::assertSame( 10, has_filter( 'do_shortcode_tag', [ $subject, 'wpcf7_shortcode' ] ) );
		self::assertSame( 10, has_filter( 'hcap_cf7_has_field', [ $subject, 'has_field' ] ) );
	}

	/**
	 * Test remove_wpcf7_add_form_tag_captcha_action().
	 *
	 * @return void
	 */
	public function test_remove_wpcf7_add_form_tag_captcha_action(): void {
		add_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' );

		$subject = new ReallySimpleCaptcha();

		self::assertSame( 10, has_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' ) );

		$subject->remove_wpcf7_add_form_tag_captcha_action();

		self::assertFalse( has_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' ) );
	}

	/**
	 * Test wpcf7_shortcode().
	 *
	 * @return void
	 */
	public function test_wpcf7_shortcode(): void {
		$tag      = 'some tag';
		$output   = 'some output [captchac some-attrs] [captchar some-attrs] more output';
		$expected = 'some output [cf7-hcaptcha some-attrs]  more output';

		$subject = new ReallySimpleCaptcha();

		// Wrong tag.
		self::assertSame( $output, $subject->wpcf7_shortcode( $output, $tag, [], [] ) );

		$tag = 'contact-form-7';

		// CF7 tag.
		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, $tag, [], [] ) );
	}

	/**
	 * Test has_field().
	 *
	 * @return void
	 */
	public function test_has_field(): void {
		$form_html = 'some html [captchac some-attrs] [captchar some-attrs] more html';

		$contact_form = Mockery::mock( WPCF7_ContactForm::class );
		$submission   = Mockery::mock( WPCF7_Submission::class );

		$contact_form->shouldReceive( 'form_html' )->andReturn( $form_html );
		$submission->shouldReceive( 'get_contact_form' )->andReturn( $contact_form );

		$subject = new ReallySimpleCaptcha();

		// Wrong type.
		self::assertFalse( $subject->has_field( false, $submission, 'some-type' ) );

		// The hcaptcha type.
		self::assertTrue( $subject->has_field( false, $submission, 'hcaptcha' ) );
	}
}
