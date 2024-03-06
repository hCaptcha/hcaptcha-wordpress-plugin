<?php
/**
 * FormsPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Events\EventsTable;
use HCaptcha\Admin\Events\FormsTable;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class FormsPage
 *
 * Settings page "Forms".
 */
class FormsPage extends PluginSettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-forms';

	/**
	 * Script localization object.
	 */
	const OBJECT = 'HCaptchaFormsObject';

	/**
	 * ListTable instance.
	 *
	 * @var FormsTable
	 */
	private $list_table;

	/**
	 * Served events.
	 *
	 * @var array
	 */
	private $served;

	/**
	 * Chart time unit.
	 *
	 * @var string
	 */
	private $unit;

	/**
	 * Statistics is on.
	 *
	 * @var bool
	 */
	private $statistics = false;

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Forms', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'forms';
	}

	/**
	 * Get tab name.
	 *
	 * @return string
	 */
	protected function tab_name(): string {
		return 'forms';
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
		$this->statistics = hcaptcha()->settings()->is_on( 'statistics' );

		if ( ! $this->statistics ) {
			return;
		}

		$this->list_table = new FormsTable();

		$this->list_table->prepare_items();

		$this->prepare_chart_data();
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/forms$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);

		if ( ! $this->statistics ) {
			return;
		}

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
			constant( 'HCAPTCHA_URL' ) . "/assets/js/forms$this->min_prefix.js",
			[ 'chart', 'chart-adapter-date-fns' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'served'      => $this->served,
				'unit'        => $this->unit,
				'servedLabel' => __( 'Served', 'hcaptcha-for-forms-and-more' ),
			]
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
		<?php

		if ( ! $this->statistics ) {
			$statistics_url = admin_url( 'options-general.php?page=hcaptcha&tab=general#statistics_1' );

			$message = sprintf(
			/* translators: 1: Statistics link. */
				__( 'Want to see forms statistics? Please turn on the %1$s.', 'hcaptcha-for-forms-and-more' ),
				sprintf(
					'<a href="%1$s" target="_blank">%2$s</a>',
					$statistics_url,
					__( 'Statistics switch on the General settings page', 'hcaptcha-for-forms-and-more' )
				)
			);
			?>

			<p><?php echo wp_kses_post( $message ); ?></p>
			<p><?php esc_html_e( 'Below is an example of an active forms page.', 'hcaptcha-for-forms-and-more' ); ?></p>
			<img
					class="hcaptcha-forms-sample"
					src="<?php echo esc_url( HCAPTCHA_URL . '/assets/images/forms-page.jpg' ); ?>"
					alt="Sample Forms Page">
			<?php

			return;
		}

		?>

		<div id="hcaptcha-forms-chart">
			<canvas id="formsChart" aria-label="The hCaptcha Forms Chart" role="img">
				<p>
					<?php esc_html_e( 'Your browser does not support the canvas element.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>
			</canvas>
		</div>
		<div id="hcaptcha-forms-wrap">
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
		$this->served = [];

		if ( ! $this->list_table->served ) {
			return;
		}

		$gmt_offset = (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$max_time   = 0;
		$min_time   = PHP_INT_MAX;

		foreach ( $this->list_table->served as $item ) {
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
			$i          = max( 0, $index - 1 );
			$this->unit = $time_units[ $i ][1];

			if ( $time_diff < $time_unit[0] ) {
				break;
			}
		}

		$date_format = 'Y-m-d';

		foreach ( $this->list_table->served as $item ) {
			$time_gmt              = strtotime( $item->date_gmt );
			$date                  = wp_date( $date_format, $time_gmt );
			$this->served[ $date ] = $this->served[ $date ] ?? 0;

			++$this->served[ $date ];
		}
	}
}
