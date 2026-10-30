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
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionClass;
use ReflectionProperty;
use WPCF7_ContactForm;
use WPCF7_Submission;

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
		update_option( 'hcaptcha_settings', [ 'cf7_status' => [ 'replace_rsc' ] ] );
		hcaptcha()->init_hooks();
		new ReallySimpleCaptcha();
		new CF7();
	}

	/**
	 * Test a live Contact Form 7 form containing Really Simple CAPTCHA tags.
	 *
	 * @param string $form_template RSC form template.
	 * @param string $shortcode CF7 shortcode alias.
	 * @dataProvider dp_live_rsc_forms
	 *
	 * @return void
	 */
	public function test_live_really_simple_captcha_form( string $form_template, string $shortcode ): void {
		$contact_form         = WPCF7_ContactForm::get_template(
			[
				'locale' => 'en_US',
				'title'  => 'Really Simple CAPTCHA form',
			]
		);
		$properties           = $contact_form->get_properties();
		$properties['form']   = $form_template;
		$properties['mail']   = [];
		$properties['mail_2'] = [];

		$contact_form->set_properties( $properties );
		$form_id = $contact_form->save();

		add_shortcode( 'contact-form-7', 'wpcf7_contact_form_tag_func' );

		if ( 'contact-form' === $shortcode ) {
			add_shortcode( 'contact-form', 'wpcf7_contact_form_tag_func' );
			add_post_meta( $form_id, '_old_cf7_unit_id', $form_id );
		}

		$output      = do_shortcode( 'contact-form' === $shortcode ? '[contact-form ' . $form_id . ']' : '[contact-form-7 id="' . $form_id . '"]' );
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
	 * RSC forms can use both native CF7 aliases and Unicode tag separators.
	 *
	 * @return array
	 */
	public function dp_live_rsc_forms(): array {
		$normal_form = '[text* your-name] [captchac captcha-1] [captchar captcha-1] [submit "Send"]';
		$nbsp        = "\u{00A0}";
		$nbsp_form   = '[text* your-name] [captchac' . $nbsp . 'captcha-1] [captchar' . $nbsp . 'captcha-1] [submit "Send"]';

		return [
			'default'         => [ $normal_form, 'contact-form-7' ],
			'nbsp'            => [ $nbsp_form, 'contact-form-7' ],
			'legacy-alias'    => [ $normal_form, 'contact-form' ],
			'alias-nbsp'      => [ $nbsp_form, 'contact-form' ],
			'one-sided-close' => [ '[text* your-name] [captchac captcha-1] [captchar captcha-1]] [submit "Send"]', 'contact-form-7' ],
			'one-sided-open'  => [ '[text* your-name] [[captchac captcha-1] [captchar captcha-1] [submit "Send"]', 'contact-form-7' ],
		];
	}

	/**
	 * Replacement alone must validate RSC forms without affecting other forms.
	 *
	 * @param string $form_template Form template.
	 * @param bool   $should_reject Whether the missing proof must be rejected.
	 * @param bool   $valid_proof   Whether to use a valid hCaptcha proof.
	 * @dataProvider dp_replace_only_submission
	 *
	 * @return void
	 */
	public function test_replace_only_submission( string $form_template, bool $should_reject, bool $valid_proof ): void {
		$contact_form         = WPCF7_ContactForm::get_template(
			[
				'locale' => 'en_US',
				'title'  => 'SEC041 form',
			]
		);
		$properties           = $contact_form->get_properties();
		$properties['form']   = $form_template;
		$properties['mail']   = [];
		$properties['mail_2'] = [];
		$contact_form->set_properties( $properties );
		$form_id = $contact_form->save();
		self::assertGreaterThan( 0, $form_id );

		$before_mail = false;
		add_filter( 'wpcf7_skip_spam_check', '__return_true' );
		add_action(
			'wpcf7_before_send_mail',
			static function ( $form, &$abort ) use ( &$before_mail ): void {
				$before_mail = true;
				$abort       = true;
			},
			10,
			2
		);

		$_POST = [
			'_wpcf7'                => (string) $form_id,
			'_wpcf7_unit_tag'       => 'wpcf7-f' . $form_id . '-o1',
			'_wpcf7_locale'         => 'en_US',
			'_wpcf7_container_post' => '0',
			'your-name'             => 'SEC041 sender',
		];
		if ( $valid_proof ) {
			$this->prepare_verify_request( 'sec041-local-proof' );
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
				[
					'source'  => [ 'contact-form-7/wp-contact-form-7.php' ],
					'form_id' => $form_id,
				]
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Synthetic native CF7 request.
		$_REQUEST                  = $_POST;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		try {
			$result = $contact_form->submit( [ 'skip_mail' => true ] );
			self::assertSame( $should_reject ? 'validation_failed' : 'aborted', $result['status'] );
			self::assertSame( ! $should_reject, $before_mail );
		} finally {
			$submission_instance = new ReflectionProperty( WPCF7_Submission::class, 'instance' );
			$submission_instance->setAccessible( true );
			$submission_instance->setValue( null, null );
			unset( $_POST, $_REQUEST, $_SERVER['REQUEST_METHOD'] );
		}
	}

	/**
	 * Cases for replacement-only CF7 submissions.
	 *
	 * @return array
	 */
	public function dp_replace_only_submission(): array {
		$nbsp = "\u{00A0}";

		return [
			'rsc-pair-missing' => [ '[text* your-name] [captchac captcha-1] [captchar captcha-1] [submit "Send"]', true, false ],
			'rsc-pair-valid'   => [ '[text* your-name] [captchac captcha-1] [captchar captcha-1] [submit "Send"]', false, true ],
			'rsc-nbsp-missing' => [ '[text* your-name] [captchac' . $nbsp . 'captcha-1] [captchar' . $nbsp . 'captcha-1] [submit "Send"]', true, false ],
			'rsc-nbsp-valid'   => [ '[text* your-name] [captchac' . $nbsp . 'captcha-1] [captchar' . $nbsp . 'captcha-1] [submit "Send"]', false, true ],
			'one-sided-close'  => [ '[text* your-name] [captchac captcha-1] [captchar captcha-1]] [submit "Send"]', true, false ],
			'one-sided-open'   => [ '[text* your-name] [[captchac captcha-1] [captchar captcha-1] [submit "Send"]', true, false ],
			'rsc-response'     => [ '[text* your-name] [captchar* captcha-1] [submit "Send"]', true, false ],
			'ordinary'         => [ '[text* your-name] [submit "Send"]', false, false ],
			'explicit-embed'   => [ '[text* your-name] [cf7-hcaptcha] [submit "Send"]', false, false ],
			'escaped-rsc'      => [ '[text* your-name] [[captchac captcha-1]] [[captchar captcha-1]] [submit "Send"]', false, false ],
		];
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
		self::assertSame( $expected, $subject->wpcf7_shortcode( $output, 'contact-form', [], [] ) );
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
