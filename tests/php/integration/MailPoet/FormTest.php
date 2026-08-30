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

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\MailPoet\Form;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use MailPoet\API\JSON\API;
use MailPoet\API\JSON\ErrorResponse;
use MailPoet\API\JSON\Response;
use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\FormEntity;
use MailPoet\Form\FormsRepository;
use Mockery;
use ReflectionClass;

/**
 * Test Form class.
 *
 * @group mailpoet
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * MailPoet plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'mailpoet/mailpoet.php';

	/**
	 * Hooks to replay after loading MailPoet.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * MailPoet can expose a recoverable migration query error as activation output on MariaDB.
	 *
	 * @var array<string, string[]>
	 */
	protected static array $plugin_allowed_activation_errors = [
		'mailpoet/mailpoet.php' => [ 'unexpected_output' ],
	];

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST['action'], $_POST['endpoint'], $_POST['method'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		parent::tearDown();
	}

	/**
	 * Test a form persisted and rendered by the live MailPoet plugin.
	 *
	 * @return void
	 */
	public function test_live_mailpoet_form(): void {
		$repository = ContainerWrapper::getInstance()->get( FormsRepository::class );
		$form       = new FormEntity( 'hCaptcha integration form' );

		$form->setBody(
			[
				[
					'id'     => 'email',
					'name'   => 'Email',
					'type'   => 'text',
					'params' => [
						'label'        => 'Email Address',
						'required'     => true,
						'label_within' => true,
					],
					'styles' => [ 'full_width' => true ],
				],
				[
					'id'     => 'submit',
					'name'   => 'Submit',
					'type'   => 'submit',
					'params' => [ 'label' => 'Subscribe' ],
					'styles' => [ 'full_width' => true ],
				],
			]
		);
		$form->setSettings(
			[
				'on_success'           => 'message',
				'success_message'      => 'Subscribed',
				'segments'             => [],
				'segments_selected_by' => 'admin',
			]
		);
		$form->setStyles( '' );

		$repository->persist( $form );
		$repository->flush();

		new Form();

		$output      = apply_filters( 'the_content', '[mailpoet_form id="' . $form->getId() . '"]' );
		$plugin_file = wp_normalize_path( ( new ReflectionClass( FormsRepository::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/mailpoet/' ), $plugin_file );
		self::assertTrue( shortcode_exists( 'mailpoet_form' ) );
		self::assertStringContainsString( 'class="mailpoet_form ', $output );
		self::assertStringContainsString( 'value="Subscribe"', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha_mailpoet_nonce"', $output );
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

		// Verify with $_POST['data'] having only form_id to cover an empty $fields path.
		$_POST['action']   = 'mailpoet';
		$_POST['endpoint'] = 'subscribers';
		$_POST['method']   = 'subscribe';
		$_POST['data']     = [
			'form_id' => '1',
		];

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );
		$this->prepare_widget_id( 1 );

		$subject->verify( $api );

		// Verify with no $_POST['data'] to cover `: []` branch.
		unset( $_POST['data'] );

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );
		$this->prepare_widget_id( 0 );

		$subject->verify( $api );
	}

	/**
	 * Test verify() with $_POST['data'] set.
	 */
	public function test_verify_with_data(): void {
		$api = Mockery::mock( API::class );

		$subject = new Form();

		$_POST['action']   = 'mailpoet';
		$_POST['endpoint'] = 'subscribers';
		$_POST['method']   = 'subscribe';

		// Verify with $_POST['data'] set.
		$_POST['data'] = [
			'form_id'                             => '1',
			'email'                               => '',
			'form_field_YjVlNWFkMmRiYTlhX2VtYWls' => 'foo@bar.com',
		];

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );
		$this->prepare_widget_id( 1 );

		$subject->verify( $api );
	}

	/**
	 * Test verify() when not verified.
	 */
	public function test_verify_not_verified(): void {
		$code          = 'fail';
		$error_message = 'The hCaptcha is invalid.';

		$error_response = Mockery::mock( ErrorResponse::class );
		$api            = Mockery::mock( API::class );

		$error_response->shouldReceive( 'send' )->once();
		$api->shouldReceive( 'createErrorResponse' )
			->with( $code, $error_message, Response::STATUS_UNAUTHORIZED )
			->andReturn( $error_response );

		$subject = new Form();

		$subject->verify( $api );

		$_POST['action']   = 'mailpoet';
		$_POST['endpoint'] = 'subscribers';
		$_POST['method']   = 'subscribe';
		$_POST['data']     = [
			'form_id'                             => '1',
			'email'                               => '',
			'form_field_YjVlNWFkMmRiYTlhX2VtYWls' => 'foo@bar.com',
		];

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet', false );
		$this->prepare_widget_id( 1 );

		$subject->verify( $api );
	}

	/**
	 * Test verify() when widget id is missing.
	 */
	public function test_verify_missing_widget_id(): void {
		$code          = 'bad-signature';
		$error_message = 'Bad hCaptcha signature!';

		$error_response = Mockery::mock( ErrorResponse::class );
		$api            = Mockery::mock( API::class );

		$error_response->shouldReceive( 'send' )->once();
		$api->shouldReceive( 'createErrorResponse' )
			->with( $code, $error_message, Response::STATUS_UNAUTHORIZED )
			->andReturn( $error_response );

		$subject = new Form();

		$_POST['action']   = 'mailpoet';
		$_POST['endpoint'] = 'subscribers';
		$_POST['method']   = 'subscribe';
		$_POST['data']     = [
			'form_id'                             => '1',
			'email'                               => '',
			'form_field_YjVlNWFkMmRiYTlhX2VtYWls' => 'foo@bar.com',
		];

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );

		$subject->verify( $api );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int   $form_id Form id.
	 * @param array $id      Widget id.
	 *
	 * @return void
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function prepare_widget_id( int $form_id, array $id = [] ): void {
		$id = array_merge(
			[
				'source'  => [ 'mailpoet/mailpoet.php' ],
				'form_id' => $form_id,
			],
			$id
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
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
