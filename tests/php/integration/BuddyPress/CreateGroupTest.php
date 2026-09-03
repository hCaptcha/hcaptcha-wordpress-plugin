<?php
/**
 * CreateGroupTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\BuddyPress;

use BP_Groups_Group;
use HCaptcha\BuddyPress\CreateGroup;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionException;

/**
 * Test CreateGroup.
 *
 * @group bp
 */
class CreateGroupTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'buddypress/bp-loader.php';

	/**
	 * Hooks to replay after loading BuddyPress.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'setup_theme',
		'after_setup_theme',
		'init',
	];

	/**
	 * Initialize BuddyPress after the WordPress test bootstrap.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Enable the BuddyPress Groups component used by these tests.
	 *
	 * @return void
	 */
	protected function before_load_test_plugins(): void {
		add_filter(
			'bp_active_components',
			static function ( $components ) {
				$components['groups'] = '1';

				return $components;
			}
		);
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		global $bp;

		unset( $bp->signup, $bp->current_component, $bp->current_action, $bp->action_variables );

		parent::tearDown();
	}

	/**
	 * Test add_captcha().
	 */
	public function test_hcap_bp_group_form(): void {
		$args     = [
			'action' => 'hcaptcha_bp_create_group',
			'name'   => 'hcaptcha_bp_create_group_nonce',
			'id'     => [
				'source'  => [ 'buddypress/bp-loader.php' ],
				'form_id' => 'create_group',
			],
		];
		$expected =
			'<div class="hcap_buddypress_group_form">' .
			$this->get_hcap_form( $args ) .
			'</div>';

		$subject = new CreateGroup();

		ob_start();

		$subject->add_captcha();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test hCaptcha in the live BuddyPress group creation template.
	 *
	 * @return void
	 */
	public function test_live_group_creation_template(): void {
		$bp                    = buddypress();
		$bp->current_component = 'groups';
		$bp->current_action    = 'create';
		$bp->action_variables  = [ 1 => 'group-details' ];
		$subject               = new CreateGroup();
		$template              = bp_locate_template( 'groups/create.php' );
		$html                  = bp_buffer_template_part( 'groups/create', null, false );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/buddypress/' ),
			wp_normalize_path( (string) $template )
		);
		self::assertStringContainsString( 'id="create-group-form"', $html );
		self::assertStringContainsString( 'name="group-name"', $html );
		self::assertStringContainsString( 'class="hcap_buddypress_group_form"', $html );
		self::assertStringContainsString( 'name="hcaptcha_bp_create_group_nonce"', $html );
		self::assertSame( 10, has_action( 'bp_after_group_details_creation_step', [ $subject, 'add_captcha' ] ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$this->set_group_creation_step();

		$subject = new CreateGroup();

		$this->prepare_verify_post( 'hcaptcha_bp_create_group_nonce', 'hcaptcha_bp_create_group' );
		$this->prepare_widget_id();

		self::assertTrue( $subject->verify( new BP_Groups_Group() ) );
	}

	/**
	 * Test get_entry().
	 *
	 * @return void
	 * @throws ReflectionException Reflection exception.
	 */
	public function test_get_entry(): void {
		$subject = new CreateGroup();
		$method  = $this->set_method_accessibility( $subject, 'get_entry' );

		$_POST['h-captcha-response'] = 'some response';

		$bp_group               = new BP_Groups_Group();
		$bp_group->name         = 'John Doe';
		$bp_group->description  = 'Some description';
		$bp_group->date_created = '2026-02-15 15:03:24';
		$bp_group->slug         = 'must-not-be-included';

		$actual = $method->invoke( $subject, $bp_group );

		self::assertSame(
			[
				'nonce_name'         => 'hcaptcha_bp_create_group_nonce',
				'nonce_action'       => 'hcaptcha_bp_create_group',
				'h-captcha-response' => 'some response',
				'data'               => [
					'name'         => 'John Doe',
					'description'  => 'Some description',
					'date_created' => '2026-02-15 15:03:24',
				],
				'expected_id'        => [
					'source'  => [ 'buddypress/bp-loader.php' ],
					'form_id' => 'create_group',
				],
			],
			$actual
		);
	}

	/**
	 * Test verify() when not in step.
	 */
	public function test_verify_not_in_step(): void {
		$this->set_group_creation_step( false );

		$subject = new CreateGroup();

		self::assertFalse( $subject->verify( new BP_Groups_Group() ) );
	}

	/**
	 * Test verify() when not verified.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function test_verify_not_verified(): void {
		$this->set_group_creation_step();

		if ( ! defined( 'BP_TESTS_DIR' ) ) {
			define( 'BP_TESTS_DIR', __DIR__ );
		}

		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				return '';
			},
			10,
			2
		);

		$subject = new CreateGroup();

		$this->prepare_verify_post( 'hcaptcha_bp_create_group_nonce', 'hcaptcha_bp_create_group', null );
		$this->prepare_widget_id();

		self::assertFalse( $subject->verify( new BP_Groups_Group() ) );

		$bp = buddypress();

		self::assertSame( 'Please complete the hCaptcha.', $bp->template_message );
		self::assertSame( 'error', $bp->template_message_type );
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function test_print_inline_styles(): void {
		$subject = new CreateGroup();

		ob_start();

		$subject->print_inline_styles();
		$css = (string) ob_get_clean();

		self::assertStringContainsString( '<style>', $css );
		self::assertStringContainsString( '#buddypress .h-captcha', $css );
		self::assertStringContainsString( 'margin-top', $css );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @return void
	 */
	private function prepare_widget_id(): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'buddypress/bp-loader.php' ],
				'form_id' => 'create_group',
			]
		);
	}

	/**
	 * Set the live BuddyPress group creation route.
	 *
	 * @param bool $group_details Whether the group details step is active.
	 *
	 * @return void
	 */
	private function set_group_creation_step( bool $group_details = true ): void {
		$bp                    = buddypress();
		$bp->current_component = $group_details ? 'groups' : 'members';
		$bp->current_action    = $group_details ? 'create' : '';
		$bp->action_variables  = $group_details ? [ 1 => 'group-details' ] : [];
	}
}
