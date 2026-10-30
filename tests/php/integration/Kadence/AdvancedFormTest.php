<?php
/**
 * AdvancedFormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Kadence\BlockParser;
use HCaptcha\Kadence\AdvancedForm;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use KB_Ajax_Advanced_Form;
use Mockery;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Test Kadence AdvancedForm.
 *
 * @group kadence
 * @group kadence-advanced-form
 */
class AdvancedFormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Kadence Blocks plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'kadence-blocks/kadence-blocks.php';

	/**
	 * Hooks to replay after loading Kadence Blocks.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Optional notice from Kadence's late-loaded dependency registry.
	 *
	 * @var string[]
	 */
	protected static array $plugin_optional_incorrect_usage = [ '_lw_harbor_instance_registry' ];

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		BlockParser::$form_id = 0;

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
		self::assertSame( 10, has_filter( 'render_block_data', [ $subject, 'render_block_data' ] ) );

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
	 * @noinspection UnnecessaryCastingInspection
	 */
	public function test_render_block(): void {
		$block_content              = 'some block content';
		$block                      = [
			'blockName' => 'some',
			'attrs'     => [],
		];
		$instance                   = Mockery::mock( 'WP_Block' );
		$form_id                    = 5;
		$adv_block_parser           = Mockery::mock( BlockParser::class );
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

		self::assertFalse( $this->get_protected_property( $subject, 'has_captcha' ) );
		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );
		self::assertTrue( $this->get_protected_property( $subject, 'has_captcha' ) );
	}

	/**
	 * Test render_block_data().
	 *
	 * @return void
	 */
	public function test_render_block_data(): void {
		$subject = new AdvancedForm();
		$block   = [
			'blockName' => 'some/block',
			'attrs'     => [ 'id' => '123' ],
		];

		BlockParser::$form_id = 5;

		self::assertSame( $block, $subject->render_block_data( $block ) );
		self::assertSame( 5, BlockParser::$form_id );

		$block['blockName'] = 'kadence/advanced-form';

		self::assertSame( $block, $subject->render_block_data( $block ) );
		self::assertSame( 123, BlockParser::$form_id );
	}

	/**
	 * Test rendering nested forms with different ids.
	 *
	 * @return void
	 */
	public function test_render_nested_forms_with_different_ids(): void {
		$form_ids = [];

		foreach ( [ 'first', 'second' ] as $slug ) {
			$form_ids[] = wp_insert_post(
				[
					'post_type'    => 'kadence_form',
					'post_status'  => 'publish',
					'post_title'   => ucfirst( $slug ) . ' form',
					'post_content' => '<!-- wp:kadence/advanced-form --><!-- wp:kadence/advanced-form-submit {"uniqueID":"submit-' . $slug . '","text":"Submit"} /--><!-- /wp:kadence/advanced-form -->',
				]
			);
		}

		$document = '';

		foreach ( $form_ids as $index => $form_id ) {
			$document .= '<!-- wp:kadence/advanced-form {"id":' . $form_id . ',"uniqueID":"embedded-' . $index . '"} /-->';
		}

		new AdvancedForm();

		$output = do_blocks( $document );

		foreach ( $form_ids as $form_id ) {
			$widget_id = HCaptcha::widget_id_value(
				[
					'source'  => [ 'kadence-blocks/kadence-blocks.php' ],
					'form_id' => $form_id,
				]
			);

			self::assertSame( 1, substr_count( $output, esc_attr( $widget_id ) ) );
		}

		self::assertStringContainsString( 'class="wp-block-kadence-advanced-form', $output );
		self::assertStringContainsString( 'class="kb-button kt-button button kb-adv-form-submit-button', $output );
		self::assertSame( end( $form_ids ), BlockParser::$form_id );
	}

	/**
	 * Test process_ajax().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_process_ajax(): void {
		$hcaptcha_response = 'some response';

		// Create a post first to get the ID, then update with a proper uniqueID.
		$post_id = wp_insert_post(
			[
				'post_content' => 'temp',
				'post_status'  => 'publish',
				'post_type'    => 'kadence_form',
			]
		);

		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact, Generic.Strings.UnnecessaryHeredoc.Found
		$post_content = <<<HTML
<!-- wp:kadence/advanced-form {"uniqueID":"{$post_id}_abc123"} -->
	<!-- wp:core/paragraph -->
	<p>Some intro text</p>
	<!-- /wp:core/paragraph -->

	<!-- wp:kadence/advanced-form {"uniqueID":"nested_bad"} /-->

	<!-- wp:kadence/advanced-form-select {"label":"Country","uniqueID":"sel001"} -->
	<div class="kb-adv-form-field">Country</div>
	<!-- /wp:kadence/advanced-form-select -->

	<!-- wp:core/column -->
	<div class="wp-block-column">
		<!-- wp:kadence/advanced-form-text {"label":"Name","uniqueID":"txt001"} -->
		<div class="kb-adv-form-field">Name</div>
		<!-- /wp:kadence/advanced-form-text -->
	</div>
	<!-- /wp:core/column -->

	<!-- wp:kadence/advanced-form-email {"label":"Email","uniqueID":"eml001"} -->
	<div class="kb-adv-form-field">Email</div>
	<!-- /wp:kadence/advanced-form-email -->

	<!-- wp:kadence/advanced-form-textarea {"label":"Message","uniqueID":"txa001"} -->
	<div class="kb-adv-form-field">Message</div>
	<!-- /wp:kadence/advanced-form-textarea -->
<!-- /wp:kadence/advanced-form -->
HTML;
		// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $post_content,
			]
		);

		$_POST['_kb_adv_form_post_id'] = $post_id;
		$_POST['fieldtxt001']          = 'John';
		$_POST['fieldeml001']          = 'john@example.com';
		$_POST['fieldtxa001']          = 'Hello there';

		$this->prepare_verify_request( $hcaptcha_response );
		$this->prepare_widget_id( (int) $post_id );

		$subject = Mockery::mock( AdvancedForm::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		$subject->process_ajax();

		// Verify that get_entry() populates data from form fields.
		$method = new ReflectionMethod( AdvancedForm::class, 'get_entry' );

		$method->setAccessible( true );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$entry = $method->invoke( $subject, $_POST );

		self::assertSame( 'John', $entry['data']['Name'] );
		self::assertSame( 'john@example.com', $entry['data']['Email'] );
		self::assertSame( 'john@example.com', $entry['data']['email'] );
		self::assertSame( 'Hello there', $entry['data']['Message'] );
	}

	/**
	 * Ignore unrelated blocks and non-content advanced form fields.
	 */
	public function test_add_form_field_ignores_non_content_blocks(): void {
		$subject   = new AdvancedForm();
		$method    = new ReflectionMethod( $subject, 'add_form_field' );
		$data      = [];
		$emails    = [];
		$form_data = [];
		$method->setAccessible( true );

		$method->invokeArgs( $subject, [ &$data, [ 'blockName' => 'core/paragraph' ], &$emails, $form_data ] );
		$method->invokeArgs( $subject, [ &$data, [ 'blockName' => 'kadence/advanced-form-hidden' ], &$emails, $form_data ] );

		self::assertSame( [], $data );
		self::assertSame( [], $emails );
	}

	/**
	 * Test process_ajax() when not success.
	 *
	 * @param bool|null $result Result of \HCaptcha\Helpers\API::verify_request().
	 *
	 * @return void
	 * @dataProvider dp_test_process_ajax_when_not_success
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_process_ajax_when_not_success( ?bool $result ): void {
		$hcaptcha_response = 'some response';
		$error_message     = 'The hCaptcha is invalid.';

		if ( null === $result ) {
			$error_message = 'Please complete the hCaptcha.';
		}

		$this->prepare_verify_request( $hcaptcha_response, $result );
		$this->prepare_widget_id( 0 );

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

		$subject = Mockery::mock( AdvancedForm::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		ob_start();
		ob_start();
		$subject->process_ajax();
		$json = ob_get_clean();
		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( $error_message, $data['data']['message'] );
		self::assertSame( 'hCaptcha Failed', $data['data']['console'] );
	}

	/**
	 * Test process_ajax() when widget id is missing.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_process_ajax_missing_widget_id(): void {
		$hcaptcha_response = 'some response';
		$error_message     = 'Bad hCaptcha signature!';

		$this->prepare_verify_request( $hcaptcha_response );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = Mockery::mock( AdvancedForm::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		ob_start();
		ob_start();
		$subject->process_ajax();
		$json = ob_get_clean();
		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( $error_message, $data['data']['message'] );
		self::assertSame( 'hCaptcha Failed', $data['data']['console'] );
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
				'source'  => [ 'kadence-blocks/kadence-blocks.php' ],
				'form_id' => $form_id,
			],
			$id
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
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
	 * Test add_type_module().
	 *
	 * @return void
	 */
	public function test_add_type_module(): void {
		$subject = new AdvancedForm();

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="/assets/js/hcaptcha-kadence-advanced.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'other-handle', '' ) );
		self::assertStringContainsString(
			'type="module"',
			$subject->add_type_module( $tag, 'hcaptcha-kadence-advanced', '' )
		);
	}

	/**
	 * Test editor_assets().
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
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
	/**
	 * Apply email policy to every trusted advanced email field.
	 *
	 * @param bool $blocked Whether the trusted email is disposable.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_typed_email_policy( bool $blocked ): void {
		$this->prepare_verify_request( 'sec063-challenge' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$post_id = $this->factory()->post->create();
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => '<!-- wp:kadence/advanced-form {"uniqueID":"' . $post_id . '_sec063"} --><!-- wp:kadence/advanced-form-email {"label":"Payment contact","uniqueID":"one","inputName":"billing_email"} /--><!-- wp:kadence/advanced-form-email {"label":"Другой адрес","uniqueID":"two"} /--><!-- wp:kadence/advanced-form-text {"label":"email","uniqueID":"three"} /--><!-- /wp:kadence/advanced-form -->',
			]
		);
		$_POST['_kb_adv_form_post_id'] = $post_id;
		$_POST['billing_email']        = $blocked ? ' person@sec063-disposable.test ' : ' person@example.com ';
		$_POST['fieldone']             = 'spoof@sec063-disposable.test';
		$_POST['fieldtwo']             = 'reader@example.com';
		$_POST['fieldthree']           = 'spoof@sec063-disposable.test';
		$this->prepare_widget_id( $post_id );
		$subject = new AdvancedForm();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$entry = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $_POST );

		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, API::verify_request( null, $entry ) );
	}
	/**
	 * Exercise both policy rejection and permanent address acceptance.
	 *
	 * @return array
	 */
	public function dp_sec063_email_policy(): array {
		return [
			'disposable' => [ true ],
			'permanent'  => [ false ],
		];
	}

	/**
	 * Match the native accepted email before enforcing disposable-address policy.
	 *
	 * @param string $raw_email Posted native email input.
	 * @param string $email     Native normalized address.
	 * @param bool   $blocked   Whether native address is disposable.
	 * @dataProvider dp_sec063_native_email_normalization
	 * @return void
	 */
	public function test_sec063_native_email_normalization( string $raw_email, string $email, bool $blocked ): void {
		$this->prepare_verify_request( 'sec063-challenge' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'kadence_form',
				'post_status' => 'publish',
			]
		);
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => '<!-- wp:kadence/advanced-form {"uniqueID":"' . $post_id . '_sec063"} --><!-- wp:kadence/advanced-form-email {"label":"Payment contact","uniqueID":"one","inputName":"billing_email","required":true} /--><!-- wp:kadence/advanced-form-text {"label":"email","uniqueID":"two","inputName":"email"} /--><!-- /wp:kadence/advanced-form -->',
			]
		);
		$_POST['_kb_adv_form_post_id'] = $post_id;
		$_POST['billing_email']        = wp_slash( $raw_email );
		$_POST['email']                = 'spoof@example.com';
		$this->prepare_widget_id( $post_id );

		$native = KB_Ajax_Advanced_Form::get_instance();
		$fields = new ReflectionProperty( KB_Ajax_Advanced_Form::class, 'fields' );
		$fields->setAccessible( true );
		$stored_fields = $fields->getValue();
		$fields->setValue( null, [] );
		try {
			$form      = $this->set_method_accessibility( $native, 'get_form' )->invoke( $native, $post_id );
			$processed = $native->process_fields( $form['fields'] );
		} finally {
			$fields->setValue( null, $stored_fields );
		}
		self::assertSame( $email, $processed[0]['value'] );
		self::assertSame( 'email', $processed[0]['type'] );
		self::assertSame( 'billing_email', $processed[0]['name'] );

		$subject = new AdvancedForm();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native pipeline owns its nonce check.
		$entry = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $_POST );
		self::assertSame( [ $processed[0]['value'] ], $entry['email_addresses'] );
		self::assertSame( $processed[0]['value'], $entry['data']['email'] );
		self::assertArrayNotHasKey( 'Payment contact', $entry['data'] );
		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, API::verify_request( null, $entry ) );
	}

	/**
	 * Native HTML insertion normalization and ordinary address controls.
	 *
	 * @return array
	 */
	public function dp_sec063_native_email_normalization(): array {
		return [
			'disposable with HTML insertion' => [ ' person@sec063-disposab<l>e.test ', 'person@sec063-disposable.test', true ],
			'permanent with HTML insertion'  => [ ' reader@examp<l>e.com ', 'reader@example.com', false ],
			'ordinary disposable'            => [ ' person@sec063-disposable.test ', 'person@sec063-disposable.test', true ],
			'ordinary permanent'             => [ ' reader@example.com ', 'reader@example.com', false ],
		];
	}

	/**
	 * Project email fields from the same post selected by native Kadence.
	 *
	 * @param string $id_format    Posted ID representation.
	 * @param string $email        Submitted email address.
	 * @param bool   $blocked      Whether the disposable policy must reject it.
	 * @param bool   $imported_id  Whether the block uses an imported unique ID.
	 * @param bool   $nested       Whether the email leaf is inside another Kadence block.
	 * @dataProvider dp_sec061_form_id_spelling
	 * @return void
	 */
	public function test_sec061_form_id_spelling( string $id_format, string $email, bool $blocked, bool $imported_id, bool $nested ): void {
		$this->prepare_verify_request( 'sec061-challenge' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec061-disposable.test' => true ] );
		$post_id     = $this->factory()->post->create(
			[
				'post_type'   => 'kadence_form',
				'post_status' => 'publish',
			]
		);
		$unique_id   = $imported_id ? 'imported_sec061' : $post_id . '_sec061';
		$email_block = '<!-- wp:kadence/advanced-form-email {"label":"Contact","uniqueID":"email","inputName":"contact_email","required":true} /-->';

		if ( $nested ) {
			$email_block = '<!-- wp:kadence/advanced-form-group -->' . $email_block . '<!-- /wp:kadence/advanced-form-group -->';
		}

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => '<!-- wp:kadence/advanced-form {"uniqueID":"' . $unique_id . '"} -->' . $email_block . '<!-- /wp:kadence/advanced-form -->',
			]
		);
		$posted_id                     = sprintf( $id_format, $post_id );
		$_POST['_kb_adv_form_post_id'] = $posted_id;
		$_POST['contact_email']        = $email;
		$this->prepare_widget_id( $post_id );

		$native = KB_Ajax_Advanced_Form::get_instance();
		$fields = new ReflectionProperty( KB_Ajax_Advanced_Form::class, 'fields' );
		$fields->setAccessible( true );
		$stored_fields = $fields->getValue();
		$fields->setValue( null, [] );
		try {
			$form      = $this->set_method_accessibility( $native, 'get_form' )->invoke( $native, $posted_id );
			$processed = $native->process_fields( $form['fields'] );
		} finally {
			$fields->setValue( null, $stored_fields );
		}
		self::assertSame( $email, $processed[0]['value'] );
		self::assertSame( 'contact_email', $processed[0]['name'] );

		$subject = new AdvancedForm();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native pipeline owns its nonce check.
		$entry = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $_POST );
		self::assertSame( $post_id, $entry['expected_id']['form_id'] );
		self::assertSame( [ $processed[0]['value'] ], $entry['email_addresses'] );
		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, API::verify_request( null, $entry ) );
	}

	/**
	 * ID spellings and disposable-email controls for SEC-061.
	 *
	 * @return array
	 */
	public function dp_sec061_form_id_spelling(): array {
		return [
			'canonical blocked'      => [ '%s', 'person@sec061-disposable.test', true, false, false ],
			'leading zero blocked'   => [ '00%s', 'person@sec061-disposable.test', true, false, false ],
			'decimal suffix blocked' => [ '%s.0', 'person@sec061-disposable.test', true, false, false ],
			'leading zero allowed'   => [ '00%s', 'reader@example.com', false, false, false ],
			'imported block ID'      => [ '%s', 'person@sec061-disposable.test', true, true, false ],
			'nested email field'     => [ '%s', 'person@sec061-disposable.test', true, false, true ],
		];
	}
}
