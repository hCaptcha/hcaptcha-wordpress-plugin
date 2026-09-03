<?php
/**
 * DownloadManagerTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\DownloadManager;

use HCaptcha\DownloadManager\DownloadManager;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use ReflectionClass;
use WPDM\Package\PackageController;

/**
 * Test DownloadManager class.
 *
 * @group download-manager
 */
class DownloadManagerTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'download-manager/download-manager.php';

	/**
	 * Download Manager redirects and exits from its activated_plugin callback.
	 *
	 * @var string[]
	 */
	protected static array $plugin_silent_activation = [ 'download-manager/download-manager.php' ];

	/**
	 * Hooks to replay after loading Download Manager.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [ 'init' ];

	/**
	 * Register Download Manager's post type after the WordPress test bootstrap.
	 *
	 * @var bool
	 */
	protected static bool $force_plugin_load_hooks = true;

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = new DownloadManager();

		self::assertSame( 10, has_action( 'wpdm_after_fetch_template', [ $subject, 'add_hcaptcha' ] ) );
		self::assertSame( 10, has_action( 'wpdm_onstart_download', [ $subject, 'verify' ] ) );
		self::assertSame( 20, has_action( 'wp_head', [ $subject, 'print_inline_styles' ] ) );
	}

	/**
	 * Test add_hcaptcha().
	 *
	 * @return void
	 * @noinspection HtmlUnknownAttribute
	 */
	public function test_add_hcaptcha(): void {
		$form_id   = 3220;
		$args      = [
			'action' => 'hcaptcha_download_manager',
			'name'   => 'hcaptcha_download_manager_nonce',
			'id'     => [
				'source'  => [ 'download-manager/download-manager.php' ],
				'form_id' => $form_id,
			],
		];
		$hcap_form = $this->get_hcap_form( $args );
		$template  = <<<HTML
<div class="row">
	<div class="col-md-12">
		<div class="card mb-3 p-3 hide_empty wpdm_hide wpdm_remove_empty">[featured_image]</div>
	</div>
	<div class="col-md-5">
		<div class="wpdm-button-area mb-3 p-3 card">
			<a class='wpdm-download-link download-on-click btn btn-primary ' rel='nofollow' href='#'
			   data-downloadurl="https://test.test/download/test-download/?wpdmdl=$form_id&refresh=6730d78f063da1731254159">Download</a>
			<div class="alert alert-warning mt-2 wpdm_hide wpdm_remove_empty">
				Download is available until [expire_date]
			</div>
		</div>
		<ul class="list-group ml-0 mb-2">
			<li class="list-group-item d-flex justify-content-between align-items-center wpdm_hide wpdm_remove_empty">
				Version
				<span class="badge"></span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:download_count]">
				Download
				<span class="badge">5</span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:file_size]">
				File Size
				<span class="badge">10 KB</span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:file_count]">
				File Count
				<span class="badge">1</span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:create_date]">
				Create Date
				<span class="badge">18.11.2022</span>
			</li>
			<li class="list-group-item  d-flex justify-content-between align-items-center [hide_empty:update_date]">
				Last Updated
				<span class="badge">18.11.2022</span>
			</li>
		</ul>
	</div>
	<div class="col-md-7">
		<h1 class="mt-0">Test Download</h1>
		<div class="wel">
		</div>
	</div>
</div>
HTML;
		$expected  = <<<HTML
<form method="post" action="https://test.test/download/test-download/?wpdmdl=$form_id&#038;refresh=6730d78f063da1731254159"><div class="row">
	<div class="col-md-12">
		<div class="card mb-3 p-3 hide_empty wpdm_hide wpdm_remove_empty">[featured_image]</div>
	</div>
	<div class="col-md-5">
		<div class="wpdm-button-area mb-3 p-3 card">
			<button type="submit" class='wpdm-download-link  btn btn-primary ' rel='nofollow' href='#'
			   data-downloadurl="https://test.test/download/test-download/?wpdmdl=$form_id&refresh=6730d78f063da1731254159">Download</button>
			<div class="alert alert-warning mt-2 wpdm_hide wpdm_remove_empty">
				Download is available until [expire_date]
			</div>
		</div>
		$hcap_form<ul class="list-group ml-0 mb-2">
			<li class="list-group-item d-flex justify-content-between align-items-center wpdm_hide wpdm_remove_empty">
				Version
				<span class="badge"></span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:download_count]">
				Download
				<span class="badge">5</span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:file_size]">
				File Size
				<span class="badge">10 KB</span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:file_count]">
				File Count
				<span class="badge">1</span>
			</li>
			<li class="list-group-item d-flex justify-content-between align-items-center [hide_empty:create_date]">
				Create Date
				<span class="badge">18.11.2022</span>
			</li>
			<li class="list-group-item  d-flex justify-content-between align-items-center [hide_empty:update_date]">
				Last Updated
				<span class="badge">18.11.2022</span>
			</li>
		</ul>
	</div>
	<div class="col-md-7">
		<h1 class="mt-0">Test Download</h1>
		<div class="wel">
		</div>
	</div>
</div></form>
HTML;
		$vars      = [];

		$subject = new DownloadManager();

		self::assertSame( $expected, $subject->add_hcaptcha( $template, $vars ) );
	}

	/**
	 * Test hCaptcha in a package rendered by the live Download Manager template.
	 *
	 * @return void
	 */
	public function test_live_package_template(): void {
		$package_id = PackageController::create(
			[
				'post_title'   => 'Live Download',
				'post_content' => 'Download Manager package content.',
				'post_status'  => 'publish',
				'template'     => 'link-template-panel.php',
				'files'        => [ 'live-download.txt' ],
				'access'       => [ 'guest' ],
			]
		);
		$subject    = new DownloadManager();
		$controller = new ReflectionClass( PackageController::class );
		$html       = WPDM()->package->fetchTemplate( 'page-template-default.php', $package_id, 'page' );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertSame( 'wpdmpro', get_post_type( $package_id ) );
		self::assertStringStartsWith(
			wp_normalize_path( WP_PLUGIN_DIR . '/download-manager/' ),
			wp_normalize_path( (string) $controller->getFileName() )
		);
		self::assertStringContainsString( 'wpdm-button-area', $html );
		self::assertStringContainsString( '<form method="post"', $html );
		self::assertStringContainsString( 'name="hcaptcha_download_manager_nonce"', $html );
		self::assertStringContainsString( 'wpdmdl=' . $package_id, $html );
		self::assertSame( 10, has_filter( 'wpdm_after_fetch_template', [ $subject, 'add_hcaptcha' ] ) );
	}

	/**
	 * Test verify().
	 */
	public function test_verify(): void {
		$form_id = 3220;

		$this->prepare_verify_post( 'hcaptcha_download_manager_nonce', 'hcaptcha_download_manager' );
		$this->prepare_widget_id( $form_id );

		$_GET['wpdmdl'] = $form_id;

		$subject = new DownloadManager();

		$subject->verify( null );
	}

	/**
	 * Test verify() not verified.
	 *
	 * @noinspection PhpUnusedParameterInspection*/
	public function test_verify_not_verified(): void {
		$die_arr  = [];
		$expected = [
			'The hCaptcha is invalid.',
			'hCaptcha error',
			[
				'back_link' => true,
				'response'  => 303,
			],
		];

		$form_id = 3220;

		$this->prepare_verify_post( 'hcaptcha_download_manager_nonce', 'hcaptcha_download_manager', false );
		$this->prepare_widget_id( $form_id );

		$_GET['wpdmdl'] = $form_id;

		$subject = new DownloadManager();

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify( null );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Test verify() with missing hCaptcha widget id.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function test_verify_missing_widget_id(): void {
		$die_arr  = [];
		$expected = [
			'Bad hCaptcha signature!',
			'hCaptcha error',
			[
				'back_link' => true,
				'response'  => 303,
			],
		];

		$_GET['wpdmdl'] = 3220;

		$this->prepare_verify_post( 'hcaptcha_download_manager_nonce', 'hcaptcha_download_manager' );

		$subject = new DownloadManager();

		add_filter(
			'wp_die_handler',
			static function ( $name ) use ( &$die_arr ) {
				return static function ( $message, $title, $args ) use ( &$die_arr ) {
					$die_arr = [ $message, $title, $args ];
				};
			}
		);

		$subject->verify( null );

		self::assertSame( $expected, $die_arr );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return void
	 */
	private function prepare_widget_id( int $form_id ): void {
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'download-manager/download-manager.php' ],
				'form_id' => $form_id,
			]
		);
	}

	/**
	 * Test print_inline_styles().
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 * @noinspection CssUnresolvedCustomProperty
	 */
	public function test_print_inline_styles(): void {
		$subject = new DownloadManager();

		ob_start();

		$subject->print_inline_styles();
		$css = (string) ob_get_clean();

		self::assertStringContainsString( '<style>', $css );
		self::assertStringContainsString( '.wpdm-button-area', $css );
		self::assertStringContainsString( 'margin-bottom', $css );
		self::assertStringContainsString( '--color-primary', $css );
	}
}
