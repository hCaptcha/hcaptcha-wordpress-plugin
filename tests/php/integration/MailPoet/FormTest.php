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

namespace HCaptcha\Tests\Integration\MailPoet;

use HCaptcha\MailPoet\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\Tests\Integration\Stubs\MailPoet\API\JSON\ResponseStub;
use MailPoet\API\JSON\API;
use MailPoet\API\JSON\Response;
use MailPoet\API\JSON\ErrorResponse;
use Mockery;

/**
 * Test Form class.
 *
 * @group mailpoet
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['action'], $_POST['endpoint'], $_POST['method'] );
		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 20, has_filter( 'the_content', [ $subject, 'the_content_filter' ] ) );
		self::assertSame( 10, has_action( 'mailpoet_api_setup', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test the_content_filter().
	 *
	 * @return void
	 */
	public function test_the_content_filter(): void {
		$placeholder = '===hcaptcha placeholder===';
		$form_id     = 1;
		$template    = <<<HTML
<form
	action="https://test.test/wp-admin/admin-post.php?action=mailpoet_subscription_form"
>
	<input type="hidden" name="data[form_id]" value="$form_id"/>
	$placeholder<input type="submit" class="mailpoet_submit" value="JOIN THE CLUB"/>
</form>
<form
	action="https://test.test/wp-admin/admin-post.php?action=mailpoet_subscription_form"
>
	<input type="hidden" name="data[form_id]" value="$form_id"/>
	<div class="h-captcha">some hCaptcha</div><input type="submit" class="mailpoet_submit" value="JOIN THE CLUB"/>
</form>
<form
	action="https://test.test/wp-admin/admin-post.php?action=mailpoet_subscription_form"
>
	<input type="hidden" name="data[form_id]" value="$form_id"/>
	$placeholder<input type="submit" class="mailpoet_submit" value="JOIN THE CLUB"/>
</form>
HTML;

		$subject = new Form();

		$content = 'some content';
		self::assertSame( $content, $subject->the_content_filter( $content ) );

		$args     = [
			'action' => 'hcaptcha_mailpoet',
			'name'   => 'hcaptcha_mailpoet_nonce',
			'id'     => [
				'source'  => [ 'mailpoet/mailpoet.php' ],
				'form_id' => $form_id,
			],
		];
		$hcaptcha = $this->get_hcap_form( $args );

		$content  = str_replace( $placeholder, '', $template );
		$expected = str_replace( $placeholder, $hcaptcha, $template );

		self::assertSame( $expected, $subject->the_content_filter( $content ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$api = Mockery::mock( API::class );

		$subject = new Form();

		$subject->verify( $api );

		$_POST['action']   = 'mailpoet';
		$_POST['endpoint'] = 'subscribers';
		$_POST['method']   = 'subscribe';

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );

		$subject->verify( $api );
	}

	/**
	 * Test verify() when not verified.
	 */
	public function test_verify_not_verified(): void {
		$code          = 'fail';
		$error_message = 'The hCaptcha is invalid.';

		Mockery::namedMock( Response::class, ResponseStub::class );
		$error_response = Mockery::mock( ErrorResponse::class );
		$api            = Mockery::mock( API::class );

		$error_response->shouldReceive( 'send' )->once();
		$api->shouldReceive( 'createErrorResponse' )
			->with( $code, $error_message, ResponseStub::STATUS_UNAUTHORIZED )
			->andReturn( $error_response );

		$subject = new Form();

		$subject->verify( $api );

		$_POST['action']   = 'mailpoet';
		$_POST['endpoint'] = 'subscribers';
		$_POST['method']   = 'subscribe';

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet', false );

		$subject->verify( $api );
	}

	/**
	 * Test enqueue_scripts().
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		self::assertFalse( wp_script_is( 'hcaptcha-mailpoet' ) );

		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( 'hcaptcha-mailpoet' ) );

		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'hcaptcha-mailpoet' ) );
	}
}
