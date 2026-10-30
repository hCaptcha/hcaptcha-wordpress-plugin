<?php
/**
 * SubscribeTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\Tests\Integration\WPDiscuz;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WPDiscuz\Comment;
use HCaptcha\WPDiscuz\Subscribe;
use Mockery;
use tad\FunctionMocker\FunctionMocker;
use function function_exists;

/**
 * Test Subscribe class.
 *
 * @group wpdiscuz
 */
class SubscribeTest extends HCaptchaWPTestCase {

	/**
	 * The wpDiscuz core class mock.
	 *
	 * @var Mockery\MockInterface|WpdiscuzCore
	 */
	private $wp_discuz;

	/**
	 * Setup test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'wpdiscuz_status', 'subscribe_form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );

		$options                  = Mockery::mock( 'WpdiscuzOptions' );
		$options->recaptcha       = [
			'siteKey'               => 'some site key',
			'showForGuests'         => 1,
			'showForUsers'          => 1,
			'isShowOnSubscribeForm' => 1,
		];
		$this->wp_discuz          = Mockery::mock( 'WpdiscuzCore' );
		$this->wp_discuz->options = $options;

		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				return 'wpDiscuz' === $function_name || function_exists( $function_name );
			}
		);
		FunctionMocker::replace( 'wpDiscuz', $this->wp_discuz );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new Subscribe();

		self::assertSame( 12, has_action( 'init', [ $subject, 'block_recaptcha' ] ) );
		self::assertSame( 11, has_action( 'wp_enqueue_scripts', [ $subject, 'enqueue_scripts' ] ) );

		self::assertSame( 10, has_action( 'wpdiscuz_after_subscription_form', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_wpdAddSubscription', [ $subject, 'verify' ] ) );
		self::assertSame( 9, has_action( 'wp_ajax_nopriv_wpdAddSubscription', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Replacing subscription protection keeps native comment reCAPTCHA.
	 *
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_block_recaptcha_preserves_comment_protection(): void {
		$subject = new Subscribe();
		$subject->block_recaptcha();

		self::assertSame( 'some site key', $this->wp_discuz->options->recaptcha['siteKey'] );
		self::assertSame( 1, $this->wp_discuz->options->recaptcha['showForGuests'] );
		self::assertSame( 1, $this->wp_discuz->options->recaptcha['showForUsers'] );
		self::assertSame( 0, $this->wp_discuz->options->recaptcha['isShowOnSubscribeForm'] );
	}

	/**
	 * Both replacement surfaces disable only their respective native controls.
	 *
	 * @noinspection PhpUndefinedFieldInspection
	 */
	public function test_both_surfaces_replace_their_native_captcha(): void {
		( new Subscribe() )->block_recaptcha();
		( new Comment() )->block_recaptcha();

		self::assertSame( 'some site key', $this->wp_discuz->options->recaptcha['siteKey'] );
		self::assertSame( 0, $this->wp_discuz->options->recaptcha['showForGuests'] );
		self::assertSame( 0, $this->wp_discuz->options->recaptcha['showForUsers'] );
		self::assertSame( 0, $this->wp_discuz->options->recaptcha['isShowOnSubscribeForm'] );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		wp_dequeue_script( 'wpdiscuz-google-recaptcha' );
		wp_deregister_script( 'wpdiscuz-google-recaptcha' );

		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertFalse( wp_script_is( 'wpdiscuz-google-recaptcha' ) );

		wp_enqueue_script(
			'wpdiscuz-google-recaptcha',
			'https://domain.tld/api.js',
			[],
			'1.0',
			true
		);

		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha' ) );

		$subject = new Subscribe();

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha', 'registered' ) );
		self::assertTrue( wp_script_is( 'wpdiscuz-google-recaptcha' ) );
		self::assertTrue( wp_script_is( 'hcaptcha-wpdiscuz-comment' ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 */
	public function test_add_hcaptcha(): void {
		$args     = [
			'id' => [
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => 0,
			],
		];
		$expected = $this->get_hcap_form( $args );

		ob_start();

		$subject = new Subscribe();

		$subject->add_hcaptcha();

		$output = (string) ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
	}

	/**
	 * Test verify().
	 *
	 * @return void
	 */
	public function test_verify(): void {
		$hcaptcha_response = 'some response';

		$this->prepare_verify_request( $hcaptcha_response );
		$this->prepare_subscription_identity();

		$subject = new Subscribe();

		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertFalse( isset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] ) );
	}

	/**
	 * Subscription policy uses the native guest address or the logged-in account.
	 *
	 * @param bool $logged_in Whether native subscription uses an account address.
	 * @param bool $blocked   Whether that address is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 */
	public function test_native_subscription_email_policy( bool $logged_in, bool $blocked ): void {
		$email = $blocked ? "it's@blocked.test" : "it's@allowed.test";
		wp_set_current_user( $logged_in ? $this->factory()->user->create( [ 'user_email' => $email ] ) : 0 );
		$this->prepare_verify_request( 'valid-subscription-response' );
		$this->prepare_subscription_identity();
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST['wpdiscuzSubscriptionEmail'] = $logged_in ? 'forged@allowed.test' : wp_slash( $email );
		$_POST['email']                     = 'forged@allowed.test';
		$checked                            = [];
		$die_arr                            = [];
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);
		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return "it's@blocked.test" === $address;
			},
			10,
			2
		);
		ob_start();
		( new Subscribe() )->verify();
		$output = ob_get_clean();

		self::assertSame( $blocked ? '{"success":false,"data":"Please use a permanent email address."}' : '', $output );
		self::assertSame( [ $email ], $checked );
		self::assertSame( $blocked ? [ '', '', [ 'response' => null ] ] : [], $die_arr );
		wp_set_current_user( 0 );
	}

	/**
	 * Native subscription recipient outcomes.
	 *
	 * @return array
	 */
	public function dp_email_policy(): array {
		return [
			'blocked guest'   => [ false, true ],
			'allowed guest'   => [ false, false ],
			'blocked account' => [ true, true ],
			'allowed account' => [ true, false ],
		];
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @return void
	 */
	public function test_verify_NOT_verified(): void {
		$hcaptcha_response = 'some response';
		$die_arr           = [];
		$expected          = [
			'',
			'',
			[ 'response' => null ],
		];

		$this->prepare_verify_request( $hcaptcha_response, false );
		$this->prepare_subscription_identity();

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new Subscribe();

		ob_start();

		$subject->verify();

		self::assertSame( '{"success":false,"data":"Please complete the hCaptcha."}', ob_get_clean() );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertFalse( isset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] ) );
		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Prepare the native subscription post selector and its rendered widget.
	 */
	private function prepare_subscription_identity(): void {
		$_POST['postId']                       = '5';
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => 5,
			]
		);
	}

	/**
	 * Bind an exemption to the post selected by wpDiscuz native sanitizer.
	 *
	 * @param string      $native_selector Native post selector.
	 * @param int         $widget_post_id  Signed widget post ID.
	 * @param int         $expected_post_id Expected native target.
	 * @param string|null $error_code      Expected verification error code.
	 *
	 * @dataProvider dp_test_exemption_uses_native_post_selector
	 */
	public function test_exemption_uses_native_post_selector(
		string $native_selector,
		int $widget_post_id,
		int $expected_post_id,
		?string $error_code
	): void {
		$this->prepare_verify_request( '' );

		$_POST['postId']                       = $native_selector;
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => $widget_post_id,
			]
		);

		$expected_id     = [];
		$remote_requests = 0;

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
				};
			}
		);
		add_filter(
			'hcap_verify_request',
			static function ( $result, $deprecated, $error_info ) use ( &$expected_id ) {
				$expected_id = $error_info->expected_id;

				return $result;
			},
			PHP_INT_MAX,
			3
		);
		add_filter(
			'hcap_protect_form',
			static function ( $protect, $source, $form_id ) {
				return [ 'wpdiscuz/class.WpdiscuzCore.php' ] === $source && 1 === $form_id ? false : $protect;
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

		ob_start();
		( new Subscribe() )->verify();
		$output = (string) ob_get_clean();

		self::assertSame(
			[
				'source'  => [ 'wpdiscuz/class.WpdiscuzCore.php' ],
				'form_id' => $expected_post_id,
			],
			$expected_id
		);
		self::assertSame(
			null === $error_code ? '' : wp_json_encode(
				[
					'success' => false,
					'data'    => hcap_get_error_messages()[ $error_code ],
				]
			),
			$output
		);
		self::assertSame( 0, $remote_requests );
	}

	/**
	 * Native post selectors and signed widgets for exemption controls.
	 *
	 * @return array
	 */
	public function dp_test_exemption_uses_native_post_selector(): array {
		return [
			'matching exempt post'    => [ '1', 1, 1, null ],
			'foreign exempt post'     => [ '12', 1, 12, 'bad-signature' ],
			'protected post'          => [ '12', 12, 12, 'empty' ],
			'native sanitizer replay' => [ '1foo2', 1, 12, 'bad-signature' ],
			'native sanitizer target' => [ '1foo2', 12, 12, 'empty' ],
			'missing post ID'         => [ '0', 12, 0, 'bad-signature' ],
		];
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 */
	public function test_print_inline_styles(): void {
		$expected = '#wpdiscuz-subscribe-form .h-captcha{margin-top:5px;margin-left:auto}';
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Subscribe();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
