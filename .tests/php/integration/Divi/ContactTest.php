<?php
/**
 * ContactTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Divi;

use HCaptcha\Divi\Contact;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class ContactTest.
 *
 * @group divi
 */
class ContactTest extends HCaptchaWPTestCase {

	/**
	 * Contact form nonce field.
	 *
	 * @var string
	 */
	private $cf_nonce_field = '_wpnonce-et-pb-contact-form-submitted-0';

	/**
	 * Contact form submit field.
	 *
	 * @var string
	 */
	private $submit_field = 'et_pb_contactform_submit_0';

	/**
	 * Contact form current form field.
	 *
	 * @var string
	 */
	private $current_form_field = 'et_pb_contact_email_fields_0';

	/**
	 * Tear down test.
	 *
	 * @noinspection PhpLanguageLevelInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function tearDown(): void { // phpcs:ignore PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		unset(
			$_POST[ $this->cf_nonce_field ],
			$_POST[ $this->submit_field ]
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 */
	public function test_constructor_and_init_hooks() {
		$subject = new Contact();

		self::assertSame(
			10,
			has_filter( 'et_pb_contact_form_shortcode_output', [ $subject, 'add_captcha' ] )
		);
		self::assertSame(
			10,
			has_filter( 'pre_do_shortcode_tag', [ $subject, 'verify' ] )
		);

		self::assertSame(
			10,
			has_filter( 'et_pb_module_shortcode_attributes', [ $subject, 'shortcode_attributes' ] )
		);
		self::assertSame(
			10,
			has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] )
		);
	}

	/**
	 * Test add_captcha().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_captcha() {
		FunctionMocker::replace( 'et_core_is_fb_enabled', false );

		$output = '
			<div id="et_pb_contact_form_0" class="et_pb_module et_pb_contact_form_0 et_pb_contact_form_container clearfix" data-form_unique_num="0">
				
				
				
				<div class="et-pb-contact-message"><p class="et_pb_contact_error_text">Make sure you entered the captcha.</p></div>
				
				<div class="et_pb_contact">
					<form class="et_pb_contact_form clearfix" method="post" action="http://test.test/divi/?XDEBUG_SESSION_START=18543">
						<p class="et_pb_contact_field et_pb_contact_field_0 et_pb_contact_field_half" data-id="name" data-type="input">
				
				
				<label for="et_pb_contact_name_0" class="et_pb_contact_form_label">Name</label>
				<input type="text" id="et_pb_contact_name_0" class="input" value="Igor Gergel" name="et_pb_contact_name_0" data-required_mark="required" data-field_type="input" data-original_id="name" placeholder="Name">
			</p><p class="et_pb_contact_field et_pb_contact_field_1 et_pb_contact_field_half et_pb_contact_field_last" data-id="email" data-type="email">
				
				
				<label for="et_pb_contact_email_0" class="et_pb_contact_form_label">Email Address</label>
				<input type="text" id="et_pb_contact_email_0" class="input" value="info@kagg.eu" name="et_pb_contact_email_0" data-required_mark="required" data-field_type="email" data-original_id="email" placeholder="Email Address">
			</p><p class="et_pb_contact_field et_pb_contact_field_2 et_pb_contact_field_last" data-id="message" data-type="text">
				
				
				<label for="et_pb_contact_message_0" class="et_pb_contact_form_label">Message</label>
				<textarea name="et_pb_contact_message_0" id="et_pb_contact_message_0" class="et_pb_contact_message input" data-required_mark="required" data-field_type="text" data-original_id="message" placeholder="Message">я</textarea>
			</p>
						<input type="hidden" value="et_contact_proccess" name="et_pb_contactform_submit_0"/>
						<div class="et_contact_bottom_container">
							
			<div class="et_pb_contact_right">
				<p class="clearfix">
					<span class="et_pb_contact_captcha_question">3 + 13</span> = <input type="text" size="2" class="input et_pb_contact_captcha" data-first_digit="3" data-second_digit="13" value="" name="et_pb_contact_captcha_0" data-required_mark="required" autocomplete="off">
				</p>
			</div><!-- .et_pb_contact_right -->
							<button type="submit" name="et_builder_submit_button" class="et_pb_contact_submit et_pb_button">Submit</button>
						</div>
						<input type="hidden" id="_wpnonce-et-pb-contact-form-submitted-0" name="_wpnonce-et-pb-contact-form-submitted-0" value="f8255b904d" /><input type="hidden" name="_wp_http_referer" value="/divi/?XDEBUG_SESSION_START=18543" />
					</form>
				</div> <!-- .et_pb_contact -->
			</div> <!-- .et_pb_contact_form_container -->
			';

		$module_slug = 'et_pb_contact_form';

		$expected = '
			<div id="et_pb_contact_form_0" class="et_pb_module et_pb_contact_form_0 et_pb_contact_form_container clearfix" data-form_unique_num="0">
				
				
				
				<div class="et-pb-contact-message"><p class="et_pb_contact_error_text">Make sure you entered the captcha.</p></div>
				
				<div class="et_pb_contact">
					<form class="et_pb_contact_form clearfix" method="post" action="http://test.test/divi/?XDEBUG_SESSION_START=18543">
						<p class="et_pb_contact_field et_pb_contact_field_0 et_pb_contact_field_half" data-id="name" data-type="input">
				
				
				<label for="et_pb_contact_name_0" class="et_pb_contact_form_label">Name</label>
				<input type="text" id="et_pb_contact_name_0" class="input" value="Igor Gergel" name="et_pb_contact_name_0" data-required_mark="required" data-field_type="input" data-original_id="name" placeholder="Name">
			</p><p class="et_pb_contact_field et_pb_contact_field_1 et_pb_contact_field_half et_pb_contact_field_last" data-id="email" data-type="email">
				
				
				<label for="et_pb_contact_email_0" class="et_pb_contact_form_label">Email Address</label>
				<input type="text" id="et_pb_contact_email_0" class="input" value="info@kagg.eu" name="et_pb_contact_email_0" data-required_mark="required" data-field_type="email" data-original_id="email" placeholder="Email Address">
			</p><p class="et_pb_contact_field et_pb_contact_field_2 et_pb_contact_field_last" data-id="message" data-type="text">
				
				
				<label for="et_pb_contact_message_0" class="et_pb_contact_form_label">Message</label>
				<textarea name="et_pb_contact_message_0" id="et_pb_contact_message_0" class="et_pb_contact_message input" data-required_mark="required" data-field_type="text" data-original_id="message" placeholder="Message">я</textarea>
			</p>
						<input type="hidden" value="et_contact_proccess" name="et_pb_contactform_submit_0"/>
						<div style="float:right;">' . $this->get_hcap_form( 'hcaptcha_divi_cf', 'hcaptcha_divi_cf_nonce' ) . '</div>
<div style="clear: both;"></div>
<div class="et_contact_bottom_container">
							
			
							<button type="submit" name="et_builder_submit_button" class="et_pb_contact_submit et_pb_button">Submit</button>
						</div>
						<input type="hidden" id="_wpnonce-et-pb-contact-form-submitted-0" name="_wpnonce-et-pb-contact-form-submitted-0" value="f8255b904d" /><input type="hidden" name="_wp_http_referer" value="/divi/?XDEBUG_SESSION_START=18543" />
					</form>
				</div> <!-- .et_pb_contact -->
			</div> <!-- .et_pb_contact_form_container -->
			';

		hcaptcha()->init_hooks();

		$subject = new Contact();

		self::assertSame( 0, $this->get_protected_property( $subject, 'render_count' ) );
		self::assertSame( $expected, $subject->add_captcha( $output, $module_slug ) );
		self::assertSame( 1, $this->get_protected_property( $subject, 'render_count' ) );
	}

	/**
	 * Test add_captcha() in frontend builder.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_add_captcha_in_frontend_builder() {
		FunctionMocker::replace( 'et_core_is_fb_enabled', true );

		$output      = [ 'some array' ];
		$module_slug = 'et_pb_contact_form';

		$subject = new Contact();

		self::assertSame( 0, $this->get_protected_property( $subject, 'render_count' ) );
		self::assertSame( $output, $subject->add_captcha( $output, $module_slug ) );
		self::assertSame( 0, $this->get_protected_property( $subject, 'render_count' ) );
	}

	/**
	 * Test verify().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify() {
		$return = 'some html';
		$tag    = 'et_pb_contact_form';

		$nonce                          = wp_create_nonce( 'et-pb-contact-form-submit' );
		$_POST[ $this->cf_nonce_field ] = $nonce;

		$_POST[ $this->submit_field ] = 'submit';

		$current_form_fields                = '[{&#34;field_id&#34;:&#34;et_pb_contact_name_0&#34;,&#34;original_id&#34;:&#34;name&#34;,&#34;required_mark&#34;:&#34;required&#34;,&#34;field_type&#34;:&#34;input&#34;,&#34;field_label&#34;:&#34;Name&#34;},{&#34;field_id&#34;:&#34;et_pb_contact_email_0&#34;,&#34;original_id&#34;:&#34;email&#34;,&#34;required_mark&#34;:&#34;required&#34;,&#34;field_type&#34;:&#34;email&#34;,&#34;field_label&#34;:&#34;Email Address&#34;},{&#34;field_id&#34;:&#34;et_pb_contact_message_0&#34;,&#34;original_id&#34;:&#34;message&#34;,&#34;required_mark&#34;:&#34;required&#34;,&#34;field_type&#34;:&#34;text&#34;,&#34;field_label&#34;:&#34;Message&#34;},{&#34;field_id&#34;:&#34;h-captcha-response-0lwsv53iy61b&#34;,&#34;original_id&#34;:&#34;&#34;,&#34;required_mark&#34;:&#34;not_required&#34;,&#34;field_type&#34;:&#34;text&#34;,&#34;field_label&#34;:&#34;&#34;}]';
		$_POST[ $this->current_form_field ] = $current_form_fields;
		$expected_current_form_fields       = '[{"field_id":"et_pb_contact_name_0","original_id":"name","required_mark":"required","field_type":"input","field_label":"Name"},{"field_id":"et_pb_contact_email_0","original_id":"email","required_mark":"required","field_type":"email","field_label":"Email Address"},{"field_id":"et_pb_contact_message_0","original_id":"message","required_mark":"required","field_type":"text","field_label":"Message"}]';

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );

		FunctionMocker::replace(
			'filter_input',
			function ( $type, $var_name, $filter ) use ( $nonce, $current_form_fields ) {
				if (
					INPUT_POST === $type &&
					$this->cf_nonce_field === $var_name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $nonce;
				}

				if (
					INPUT_POST === $type &&
					$this->current_form_field === $var_name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $current_form_fields;
				}

				return null;
			}
		);

		$subject = new Contact();

		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
		self::assertEquals( $return, $subject->verify( $return, $tag, [], [] ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		self::assertEquals( $expected_current_form_fields, $_POST[ $this->current_form_field ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_not_verified() {
		$return = 'some html';
		$tag    = 'et_pb_contact_form';

		$nonce                          = wp_create_nonce( 'et-pb-contact-form-submit' );
		$_POST[ $this->cf_nonce_field ] = $nonce;

		$_POST[ $this->submit_field ] = 'submit';

		$current_form_fields                = '[{&#34;field_id&#34;:&#34;et_pb_contact_name_0&#34;,&#34;original_id&#34;:&#34;name&#34;,&#34;required_mark&#34;:&#34;required&#34;,&#34;field_type&#34;:&#34;input&#34;,&#34;field_label&#34;:&#34;Name&#34;},{&#34;field_id&#34;:&#34;et_pb_contact_email_0&#34;,&#34;original_id&#34;:&#34;email&#34;,&#34;required_mark&#34;:&#34;required&#34;,&#34;field_type&#34;:&#34;email&#34;,&#34;field_label&#34;:&#34;Email Address&#34;},{&#34;field_id&#34;:&#34;et_pb_contact_message_0&#34;,&#34;original_id&#34;:&#34;message&#34;,&#34;required_mark&#34;:&#34;required&#34;,&#34;field_type&#34;:&#34;text&#34;,&#34;field_label&#34;:&#34;Message&#34;},{&#34;field_id&#34;:&#34;h-captcha-response-0lwsv53iy61b&#34;,&#34;original_id&#34;:&#34;&#34;,&#34;required_mark&#34;:&#34;not_required&#34;,&#34;field_type&#34;:&#34;text&#34;,&#34;field_label&#34;:&#34;&#34;}]';
		$_POST[ $this->current_form_field ] = $current_form_fields;
		$expected_current_form_fields       = '[{"field_id":"et_pb_contact_name_0","original_id":"name","required_mark":"required","field_type":"input","field_label":"Name"},{"field_id":"et_pb_contact_email_0","original_id":"email","required_mark":"required","field_type":"email","field_label":"Email Address"},{"field_id":"et_pb_contact_message_0","original_id":"message","required_mark":"required","field_type":"text","field_label":"Message"}]';

		$this->prepare_hcaptcha_get_verify_message_html( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf', false );

		FunctionMocker::replace(
			'filter_input',
			function ( $type, $var_name, $filter ) use ( $nonce, $current_form_fields ) {
				if (
					INPUT_POST === $type &&
					$this->cf_nonce_field === $var_name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $nonce;
				}

				if (
					INPUT_POST === $type &&
					$this->current_form_field === $var_name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $current_form_fields;
				}

				return null;
			}
		);

		$subject = new Contact();

		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
		self::assertEquals( $return, $subject->verify( $return, $tag, [], [] ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		self::assertEquals( $expected_current_form_fields, $_POST[ $this->current_form_field ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		self::assertSame( 'on', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Test verify() with wrong tag.
	 */
	public function test_verify_wrong_tag() {
		$return = 'some html';
		$tag    = 'wrong tag';

		$subject = new Contact();

		self::assertEquals( $return, $subject->verify( $return, $tag, [], [] ) );
	}

	/**
	 * Test shortcode_attributes().
	 *
	 * @param string|null $captcha     Current captcha in props.
	 * @param string      $own_captcha Own captcha in Contact class.
	 *
	 * @dataProvider dp_test_shortcode_attributes
	 * @throws ReflectionException ReflectionException.
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function test_shortcode_attributes( $captcha, string $own_captcha ) {
		$props    = [ 'foo' => 'bar' ];
		$attrs    = [];
		$slug     = 'et_pb_contact_form';
		$_address = '0.0.0.0';
		$content  = 'some content';

		if ( $captcha ) {
			$props['captcha'] = $captcha;
		}

		$expected                     = $props;
		$expected['captcha']          = $own_captcha;
		$expected['use_spam_service'] = $own_captcha;

		$subject = new Contact();
		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
		$this->set_protected_property( $subject, 'captcha', $own_captcha );

		self::assertSame( $expected, $subject->shortcode_attributes( $props, $attrs, $slug, $_address, $content ) );
		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Data provider for dp_test_shortcode_attributes().
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
	 * Test shortcode_attributes() with wrong slug.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_shortcode_attributes_with_wrong_slug() {
		$props    = [
			'foo'     => 'bar',
			'captcha' => 'some',
		];
		$attrs    = [];
		$slug     = 'wrong';
		$_address = '0.0.0.0';
		$content  = 'some content';

		$expected = $props;

		$subject = new Contact();
		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );

		self::assertSame( $expected, $subject->shortcode_attributes( $props, $attrs, $slug, $_address, $content ) );
		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts() {
		$subject = new Contact();

		self::assertFalse( wp_script_is( 'hcaptcha-divi' ) );

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-divi' ) );
	}
}
