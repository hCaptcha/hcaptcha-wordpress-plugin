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

use HCaptcha\Helpers\API as HCaptchaAPI;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\MailPoet\Form;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use MailPoet\API\JSON\API;
use MailPoet\API\JSON\ErrorResponse;
use MailPoet\API\JSON\Response;
use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\FormEntity;
use MailPoet\Form\FormsRepository;
use MailPoet\Form\Util\FieldNameObfuscator;
use Mockery;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test Form class.
 *
 * @group mailpoet
 */
class FormTest extends HCaptchaPluginWPTestCase {
	/**
	 * Request method before an admin-post test changes it.
	 *
	 * @var string|null
	 */
	private ?string $original_request_method = null;

	/**
	 * Whether an admin-post test changed the request method.
	 *
	 * @var bool
	 */
	private bool $request_method_changed = false;

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
		unset( $_GET['action'], $_GET['data'], $_REQUEST['action'], $_REQUEST['data'], $_REQUEST['api_version'], $_REQUEST['endpoint'], $_REQUEST['method'], $_REQUEST['mailpoet_method'], $_REQUEST['token'], $_POST['action'], $_POST['data'], $_POST['api_version'], $_POST['endpoint'], $_POST['method'], $_POST['mailpoet_method'], $_POST['token'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );

		if ( $this->request_method_changed ) {
			if ( null === $this->original_request_method ) {
				unset( $_SERVER['REQUEST_METHOD'] );
			} else {
				$_SERVER['REQUEST_METHOD'] = $this->original_request_method;
			}
		}

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
		self::assertSame( 0, has_action( 'admin_post_mailpoet_subscription_form', [ $subject, 'verify_admin_post' ] ) );
		self::assertSame( 0, has_action( 'admin_post_nopriv_mailpoet_subscription_form', [ $subject, 'verify_admin_post' ] ) );
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
		$_POST['action']    = 'mailpoet';
		$_REQUEST['action'] = 'mailpoet';
		$_POST['endpoint']  = 'subscribers';
		$_POST['method']    = 'subscribe';
		$_POST['data']      = [
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

		$_POST['action']    = 'mailpoet';
		$_REQUEST['action'] = 'mailpoet';
		$_POST['endpoint']  = 'subscribers';
		$_POST['method']    = 'subscribe';

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
	 * The disposable-email policy must inspect the address MailPoet decodes as its email field.
	 *
	 * @dataProvider dp_native_email_field_projection
	 *
	 * @param array  $fields         Native field names and submitted values, in POST order.
	 * @param string $expected_email Address retained by MailPoet's native decoder.
	 * @param bool   $blocked        Whether the disposable-email policy must reject it.
	 */
	public function test_native_email_field_projection( array $fields, string $expected_email, bool $blocked ): void {
		$obfuscator = ContainerWrapper::getInstance()->get( FieldNameObfuscator::class );
		$data       = [
			'form_id' => '1',
			'email'   => '',
		];

		foreach ( $fields as [ $name, $value ] ) {
			$data[ $obfuscator->obfuscate( $name ) ] = $value;
		}

		$_POST['data'] = $data;
		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );
		$this->prepare_widget_id( 1 );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'disposable.example' => true ] );

		$method = new ReflectionMethod( Form::class, 'get_entry' );
		$method->setAccessible( true );
		$entry  = $method->invoke( new Form() );
		$native = $obfuscator->deobfuscateFormPayload( $data );

		self::assertSame( $expected_email, $native['email'] );
		self::assertSame( $expected_email, $entry['data']['email'] );
		self::assertSame( [ 'email' => $expected_email ], array_intersect_key( $native, [ 'email' => true ] ) );

		$checked = [];
		$capture = static function ( bool $disposable, string $email ) use ( &$checked ): bool {
			$checked[] = $email;

			return $disposable;
		};
		add_filter( 'hcap_is_disposable_email', $capture, 10, 2 );

		try {
			$result = HCaptchaAPI::verify( $entry );
		} finally {
			remove_filter( 'hcap_is_disposable_email', $capture );
			delete_transient( 'hcaptcha_disposable_email_blocklist' );
		}

		self::assertSame( [ $expected_email ], $checked );
		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : null, $result );
	}

	/**
	 * Both sides of the native field-name boundary and an underscored content field.
	 *
	 * @return array
	 */
	public function dp_native_email_field_projection(): array {
		return [
			'canonical disposable'     => [ [ [ 'email', 'person@disposable.example' ] ], 'person@disposable.example', true ],
			'ignored field after'      => [ [ [ 'email', 'person@disposable.example' ], [ 'spare_email', 'reader@example.com' ] ], 'person@disposable.example', true ],
			'ignored field before'     => [ [ [ 'spare_email', 'reader@example.com' ], [ 'email', 'person@disposable.example' ] ], 'person@disposable.example', true ],
			'ignored disposable extra' => [ [ [ 'email', 'reader@example.com' ], [ 'spare_email', 'person@disposable.example' ] ], 'reader@example.com', false ],
			'underscored content'      => [ [ [ 'email', 'reader@example.com' ], [ 'first_name', 'Ava' ] ], 'reader@example.com', false ],
			'ignored sensitive field'  => [ [ [ 'email', 'reader@example.com' ], [ 'password', 'secret' ] ], 'reader@example.com', false ],
		];
	}

	/**
	 * Accept only the native admin-post subscription request shape.
	 */
	public function test_is_native_admin_post_subscription(): void {
		$subject = new Form();
		$method  = new ReflectionMethod( $subject, 'is_native_admin_post_subscription' );
		$method->setAccessible( true );

		self::assertFalse( $method->invoke( $subject, [] ) );

		$_REQUEST['endpoint']    = 'subscribers';
		$_REQUEST['method']      = 'subscribe';
		$_REQUEST['api_version'] = 'v1';
		$_REQUEST['token']       = '';

		self::assertFalse( $method->invoke( $subject, [ 'form_id' => '1' ] ) );

		$_REQUEST['token'] = 'native-token';
		self::assertTrue( $method->invoke( $subject, [ 'form_id' => '1' ] ) );
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

		$_POST['action']    = 'mailpoet';
		$_REQUEST['action'] = 'mailpoet';
		$_POST['endpoint']  = 'subscribers';
		$_POST['method']    = 'subscribe';
		$_POST['data']      = [
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

		$_POST['action']    = 'mailpoet';
		$_REQUEST['action'] = 'mailpoet';
		$_POST['endpoint']  = 'subscribers';
		$_POST['method']    = 'subscribe';
		$_POST['data']      = [
			'form_id'                             => '1',
			'email'                               => '',
			'form_field_YjVlNWFkMmRiYTlhX2VtYWls' => 'foo@bar.com',
		];

		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );

		$subject->verify( $api );
	}

	/**
	 * MailPoet's method alias reaches the public subscribe action without a method field.
	 *
	 * @return void
	 */
	public function test_verify_mailpoet_method_alias(): void {
		$error_response = Mockery::mock( ErrorResponse::class );
		$error_response->shouldReceive( 'send' )->once();
		$api = Mockery::mock( API::class );
		$api->shouldReceive( 'createErrorResponse' )
			->with( 'fail', 'The hCaptcha is invalid.', Response::STATUS_UNAUTHORIZED )
			->once()
			->andReturn( $error_response );

		$_REQUEST['action']       = 'mailpoet';
		$_POST['action']          = 'mailpoet';
		$_POST['api_version']     = 'v1';
		$_POST['endpoint']        = 'subscribers';
		$_POST['mailpoet_method'] = 'subscribe';
		$_POST['data']            = [ 'form_id' => '1' ];
		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet', false );
		$this->prepare_widget_id( 1 );

		( new Form() )->verify( $api );
	}

	/**
	 * Native endpoint normalization can resolve alternate names to Subscribers.
	 *
	 * @param string $endpoint Native endpoint selector.
	 * @return void
	 * @dataProvider dp_subscribers_endpoint_aliases
	 */
	public function test_verify_subscribers_endpoint_alias( string $endpoint ): void {
		$error_response = Mockery::mock( ErrorResponse::class );
		$error_response->shouldReceive( 'send' )->once();
		$api = Mockery::mock( API::class );
		$api->shouldReceive( 'createErrorResponse' )
			->with( 'fail', 'The hCaptcha is invalid.', Response::STATUS_UNAUTHORIZED )
			->once()
			->andReturn( $error_response );

		$_REQUEST['action']       = 'mailpoet';
		$_POST['api_version']     = 'v1';
		$_POST['endpoint']        = $endpoint;
		$_POST['mailpoet_method'] = 'subscribe';
		$_POST['data']            = [ 'form_id' => '1' ];
		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet', false );
		$this->prepare_widget_id( 1 );

		( new Form() )->verify( $api );
	}

	/**
	 * Alternate native names that resolve to Subscribers.
	 *
	 * @return array
	 */
	public function dp_subscribers_endpoint_aliases(): array {
		return [
			'leading underscore' => [ '_subscribers' ],
			'capitalized'        => [ 'Subscribers' ],
		];
	}

	/**
	 * WordPress may select the AJAX action from the query while MailPoet selects the alias from POST.
	 *
	 * @return void
	 */
	public function test_verify_query_action_and_conflicting_method(): void {
		$error_response = Mockery::mock( ErrorResponse::class );
		$error_response->shouldReceive( 'send' )->once();
		$api = Mockery::mock( API::class );
		$api->shouldReceive( 'createErrorResponse' )
			->with( 'fail', 'The hCaptcha is invalid.', Response::STATUS_UNAUTHORIZED )
			->once()
			->andReturn( $error_response );

		$_GET['action']           = 'mailpoet';
		$_REQUEST['action']       = 'mailpoet';
		$_POST['api_version']     = 'v1';
		$_POST['endpoint']        = 'subscribers';
		$_POST['method']          = 'other';
		$_POST['mailpoet_method'] = 'subscribe';
		$_POST['data']            = [ 'form_id' => '1' ];
		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet', false );
		$this->prepare_widget_id( 1 );

		( new Form() )->verify( $api );
	}

	/**
	 * A present alias suppresses the legacy method even when the alias is invalid.
	 *
	 * @return void
	 */
	public function test_verify_does_not_fall_back_from_invalid_alias(): void {
		$api = Mockery::mock( API::class );
		$api->shouldNotReceive( 'createErrorResponse' );

		$_REQUEST['action'] = 'mailpoet';
		$_POST['action']    = 'mailpoet';
		$_POST['endpoint']  = 'subscribers';
		$_POST['method']    = 'subscribe';

		$subject = new Form();
		foreach ( [ 'other', '', [] ] as $alias ) {
			$_POST['mailpoet_method'] = $alias;
			$subject->verify( $api );
		}
	}

	/**
	 * The WordPress-selected action controls this gate when GET and POST disagree.
	 *
	 * @return void
	 */
	public function test_verify_ignores_other_ajax_action(): void {
		$api = Mockery::mock( API::class );
		$api->shouldNotReceive( 'createErrorResponse' );

		$_REQUEST['action']       = 'other';
		$_POST['action']          = 'mailpoet';
		$_POST['endpoint']        = 'subscribers';
		$_POST['mailpoet_method'] = 'subscribe';

		( new Form() )->verify( $api );
	}

	/**
	 * A bad proof on MailPoet's native POST route is rejected before submission.
	 *
	 * @return void
	 */
	public function test_verify_admin_post_rejects_bad_proof(): void {
		$this->prepare_admin_post_request();
		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet', false );
		$this->prepare_widget_id( 1 );

		$die         = [];
		$die_handler = $this->capture_admin_post_die( $die );

		try {
			( new Form() )->verify_admin_post();
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
		}

		self::assertSame( 'The hCaptcha is invalid.', $die[0] ?? null );
		self::assertSame( 'hCaptcha error', $die[1] ?? null );
		self::assertSame( Response::STATUS_FORBIDDEN, $die[2]['response'] ?? null );
	}

	/**
	 * A valid proof lets the native submission handler continue.
	 *
	 * @return void
	 */
	public function test_verify_admin_post_allows_valid_proof(): void {
		$this->prepare_admin_post_request();
		$this->prepare_verify_post( 'hcaptcha_mailpoet_nonce', 'hcaptcha_mailpoet' );
		$this->prepare_widget_id( 1 );

		$die         = [];
		$die_handler = $this->capture_admin_post_die( $die );

		try {
			( new Form() )->verify_admin_post();
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
		}

		self::assertSame( [], $die );
	}

	/**
	 * A crawler's bare GET may redirect, while a GET carrying form data is rejected.
	 *
	 * @return void
	 */
	public function test_verify_admin_post_rejects_get_payload(): void {
		$this->set_request_method( 'GET' );
		$_GET['action']     = 'mailpoet_subscription_form';
		$_REQUEST['action'] = 'mailpoet_subscription_form';
		$subject            = new Form();

		$die         = [];
		$die_handler = $this->capture_admin_post_die( $die );

		try {
			$subject->verify_admin_post();
			self::assertSame( [], $die );

			$_GET['data']     = [ 'form_id' => '1' ];
			$_REQUEST['data'] = [ 'form_id' => '1' ];
			$subject->verify_admin_post();
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
		}

		self::assertSame( 405, $die[2]['response'] ?? null );
	}

	/**
	 * A POST cannot subscribe with GET-only data that get_entry() would not verify.
	 *
	 * @return void
	 */
	public function test_verify_admin_post_rejects_get_only_data_on_post(): void {
		$this->prepare_admin_post_request();
		$_GET['data'] = [ 'form_id' => '1' ];
		unset( $_POST['data'] );

		$die         = [];
		$die_handler = $this->capture_admin_post_die( $die );

		try {
			( new Form() )->verify_admin_post();
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
		}

		self::assertSame( Response::STATUS_FORBIDDEN, $die[2]['response'] ?? null );
	}

	/**
	 * Native requests for a different MailPoet endpoint retain their own handling.
	 *
	 * @return void
	 */
	public function test_verify_admin_post_ignores_other_endpoint(): void {
		$this->prepare_admin_post_request();
		$_REQUEST['endpoint'] = 'other';

		$die         = [];
		$die_handler = $this->capture_admin_post_die( $die );

		try {
			( new Form() )->verify_admin_post();
		} finally {
			remove_filter( 'wp_die_handler', $die_handler );
		}

		self::assertSame( [], $die );
	}

	/**
	 * Prepare the fields consumed by MailPoet's native admin-post submission handler.
	 *
	 * @return void
	 */
	private function prepare_admin_post_request(): void {
		$this->set_request_method( 'POST' );
		$_GET['action']     = 'mailpoet_subscription_form';
		$_REQUEST['action'] = 'mailpoet_subscription_form';

		$payload = [
			'api_version'     => 'v1',
			'endpoint'        => 'subscribers',
			'mailpoet_method' => 'subscribe',
			'token'           => 'mailpoet-token',
			'data'            => [ 'form_id' => '1' ],
		];

		foreach ( $payload as $name => $value ) {
			$_POST[ $name ]    = $value;
			$_REQUEST[ $name ] = $value;
		}
	}

	/**
	 * Capture the WordPress termination response without ending the test process.
	 *
	 * @param array $die_response Captured wp_die() arguments.
	 * @return callable
	 */
	private function capture_admin_post_die( array &$die_response ): callable {
		$die_handler = static function () use ( &$die_response ): callable {
			return static function ( $message, $title, $args ) use ( &$die_response ): void {
				$die_response = [ $message, $title, $args ];
			};
		};
		add_filter( 'wp_die_handler', $die_handler );

		return $die_handler;
	}

	/**
	 * Set the request method and retain its original value for tear down.
	 *
	 * @param string $method Request method.
	 * @return void
	 */
	private function set_request_method( string $method ): void {
		if ( ! $this->request_method_changed ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Restore the exact original test environment.
			$this->original_request_method = $_SERVER['REQUEST_METHOD'] ?? null;
			$this->request_method_changed  = true;
		}

		$_SERVER['REQUEST_METHOD'] = $method;
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
