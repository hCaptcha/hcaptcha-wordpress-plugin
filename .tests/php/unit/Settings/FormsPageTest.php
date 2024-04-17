<?php
/**
 * FormsPageTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Admin\Events\FormsTable;
use HCaptcha\Main;
use HCaptcha\Settings\FormsPage;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\Settings;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use KAGG\Settings\Abstracts\SettingsBase;
use Mockery;
use ReflectionException;
use WP_List_Table;
use WP_Mock;
use tad\FunctionMocker\FunctionMocker;

/**
 * Class FormsPageTest
 *
 * @group settings
 * @group settings-forms-page
 */
class FormsPageTest extends HCaptchaTestCase {
	/**
	 * Test page_title().
	 */
	public function test_page_title() {
		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'page_title';
		self::assertSame( 'Forms', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title() {
		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'section_title';
		self::assertSame( 'forms', $subject->$method() );
	}

	/**
	 * Test tab_name().
	 */
	public function test_tab_name() {
		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'tab_name';
		self::assertSame( 'Forms', $subject->$method() );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks() {
		$plugin_base_name = 'hcaptcha-wordpress-plugin/hcaptcha.php';

		$subject = Mockery::mock( FormsPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'plugin_basename' )->andReturn( $plugin_base_name );

		WP_Mock::expectActionAdded( 'admin_init', [ $subject, 'admin_init' ] );

		$subject->init_hooks();
	}

	/**
	 * Test admin_init().
	 *
	 * @param bool $statistics Whether statistics are on.
	 *
	 * @return void
	 * @dataProvider dp_test_admin_init
	 */
	public function test_admin_init( bool $statistics ) {
		$times = $statistics ? 1 : 0;

		new WP_List_Table();

		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();
		$subject  = Mockery::mock( FormsPage::class )->makePartial();

		$settings->shouldReceive( 'is_on' )->with( 'statistics' )->andReturn( $statistics );
		$main->shouldReceive( 'settings' )->andReturn( $settings );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'prepare_chart_data' )->times( $times );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );
		WP_Mock::userFunction( 'get_plugins' )->with()->andReturn( [] );

		WP_Mock::userFunction( 'set_screen_options' )->with()->times( $times );

		$subject->admin_init();
	}

	/**
	 * Data provider for test_admin_init().
	 *
	 * @return array
	 */
	public function dp_test_admin_init(): array {
		return [
			'allowed'     => [ true ],
			'not_allowed' => [ false ],
		];
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @param bool $allowed Whether the page is allowed.
	 *
	 * @dataProvider dp_test_admin_enqueue_scripts
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_enqueue_scripts( bool $allowed ) {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min_prefix     = '.min';
		$served         = [ 'some served events' ];
		$times          = $allowed ? 1 : 0;

		$subject = Mockery::mock( FormsPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'min_prefix', $min_prefix );
		$this->set_protected_property( $subject, 'allowed', $allowed );
		$this->set_protected_property( $subject, 'served', $served );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_url, $plugin_version ) {
				if ( 'HCAPTCHA_URL' === $name ) {
					return $plugin_url;
				}

				if ( 'HCAPTCHA_VERSION' === $name ) {
					return $plugin_version;
				}

				return '';
			}
		);

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				FormsPage::HANDLE,
				$plugin_url . "/assets/css/forms$min_prefix.css",
				[ PluginSettingsBase::PREFIX . '-' . SettingsBase::HANDLE ],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				'chart',
				$plugin_url . '/assets/lib/chart.umd.min.js',
				[],
				'v4.4.2',
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				'chart-adapter-date-fns',
				$plugin_url . '/assets/lib/chartjs-adapter-date-fns.bundle.min.js',
				[ 'chart' ],
				'v3.0.0',
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				FormsPage::HANDLE,
				$plugin_url . "/assets/js/forms$min_prefix.js",
				[ 'chart', 'chart-adapter-date-fns' ],
				$plugin_version,
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				FormsPage::HANDLE,
				FormsPage::OBJECT,
				[
					'served'      => $served,
					'servedLabel' => __( 'Served', 'hcaptcha-for-forms-and-more' ),
				]
			)
			->times( $times );

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Data provider for test_admin_enqueue_scripts().
	 *
	 * @return array
	 */
	public function dp_test_admin_enqueue_scripts(): array {
		return [
			[ true ],
			[ false ],
		];
	}

	/**
	 * Test section_callback().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_section_callback() {
		$expected = '		<h2>
			Forms		</h2>
				<div id="hcaptcha-forms-chart">
			<canvas id="formsChart" aria-label="The hCaptcha Forms Chart" role="img">
				<p>
					Your browser does not support the canvas element.				</p>
			</canvas>
		</div>
		<div id="hcaptcha-forms-wrap">
					</div>
		';

		$list_table = Mockery::mock( FormsTable::class )->makePartial();
		$subject    = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$list_table->shouldReceive( 'display' )->once();
		$this->set_protected_property( $subject, 'allowed', true );
		$this->set_protected_property( $subject, 'list_table', $list_table );

		ob_start();
		$subject->section_callback( [ 'id' => 'some id' ] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test section_callback() when not allowed.
	 *
	 * @noinspection HtmlUnknownTarget
	 */
	public function test_section_callback_when_not_allowed() {
		$expected = '		<h2>
			Forms		</h2>
					<div class="hcaptcha-forms-sample-bg"></div>

			<div class="hcaptcha-forms-sample-text">
				<p>It is an example of the Forms page.</p>
				<p>Want to see forms statistics? Please turn on the <a href="options-general.php?page=hcaptcha&tab=general#statistics_1" target="_blank">Statistics switch</a> on the General settings page.</p>
			</div>
			';

		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		WP_Mock::passthruFunction( 'admin_url' );
		WP_Mock::passthruFunction( 'wp_kses_post' );

		ob_start();
		$subject->section_callback( [ 'id' => 'some id' ] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test prepare_chart_data().
	 *
	 * @param array $items    Items.
	 * @param array $expected Expected.
	 *
	 * @dataProvider dp_test_prepare_chart_data
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_prepare_chart_data( array $items, array $expected ) {
		$gmt_offset = 3.0;

		$list_table = Mockery::mock( FormsTable::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject    = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$list_table->shouldReceive( 'prepare_items' )->once();
		$this->set_protected_property( $list_table, 'served', $items );
		$this->set_protected_property( $subject, 'list_table', $list_table );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				if ( 'MINUTE_IN_SECONDS' === $name ) {
					return 60;
				}

				if ( 'HOUR_IN_SECONDS' === $name ) {
					return 3600;
				}

				if ( 'DAY_IN_SECONDS' === $name ) {
					return 86400;
				}

				return null;
			}
		);

		WP_Mock::userFunction( 'get_option' )->with( 'gmt_offset' )->andReturn( $gmt_offset );
		WP_Mock::userFunction( 'wp_date' )->andReturnUsing(
			function ( $format, $timestamp ) use ( $gmt_offset ) {
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				return date( $format, $timestamp + $gmt_offset * 3600 );
			}
		);

		$method = 'prepare_chart_data';
		$subject->$method();

		self::assertSame( $expected['served'], $this->get_protected_property( $subject, 'served' ) );
	}

	/**
	 * Data provider for test_prepare_chart_data().
	 *
	 * @return array
	 */
	public function dp_test_prepare_chart_data(): array {
		return [
			'many items' => [
				'served'   => [
					(object) [ 'date_gmt' => '2024-03-03 12:03:21' ],
					(object) [ 'date_gmt' => '2024-03-03 12:03:26' ],
					(object) [ 'date_gmt' => '2024-03-03 12:03:58' ],
					(object) [ 'date_gmt' => '2024-03-03 12:05:13' ],
					(object) [ 'date_gmt' => '2024-03-03 12:05:33' ],
					(object) [ 'date_gmt' => '2024-03-03 12:06:29' ],
					(object) [ 'date_gmt' => '2024-03-03 12:07:11' ],
					(object) [ 'date_gmt' => '2024-03-03 12:07:34' ],
					(object) [ 'date_gmt' => '2024-03-03 12:08:10' ],
					(object) [ 'date_gmt' => '2024-03-03 12:08:20' ],
					(object) [ 'date_gmt' => '2024-03-03 12:08:37' ],
					(object) [ 'date_gmt' => '2024-03-03 12:12:13' ],
					(object) [ 'date_gmt' => '2024-03-03 12:34:21' ],
					(object) [ 'date_gmt' => '2024-03-03 12:40:01' ],
					(object) [ 'date_gmt' => '2024-03-05 20:47:30' ],
					(object) [ 'date_gmt' => '2024-03-05 20:47:37' ],
					(object) [ 'date_gmt' => '2024-03-05 20:47:47' ],
					(object) [ 'date_gmt' => '2024-03-05 20:48:53' ],
					(object) [ 'date_gmt' => '2024-03-05 20:49:00' ],
					(object) [ 'date_gmt' => '2024-03-05 20:49:10' ],
					(object) [ 'date_gmt' => '2024-03-24 13:15:42' ],
					(object) [ 'date_gmt' => '2024-03-24 13:19:48' ],
					(object) [ 'date_gmt' => '2024-03-24 13:20:14' ],
					(object) [ 'date_gmt' => '2024-03-24 13:22:40' ],
					(object) [ 'date_gmt' => '2024-03-24 14:31:28' ],
					(object) [ 'date_gmt' => '2024-03-24 14:34:12' ],
					(object) [ 'date_gmt' => '2024-03-24 14:35:10' ],
					(object) [ 'date_gmt' => '2024-03-24 14:36:29' ],
					(object) [ 'date_gmt' => '2024-03-24 14:36:50' ],
					(object) [ 'date_gmt' => '2024-03-24 14:45:09' ],
					(object) [ 'date_gmt' => '2024-03-24 14:45:49' ],
					(object) [ 'date_gmt' => '2024-03-24 14:46:08' ],
					(object) [ 'date_gmt' => '2024-03-24 15:01:50' ],
					(object) [ 'date_gmt' => '2024-03-24 15:08:35' ],
					(object) [ 'date_gmt' => '2024-03-24 15:09:21' ],
					(object) [ 'date_gmt' => '2024-03-24 15:09:56' ],
					(object) [ 'date_gmt' => '2024-03-24 15:10:12' ],
					(object) [ 'date_gmt' => '2024-03-24 15:11:00' ],
					(object) [ 'date_gmt' => '2024-03-24 15:13:00' ],
					(object) [ 'date_gmt' => '2024-03-24 15:13:09' ],
					(object) [ 'date_gmt' => '2024-03-24 15:38:09' ],
					(object) [ 'date_gmt' => '2024-03-24 15:38:39' ],
					(object) [ 'date_gmt' => '2024-03-24 15:53:53' ],
					(object) [ 'date_gmt' => '2024-03-24 15:56:26' ],
					(object) [ 'date_gmt' => '2024-03-24 16:00:53' ],
					(object) [ 'date_gmt' => '2024-03-24 16:15:57' ],
					(object) [ 'date_gmt' => '2024-03-24 16:20:05' ],
					(object) [ 'date_gmt' => '2024-03-24 16:20:59' ],
					(object) [ 'date_gmt' => '2024-03-24 16:21:17' ],
					(object) [ 'date_gmt' => '2024-03-24 16:21:38' ],
					(object) [ 'date_gmt' => '2024-03-24 16:22:21' ],
					(object) [ 'date_gmt' => '2024-03-24 16:22:30' ],
					(object) [ 'date_gmt' => '2024-03-24 16:23:03' ],
					(object) [ 'date_gmt' => '2024-03-24 16:23:15' ],
					(object) [ 'date_gmt' => '2024-03-24 16:26:16' ],
					(object) [ 'date_gmt' => '2024-03-24 16:26:45' ],
					(object) [ 'date_gmt' => '2024-03-24 16:26:50' ],
					(object) [ 'date_gmt' => '2024-03-24 16:27:48' ],
					(object) [ 'date_gmt' => '2024-03-24 18:54:20' ],
					(object) [ 'date_gmt' => '2024-03-24 18:54:41' ],
					(object) [ 'date_gmt' => '2024-03-24 19:44:19' ],
					(object) [ 'date_gmt' => '2024-03-24 19:45:33' ],
					(object) [ 'date_gmt' => '2024-03-24 19:45:39' ],
					(object) [ 'date_gmt' => '2024-03-24 19:55:04' ],
					(object) [ 'date_gmt' => '2024-03-24 19:55:24' ],
					(object) [ 'date_gmt' => '2024-03-24 19:56:46' ],
					(object) [ 'date_gmt' => '2024-03-24 20:01:25' ],
					(object) [ 'date_gmt' => '2024-03-24 20:01:28' ],
					(object) [ 'date_gmt' => '2024-03-24 20:02:30' ],
					(object) [ 'date_gmt' => '2024-03-24 20:03:13' ],
					(object) [ 'date_gmt' => '2024-03-24 20:03:41' ],
					(object) [ 'date_gmt' => '2024-03-25 15:32:25' ],
					(object) [ 'date_gmt' => '2024-03-25 15:32:59' ],
					(object) [ 'date_gmt' => '2024-03-25 15:37:45' ],
					(object) [ 'date_gmt' => '2024-03-25 15:37:57' ],
					(object) [ 'date_gmt' => '2024-03-25 15:38:01' ],
					(object) [ 'date_gmt' => '2024-03-25 15:56:45' ],
					(object) [ 'date_gmt' => '2024-03-25 16:16:20' ],
					(object) [ 'date_gmt' => '2024-03-25 16:51:01' ],
					(object) [ 'date_gmt' => '2024-03-25 16:52:15' ],
					(object) [ 'date_gmt' => '2024-03-25 19:03:09' ],
					(object) [ 'date_gmt' => '2024-03-25 19:03:14' ],
					(object) [ 'date_gmt' => '2024-03-25 19:03:34' ],
					(object) [ 'date_gmt' => '2024-03-25 19:06:31' ],
					(object) [ 'date_gmt' => '2024-03-25 19:17:26' ],
					(object) [ 'date_gmt' => '2024-03-25 19:18:15' ],
					(object) [ 'date_gmt' => '2024-03-25 19:36:05' ],
					(object) [ 'date_gmt' => '2024-03-25 19:36:36' ],
					(object) [ 'date_gmt' => '2024-03-25 19:37:16' ],
					(object) [ 'date_gmt' => '2024-03-25 19:38:07' ],
					(object) [ 'date_gmt' => '2024-03-25 19:38:27' ],
					(object) [ 'date_gmt' => '2024-03-25 19:38:47' ],
					(object) [ 'date_gmt' => '2024-03-25 21:07:32' ],
					(object) [ 'date_gmt' => '2024-03-26 19:17:41' ],
					(object) [ 'date_gmt' => '2024-03-26 19:17:43' ],
					(object) [ 'date_gmt' => '2024-03-26 19:17:59' ],
					(object) [ 'date_gmt' => '2024-03-26 19:18:53' ],
					(object) [ 'date_gmt' => '2024-03-26 19:28:32' ],
					(object) [ 'date_gmt' => '2024-03-26 19:29:08' ],
					(object) [ 'date_gmt' => '2024-03-26 19:29:44' ],
					(object) [ 'date_gmt' => '2024-03-26 19:48:41' ],
					(object) [ 'date_gmt' => '2024-03-29 11:01:04' ],
					(object) [ 'date_gmt' => '2024-03-29 11:01:34' ],
					(object) [ 'date_gmt' => '2024-03-29 11:06:32' ],
					(object) [ 'date_gmt' => '2024-03-29 11:08:14' ],
					(object) [ 'date_gmt' => '2024-03-29 11:08:38' ],
					(object) [ 'date_gmt' => '2024-03-30 16:33:00' ],
					(object) [ 'date_gmt' => '2024-03-30 16:34:16' ],
					(object) [ 'date_gmt' => '2024-03-30 17:00:19' ],
					(object) [ 'date_gmt' => '2024-03-30 17:00:46' ],
					(object) [ 'date_gmt' => '2024-03-30 17:01:12' ],
					(object) [ 'date_gmt' => '2024-04-01 20:11:46' ],
					(object) [ 'date_gmt' => '2024-04-01 20:12:32' ],
					(object) [ 'date_gmt' => '2024-04-01 20:12:43' ],
					(object) [ 'date_gmt' => '2024-04-01 20:47:17' ],
					(object) [ 'date_gmt' => '2024-04-01 20:47:35' ],
					(object) [ 'date_gmt' => '2024-04-01 20:49:06' ],
				],
				'expected' => [
					'served' => [
						'2024-03-03' => 14,
						'2024-03-05' => 6,
						'2024-03-24' => 51,
						'2024-03-25' => 21,
						'2024-03-26' => 9,
						'2024-03-29' => 5,
						'2024-03-30' => 5,
						'2024-04-01' => 6,
					],
				],
			],
		];
	}

	/**
	 * Test prepare_chart_data() when no items.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_prepare_chart_data_when_no_items() {
		$items = [];

		$list_table = Mockery::mock( FormsTable::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject    = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$list_table->shouldReceive( 'prepare_items' )->once();
		$this->set_protected_property( $list_table, 'items', $items );
		$this->set_protected_property( $subject, 'list_table', $list_table );

		$method = 'prepare_chart_data';
		$subject->$method();

		self::assertSame( [], $this->get_protected_property( $subject, 'served' ) );
	}
}
