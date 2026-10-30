<?php
/**
 * ContactTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Divi;

use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET_Builder_Module_Contact_Form;
use HCaptcha\Divi\Contact;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
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
	 * Set up the test.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

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
	 * Test honeypot output and widget source for a Divi component.
	 *
	 * @param string $component Active Divi component.
	 * @param string $source    Expected source.
	 *
	 * @dataProvider dp_test_honeypot_for_component
	 * @return void
	 */
	public function test_honeypot_for_component( string $component, string $source ): void {
		$subject = Mockery::mock( Contact::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_divi_component' )->andReturn( $component );

		$output = $subject->add_hcaptcha(
			'<form><div class="et_contact_bottom_container"></div></form>',
			'et_pb_contact_form'
		);
		$id     = HCaptcha::widget_id_value(
			[
				'source'  => [ $source ],
				'form_id' => 'contact',
			]
		);

		self::assertStringContainsString( 'value="' . esc_attr( $id ) . '"', $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test that a filled Divi component contact honeypot is rejected.
	 *
	 * @param string $component Active Divi component.
	 * @param string $source    Expected source.
	 *
	 * @dataProvider dp_test_honeypot_for_component
	 * @return void
	 */
	public function test_filled_honeypot_for_component( string $component, string $source ): void {
		$this->prepare_verify_post( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ $source ],
				'form_id' => 'contact',
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';
		$_POST['et_pb_contactform_submit_0']   = 'et_contact_proccess';

		$subject = Mockery::mock( Contact::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_active_divi_component' )->andReturn( $component );

		self::assertFalse( $subject->verify_4( false, 'et_pb_contact_form', [], [] ) );
		self::assertSame( 'on', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Data provider for Divi component honeypot tests.
	 *
	 * @return array
	 */
	public function dp_test_honeypot_for_component(): array {
		return [
			'Divi Builder' => [ 'divi_builder', 'divi-builder/divi-builder.php' ],
			'Extra theme'  => [ 'extra', 'Extra' ],
		];
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
	 * Divi 5 skips requests outside its contact form submission route.
	 */
	public function test_verify_5_ignores_other_requests(): void {
		$subject                   = new Contact();
		$attrs                     = [ 'unchanged' => true ];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		self::assertSame( $attrs, $subject->verify_5( $attrs, [ 'name' => 'divi/contact-form' ] ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		self::assertSame( $attrs, $subject->verify_5( $attrs, [ 'name' => 'divi/other' ] ) );
	}

	/**
	 * A missing Divi 5 block leaves module attributes unchanged.
	 */
	public function test_verify_5_ignores_missing_block(): void {
		if ( ! class_exists( BlockParserStore::class ) ) {
			require_once dirname( __DIR__ ) . '/Stubs/ET/Builder/FrontEnd/BlockParser/BlockParserStore.php';
		}
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$attrs                     = [ 'unchanged' => true ];
		$subject                   = new Contact();

		self::assertSame(
			$attrs,
			$subject->verify_5(
				$attrs,
				[
					'name'          => 'divi/contact-form',
					'id'            => 'missing',
					'storeInstance' => null,
				]
			)
		);
	}

	/**
	 * Divi form fields contribute only ordinary name and email data to the entry.
	 */
	public function test_entry_collects_native_contact_fields(): void {
		$subject                           = new Contact();
		$method                            = $this->set_method_accessibility( $subject, 'get_entry' );
		$_POST['et_pb_contact_name_0']     = 'Jane Doe';
		$_POST['et_pb_contact_email_0']    = 'person@example.com';
		$_POST['et_pb_contact_password_0'] = 'secret';
		$fields                            = [
			[
				'field_type'  => 'input',
				'field_id'    => 'et_pb_contact_name_0',
				'original_id' => 'name',
				'field_label' => 'Name',
			],
			[
				'field_type'  => 'email',
				'field_id'    => 'et_pb_contact_email_0',
				'original_id' => 'email',
				'field_label' => 'Email',
			],
			[
				'field_type'  => 'input',
				'field_id'    => 'et_pb_contact_password_0',
				'original_id' => 'password',
				'field_label' => 'Password',
			],
		];

		$entry = $method->invoke( $subject, $fields );
		self::assertSame( 'Jane Doe', $entry['name'] );
		self::assertSame( 'person@example.com', $entry['email'] );
		self::assertArrayNotHasKey( 'Password', $entry['data'] );
	}

	/**
	 * Divi 5 field values follow its own text and checkbox normalization.
	 */
	public function test_block_field_value_normalization(): void {
		$subject          = new Contact();
		$method           = $this->set_method_accessibility( $subject, 'get_block_field_value' );
		$_POST['message'] = "First line\nSecond line";
		$_POST['choices'] = '';
		self::assertSame( "First line\nSecond line", $method->invoke( $subject, 'message', 'text' ) );
		self::assertSame( [], $method->invoke( $subject, 'choices', 'checkbox' ) );
	}

	/**
	 * Divi 4 removes hCaptcha metadata before comparing submitted form fields.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_4_filters_submitted_field_metadata(): void {
		$this->prepare_verify_post( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'Divi' ],
				'form_id' => 'contact',
			]
		);
		$_POST['et_pb_contactform_submit_0']   = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_0']    = '';
		$_POST['et_pb_contact_name_0']         = 'Jane';
		$fields                                = [
			[
				'field_type'  => 'input',
				'field_id'    => 'et_pb_contact_name_0',
				'original_id' => 'name',
				'field_label' => 'Name',
			],
			[
				'field_type'  => 'text',
				'field_id'    => 'h-captcha-response',
				'original_id' => 'captcha',
				'field_label' => '',
			],
		];
		$encoded                               = wp_json_encode( $fields );
		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name ) use ( $encoded ) {
				return 'et_pb_contact_email_fields_0' === $name ? $encoded : FunctionMocker::callOriginal();
			}
		);

		$subject = new Contact();
		$subject->verify_4( false, 'et_pb_contact_form', [], [] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Assert the raw POST value written by the filter under test.
		$filtered = json_decode( wp_unslash( $_POST['et_pb_contact_email_fields_0'] ), true );
		self::assertCount( 1, $filtered );
		self::assertSame( 'et_pb_contact_name_0', $filtered[0]['field_id'] );
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
	 * A forged earlier counter cannot select a filled honeypot instead of the active form.
	 *
	 * @return void
	 */
	public function test_sec050_forged_earlier_submit_counter(): void {
		$_POST['et_pb_contactform_submit_999'] = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_999']  = 'bot';
		$_POST['et_pb_contactform_submit_0']   = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_0']    = '';

		$subject = new Contact();
		$subject->verify_4( false, 'et_pb_contact_form', [], [] );

		self::assertSame( 'on', $this->get_protected_property( $subject, 'captcha' ) );
		self::assertSame(
			[
				'captcha'          => 'on',
				'use_spam_service' => 'on',
			],
			$subject->shortcode_attributes( [], [], 'et_pb_contact_form', '', '' )
		);
	}

	/**
	 * The rendered form counter remains authoritative after an earlier form.
	 *
	 * @return void
	 */
	public function test_sec050_later_rendered_form_counter(): void {
		$_POST['et_pb_contactform_submit_0'] = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_0']  = 'bot';
		$_POST['et_pb_contactform_submit_1'] = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_1']  = '';

		$subject = new Contact();
		$this->set_protected_property( $subject, 'render_count', 1 );
		$subject->verify_4( false, 'et_pb_contact_form', [], [] );

		self::assertSame( 'on', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * A real filled Divi 4 honeypot remains native-rejected without hCaptcha verification.
	 *
	 * @return void
	 */
	public function test_sec050_actual_honeypot_control(): void {
		$_POST['et_pb_contactform_submit_999'] = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_999']  = '';
		$_POST['et_pb_contactform_submit_0']   = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_0']    = 'bot';

		$subject = new Contact();
		$subject->verify_4( false, 'et_pb_contact_form', [], [] );

		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
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
	/**
	 * Apply email policy using the server shortcode fields rather than client type claims.
	 *
	 * @param bool $blocked Whether the trusted email is disposable.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_typed_email_policy( bool $blocked ): void {
		$this->prepare_verify_post( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]    = HCaptcha::widget_id_value(
			[
				'source'  => [ 'Divi' ],
				'form_id' => 'contact',
			]
		);
		$_POST['et_pb_contactform_submit_0']      = 'et_contact_proccess';
		$_POST['et_pb_contact_billing_address_0'] = $blocked ? 'person@sec063-disposable.test' : 'person@example.com';
		$_POST['et_pb_contact_contact_address_0'] = 'reader@example.com';
		$_POST['et_pb_contact_email_0']           = 'spoof@example.com';
		$subject                                  = new Contact();
		$subject->verify_4(
			false,
			'et_pb_contact_form',
			[],
			[ 5 => '[et_pb_contact_field field_type="email" field_id="billing_address" field_title="Payment contact" /][et_pb_contact_field field_type="email" field_id="contact_address" field_title="Другой адрес" /][et_pb_contact_field field_type="input" field_id="email" /]' ]
		);

		self::assertSame( $blocked ? 'on' : 'off', $this->get_protected_property( $subject, 'captcha' ) );
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
	 * Exclude stale conditional emails only when native stored fields permit hiding.
	 *
	 * @param string $conditional Native conditional field status.
	 * @param bool   $blocked     Whether the address remains effective.
	 *
	 * @dataProvider dp_sec063_hidden_email_policy
	 * @return void
	 */
	public function test_sec063_hidden_email_policy( string $conditional, bool $blocked ): void {
		$this->prepare_verify_post( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ]        = HCaptcha::widget_id_value(
			[
				'source'  => [ 'Divi' ],
				'form_id' => 'contact',
			]
		);
		$_POST['et_pb_contactform_submit_0']          = 'et_contact_proccess';
		$_POST['et_pb_contact_billing_address_0']     = 'person@sec063-disposable.test';
		$_POST['et_pb_contact_contact_address_0']     = 'reader@example.com';
		$_POST['et_pb_contact_email_hidden_fields_0'] = wp_slash( wp_json_encode( [ 'billing_address', 'contact_address' ] ) );
		$content                                      = '[et_pb_contact_field field_type="email" field_id="billing_address" conditional_logic="' . $conditional . '" /][et_pb_contact_field field_type="email" field_id="contact_address" conditional_logic="off" /]';
		$native                                       = new ET_Builder_Module_Contact_Form();
		$map = $native->get_form_map( $content, 0, [ 'billing_address', 'contact_address' ] );
		self::assertCount( $blocked ? 2 : 1, $map['fields'] );
		$subject = new Contact();
		$subject->verify_4( false, 'et_pb_contact_form', [], [ 5 => $content ] );

		self::assertSame( $blocked ? 'on' : 'off', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Native conditional and nonconditional hidden-address controls.
	 *
	 * @return array
	 */
	public function dp_sec063_hidden_email_policy(): array {
		return [
			'conditional stale address allowed' => [ 'on', false ],
			'nonconditional address blocked'    => [ 'off', true ],
		];
	}
}
