<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\HTMLForms;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\HTMLForms\Form;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test the HTML Forms integration.
 *
 * @group html-forms
 */
class FormTest extends HCaptchaPluginWPTestCase {
	/**
	 * Preview rendering includes spacing styles next to the protected form.
	 */
	public function test_preview_form_renders_inline_styles(): void {
		$_GET['hf_preview_form'] = '42';
		$form                    = new \HTML_Forms\Form( 42 );
		$output                  = ( new Form() )->add_captcha( '<p><input type="submit" value="Send"></p>', $form );

		self::assertStringContainsString( '#form-preview .h-captcha', $output );
		self::assertStringContainsString( 'name="html_forms_form_nonce"', $output );
	}

	/**
	 * The editor adds one challenge and leaves an existing challenge untouched.
	 */
	public function test_editor_fields_are_protected_once(): void {
		$form         = new \HTML_Forms\Form( 42 );
		$form->markup = '<p><input type="submit" value="Send"></p>';
		$subject      = new Form();
		$subject->add_to_fields( $form );
		$once = $form->markup;

		self::assertStringContainsString( 'h-captcha', $once );
		$subject->add_to_fields( $form );
		self::assertSame( $once, $form->markup );
	}

	/**
	 * Editor saves strip generated challenge and nonce fields from HTML Forms posts.
	 */
	public function test_saved_markup_removes_generated_fields(): void {
		$subject = new Form();
		$html    = '<p>Keep</p><div class="h-captcha">Challenge</div>' .
			'<input type="hidden" id="html_forms_form_nonce" value="nonce" />' .
			'<input type="hidden" name="_wp_http_referer" value="/form" />';
		$data    = [ 'post_content' => wp_slash( $html ) ];

		self::assertSame( $data, $subject->insert_post_data( $data, [ 'post_type' => 'post' ], [], false ) );
		self::assertSame(
			[ 'post_content' => '<p>Keep</p>' ],
			$subject->insert_post_data( $data, [ 'post_type' => 'html-form' ], [], true )
		);
	}


	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'html-forms/html-forms.php';

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'html_forms_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_form_contains_honeypot(): void {
		$form = new \HTML_Forms\Form( 42 );

		$output = ( new Form() )->add_captcha(
			'<form><p><input type="submit" value="Send"></p></form>',
			$form
		);

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test that a filled honeypot blocks form processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'html_forms_form_nonce', 'html_forms_form' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'html-forms/html-forms.php' ],
				'form_id' => 42,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$form    = new \HTML_Forms\Form( 42 );
		$subject = new Form();

		self::assertSame( 'hcaptcha_error', $subject->verify( '', $form, [] ) );
		self::assertSame( 'Anti-spam check failed.', $subject->get_message( 'hcaptcha_error' ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-html-forms' ) );
		self::assertSame(
			[ 'hcaptcha' ],
			wp_scripts()->registered['hcaptcha-html-forms']->deps
		);
	}
	/**
	 * Apply email policy to the native configured custom email field.
	 *
	 * @param bool $blocked Whether the trusted email is disposable.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_custom_email_policy( bool $blocked ): void {
		$this->prepare_verify_post( 'html_forms_form_nonce', 'html_forms_form' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'html-forms/html-forms.php' ],
				'form_id' => 42,
			]
		);
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$form           = new \HTML_Forms\Form( 42 );
		$form->settings = [ 'email_fields' => 'contact_address' ];
		$subject        = new Form();

		self::assertSame( $blocked ? 'hcaptcha_error' : '', $subject->verify( '', $form, [ 'contact_address' => $blocked ? 'person@sec063-disposable.test' : 'person@example.com' ] ) );
		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, $subject->get_message( 'hcaptcha_error' ) );
	}
	/**
	 * Exercise both policy rejection and permanent address acceptance.
	 *
	 * @return array
	 */
	public function dp_sec063_email_policy(): array {
		return [
			'disposable' => [ true ],
			'permanent'  => [ false ],
		];
	}
	/**
	 * Resolve the exact dot path used by native configured email validation.
	 *
	 * @param bool $blocked Whether the native email is disposable.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_nested_email_policy( bool $blocked ): void {
		$this->prepare_verify_post( 'html_forms_form_nonce', 'html_forms_form' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'html-forms/html-forms.php' ],
				'form_id' => 42,
			]
		);
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$form           = new \HTML_Forms\Form( 42 );
		$form->settings = [ 'email_fields' => 'contact.address' ];
		$subject        = new Form();
		self::assertSame(
			$blocked ? 'hcaptcha_error' : '',
			$subject->verify(
				'',
				$form,
				[
					'contact' => [ 'address' => $blocked ? 'person@sec063-disposable.test' : 'reader@example.com' ],
					'email'   => 'spoof@sec063-disposable.test',
				]
			)
		);
		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, $subject->get_message( 'hcaptcha_error' ) );
	}
}
