<?php
/**
 * CaptchaIsolationTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Kadence\Base;
use HCaptcha\Kadence\BlockParser;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Kadence\Form;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use KB_Ajax_Advanced_Form;
use KB_Ajax_Form;
use ReflectionProperty;
use RuntimeException;

/**
 * Keep independently configured Kadence CAPTCHA integrations isolated.
 *
 * @group kadence
 * @group kadence-captcha-isolation
 */
class CaptchaIsolationTest extends HCaptchaPluginWPTestCase {

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
	protected static array $plugin_load_hooks = [ 'plugins_loaded', 'init' ];

	/**
	 * Optional notice from Kadence's late-loaded dependency registry.
	 *
	 * @var string[]
	 */
	protected static array $plugin_optional_incorrect_usage = [ '_lw_harbor_instance_registry' ];

	/**
	 * Request data before simulating native AJAX transport.
	 *
	 * @var array
	 */
	private array $request = [];

	/**
	 * Request method before simulating a frontend or AJAX request.
	 *
	 * @var string|null
	 */
	private ?string $request_method = null;

	/**
	 * Save request globals before exercising the native handlers.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve the existing test request.
		$this->request = $_REQUEST;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Restore the original transport unchanged.
		$this->request_method = $_SERVER['REQUEST_METHOD'] ?? null;
	}

	/**
	 * Restore request globals changed by the AJAX fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_REQUEST = $this->request;
		if ( null === $this->request_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->request_method;
		}
		BlockParser::$form_id = 0;
		unset( $GLOBALS['current_screen'] );
		parent::tearDown();
	}

	/**
	 * Preserve the native classic CAPTCHA check under advanced-only integration.
	 *
	 * @param array     $modules Enabled Kadence integration modules.
	 * @param bool|null $captcha Stored native CAPTCHA setting, or absence.
	 * @param bool      $success Whether the native form should submit.
	 *
	 * @dataProvider dp_native_classic_submission
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_native_classic_submission( array $modules, ?bool $captcha, bool $success ): void {
		$this->load_kadence_modules( $modules );
		$post_id = $this->create_classic_form( $captcha );
		$native  = KB_Ajax_Form::get_instance();
		$args    = $this->set_method_accessibility( $native, 'get_form_args' )->invoke( $native, (string) $post_id, 'sec003-classic' );

		self::assertNotEmpty( $args );
		self::assertFalse( $this->has_classic_verifier() );

		$_POST  = [
			'action'           => 'kb_process_ajax_submit',
			'_kb_form_post_id' => (string) $post_id,
			'_kb_form_id'      => 'sec003-classic',
			'_kb_verify_email' => '',
			'kb_field_0'       => 'Reader <b>Name</b>',
		];
		$result = $this->submit_classic_form();

		self::assertSame( $success, $result['json']['success'] );
		self::assertCount( $success ? 1 : 0, $result['mail'] );
		if ( $success ) {
			self::assertSame( 'sec003-recipient@example.com', $result['mail'][0]['to'] );
			self::assertStringContainsString( 'Reader Name', $result['mail'][0]['message'] );
		} else {
			self::assertSame( 'reCAPTCHA Failed', $result['json']['data']['console'] );
		}
		if ( null === $captcha ) {
			self::assertArrayNotHasKey( 'recaptcha', $args );
		} else {
			self::assertSame( $captcha, $args['recaptcha'] );
		}
	}

	/**
	 * Native CAPTCHA rejection and CAPTCHA-free submission controls.
	 *
	 * @return array
	 */
	public function dp_native_classic_submission(): array {
		return [
			'neither module, native CAPTCHA' => [ [], true, false ],
			'advanced only, native CAPTCHA'  => [ [ 'advanced_form' ], true, false ],
			'advanced only, CAPTCHA off'     => [ [ 'advanced_form' ], false, true ],
			'advanced only, CAPTCHA absent'  => [ [ 'advanced_form' ], null, true ],
		];
	}

	/**
	 * A classic integration must replace native CAPTCHA with real hCaptcha proof.
	 *
	 * @param array $modules Enabled Kadence integration modules.
	 * @param bool  $proof   Whether a valid hCaptcha proof is submitted.
	 *
	 * @dataProvider dp_integrated_classic_submission
	 * @return void
	 */
	public function test_integrated_classic_submission( array $modules, bool $proof ): void {
		$this->prepare_verify_request( 'sec003-hcaptcha-proof' );
		$this->load_kadence_modules( $modules );
		$post_id = $this->create_classic_form( true );
		self::assertTrue( $this->has_classic_verifier() );

		$_POST['_kb_form_post_id']             = (string) $post_id;
		$_POST['_kb_form_id']                  = 'sec003-classic';
		$_POST['_kb_verify_email']             = '';
		$_POST['kb_field_0']                   = 'Reader Name';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ static::$plugin ],
				'form_id' => $post_id,
			]
		);
		if ( ! $proof ) {
			unset( $_POST['h-captcha-response'] );
		}
		$result = $this->submit_classic_form();

		self::assertSame( $proof, $result['json']['success'] );
		self::assertCount( $proof ? 1 : 0, $result['mail'] );
		if ( ! $proof ) {
			self::assertSame( 'hCaptcha Failed', $result['json']['data']['console'] );
		}
	}

	/**
	 * Classic-only and both-module hCaptcha proof controls.
	 *
	 * @return array
	 */
	public function dp_integrated_classic_submission(): array {
		return [
			'classic only, valid proof'   => [ [ 'form' ], true ],
			'classic only, missing proof' => [ [ 'form' ], false ],
			'both modules, valid proof'   => [ [ 'form', 'advanced_form' ], true ],
			'both modules, missing proof' => [ [ 'form', 'advanced_form' ], false ],
		];
	}

	/**
	 * Keep native form configuration scoped to its selected integration module.
	 *
	 * @param array     $modules Enabled Kadence integration modules.
	 * @param bool|null $captcha Classic native CAPTCHA setting, or absence.
	 *
	 * @dataProvider dp_module_configuration
	 * @return void
	 */
	public function test_module_configuration( array $modules, ?bool $captcha ): void {
		// Store the native forms before installing the submission request's parser.
		$post_id     = $this->create_classic_form( $captcha );
		$advanced_id = $this->factory()->post->create(
			[
				'post_type'    => 'kadence_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:kadence/advanced-form --><!-- wp:kadence/advanced-form-text {"uniqueID":"sec003-text","required":true} /--><!-- wp:kadence/advanced-form-captcha {"uniqueID":"sec003-captcha","type":"hcaptcha"} /--><!-- /wp:kadence/advanced-form -->',
			]
		);
		$this->load_kadence_modules( $modules );
		$args    = $this->set_method_accessibility( KB_Ajax_Form::get_instance(), 'get_form_args' )->invoke( KB_Ajax_Form::get_instance(), (string) $post_id, 'sec003-classic' );
		$classic = in_array( 'form', $modules, true );

		self::assertSame( $classic, $this->has_classic_verifier() );
		if ( $classic || null !== $captcha ) {
			self::assertSame( $classic ? false : $captcha, $args['recaptcha'] );
		} else {
			self::assertArrayNotHasKey( 'recaptcha', $args );
		}
		self::assertTrue( $args['honeyPot'] );
		self::assertTrue( $args['fields'][0]['required'] );

		$native  = KB_Ajax_Advanced_Form::get_instance();
		$fields  = new ReflectionProperty( KB_Ajax_Advanced_Form::class, 'fields' );
		$captcha = new ReflectionProperty( KB_Ajax_Advanced_Form::class, 'captcha_attrs' );
		$fields->setAccessible( true );
		$captcha->setAccessible( true );
		$old_fields  = $fields->getValue();
		$old_captcha = $captcha->getValue();
		$fields->setValue( null, [] );
		$captcha->setValue( null, false );
		try {
			$advanced = $this->set_method_accessibility( $native, 'get_form' )->invoke( $native, $advanced_id );
			self::assertCount( in_array( 'advanced_form', $modules, true ) ? 1 : 2, $advanced['fields'] );
			self::assertTrue( $advanced['fields'][0]['required'] );
			if ( in_array( 'advanced_form', $modules, true ) ) {
				self::assertFalse( $captcha->getValue() );
			} else {
				self::assertSame( 'hcaptcha', $captcha->getValue()['type'] );
			}
		} finally {
			$fields->setValue( null, $old_fields );
			$captcha->setValue( null, $old_captcha );
		}
	}

	/**
	 * All independent module settings and stored classic CAPTCHA states.
	 *
	 * @return array
	 */
	public function dp_module_configuration(): array {
		$cases = [];
		foreach ( [
			'neither'  => [],
			'classic'  => [ 'form' ],
			'advanced' => [ 'advanced_form' ],
			'both'     => [ 'form', 'advanced_form' ],
		] as $name => $modules ) {
			foreach ( [
				'on'     => true,
				'off'    => false,
				'absent' => null,
			] as $state => $captcha ) {
				$cases[ $name . ' CAPTCHA ' . $state ] = [ $modules, $captcha ];
			}
		}
		return $cases;
	}

	/**
	 * Exercise the native locator for nested, reusable, and widget forms.
	 *
	 * @param string $location Native form location.
	 *
	 * @dataProvider dp_native_locations
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_native_locations( string $location ): void {
		$this->load_kadence_modules( [ 'advanced_form' ] );
		$post_id   = $this->create_classic_form( true );
		$native_id = $this->relocate_classic_form( $post_id, $location );
		$native    = KB_Ajax_Form::get_instance();
		$args      = $this->set_method_accessibility( $native, 'get_form_args' )->invoke( $native, $native_id, 'sec003-classic' );
		self::assertTrue( $args['recaptcha'] );

		$_POST  = [
			'_kb_form_post_id' => $native_id,
			'_kb_form_id'      => 'sec003-classic',
			'_kb_verify_email' => '',
			'kb_field_0'       => 'Reader Name',
		];
		$result = $this->submit_classic_form();
		self::assertFalse( $result['json']['success'] );
		self::assertSame( 'reCAPTCHA Failed', $result['json']['data']['console'] );
		self::assertCount( 0, $result['mail'] );
	}

	/**
	 * Supported native form locators.
	 *
	 * @return array
	 */
	public function dp_native_locations(): array {
		return [
			'post'           => [ 'post' ],
			'nested'         => [ 'nested' ],
			'reusable'       => [ 'reusable' ],
			'widget'         => [ 'widget' ],
			'unknown widget' => [ 'unknown-widget' ],
		];
	}

	/**
	 * Keep the native required field, honeypot, and optional nonce checks.
	 *
	 * @param array  $overrides Submitted values overriding valid defaults.
	 * @param bool   $nonce     Whether native nonce checking is enabled.
	 * @param string $error     Expected native rejection, or empty on success.
	 *
	 * @dataProvider dp_native_input_guards
	 * @return void
	 */
	public function test_native_input_guards( array $overrides, bool $nonce, string $error ): void {
		$this->load_kadence_modules( [ 'advanced_form' ] );
		$post_id = $this->create_classic_form( false );
		if ( $nonce ) {
			add_filter( 'kadence_blocks_form_verify_nonce', '__return_true' );
		}
		$_POST = array_merge(
			[
				'_kb_form_post_id' => (string) $post_id,
				'_kb_form_id'      => 'sec003-classic',
				'_kb_verify_email' => '',
				'_kb_form_verify'  => wp_create_nonce( 'kb_form_nonce' ),
				'kb_field_0'       => 'Reader <b>Name</b>',
			],
			$overrides
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native check_ajax_referer reads the submitted transport.
		$_REQUEST = $_POST;
		$result   = $this->submit_classic_form();
		self::assertSame( '' === $error, $result['json']['success'] );
		self::assertCount( '' === $error ? 1 : 0, $result['mail'] );
		if ( '' !== $error ) {
			self::assertSame( $error, $result['json']['data']['console'] );
		} else {
			self::assertStringContainsString( 'Reader Name', $result['mail'][0]['message'] );
		}
	}

	/**
	 * Native validation controls unrelated to CAPTCHA replacement.
	 *
	 * @return array
	 */
	public function dp_native_input_guards(): array {
		return [
			'required field' => [ [ 'kb_field_0' => '' ], false, 'Required Field Empty' ],
			'honeypot'       => [ [ '_kb_verify_email' => 'robot' ], false, 'Spam Detected' ],
			'invalid nonce'  => [ [ '_kb_form_verify' => 'invalid' ], true, 'Token invalid' ],
			'valid nonce'    => [ [], true, '' ],
		];
	}

	/**
	 * Replacing an advanced CAPTCHA must preserve native classic API scripts.
	 *
	 * @param string $version Native classic reCAPTCHA version.
	 *
	 * @dataProvider dp_native_recaptcha_versions
	 * @return void
	 */
	public function test_mixed_frontend_preserves_native_classic_scripts( string $version ): void {
		$this->load_kadence_modules( [ 'advanced_form' ], true );
		update_option( 'kadence_blocks_recaptcha_site_key', 'sec003-native-site-key' );
		update_option( 'kadence_blocks_recaptcha_secret_key', 'sec003-native-secret-key' );
		$handle = 'kadence-blocks-google-recaptcha-' . $version;
		foreach ( [ $handle, 'kadence-blocks-recaptcha' ] as $native_handle ) {
			wp_dequeue_script( $native_handle );
			wp_deregister_script( $native_handle );
		}
		$form_id = $this->factory()->post->create(
			[
				'post_type'    => 'kadence_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:kadence/advanced-form --><!-- wp:kadence/advanced-form-captcha {"uniqueID":"sec003-advanced-captcha","type":"googlev2"} /--><!-- wp:kadence/advanced-form-submit {"uniqueID":"sec003-submit","text":"Submit"} /--><!-- /wp:kadence/advanced-form -->',
			]
		);
		$captcha = 'v2' === $version
			? '<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap"><div class="kadence-blocks-g-recaptcha-v2"></div></div>'
			: '<input type="hidden" name="recaptcha_response" class="kb_recaptcha_response">';
		$classic = '<!-- wp:kadence/form {"uniqueID":"sec003-classic-' . $form_id . '","recaptcha":true,"recaptchaVersion":"' . $version . '"} --><div class="wp-block-kadence-form">' . $captcha . '<div class="kadence-blocks-form-field kb-submit-field"></div></div><!-- /wp:kadence/form -->';
		$output  = do_blocks( $classic . '<!-- wp:kadence/advanced-form {"id":' . $form_id . ',"uniqueID":"sec003-embedded-' . $form_id . '"} /-->' );

		self::assertStringContainsString( $captcha, $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringNotContainsString( 'class="g-recaptcha"', $output );
		self::assertTrue( wp_script_is( $handle ) );
		self::assertTrue( wp_script_is( 'kadence-blocks-recaptcha' ) );

		$callbacks = $GLOBALS['wp_filter']['wp_print_footer_scripts']->callbacks[8] ?? [];
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Base ) {
				call_user_func( $callback['function'] );
			}
		}
		self::assertTrue( wp_script_is( $handle ) );
		self::assertTrue( wp_script_is( $handle, 'registered' ) );
		self::assertFalse( wp_script_is( 'kadence-blocks-recaptcha' ) );
		self::assertFalse( wp_script_is( 'kadence-blocks-recaptcha', 'registered' ) );
	}

	/**
	 * Classic native APIs share neither handle with the advanced API.
	 *
	 * @return array
	 */
	public function dp_native_recaptcha_versions(): array {
		return [
			'v2' => [ 'v2' ],
			'v3' => [ 'v3' ],
		];
	}

	/**
	 * Saving content must preserve native CAPTCHA after hCaptcha is disabled.
	 *
	 * @param array  $modules Enabled Kadence integration modules.
	 * @param string $method  Native WordPress save operation.
	 * @param bool   $ajax    Whether the save happens during native form AJAX.
	 *
	 * @dataProvider dp_classic_content_save
	 * @return void
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_classic_content_save_preserves_native_captcha( array $modules, string $method, bool $ajax ): void {
		$post_id = 'update' === $method ? $this->factory()->post->create( [ 'post_content' => 'Original content' ] ) : 0;
		$user_id = $this->factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user_id );
		self::assertFalse( current_user_can( 'unfiltered_html' ) );
		kses_init();
		self::assertSame( 10, has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
		set_current_screen( 'post' );
		$this->load_kadence_modules( $modules, false, $ajax ? 'kb_process_ajax_submit' : '' );

		$content = '<!-- wp:kadence/form {"uniqueID":"sec003-classic","recaptcha":true,"recaptchaVersion":"v2","honeyPot":true,"fields":[{"type":"text","label":"Name","required":true,"multiSelect":false}],"actions":["email"],"email":[{"emailTo":"sec003-recipient@example.com","subject":"SEC003 saved native form","html":false}]} /-->';
		$data    = [
			'post_author'  => $user_id,
			'post_status'  => 'publish',
			'post_content' => $content,
		];
		if ( 'update' === $method ) {
			$data['ID'] = $post_id;
			$post_id    = wp_update_post( $data, true );
		} else {
			$post_id = wp_insert_post( $data, true );
		}
		$this->assertNotWPError( $post_id );
		self::assertGreaterThan( 0, $post_id );

		unset( $GLOBALS['current_screen'] );
		$this->disable_kadence_modules();
		self::assertFalse( $this->has_classic_verifier(), 'Disabling the integration must remove its request verifier.' );
		$native = KB_Ajax_Form::get_instance();
		$args   = $this->set_method_accessibility( $native, 'get_form_args' )->invoke( $native, (string) $post_id, 'sec003-classic' );
		$_POST  = [
			'action'           => 'kb_process_ajax_submit',
			'_kb_form_post_id' => (string) $post_id,
			'_kb_form_id'      => 'sec003-classic',
			'_kb_verify_email' => '',
			'kb_field_0'       => 'Reader Name',
		];
		$result = $this->submit_classic_form();
		self::assertFalse( $result['json']['success'], 'The saved native CAPTCHA must reject after hCaptcha is disabled.' );
		self::assertCount( 0, $result['mail'] );
		self::assertSame( 'reCAPTCHA Failed', $result['json']['data']['console'] );
		self::assertTrue( $args['recaptcha'] );
	}

	/**
	 * Insert and update through KSES during editor and native AJAX requests.
	 *
	 * @return array
	 */
	public function dp_classic_content_save(): array {
		$cases = [];
		foreach ( [
			'classic' => [ 'form' ],
			'both'    => [ 'form', 'advanced_form' ],
		] as $module => $enabled ) {
			foreach ( [ 'insert', 'update' ] as $method ) {
				foreach ( [
					'editor'      => false,
					'native AJAX' => true,
				] as $context => $ajax ) {
					$cases[ $module . ' ' . $method . ' ' . $context ] = [ $enabled, $method, $ajax ];
				}
			}
		}
		return $cases;
	}

	/**
	 * Save-time KSES must retain and serialize native advanced CAPTCHA blocks.
	 *
	 * @param string $method Native WordPress save operation.
	 *
	 * @dataProvider dp_advanced_content_save
	 * @return void
	 */
	public function test_advanced_content_save_preserves_native_captcha( string $method ): void {
		$post_id = 'update' === $method ? $this->factory()->post->create( [ 'post_content' => 'Original content' ] ) : 0;
		$user_id = $this->factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user_id );
		self::assertFalse( current_user_can( 'unfiltered_html' ) );
		kses_init();
		self::assertSame( 10, has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
		set_current_screen( 'post' );
		$this->load_kadence_modules( [ 'form', 'advanced_form' ], false, 'kb_process_advanced_form_submit' );

		$content = '<!-- wp:kadence/advanced-form --><!-- wp:kadence/advanced-form-text {"uniqueID":"sec003-text","required":true} /--><!-- wp:kadence/advanced-form-captcha {"uniqueID":"sec003-captcha","type":"hcaptcha"} /--><!-- /wp:kadence/advanced-form -->';
		$data    = [
			'post_author'  => $user_id,
			'post_type'    => 'kadence_form',
			'post_status'  => 'publish',
			'post_content' => $content,
		];
		if ( 'update' === $method ) {
			$data['ID'] = $post_id;
			$post_id    = wp_update_post( $data, true );
		} else {
			$post_id = wp_insert_post( $data, true );
		}
		$this->assertNotWPError( $post_id );
		self::assertGreaterThan( 0, $post_id );

		$this->disable_kadence_modules();
		$stored = get_post( $post_id )->post_content;
		$blocks = parse_blocks( $stored );
		self::assertCount( 2, $blocks[0]['innerBlocks'] );
		self::assertSame( 'kadence/advanced-form-captcha', $blocks[0]['innerBlocks'][1]['blockName'] );
		self::assertSame( 'hcaptcha', $blocks[0]['innerBlocks'][1]['attrs']['type'] );
		self::assertSame( $stored, serialize_blocks( $blocks ) );
	}

	/**
	 * Native insert and update APIs run identical KSES serialization.
	 *
	 * @return array
	 */
	public function dp_advanced_content_save(): array {
		return [
			'insert' => [ 'insert' ],
			'update' => [ 'update' ],
		];
	}

	/**
	 * Start the next native request with the integrations disabled in all tabs.
	 *
	 * @return void
	 */
	private function disable_kadence_modules(): void {
		$settings                   = (array) get_option( 'hcaptcha_settings', [] );
		$settings['kadence_status'] = [];
		update_option( 'hcaptcha_settings', $settings );
		hcaptcha()->init_hooks();
		$this->load_kadence_modules( [] );
	}

	/**
	 * Store a classic form in a location accepted by the native handler.
	 *
	 * @param int    $post_id  Original form post.
	 * @param string $location Native form location.
	 *
	 * @return string
	 */
	private function relocate_classic_form( int $post_id, string $location ): string {
		$content = get_post( $post_id )->post_content;
		if ( 'nested' === $location ) {
			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => '<!-- wp:group --><!-- wp:columns --><!-- wp:column -->' . $content . '<!-- /wp:column --><!-- /wp:columns --><!-- /wp:group -->',
				]
			);
		} elseif ( 'reusable' === $location ) {
			$reference = $this->factory()->post->create(
				[
					'post_type'    => 'wp_block',
					'post_content' => $content,
				]
			);
			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => '<!-- wp:block {"ref":' . $reference . '} /-->',
				]
			);
		} elseif ( in_array( $location, [ 'widget', 'unknown-widget' ], true ) ) {
			update_option( 'widget_block', [ 5 => [ 'content' => $content ] ] );
			return 'widget' === $location ? 'block-5' : 'block-unknown';
		}
		return (string) $post_id;
	}

	/**
	 * Replay enabled module constructors using the real module settings map.
	 *
	 * Main avoids constructing classes already loaded by earlier integration tests.
	 * WPTestCase resets their hooks, so each case must replay the enabled modules.
	 *
	 * @param array  $enabled     Enabled module setting values.
	 * @param bool   $frontend    Whether to simulate a frontend request.
	 * @param string $ajax_action Native AJAX action, or empty for an editor save.
	 *
	 * @return void
	 */
	private function load_kadence_modules( array $enabled, bool $frontend = false, string $ajax_action = 'kb_process_ajax_submit' ): void {
		$this->set_kadence_request( $frontend, $ajax_action );
		hcaptcha()->settings()->set( 'kadence_status', $enabled );
		hcaptcha()->load_modules();

		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook ) {
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) && $function[0] instanceof Base ) {
						remove_filter( $hook_name, $function, $priority );
					}
				}
			}
		}
		remove_all_filters( 'block_parser_class' );

		foreach ( [ 'Kadence Form', 'Kadence Advanced Form' ] as $name ) {
			$module = hcaptcha()->modules[ $name ];
			if ( hcaptcha()->settings()->is( $module[0][0], $module[0][1] ) ) {
				$class = $module[2];
				new $class();
			}
		}
	}

	/**
	 * Model the native WordPress request before module hooks are registered.
	 *
	 * @param bool   $frontend    Whether to simulate a frontend request.
	 * @param string $ajax_action Native AJAX action, or empty for an editor save.
	 *
	 * @return void
	 */
	private function set_kadence_request( bool $frontend, string $ajax_action ): void {
		$_SERVER['REQUEST_METHOD'] = $frontend ? 'GET' : 'POST';
		if ( $frontend || '' === $ajax_action ) {
			remove_filter( 'wp_doing_ajax', '__return_true' );
		} else {
			add_filter( 'wp_doing_ajax', '__return_true' );
		}
		if ( ! $frontend ) {
			$_POST['action']    = $ajax_action ?: 'editpost';
			$_REQUEST['action'] = $ajax_action ?: 'editpost';
		}
	}

	/**
	 * Create a native classic form with a safe email action.
	 *
	 * @param bool|null $captcha Native CAPTCHA setting, or absence.
	 *
	 * @return int
	 */
	private function create_classic_form( ?bool $captcha ): int {
		$attributes = [
			'uniqueID'         => 'sec003-classic',
			'recaptchaVersion' => 'v2',
			'honeyPot'         => true,
			'fields'           => [
				[
					'type'        => 'text',
					'label'       => 'Name',
					'required'    => true,
					'multiSelect' => false,
				],
			],
			'actions'          => [ 'email' ],
			'email'            => [
				[
					'emailTo' => 'sec003-recipient@example.com',
					'subject' => 'SEC003 native form',
					'html'    => false,
				],
			],
		];
		if ( null !== $captcha ) {
			$attributes['recaptcha'] = $captcha;
		}

		return $this->factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:kadence/form ' . wp_json_encode( $attributes ) . ' /-->',
			]
		);
	}

	/**
	 * Check whether classic hCaptcha verification is hooked before Kadence.
	 *
	 * @return bool
	 */
	private function has_classic_verifier(): bool {
		$callbacks = $GLOBALS['wp_filter']['wp_ajax_nopriv_kb_process_ajax_submit']->callbacks[9] ?? [];
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Form ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run the native AJAX action, capture email, and stop at the JSON exit.
	 *
	 * @return array
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function submit_classic_form(): array {
		$mail         = [];
		$mail_capture = static function ( $pre, array $args ) use ( &$mail ) {
			$mail[] = $args;
			return true;
		};
		$die_handler  = static function () {
			return static function () {
				throw new RuntimeException( 'sec003-json-exit' );
			};
		};
		add_filter( 'pre_wp_mail', $mail_capture, PHP_INT_MAX, 2 );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		$level = ob_get_level();
		ob_start();
		try {
			do_action( 'wp_ajax_nopriv_kb_process_ajax_submit' );
			self::fail( 'The native AJAX action must terminate with JSON.' );
		} catch ( RuntimeException $error ) {
			self::assertSame( 'sec003-json-exit', $error->getMessage() );
			$json = ob_get_contents();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			remove_filter( 'pre_wp_mail', $mail_capture, PHP_INT_MAX );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
		}
		return [
			'json' => json_decode( $json, true ),
			'mail' => $mail,
		];
	}
}
