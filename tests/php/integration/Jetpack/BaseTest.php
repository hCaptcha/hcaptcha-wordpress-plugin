<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Jetpack;

use Automattic\Jetpack\Forms\ContactForm\Contact_Form as JetpackForm;
use Automattic\Jetpack\Forms\ContactForm\Contact_Form_Plugin as JetpackFormPlugin;
use Automattic\Jetpack\Forms\ContactForm\Feedback;
use HCaptcha\AntiSpam\DisposableEmail;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Jetpack\Form;
use Jetpack;
use ReflectionClass;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Class BaseTest.
 *
 * @group jetpack
 */
class BaseTest extends JetpackTestCase {

	/**
	 * Test that the live Jetpack Forms module is loaded.
	 *
	 * @return void
	 */
	public function test_live_plugin_is_loaded(): void {
		$plugin_dir = realpath( WP_PLUGIN_DIR . '/jetpack' );
		$form_file  = wp_normalize_path( ( new ReflectionClass( JetpackForm::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( 'jetpack/jetpack.php' ) );
		self::assertNotFalse( $plugin_dir );
		self::assertStringStartsWith( trailingslashit( wp_normalize_path( $plugin_dir ) ), $form_file );
		self::assertTrue( version_compare( constant( 'JETPACK__VERSION' ), '16.0.1', '>=' ) );
		self::assertTrue( Jetpack::is_module_active( 'contact-form' ) );
		$shortcode_callback = $GLOBALS['shortcode_tags']['contact-form'];

		self::assertSame( JetpackForm::class, ltrim( $shortcode_callback[0], '\\' ) );
		self::assertSame( 'parse', $shortcode_callback[1] );
		self::assertSame(
			10,
			has_filter(
				'jetpack_contact_form_is_spam',
				[ JetpackFormPlugin::init(), 'is_spam_blocklist' ]
			)
		);
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset(
			$_POST['contact-form-hash'],
			$_POST['contact-form-id'],
			$_POST['jetpack_contact_form_jwt'],
			$_POST['hcaptcha_jetpack_nonce'],
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ],
			$_POST['g13-name'],
			$_POST['g13-email'],
			$_POST['g13'],
			$GLOBALS['pagenow'],
			$_GET['post'],
			$_GET['action']
		);

		parent::tearDown();
	}

	/**
	 * Test constructor and init_hooks.
	 *
	 * @param bool $is_editing_jetpack_form_post Is editing a post with a Jetpack form.
	 *
	 * @dataProvider dp_test_init_hooks
	 */
	public function test_init_hooks( bool $is_editing_jetpack_form_post ): void {
		if ( $is_editing_jetpack_form_post ) {
			$post_id = wp_insert_post( [ 'post_content' => '<!-- wp:jetpack/contact-form' ] );

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['pagenow'] = 'post.php';
			$_GET['post']       = $post_id;
			$_GET['action']     = 'edit';
		}

		$subject = new Form();

		self::assertSame( 10, has_filter( 'jetpack_contact_form_html', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 0, has_filter( 'widget_text', [ $subject, 'add_hcaptcha' ] ) );

		self::assertSame( 10, has_filter( 'widget_text', 'shortcode_unautop' ) );
		self::assertSame( 10, has_filter( 'widget_text', 'do_shortcode' ) );

		self::assertSame( 100, has_filter( 'jetpack_contact_form_is_spam', [ $subject, 'verify' ] ) );
		self::assertSame( PHP_INT_MAX, has_filter( 'jetpack_forms_extra_webhooks', [ $subject, 'capture_form_hash' ] ) );

		self::assertSame( 10, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );

		self::assertSame( 10, has_action( 'the_content', [ $subject, 'the_content_filter' ] ) );

		if ( $is_editing_jetpack_form_post ) {
			self::assertSame( 0, has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
			self::assertSame( 9, has_action( 'wp_print_footer_scripts', [ $subject, 'editor_enqueue_scripts' ] ) );
		} else {
			self::assertFalse( has_filter( 'hcap_print_hcaptcha_scripts', [ $subject, 'print_hcaptcha_scripts' ] ) );
			self::assertFalse( has_action( 'wp_print_footer_scripts', [ $subject, 'editor_enqueue_scripts' ] ) );
		}
	}

	/**
	 * Data provider for test_init_hooks.
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			[ false ],
			[ true ],
		];
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->prepare_verify_post( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );
		$this->prepare_widget_id();

		$_POST['contact-form-id'] = '13';
		$_POST['g13-name']        = 'Some name';
		$_POST['g13-email']       = 'foo@bar.com';
		$_POST['g13']             = 'Some message';

		$subject = new Form();

		self::assertFalse( $subject->verify() );
		self::assertTrue( $subject->verify( true ) );
	}

	/**
	 * Test hCaptcha verification in the live Jetpack spam pipeline.
	 *
	 * @return void
	 */
	public function test_live_jetpack_spam_pipeline(): void {
		$this->prepare_verify_post( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );
		$this->prepare_widget_id();

		$_POST['contact-form-id'] = '13';
		$_POST['g13-name']        = 'Some name';
		$_POST['g13-email']       = 'foo@bar.com';
		$_POST['g13']             = 'Some message';

		$subject = new Form();
		$form    = [
			'comment_author'       => 'Some name',
			'comment_author_email' => 'foo@bar.com',
			'comment_author_url'   => '',
			'comment_content'      => 'Some message',
			'user_ip'              => '127.0.0.1',
			'user_agent'           => 'Jetpack integration test',
		];

		self::assertSame( 100, has_filter( 'jetpack_contact_form_is_spam', [ $subject, 'verify' ] ) );
		self::assertFalse( apply_filters( 'jetpack_contact_form_is_spam', false, $form ) );
	}

	// These tests pass synthetic POST data into native Jetpack readers without saving a submission.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	/**
	 * Disposable-email checks must use Jetpack's email field, not a later POST alias.
	 *
	 * @param bool   $jwt   Whether Jetpack selected the form from a JWT.
	 * @param string $label Native email field label.
	 *
	 * @dataProvider dp_test_native_email_policy
	 */
	public function test_native_email_policy_ignores_post_alias( bool $jwt, string $label ): void {
		$original_post = $_POST;
		$subject       = new Form();
		do_shortcode( '[contact-form id="13"][contact-field label="' . $label . '" type="email" required="1"/][/contact-form]' );
		$rendered = JetpackForm::$last;
		$form     = $jwt ? JetpackForm::get_instance_from_jwt( $rendered->get_jwt(), true ) : $rendered;
		$email_id = $form->get_field_ids()['email'];

		try {
			$_POST = [
				'contact-form-id'   => '13',
				'contact-form-hash' => $form->hash,
				$email_id           => 'blocked@sec065-disposable.test',
				'g13g13-email'      => 'allowed@example.org',
			];

			if ( $jwt ) {
				$_POST['jetpack_contact_form_jwt'] = $rendered->get_jwt();
				$subject->capture_form_hash( [], $form );
			}

			$form->validate();
			self::assertFalse( $form->has_errors() );

			$spam_data = Feedback::from_submission( $_POST, $form )->get_akismet_vars();
			$entry     = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $spam_data );

			self::assertSame( 'blocked@sec065-disposable.test', $spam_data['comment_author_email'] );
			self::assertSame( 'blocked@sec065-disposable.test', $entry['email'] );
			self::assertSame( 'blocked@sec065-disposable.test', $entry['data']['email'] );
			self::assertSame( [ 'blocked@sec065-disposable.test' ], EntryData::get_emails( $entry ) );

			set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec065-disposable.test' => true ] );
			self::assertFalse( (bool) ( new DisposableEmail() )->verify( $entry ) );

			$_POST[ $email_id ]    = 'allowed@example.org';
			$_POST['g13g13-email'] = 'blocked@sec065-disposable.test';
			$spam_data             = Feedback::from_submission( $_POST, $form )->get_akismet_vars();
			$entry                 = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $spam_data );

			self::assertSame( [ 'allowed@example.org' ], EntryData::get_emails( $entry ) );
			self::assertTrue( (bool) ( new DisposableEmail() )->verify( $entry ) );
		} finally {
			delete_transient( 'hcaptcha_disposable_email_blocklist' );
			$_POST = $original_post;
		}
	}

	/**
	 * Email policy routes and labels.
	 *
	 * @return array
	 */
	public function dp_test_native_email_policy(): array {
		return [
			'jwt_default'    => [ true, 'Email' ],
			'jwt_custom'     => [ true, 'Contact Address' ],
			'legacy_default' => [ false, 'Email' ],
			'legacy_custom'  => [ false, 'Contact Address' ],
		];
	}

	/**
	 * Every visible native email field is checked, even when the first is permitted.
	 */
	public function test_native_email_policy_checks_additional_email_fields(): void {
		$original_post = $_POST;
		$subject       = new Form();
		do_shortcode( '[contact-form id="13"][contact-field label="Primary" type="email" required="1"/][contact-field label="Secondary" type="email" required="1"/][/contact-form]' );
		$form = JetpackForm::$last;
		$ids  = $form->get_field_ids()['all'];

		try {
			$_POST = [
				'contact-form-id'   => '13',
				'contact-form-hash' => $form->hash,
				$ids[0]             => 'allowed@example.org',
				$ids[1]             => 'foo&bar@sec065-disposable.test',
			];

			$form->validate();
			self::assertFalse( $form->has_errors() );

			$spam_data = Feedback::from_submission( $_POST, $form )->get_akismet_vars();
			$entry     = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $spam_data );

			self::assertSame( 'allowed@example.org', $spam_data['comment_author_email'] );
			self::assertSame( [ 'allowed@example.org', 'foo&bar@sec065-disposable.test' ], EntryData::get_emails( $entry ) );
			set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec065-disposable.test' => true ] );
			self::assertFalse( (bool) ( new DisposableEmail() )->verify( $entry ) );
		} finally {
			delete_transient( 'hcaptcha_disposable_email_blocklist' );
			$_POST = $original_post;
		}
	}

	/**
	 * Legacy Jetpack can select a rebuilt form whose hash differs from the submitted hash.
	 */
	public function test_native_email_policy_checks_rebuilt_legacy_form(): void {
		$original_post = $_POST;
		$subject       = new Form();
		do_shortcode( '[contact-form id="13"][contact-field label="Primary" type="email" required="1"/][contact-field label="Secondary" type="email" required="1"/][/contact-form]' );
		$form      = JetpackForm::$last;
		$ids       = $form->get_field_ids()['all'];
		$old_hash  = $form->hash;
		$fake_hash = str_repeat( 'a', 40 );

		try {
			self::assertNotSame( $old_hash, $fake_hash );
			self::assertArrayNotHasKey( $fake_hash, JetpackForm::$forms );
			$_POST = [
				'contact-form-id'   => '13',
				'contact-form-hash' => $fake_hash,
				$ids[0]             => 'allowed@example.org',
				$ids[1]             => 'blocked@sec065-disposable.test',
			];

			$spam_data = Feedback::from_submission( $_POST, $form )->get_akismet_vars();
			$entry     = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $spam_data );

			self::assertSame( 'allowed@example.org', $spam_data['comment_author_email'] );
			self::assertSame( [ 'allowed@example.org', 'blocked@sec065-disposable.test' ], EntryData::get_emails( $entry ) );
			set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec065-disposable.test' => true ] );
			self::assertFalse( (bool) ( new DisposableEmail() )->verify( $entry ) );
		} finally {
			delete_transient( 'hcaptcha_disposable_email_blocklist' );
			$_POST = $original_post;
		}
	}

	/**
	 * A filter may change the author email while Jetpack retains the submitted field.
	 */
	public function test_native_email_policy_checks_stored_email_after_author_filter(): void {
		$original_post = $_POST;
		$subject       = new Form();
		do_shortcode( '[contact-form id="13"][contact-field label="Email" type="email" required="1"/][/contact-form]' );
		$form     = JetpackForm::$last;
		$email_id = $form->get_field_ids()['email'];
		$filter   = static function (): string {
			return 'allowed@example.org';
		};

		try {
			$_POST = [
				'contact-form-id'   => '13',
				'contact-form-hash' => $form->hash,
				$email_id           => 'blocked@sec065-disposable.test',
			];

			$form->validate();
			self::assertFalse( $form->has_errors() );
			add_filter( 'pre_comment_author_email', $filter );

			$feedback  = Feedback::from_submission( $_POST, $form );
			$spam_data = $feedback->get_akismet_vars();
			$entry     = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $spam_data );

			self::assertSame( 'allowed@example.org', $spam_data['comment_author_email'] );
			self::assertSame( [ 'allowed@example.org', 'blocked@sec065-disposable.test' ], EntryData::get_emails( $entry ) );
			set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec065-disposable.test' => true ] );
			self::assertFalse( (bool) ( new DisposableEmail() )->verify( $entry ) );
		} finally {
			remove_filter( 'pre_comment_author_email', $filter );
			delete_transient( 'hcaptcha_disposable_email_blocklist' );
			$_POST = $original_post;
		}
	}

	/**
	 * A text field named email does not become a native email field.
	 */
	public function test_native_email_policy_preserves_email_free_form(): void {
		$original_post = $_POST;
		$subject       = new Form();
		do_shortcode( '[contact-form id="13"][contact-field label="Email" type="text"/][/contact-form]' );
		$form = JetpackForm::$last;
		$id   = $form->get_field_ids()['all'][0];

		try {
			$_POST = [
				'contact-form-id'   => '13',
				'contact-form-hash' => $form->hash,
				$id                 => 'blocked@sec065-disposable.test',
				'g13g13-email'      => 'blocked@sec065-disposable.test',
			];

			$spam_data = Feedback::from_submission( $_POST, $form )->get_akismet_vars();
			$entry     = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, $spam_data );

			self::assertSame( '', $spam_data['comment_author_email'] );
			self::assertSame( [], EntryData::get_emails( $entry ) );
			self::assertArrayNotHasKey( 'email', $entry );
			self::assertArrayNotHasKey( 'email', $entry['data'] );
		} finally {
			$_POST = $original_post;
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/**
	 * A JWT-selected protected form cannot inherit another form's CAPTCHA exemption.
	 */
	public function test_jwt_form_hash_owns_captcha_policy(): void {
		$subject = new Form();
		$html_a  = do_shortcode( '[contact-form id="13"][contact-field label="Name A" type="name" required="1"/][/contact-form]' );
		$form_a  = JetpackForm::$last;
		$jwt_a   = $form_a->get_jwt();
		$hash_a  = $form_a->hash;
		$html_b  = do_shortcode( '[contact-form id="13"][contact-field label="Name B" type="name" required="1"/][/contact-form]' );
		$form_b  = JetpackForm::$last;
		$jwt_b   = $form_b->get_jwt();
		$hash_b  = $form_b->hash;
		$source  = HCaptcha::get_class_source( Form::class );

		self::assertStringContainsString( "name='jetpack_contact_form_jwt'", $html_a );
		self::assertStringContainsString( "name='jetpack_contact_form_jwt'", $html_b );
		self::assertNotSame( $hash_a, $hash_b );

		$native_a = JetpackForm::get_instance_from_jwt( $jwt_a, true );
		$native_b = JetpackForm::get_instance_from_jwt( $jwt_b, true );
		$exempt_b = static function ( $protect, $form_source, $form_id ) use ( $source, $hash_b ) {
			return $source === $form_source && 'contact_' . $hash_b === $form_id ? false : $protect;
		};

		add_filter( 'hcap_protect_form', $exempt_b, 10, 3 );

		try {
			$_POST['contact-form-id']              = '13';
			$_POST['hcaptcha_jetpack_nonce']       = wp_create_nonce( 'hcaptcha_jetpack' );
			$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
				[
					'source'  => $source,
					'form_id' => 'contact_' . $hash_b,
				]
			);
			$_POST['jetpack_contact_form_jwt']     = $jwt_a;
			$_POST['contact-form-hash']            = $hash_b;

			self::assertSame( [], $subject->capture_form_hash( [], $native_a ) );
			self::assertSame( 'invalid_hcaptcha', $subject->verify()->get_error_code() );
			self::assertSame( $hash_a, $this->get_protected_property( $subject, 'error_form_hash' ) );

			$_POST['contact-form-hash'] = $hash_a;
			self::assertSame( 'invalid_hcaptcha', $subject->verify()->get_error_code() );

			$_POST['jetpack_contact_form_jwt'] = $jwt_b;
			$_POST['contact-form-hash']        = $hash_b;
			self::assertSame( [], $subject->capture_form_hash( [], $native_b ) );
			self::assertFalse( $subject->verify() );

			unset( $_POST['jetpack_contact_form_jwt'] );
			self::assertFalse( $subject->verify() );
		} finally {
			remove_filter( 'hcap_protect_form', $exempt_b );
		}
	}

	/**
	 * Test verify() not verified.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_verify_not_verified(): void {
		$hash  = 'some hash';
		$error = new WP_Error( 'invalid_hcaptcha', 'The hCaptcha is invalid.' );

		$this->prepare_verify_post( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack', false );
		$this->prepare_widget_id();

		$subject = new Form();

		self::assertEquals( $error, $subject->verify() );
		self::assertNull( $this->get_protected_property( $subject, 'error_form_hash' ) );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', [ $subject, 'error_message' ] ) );

		$_POST['contact-form-hash'] = $hash;
		$this->prepare_widget_id( $hash );

		self::assertEquals( $error, $subject->verify() );
		self::assertSame( $hash, $this->get_protected_property( $subject, 'error_form_hash' ) );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', [ $subject, 'error_message' ] ) );
	}

	/**
	 * Test verify() when widget id is bad.
	 */
	public function test_verify_bad_widget_id(): void {
		$error = new WP_Error( 'invalid_hcaptcha', 'Bad hCaptcha signature!' );
		$hash  = 'some hash';

		$this->prepare_verify_post( 'hcaptcha_jetpack_nonce', 'hcaptcha_jetpack' );
		$this->prepare_widget_id(
			$hash,
			[
				'source'  => [ 'WordPress' ],
				'form_id' => 'contact_' . $hash,
			]
		);

		$_POST['contact-form-hash'] = $hash;

		$subject = new Form();

		self::assertEquals( $error, $subject->verify() );
		self::assertSame( $hash, $this->get_protected_property( $subject, 'error_form_hash' ) );
	}

	/**
	 * Test error_message().
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_error_message(): void {
		$hcaptcha_content = 'some content';
		$error_message    = 'some error message';
		$error_form_hash  = 'some hash';
		$args             = [
			'id' => [
				'form_id' => $error_form_hash,
			],
		];

		$subject = new Form();

		// No error.
		self::assertSame( $hcaptcha_content, $subject->error_message( $hcaptcha_content ) );

		// Error without form_id.
		$this->set_protected_property( $subject, 'error_message', $error_message );
		$this->set_protected_property( $subject, 'error_form_hash', $error_form_hash );

		$expected = $hcaptcha_content . '
<div class="contact-form__input-error">
	<span class="contact-form__warning-icon">
		<span class="visually-hidden">Warning.</span>
		<i aria-hidden="true"></i>
	</span>
	<span>' . $error_message . '</span>
</div>
';

		self::assertSame( $expected, $subject->error_message( $hcaptcha_content ) );

		// Error with form_id.
		self::assertSame( $expected, $subject->error_message( $hcaptcha_content, $args ) );

		// No error with wrong form_id.
		$wrong_args = [
			'id' => [
				'form_id' => 'contact_wrong hash',
			],
		];
		self::assertSame( $hcaptcha_content, $subject->error_message( $hcaptcha_content, $wrong_args ) );
	}

	/**
	 * Test print_hcaptcha_scripts().
	 *
	 * @return void
	 */
	public function test_print_hcaptcha_scripts(): void {
		$subject = new Form();

		self::assertTrue( $subject->print_hcaptcha_scripts( false ) );
	}

	/**
	 * Test enqueue_scripts().
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$handle = 'hcaptcha-jetpack';

		$subject = new Form();

		self::assertFalse( wp_script_is( $handle ) );

		// Test when hCaptcha was not shown.
		$subject->enqueue_scripts();

		self::assertFalse( wp_script_is( $handle ) );

		// Test when hCaptcha was shown.
		hcaptcha()->form_shown = true;

		$subject->enqueue_scripts();

		self::assertTrue( wp_script_is( $handle ) );
	}

	/**
	 * Test add_type_module().
	 *
	 * @return void
	 * @noinspection JSUnresolvedLibraryURL
	 */
	public function test_add_type_module(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tag      = '<script src="https://test.test/a.js">some</script>';
		$expected = '<script type="module" src="https://test.test/a.js">some</script>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$subject = new Form();

		// Wrong tag.
		self::assertSame( $tag, $subject->add_type_module( $tag, 'some-handle', '' ) );

		// Proper tag.
		self::assertSame( $expected, $subject->add_type_module( $tag, 'hcaptcha-jetpack', '' ) );
	}

	/**
	 * Test editor_enqueue_scripts().
	 *
	 * @return void
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_editor_enqueue_scripts(): void {
		$admin_handle   = 'admin-jetpack';
		$args           = [
			'action' => 'hcaptcha_jetpack',
			'name'   => 'hcaptcha_jetpack_nonce',
			'id'     => [
				'source'  => [ 'jetpack/jetpack.php' ],
				'form_id' => 'contact',
			],
		];
		$hcaptcha       = '<div class="grunion-field-hcaptcha-wrap grunion-field-wrap">' . $this->get_hcap_form( $args ) . '</div>';
		$params         = [
			'hCaptcha' => $hcaptcha,
		];
		$expected_extra = [
			'group' => 1,
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			'data'  => 'var HCaptchaJetpackObject = ' . json_encode( $params, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
		];

		$subject = new Form();

		self::assertFalse( wp_script_is( $admin_handle ) );

		$subject->editor_enqueue_scripts();

		self::assertTrue( wp_script_is( $admin_handle ) );

		$script = wp_scripts()->registered[ $admin_handle ];

		self::assertSame( HCAPTCHA_URL . '/assets/js/admin-jetpack.min.js', $script->src );
		self::assertSame( [ 'hcaptcha' ], $script->deps );
		self::assertSame( HCAPTCHA_VERSION, $script->ver );
		self::assertSame( $expected_extra, $script->extra );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'CSS'
	form.contact-form .grunion-field-hcaptcha-wrap.grunion-field-wrap {
		flex-direction: row !important;
	}

	form.contact-form .grunion-field-hcaptcha-wrap.grunion-field-wrap .h-captcha,
	form.wp-block-jetpack-contact-form .grunion-field-wrap .h-captcha {
		margin-bottom: 0;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new Form();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test the_content_filter().
	 *
	 * @return void
	 */
	public function test_the_content_filter(): void {
		$subject = new Form();

		// Some content.
		$content = 'some content';

		self::assertSame( $content, $subject->the_content_filter( $content ) );

		// With contact-form shortcode.
		$content = '[contact-form]Some form content[/contact-form]';

		self::assertSame( $content, $subject->the_content_filter( $content ) );

		// With contact-form and hcaptcha shortcode outside.
		$content = '[contact-form]Some form content[/contact-form][hcaptcha]';

		self::assertSame( $content, $subject->the_content_filter( $content ) );

		// With contact-form and hcaptcha shortcode inside.
		$content  = '[contact-form]Some form content[hcaptcha][/contact-form]';
		$expected = '[contact-form]Some form content[hcaptcha force="" size="" id--source--0="jetpack/jetpack.php" id--form_id="0" protect="1" action="hcaptcha_jetpack" name="hcaptcha_jetpack_nonce" auto=""][/contact-form]';

		self::assertSame( $expected, $subject->the_content_filter( $content ) );
	}

	/**
	 * Test is_editing_jetpack_form_post().
	 *
	 * @return void
	 */
	public function test_is_editing_jetpack_form_post(): void {
		$subject = new class() extends Form {
			/**
			 * Expose the editor-state check for testing.
			 *
			 * @return bool
			 */
			public function is_editing_jetpack_form_post_for_test(): bool {
				return $this->is_editing_jetpack_form_post();
			}
		};

		self::assertFalse( $subject->is_editing_jetpack_form_post_for_test() );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['pagenow'] = 'post.php';

		self::assertFalse( $subject->is_editing_jetpack_form_post_for_test() );

		$_GET['post'] = 1;

		self::assertFalse( $subject->is_editing_jetpack_form_post_for_test() );

		$_GET['action'] = 'some';

		self::assertFalse( $subject->is_editing_jetpack_form_post_for_test() );

		$_GET['action'] = 'edit';

		self::assertFalse( $subject->is_editing_jetpack_form_post_for_test() );

		$post_id = wp_insert_post( [ 'post_content' => 'some content' ] );

		$_GET['post'] = $post_id;

		self::assertFalse( $subject->is_editing_jetpack_form_post_for_test() );

		$post_id = wp_insert_post( [ 'post_content' => '<!-- wp:jetpack/contact-form' ] );

		$_GET['post'] = $post_id;

		self::assertTrue( $subject->is_editing_jetpack_form_post_for_test() );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param string $hash Form hash.
	 * @param array  $id   Widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( string $hash = '', array $id = [] ): void {
		if ( $hash && '_' !== $hash[0] ) {
			$hash = '_' . $hash;
		}

		$id = $id ?: [
			'source'  => [ 'jetpack/jetpack.php' ],
			'form_id' => 'contact' . $hash,
		];

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}
}
