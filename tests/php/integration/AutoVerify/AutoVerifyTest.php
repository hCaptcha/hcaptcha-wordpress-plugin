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
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;

/**
 * Test AutoVerify class.
 *
 * @group auto-verify
 */
class AutoVerifyTest extends HCaptchaWPTestCase {

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset(
			$_SERVER['REQUEST_METHOD'],
			$_GET['pagename'],
			$_GET['page_id'],
			$_GET['rest_route'],
			$GLOBALS['current_screen']
		);
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
		self::assertSame( 10, has_filter( 'hcap_form_args', [ $subject, 'add_default_id' ] ) );
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
	 * Test content_filter() limits the transient size using LRU eviction.
	 *
	 * @return void
	 */
	public function test_content_filter_limits_transient_size_with_lru_eviction(): void {
		$content = $this->get_test_content();
		$subject = new AutoVerify();

		$subject->init();

		$_SERVER['REQUEST_URI'] = '/path-one';

		apply_filters( 'the_content', $content );

		$registered_forms = get_transient( AutoVerify::TRANSIENT );
		$action_forms     = $registered_forms['/path-one'];
		$two_paths        = [
			'/path-one' => $action_forms,
			'/path-two' => $action_forms,
		];
		$max_size         = strlen( maybe_serialize( $two_paths ) );
		$size_filter      = static function () use ( $max_size ): int {
			return $max_size;
		};

		add_filter( 'hcap_auto_verify_transient_max_size', $size_filter );

		try {
			$_SERVER['REQUEST_URI'] = '/path-two';

			apply_filters( 'the_content', $content );

			$_SERVER['REQUEST_URI'] = '/path-one';

			apply_filters( 'the_content', $content );

			$_SERVER['REQUEST_URI'] = '/path-new';

			apply_filters( 'the_content', $content );

			$expected = [
				'/path-one' => $action_forms,
				'/path-new' => $action_forms,
			];
			$actual   = get_transient( AutoVerify::TRANSIENT );

			self::assertSame( $expected, $actual );
			self::assertLessThanOrEqual( $max_size, strlen( maybe_serialize( $actual ) ) );
		} finally {
			remove_filter( 'hcap_auto_verify_transient_max_size', $size_filter );
		}
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
	 * Test verify_form() when the widget id is missing.
	 */
	public function test_verify_form_when_widget_id_is_missing(): void {
		$this->assert_missing_widget_id_is_rejected(
			$this->get_test_request_uri(),
			$this->get_test_registered_forms()
		);
	}

	/**
	 * Test verify_form() with duplicate leading slashes.
	 */
	public function test_verify_form_with_duplicate_leading_slashes(): void {
		$this->assert_missing_widget_id_is_rejected(
			'//' . ltrim( $this->get_test_request_uri(), '/' ),
			$this->get_test_registered_forms()
		);
	}

	/**
	 * Test verify_form() with a query-variable route.
	 *
	 * @param string $query_var Query variable.
	 *
	 * @dataProvider dp_test_verify_form_with_query_var_route
	 */
	public function test_verify_form_with_query_var_route( string $query_var ): void {
		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'hcaptcha-arbitrary-form',
			]
		);

		$query_value = 'page_id' === $query_var ? (string) $page_id : 'hcaptcha-arbitrary-form';

		$permalink      = get_permalink( $page_id );
		$canonical_path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$canonical_path = '/' === $canonical_path ? $canonical_path : untrailingslashit( $canonical_path );

		$registered_forms = $this->get_test_registered_forms();
		$action_forms     = $registered_forms[ array_key_first( $registered_forms ) ];

		$_GET[ $query_var ] = $query_value;

		$this->assert_missing_widget_id_is_rejected(
			'/index.php?' . $query_var . '=' . $query_value,
			[ $canonical_path => $action_forms ]
		);
	}

	/**
	 * Data provider for test_verify_form_with_query_var_route().
	 *
	 * @return array
	 */
	public function dp_test_verify_form_with_query_var_route(): array {
		return [
			'page ID'   => [ 'page_id' ],
			'page name' => [ 'pagename' ],
		];
	}

	/**
	 * Test verify_form() rejects a request with an omitted registered input.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_form_when_registered_input_is_omitted(): void {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;
		$_GET['rest_route']        = '/x';

		$_POST['hcap_hp_test'] = '';
		$_POST['hcap_hp_sig']  = wp_create_nonce( 'hcap_hp_test' );
		$this->prepare_widget_id();

		$die_arr    = [];
		$error_info = null;
		$expected   = [
			'Bad hCaptcha signature!',
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
		add_filter(
			'hcap_verify_request',
			static function ( $result, $deprecated, $info ) use ( &$error_info ) {
				$error_info = $info;

				return $result;
			},
			PHP_INT_MAX,
			3
		);

		$subject = new AutoVerify();
		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [], $_POST );

		self::assertSame( $expected, $die_arr );
		self::assertSame( [ 'bad-signature' ], $error_info->codes );
		self::assertSame( [], $error_info->expected_id );
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
			'hcaptcha-widget-id' => $this->get_test_widget_id(),
			'hcaptcha_nonce'     => $this->get_test_nonce(),
			'h-captcha-response' => $hcaptcha_response,
			'hcap_fst_token'     => 'test_token',
		];

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$_POST['test_input']   = 'some input';
		$_POST['hcap_hp_test'] = '';
		$_POST['hcap_hp_sig']  = wp_create_nonce( 'hcap_hp_test' );
		$this->prepare_widget_id();

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
	 * Test get_entry().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_get_entry(): void {
		$subject = new AutoVerify();
		$method  = $this->set_method_accessibility( $subject, 'get_entry' );

		$_POST = [
			'hcap_fst_token'     => 'token',
			'test_input'         => 'some input',
			'hcap_hp_test'       => '',
			'hcaptcha-widget-id' => 'widget-id',
			'h-captcha-response' => 'response',
			'hcaptcha_nonce'     => 'nonce',
			'_wp_http_referer'   => '/some-path/',
			'hcap_hp_sig'        => 'sig',
		];

		$expected_id = [
			'source'  => [ AutoVerify::class ],
			'form_id' => 0,
		];
		$actual      = $method->invoke( $subject, 'hcaptcha_nonce', 'hcaptcha_action', $expected_id );

		self::assertSame(
			[
				'nonce_name'         => 'hcaptcha_nonce',
				'nonce_action'       => 'hcaptcha_action',
				'h-captcha-response' => 'response',
				'data'               => [
					'test_input' => 'some input',
				],
				'expected_id'        => $expected_id,
			],
			$actual
		);
	}

	/**
	 * Assert that a missing widget ID is rejected.
	 *
	 * @param string $request_uri     Request URI.
	 * @param array  $registered_forms Registered forms.
	 */
	private function assert_missing_widget_id_is_rejected( string $request_uri, array $registered_forms ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;

		$_POST['test_input'] = 'some input';

		set_transient( AutoVerify::TRANSIENT, $registered_forms );

		$die_arr  = [];
		$expected = [
			'Bad hCaptcha signature!',
			'hCaptcha',
			[
				'back_link' => true,
				'response'  => 403,
			],
		];

		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
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
	 * Prepare widget id.
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = $this->get_test_widget_id();
	}

	/**
	 * Get test widget id.
	 *
	 * @return string
	 */
	private function get_test_widget_id(): string {
		return HCaptcha::widget_id_value(
			[
				'source'  => [ AutoVerify::class ],
				'form_id' => 0,
			]
		);
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
			'theme'   => 'light',
			'size'    => 'normal',
			'id'      => [
				'source'  => [ AutoVerify::class ],
				'form_id' => 0,
			],
			'protect' => true,
		];

		return [
			untrailingslashit( $request_uri ) =>
				[
					[
						'inputs'    => [
							'test_input',
						],
						'args'      => $args,
						'widget_id' => $this->get_test_widget_id(),
					],
				],
		];
	}
}
