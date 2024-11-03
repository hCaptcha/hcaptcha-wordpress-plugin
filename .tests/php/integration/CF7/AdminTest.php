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
	 * @param bool $mode_embed Mode embed.
	 * @param bool $mode_live  Mode live.
	 * @param bool $is_admin   Admin mode.
	 *
	 * @dataProvider dp_test_init_hooks
	 */
	public function test_init_hooks( bool $mode_embed, bool $mode_live, bool $is_admin ): void {
		$cf7_status = array_filter( [ $mode_embed ? 'embed' : '', $mode_live ? 'live' : '' ] );
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

		if ( $is_admin ) {
			set_current_screen( $cf7_screen );
		}

		if ( $mode_embed && $is_admin ) {
			self::assertSame(
				54,
				has_action( 'wpcf7_admin_init', [ $subject, 'add_tag_generator_hcaptcha' ] )
			);
		} else {
			self::assertFalse(
				has_action( 'wpcf7_admin_init', [ $subject, 'add_tag_generator_hcaptcha' ] )
			);
		}

		if ( $mode_live && $is_admin ) {
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
	 * Data provider for test_init_hooks().
	 *
	 * @return array
	 */
	public function dp_test_init_hooks(): array {
		return [
			[ false, false, false ],
			[ false, false, true ],
			[ false, true, false ],
			[ false, true, true ],
			[ true, false, false ],
			[ true, false, true ],
			[ true, true, false ],
			[ true, true, true ],
		];
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
		$expected = '		<header class="description-box">
			<h3>hCaptcha field form-tag generator</h3>
			<p>Generate a form-tag for a hCaptcha field.</p>
		</header>

		<div class="control-box">
			<fieldset>
	<legend id="tag-generator-panel-cf7-hcaptcha-type-legend">Field type</legend>

	<select data-tag-part="basetype" aria-labelledby="tag-generator-panel-cf7-hcaptcha-type-legend"><option value="cf7-hcaptcha">hCaptcha field</option></select>

		<br />
	<label>
		<input type="checkbox" data-tag-part="type-suffix" value="*" />
		This is a required field.	</label>
	</fieldset>
<fieldset>
	<legend id="tag-generator-panel-cf7-hcaptcha-name-legend">Field name</legend>
	<input type="text" data-tag-part="name" pattern="[A-Za-z][A-Za-z0-9_\-]*" aria-labelledby="tag-generator-panel-cf7-hcaptcha-name-legend" />

</fieldset>
<fieldset>
	<legend id="tag-generator-panel-cf7-hcaptcha-class-legend">Class attribute</legend>
	<input type="text" data-tag-part="option" data-tag-option="class:" pattern="[A-Za-z0-9_\-\s]*" aria-labelledby="tag-generator-panel-cf7-hcaptcha-class-legend" />
</fieldset>
		</div>

		<footer class="insert-box">
			<div class="flex-container">
	<input type="text" class="code" readonly="readonly" onfocus="this.select();" data-tag-part="tag" aria-label="The form-tag to be inserted into the form template" />	<button type="button" class="button button-primary" data-taggen="insert-tag">Insert Tag</button>
</div>
<p class="mail-tag-tip">To use the user input in the email, insert the corresponding mail-tag <strong data-tag-part="mail-tag"></strong> into the email template.</p>
		</footer>
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

		$data = $wp_scripts->registered['wpcf7-admin']->extra['before'][1];
		preg_match( '/var wpcf7 = ({.+});/s', $data, $m );
		$wpcf7 = json_decode( $m[1], true );

		self::assertArrayNotHasKey( 'api', $wpcf7 );

		$subject->enqueue_admin_scripts_after_cf7();

		$data = $wp_scripts->registered['wpcf7-admin']->extra['before'][1];
		preg_match( '/var wpcf7 = ({.+});/s', $data, $m );
		$wpcf7 = json_decode( $m[1], true );

		self::assertArrayHasKey( 'api', $wpcf7 );
	}
}
