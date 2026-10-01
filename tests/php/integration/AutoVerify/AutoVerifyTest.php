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
			$_GET['p'],
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
		$path = array_key_first( $expected );
		self::assertSame( $expected[ $path ], $this->get_registered_action_forms( $path ) );
		self::assertFalse( get_transient( $subject::TRANSIENT ) );
	}

	/**
	 * An actionless form on a query route must not claim unrelated home requests.
	 *
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_query_route_form_does_not_claim_home(): void {
		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/?page_id=' . $page_id . '&preview=true';
		$_GET['page_id']        = (string) $page_id;
		apply_filters( 'the_content', $this->get_test_content() );

		$target_key  = 'post:' . $page_id . ':/';
		$option_name = 'hcaptcha_auto_verify_form_' . hash( 'sha256', $target_key );

		self::assertCount( 1, get_option( $option_name ) );
		self::assertFalse( get_option( 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/' ), false ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/';
		$_POST['foo']              = 'bar';
		unset( $_GET['page_id'] );
		$subject->verify();

		self::assertSame( [ 'foo' => 'bar' ], $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$_SERVER['REQUEST_URI'] = '/?page_id=' . $page_id . '&utm_source=test';
		$_GET['page_id']        = (string) $page_id;
		$_POST                  = [ 'test_input' => 'value' ];
		$die_arr                = [];

		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify();

		self::assertSame( 'Bad hCaptcha signature!', $die_arr[0] ?? null );
		self::assertSame( 403, $die_arr[2]['response'] ?? null );
	}

	/**
	 * An explicit root action on a query page still targets the home route.
	 */
	public function test_explicit_home_action_on_query_page(): void {
		$page_id = $this->factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
			]
		);
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/?page_id=' . $page_id;
		$_GET['page_id']        = (string) $page_id;
		$content                = str_replace( '<form method="post">', '<form method="post" action="/">', $this->get_test_content() );
		apply_filters( 'the_content', $content );

		self::assertCount( 1, $this->get_registered_action_forms( '/' ) );
		self::assertSame( [], $this->get_registered_action_forms( 'post:' . $page_id . ':/' ) );
	}

	/**
	 * A real home form still protects posts to its path with query parameters.
	 *
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_home_registration_protects_query_route(): void {
		$page_id = $this->factory()->post->create();
		$forms   = $this->get_test_registered_forms();
		$form    = $forms[ array_key_first( $forms ) ][0];

		update_option( 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/' ), [ $form ], false );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/?p=' . $page_id;
		$_GET['p']                 = (string) $page_id;
		$_POST                     = [ 'foo' => 'bar' ];

		$subject = new AutoVerify();

		self::assertSame( [], $this->set_method_accessibility( $subject, 'get_registered_form_for_request' )->invoke( $subject ) );
	}

	/**
	 * Adding a page selector to another endpoint cannot bypass its protection.
	 */
	public function test_page_selector_does_not_change_other_action(): void {
		$subject = new AutoVerify();
		$method  = $this->set_method_accessibility( $subject, 'get_target_key' );

		self::assertSame( '/form', $method->invoke( $subject, '/form/?p=123' ) );
		self::assertSame( '/form', $method->invoke( $subject, '/form/?page_id=123' ) );
		self::assertSame( 'post:123:/', $method->invoke( $subject, '/?p=123' ) );
	}

	/**
	 * A legacy Brevo registration must only apply to Brevo submissions.
	 *
	 * @noinspection PhpArrayWriteIsNotUsedInspection
	 */
	public function test_legacy_brevo_home_registration_ignores_other_posts(): void {
		$registered_form              = $this->get_test_registered_forms();
		$form                         = $registered_form[ array_key_first( $registered_form ) ][0];
		$form['args']['id']['source'] = [ 'mailin/sendinblue.php' ];
		$option_name                  = 'hcaptcha_auto_verify_form_' . hash( 'sha256', '/' );
		$subject                      = new AutoVerify();

		update_option( $option_name, [ $form ], false );
		$_POST = [ 'edd_action' => 'activate_license' ];

		self::assertNull( $this->set_method_accessibility( $subject, 'get_registered_form' )->invoke( $subject, '/' ) );

		$_POST['sib_form_action'] = 'subscribe_form_submit';

		self::assertSame( [], $this->set_method_accessibility( $subject, 'get_registered_form' )->invoke( $subject, '/' ) );
	}

	/**
	 * Rendering several forms persists them without creating a transient.
	 */
	public function test_content_filter_does_not_write_transient(): void {
		$content = $this->get_test_content();
		$subject = new AutoVerify();

		$subject->init();

		$_SERVER['REQUEST_URI'] = '/path-one';
		apply_filters( 'the_content', $content );
		$_SERVER['REQUEST_URI'] = '/path-two';
		apply_filters( 'the_content', $content );

		self::assertCount( 1, $this->get_registered_action_forms( '/path-one' ) );
		self::assertCount( 1, $this->get_registered_action_forms( '/path-two' ) );
		self::assertFalse( get_transient( AutoVerify::TRANSIENT ) );
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
		$path = array_key_first( $expected );
		self::assertSame( $expected[ $path ], $this->get_registered_action_forms( $path ) );
		self::assertFalse( get_transient( $subject::TRANSIENT ) );
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
		$expected[ array_key_first( $expected ) ][0]['source'] = 'some-uri';

		$subject = new AutoVerify();

		$subject->init();

		self::assertFalse( get_transient( $subject::TRANSIENT ) );
		apply_filters( 'the_content', $content );
		$path = array_key_first( $expected );
		self::assertSame( $expected[ $path ], $this->get_registered_action_forms( $path ) );
		self::assertFalse( get_transient( $subject::TRANSIENT ) );
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
		self::assertFalse( get_transient( $subject::TRANSIENT ) );
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
	 * A rendered form remains protected without a transient entry.
	 */
	public function test_verify_form_without_transient_entry(): void {
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/path-one';
		apply_filters( 'the_content', $this->get_test_content() );

		self::assertFalse( get_transient( AutoVerify::TRANSIENT ) );
		self::assertCount( 1, $this->get_registered_action_forms( '/path-one' ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/path-one';
		$_POST['test_input']       = 'some input';

		$die_arr = [];
		add_filter(
			'wp_die_handler',
			static function () use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify();

		self::assertSame( 403, $die_arr[2]['response'] ?? null );
		self::assertSame( [], $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Rendering a matching form on another page cannot remove its registration.
	 */
	public function test_non_owner_render_cannot_remove_registration(): void {
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/victim';
		apply_filters( 'the_content', $this->get_test_content() );

		$registered_forms = $this->get_registered_action_forms( '/victim' );
		$widget_id        = $registered_forms[0]['widget_id'];
		$attacker_form    = '<form method="post" action="/victim" class="h-captcha">' .
			'<input type="hidden" name="hcaptcha-widget-id" value="' . esc_attr( $widget_id ) . '">' .
			'<input type="text" name="test_input"></form>';

		$_SERVER['REQUEST_URI'] = '/attacker';

		$attacker = new AutoVerify();
		$attacker->content_filter( $attacker_form );
		self::assertCount( 1, $this->get_registered_action_forms( '/victim' ) );
		$attacker->content_filter( str_replace( '<input type="hidden" name="hcaptcha-widget-id" value="' . esc_attr( $widget_id ) . '">', '', $attacker_form ) );

		self::assertSame( $registered_forms, $this->get_registered_action_forms( '/victim' ) );
	}

	/**
	 * Distinct pages using plain permalinks cannot remove each other's forms.
	 */
	public function test_non_owner_plain_permalink_cannot_remove_registration(): void {
		$victim_id   = $this->factory()->post->create( [ 'post_type' => 'page' ] );
		$attacker_id = $this->factory()->post->create( [ 'post_type' => 'page' ] );
		$subject     = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/?page_id=' . $victim_id;
		apply_filters( 'the_content', $this->get_test_content() );

		$registered_forms = $this->get_registered_action_forms( 'post:' . $victim_id . ':/' );
		$attacker_form    = '<form method="post" action="/?page_id=' . $victim_id . '" class="h-captcha">' .
			'<input type="text" name="test_input"></form>';

		$_SERVER['REQUEST_URI'] = '/?page_id=' . $attacker_id;
		( new AutoVerify() )->content_filter( $attacker_form );

		self::assertSame( $registered_forms, $this->get_registered_action_forms( 'post:' . $victim_id . ':/' ) );
	}

	/**
	 * An auto form on another page cannot replace the original registration.
	 */
	public function test_non_owner_auto_form_cannot_replace_registration(): void {
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/victim';
		apply_filters( 'the_content', $this->get_test_content() );

		$registered_forms = $this->get_registered_action_forms( '/victim' );
		$victim_form      = $registered_forms[0];
		$attacker_form    = '<form method="post" action="/victim" class="h-captcha">' .
			'<input type="hidden" name="hcaptcha-widget-id" value="' . esc_attr( $victim_form['widget_id'] ) . '">' .
			'<input type="text" name="test_input"></form>';

		$_SERVER['REQUEST_URI'] = '/attacker';

		$attacker = new AutoVerify();
		$attacker->register_hcaptcha( $victim_form['args'] );
		$attacker->content_filter( $attacker_form );
		delete_transient( AutoVerify::TRANSIENT );

		$_POST['test_input']         = 'some input';
		$_POST['hcaptcha-widget-id'] = $victim_form['widget_id'];
		$actual                      = $this->set_method_accessibility( $subject, 'get_registered_form' )->invoke( $subject, '/victim' );

		self::assertSame( $victim_form, $actual );
	}

	/**
	 * The source page can stop auto-verifying a form it registered.
	 */
	public function test_owner_render_can_remove_registration(): void {
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/victim';
		apply_filters( 'the_content', $this->get_test_content() );

		$registered_forms = $this->get_registered_action_forms( '/victim' );
		$widget_id        = $registered_forms[0]['widget_id'];
		$regular_form     = '<form method="post" action="/victim" class="h-captcha">' .
			'<input type="hidden" name="hcaptcha-widget-id" value="' . esc_attr( $widget_id ) . '">' .
			'<input type="text" name="test_input"></form>';

		( new AutoVerify() )->content_filter( $regular_form );

		self::assertSame( [], $this->get_registered_action_forms( '/victim' ) );
		self::assertNull( $this->set_method_accessibility( $subject, 'get_registered_form' )->invoke( $subject, '/victim' ) );
	}

	/**
	 * A pre-upgrade transient entry is replaced, then removable by its source.
	 */
	public function test_legacy_registration_is_replaced_on_render(): void {
		$request_uri      = $this->get_test_request_uri();
		$registered_forms = $this->get_test_registered_forms();
		$path             = array_key_first( $registered_forms );
		$widget_id        = $registered_forms[ $path ][0]['widget_id'];

		unset( $registered_forms[ $path ][0]['source'] );
		set_transient( AutoVerify::TRANSIENT, $registered_forms );

		$_SERVER['REQUEST_URI'] = $request_uri;

		$subject = new AutoVerify();
		$subject->init();
		apply_filters( 'the_content', $this->get_test_content() );

		$updated = $this->get_registered_action_forms( $path );
		self::assertCount( 1, $updated );
		self::assertSame( $request_uri, $updated[0]['source'] );
		self::assertFalse( get_transient( AutoVerify::TRANSIENT ) );

		$regular_form = '<form method="post" class="h-captcha">' .
			'<input type="hidden" name="hcaptcha-widget-id" value="' . esc_attr( $widget_id ) . '">' .
			'<input type="text" name="test_input"></form>';
		( new AutoVerify() )->content_filter( $regular_form );
		delete_transient( AutoVerify::TRANSIENT );

		self::assertNull( $this->set_method_accessibility( $subject, 'get_registered_form' )->invoke( $subject, $path ) );
	}

	/**
	 * Migrating one legacy action preserves other transient registrations.
	 */
	public function test_legacy_registration_preserves_other_actions(): void {
		$registered_forms           = $this->get_test_registered_forms();
		$path                       = array_key_first( $registered_forms );
		$registered_forms['/other'] = $registered_forms[ $path ];
		set_transient( AutoVerify::TRANSIENT, $registered_forms );

		$_SERVER['REQUEST_URI'] = $this->get_test_request_uri();
		$subject                = new AutoVerify();
		$subject->init();
		apply_filters( 'the_content', $this->get_test_content() );

		self::assertCount( 1, $this->get_registered_action_forms( $path ) );
		self::assertSame( [ '/other' => $registered_forms['/other'] ], get_transient( AutoVerify::TRANSIENT ) );
	}

	/**
	 * A valid submission still succeeds after the transient entry disappears.
	 */
	public function test_verify_form_succeeds_without_transient_entry(): void {
		$subject = new AutoVerify();
		$subject->init();

		$_SERVER['REQUEST_URI'] = '/valid-form';
		apply_filters( 'the_content', $this->get_test_content() );
		delete_transient( AutoVerify::TRANSIENT );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$expected_post             = [
			'test_input'         => 'some input',
			'hcap_hp_test'       => '',
			'hcap_hp_sig'        => wp_create_nonce( 'hcap_hp_test' ),
			'hcaptcha-widget-id' => $this->get_test_widget_id(),
			'hcaptcha_nonce'     => $this->get_test_nonce(),
			'h-captcha-response' => 'some response',
			'hcap_fst_token'     => 'test_token',
		];

		$_POST = $expected_post;

		$this->prepare_verify_request( 'some response' );
		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( $expected_post, $_POST );
		self::assertTrue( hcaptcha()->has_result );
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
	 * Test verify_form() defers an unmatched request to a dedicated verifier.
	 *
	 * @return void
	 */
	public function test_verify_form_defers_unmatched_request(): void {
		$request_uri = $this->get_test_request_uri();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;
		$_POST['test_input']       = 'some input';

		set_transient( AutoVerify::TRANSIENT, $this->get_test_registered_forms() );
		add_filter( 'hcap_auto_verify_unmatched_form', '__return_null' );

		$subject = new AutoVerify();
		$subject->verify();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::assertSame( [ 'test_input' => 'some input' ], $_POST );
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
	 * Get persisted registrations for one action.
	 *
	 * @param string $action Form action.
	 *
	 * @return array
	 */
	private function get_registered_action_forms( string $action ): array {
		return (array) get_option( 'hcaptcha_auto_verify_form_' . hash( 'sha256', $action ), [] );
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
		$source      = $this->get_test_request_uri();
		$request_uri = wp_parse_url( $source, PHP_URL_PATH );
		$args        = [
			'auto'          => true,
			'action'        => 'hcaptcha_action',
			'name'          => 'hcaptcha_nonce',
			'sign'          => '',
			'ajax'          => false,
			'force'         => false,
			'theme'         => 'light',
			'size'          => 'normal',
			'widget_params' => [],
			'id'            => [
				'source'  => [ AutoVerify::class ],
				'form_id' => 0,
			],
			'protect'       => true,
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
						'source'    => $source,
					],
				],
		];
	}
}
