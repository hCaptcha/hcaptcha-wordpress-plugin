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

namespace HCaptcha\Tests\Integration\Spectra;

use HCaptcha\Spectra\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use WP_Block;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Form class.
 *
 * @group spectra
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 9, has_filter( 'wp_ajax_uagb_process_forms', [ $subject, 'process_ajax' ] ) );
		self::assertSame( 9, has_filter( 'wp_ajax_nopriv_uagb_process_forms', [ $subject, 'process_ajax' ] ) );

		self::assertSame( 10, has_action( 'render_block', [ $subject, 'render_block' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test init_hooks() not on frontend.
	 *
	 * @return void
	 */
	public function test_init_hooks_not_on_frontend(): void {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = new Form();

		self::assertSame( 9, has_filter( 'wp_ajax_uagb_process_forms', [ $subject, 'process_ajax' ] ) );
		self::assertSame( 9, has_filter( 'wp_ajax_nopriv_uagb_process_forms', [ $subject, 'process_ajax' ] ) );

		self::assertFalse( has_action( 'render_block', [ $subject, 'render_block' ] ) );
		self::assertFalse( has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertFalse( has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
		self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test render_block().
	 *
	 * @return void
	 */
	public function test_render_block(): void {
		$placeholder = '===hcaptcha placeholder===';
		$form_id     = 1;
		$block       = [
			'blockName' => 'some block',
		];
		$instance    = Mockery::mock( WP_Block::class );
		$template    = <<<HTML
<div class="wp-block-uagb-forms uagb-forms__outer-wrap uagb-block-f89cebda uagb-forms__medium-btn">
	<form class="uagb-forms-main-form" method="post" autocomplete="on" name="uagb-form-f89cebda">
		<div class="wp-block-uagb-forms-name uagb-forms-name-wrap uagb-forms-field-set uagb-block-046ed4b7">
			<div class="uagb-forms-name-label required uagb-forms-input-label" id="046ed4b7">First Name</div>
			<input type="text" placeholder="John" required class="uagb-forms-name-input uagb-forms-input"
				   name="046ed4b7" autocomplete="given-name"/></div>
		<div class="wp-block-uagb-forms-name uagb-forms-name-wrap uagb-forms-field-set uagb-block-75b30804">
			<div class="uagb-forms-name-label required uagb-forms-input-label" id="75b30804">Last Name</div>
			<input type="text" placeholder="Doe" required class="uagb-forms-name-input uagb-forms-input" name="75b30804"
				   autocomplete="family-name"/></div>
		<div class="wp-block-uagb-forms-email uagb-forms-email-wrap uagb-forms-field-set uagb-block-9f2177c9">
			<div class="uagb-forms-email-label  uagb-forms-input-label" id="9f2177c9">Email</div>
			<input type="email" class="uagb-forms-email-input uagb-forms-input" placeholder="example@mail.com"
				   name="9f2177c9" autocomplete="email"/></div>
		<div class="wp-block-uagb-forms-textarea uagb-forms-textarea-wrap uagb-forms-field-set uagb-block-147f2552">
			<div class="uagb-forms-textarea-label required uagb-forms-input-label" id="147f2552">Message</div>
			<textarea required class="uagb-forms-textarea-input uagb-forms-input" rows="4"
					  placeholder="Enter your message" name="147f2552" autocomplete="off"></textarea></div>
		<div class="uagb-forms-form-hidden-data"><input type="hidden" class="uagb_forms_form_label"
														value="Spectra Form"/><input type="hidden"
																					 class="uagb_forms_form_id"
																					 value="uagb-form-f89cebda"/></div>
		<div class="uagb-form-reacaptcha-error-f89cebda"></div>
		$placeholder<div class="uagb-forms-main-submit-button-wrap wp-block-button">
			<button class="uagb-forms-main-submit-button wp-block-button__link">
				<div class="uagb-forms-main-submit-button-text">Submit</div>
			</button>
		</div>
	</form>
	<div class="uagb-forms-success-message-f89cebda uagb-forms-submit-message-hide"><span>The form has been submitted successfully!</span>
	</div>
	<div class="uagb-forms-failed-message-f89cebda uagb-forms-submit-message-hide"><span>There has been some error while submitting the form. Please verify all form fields again.</span>
	</div>
</div>
HTML;

		$subject = new Form();

		$content = 'some content';

		// Ignore non-form blocks.
		self::assertSame( $content, $subject->render_block( $content, $block, $instance ) );

		$content = str_replace( $placeholder, 'uagb-forms-recaptcha', $template );
		$block   = [
			'blockName' => 'uagb/forms',
			'attrs'     => [
				'block_id' => $form_id,
			],
		];

		// Do not replace reCaptcha.
		self::assertSame( $content, $subject->render_block( $content, $block, $instance ) );

		$args     = [
			'action' => 'hcaptcha_spectra_form',
			'name'   => 'hcaptcha_spectra_form_nonce',
			'id'     => [
				'source'  => [ 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php' ],
				'form_id' => $form_id,
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$content  = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha, $template );

		self::assertSame( $expected, $subject->render_block( $content, $block, $instance ) );
	}

	/**
	 * Test process_ajax().
	 *
	 * @return void
	 */
	public function test_process_ajax(): void {
		$nonce_field_name  = 'hcaptcha_spectra_form_nonce';
		$nonce_action_name = 'hcaptcha_spectra_form';
		$hcaptcha_response = 'some response';
		$form_data         = wp_json_encode(
			[
				'h-captcha-response' => $hcaptcha_response,
				$nonce_field_name    => wp_create_nonce( $nonce_action_name ),
				'hcaptcha-widget-id' => [ 'some widget' ],
				'test_input'         => 'some input',
				'hcap_hp_test'       => '',
				'hcap_hp_sig'        => wp_create_nonce( 'hcap_hp_test' ),
			]
		);

		$post_id = wp_insert_post( [ 'post_content' => 'some content' ] );

		$_POST['post_id']   = $post_id;
		$_POST['form_data'] = $form_data;

		$this->prepare_verify_request( $hcaptcha_response );

		add_filter( 'wp_doing_ajax', '__return_true' );

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'has_recaptcha' )->andReturn( false );

		$subject->process_ajax();
	}

	/**
	 * Test process_ajax() when not verified.
	 *
	 * @return void
	 */
	public function test_process_ajax_when_not_verified(): void {
		$nonce_field_name  = 'hcaptcha_spectra_form_nonce';
		$nonce_action_name = 'hcaptcha_spectra_form';
		$hcaptcha_response = 'some response';
		$form_data         = wp_json_encode(
			[
				'h-captcha-response' => $hcaptcha_response,
				$nonce_field_name    => wp_create_nonce( $nonce_action_name ),
			]
		);
		$response          = [
			'success' => false,
			'data'    => 'Please complete the hCaptcha.',
		];
		$expected          = [
			'',
			'',
			[ 'response' => null ],
		];

		$post_id = wp_insert_post( [ 'post_content' => 'some content' ] );

		$_POST['post_id'] = $post_id;

		$this->prepare_verify_request( $hcaptcha_response, false );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'has_recaptcha' )->andReturn( false );

		// Without form_data.
		ob_start();

		$subject->process_ajax();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $response ), ob_get_clean() );

		self::assertSame( $expected, $die_arr );

		// With form_data.
		$_POST['form_data'] = $form_data;

		ob_start();

		$subject->process_ajax();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		self::assertSame( json_encode( $response ), ob_get_clean() );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test process_ajax() when has recaptcha.
	 *
	 * @return void
	 */
	public function test_process_ajax_when_has_recaptcha(): void {
		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'has_recaptcha' )->andReturn( true );

		$subject->process_ajax();
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
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

		$expected = <<<'CSS'
	.uagb-forms-main-form .h-captcha {
		margin-bottom: 20px;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		// Show styles.
		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );

		// No output if already shown.
		ob_start();

		$subject->print_inline_styles();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_print_hcaptcha_scripts(): void {
		$subject = new Form();

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );

		$this->set_protected_property( $subject, 'has_recaptcha_field', true );

		self::assertFalse( $subject->print_hcaptcha_scripts( false ) );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		self::assertFalse( wp_script_is( 'hcaptcha-spectra' ) );

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-spectra' ) );
	}

	/**
	 * Test has_recaptcha().
	 *
	 * @return void
	 */
	public function test_has_recaptcha() {
		$block_id              = 'f89cebda';
		$recaptcha_placeholder = '=== recaptcha placeholder ===';
		$template              = <<<HTML
<!-- wp:uagb/forms {"block_id":"$block_id",$recaptcha_placeholder"labelAlignment":"left","fieldBorderTopWidth":1,"fieldBorderLeftWidth":1,"fieldBorderRightWidth":1,"fieldBorderBottomWidth":1,"fieldBorderTopLeftRadius":3,"fieldBorderTopRightRadius":3,"fieldBorderBottomLeftRadius":3,"fieldBorderBottomRightRadius":3,"fieldBorderStyle":"solid","fieldBorderColor":"#BDBDBD","checkBoxToggleBorderTopWidth":1,"checkBoxToggleBorderLeftWidth":1,"checkBoxToggleBorderRightWidth":1,"checkBoxToggleBorderBottomWidth":1,"checkBoxToggleBorderTopLeftRadius":3,"checkBoxToggleBorderTopRightRadius":3,"checkBoxToggleBorderBottomLeftRadius":3,"checkBoxToggleBorderBottomRightRadius":3,"checkBoxToggleBorderStyle":"solid","checkBoxToggleBorderColor":"#BDBDBD","btnBorderTopLeftRadius":3,"btnBorderTopRightRadius":3,"btnBorderBottomLeftRadius":3,"btnBorderBottomRightRadius":3,"variationSelected":true} -->
<div class="wp-block-uagb-forms uagb-forms__outer-wrap uagb-block-$block_id uagb-forms__medium-btn">
	<form class="uagb-forms-main-form" method="post" autocomplete="on" name="uagb-form-$block_id">
		<!-- wp:uagb/forms-name {"block_id":"046ed4b7","nameRequired":true,"name":"First Name","placeholder":"John","autocomplete":"given-name"} -->
		<div class="wp-block-uagb-forms-name uagb-forms-name-wrap uagb-forms-field-set uagb-block-046ed4b7">
			<div class="uagb-forms-name-label required uagb-forms-input-label" id="046ed4b7">First Name</div>
			<input type="text" placeholder="John" required class="uagb-forms-name-input uagb-forms-input"
				   name="046ed4b7" autocomplete="given-name"/></div>
		<!-- /wp:uagb/forms-name -->

		<!-- wp:uagb/forms-name {"block_id":"75b30804","nameRequired":true,"name":"Last Name","placeholder":"Doe","autocomplete":"family-name"} -->
		<div class="wp-block-uagb-forms-name uagb-forms-name-wrap uagb-forms-field-set uagb-block-75b30804">
			<div class="uagb-forms-name-label required uagb-forms-input-label" id="75b30804">Last Name</div>
			<input type="text" placeholder="Doe" required class="uagb-forms-name-input uagb-forms-input" name="75b30804"
				   autocomplete="family-name"/></div>
		<!-- /wp:uagb/forms-name -->

		<!-- wp:uagb/forms-email {"block_id":"9f2177c9"} -->
		<div class="wp-block-uagb-forms-email uagb-forms-email-wrap uagb-forms-field-set uagb-block-9f2177c9">
			<div class="uagb-forms-email-label  uagb-forms-input-label" id="9f2177c9">Email</div>
			<input type="email" class="uagb-forms-email-input uagb-forms-input" placeholder="example@mail.com"
				   name="9f2177c9" autocomplete="email"/></div>
		<!-- /wp:uagb/forms-email -->

		<!-- wp:uagb/forms-textarea {"block_id":"147f2552","textareaRequired":true} -->
		<div class="wp-block-uagb-forms-textarea uagb-forms-textarea-wrap uagb-forms-field-set uagb-block-147f2552">
			<div class="uagb-forms-textarea-label required uagb-forms-input-label" id="147f2552">Message</div>
			<textarea required class="uagb-forms-textarea-input uagb-forms-input" rows="4"
					  placeholder="Enter your message" name="147f2552" autocomplete="off"></textarea></div>
		<!-- /wp:uagb/forms-textarea -->
		<div class="uagb-forms-form-hidden-data"><input type="hidden" class="uagb_forms_form_label"
														value="Spectra Form"/><input type="hidden"
																					 class="uagb_forms_form_id"
																					 value="uagb-form-$block_id"/></div>
		<div class="uagb-form-reacaptcha-error-$block_id"></div>
		<div class="uagb-forms-main-submit-button-wrap wp-block-button">
			<button class="uagb-forms-main-submit-button wp-block-button__link">
				<div class="uagb-forms-main-submit-button-text">Submit</div>
			</button>
		</div>
	</form>
	<div class="uagb-forms-success-message-$block_id uagb-forms-submit-message-hide"><span>The form has been submitted successfully!</span>
	</div>
	<div class="uagb-forms-failed-message-$block_id uagb-forms-submit-message-hide"><span>There has been some error while submitting the form. Please verify all form fields again.</span>
	</div>
</div>
<!-- /wp:uagb/forms -->
HTML;

		$subject = Mockery::mock( Form::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->has_recaptcha() );

		$post_content      = str_replace( $recaptcha_placeholder, '', $template );
		$post_id           = wp_insert_post( [ 'post_content' => $post_content ] );
		$_POST['post_id']  = $post_id;
		$_POST['block_id'] = $block_id;

		self::assertFalse( $subject->has_recaptcha() );

		$post_content     = str_replace( $recaptcha_placeholder, '"reCaptchaEnable":"true",', $template );
		$post_id          = wp_insert_post( [ 'post_content' => $post_content ] );
		$_POST['post_id'] = $post_id;

		self::assertTrue( $subject->has_recaptcha() );
	}
}
