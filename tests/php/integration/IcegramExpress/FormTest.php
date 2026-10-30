<?php
/**
 * FormTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\IcegramExpress;

use ES_Form_Widget;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\IcegramExpress\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use stdClass;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the Icegram Express form integration.
 *
 * @group icegram-express
 */
class FormTest extends HCaptchaWPTestCase {
	/**
	 * Plugin basenames used to detect Icegram Express editions.
	 */
	private const PLUGIN_BASENAMES = [
		'email-subscribers-premium/email-subscribers-premium.php',
		'email-subscribers/email-subscribers.php',
	];

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'icegram_express_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Remove frontend script state between form rendering tests.
	 */
	public function tearDown(): void {
		wp_dequeue_script( 'hcaptcha-icegram-express' );
		wp_deregister_script( 'hcaptcha-icegram-express' );

		parent::tearDown();
	}

	/**
	 * Other shortcodes and failed native validation are left unchanged.
	 */
	public function test_unrelated_shortcode_and_native_error(): void {
		$subject = new Form();
		self::assertSame( 'original', $subject->add_hcaptcha( 'original', 'other', [], [] ) );
		self::assertSame( [ 'status' => 'ERROR' ], $subject->verify( [ 'status' => 'ERROR' ], [] ) );
	}

	/**
	 * The native sidebar widget receives hCaptcha inside its ordinary form.
	 *
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function test_native_sidebar_widget_rendering(): void {
		$subject = new Form();
		$widget  = Mockery::mock( ES_Form_Widget::class );
		$subject->dynamic_sidebar( [ 'callback' => [ new stdClass(), 'display_callback' ] ] );
		$subject->dynamic_sidebar_after( 'sidebar-1', true );
		$level = ob_get_level();

		ob_start();
		$subject->dynamic_sidebar( [ 'callback' => [ $widget, 'display_callback' ] ] );
		self::assertSame( $level + 2, ob_get_level() );
		echo '<form data-form-id="3"><input type="submit" value="Join"></form>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Test fixture.
		$subject->dynamic_sidebar_after( 'sidebar-1', true );
		$output = ob_get_clean();

		self::assertSame( $level, ob_get_level() );
		self::assertStringContainsString( 'h-captcha', $output );
	}

	/**
	 * Default arguments and inline styling support generic subscription forms.
	 */
	public function test_generic_form_arguments_and_styles(): void {
		$subject = new Form();
		$method  = $this->set_method_accessibility( $subject, 'get_args' );
		$args    = $method->invoke( $subject, 0 );
		self::assertTrue( $args['mandatory'] );
		self::assertSame( 0, $args['id']['form_id'] );

		ob_start();
		$subject->print_inline_styles();
		self::assertStringContainsString( '.emaillist .h-captcha', ob_get_clean() );
	}

	/**
	 * A shortcode without a stored Icegram form uses the ordinary inline widget.
	 */
	public function test_shortcode_without_stored_form(): void {
		$forms_db = Mockery::mock();
		$forms_db->shouldReceive( 'get_form_by_id' )->with( 3 )->andReturn( null );
		$service = (object) [ 'forms_db' => $forms_db ];
		FunctionMocker::replace(
			'\HCaptcha\IcegramExpress\ES',
			static function () use ( $service ) {
				return $service;
			}
		);

		$markup = '<form><input type="submit" value="Join"></form>';
		$output = ( new Form() )->add_hcaptcha( $markup, 'email-subscribers-form', [ 'id' => 3 ], [] );

		self::assertStringContainsString( 'h-captcha', $output );
	}

	/**
	 * Popup forms defer their hCaptcha markup to the frontend script.
	 */
	public function test_popup_shortcode_defers_hcaptcha(): void {
		$forms_db = Mockery::mock();
		$forms_db->shouldReceive( 'get_form_by_id' )->with( 3 )->andReturn( (object) [ 'id' => 3 ] );
		$service = (object) [ 'forms_db' => $forms_db ];
		FunctionMocker::replace(
			'\HCaptcha\IcegramExpress\ES',
			static function () use ( $service ) {
				return $service;
			}
		);
		Mockery::mock( 'alias:ES_Forms_Table' )
			->shouldReceive( 'get_form_data_from_body' )
			->andReturn( [ 'show_in_popup' => 'yes' ] );

		$subject = new Form();
		$markup  = '<form><input type="submit" value="Join"></form>';
		self::assertSame( $markup, $subject->add_hcaptcha( $markup, 'email-subscribers-form', [ 'id' => 3 ], [] ) );
		ob_start();
		$subject->print_footer_scripts();
		$output = ob_get_clean();
		self::assertStringContainsString( 'hCaptchaWidgets', $output );
	}

	/**
	 * Test that both editions are recognized.
	 *
	 * @param string $plugin Plugin basename.
	 *
	 * @dataProvider dp_test_detects_icegram_variants
	 */
	public function test_detects_icegram_variants( string $plugin ): void {
		self::assertSame( self::PLUGIN_BASENAMES, hcaptcha()->modules['Icegram Express'][1] );
		self::assertSame( self::PLUGIN_BASENAMES, HCaptcha::get_class_source( Form::class ) );

		$filter = static function () use ( $plugin ): array {
			return [ $plugin ];
		};

		add_filter( 'pre_option_active_plugins', $filter );

		try {
			self::assertTrue( hcaptcha()->plugin_or_theme_active( self::PLUGIN_BASENAMES ) );
		} finally {
			remove_filter( 'pre_option_active_plugins', $filter );
		}
	}

	/**
	 * Icegram Express plugin basenames.
	 *
	 * @return array
	 */
	public function dp_test_detects_icegram_variants(): array {
		return [
			'Max'  => [ self::PLUGIN_BASENAMES[0] ],
			'Free' => [ self::PLUGIN_BASENAMES[1] ],
		];
	}

	/**
	 * Test a filled honeypot blocks subscription validation.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_icegram_express_nonce', 'hcaptcha_icegram_express' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => self::PLUGIN_BASENAMES,
				'form_id' => 1,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$result = ( new Form() )->verify( [ 'status' => 'SUCCESS' ], [ 'esfpx_form_id' => '1' ] );

		self::assertSame(
			[
				'status'  => 'ERROR',
				'message' => 'hcaptcha_error',
			],
			$result
		);
		self::assertSame(
			'Anti-spam check failed.',
			apply_filters( 'ig_es_subscription_messages', [] )['hcaptcha_error']
		);
	}

	/**
	 * Generic subscription forms retain widget identity checks with form ID zero.
	 */
	public function test_generic_form_rejects_foreign_widget(): void {
		$this->prepare_verify_post( 'hcaptcha_icegram_express_nonce', 'hcaptcha_icegram_express' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => self::PLUGIN_BASENAMES,
				'form_id' => 1,
			]
		);

		$result = ( new Form() )->verify( [ 'status' => 'SUCCESS' ], [ 'esfpx_form_id' => '0' ] );

		self::assertSame(
			[
				'status'  => 'ERROR',
				'message' => 'hcaptcha_error',
			],
			$result
		);
		self::assertSame(
			hcap_get_error_messages()['bad-signature'],
			apply_filters( 'ig_es_subscription_messages', [] )['hcaptcha_error']
		);
	}

	/**
	 * Bind subscription exemptions to the native submitted form.
	 *
	 * @param int         $native_form_id Native form ID.
	 * @param int         $widget_form_id Signed widget form ID.
	 * @param string|null $error_code     Expected verification error code.
	 *
	 * @dataProvider dp_test_exemption_uses_native_form
	 */
	public function test_exemption_uses_native_form( int $native_form_id, int $widget_form_id, ?string $error_code ): void {
		$this->prepare_verify_request( '' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => self::PLUGIN_BASENAMES,
				'form_id' => $widget_form_id,
			]
		);

		$remote_requests = 0;

		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) {
				return self::PLUGIN_BASENAMES === $source && 1 === $form_id ? false : $protect;
			},
			PHP_INT_MAX,
			3
		);
		add_filter(
			'pre_http_request',
			static function () use ( &$remote_requests ): array {
				++$remote_requests;

				return [ 'body' => wp_json_encode( [ 'success' => false ] ) ];
			},
			PHP_INT_MAX
		);

		$result = ( new Form() )->verify( [ 'status' => 'SUCCESS' ], [ 'esfpx_form_id' => (string) $native_form_id ] );

		if ( null === $error_code ) {
			self::assertSame( [ 'status' => 'SUCCESS' ], $result );
		} else {
			self::assertSame(
				[
					'status'  => 'ERROR',
					'message' => 'hcaptcha_error',
				],
				$result
			);
			self::assertSame(
				hcap_get_error_messages()[ $error_code ],
				apply_filters( 'ig_es_subscription_messages', [] )['hcaptcha_error']
			);
		}

		self::assertSame( 0, $remote_requests );
	}

	/**
	 * Native form targets and widget identities for exemption controls.
	 *
	 * @return array
	 */
	public function dp_test_exemption_uses_native_form(): array {
		return [
			'matching exempt form' => [ 1, 1, null ],
			'foreign exempt form'  => [ 2, 1, 'bad-signature' ],
			'protected form'       => [ 2, 2, 'empty' ],
		];
	}

	/**
	 * Test that the integration script is only registered after a form is shown.
	 *
	 * @return void
	 */
	public function test_print_footer_scripts(): void {
		$subject = new Form();

		$subject->print_footer_scripts();
		self::assertFalse( wp_script_is( 'hcaptcha-icegram-express', 'registered' ) );

		$this->set_protected_property( $subject, 'form_shown', true );

		ob_start();
		$subject->print_footer_scripts();
		$output = (string) ob_get_clean();

		self::assertTrue( wp_script_is( 'hcaptcha-icegram-express', 'registered' ) );
		self::assertStringContainsString( 'HCaptchaIcegramExpressObject', $output );
	}

	/**
	 * Check the native subscription email from the validated form data.
	 *
	 * @dataProvider dp_test_native_email_policy
	 *
	 * @param string $email   Native email.
	 * @param bool   $blocked Whether disposal policy rejects it.
	 */
	public function test_native_email_policy( string $email, bool $blocked ): void {
		add_filter(
			'hcap_disposable_email_domains',
			static function (): array {
				return [ 'disposable.example' => true ];
			}
		);
		$this->prepare_verify_post( 'hcaptcha_icegram_express_nonce', 'hcaptcha_icegram_express' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		hcaptcha()->settings()->set( 'icegram_express_status', [ 'form' ] );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => self::PLUGIN_BASENAMES,
				'form_id' => 1,
			]
		);
		$_POST['email']                        = 'safe@example.com';

		$result = ( new Form() )->verify(
			[ 'status' => 'SUCCESS' ],
			[
				'esfpx_form_id' => '1',
				'esfpx_email'   => $email,
			]
		);

		self::assertSame(
			$blocked ? [
				'status'  => 'ERROR',
				'message' => 'hcaptcha_error',
			] : [ 'status' => 'SUCCESS' ],
			$result
		);

		if ( $blocked ) {
			self::assertSame( hcap_get_error_messages()['disposable-email'], apply_filters( 'ig_es_subscription_messages', [] )['hcaptcha_error'] );
		}
	}

	/**
	 * Native email policy controls.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'disposable email' => [ 'USER@DISPOSABLE.EXAMPLE', true ],
			'permanent email'  => [ 'safe@example.com', false ],
			'email-free data'  => [ '', false ],
		];
	}
}
