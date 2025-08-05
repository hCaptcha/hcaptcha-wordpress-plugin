<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\EssentialBlocks;

use HCaptcha\EssentialBlocks\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use WP_Block;

/**
 * Class FormTest
 *
 * @group essential-blocks
 * @group essential-blocks-form
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @param bool $frontend Whether request is on the frontend.
	 *
	 * @return void
	 * @dataProvider dp_test_init_hooks
	 */
	public function test_init_hooks( bool $frontend ): void {
		if ( ! $frontend ) {
			add_filter( 'wp_doing_ajax', '__return_true' );
		}

		$subject = new Form();

		self::assertTrue( hcaptcha()->settings()->is_on( 'recaptcha_compat_off' ) );

		self::assertSame( 9, has_action( 'wp_ajax_eb_form_submit', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_eb_form_submit', [ $subject, 'verify' ] ) );

		if ( $frontend ) {
			self::assertSame( 10, has_filter( 'render_block', [ $subject, 'add_hcaptcha' ] ) );
			self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		} else {
			self::assertFalse( has_filter( 'render_block', [ $subject, 'add_hcaptcha' ] ) );
			self::assertFalse( has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		}
	}

	/**
	 * Data provider for test_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @param string $content  The content to filter.
	 * @param array  $block    The block attributes.
	 * @param string $expected The expected filtered content.
	 *
	 * @return void
	 * @dataProvider dp_test_add_hcaptcha
	 */
	public function test_add_hcaptcha( string $content, array $block, string $expected ): void {
		$instance = Mockery::mock( WP_Block::class )->makePartial();

		$args = [
			'action' => 'hcaptcha_essential_blocks',
			'name'   => 'hcaptcha_essential_blocks_nonce',
			'id'     => [
				'source'  => [ 'essential-blocks/essential-blocks.php' ],
				'form_id' => 'ebf-faf933',
			],
		];

		$hcap_form = $this->get_hcap_form( $args );
		$expected  = str_replace( '{hcaptcha}', $hcap_form . "\n", $expected );

		$subject = new Form();

		self::assertSame( $expected, $subject->add_hcaptcha( $content, $block, $instance ) );
	}

	/**
	 * Data provider for test_add_hcaptcha().
	 *
	 * @return array
	 */
	public function dp_test_add_hcaptcha(): array {
		return [
			'Not an essential blocks form' => [
				'<div class="example-block"></div>',
				[ 'blockName' => 'core/paragraph' ],
				'<div class="example-block"></div>',
			],
			'Essential Blocks Form'        => [
				'<form id="ebf-faf933" class="example-form"><div class="eb-form-submit>Submit</div></form>',
				[ 'blockName' => 'essential-blocks/form' ],
				'<form id="ebf-faf933" class="example-form">{hcaptcha}<div class="eb-form-submit>Submit</div></form>',
			],
		];
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_essential_blocks_nonce', 'hcaptcha_essential_blocks' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$form_data['h-captcha-response']              = $_POST['h-captcha-response'];
		$form_data['hcaptcha_essential_blocks_nonce'] = $_POST['hcaptcha_essential_blocks_nonce'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		unset( $_POST['h-captcha-response'], $_POST['hcaptcha_essential_blocks_nonce'] );

		$_POST['form_data'] = wp_json_encode( $form_data );

		$subject = new Form();

		$subject->verify();
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

		$this->prepare_verify_post( 'hcaptcha_essential_blocks_nonce', 'hcaptcha_essential_blocks', false );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$form_data['h-captcha-response']              = $_POST['h-captcha-response'];
		$form_data['hcaptcha_essential_blocks_nonce'] = $_POST['hcaptcha_essential_blocks_nonce'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

		unset( $_POST['h-captcha-response'], $_POST['hcaptcha_essential_blocks_nonce'] );

		$_POST['form_data'] = wp_json_encode( $form_data );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Form();

		ob_start();
		$subject->verify();
		$json = ob_get_clean();

		self::assertSame( '{"success":false,"data":"The hCaptcha is invalid."}', $json );
		self::assertSame( $expected, $die_arr );
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
	.wp-block-essential-blocks-form .h-captcha {
		margin: 15px 0 0 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-essential-blocks' ) );
	}
}
