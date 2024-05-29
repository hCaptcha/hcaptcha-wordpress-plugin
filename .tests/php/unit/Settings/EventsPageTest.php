<?php
/**
 * EventsPageTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Admin\Events\EventsTable;
use HCaptcha\Main;
use HCaptcha\Settings\EventsPage;
use HCaptcha\Settings\ListPageBase;
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
 * Class EventsPageTest
 *
 * @group settings
 * @group settings-events-page
 */
class EventsPageTest extends HCaptchaTestCase {

	/**
	 * Test page_title().
	 */
	public function test_page_title() {
		$subject = Mockery::mock( EventsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'page_title';
		self::assertSame( 'Events', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title() {
		$subject = Mockery::mock( EventsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'section_title';
		self::assertSame( 'events', $subject->$method() );
	}

	/**
	 * Test tab_name().
	 */
	public function test_tab_name() {
		$subject = Mockery::mock( EventsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'tab_name';
		self::assertSame( 'Events', $subject->$method() );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks() {
		$subject = Mockery::mock( EventsPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_tab_active' )->with( $subject )->andReturn( false );

		WP_Mock::expectActionAdded( 'admin_init', [ $subject, 'admin_init' ] );

		$method = 'init_hooks';

		$subject->$method();
	}

	/**
	 * Test admin_init().
	 *
	 * @param bool $statistics Whether statistics are on.
	 * @param bool $is_pro     Whether the plugin is pro.
	 *
	 * @return void
	 * @dataProvider dp_test_admin_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_init( bool $statistics, bool $is_pro ) {
		$times       = $statistics && $is_pro ? 1 : 0;
		$option_page = 'hcaptcha-events';
		$parent_slug = '';
		$page_hook   = 'hcaptcha_page_' . $parent_slug . $option_page;

		new WP_List_Table();

		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();
		$subject  = Mockery::mock( EventsPage::class )->makePartial();

		$settings->shouldReceive( 'is_on' )->with( 'statistics' )->andReturn( $statistics );
		$main->shouldReceive( 'settings' )->andReturn( $settings );
		$settings->shouldReceive( 'is_pro' )->andReturn( $is_pro );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_page' )->times( $times )->andReturn( $option_page );
		$subject->shouldReceive( 'prepare_chart_data' )->times( $times );

		$this->set_protected_property( $subject, 'parent_slug', $parent_slug );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );
		WP_Mock::userFunction( 'get_option' )->with( 'date_format' )->andReturn( 'some date format' );
		WP_Mock::userFunction( 'get_option' )->with( 'time_format' )->andReturn( 'some time format' );
		WP_Mock::userFunction( 'get_plugins' )->with()->andReturn( [] );
		WP_Mock::userFunction( 'get_plugin_page_hook' )->with( $option_page, $parent_slug )->andReturn( $page_hook );

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
			'allowed'                => [ true, true ],
			'statistics_off'         => [ false, true ],
			'pro_off'                => [ true, false ],
			'statistics_and_pro_off' => [ false, false ],
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
		$min_suffix     = '.min';
		$succeed        = [ 'some succeed events' ];
		$failed         = [ 'some failed events' ];
		$unit           = 'day';
		$language_code  = 'en';
		$times          = $allowed ? 1 : 0;

		$subject = Mockery::mock( EventsPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'min_suffix', $min_suffix );
		$this->set_protected_property( $subject, 'allowed', $allowed );
		$this->set_protected_property( $subject, 'succeed', $succeed );
		$this->set_protected_property( $subject, 'failed', $failed );
		$this->set_protected_property( $subject, 'unit', $unit );

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
				EventsPage::HANDLE,
				$plugin_url . "/assets/css/events$min_suffix.css",
				[ PluginSettingsBase::PREFIX . '-' . SettingsBase::HANDLE ],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				ListPageBase::CHART_HANDLE,
				$plugin_url . '/assets/lib/chartjs/chart.umd.min.js',
				[],
				'v4.4.2',
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				'chart-adapter-date-fns',
				$plugin_url . '/assets/lib/chartjs/chartjs-adapter-date-fns.bundle.min.js',
				[ ListPageBase::CHART_HANDLE ],
				'v3.0.0',
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				ListPageBase::FLATPICKR_HANDLE,
				$plugin_url . '/assets/lib/flatpickr/flatpickr.min.css',
				[],
				'4.6.13'
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				ListPageBase::FLATPICKR_HANDLE,
				$plugin_url . '/assets/lib/flatpickr/flatpickr.min.js',
				[],
				'4.6.13',
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				ListPageBase::HANDLE,
				$plugin_url . "/assets/css/settings-list-page-base$min_suffix.css",
				[ ListPageBase::FLATPICKR_HANDLE ],
				$plugin_version
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				ListPageBase::HANDLE,
				$plugin_url . "/assets/js/settings-list-page-base$min_suffix.js",
				[ ListPageBase::FLATPICKR_HANDLE ],
				$plugin_version,
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				ListPageBase::HANDLE,
				ListPageBase::OBJECT,
				[
					'delimiter' => ListPageBase::TIMESPAN_DELIMITER,
					'locale'    => $language_code,
				]
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				EventsPage::HANDLE,
				$plugin_url . "/assets/js/events$min_suffix.js",
				[ 'chart', 'chart-adapter-date-fns' ],
				$plugin_version,
				true
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				EventsPage::HANDLE,
				EventsPage::OBJECT,
				[
					'succeed'      => $succeed,
					'failed'       => $failed,
					'succeedLabel' => __( 'Succeed', 'hcaptcha-for-forms-and-more' ),
					'failedLabel'  => __( 'Failed', 'hcaptcha-for-forms-and-more' ),
					'unit'         => $unit,
				]
			)
			->times( $times );

		WP_Mock::userFunction( 'get_user_locale' )->andReturn( $language_code );

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
		$datepicker = '<div class="hcaptcha-filter"></div>';
		$expected   = '		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					Events				</h2>
			</div>
			' . $datepicker . '		</div>
				<div id="hcaptcha-events-chart">
			<canvas id="eventsChart" aria-label="The hCaptcha Events Chart" role="img">
				<p>
					Your browser does not support the canvas element.				</p>
			</canvas>
		</div>
		<div id="hcaptcha-events-wrap">
					</div>
		';

		$list_table = Mockery::mock( EventsTable::class )->makePartial();
		$subject    = Mockery::mock( EventsPage::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'date_picker_display' )->andReturnUsing(
			static function () use ( $datepicker ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $datepicker;
			}
		);

		WP_Mock::onAction( 'kagg_settings_header' )->with( null )->perform( [ $subject, 'date_picker_display' ] );

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
		$expected = '		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					Events				</h2>
			</div>
					</div>
					<div class="hcaptcha-events-sample-bg"></div>

			<div class="hcaptcha-events-sample-text">
				<p>It is an example of the Events page.</p>
				<p>Want to see events statistics? Please turn on the <a href="options-general.php?page=hcaptcha&tab=general#statistics_1" target="_blank">Statistics switch</a> on the General settings page and upgrade to <a href="https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not" target="_blank">Pro account</a>.</p>
			</div>
			';

		$subject = Mockery::mock( EventsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

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

		$list_table = Mockery::mock( EventsTable::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject    = Mockery::mock( EventsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$list_table->shouldReceive( 'prepare_items' )->once();
		$this->set_protected_property( $list_table, 'items', $items );
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

				if ( 'WEEK_IN_SECONDS' === $name ) {
					return 604800;
				}

				if ( 'MONTH_IN_SECONDS' === $name ) {
					return 2592000;
				}

				if ( 'YEAR_IN_SECONDS' === $name ) {
					return 31536000;
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

		self::assertSame( $expected['succeed'], $this->get_protected_property( $subject, 'succeed' ) );
		self::assertSame( $expected['failed'], $this->get_protected_property( $subject, 'failed' ) );
		self::assertSame( $expected['unit'], $this->get_protected_property( $subject, 'unit' ) );
	}

	/**
	 * Data provider for test_prepare_chart_data().
	 *
	 * @return array
	 */
	public function dp_test_prepare_chart_data(): array {
		return [
			'many items'            => [
				'items'    => [
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-13 08:17:54',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["invalid-or-already-seen-response"]',
						'date_gmt'    => '2024-04-13 08:17:33',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-13 08:17:32',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-04 09:07:48',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["bad-nonce"]',
						'date_gmt'    => '2024-04-04 09:07:10',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-04 09:07:09',
					],
					(object) [
						'source'      => '[]',
						'form_id'     => '0',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 21:34:14',
					],
					(object) [
						'source'      => '[]',
						'form_id'     => '0',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["not-using-dummy-passcode"]',
						'date_gmt'    => '2024-04-01 21:33:44',
					],
					(object) [
						'source'      => '[]',
						'form_id'     => '0',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["not-using-dummy-passcode"]',
						'date_gmt'    => '2024-04-01 21:33:24',
					],
					(object) [
						'source'      => '[]',
						'form_id'     => '0',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 21:32:27',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 21:05:58',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:54:34',
					],
					(object) [
						'source'      => '["essential-addons-for-elementor-lite\\/essential_adons_elementor.php"]',
						'form_id'     => 'register',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:49:06',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:47:52',
					],
					(object) [
						'source'      => '["essential-addons-for-elementor-lite\\/essential_adons_elementor.php"]',
						'form_id'     => 'register',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["empty"]',
						'date_gmt'    => '2024-04-01 20:47:35',
					],
					(object) [
						'source'      => '["essential-addons-for-elementor-lite\\/essential_adons_elementor.php"]',
						'form_id'     => 'register',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:47:17',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:44:13',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["empty"]',
						'date_gmt'    => '2024-04-01 20:44:10',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:41:28',
					],
					(object) [
						'source'      => '["essential-addons-for-elementor-lite\\/essential_adons_elementor.php"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-01 20:12:43',
					],
				],
				'expected' => [
					'succeed' => [
						'2024-04-13' => 2,
						'2024-04-04' => 2,
						'2024-04-02' => 3,
						'2024-04-01' => 7,
					],
					'failed'  => [
						'2024-04-13' => 1,
						'2024-04-04' => 1,
						'2024-04-02' => 2,
						'2024-04-01' => 2,
					],
					'unit'    => 'day',
				],
			],
			'items within a day'    => [
				'items'    => [
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-13 07:00:00',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["invalid-or-already-seen-response"]',
						'date_gmt'    => '2024-04-13 08:17:33',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-13 08:17:32',
					],
				],
				'expected' => [
					'succeed' => [
						'2024-04-13 10:00' => 1,
						'2024-04-13 11:17' => 1,
					],
					'failed'  => [
						'2024-04-13 10:00' => 0,
						'2024-04-13 11:17' => 1,
					],
					'unit'    => 'minute',
				],
			],
			'items within a minute' => [
				'items'    => [
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-13 08:17:54',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '["invalid-or-already-seen-response"]',
						'date_gmt'    => '2024-04-13 08:17:33',
					],
					(object) [
						'source'      => '["WordPress"]',
						'form_id'     => 'login',
						'ip'          => '',
						'user_agent'  => '',
						'error_codes' => '[]',
						'date_gmt'    => '2024-04-13 08:17:32',
					],
				],
				'expected' => [
					'succeed' => [
						'2024-04-13 11:17:54' => 1,
						'2024-04-13 11:17:33' => 0,
						'2024-04-13 11:17:32' => 1,
					],
					'failed'  => [
						'2024-04-13 11:17:54' => 0,
						'2024-04-13 11:17:33' => 1,
						'2024-04-13 11:17:32' => 0,
					],
					'unit'    => 'second',
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

		$list_table = Mockery::mock( EventsTable::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$subject    = Mockery::mock( EventsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$list_table->shouldReceive( 'prepare_items' )->once();
		$this->set_protected_property( $list_table, 'items', $items );
		$this->set_protected_property( $subject, 'list_table', $list_table );

		$method = 'prepare_chart_data';
		$subject->$method();

		self::assertSame( [], $this->get_protected_property( $subject, 'succeed' ) );
		self::assertSame( [], $this->get_protected_property( $subject, 'failed' ) );
	}
}
