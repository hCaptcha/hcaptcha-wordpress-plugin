<?php
/**
 * AutoVerifyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\AutoVerify;

use HCaptcha\AutoVerify\AutoVerify;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;

/**
 * Test AutoVerify class.
 *
 * @group auto-verify
 */
class AutoVerifyTest extends HCaptchaWPTestCase {

	/**
	 * Teardown test.
	 */
	public function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'], $GLOBALS['current_screen'] );
		delete_transient( AutoVerify::TRANSIENT );

		parent::tearDown();
	}

	/**
	 * Test init() and init_hooks().
	 */
	public function test_init_and_init_hooks(): void {
		$subject = new AutoVerify();
		$subject->init();

		self::assertSame( -PHP_INT_MAX, has_action( 'init', [ $subject, 'verify' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'the_content', [ $subject, 'content_filter' ] ) );
		self::assertSame(
			PHP_INT_MAX,
			has_filter( 'widget_block_content', [ $subject, 'widget_block_content_filter' ] )
		);
		self::assertSame( 10, has_action( 'hcap_auto_verify_register', [ $subject, 'content_filter' ] ) );
	}

	/**
	 * Test content_filter().
	 */
	public function test_content_filter(): void {
		$request_uri = $this->get_test_request_uri();
		$content     = $this->get_test_content();

		$_SERVER['REQUEST_URI'] = $request_uri;

		$expected = $this->get_test_registered_forms();

		$subject = new AutoVerify();

		$subject->init();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		apply_filters( 'the_content', $content );
		self::assertSame( $expected, get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test widget_block_content_filter().
	 */
	public function test_widget_block_content_filter(): void {
		$wp_widget_block = Mockery::mock( 'WP_Widget_Block' );

		$request_uri = $this->get_test_request_uri();
		$content     = $this->get_test_content();

		$_SERVER['REQUEST_URI'] = $request_uri;

		$expected = $this->get_test_registered_forms();

		$subject = new AutoVerify();

		$subject->init();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		apply_filters( 'widget_block_content', $content, [], $wp_widget_block );
		self::assertSame( $expected, get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test content_filter() with an action containing host.
	 */
	public function test_content_filter_with_action(): void {
		$request_uri = $this->get_test_request_uri();
		$content     = $this->get_test_content();
		$content     = str_replace(
			'<form method="post">',
			'<form action="http://test.test' . $request_uri . '" method="post">',
			$content
		);

		$_SERVER['REQUEST_URI'] = 'some-uri';

		$expected = $this->get_test_registered_forms();

		$subject = new AutoVerify();

		$subject->init();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		apply_filters( 'the_content', $content );
		self::assertSame( $expected, get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test content_filter() when form action cannot be determined.
	 */
	public function test_content_filter_without_form_action(): void {
		$content = $this->get_test_content();

		$_SERVER['REQUEST_URI'] = '';

		$subject = new AutoVerify();

		$subject->init();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		apply_filters( 'the_content', $content );
		self::assertSame( [], get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test content_filter() in admin.
	 */
	public function test_content_filter_in_admin(): void {
		set_current_screen( 'some-screen' );

		$content = $this->get_test_content();

		$subject = new AutoVerify();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		self::assertSame( $content, $subject->content_filter( $content ) );
		self::assertFalse( get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test content_filter() in ajax.
	 */
	public function test_content_filter_in_ajax(): void {
		$content = $this->get_test_content();

		$subject = new AutoVerify();

		add_filter(
			'wp_doing_ajax',
			static function () {
				return true;
			}
		);

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		self::assertSame( $content, $subject->content_filter( $content ) );
		self::assertFalse( get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * Test verify_form() when not POST request.
	 */
	public function test_verify_form_when_not_post(): void {
		$subject = new AutoVerify();
		$subject->verify();

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$subject->verify();
	}

	/**
	 * Test verify_form() when no $_SERVER['REQUEST_URI'] defined.
	 */
	public function test_verify_form_when_no_request_uri(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		unset( $_SERVER['REQUEST_URI'] );

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify_form() when no forms are registered.
	 */
	public function test_verify_form_when_no_forms_are_registered(): void {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify_form() when forms on another uri are registered.
	 */
	public function test_verify_form_when_forms_on_another_uri_are_registered(): void {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$registered_forms             = $this->get_test_registered_forms();
		$registered_forms['some_uri'] = $registered_forms[ untrailingslashit( wp_parse_url( $request_uri, PHP_URL_PATH ) ) ];
		unset( $registered_forms[ untrailingslashit( wp_parse_url( $request_uri, PHP_URL_PATH ) ) ] );

		set_transient( AutoVerify::TRANSIENT, $registered_forms );

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify_form() when other forms on the same uri are registered.
	 */
	public function test_verify_form_when_other_forms_on_the_same_uri_are_registered(): void {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$registered_forms = $this->get_test_registered_forms();

		$registered_forms[ untrailingslashit( $request_uri ) ][0] = [ 'some_input' ];

		set_transient( AutoVerify::TRANSIENT, $registered_forms );

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify_form() when verify is not successful.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_form_when_no_success(): void {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$_POST['test_input']   = 'some input';
		$_POST['hcap_hp_test'] = '';
		$_POST['hcap_hp_sig']  = wp_create_nonce( 'hcap_hp_test' );

		$die_arr  = [];
		$expected = [
			'Please complete the hCaptcha.',
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 403,
			],
		];

		set_transient( AutoVerify::TRANSIENT, $this->get_test_registered_forms() );

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject = new AutoVerify();
		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify_form() when verify is successful.
	 */
	public function test_verify_form_when_success(): void {
		$request_uri       = $this->get_test_request_uri();
		$hcaptcha_response = 'some response';
		$expected          = [
			'test_input'         => 'some input',
			'hcap_hp_test'       => '',
			'hcap_hp_sig'        => wp_create_nonce( 'hcap_hp_test' ),
			'hcaptcha_nonce'     => $this->get_test_nonce(),
			'h-captcha-response' => $hcaptcha_response,
			'hcap_fst_token'     => 'test_token',
		];

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$_POST['test_input']   = 'some input';
		$_POST['hcap_hp_test'] = '';
		$_POST['hcap_hp_sig']  = wp_create_nonce( 'hcap_hp_test' );

		set_transient( AutoVerify::TRANSIENT, $this->get_test_registered_forms() );

		$this->prepare_verify_request( $hcaptcha_response );

		$subject = new AutoVerify();
		$subject->verify();

		$_POST[ HCAPTCHA_NONCE ] = $this->get_test_nonce();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( $expected, $_POST );
	}

	/**
	 * Test verify_form() in admin.
	 */
	public function test_verify_form_in_admin(): void {
		set_current_screen( 'some-screen' );

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify_form() in ajax.
	 */
	public function test_verify_form_in_ajax(): void {
		add_filter(
			'wp_doing_ajax',
			static function () {
				return true;
			}
		);

		$subject = new AutoVerify();
		$subject->verify();
	}

	/**
	 * Test verify_form() in the REST, case 3 and 4.
	 */
	public function test_verify_form_in_rest_case_3_and_4(): void {
		$old_wp_rewrite = $GLOBALS['wp_rewrite'];

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_rewrite'] = null;

		$_SERVER['REQUEST_URI'] = rest_url();

		$subject = new AutoVerify();
		$subject->verify();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_rewrite'] = $old_wp_rewrite;
	}

	/**
	 * Get test request URI.
	 *
	 * @return string
	 */
	private function get_test_request_uri(): string {
		return '/hcaptcha-arbitrary-form/?some_argument=22';
	}

	/**
	 * Get test nonce.
	 *
	 * @return string
	 */
	private function get_test_nonce(): string {
		return '5e9f1e63ed';
	}

	/**
	 * Get test content.
	 *
	 * @return string
	 */
	private function get_test_content(): string {
		return '
<form method="post">
	<input type="text" name="test_input" id="test_input">
	<input type="submit" value="Send">
	[hcaptcha auto="true"]
</form>

<form role="search" method="get" action="http://test.test/"
	  class="wp-block-search__button-outside wp-block-search__text-button wp-block-search">
	<label for="wp-block-search__input-1" class="wp-block-search__label">Search</label>
	<div class="wp-block-search__inside-wrapper">
		<input type="search" id="wp-block-search__input-1"
			   class="wp-block-search__input" name="s" value="" placeholder=""
			   required/>
		<button type="submit" class="wp-block-search__button ">Search</button>
	</div>
</form>
';
	}

	/**
	 * Get registered forms.
	 *
	 * @return string[][][]
	 */
	private function get_test_registered_forms(): array {
		$request_uri = $this->get_test_request_uri();
		$request_uri = wp_parse_url( $request_uri, PHP_URL_PATH );
		$args        = [
			'auto'    => true,
			'action'  => 'hcaptcha_action',
			'name'    => 'hcaptcha_nonce',
			'sign'    => '',
			'ajax'    => false,
			'force'   => false,
			'theme'   => '',
			'size'    => '',
			'id'      => [
				'source'  => [],
				'form_id' => 0,
			],
			'protect' => true,
		];

		return [
			untrailingslashit( $request_uri ) =>
				[
					[
						'inputs' => [
							'test_input',
							'hcap_hp_test',
						],
						'args'   => $args,
					],
				],
		];
	}
}
