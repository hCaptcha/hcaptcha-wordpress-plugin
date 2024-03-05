<?php
/**
 * EventsInfo class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Events\ListTable;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class EventsInfo
 *
 * Settings page "Events".
 */
class Events extends PluginSettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-events';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaEventsObject';

	/**
	 * ListTable instance.
	 *
	 * @var ListTable
	 */
	private $list_table;

	/**
	 * Passed events.
	 *
	 * @var array
	 */
	private $passed;

	/**
	 * Failed events.
	 *
	 * @var array
	 */
	private $failed;

	/**
	 * Chart time unit.
	 *
	 * @var string
	 */
	private $unit;

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Events', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'events';
	}

	/**
	 * Get tab name.
	 *
	 * @return string
	 */
	protected function tab_name(): string {
		return 'events';
	}

	/**
	 * Init class hooks.
	 */
	protected function init_hooks() {
		parent::init_hooks();

		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Admin init.
	 *
	 * @return void
	 */
	public function admin_init() {
		$this->list_table = new ListTable();

		$this->list_table->prepare_items();

		$this->prepare_chart_data();
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script(
			'chart',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/chart.umd.min.js',
			[],
			'v4.4.2',
			true
		);

		wp_enqueue_script(
			'chart-adapter-date-fns',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/chartjs-adapter-date-fns.bundle.min.js',
			[ 'chart' ],
			'v3.0.0',
			true
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/events$this->min_prefix.js",
			[ 'chart', 'chart-adapter-date-fns' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'passed' => $this->passed,
				'failed' => $this->failed,
				'unit'   => $this->unit,
			]
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/events$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ) {
		?>
		<h2>
			<?php echo esc_html( $this->page_title() ); ?>
		</h2>
		<div id="hcaptcha-events-chart">
			<canvas id="eventsChart" aria-label="The hCaptcha Events Chart" role="img">
				<p>
					<?php esc_html_e( 'Your browser does not support the canvas element.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>
			</canvas>
		</div>
		<div id="hcaptcha-events-wrap">
			<?php
			$this->list_table->display();
			?>
		</div>
		<?php
	}

	/**
	 * Prepare chart data.
	 *
	 * @return void
	 */
	private function prepare_chart_data() {
		$this->passed = [];
		$this->failed = [];

		if ( ! $this->list_table->items ) {
			return;
		}

		$gmt_offset = (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$max_time   = 0;
		$min_time   = PHP_INT_MAX;

		foreach ( $this->list_table->items as $item ) {
			$time     = strtotime( $item->date_gmt ) + $gmt_offset;
			$max_time = max( $time, $max_time );
			$min_time = min( $time, $min_time );
		}

		$time_diff = $max_time - $min_time;

		$time_units = [
			[ 1, 'second' ],
			[ MINUTE_IN_SECONDS, 'minute' ],
			[ HOUR_IN_SECONDS, 'hour' ],
			[ DAY_IN_SECONDS, 'day' ],
			[ WEEK_IN_SECONDS, 'week' ],
			[ MONTH_IN_SECONDS, 'month' ],
			[ YEAR_IN_SECONDS, 'year' ],
		];

		foreach ( $time_units as $index => $time_unit ) {
			$i          = max( 0, $index - 2 );
			$this->unit = $time_units[ $i ][1];

			if ( $time_diff < $time_unit[0] ) {
				break;
			}
		}

		if ( $time_diff < MINUTE_IN_SECONDS ) {
			$date_format = 'Y-m-d H:i:s';
		} else {
			$date_format = 'Y-m-d H:i';
		}

		foreach ( $this->list_table->items as $item ) {
			$time_gmt = strtotime( $item->date_gmt );

			$date    = wp_date( $date_format, $time_gmt );
			$success = '[]' === $item->error_codes;

			$this->passed[ $date ] = $this->passed[ $date ] ?? 0;
			$this->failed[ $date ] = $this->failed[ $date ] ?? 0;

			if ( $success ) {
				++$this->passed[ $date ];
			} else {
				++$this->failed[ $date ];
			}
		}
	}
}
