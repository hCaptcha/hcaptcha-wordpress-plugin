<?php
/**
 * BaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use HCaptcha\Jetpack\Base;
use HCaptcha\Jetpack\Form;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Error;

/**
 * Class BaseTest.
 *
 * @group jetpack
 */
class BaseTest extends HCaptchaWPTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST['contact-form-hash'], $GLOBALS['pagenow'], $_GET );

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
		$subject = Mockery::mock( Form::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_editing_jetpack_form_post' )->andReturn( $is_editing_jetpack_form_post );

		$subject->__construct();

		self::assertSame( 10, has_filter( 'jetpack_contact_form_html', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 0, has_filter( 'widget_text', [ $subject, 'add_hcaptcha' ] ) );

		self::assertSame( 10, has_filter( 'widget_text', 'shortcode_unautop' ) );
		self::assertSame( 10, has_filter( 'widget_text', 'do_shortcode' ) );

		self::assertSame( 100, has_filter( 'jetpack_contact_form_is_spam', [ $subject, 'verify' ] ) );

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

		$subject = new Form();

		self::assertFalse( $subject->verify() );
		self::assertTrue( $subject->verify( true ) );
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

		$subject = new Form();

		self::assertEquals( $error, $subject->verify() );
		self::assertNull( $this->get_protected_property( $subject, 'error_form_hash' ) );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', [ $subject, 'error_message' ] ) );

		$_POST['contact-form-hash'] = $hash;

		self::assertEquals( $error, $subject->verify() );
		self::assertSame( $hash, $this->get_protected_property( $subject, 'error_form_hash' ) );
		self::assertSame( 10, has_action( 'hcap_hcaptcha_content', [ $subject, 'error_message' ] ) );
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
			'data'  => 'var HCaptchaJetpackObject = ' . json_encode( $params ) . ';',
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
		$subject = Mockery::mock( Base::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->is_editing_jetpack_form_post() );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['pagenow'] = 'post.php';

		self::assertFalse( $subject->is_editing_jetpack_form_post() );

		$_GET['post'] = 1;

		self::assertFalse( $subject->is_editing_jetpack_form_post() );

		$_GET['action'] = 'some';

		self::assertFalse( $subject->is_editing_jetpack_form_post() );

		$_GET['action'] = 'edit';

		self::assertFalse( $subject->is_editing_jetpack_form_post() );

		$post_id = wp_insert_post( [ 'post_content' => 'some content' ] );

		$_GET['post'] = $post_id;

		self::assertFalse( $subject->is_editing_jetpack_form_post() );

		$post_id = wp_insert_post( [ 'post_content' => '<!-- wp:jetpack/contact-form' ] );

		$_GET['post'] = $post_id;

		self::assertTrue( $subject->is_editing_jetpack_form_post() );
	}
}
