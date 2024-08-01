<?php
/**
 * AdminTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\CF7;

use HCaptcha\CF7\Admin;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Mockery;
use WPCF7_TagGenerator;

/**
 * Test Admin class.
 *
 * @requires PHP >= 7.4
 *
 * @group    cf7
 * @group    cf7-admin
 */
class AdminTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'contact-form-7/wp-contact-form-7.php';

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['current_screen'], $_GET['post'], $_GET['page'] );

		parent::tearDown();
	}

	/**
	 * Test init_hooks().
	 *
	 * @param bool $mode_auto  Mode auto.
	 * @param bool $mode_embed Mode embed.
	 * @param bool $is_admin   Admin mode.
	 * @param bool $expected   Hooks expected to be added.
	 *
	 * @dataProvider dp_test_init_hooks
	 */
	public function test_init_hooks( bool $mode_auto, bool $mode_embed, bool $is_admin, bool $expected ): void {
		$cf7_status = array_filter( [ $mode_auto ? 'form' : '', $mode_embed ? 'embed' : '' ] );
		$cf7_screen = 'toplevel_page_wpcf7';

		if ( $is_admin ) {
			$_GET['page'] = 'wpcf7';
			$_GET['post'] = 177;

			set_current_screen( 'some' );
		}

		update_option(
			'hcaptcha_settings',
			[
				'cf7_status' => $cf7_status,
			]
		);

		hcaptcha()->init_hooks();

		$subject = new Admin();

		if ( $is_admin && $cf7_status ) {
			set_current_screen( $cf7_screen );
		}

		if ( $expected ) {
			self::assertSame(
				54,
				has_action( 'wpcf7_admin_init', [ $subject, 'add_tag_generator_hcaptcha' ] )
			);
			self::assertSame(
				10,
				has_action( 'current_screen', [ $subject, 'current_screen' ] )
			);
			self::assertSame(
				0,
				has_action( $cf7_screen, [ $subject, 'before_toplevel_page_wpcf7' ] )
			);
			self::assertSame(
				20,
				has_action( $cf7_screen, [ $subject, 'after_toplevel_page_wpcf7' ] )
			);
			self::assertSame(
				0,
				has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_before_cf7' ] )
			);
			self::assertSame(
				20,
				has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_after_cf7' ] )
			);
		} else {
			self::assertFalse(
				has_action( 'wpcf7_admin_init', [ $subject, 'add_tag_generator_hcaptcha' ] )
			);
			self::assertFalse(
				has_action( 'current_screen', [ $subject, 'current_screen' ] )
			);
			self::assertFalse(
				has_action( $cf7_screen, [ $subject, 'before_toplevel_page_wpcf7' ] )
			);
			self::assertFalse(
				has_action( $cf7_screen, [ $subject, 'after_toplevel_page_wpcf7' ] )
			);
			self::assertFalse(
				has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_before_cf7' ] )
			);
			self::assertFalse( has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_after_cf7' ] ) );
		}
	}

	/**
	 * Test init_hooks() when not on CF7 admin page.
	 *
	 * @return void
	 */
	public function test_init_hooks_NOT_on_cf7_admin_page(): void {
		set_current_screen( 'some' );

		update_option(
			'hcaptcha_settings',
			[
				'cf7_status' => [ 'form', 'embed' ],
			]
		);

		hcaptcha()->init_hooks();

		$subject = new Admin();

		self::assertFalse(
			has_action( 'wpcf7_admin_init', [ $subject, 'add_tag_generator_hcaptcha' ] )
		);
		self::assertFalse(
			has_action( 'current_screen', [ $subject, 'current_screen' ] )
		);
	}

	/**
	 * Data provider for test_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			[ false, false, false, false ],
			[ false, false, true, false ],
			[ false, true, false, false ],
			[ false, true, true, true ],
			[ true, false, false, false ],
			[ true, false, true, true ],
			[ true, true, false, false ],
			[ true, true, true, true ],
		];
	}

	/**
	 * Test current_screen().
	 *
	 * @param ?object $current_screen Current screen.
	 *
	 * @return void
	 * @dataProvider dp_test_current_screen
	 */
	public function test_current_screen( ?object $current_screen ): void {
		$current_screen_id = $current_screen->id ?? '';

		$subject = new Admin();

		$subject->current_screen( $current_screen );

		if ( $current_screen_id ) {
			self::assertSame(
				0,
				has_action( $current_screen_id, [ $subject, 'before_toplevel_page_wpcf7' ] )
			);
			self::assertSame(
				20,
				has_action( $current_screen_id, [ $subject, 'after_toplevel_page_wpcf7' ] )
			);
			self::assertSame(
				0,
				has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_before_cf7' ] )
			);
			self::assertSame(
				20,
				has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_after_cf7' ] )
			);
		} else {
			self::assertFalse(
				has_action( $current_screen_id, [ $subject, 'before_toplevel_page_wpcf7' ] )
			);
			self::assertFalse(
				has_action( $current_screen_id, [ $subject, 'after_toplevel_page_wpcf7' ] )
			);
			self::assertFalse(
				has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_before_cf7' ] )
			);
			self::assertFalse( has_action( 'admin_enqueue_scripts', [ $subject, 'enqueue_admin_scripts_after_cf7' ] ) );
		}
	}

	/**
	 * Data provider for test_current_screen().
	 *
	 * @return array
	 */
	public function dp_test_current_screen(): array {
		return [
			[ null ],
			[ (object) [ 'id' => 'some_id' ] ],
		];
	}

	/**
	 * Test before_toplevel_page_wpcf7() and after_toplevel_page_wpcf7().
	 *
	 * @return void
	 */
	public function test_toplevel_page_wpcf7(): void {
		$form = <<<HTML
<div class="wrap" id="wpcf7-contact-form-editor">
	<h1 class="wp-heading-inline">Edit Contact Form</h1>
	<form method="post" action="https://test.test/wp-admin/admin.php?page=wpcf7&post=2642" id="wpcf7-admin-form-element">
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<input type="text" id="wpcf7-shortcode" class="large-text code" value="[contact-form-7 id=&quot;4315f34&quot; title=&quot;Contact form 2&quot;]" />
				</div><!-- #post-body-content -->
				<div id="postbox-container-1" class="postbox-container">
				</div><!-- #postbox-container-1 -->
				<div id="postbox-container-2" class="postbox-container">
					<div id="contact-form-editor">
						<h2>Form</h2>
						<fieldset>
							<textarea id="wpcf7-form" name="wpcf7-form" cols="100" rows="24" class="large-text code" data-config-field="form.body">
							</textarea>
						</fieldset>
					</div>
				</div><!-- #postbox-container-2 -->
			</div><!-- #post-body -->
			<br class="clear" />
		</div><!-- #poststuff -->
	</form>
</div><!-- .wrap -->
HTML;

		$cf7_shortcode         = '[contact-form-7 id="4315f34" title="Contact form 2"]';
		$live_container        = '<div id="postbox-container-live" class="postbox-container"><div id="form-live"><h3>Live Form</h3>' . $cf7_shortcode . '</div></div>';
		$stripe_message        = '<h4><em>The Stripe payment element already contains an invisible hCaptcha. No need to add it to the form.</em></h4>';
		$stripe_form           = '<div class="wpcf7-stripe">Some Stripe content</div>';
		$live_stripe_container = '<div id="postbox-container-live" class="postbox-container"><div id="form-live"><h3>Live Form</h3>' . $stripe_message . $stripe_form . '</div></div>';

		$expected = <<<HTML
<div class="wrap" id="wpcf7-contact-form-editor">
	<h1 class="wp-heading-inline">Edit Contact Form</h1>
	<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<form method="post" action="https://test.test/wp-admin/admin.php?page=wpcf7&post=2642" id="wpcf7-admin-form-element">
		<div id="post-body-content">
					<input type="text" id="wpcf7-shortcode" class="large-text code" value="[contact-form-7 id=&quot;4315f34&quot; title=&quot;Contact form 2&quot;]" />
				</div><!-- #post-body-content -->
				<div id="postbox-container-1" class="postbox-container">
				</div><!-- #postbox-container-1 -->
				<div id="postbox-container-2" class="postbox-container">
					<div id="contact-form-editor">
						<h2>Form</h2>
						<fieldset>
							<textarea id="wpcf7-form" name="wpcf7-form" cols="100" rows="24" class="large-text code" data-config-field="form.body">
							</textarea>
						</fieldset>
					</div>
				</div><!-- #postbox-container-2 -->
			</form></div><!-- #post-body -->
$live_container

			<br class="clear" />
		</div><!-- #poststuff -->
</div><!-- .wrap -->
HTML;

		$subject = new Admin();

		// No form start.
		ob_start();
		$no_form_start = 'some html';
		$subject->before_toplevel_page_wpcf7();
		echo $no_form_start; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$subject->after_toplevel_page_wpcf7();
		self::assertSame( $no_form_start, ob_get_clean() );

		// No form end.
		ob_start();
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @noinspection HtmlUnknownAttribute */
		$no_form_end = '<form some-html <div id="poststuff">';
		$subject->before_toplevel_page_wpcf7();
		echo $no_form_end; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$subject->after_toplevel_page_wpcf7();
		self::assertSame( $no_form_end, ob_get_clean() );

		// No shortcode.
		ob_start();
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @noinspection HtmlUnknownAttribute */
		$no_shortcode = '<form some-html <div id="poststuff"> </div><!-- #poststuff --> </form>';
		$subject->before_toplevel_page_wpcf7();
		echo $no_shortcode; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$subject->after_toplevel_page_wpcf7();
		self::assertSame( $no_shortcode, ob_get_clean() );

		// Real case.
		ob_start();
		$subject->before_toplevel_page_wpcf7();
		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$subject->after_toplevel_page_wpcf7();
		self::assertSame( $expected, ob_get_clean() );

		add_shortcode(
			'contact-form-7',
			static function () use ( $stripe_form ) {
				return $stripe_form;
			}
		);

		$expected = str_replace( $live_container, $live_stripe_container, $expected );

		// Real case with a Stripe element.
		ob_start();
		$subject->before_toplevel_page_wpcf7();
		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$subject->after_toplevel_page_wpcf7();
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test add_tag_generator_hcaptcha().
	 *
	 * @return void
	 * @noinspection PhpUndefinedConstantInspection
	 */
	public function test_add_tag_generator_hcaptcha(): void {
		require_once WPCF7_PLUGIN_DIR . '/admin/includes/tag-generator.php';

		$tag_generator = WPCF7_TagGenerator::get_instance();

		$subject = new Admin();

		ob_start();
		$tag_generator->print_buttons();
		$buttons = ob_get_clean();

		self::assertFalse( strpos( $buttons, 'hcaptcha' ) );

		$subject->add_tag_generator_hcaptcha();

		ob_start();
		$tag_generator->print_buttons();
		$buttons = ob_get_clean();

		self::assertFalse( strpos( $buttons, 'hcaptcha' ) );

		update_option(
			'hcaptcha_settings',
			[
				'cf7_status' => [ 'embed' ],
			]
		);

		hcaptcha()->init_hooks();

		$subject = new Admin();

		ob_start();
		$tag_generator->print_buttons();
		$buttons = ob_get_clean();

		self::assertFalse( strpos( $buttons, 'hcaptcha' ) );

		$subject->add_tag_generator_hcaptcha();

		ob_start();
		$tag_generator->print_buttons();
		$buttons = ob_get_clean();

		self::assertNotFalse( strpos( $buttons, 'hcaptcha' ) );
	}

	/**
	 * Test tag_generator_hcaptcha().
	 *
	 * @return void
	 */
	public function test_tag_generator_hcaptcha(): void {
		$args     = [
			'id'      => 'cf7-hcaptcha',
			'title'   => 'hCaptcha',
			'content' => 'tag-generator-panel-cf7-hcaptcha',
		];
		$expected = '		<div class="control-box">
			<fieldset>
				<legend>Generate a form-tag for a hCaptcha field.</legend>

				<table class="form-table">
					<tbody>

					<tr>
						<th scope="row">
							<label for="tag-generator-panel-cf7-hcaptcha-id">
								Id attribute							</label>
						</th>
						<td>
							<input
									type="text" name="id" class="idvalue oneline option"
									id="tag-generator-panel-cf7-hcaptcha-id"/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="tag-generator-panel-cf7-hcaptcha-class">
								Class attribute							</label>
						</th>
						<td>
							<input
									type="text" name="class" class="classvalue oneline option"
									id="tag-generator-panel-cf7-hcaptcha-class"/>
						</td>
					</tr>

					</tbody>
				</table>
			</fieldset>
		</div>

		<div class="insert-box">
			<label>
				<input
						type="text" name="cf7-hcaptcha" class="tag code" readonly="readonly"
						onfocus="this.select()"/>
			</label>

			<div class="submitbox">
				<input
						type="button" class="button button-primary insert-tag"
						value="Insert Tag"/>
			</div>
		</div>
		';

		$subject = new Admin();

		ob_start();
		$subject->tag_generator_hcaptcha( [], $args );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test enqueue_admin_scripts_before_cf7() and enqueue_admin_scripts_after_cf7().
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection PhpUndefinedConstantInspection
	 */
	public function test_enqueue_admin_scripts(): void {
		global $wp_scripts;

		set_current_screen( 'some' );

		$subject = new Admin();

		self::assertFalse( wp_style_is( 'contact-form-7' ) );
		self::assertFalse( wp_script_is( 'swv' ) );
		self::assertFalse( wp_script_is( 'contact-form-7' ) );
		self::assertFalse( wp_style_is( $subject::ADMIN_HANDLE ) );

		$subject->enqueue_admin_scripts_before_cf7();

		self::assertTrue( wp_style_is( 'contact-form-7' ) );
		self::assertTrue( wp_script_is( 'swv' ) );
		self::assertTrue( wp_script_is( 'contact-form-7' ) );
		self::assertTrue( wp_style_is( $subject::ADMIN_HANDLE ) );

		require_once WPCF7_PLUGIN_DIR . '/admin/admin.php';

		wpcf7_admin_enqueue_scripts( 'wpcf7' );

		$data = $wp_scripts->registered['wpcf7-admin']->extra['data'];
		preg_match( '/var wpcf7 = ({.+});/', $data, $m );
		$wpcf7 = json_decode( $m[1], true );

		self::assertArrayNotHasKey( 'api', $wpcf7 );

		$subject->enqueue_admin_scripts_after_cf7();

		$data = $wp_scripts->registered['wpcf7-admin']->extra['data'];
		preg_match( '/var wpcf7 = ({.+});/', $data, $m );
		$wpcf7 = json_decode( $m[1], true );

		self::assertArrayHasKey( 'api', $wpcf7 );
	}

	/**
	 * Test is_cf7_form_admin_page().
	 *
	 * @param string $page     Page.
	 * @param bool   $has_post Has post.
	 * @param bool   $expected Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_is_cf7_form_admin_page
	 */
	public function test_is_cf7_form_admin_page( string $page, bool $has_post, bool $expected ): void {
		$subject = Mockery::mock( Admin::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		$method       = 'is_cf7_form_admin_page';
		$_GET['page'] = $page;

		if ( $has_post ) {
			$_GET['post'] = 177;
		}

		self::assertSame( $expected, $subject->$method( $expected ) );
	}

	/**
	 * Data provider for test_is_cf7_form_admin_page().
	 *
	 * @return array
	 */
	public function dp_test_is_cf7_form_admin_page(): array {
		return [
			[ 'some', false, false ],
			[ 'wpcf7', false, false ],
			[ 'wpcf7', true, true ],
			[ 'wpcf7-new', false, true ],
			[ 'wpcf7-new', true, true ],
		];
	}
}
