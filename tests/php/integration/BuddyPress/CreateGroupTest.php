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
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;

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
	 * Tear down the test.
	 */
	public function tearDown(): void {
		global $bp;

		unset( $bp->signup );

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
	 * Test verify().
	 */
	public function test_verify(): void {
		FunctionMocker::replace(
			'bp_is_group_creation_step',
			static function ( $step_slug ) {
				return 'group-details' === $step_slug;
			}
		);

		$subject = new CreateGroup();

		$this->prepare_verify_post( 'hcaptcha_bp_create_group_nonce', 'hcaptcha_bp_create_group' );

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
			],
			$actual
		);
	}

	/**
	 * Test verify() when not in step.
	 */
	public function test_verify_not_in_step(): void {
		FunctionMocker::replace( 'bp_is_group_creation_step', false );

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
		FunctionMocker::replace(
			'bp_is_group_creation_step',
			static function ( $step_slug ) {
				return 'group-details' === $step_slug;
			}
		);

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'BP_TESTS_DIR' === $constant_name;
			}
		);

		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				return '';
			},
			10,
			2
		);

		FunctionMocker::replace( 'bp_get_groups_root_slug', '' );

		$subject = new CreateGroup();

		$this->prepare_verify_post( 'hcaptcha_bp_create_group_nonce', 'hcaptcha_bp_create_group', null );

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
	#buddypress .h-captcha {
		margin-top: 15px;
	}
CSS;
		$expected = "<style>\n$expected\n</style>\n";

		$subject = new CreateGroup();

		ob_start();

		$subject->print_inline_styles();

		self::assertSame( $expected, ob_get_clean() );
	}
}
