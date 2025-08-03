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

namespace HCaptcha\Tests\Integration\CoBlocks;

use CoBlocks_Form;
use HCaptcha\CoBlocks\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use WP_Block;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test Form class.
 *
 * @group coblocks
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

		self::assertSame( 10, has_action( 'render_block', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_action( 'render_block_data', [ $subject, 'render_block_data' ] ) );
		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$placeholder = '===hcaptcha placeholder===';
		$form_id     = '5509c11f003ddee1ee47fbb0a2ffb7b47b57434c';
		$block       = [
			'blockName' => 'some block',
		];
		$instance    = Mockery::mock( WP_Block::class );
		$template    = <<<HTML
<div class="coblocks-form" id="$form_id">
	<form action="https://test.test/coblocks/#$form_id" method="post">
		<div class="wp-block-coblocks-form"><label class="coblocks-label">Name</label>
			<input type="hidden" name="field-name[label]" value="Name">
			<input type="hidden" id="name-field-id" name="name-field-id" class="coblocks-name-field-id"
				   value="field-name"/>
			<input type="text" id="name" name="field-name[value]" class="coblocks-field coblocks-field--name"/>
			<label class="coblocks-label">Email <span class="required">&#042;</span></label>
			<input type="hidden" name="field-email[label]" value="Email">
			<input type="hidden" id="email-field-id" name="email-field-id" class="coblocks-email-field-id"
				   value="field-email"/>
			<input type="email" id="email" aria-label="Email" name="field-email[value]"
				   class="coblocks-field coblocks-field--email" required/>
			<label class="coblocks-label">Message <span class="required">&#042;</span></label>
			<input type="hidden" name="field-message[label]" value="Message">
			<textarea name="field-message[value]" aria-label="Message" id="message"
					  class="coblocks-field coblocks-textarea" rows="20" required>
			</textarea>
			<div class="coblocks-form__submit wp-block-button">
				$placeholder<button type="submit" class="wp-block-button__link" style="">Contact Us</button>
				<input type="hidden" id="form-submit" name="form-submit" value="0a5367f3bd"/>
				<input type="hidden" name="_wp_http_referer" value="/coblocks/"/>
				<input type="hidden" name="action" value="coblocks-form-submit">
			</div>
		</div>
		<input class="coblocks-field verify" aria-label="Enter your email address to verify" type="email"
			   name="coblocks-verify-email" autocomplete="off" placeholder="Email" tabindex="-1">
		<input type="hidden" name="form-hash" value="$form_id">
	</form>
</div>
HTML;

		$subject = new Form();

		$content = 'some content';

		// Ignore non-form blocks.
		self::assertSame( $content, $subject->add_hcaptcha( $content, $block, $instance ) );

		$block = [
			'blockName' => 'coblocks/form',
		];

		$args     = [
			'action' => 'hcaptcha_coblocks',
			'name'   => 'hcaptcha_coblocks_nonce',
			'id'     => [
				'source'  => [ 'coblocks/class-coblocks.php' ],
				'form_id' => $form_id,
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$content  = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha . "\n", $template );

		self::assertSame( $expected, $subject->add_hcaptcha( $content, $block, $instance ) );
	}

	/**
	 * Test render_block_data().
	 *
	 * @return void
	 */
	public function test_render_block_data(): void {
		$parsed_block = [
			'blockName' => 'some',
			'attrs'     => [ 'some' ],
		];
		$source_block = [
			'blockName' => 'coblocks/form',
			'attrs'     => [ 'some' ],
		];

		$subject = new Form();

		self::assertFalse( has_action( 'coblocks_before_form_submit', [ $subject, 'before_form_submit' ] ) );

		// Ignore other blocks.
		$subject->render_block_data( $parsed_block, $source_block );

		self::assertFalse( has_action( 'coblocks_before_form_submit', [ $subject, 'before_form_submit' ] ) );

		// Ignore for coblocks/form if no POST data.
		$parsed_block['blockName'] = 'coblocks/form';

		$subject->render_block_data( $parsed_block, $source_block );

		self::assertFalse( has_action( 'coblocks_before_form_submit', [ $subject, 'before_form_submit' ] ) );

		// Add action for coblocks/form.
		$_POST['action'] = 'coblocks-form-submit';

		$subject->render_block_data( $parsed_block, $source_block );

		self::assertSame( 10, has_action( 'coblocks_before_form_submit', [ $subject, 'before_form_submit' ] ) );

		// Do not add action for coblocks/form if already added.
		remove_action( 'coblocks_before_form_submit', [ $subject, 'before_form_submit' ] );

		$subject->render_block_data( $parsed_block, $source_block );

		self::assertFalse( has_action( 'coblocks_before_form_submit', [ $subject, 'before_form_submit' ] ) );
	}

	/**
	 * Test before_form_submit().
	 *
	 * @return void
	 */
	public function test_before_form_submit(): void {
		$post = [ 'some' ];
		$atts = [ 'some' ];

		$subject = new Form();

		$subject->before_form_submit( $post, $atts );

		self::assertSame( 10, has_filter( 'pre_option_coblocks_google_recaptcha_site_key', '__return_true' ) );
		self::assertSame( 10, has_filter( 'pre_option_coblocks_google_recaptcha_secret_key', '__return_true' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		self::assertSame( 'hcaptcha_token', $_POST['g-recaptcha-token'] );

		self::assertSame( 10, has_filter( 'pre_http_request', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$verify_url  = CoBlocks_Form::GCAPTCHA_VERIFY_URL;
		$response    = [ 'some response' ];
		$parsed_args = [
			'body' => [
				'response' => 'some response',
			],
		];
		$url         = 'some url';
		$expected    = [
			'body'     => '{"success":true}',
			'response' =>
				[
					'code'    => 200,
					'message' => 'OK',
				],
		];

		$this->prepare_verify_post( 'hcaptcha_coblocks_nonce', 'hcaptcha_coblocks' );

		$subject = new Form();

		add_filter( 'pre_http_request', [ $subject, 'verify' ] );

		// Wrong url.
		self::assertSame( $response, $subject->verify( $response, $parsed_args, $url ) );
		self::assertSame( 10, has_filter( 'pre_http_request', [ $subject, 'verify' ] ) );

		// Wrong token.
		$url = $verify_url;

		self::assertSame( $response, $subject->verify( $response, $parsed_args, $url ) );
		self::assertSame( 10, has_filter( 'pre_http_request', [ $subject, 'verify' ] ) );

		// Process verification.
		$parsed_args['body']['response'] = 'hcaptcha_token';

		self::assertSame( $expected, $subject->verify( $response, $parsed_args, $url ) );
		self::assertFalse( has_filter( 'pre_http_request', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_not_verified(): void {
		$verify_url  = CoBlocks_Form::GCAPTCHA_VERIFY_URL;
		$response    = [ 'some response' ];
		$parsed_args = [
			'body' => [
				'response' => 'hcaptcha_token',
			],
		];
		$url         = $verify_url;
		$expected    = [
			'body'     => '{"success":false}',
			'response' =>
				[
					'code'    => 200,
					'message' => 'OK',
				],
		];

		$this->prepare_verify_post( 'hcaptcha_coblocks_nonce', 'hcaptcha_coblocks', false );

		$subject = new Form();

		add_filter( 'pre_http_request', [ $subject, 'verify' ] );

		// Process verification.
		$parsed_args['body']['response'] = 'hcaptcha_token';

		self::assertSame( $expected, $subject->verify( $response, $parsed_args, $url ) );
		self::assertFalse( has_filter( 'pre_http_request', [ $subject, 'verify' ] ) );
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
	.wp-block-coblocks-form .h-captcha-error {
		color: red;
		margin-bottom: 25px;
	}

	.wp-block-coblocks-form .h-captcha {
		margin-bottom: 25px;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		// Show styles.
		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
