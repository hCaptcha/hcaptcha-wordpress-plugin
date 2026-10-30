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

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Otter\Form;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use ThemeIsle\GutenbergBlocks\Integration\Form_Data_Request;
use WP_Block;
use WP_Block_Type_Registry;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Test Otter Form.
 *
 * @group otter
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'otter-blocks/otter-blocks.php';

	/**
	 * Hooks to replay after loading Otter Blocks.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Force lifecycle hook replay after WPTestCase resets action counters.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Test a live Otter form block and request an object.
	 */
	public function test_live_form_block(): void {
		$form_id     = 'live-form';
		$form_option = 'live-form-option';
		$content     = <<<HTML
<!-- wp:themeisle-blocks/form {"id":"wp-block-themeisle-blocks-form-$form_id","optionName":"$form_option"} -->
<div id="wp-block-themeisle-blocks-form-$form_id" class="wp-block-themeisle-blocks-form has-captcha" data-option-name="$form_option"><form class="otter-form__container"><div class="wp-block-themeisle-blocks-form-input"><input type="text" class="otter-form-input" /></div><div class="wp-block-button"><button class="wp-block-button__link" type="submit">Submit</button></div></form></div>
<!-- /wp:themeisle-blocks/form -->
HTML;

		$integration = new Form();
		$class_file  = wp_normalize_path( ( new ReflectionClass( Form_Data_Request::class ) )->getFileName() );
		$html        = do_blocks( $content );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/otter-blocks/' ), $class_file );
		self::assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'themeisle-blocks/form' ) );
		self::assertSame( 10, has_filter( 'otter_form_anti_spam_validation', [ $integration, 'verify' ] ) );
		self::assertStringContainsString( 'otter-form__container', $html );
		self::assertStringContainsString( 'class="h-captcha"', $html );
		self::assertStringContainsString( 'hcaptcha_otter_nonce', $html );
		self::assertSame( 1, preg_match( '/name="hcaptcha_otter_form_nonce" value="([^"]+)"/', $html, $form_nonce ) );
		self::assertStringNotContainsString( 'has-captcha', $html );

		$this->prepare_verify_post( 'hcaptcha_otter_nonce', 'hcaptcha_otter' );

		$request_data = [
			'payload'                   => [
				'formId'           => 'wp-block-themeisle-blocks-form-' . $form_id,
				'formOption'       => $form_option,
				'antiSpamTime'     => 5000,
				'antiSpamHoneyPot' => '',
				'postId'           => wp_insert_post(
					[
						'post_title'  => 'Otter form page',
						'post_status' => 'publish',
					]
				),
				'formInputsData'   => [
					[
						'id'    => 'name',
						'label' => 'Name',
						'type'  => 'text',
						'value' => 'John Doe',
					],
				],
			],
			'h-captcha-response'        => 'some response',
			'hcaptcha_otter_form_nonce' => $form_nonce[1],
			'hcaptcha-widget-id'        => HCaptcha::widget_id_value(
				[
					'source'  => [ static::$plugin ],
					'form_id' => $form_id,
				]
			),
		];

		foreach ( [ 'hcaptcha_otter_nonce', 'hcap_fst_token', 'hcap_hp_test', 'hcap_hp_sig' ] as $key ) {
			$request_data[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		$request = new WP_REST_Request( 'POST', '/otter/v1/form/frontend' );
		$request->set_body( wp_json_encode( $request_data ) );

		$form_data = new Form_Data_Request( $request );
		$result    = apply_filters( 'otter_form_anti_spam_validation', $form_data );

		self::assertSame( $form_data, $result );
		self::assertFalse( $result->has_error() );
	}

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
	 * Do not replace the native site key in the admin area.
	 */
	public function test_init_hooks_in_admin(): void {
		set_current_screen( 'dashboard' );

		$subject = new Form();

		self::assertFalse( has_filter( 'option_themeisle_google_captcha_api_site_key', [ $subject, 'replace_site_key' ] ) );
	}

	/**
	 * Reject a payload that cannot contain the native form nonce.
	 */
	public function test_has_valid_form_nonce_rejects_non_array_payload(): void {
		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'has_valid_form_nonce' );

		self::assertFalse( $method->invoke( $subject, [ 'payload' => 'invalid' ] ) );
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
		$form_id     = 'fc0b7800';
		$form_option = 'fc0b7800-option';
		$form_nonce  = $this->create_form_nonce( $form_id, $form_option );
		$hcap_form   = $this->get_hcap_form(
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
		$block         = [
			'blockName' => 'themeisle-blocks/form',
			'attrs'     => [ 'optionName' => $form_option ],
		];
		$expected      = <<<HTML
<div id="wp-block-themeisle-blocks-form-$form_id" class="wp-block-themeisle-blocks-form ">
	<form>
		Some form inputs
		$hcap_form
<input type="hidden" name="hcaptcha_otter_form_nonce" value="$form_nonce">
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
	 * The signed option must match the rendered value Otter's script submits.
	 */
	public function test_add_hcaptcha_binds_rendered_option_name(): void {
		$subject  = new Form();
		$instance = Mockery::mock( WP_Block::class );
		$block    = [
			'blockName' => 'themeisle-blocks/form',
			'attrs'     => [ 'optionName' => 'saved-option' ],
		];
		$content  = '<div data-option-name="rendered-option" id="wp-block-themeisle-blocks-form-rendered"><form><div class="wp-block-button"><button type="submit">Submit</button></div></form></div>';
		$rendered = $subject->add_hcaptcha( $content, $block, $instance );

		self::assertSame( 1, preg_match( '/name="hcaptcha_otter_form_nonce" value="([^"]+)"/', $rendered, $matches ) );
		self::assertNotFalse( wp_verify_nonce( $matches[1], 'hcaptcha_otter|' . wp_json_encode( [ 'rendered', 'rendered-option' ] ) ) );
		self::assertFalse( wp_verify_nonce( $matches[1], 'hcaptcha_otter|' . wp_json_encode( [ 'rendered', 'saved-option' ] ) ) );
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

		$action      = 'hcaptcha_otter';
		$nonce       = 'hcaptcha_otter_nonce';
		$form_id     = 'fc0b7800';
		$form_option = 'fc0b7800-option';
		$widget_id   = HCaptcha::widget_id_value(
			[
				'source'  => [ 'otter-blocks/otter-blocks.php' ],
				'form_id' => $form_id,
			]
		);

		$this->prepare_verify_post( $nonce, $action, $verified );

		$form_data_arr = [
			'form_data' => [
				'payload'                   => [
					'formId'     => 'wp-block-themeisle-blocks-form-' . $form_id,
					'formOption' => $form_option,
				],
				'h-captcha-response'        => 'some response',
				'hcaptcha_otter_form_nonce' => $this->create_form_nonce( $form_id, $form_option ),
				'hcaptcha-widget-id'        => $widget_id,
				$nonce                      => $_POST[ $nonce ], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				'hcap_fst_token'            => $_POST['hcap_fst_token'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				'hcap_hp_test'              => $_POST['hcap_hp_test'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				'hcap_hp_sig'               => $_POST['hcap_hp_sig'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
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
	 * Test verify() when widget id is missing.
	 *
	 * @return void
	 */
	public function test_verify_missing_widget_id(): void {
		$subject           = new Form();
		$form_data_request = Mockery::mock( Form_Data_Request::class );
		$action            = 'hcaptcha_otter';
		$nonce             = 'hcaptcha_otter_nonce';

		$this->prepare_verify_post( $nonce, $action );

		$form_data_request->shouldReceive( 'has_error' )->andReturn( false );
		$form_data_request->shouldReceive( 'dump_data' )->with()->andReturn(
			[
				'form_data' => [
					'payload'                   => [
						'formId'     => 'wp-block-themeisle-blocks-form-fc0b7800',
						'formOption' => 'fc0b7800-option',
					],
					'h-captcha-response'        => 'some response',
					'hcaptcha_otter_form_nonce' => $this->create_form_nonce( 'fc0b7800', 'fc0b7800-option' ),
					$nonce                      => $_POST[ $nonce ], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'hcap_fst_token'            => $_POST['hcap_fst_token'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'hcap_hp_test'              => $_POST['hcap_hp_test'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'hcap_hp_sig'               => $_POST['hcap_hp_sig'], // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				],
			]
		);
		$form_data_request->shouldReceive( 'set_error' )->once()->with( 'bad-signature', 'Bad hCaptcha signature!' );

		$result = $subject->verify( $form_data_request );

		self::assertInstanceOf( Form_Data_Request::class, $result );
	}

	/**
	 * A signed widget for an exempt form cannot select another form's options.
	 */
	public function test_form_option_binding_rejects_exempt_widget_swap(): void {
		$subject     = new Form();
		$form_id     = 'sec009-source';
		$source_name = 'sec009-source-option';
		$target_name = 'sec009-target-option';
		$block       = [
			'blockName' => 'themeisle-blocks/form',
			'attrs'     => [ 'optionName' => $source_name ],
		];
		$content     = '<div id="wp-block-themeisle-blocks-form-' . $form_id . '" class="wp-block-themeisle-blocks-form"><form><div class="wp-block-button"><button type="submit">Submit</button></div></form></div>';
		$instance    = Mockery::mock( WP_Block::class );
		$rendered    = $subject->add_hcaptcha( $content, $block, $instance );

		self::assertSame( 1, preg_match( '/name="hcaptcha_otter_form_nonce" value="([^"]+)"/', $rendered, $matches ) );
		$source_nonce = $matches[1];

		$exempt_source = static function ( $protect, $source, $checked_form_id ) use ( $form_id ) {
			return $form_id === $checked_form_id ? false : $protect;
		};
		add_filter( 'hcap_protect_form', $exempt_source, 10, 3 );

		try {
			$widget_id = HCaptcha::widget_id_value(
				[
					'source'  => [ static::$plugin ],
					'form_id' => $form_id,
				]
			);

			$make_request = static function ( $form_option, $form_nonce, $submitted_form_id = null ) use ( $form_id, $widget_id ) {
				$request = new WP_REST_Request( 'POST', '/otter/v1/form/frontend' );
				$request->set_body(
					wp_json_encode(
						[
							'payload'                   => [
								'formId'     => $submitted_form_id ?? 'wp-block-themeisle-blocks-form-' . $form_id,
								'formOption' => $form_option,
								'postId'     => 0,
							],
							'hcaptcha-widget-id'        => $widget_id,
							'hcaptcha_otter_form_nonce' => $form_nonce,
						]
					)
				);

				return new Form_Data_Request( $request );
			};

			$swapped = $make_request( $target_name, $source_nonce );
			$subject->verify( $swapped );
			self::assertTrue( $swapped->has_error() );
			self::assertSame( 'bad-signature', $swapped->get_error_code() );

			$missing = $make_request( $source_name, '' );
			$subject->verify( $missing );
			self::assertTrue( $missing->has_error() );
			self::assertSame( 'bad-signature', $missing->get_error_code() );

			$synthetic = $make_request( $source_name, $source_nonce, 'wp-block-themeisle-blocks-form-wrong' );
			$subject->verify( $synthetic );
			self::assertTrue( $synthetic->has_error() );
			self::assertSame( 'bad-signature', $synthetic->get_error_code() );

			$legitimate = $make_request( $source_name, $source_nonce );
			$subject->verify( $legitimate );
			self::assertFalse( $legitimate->has_error() );

			$legacy_id = $make_request( $source_name, $source_nonce, $form_id );
			$subject->verify( $legacy_id );
			self::assertFalse( $legacy_id->has_error() );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt_source );
		}
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
	 * Test get_entry() and get_data().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_entry_and_data(): void {
		$subject   = new Form();
		$post_id   = wp_insert_post(
			[
				'post_title'  => 'Otter Test Form',
				'post_status' => 'publish',
			]
		);
		$post      = get_post( $post_id );
		$inputs    = [
			[
				'label' => 'Name',
				'value' => 'Jane Doe',
				'type'  => 'text',
			],
			[
				'label' => 'Email',
				'value' => 'jane@sec060-disposable.test',
				'type'  => 'email',
			],
			[
				'label' => 'Secondary email',
				'value' => 'jane@example.com',
				'type'  => 'email',
			],
			[
				'label' => 'Message',
				'value' => [ 'Hello', 'world' ],
				'type'  => 'textarea',
			],
			[
				'label' => '',
				'id'    => 'field-id',
				'value' => 'Field value',
			],
			[
				'label' => 'Empty',
				'value' => '',
			],
			[
				'label' => 'Empty array',
				'value' => [],
			],
		];
		$post_data = [
			'payload'            => [
				'formId'         => 'wp-block-themeisle-blocks-form-fc0b7800',
				'formInputsData' => $inputs,
				'postId'         => $post_id,
			],
			'h-captcha-response' => 'token',
		];

		$method = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry  = $method->invoke( $subject, $post_data );

		self::assertSame( 'hcaptcha_otter_nonce', $entry['nonce_name'] );
		self::assertSame( 'hcaptcha_otter', $entry['nonce_action'] );
		self::assertSame( 'token', $entry['h-captcha-response'] );
		self::assertSame( $post->post_modified_gmt, $entry['form_date_gmt'] );
		self::assertSame( $post_data, $entry['post_data'] );
		self::assertSame(
			[
				'source'  => [ 'otter-blocks/otter-blocks.php' ],
				'form_id' => 'fc0b7800',
			],
			$entry['expected_id']
		);
		self::assertSame( 'jane@sec060-disposable.test', $entry['data']['email'] );
		self::assertSame( [ 'jane@sec060-disposable.test', 'jane@example.com' ], $entry['email_addresses'] );
		self::assertSame( 'jane@example.com', $entry['data']['Secondary email'] );
		self::assertSame( 'Jane Doe', $entry['data']['name'] );
		self::assertSame( 'Jane Doe', $entry['data']['Name'] );
		self::assertSame( [ 'Hello', 'world' ], $entry['data']['Message'] );
		self::assertSame( 'Field value', $entry['data']['field-id'] );
		self::assertArrayNotHasKey( 'Empty', $entry['data'] );
		self::assertArrayNotHasKey( 'Empty array', $entry['data'] );

		$post_data['payload']['formInputsData'] = [
			[
				'label' => '',
				'id'    => '',
				'type'  => 'email',
				'value' => 'nameless@sec060-disposable.test',
			],
		];
		$entry                                  = $method->invoke( $subject, $post_data );

		self::assertSame( 'nameless@sec060-disposable.test', $entry['data']['email'] );
		self::assertSame( [ 'nameless@sec060-disposable.test' ], $entry['email_addresses'] );
	}

	/**
	 * Test filter_response().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_filter_response(): void {
		$subject  = new Form();
		$response = new WP_REST_Response( [ 'success' => false ] );
		$request  = new WP_REST_Request( 'POST', '/otter/v1/form/frontend' );

		$this->set_protected_property( $subject, 'error_code', 'fail' );
		$this->set_protected_property( $subject, 'error_message', 'The hCaptcha is invalid.' );

		$result = $subject->filter_response( $response, [], $request );
		$data   = $result->get_data();

		self::assertSame( 'fail', $data['code'] );
		self::assertSame( 'The hCaptcha is invalid.', $data['displayError'] );

		$request_other  = new WP_REST_Request( 'POST', '/otter/v1/other' );
		$response_other = new WP_REST_Response( [ 'success' => true ] );
		$result_other   = $subject->filter_response( $response_other, [], $request_other );

		self::assertSame( [ 'success' => true ], $result_other->get_data() );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 */
	public function test_add_type_module(): void {
		$subject = new Form();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="/assets/js/hcaptcha-otter.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'other-handle', '' ) );
		self::assertStringContainsString( 'type="module"', $subject->add_type_module( $tag, 'hcaptcha-otter', '' ) );
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

	/**
	 * Create an Otter form binding nonce for existing direct-filter cases.
	 *
	 * @param string $form_id     Form id without the Otter prefix.
	 * @param string $form_option Native Otter option name.
	 *
	 * @return string
	 */
	private function create_form_nonce( string $form_id, string $form_option ): string {
		return wp_create_nonce( 'hcaptcha_otter|' . wp_json_encode( [ $form_id, $form_option ] ) );
	}
}
