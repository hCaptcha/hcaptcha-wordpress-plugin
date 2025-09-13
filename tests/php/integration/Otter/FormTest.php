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

namespace HCaptcha\Tests\Integration\Otter;

use HCaptcha\Otter\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request;
use WP_Block;

/**
 * Test Otter Form.
 *
 * @group otter
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		// Check if the filters and actions are added.
		self::assertSame(
			10,
			has_filter( 'option_themeisle_google_captcha_api_site_key', [ $subject, 'replace_site_key' ] )
		);
		self::assertSame(
			99,
			has_filter( 'default_option_themeisle_google_captcha_api_site_key', [ $subject, 'replace_site_key' ] )
		);
		self::assertSame( 10, has_filter( 'render_block', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'otter_form_anti_spam_validation', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test replace_site_key().
	 *
	 * @return void
	 */
	public function test_replace_site_key(): void {
		$subject = new Form();

		// Check if the site key is replaced with an empty string.
		self::assertSame( '', $subject->replace_site_key() );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 'fc0b7800';
		$hcap_form = $this->get_hcap_form(
			[
				'action' => 'hcaptcha_otter',
				'name'   => 'hcaptcha_otter_nonce',
				'id'     => [
					'source'  => [ 'otter-blocks/otter-blocks.php' ],
					'form_id' => $form_id,
				],
			]
		);

		$subject = new Form();

		$block_content = 'Some content';
		$block         = [ 'blockName' => 'some/some' ];
		$instance      = Mockery::mock( WP_Block::class );

		self::assertSame( $block_content, $subject->add_hcaptcha( $block_content, $block, $instance ) );

		$block_content = <<<HTML
<div id="wp-block-themeisle-blocks-form-$form_id" class="wp-block-themeisle-blocks-form has-captcha">
	<form>
		Some form inputs
		<div class="wp-block-button">
			<button class="wp-block-button__link" type="submit">Submit</button>
		</div>
	</form>
</div>
HTML;
		$block         = [ 'blockName' => 'themeisle-blocks/form' ];
		$expected      = <<<HTML
<div id="wp-block-themeisle-blocks-form-$form_id" class="wp-block-themeisle-blocks-form ">
	<form>
		Some form inputs
		$hcap_form
<div class="wp-block-button">
			<button class="wp-block-button__link" type="submit">Submit</button>
		</div>
	</form>
</div>
HTML;

		// Check if hCaptcha is added to the form block.
		self::assertSame( $expected, $subject->add_hcaptcha( $block_content, $block, $instance ) );
	}

	/**
	 * Test verify().
	 *
	 * @param bool $verified Verified or not.
	 *
	 * @return void
	 * @dataProvider dp_test_verify
	 */
	public function test_verify( bool $verified ): void {
		$subject = new Form();

		// Do not verify if the form data is null.
		self::assertNull( $subject->verify( null ) );

		// Do not verify if the form has errors.
		$form_data_request = Mockery::mock( Form_Data_Request::class );

		$form_data_request->shouldReceive( 'has_error' )->andReturn( true );

		self::assertSame( $form_data_request, $subject->verify( $form_data_request ) );

		// Verify the hCaptcha.
		$form_data_request = Mockery::mock( Form_Data_Request::class );

		$form_data_request->shouldReceive( 'has_error' )->andReturn( false );

		$action = 'hcaptcha_otter';
		$nonce  = 'hcaptcha_otter_nonce';

		$this->prepare_verify_post( $nonce, $action, $verified );

		$form_data_arr = [
			'form_data' => [
				'h-captcha-response'   => 'some response',
				'hcaptcha-widget-id'   => 'some widget id',
				'hcaptcha_otter_nonce' => $_POST[ $nonce ], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				'hcap_fst_token'       => 'some token',
				'hcap_hp_test'         => '',
				'hcap_hp_sig'          => 'some signature',
			],
		];

		$form_data_request->shouldReceive( 'dump_data' )->with()->andReturn( $form_data_arr );

		if ( $verified ) {
			$form_data_request->shouldReceive( 'set_error' )->never();
		} else {
			$form_data_request->shouldReceive( 'set_error' )->once()->with( 'fail', 'The hCaptcha is invalid.' );
		}

		$result = $subject->verify( $form_data_request );

		self::assertInstanceOf( Form_Data_Request::class, $result );
	}

	/**
	 * Data provider for test_verify().
	 *
	 * @return array
	 */
	public function dp_test_verify(): array {
		return [
			[ 'not verified' => false ],
			[ 'verified' => true ],
		];
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		// Form isn't shown.
		self::assertFalse( wp_script_is( 'hcaptcha-otter' ) );

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-otter' ) );

		// Form is shown.
		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		// Check if the script is enqueued.
		self::assertTrue( wp_script_is( 'hcaptcha-otter' ) );
	}
}
