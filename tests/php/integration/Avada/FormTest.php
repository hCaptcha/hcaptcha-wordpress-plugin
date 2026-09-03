<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Avada;

use HCaptcha\Avada\Form;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;

/**
 * Test FormTest class.
 *
 * @group avada
 */
class FormTest extends HCaptchaPluginWPTestCase {

	/**
	 * Avada Builder plugin entry file.
	 *
	 * @var string
	 */
	protected static $plugin = 'fusion-builder/fusion-builder.php';

	/**
	 * Theme stylesheet slug.
	 *
	 * @var string
	 */
	protected static string $theme = 'Avada';

	/**
	 * Hooks to replay after loading Avada Builder.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'after_setup_theme',
		'init',
		'fusion_builder_before_init',
		'wp_loaded',
	];

	/**
	 * Expected incorrect usage notices caused by the late theme load.
	 *
	 * @var string[]
	 */
	protected static array $theme_expected_incorrect_usage = [ "add_theme_support( 'title-tag' )" ];

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['field_types'], $_POST['formData'], $_POST['hcaptcha-widget-id'] );

		wp_dequeue_script( 'hcaptcha-avada' );
		wp_deregister_script( 'hcaptcha-avada' );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Form();

		self::assertSame( 'Avada', get_template() );
		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertTrue( class_exists( 'Fusion_Form_Builder' ) );
		self::assertTrue( shortcode_exists( 'fusion_form' ) );
		self::assertSame( 10, has_action( 'fusion_form_after_open', [ $subject, 'form_after_open' ] ) );
		self::assertSame( 10, has_filter( 'fusion_builder_form_submission_data', [ $subject, 'submission_data' ] ) );
		self::assertSame( 10, has_action( 'fusion_element_form_content', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_filter( 'fusion_form_demo_mode', [ $subject, 'verify' ] ) );
	}

	/**
	 * Test the live Avada Form element.
	 *
	 * @return void
	 */
	public function test_live_avada_form(): void {
		$form_id = $this->factory()->post->create(
			[
				'post_title'   => 'Avada Test Form',
				'post_name'    => 'avada-test-form',
				'post_content' => '[fusion_builder_container type="flex"][fusion_builder_row][fusion_builder_column type="1_1" layout="1_1"][fusion_form_text label="Text" name="text" required="yes" /][fusion_form_email label="Email" name="email" /][fusion_form_textarea label="Message" name="textarea" /][fusion_form_submit]Submit[/fusion_form_submit][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]',
				'post_status'  => 'publish',
				'post_type'    => 'fusion_form',
			]
		);

		update_option( 'hcaptcha_settings', [ 'avada_status' => [ 'form' ] ] );
		hcaptcha()->init_hooks();
		new Form();

		$output = do_shortcode( '[fusion_form form_post_id="' . $form_id . '" /]' );

		self::assertStringContainsString( 'class="fusion-form', $output );
		self::assertStringContainsString( '<button type="submit"', $output );
		self::assertStringContainsString( '<h-captcha', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Test submission_data().
	 *
	 * @return void
	 */
	public function test_submission_data(): void {
		$data     = [
			'data' => [
				'hcaptcha-widget-id'   => 'some_widget_id',
				'h-captcha-response'   => 'some_h_response',
				'g-recaptcha-response' => 'some_g_response',
				'hcap_fst_token'       => 'some_token',
				'hcap_hp_123'          => '',
				'hcap_hp_sig'          => 'some_signature',
				'input_1'              => 'some_text',
			],
			'more' => [ 'some' ],
		];
		$expected = [
			'data' => [
				'input_1' => 'some_text',
			],
			'more' => [ 'some' ],
		];

		$subject = new Form();

		self::assertSame( $expected, $subject->submission_data( $data ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$form_id    = 5;
		$args       = [
			'id' =>
				[
					'source'  => [ 'Avada' ],
					'form_id' => $form_id,
				],
		];
		$params     = [ 'id' => $form_id ];
		$wrong_html = 'some html';
		$html       = '<button type="submit">';
		$form       = $this->get_hcap_form( $args );
		$expected   = $form . $html;

		$subject = new Form();

		$subject->form_after_open( $args, $params );
		self::assertSame( $wrong_html, $subject->add_hcaptcha( $wrong_html, $args ) );
		self::assertSame( $expected, $subject->add_hcaptcha( $html, $args ) );

		add_filter( 'hcap_delay_api_event', '__return_true' );

		$actual = $subject->add_hcaptcha( $html, $args );

		remove_filter( 'hcap_delay_api_event', '__return_true' );

		self::assertStringContainsString( 'class="h-captcha hcaptcha-api-delayed"', $actual );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_verify(): void {
		$demo_mode         = true;
		$form_id           = 123;
		$hcaptcha_response = 'some_response';
		$field_types       = [
			'input_1' => 'text',
		];
		$form_data         = $this->get_form_data( $form_id, $hcaptcha_response );

		$this->prepare_verify_request( $hcaptcha_response );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['field_types'] = json_encode( $field_types );
		$_POST['formData']    = $form_data;

		$subject = new Form();

		self::assertSame( $demo_mode, $subject->verify( $demo_mode ) );
	}

	/**
	 * Test verify() when widget id is bad.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_bad_widget_id(): void {
		$form_id           = 123;
		$hcaptcha_response = 'some_response';
		$die_arr           = [];
		$expected          = [
			'{"status":"error","info":{"hcaptcha":"Bad hCaptcha signature!"}}',
			'',
			[],
		];
		$bad_widget_id     = HCaptcha::widget_id_value(
			[
				'source'  => [ 'WordPress' ],
				'form_id' => $form_id,
			]
		);

		$this->prepare_verify_request( $hcaptcha_response );

		$_POST['formData'] = $this->get_form_data( $form_id, $hcaptcha_response, $bad_widget_id );

		$subject = new Form();

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify( true );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_not_verified(): void {
		$hcaptcha_response = 'some_response';
		$die_arr           = [];
		$expected          = [
			'{"status":"error","info":{"hcaptcha":"Please complete the hCaptcha."}}',
			'',
			[],
		];

		$this->prepare_verify_request( $hcaptcha_response, false );

		$subject = new Form();

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify( true );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Form();

		// By default, form_shown is false -> the script should NOT be enqueued.
		$subject->enqueue_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-avada' ) );

		// When form_shown is true -> the script should be enqueued.
		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();
		self::assertTrue( wp_script_is( 'hcaptcha-avada' ) );
	}

	/**
	 * Get form data.
	 *
	 * @param int    $form_id           Form id.
	 * @param string $hcaptcha_response hCaptcha response.
	 * @param string $widget_id         Widget id.
	 *
	 * @return string
	 */
	private function get_form_data( int $form_id, string $hcaptcha_response, string $widget_id = '' ): string {
		$widget_id = $widget_id ?: HCaptcha::widget_id_value(
			[
				'source'  => [ 'Avada' ],
				'form_id' => $form_id,
			]
		);

		return http_build_query(
			[
				'h-captcha-response'         => $hcaptcha_response,
				'hcaptcha-widget-id'         => $widget_id,
				"fusion-form-nonce-$form_id" => 'some_fusion_form_nonce',
				'input_1'                    => 'some_text',
			]
		);
	}
}
