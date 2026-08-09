<?php
/**
 * ContactTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\Contact;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionException;
use WP_Block;

/**
 * Class ContactTest.
 *
 * @group divi
 */
class ContactTest extends HCaptchaPluginWPTestCase {

	/**
	 * Theme stylesheet slug.
	 *
	 * @var string
	 */
	protected static string $theme = 'Divi';

	/**
	 * Live Divi shortcode modules used by the test.
	 *
	 * @var array<string, string>
	 */
	protected static array $theme_shortcode_classes = [
		'et_pb_contact_form' => 'ET_Builder_Module_Contact_Form',
	];

	/**
	 * Expected incorrect usage notices caused by the late theme load.
	 *
	 * @var string[]
	 */
	protected static array $theme_expected_incorrect_usage = [ "add_theme_support( 'title-tag' )" ];

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_dequeue_script( 'et-core-api-spam-recaptcha' );
		wp_dequeue_script( 'hcaptcha-divi' );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @return void
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Contact();

		self::assertSame( 10, has_filter( 'et_pb_contact_form_shortcode_output', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'pre_do_shortcode_tag', [ $subject, 'verify_4' ] ) );
		self::assertSame( 10, has_filter( 'et_pb_module_shortcode_attributes', [ $subject, 'shortcode_attributes' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test the live Divi Contact Form module.
	 *
	 * @return void
	 */
	public function test_live_contact_form_module(): void {
		update_option( 'hcaptcha_settings', [ 'divi_status' => [ 'contact' ] ] );
		hcaptcha()->init_hooks();

		new Contact();

		$output = do_shortcode( '[et_pb_contact_form captcha="on"][/et_pb_contact_form]' );

		self::assertStringContainsString( 'et_pb_contact_form_container', $output );
		self::assertStringContainsString( 'name="et_pb_contactform_submit_', $output );
		self::assertStringContainsString( 'class="hcaptcha-divi-wrapper"', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_divi_cf_nonce"', $output );
		self::assertStringNotContainsString( 'class="et_pb_contact_right"', $output );
	}

	/**
	 * Test add_hcaptcha_to_block().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_to_block(): void {
		$block_content = '<form><div class="et_contact_bottom_container"></div></form>';
		$subject       = new Contact();
		$block         = new WP_Block(
			[
				'blockName'    => 'divi/contact-form',
				'attrs'        => [],
				'innerBlocks'  => [],
				'innerHTML'    => '',
				'innerContent' => [],
			]
		);

		self::assertSame(
			$block_content,
			$subject->add_hcaptcha_to_block( $block_content, [ 'blockName' => 'core/paragraph' ], $block )
		);

		$output = $subject->add_hcaptcha_to_block(
			$block_content,
			[ 'blockName' => 'divi/contact-form' ],
			$block
		);

		self::assertStringContainsString( 'class="hcaptcha-divi-5-wrapper"', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_divi_cf_nonce"', $output );
	}

	/**
	 * Test add_hcaptcha() in the live Divi frontend builder state.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_add_hcaptcha_in_frontend_builder(): void {
		$output = '<form><div class="et_contact_bottom_container"></div></form>';

		add_filter( 'et_fb_is_enabled', '__return_true' );

		$subject = new Contact();

		self::assertTrue( et_core_is_fb_enabled() );
		self::assertSame( 0, $this->get_protected_property( $subject, 'render_count' ) );
		self::assertSame( $output, $subject->add_hcaptcha( $output, 'et_pb_contact_form' ) );
		self::assertSame( 0, $this->get_protected_property( $subject, 'render_count' ) );
	}

	/**
	 * Test filtering malformed submitted field metadata.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_filter_fields_data_rejects_nested_values(): void {
		$valid     = [
			'field_id'    => 'et_pb_contact_name_0',
			'field_type'  => 'input',
			'original_id' => 'name',
			'field_label' => 'Name',
		];
		$malformed = [
			'scalar',
			[ 'field_id' => [] ],
			[
				'field_id'    => 'et_pb_contact_email_0',
				'field_type'  => 'email',
				'field_label' => [],
			],
			[
				'field_id'    => 'h-captcha-response',
				'field_type'  => 'text',
				'field_label' => '',
			],
		];

		$subject = new Contact();
		$method  = $this->set_method_accessibility( $subject, 'filter_fields_data' );

		self::assertSame( [ $valid ], $method->invoke( $subject, array_merge( [ $valid ], $malformed ) ) );
	}

	/**
	 * Test verify_4() with the wrong shortcode tag.
	 *
	 * @return void
	 */
	public function test_verify_4_with_wrong_tag(): void {
		$subject = new Contact();

		self::assertSame( 'some html', $subject->verify_4( 'some html', 'wrong-tag', [], [] ) );
	}

	/**
	 * Test shortcode_attributes().
	 *
	 * @param string|null $captcha     Current captcha in props.
	 * @param string      $own_captcha Own captcha in Contact class.
	 *
	 * @dataProvider dp_test_shortcode_attributes
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_shortcode_attributes( $captcha, string $own_captcha ): void {
		$props = [ 'foo' => 'bar' ];

		if ( $captcha ) {
			$props['captcha'] = $captcha;
		}

		$expected                     = $props;
		$expected['captcha']          = $own_captcha;
		$expected['use_spam_service'] = $own_captcha;

		$subject = new Contact();
		$this->set_protected_property( $subject, 'captcha', $own_captcha );

		self::assertSame(
			$expected,
			$subject->shortcode_attributes( $props, [], 'et_pb_contact_form', '0.0.0.0', 'some content' )
		);
		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Data provider for test_shortcode_attributes().
	 *
	 * @return array
	 */
	public function dp_test_shortcode_attributes(): array {
		return [
			'in props no captcha, own off' => [ null, 'off' ],
			'in props no captcha, own on'  => [ null, 'on' ],
			'in props off, own off'        => [ 'off', 'off' ],
			'in props off, own on'         => [ 'off', 'on' ],
			'in props on, own off'         => [ 'on', 'off' ],
			'in props on, own on'          => [ 'on', 'on' ],
		];
	}

	/**
	 * Test shortcode_attributes() with the wrong slug.
	 *
	 * @return void
	 */
	public function test_shortcode_attributes_with_wrong_slug(): void {
		$props   = [ 'captcha' => 'some' ];
		$subject = new Contact();

		self::assertSame( $props, $subject->shortcode_attributes( $props, [], 'wrong', '0.0.0.0', '' ) );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles(): void {
		$subject = new Contact();

		ob_start();
		$subject->print_inline_styles();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( '<style>', $output );
		self::assertStringContainsString( '.hcaptcha-divi-wrapper', $output );
		self::assertStringContainsString( '.hcaptcha-divi-5-wrapper', $output );
		self::assertStringContainsString( '</style>', $output );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		hcaptcha()->form_shown = true;

		wp_register_script( 'et-recaptcha-v3', 'https://example.com/recaptcha-api.js', [], '1.0.0', true );
		wp_register_script( 'es6-promise', 'https://example.com/es6-promise.js', [], '1.0.0', true );
		wp_enqueue_script( 'et-core-api-spam-recaptcha', 'https://example.com/recaptcha.js', [], '1.0.0', true );

		$subject = new Contact();
		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'et-recaptcha-v3', 'registered' ) );
		self::assertFalse( wp_script_is( 'es6-promise', 'registered' ) );
		self::assertFalse( wp_script_is( 'et-core-api-spam-recaptcha' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-divi' ) );
	}

	/**
	 * Test enqueue_scripts() when hCaptcha was not shown.
	 *
	 * @return void
	 */
	public function test_enqueue_scripts_when_hcaptcha_was_not_shown(): void {
		wp_enqueue_script( 'et-core-api-spam-recaptcha', 'https://example.com/recaptcha.js', [], '1.0.0', true );

		$subject = new Contact();
		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'et-core-api-spam-recaptcha' ) );
		self::assertFalse( wp_script_is( 'hcaptcha-divi' ) );
	}
}
