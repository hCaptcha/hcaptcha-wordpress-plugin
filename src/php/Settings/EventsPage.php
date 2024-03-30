<?php
/**
 * EventsPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Events\EventsTable;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class EventsPage
 *
 * Settings page "Events".
 */
class EventsPage extends PluginSettingsBase {

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
	 * @var EventsTable
	 */
	private $list_table;

	/**
	 * Succeed events.
	 *
	 * @var array
	 */
	private $succeed;

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
	 * The page is allowed to be shown.
	 *
	 * @var bool
	 */
	private $allowed = false;

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
	public function tab_name(): string {
		return 'Events';
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
		$this->allowed = ( hcaptcha()->settings()->is_on( 'statistics' ) && hcaptcha()->is_pro() );

		if ( ! $this->allowed ) {
			return;
		}

		$this->list_table = new EventsTable();

		$this->list_table->prepare_items();

		$this->prepare_chart_data();
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/events$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);

		if ( ! $this->allowed ) {
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
			constant( 'HCAPTCHA_URL' ) . "/assets/js/events$this->min_prefix.js",
			[ 'chart', 'chart-adapter-date-fns' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'succeed'      => $this->succeed,
				'failed'       => $this->failed,
				'unit'         => $this->unit,
				'succeedLabel' => __( 'Succeed', 'hcaptcha-for-forms-and-more' ),
				'failedLabel'  => __( 'Failed', 'hcaptcha-for-forms-and-more' ),
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

		if ( ! $this->allowed ) {
			$statistics_url = admin_url( 'options-general.php?page=hcaptcha&tab=general#statistics_1' );
			$pro_url        = 'https://www.hcaptcha.com/pro?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=not';

			$message = sprintf(
			/* translators: 1: Statistics link, 2: Pro account link. */
				__( 'Want to see events statistics? Please turn on the %1$s on the General settings page and upgrade to %2$s.', 'hcaptcha-for-forms-and-more' ),
				sprintf(
				/* translators: 1: Statistics switch link, 2: Statistics switch text. */
					'<a href="%1$s" target="_blank">%2$s</a>',
					$statistics_url,
					__( 'Statistics switch', 'hcaptcha-for-forms-and-more' )
				),
				sprintf(
				/* translators: 1: Pro account link, 2: Pro account text. */
					'<a href="%1$s" target="_blank">%2$s</a>',
					$pro_url,
					__( 'Pro account', 'hcaptcha-for-forms-and-more' )
				)
			);

			?>
			<div class="hcaptcha-events-sample-bg"></div>

			<div class="hcaptcha-events-sample-text">
				<p><?php esc_html_e( 'It is an example of the Events page.', 'hcaptcha-for-forms-and-more' ); ?></p>
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php

			return;
		}

		?>
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
		$this->succeed = [];
		$this->failed  = [];

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
			$i          = max( 0, $index - 1 );
			$this->unit = $time_units[ $i ][1];

			if ( $time_diff < $time_unit[0] ) {
				break;
			}
		}

		if ( $time_diff < MINUTE_IN_SECONDS ) {
			$date_format = 'Y-m-d H:i:s';
		} elseif ( $time_diff < DAY_IN_SECONDS ) {
			$date_format = 'Y-m-d H:i';
		} else {
			$date_format = 'Y-m-d';
		}

		foreach ( $this->list_table->items as $item ) {
			$time_gmt = strtotime( $item->date_gmt );

			$date    = wp_date( $date_format, $time_gmt );
			$success = '[]' === $item->error_codes;

			$this->succeed[ $date ] = $this->succeed[ $date ] ?? 0;
			$this->failed[ $date ]  = $this->failed[ $date ] ?? 0;

			if ( $success ) {
				++$this->succeed[ $date ];
			} else {
				++$this->failed[ $date ];
			}
		}
	}
}
