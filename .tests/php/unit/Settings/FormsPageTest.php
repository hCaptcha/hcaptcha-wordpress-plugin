<?php
/**
 * FormsPageTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use DateTimeZone;
use HCaptcha\Admin\Events\Events;
use HCaptcha\Admin\Events\FormsTable;
use HCaptcha\Main;
use HCaptcha\Settings\FormsPage;
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
 * Class FormsPageTest
 *
 * @group settings
 * @group settings-forms-page
 */
class FormsPageTest extends HCaptchaTestCase {

	/**
	 * Tear down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		parent::tearDown();
	}

	/**
	 * Test page_title().
	 */
	public function test_page_title(): void {
		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'page_title';
		self::assertSame( 'Forms', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title(): void {
		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'section_title';
		self::assertSame( 'forms', $subject->$method() );
	}

	/**
	 * Test tab_name().
	 */
	public function test_tab_name(): void {
		$subject = Mockery::mock( FormsPage::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'tab_name';
		self::assertSame( 'Forms', $subject->$method() );
	}

	/**
	 * Test init_hooks().
	 *
	 * @return void
	 */
	public function test_init_hooks(): void {
		$subject = Mockery::mock( FormsPage::class )->makePartial();
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
	 *
	 * @return void
	 * @dataProvider dp_test_admin_init
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_init( bool $statistics ): void {
		$times       = $statistics ? 1 : 0;
		$option_page = 'hcaptcha-forms';
		$parent_slug = '';
		$page_hook   = 'hcaptcha_page_' . $parent_slug . $option_page;

		new WP_List_Table();

		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();
		$subject  = Mockery::mock( FormsPage::class )->makePartial();

		$settings->shouldReceive( 'is_on' )->with( 'statistics' )->andReturn( $statistics );
		$main->shouldReceive( 'settings' )->andReturn( $settings );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'option_page' )->times( $times )->andReturn( $option_page );
		$subject->shouldReceive( 'prepare_chart_data' )->times( $times );

		$this->set_protected_property( $subject, 'parent_slug', $parent_slug );

		WP_Mock::userFunction( 'hcaptcha' )->with()->andReturn( $main );
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
	public function test_admin_enqueue_scripts( bool $allowed ): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min_suffix     = '.min';
		$served         = [ 'some served events' ];
		$unit           = 'hour';
		$language_code  = 'en';
		$times          = $allowed ? 1 : 0;
		$nonce          = 'some nonce';
		$transient      = 'some message';

		$subject = Mockery::mock( FormsPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'min_suffix', $min_suffix );
		$this->set_protected_property( $subject, 'allowed', $allowed );
		$this->set_protected_property( $subject, 'served', $served );
		$this->set_protected_property( $subject, 'unit', $unit );
		$subject->shouldReceive( 'get_clean_transient' )->andReturn( $transient );

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
				$plugin_url . "/assets/css/forms$min_suffix.css",
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
					'noAction'  => 'Please select a bulk action.',
					'noItems'   => 'Please select at least one item to perform this action on.',
					'DoingBulk' => 'Doing bulk action...',
					'delimiter' => ListPageBase::TIMESPAN_DELIMITER,
					'locale'    => $language_code,
				]
			)
			->times( $times );

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				FormsPage::HANDLE,
				$plugin_url . "/assets/js/forms$min_suffix.js",
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
					'ajaxUrl'     => 'admin-ajax.php',
					'bulkAction'  => FormsPage::BULK_ACTION,
					'bulkNonce'   => $nonce,
					'bulkMessage' => $transient,
					'served'      => $served,
					'servedLabel' => __( 'Served', 'hcaptcha-for-forms-and-more' ),
					'unit'        => $unit,
				]
			)
			->times( $times );

		WP_Mock::passthruFunction( 'admin_url' );
		WP_Mock::userFunction( 'wp_create_nonce' )->andReturn( $nonce );
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
	public function test_section_callback(): void {
		$served_limit = Events::SERVED_LIMIT;
		$datepicker   = '<div class="hcaptcha-filter"></div>';
		$expected     = '		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					Forms				</h2>
			</div>
			' . $datepicker . '		</div>
				<div id="hcaptcha-message"></div>
				<div id="hcaptcha-forms-chart">
			<canvas id="formsChart" aria-label="The hCaptcha Forms Chart" role="img">
				<p>
					Your browser does not support the canvas element.				</p>
			</canvas>
				<div id="hcaptcha-chart-message">The chart is limited to displaying a maximum of ' . $served_limit . ' elements.</div>		</div>
		<div id="hcaptcha-forms-wrap">
					</div>
		';

		$list_table         = Mockery::mock( FormsTable::class )->makePartial();
		$list_table->served = array_fill( 0, $served_limit, 'some served event' );

		$subject = Mockery::mock( FormsPage::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'date_picker_display' )->andReturnUsing(
			static function () use ( $datepicker ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $datepicker;
			}
		);

		WP_Mock::passthruFunction( 'number_format_i18n' );
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
	public function test_section_callback_when_not_allowed(): void {
		$expected = '		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					Forms				</h2>
			</div>
					</div>
				<div id="hcaptcha-message"></div>
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
	public function test_prepare_chart_data( array $items, array $expected ): void {
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
	public function test_prepare_chart_data_when_no_items(): void {
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

	/**
	 * Test delete_events().
	 *
	 * @return void
	 */
	public function test_delete_events(): void {
		global $wpdb;

		$ids          = [
			[
				'source' => 'WordPress',
				'formId' => 'login',
			],
			[
				'source' => 'Contact Form 7',
				'formId' => '177',
			],
		];
		$dates        = [
			'2025-01-24',
			'2025-02-23',
		];
		$args         = [
			'ids'   => $ids,
			'dates' => $dates,
		];
		$where_clause = '((source = %s AND form_id = %s) OR (source = %s AND form_id = %s)) AND date_gmt BETWEEN %s AND %s';
		$prefix       = 'wp_';
		$table_name   = 'hcaptcha_events';
		$sql          = "DELETE FROM $prefix$table_name WHERE $where_clause";
		$prepared     = "DELETE FROM $prefix$table_name WHERE ((source = '[\"WordPress\"]' AND form_id = 'login') OR (source = '[\"Contact Form 7\"]' AND form_id = '177')) AND date_gmt BETWEEN '2025-01-24 00:00:00' AND '2025-02-23 23:59:59'";
		$result       = count( $ids );

		WP_Mock::passthruFunction( 'get_gmt_from_date' );
		WP_Mock::userFunction( 'wp_timezone' )->with()->andReturn( new DateTimeZone( 'UTC' ) );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb         = Mockery::mock( 'WPDB' );
		$wpdb->prefix = $prefix;
		$wpdb->shouldReceive( 'prepare' )
			->with( $sql, 'WordPress', 'login', 'Contact Form 7', '177', '2025-01-24 00:00:00', '2025-02-23 23:59:59' )
			->andReturn( $prepared );
		$wpdb->shouldReceive( 'query' )->with( $prepared )->andReturn( $result );

		$subject = Mockery::mock( FormsPage::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		self::assertFalse( $subject->delete_events( [] ) );
		self::assertTrue( $subject->delete_events( $args ) );
	}
}
