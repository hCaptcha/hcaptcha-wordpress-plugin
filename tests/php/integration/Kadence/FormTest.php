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

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Kadence\Form;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use ReflectionClass;
use ReflectionException;
use WP_Block_Type_Registry;

/**
 * Test Kadence Form.
 *
 * @group kadence
 * @group kadence-form
 */
class FormTest extends HCaptchaPluginWPTestCase {
	/**
	 * Resolve native widget and nested block locations safely.
	 */
	public function test_form_block_lookup_fallbacks(): void {
		$subject       = new Form();
		$get_blocks    = $this->set_method_accessibility( $subject, 'get_form_blocks' );
		$get_form      = $this->set_method_accessibility( $subject, 'get_form' );
		$get_reference = $this->set_method_accessibility( $subject, 'get_referenced_form' );
		$nested_form   = [
			'blockName' => 'kadence/form',
			'attrs'     => [ 'uniqueID' => 'target' ],
		];

		$_POST['_kb_form_id'] = [];
		self::assertSame( [], $get_blocks->invoke( $subject, 'block-unknown' ) );

		$_POST['_kb_form_id'] = 'missing';
		self::assertSame( [], $get_blocks->invoke( $subject, 'block-unknown' ) );
		self::assertSame(
			$nested_form,
			$get_form->invoke(
				$subject,
				[
					[
						'blockName'   => 'core/group',
						'innerBlocks' => [ $nested_form ],
					],
				],
				'target'
			)
		);
		self::assertSame(
			[],
			$get_reference->invoke(
				$subject,
				[
					'blockName' => 'core/block',
					'attrs'     => [ 'ref' => 9999999 ],
				],
				'target',
				[]
			)
		);
	}

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
	 * Test that the live Kadence form block is registered.
	 *
	 * @return void
	 */
	public function test_live_plugin_is_loaded(): void {
		$plugin_file = wp_normalize_path( ( new ReflectionClass( 'Kadence_Blocks_Form_Block' ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/kadence-blocks/' ), $plugin_file );
		self::assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'kadence/form' ) );
	}

	/**
	 * Tear down the test.
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

		$subject = new Form();

		self::assertSame( 8, has_action( 'wp_print_footer_scripts', [ $subject, 'dequeue_kadence_captcha_api' ] ) );

		self::assertSame( 9, has_action( 'wp_ajax_kb_process_ajax_submit', [ $subject, 'process_ajax' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_kb_process_ajax_submit', [ $subject, 'process_ajax' ] ) );

		if ( $is_frontend ) {
			self::assertSame( 10, has_filter( 'render_block', [ $subject, 'render_block' ] ) );
			self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		} else {
			self::assertFalse( has_filter( 'render_block', [ $subject, 'render_block' ] ) );
			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
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
		$form_id       = 5;
		$block_content = 'some block content';
		$block         = [
			'blockName' => 'some',
			'attrs'     => [
				'postID' => $form_id,
			],
		];
		$instance      = Mockery::mock( 'WP_Block' );

		$subject = new Form();

		self::assertSame( $block_content, $subject->render_block( $block_content, $block, $instance ) );

		$block['blockName'] = 'kadence/form';
		$hcap_form          = $this->get_hcap_form(
			[
				'id' => [
					'source'  => [ 'kadence-blocks/kadence-blocks.php' ],
					'form_id' => $form_id,
				],
			]
		);

		// Replace reCaptcha V2 + V3.
		$block_content = '<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">Some block content</div><input type="hidden" name="recaptcha_response" class="some-class">';
		$expected      = str_replace(
			'<div class="kadence-blocks-form-field google-recaptcha-checkout-wrap">Some block content</div><input type="hidden" name="recaptcha_response" class="some-class">',
			$hcap_form,
			$block_content
		);

		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );

		// No reCaptcha, add hCaptcha before submitting.
		$block_content = '<div class="kadence-blocks-form-field kb-submit-field">Some block content</div>';
		$expected      = $hcap_form . $block_content;

		self::assertFalse( $this->get_protected_property( $subject, 'has_captcha' ) );
		self::assertSame( $expected, $subject->render_block( $block_content, $block, $instance ) );
		self::assertTrue( $this->get_protected_property( $subject, 'has_captcha' ) );
	}

	/**
	 * Test process_ajax().
	 *
	 * @return void
	 */
	public function test_process_ajax(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );
		$this->prepare_widget_id( 0 );

		$subject = new Form();

		$subject->process_ajax();
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

		$die_arr       = [];
		$expected_json = [
			'success' => false,
			'data'    => [
				'html'         => "<div class=\"kadence-blocks-form-message kadence-blocks-form-warning\">$error_message</div>",
				'console'      => 'hCaptcha Failed',
				'required'     => null,
				'headers_sent' => true,
			],
		];
		$expected      = [
			'',
			'',
			[ 'response' => null ],
		];

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

		$subject = new Form();

		ob_start();
		$subject->process_ajax();
		$json = ob_get_clean();

		$expected_json['data']['headers_sent'] = json_decode( $json, true )['data']['headers_sent'];

		self::assertSame( wp_json_encode( $expected_json ), $json );
		self::assertSame( $expected, $die_arr );
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
		$die_arr           = [];
		$expected_json     = [
			'success' => false,
			'data'    => [
				'html'         => "<div class=\"kadence-blocks-form-message kadence-blocks-form-warning\">$error_message</div>",
				'console'      => 'hCaptcha Failed',
				'required'     => null,
				'headers_sent' => true,
			],
		];
		$expected          = [
			'',
			'',
			[ 'response' => null ],
		];

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

		$subject = new Form();

		ob_start();
		$subject->process_ajax();
		$json = ob_get_clean();

		$expected_json['data']['headers_sent'] = json_decode( $json, true )['data']['headers_sent'];

		self::assertSame( wp_json_encode( $expected_json ), $json );
		self::assertSame( $expected, $die_arr );
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
		$handle = 'hcaptcha-kadence';

		$subject = new Form();

		self::assertFalse( wp_script_is( $handle ) );

		$subject::enqueue_scripts();

		self::assertTrue( wp_script_is( $handle ) );
	}
	/**
	 * A malformed post selector cannot borrow another post's exemption.
	 *
	 * @param string $separator Text removed by Kadence's native sanitizer.
	 *
	 * @return void
	 * @dataProvider dp_sec020_post_id_aliases
	 */
	public function test_sec020_malformed_post_id_uses_native_identity( string $separator ): void {
		[ $exempt_id, $protected_id, $suffix ] = $this->create_sec020_forms();
		$alias                                 = $exempt_id . $separator . $suffix;
		self::assertSame( (string) $protected_id, sanitize_text_field( wp_unslash( $alias ) ) );

		$exempt = $this->exempt_sec020_form( $exempt_id );

		try {
			$_POST['_kb_form_id'] = 'sec020-form';
			$this->prepare_widget_id( $exempt_id );
			$subject = new Form();
			$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke(
				$subject,
				[
					'_kb_form_post_id' => $alias,
					'kb_field_0'       => 'reader@example.com',
				]
			);

			self::assertSame( $protected_id, $entry['expected_id']['form_id'] );
			self::assertSame( get_post( $protected_id )->post_modified_gmt, $entry['form_date_gmt'] );
			self::assertSame( 'reader@example.com', $entry['data']['Protected contact'] ?? null );
			self::assertArrayNotHasKey( 'Exempt contact', $entry['data'] );
			self::assertSame( 'Bad hCaptcha signature!', API::verify_request( null, $entry ) );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * Encoded separators that Kadence removes before post lookup.
	 *
	 * @return array
	 */
	public function dp_sec020_post_id_aliases(): array {
		return [
			'HTML tag'       => [ '<br>' ],
			'percent escape' => [ '%20' ],
		];
	}

	/**
	 * A canonical signed exemption remains valid for its own post.
	 *
	 * @return void
	 */
	public function test_sec020_own_exemption_remains_valid(): void {
		[ $exempt_id ] = $this->create_sec020_forms();
		$exempt        = $this->exempt_sec020_form( $exempt_id );

		try {
			$_POST['_kb_form_id'] = 'sec020-form';
			$this->prepare_widget_id( $exempt_id );
			$subject = new Form();
			$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke(
				$subject,
				[ '_kb_form_post_id' => (string) $exempt_id ]
			);

			self::assertSame( $exempt_id, $entry['expected_id']['form_id'] );
			self::assertNull( API::verify_request( null, $entry ) );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt );
		}
	}

	/**
	 * A protected canonical post still accepts a verified challenge.
	 *
	 * @return void
	 */
	public function test_sec020_protected_post_accepts_valid_challenge(): void {
		[ , $protected_id ] = $this->create_sec020_forms();
		$response           = 'sec020-valid-response';
		$this->prepare_verify_request( $response );
		$_POST['_kb_form_id'] = 'sec020-form';
		$this->prepare_widget_id( $protected_id );
		$subject = new Form();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke(
			$subject,
			[
				'_kb_form_post_id'   => (string) $protected_id,
				'h-captcha-response' => $response,
			]
		);

		self::assertSame( $protected_id, $entry['expected_id']['form_id'] );
		self::assertNull( API::verify_request( $response, $entry ) );
	}

	/**
	 * Create two real Kadence form posts whose ids share a numeric prefix.
	 *
	 * @return array{int, int, string}
	 */
	private function create_sec020_forms(): array {
		$form_id      = 'sec020-form';
		$exempt_id    = 0;
		$protected_id = 0;
		$suffix       = '';

		// Use vacant low IDs so repeated tests do not grow AUTO_INCREMENT.
		for ( $candidate = 100; $candidate < 1000 && ! $exempt_id; ++$candidate ) {
			if ( get_post( $candidate ) ) {
				continue;
			}

			for ( $digit = 1; $digit <= 9; ++$digit ) {
				$target = (int) ( $candidate . $digit );

				if ( ! get_post( $target ) ) {
					$exempt_id    = $candidate;
					$protected_id = $target;
					$suffix       = (string) $digit;
					break;
				}
			}
		}

		self::assertNotSame( 0, $exempt_id );
		$created_id = $this->factory()->post->create(
			[
				'import_id'    => $exempt_id,
				'post_content' => '<!-- wp:kadence/form {"uniqueID":"' . $form_id . '","fields":[{"type":"email","label":"Exempt contact"}]} /-->',
			]
		);
		self::assertSame( $exempt_id, $created_id );
		$created_id = $this->factory()->post->create(
			[
				'import_id'    => $protected_id,
				'post_content' => '<!-- wp:kadence/form {"uniqueID":"' . $form_id . '","fields":[{"type":"email","label":"Protected contact"}]} /-->',
			]
		);
		self::assertSame( $protected_id, $created_id );

		return [ $exempt_id, $protected_id, $suffix ];
	}

	/**
	 * Exempt only the specified Kadence form post.
	 *
	 * @param int $form_id Form post id.
	 *
	 * @return callable
	 */
	private function exempt_sec020_form( int $form_id ): callable {
		$exempt = static function ( $protect, $source, $candidate ) use ( $form_id ) {
			return [ 'kadence-blocks/kadence-blocks.php' ] === $source && $form_id === $candidate ? false : $protect;
		};
		add_filter( 'hcap_protect_form', $exempt, 10, 3 );

		return $exempt;
	}

	/**
	 * Apply email policy independently of labels and the last email field.
	 *
	 * @param bool $blocked Whether the trusted email is disposable.
	 * @dataProvider dp_sec063_email_policy
	 */
	public function test_sec063_typed_email_policy( bool $blocked ): void {
		$this->prepare_verify_request( 'sec063-challenge' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$post_id              = $this->factory()->post->create(
			[ 'post_content' => '<!-- wp:kadence/form {"uniqueID":"sec063","fields":[{"type":"email","label":"Payment contact"},{"type":"email","label":"Другой адрес"},{"type":"text","label":"email"}]} /-->' ]
		);
		$_POST['_kb_form_id'] = 'sec063';
		$this->prepare_widget_id( $post_id );
		$data    = [
			'_kb_form_post_id' => $post_id,
			'kb_field_0'       => $blocked ? ' person@sec063-disposable.test ' : ' person@example.com ',
			'kb_field_1'       => 'reader@example.com',
			'kb_field_2'       => 'spoof@sec063-disposable.test',
		];
		$subject = new Form();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $data );

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
	 * Apply native email policy in reusable blocks and supported block widgets.
	 *
	 * @param string $location Native form location.
	 * @param bool   $blocked Whether the submitted address is disposable.
	 * @dataProvider dp_sec063_native_locations
	 */
	public function test_sec063_native_location_policy( string $location, bool $blocked ): void {
		$this->prepare_verify_request( 'sec063-challenge' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
		$content = '<!-- wp:kadence/form {"uniqueID":"sec063-location","fields":[{"type":"email","label":"Адрес"},{"type":"text","label":"email"}]} /-->';
		$post_id = 0;
		if ( in_array( $location, [ 'reusable', 'reusable-cycle' ], true ) ) {
			$reference = $this->factory()->post->create(
				[
					'post_type'    => 'wp_block',
					'post_content' => $content,
				]
			);

			if ( 'reusable-cycle' === $location ) {
				wp_update_post(
					[
						'ID'           => $reference,
						'post_content' => '<!-- wp:block {"ref":' . $reference . '} /-->' . $content,
					]
				);
			}
			$post_id        = $this->factory()->post->create( [ 'post_content' => '<!-- wp:block {"ref":' . $reference . '} /-->' ] );
			$native_post_id = $post_id;
		} else {
			update_option( 'widget_block', [ 5 => [ 'content' => $content ] ] );
			$native_post_id = 'unknown-widget' === $location ? 'block-unknown' : 'block-5';
		}
		$_POST['_kb_form_id'] = 'sec063-location';
		$this->prepare_widget_id( $post_id );
		$data    = [
			'_kb_form_post_id' => $native_post_id,
			'kb_field_0'       => $blocked ? 'person@sec063-disposable.test' : 'reader@example.com',
			'kb_field_1'       => 'spoof@sec063-disposable.test',
		];
		$subject = new Form();
		$entry   = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $data );
		self::assertSame( $blocked ? 'Please use a permanent email address.' : null, API::verify_request( null, $entry ) );
	}

	/**
	 * Supported native form locations and block/allow controls.
	 *
	 * @return array
	 */
	public function dp_sec063_native_locations(): array {
		return [
			'reusable blocked'       => [ 'reusable', true ],
			'reusable allowed'       => [ 'reusable', false ],
			'reusable cycle blocked' => [ 'reusable-cycle', true ],
			'reusable cycle allowed' => [ 'reusable-cycle', false ],
			'widget blocked'         => [ 'widget', true ],
			'widget allowed'         => [ 'widget', false ],
			'unknown widget blocked' => [ 'unknown-widget', true ],
			'unknown widget allowed' => [ 'unknown-widget', false ],
		];
	}
}
