<?php
/**
 * AdvancedFormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Kadence\AdvancedBlockParser;
use HCaptcha\Kadence\AdvancedForm;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use KB_Ajax_Advanced_Form;
use Mockery;
use ReflectionException;

/**
 * Test Kadence AdvancedForm.
 *
 * @group kadence
 * @group kadence-advanced-form
 */
class AdvancedFormTest extends HCaptchaWPTestCase {

	/**
	 * Teardown test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'], $_POST );

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks().
	 *
	 * @param bool $is_frontend Whether it is a frontend request.
	 *
	 * @return void
	 * @dataProvider dp_test_constructor_and_init_hooks
	 */
	public function test_constructor_and_init_hooks( bool $is_frontend ): void {
		if ( ! $is_frontend ) {
			set_current_screen( 'some' );
		}

		$subject = new AdvancedForm();

		self::assertSame( 8, has_action( 'wp_print_footer_scripts', [ $subject, 'dequeue_kadence_captcha_api' ] ) );

		self::assertSame( 10, has_filter( 'render_block', [ $subject, 'render_block' ] ) );

		if ( $is_frontend ) {
			self::assertTrue( has_action( 'block_parser_class' ) );

			self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );

			self::assertFalse( has_action( 'wp_ajax_kb_process_advanced_form_submit', [ $subject, 'process_ajax' ] ) );
			self::assertFalse( has_action( 'wp_ajax_nopriv_kb_process_advanced_form_submit', [ $subject, 'process_ajax' ] ) );
			self::assertFalse( has_filter( 'pre_option_kadence_blocks_hcaptcha_site_key' ) );
			self::assertFalse( has_filter( 'pre_option_kadence_blocks_hcaptcha_secret_key' ) );
			self::assertFalse( has_action( 'enqueue_block_editor_assets', [ $subject, 'editor_assets' ] ) );
		} else {
			self::assertFalse( has_action( 'block_parser_class' ) );

			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );

			self::assertSame( 9, has_action( 'wp_ajax_kb_process_advanced_form_submit', [ $subject, 'process_ajax' ] ) );
			self::assertSame( 9, has_action( 'wp_ajax_nopriv_kb_process_advanced_form_submit', [ $subject, 'process_ajax' ] ) );
			self::assertTrue( has_filter( 'pre_option_kadence_blocks_hcaptcha_site_key' ) );
			self::assertTrue( has_filter( 'pre_option_kadence_blocks_hcaptcha_secret_key' ) );
			self::assertSame( 10, has_action( 'enqueue_block_editor_assets', [ $subject, 'editor_assets' ] ) );
		}
	}

	/**
	 * Data provider for dp_test_constructor_and_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_constructor_and_init_hooks(): array {
		return [
			'frontend' => [ true ],
			'backend'  => [ false ],
		];
	}

	/**
	 * Test render_block().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_render_block(): void {
		$block_content              = 'some block content';
		$block                      = [
			'blockName' => 'some',
			'attrs'     => [],
		];
		$instance                   = Mockery::mock( 'WP_Block' );
		$form_id                    = 5;
		$adv_block_parser           = Mockery::mock( AdvancedBlockParser::class );
		$adv_block_parser::$form_id = $form_id;
		$hcap_form                  = $this->get_hcap_form(
			[
				'id' => [
					'source'  => [ 'kadence-blocks/kadence-blocks.php' ],
					'form_id' => $form_id,
				],
			]
		);

		$subject = new AdvancedForm();

		// Not a form block.
		self::assertSame( $block_content, $subject->render_block( $block_content, $block, $instance ) );

		// Form-submit block.
		$block['blockName'] = 'kadence/advanced-form-submit';
		$search             = '<div class="kb-adv-form-field kb-submit-field';
		$block_content      = $search . '">Some block content</div>';
		$expected           = str_replace( $search, $hcap_form . $search, $block_content );

		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );

		// Form-captcha block.
		$block['blockName'] = 'kadence/advanced-form-captcha';
		$block_content      = '<div class="kb-adv-form-field kb-field5af748-cb wp-block-kadence-advanced-form-captcha"><div class="h-captcha" <div class="h-captcha" data-size="normal">Some block content</div></div>';
		$expected           = (string) preg_replace(
			'#<div class="h-captcha" .*?></div>#',
			$hcap_form,
			$block_content,
			1
		);

		self::assertFalse( $this->get_protected_property( $subject, 'has_hcaptcha' ) );
		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );
		self::assertTrue( $this->get_protected_property( $subject, 'has_hcaptcha' ) );
	}

	/**
	 * Test process_ajax().
	 *
	 * @return void
	 */
	public function test_process_ajax(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = Mockery::mock( AdvancedForm::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->process_ajax();
	}

	/**
	 * Test process_ajax() when not success.
	 *
	 * @param bool|null $result Result of \HCaptcha\Helpers\API::verify_request().
	 *
	 * @return void
	 * @dataProvider dp_test_process_ajax_when_not_success
	 */
	public function test_process_ajax_when_not_success( ?bool $result ): void {
		$hcaptcha_response = 'some response';
		$error_message     = 'The hCaptcha is invalid.';

		if ( null === $result ) {
			$error_message = 'Please complete the hCaptcha.';
		}

		$this->prepare_verify_request( $hcaptcha_response, $result );

		if ( null === $result ) {
			unset( $_POST['h-captcha-response'] );
		}

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$kb_ajax_advanced_form = Mockery::mock( 'alias:KB_Ajax_Advanced_Form' );
		$kb_ajax_advanced_form->shouldReceive( 'get_instance' )->once()->andReturn( $kb_ajax_advanced_form );
		$kb_ajax_advanced_form->shouldReceive( 'process_bail' )
			->once()->with( $error_message, 'hCaptcha Failed' );

		$subject = Mockery::mock( AdvancedForm::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$subject->process_ajax();
	}

	/**
	 * Data provider for test_process_ajax_when_not_success().
	 *
	 * @return array
	 */
	public function dp_test_process_ajax_when_not_success(): array {
		return [
			'null'  => [ null ],
			'false' => [ false ],
		];
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$handle = 'hcaptcha-kadence-advanced';

		$subject = new AdvancedForm();

		self::assertFalse( wp_script_is( $handle ) );

		$subject::enqueue_scripts();

		self::assertTrue( wp_script_is( $handle ) );
	}

	/**
	 * Test editor_assets().
	 *
	 * @return void
	 */
	public function test_editor_assets(): void {
		$handle = 'admin-kadence-advanced';
		$object = 'HCaptchaKadenceAdvancedFormObject';

		$subject = new AdvancedForm();

		// Ensure the script and style are not enqueued before calling the method.
		self::assertFalse( wp_script_is( $handle ) );
		self::assertFalse( wp_style_is( $handle ) );

		// Call the method to enqueue the assets.
		$subject->editor_assets();

		// Check if the script and style are enqueued.
		self::assertTrue( wp_script_is( $handle ) );
		self::assertTrue( wp_style_is( $handle ) );

		// Check if the script is localized with the correct data.
		$data = wp_scripts()->get_data( $handle, 'data' );

		preg_match( '/var ' . $object . ' = (.+);/', $data, $m );

		$notice_json = $m[1];
		$notice      = json_decode( $notice_json, true );
		$hcap_notice = HCaptcha::get_hcaptcha_plugin_notice();
		$expected    = [
			'noticeLabel'       => $hcap_notice['label'],
			'noticeDescription' => html_entity_decode( $hcap_notice['description'] ),
		];

		self::assertSame( $expected, $notice );
	}


	/**
	 * Test pre_option filters.
	 *
	 * @return void
	 */
	public function test_pre_option_filters(): void {
		$site_key   = 'some site key';
		$secret_key = 'some secret key';

		add_filter(
			'hcap_site_key',
			static function () use ( $site_key ) {
				return $site_key;
			}
		);
		add_filter(
			'hcap_secret_key',
			static function () use ( $secret_key ) {
				return $secret_key;
			}
		);

		set_current_screen( 'some' );

		new AdvancedForm();

		self::assertSame( $site_key, apply_filters( 'pre_option_kadence_blocks_hcaptcha_site_key', 'some' ) );
		self::assertSame( $secret_key, apply_filters( 'pre_option_kadence_blocks_hcaptcha_secret_key', 'some' ) );
	}
}
