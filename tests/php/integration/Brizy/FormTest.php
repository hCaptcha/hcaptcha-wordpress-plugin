<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\Brizy;

use Brizy_Editor_Project;
use HCaptcha\Brizy\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the Brizy form integration.
 *
 * @group brizy
 */
class FormTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'brizy_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['data'] );

		parent::tearDown();
	}

	/**
	 * Test that the Brizy hooks are registered.
	 */
	public function test_constructor_and_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 10, has_filter( 'brizy_content', [ $subject, 'add_captcha' ] ) );
		self::assertSame( 10, has_filter( 'brizy_form', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
		self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'enqueue_scripts' ] ) );
		self::assertSame( 10, has_filter( 'script_loader_tag', [ $subject, 'add_type_module' ] ) );
	}

	/**
	 * Test captcha placement and form arguments.
	 */
	public function test_add_captcha(): void {
		$content = '<div class="brz-forms2 brz-forms2__item brz-forms2__item-button">Button</div>';
		$project = Mockery::mock( Brizy_Editor_Project::class );
		$post    = get_post( wp_insert_post( [ 'post_title' => 'Brizy test' ] ) );
		$subject = new Form();
		$args    = [];

		self::assertSame( $content, $subject->add_captcha( $content, $project, $post ) );

		FunctionMocker::replace( '\\HCaptcha\\Helpers\\HCaptcha::get_class_source', [ 'source' ] );
		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::form',
			static function ( array $form_args ) use ( &$args ): string {
				$args = $form_args;

				return 'captcha';
			}
		);

		self::assertSame(
			'<div class="brz-forms2 brz-forms2__item">captcha</div>' . $content,
			$subject->add_captcha( $content, $project, $post, 'body' )
		);
		self::assertSame(
			[
				'action' => 'hcaptcha_brizy_form',
				'name'   => 'hcaptcha_brizy_nonce',
				'id'     => [
					'source'  => [ 'source' ],
					'form_id' => 'form',
				],
			],
			$args
		);
	}

	/**
	 * Test that submitted fields are passed to verification.
	 */
	public function test_verify(): void {
		$post_data = [
			'email'                => 'person@example.com',
			'h-captcha-response'   => 'response-token',
			'hcaptcha-widget-id'   => 'widget-id',
			'hcaptcha_brizy_nonce' => 'nonce',
			'hcap_hp_dynamic'      => '',
			'hcap_hp_sig'          => 'honeypot-signature',
			'hcap_fst_token'       => 'fst-token',
		];
		$data      = [];

		foreach ( $post_data as $name => $value ) {
			$data[] = [
				'name'  => $name,
				'value' => $value,
			];
		}

		$data[] = [ 'name' => 'missing-value' ];
		$data[] = [
			'name'  => [ 'invalid' ],
			'value' => 'invalid',
		];

		$_POST['data'] = wp_json_encode( $data );
		$actual        = [];

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\API::verify_post_data',
			static function ( string $name, string $action, array $submitted_data ) use ( &$actual ): ?string {
				$actual = [ $name, $action, $submitted_data ];

				return null;
			}
		);

		self::assertSame( 'form', ( new Form() )->verify( 'form' ) );
		self::assertSame( [ 'hcaptcha_brizy_nonce', 'hcaptcha_brizy_form', $post_data ], $actual );
	}

	/**
	 * Test that the legacy response field is normalized.
	 */
	public function test_verify_with_legacy_response_name(): void {
		$_POST['data'] = wp_json_encode(
			[
				[
					'name'  => 'g-recaptcha-response',
					'value' => 'response-token',
				],
				[
					'name'  => 'hcaptcha_brizy_nonce',
					'value' => 'nonce',
				],
			]
		);
		$actual        = [];

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\API::verify_post_data',
			static function ( string $name, string $action, array $submitted_data ) use ( &$actual ): ?string {
				$actual = [ $name, $action, $submitted_data ];

				return null;
			}
		);

		( new Form() )->verify( 'form' );

		self::assertSame(
			[
				'hcaptcha_brizy_nonce',
				'hcaptcha_brizy_form',
				[
					'h-captcha-response'   => 'response-token',
					'hcaptcha_brizy_nonce' => 'nonce',
				],
			],
			$actual
		);
	}

	/**
	 * Test that a verification error is returned as JSON.
	 *
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify_with_error(): void {
		FunctionMocker::replace( '\\HCaptcha\\Helpers\\API::verify_post_data', 'Verification failed.' );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
				};
			}
		);

		ob_start();
		( new Form() )->verify( 'form' );
		$result = json_decode( (string) ob_get_clean(), true );

		self::assertFalse( $result['success'] );
		self::assertSame(
			[
				'code'    => 400,
				'message' => 'Verification failed.',
			],
			$result['data']
		);
	}

	/**
	 * Test that Brizy styles are printed only once.
	 */
	public function test_print_inline_styles(): void {
		$styles = [];

		FunctionMocker::replace(
			'\\HCaptcha\\Helpers\\HCaptcha::css_display',
			static function ( string $css ) use ( &$styles ): void {
				$styles[] = $css;
			}
		);

		$subject = new Form();
		$subject->print_inline_styles();
		$subject->print_inline_styles();

		self::assertCount( 1, $styles );
		self::assertStringContainsString( 'margin-bottom: 0;', $styles[0] );
	}

	/**
	 * Test script loads when a form is shown.
	 *
	 * @param bool $form_shown Whether a form is shown.
	 * @param bool $expected Whether the script is expected.
	 * @dataProvider dp_test_enqueue_scripts
	 */
	public function test_enqueue_scripts( bool $form_shown, bool $expected ): void {
		$subject               = new Form();
		hcaptcha()->form_shown = $form_shown;

		$subject->enqueue_scripts();

		self::assertSame( $expected, wp_script_is( Form::HANDLE ) );

		if ( $expected ) {
			self::assertSame(
				HCAPTCHA_URL . '/assets/js/hcaptcha-brizy' . hcap_min_suffix() . '.js',
				wp_scripts()->registered[ Form::HANDLE ]->src
			);
		}
	}

	/**
	 * Data provider for script loading.
	 *
	 * @return array
	 */
	public function dp_test_enqueue_scripts(): array {
		return [
			'not shown' => [ false, false ],
			'shown'     => [ true, true ],
		];
	}

	/**
	 * Test adding a module type only to the Brizy script.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_add_type_module(): void {
		$subject = new Form();
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag = '<script src="script.js"></script>';

		self::assertSame( $tag, $subject->add_type_module( $tag, 'other', 'script.js' ) );
		self::assertSame(
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			'<script type="module" src="script.js"></script>',
			$subject->add_type_module( $tag, Form::HANDLE, 'script.js' )
		);
	}

	/**
	 * Test filled honeypot blocks Brizy form processing.
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$response = 'some response';
		$data     = [
			[
				'name'  => HCaptcha::HCAPTCHA_WIDGET_ID,
				'value' => HCaptcha::widget_id_value(
					[
						'source'  => [ 'brizy/brizy.php' ],
						'form_id' => 'form',
					]
				),
			],
			[
				'name'  => 'hcaptcha_brizy_nonce',
				'value' => wp_create_nonce( 'hcaptcha_brizy_form' ),
			],
			[
				'name'  => 'h-captcha-response',
				'value' => $response,
			],
			[
				'name'  => 'hcap_hp_test',
				'value' => 'bot',
			],
			[
				'name'  => 'hcap_hp_sig',
				'value' => wp_create_nonce( 'hcap_hp_test' ),
			],
		];

		$_POST['data'] = wp_json_encode( $data );

		$this->prepare_verify_request( $response );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
				};
			}
		);

		ob_start();
		( new Form() )->verify( 'form' );
		$json = (string) ob_get_clean();
		$data = json_decode( $json, true );

		self::assertFalse( $data['success'] );
		self::assertSame( 'Anti-spam check failed.', $data['data']['message'] );
	}
}
