<?php
/**
 * EventsPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Admin\Events\EventsTable;
use HCaptcha\Helpers\DB;
use HCaptcha\Helpers\Utils;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class EventsPage.
 *
 * Settings page "Events".
 */
class EventsPage extends ListPageBase {

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'hcaptcha-events';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaEventsObject';

	/**
	 * Bulk ajax action.
	 */
	public const BULK_ACTION = 'hcaptcha-events-bulk';

	/**
	 * ListTable instance.
	 *
	 * @var EventsTable
	 */
	protected EventsTable $list_table;

	/**
	 * Succeed events.
	 *
	 * @var array
	 */
	protected array $succeed = [];

	/**
	 * Failed events.
	 *
	 * @var array
	 */
	protected array $failed = [];

	/**
	 * Dashboard data.
	 *
	 * @var array
	 */
	protected array $dashboard_data = [];

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
	 * Admin init.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		$settings = hcaptcha()->settings();

		$this->allowed = $settings && $settings->is_on( 'statistics' ) && $settings->is_pro();

		if ( ! $this->allowed ) {
			return;
		}

		$this->list_table = new EventsTable( (string) get_plugin_page_hook( $this->option_page(), $this->parent_slug ) );

		$this->prepare_chart_data();
		$this->prepare_dashboard_data();
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/events$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);

		if ( ! $this->allowed ) {
			return;
		}

		parent::admin_enqueue_scripts();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/events$this->min_suffix.js",
			[ 'chart', 'chart-adapter-date-fns' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'bulkAction'   => self::BULK_ACTION,
				'bulkNonce'    => wp_create_nonce( self::BULK_ACTION ),
				'bulkMessage'  => $this->get_clean_transient(),
				'succeed'      => $this->succeed,
				'failed'       => $this->failed,
				'succeedLabel' => __( 'Succeed', 'hcaptcha-for-forms-and-more' ),
				'failedLabel'  => __( 'Failed', 'hcaptcha-for-forms-and-more' ),
				'unit'         => $this->unit,
			]
		);
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 *
	 * @return void
	 * @noinspection HtmlUnknownTarget
	 */
	public function section_callback( array $arguments ): void {
		$this->print_header();

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
			<button
				id="hcaptcha-events-toggle"
				class="button hcaptcha-events-toggle"
				type="button"
				aria-controls="eventsChart hcaptcha-events-dashboard"
				aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Show dashboard', 'hcaptcha-for-forms-and-more' ); ?>"
				title="<?php esc_attr_e( 'Show dashboard', 'hcaptcha-for-forms-and-more' ); ?>"
				data-dashboard-icon="dashicons-dashboard"
				data-chart-icon="dashicons-chart-bar"
				data-show-dashboard-label="<?php esc_attr_e( 'Show dashboard', 'hcaptcha-for-forms-and-more' ); ?>"
				data-show-chart-label="<?php esc_attr_e( 'Show chart', 'hcaptcha-for-forms-and-more' ); ?>">
				<span
					class="dashicons dashicons-dashboard hcaptcha-events-toggle-icon"
					aria-hidden="true"></span>
			</button>
			<div class="hcaptcha-events-chart-panel">
				<canvas id="eventsChart" aria-label="The hCaptcha Events Chart" role="img">
					<p>
						<?php esc_html_e( 'Your browser does not support the canvas element.', 'hcaptcha-for-forms-and-more' ); ?>
					</p>
				</canvas>
			</div>
			<?php $this->render_dashboard(); ?>
		</div>
		<div id="hcaptcha-events-wrap">
			<?php
			$this->list_table->views();
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
	protected function prepare_chart_data(): void {
		$this->succeed = [];
		$this->failed  = [];

		$this->list_table->prepare_items();

		if ( ! $this->list_table->items ) {
			return;
		}

		$date_format = $this->get_date_format( $this->list_table->items );

		foreach ( $this->list_table->items as $item ) {
			$time_gmt = strtotime( $item->date_gmt );
			$date     = wp_date( $date_format, $time_gmt );

			$this->succeed[ $date ] = $this->succeed[ $date ] ?? 0;
			$this->failed[ $date ]  = $this->failed[ $date ] ?? 0;

			if ( '[]' === $item->error_codes ) {
				++$this->succeed[ $date ];
			} else {
				++$this->failed[ $date ];
			}
		}
	}

	/**
	 * Prepare dashboard data.
	 *
	 * @return void
	 */
	protected function prepare_dashboard_data(): void {
		$this->dashboard_data = Events::get_dashboard_data(
			[
				'dates'  => $this->get_request_dates(),
				'status' => $this->get_request_status(),
			]
		);
	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	private function render_dashboard(): void {
		$dashboard  = $this->get_dashboard_data();
		$totals     = $dashboard['totals'];
		$risk       = $dashboard['risk'];
		$total      = (int) $totals['total'];
		$succeed    = (int) $totals['succeed'];
		$failed     = (int) $totals['failed'];
		$risk_level = $this->get_risk_level_label( $risk['level'] );

		?>
		<div id="hcaptcha-events-dashboard" class="hcaptcha-events-dashboard" hidden>
			<div class="hcaptcha-events-dashboard-metrics">
				<?php
				$this->render_dashboard_metric(
					__( 'Total events', 'hcaptcha-for-forms-and-more' ),
					number_format_i18n( $total ),
					__( 'Selected period', 'hcaptcha-for-forms-and-more' )
				);
				$this->render_dashboard_metric(
					__( 'Succeeded', 'hcaptcha-for-forms-and-more' ),
					$this->format_percent( $this->get_rate( $succeed, $total ) ),
					sprintf(
					/* translators: 1: Number of events. */
						__( '%s events', 'hcaptcha-for-forms-and-more' ),
						number_format_i18n( $succeed )
					),
					'is-success'
				);
				$this->render_dashboard_metric(
					__( 'Failed', 'hcaptcha-for-forms-and-more' ),
					$this->format_percent( $this->get_rate( $failed, $total ) ),
					sprintf(
					/* translators: 1: Number of events. */
						__( '%s events', 'hcaptcha-for-forms-and-more' ),
						number_format_i18n( $failed )
					),
					'is-failed'
				);
				$this->render_dashboard_metric(
					__( 'Risk level', 'hcaptcha-for-forms-and-more' ),
					$risk_level,
					sprintf(
					/* translators: 1: Risk score. */
						__( 'Score %s/100', 'hcaptcha-for-forms-and-more' ),
						number_format_i18n( (int) $risk['score'] )
					),
					'is-risk-' . $this->get_risk_level_class( (string) $risk['level'] )
				);
				?>
			</div>
			<div class="hcaptcha-events-dashboard-grid">
				<?php
				$this->render_dashboard_top_forms( $dashboard['top_forms'] );
				$this->render_dashboard_top_errors( $dashboard['top_errors'] );
				$this->render_dashboard_peak( $dashboard['peak'] );
				$this->render_dashboard_risk_factors( $dashboard );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render dashboard metric.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $meta  Meta.
	 * @param string $extra_class Extra class.
	 *
	 * @return void
	 */
	private function render_dashboard_metric( string $label, string $value, string $meta = '', string $extra_class = '' ): void {
		$class = trim( 'hcaptcha-events-dashboard-metric ' . $extra_class );

		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<span class="hcaptcha-events-dashboard-metric-label"><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
			<?php if ( $meta ) : ?>
				<span class="hcaptcha-events-dashboard-metric-meta"><?php echo esc_html( $meta ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render dashboard top forms.
	 *
	 * @param array $forms Forms.
	 *
	 * @return void
	 */
	private function render_dashboard_top_forms( array $forms ): void {
		?>
		<section class="hcaptcha-events-dashboard-panel">
			<h3><?php esc_html_e( 'Top forms', 'hcaptcha-for-forms-and-more' ); ?></h3>
			<?php if ( ! $forms ) : ?>
				<p class="hcaptcha-events-dashboard-empty"><?php esc_html_e( 'No forms found.', 'hcaptcha-for-forms-and-more' ); ?></p>
			<?php else : ?>
				<table>
					<thead>
					<tr>
						<th><?php esc_html_e( 'Form', 'hcaptcha-for-forms-and-more' ); ?></th>
						<th><?php esc_html_e( 'Total', 'hcaptcha-for-forms-and-more' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'hcaptcha-for-forms-and-more' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $forms as $form ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $this->get_source_label( (string) $form['source'] ) ); ?></strong>
								<span><?php echo esc_html( (string) $form['form_id'] ); ?></span>
							</td>
							<td><?php echo esc_html( number_format_i18n( (int) $form['total'] ) ); ?></td>
							<td>
								<?php
								echo esc_html(
									sprintf(
									/* translators: 1: Failed event count, 2: Failed percent. */
										__( '%1$s (%2$s)', 'hcaptcha-for-forms-and-more' ),
										number_format_i18n( (int) $form['failed'] ),
										$this->format_percent( (float) $form['failed_rate'] )
									)
								);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render dashboard top errors.
	 *
	 * @param array $errors Errors.
	 *
	 * @return void
	 */
	private function render_dashboard_top_errors( array $errors ): void {
		$error_messages = $errors ? hcap_get_error_messages() : [];

		?>
		<section class="hcaptcha-events-dashboard-panel">
			<h3><?php esc_html_e( 'Top errors', 'hcaptcha-for-forms-and-more' ); ?></h3>
			<?php if ( ! $errors ) : ?>
				<p class="hcaptcha-events-dashboard-empty"><?php esc_html_e( 'No failed events found.', 'hcaptcha-for-forms-and-more' ); ?></p>
			<?php else : ?>
				<ul class="hcaptcha-events-dashboard-list">
					<?php foreach ( $errors as $error ) : ?>
						<?php $code = (string) $error['code']; ?>
						<li>
							<span><?php echo esc_html( $error_messages[ $code ] ?? $code ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( (int) $error['total'] ) ); ?></strong>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render dashboard peak.
	 *
	 * @param array $peak Peak.
	 *
	 * @return void
	 */
	private function render_dashboard_peak( array $peak ): void {
		?>
		<section class="hcaptcha-events-dashboard-panel">
			<h3><?php esc_html_e( 'Peak activity', 'hcaptcha-for-forms-and-more' ); ?></h3>
			<?php if ( ! $peak['total'] ) : ?>
				<p class="hcaptcha-events-dashboard-empty"><?php esc_html_e( 'No events found.', 'hcaptcha-for-forms-and-more' ); ?></p>
			<?php else : ?>
				<div class="hcaptcha-events-dashboard-peak">
					<strong><?php echo esc_html( $this->format_peak_bucket( (string) $peak['bucket'] ) ); ?></strong>
					<span>
						<?php
						echo esc_html(
							sprintf(
							/* translators: 1: Total events, 2: Succeeded events, 3: Failed events. */
								__( '%1$s total, %2$s succeeded, %3$s failed', 'hcaptcha-for-forms-and-more' ),
								number_format_i18n( (int) $peak['total'] ),
								number_format_i18n( (int) $peak['succeed'] ),
								number_format_i18n( (int) $peak['failed'] )
							)
						);
						?>
					</span>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Render dashboard risk factors.
	 *
	 * @param array $dashboard Dashboard data.
	 *
	 * @return void
	 */
	private function render_dashboard_risk_factors( array $dashboard ): void {
		$factors = $this->get_risk_factors( $dashboard );
		$totals  = $dashboard['totals'];

		?>
		<section class="hcaptcha-events-dashboard-panel">
			<h3><?php esc_html_e( 'Risk factors', 'hcaptcha-for-forms-and-more' ); ?></h3>
			<ul class="hcaptcha-events-dashboard-list">
				<?php foreach ( $factors as $factor ) : ?>
					<li><span><?php echo esc_html( $factor ); ?></span></li>
				<?php endforeach; ?>
			</ul>
			<div class="hcaptcha-events-dashboard-signals">
				<span>
					<?php
					echo esc_html(
						$this->get_signal_label(
							__( 'IP', 'hcaptcha-for-forms-and-more' ),
							(int) $totals['ip_total'],
							(int) $totals['unique_ip']
						)
					);
					?>
				</span>
				<span>
					<?php
					echo esc_html(
						$this->get_signal_label(
							__( 'User Agent', 'hcaptcha-for-forms-and-more' ),
							(int) $totals['user_agent_total'],
							(int) $totals['unique_user_agent']
						)
					);
					?>
				</span>
			</div>
		</section>
		<?php
	}

	/**
	 * Get normalized dashboard data.
	 *
	 * @return array
	 */
	private function get_dashboard_data(): array {
		$dashboard = array_merge(
			[
				'totals'     => [],
				'top_forms'  => [],
				'top_errors' => [],
				'peak'       => [],
				'risk'       => [],
			],
			$this->dashboard_data
		);

		$dashboard['totals'] = array_merge(
			[
				'total'             => 0,
				'succeed'           => 0,
				'failed'            => 0,
				'ip_total'          => 0,
				'unique_ip'         => 0,
				'user_agent_total'  => 0,
				'unique_user_agent' => 0,
			],
			(array) $dashboard['totals']
		);
		$dashboard['peak']   = array_merge(
			[
				'bucket'       => '',
				'total'        => 0,
				'succeed'      => 0,
				'failed'       => 0,
				'bucket_count' => 0,
			],
			(array) $dashboard['peak']
		);
		$dashboard['risk']   = array_merge(
			[
				'score'      => 0,
				'level'      => 'low',
				'components' => [],
			],
			(array) $dashboard['risk']
		);

		return $dashboard;
	}

	/**
	 * Get request dates.
	 *
	 * @return array
	 */
	private function get_request_dates(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$date = isset( $_GET['date'] )
			// We need filter_input here to keep the delimiter intact.
			? filter_input( INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dates = explode( self::TIMESPAN_DELIMITER, (string) $date );

		return array_filter( array_map( 'trim', $dates ) );
	}

	/**
	 * Get request status.
	 *
	 * @return string
	 */
	private function get_request_status(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['event_status'] ) ? sanitize_key( wp_unslash( $_GET['event_status'] ) ) : Events::STATUS_ACTIVE;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return in_array( $status, [ Events::STATUS_ACTIVE, Events::STATUS_TRASH ], true ) ? $status : Events::STATUS_ACTIVE;
	}

	/**
	 * Get dashboard rate.
	 *
	 * @param int $value Value.
	 * @param int $total Total.
	 *
	 * @return float
	 */
	private function get_rate( int $value, int $total ): float {
		if ( ! $total ) {
			return 0;
		}

		return round( $value / $total * 100, 1 );
	}

	/**
	 * Format percent.
	 *
	 * @param float $value Value.
	 *
	 * @return string
	 */
	private function format_percent( float $value ): string {
		return number_format_i18n( $value, 1 ) . '%';
	}

	/**
	 * Get risk level label.
	 *
	 * @param string $level Level.
	 *
	 * @return string
	 */
	private function get_risk_level_label( string $level ): string {
		$labels = [
			'low'      => __( 'Low', 'hcaptcha-for-forms-and-more' ),
			'elevated' => __( 'Elevated', 'hcaptcha-for-forms-and-more' ),
			'high'     => __( 'High', 'hcaptcha-for-forms-and-more' ),
			'critical' => __( 'Critical', 'hcaptcha-for-forms-and-more' ),
		];

		return $labels[ $level ] ?? $labels['low'];
	}

	/**
	 * Get risk level class.
	 *
	 * @param string $level Level.
	 *
	 * @return string
	 */
	private function get_risk_level_class( string $level ): string {
		return in_array(
			$level,
			[ 'low', 'elevated', 'high', 'critical' ],
			true
		) ? $level : 'low';
	}

	/**
	 * Get source label.
	 *
	 * @param string $source Source.
	 *
	 * @return string
	 */
	private function get_source_label( string $source ): string {
		$source_arr = Utils::json_decode_arr( $source );
		$plugins    = get_plugins();

		foreach ( $source_arr as &$slug ) {
			if ( 'WordPress' === $slug || false === strpos( (string) $slug, '/' ) ) {
				continue;
			}

			$slug = isset( $plugins[ $slug ] ) ? $plugins[ $slug ]['Name'] : $slug;
		}

		unset( $slug );

		$label = implode( ', ', $source_arr );

		return $label ?: __( 'Unknown', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Format peak bucket.
	 *
	 * @param string $bucket Bucket.
	 *
	 * @return string
	 */
	private function format_peak_bucket( string $bucket ): string {
		$timestamp = strtotime( $bucket );

		if ( false === $timestamp ) {
			return $bucket;
		}

		$date_format = get_option( 'date_format' );
		$format      = false === strpos( $bucket, ' ' ) ? $date_format : $date_format . ' ' . get_option( 'time_format' );

		return wp_date( $format, $timestamp );
	}

	/**
	 * Get risk factors.
	 *
	 * @param array $dashboard Dashboard data.
	 *
	 * @return array
	 */
	private function get_risk_factors( array $dashboard ): array {
		$risk       = $dashboard['risk'];
		$components = array_merge(
			[
				'failed_rate'            => 0,
				'spike_ratio'            => 0,
				'ip_repeat_rate'         => 0,
				'user_agent_repeat_rate' => 0,
				'error_concentration'    => 0,
			],
			(array) $risk['components']
		);
		$factors    = [];

		if ( $components['failed_rate'] >= 30 ) {
			$factors[] = sprintf(
			/* translators: 1: Failed percent. */
				__( 'Failed rate is %s.', 'hcaptcha-for-forms-and-more' ),
				$this->format_percent( (float) $components['failed_rate'] )
			);
		}

		if ( $components['spike_ratio'] >= 2 ) {
			$factors[] = sprintf(
			/* translators: 1: Spike ratio. */
				__( 'Peak activity is %sx above average.', 'hcaptcha-for-forms-and-more' ),
				number_format_i18n( (float) $components['spike_ratio'], 1 )
			);
		}

		if ( $components['ip_repeat_rate'] >= 50 ) {
			$factors[] = sprintf(
			/* translators: 1: Repeat percent. */
				__( 'Repeated IP signal is %s.', 'hcaptcha-for-forms-and-more' ),
				$this->format_percent( (float) $components['ip_repeat_rate'] )
			);
		}

		if ( $components['user_agent_repeat_rate'] >= 50 ) {
			$factors[] = sprintf(
			/* translators: 1: Repeat percent. */
				__( 'Repeated User Agent signal is %s.', 'hcaptcha-for-forms-and-more' ),
				$this->format_percent( (float) $components['user_agent_repeat_rate'] )
			);
		}

		if ( $components['error_concentration'] >= 50 ) {
			$factors[] = sprintf(
			/* translators: 1: Error concentration percent. */
				__( 'Top error concentration is %s.', 'hcaptcha-for-forms-and-more' ),
				$this->format_percent( (float) $components['error_concentration'] )
			);
		}

		if ( ! $factors ) {
			$factors[] = __( 'No elevated risk factors.', 'hcaptcha-for-forms-and-more' );
		}

		return $factors;
	}

	/**
	 * Get signal label.
	 *
	 * @param string $label Label.
	 * @param int    $total Total.
	 * @param int    $unique Unique.
	 *
	 * @return string
	 */
	private function get_signal_label( string $label, int $total, int $unique ): string {
		if ( ! $total ) {
			return sprintf(
			/* translators: 1: Signal label. */
				__( '%s: not collected', 'hcaptcha-for-forms-and-more' ),
				$label
			);
		}

		return sprintf(
		/* translators: 1: Signal label, 2: Unique count, 3: Total count. */
			__( '%1$s: %2$s unique of %3$s', 'hcaptcha-for-forms-and-more' ),
			$label,
			number_format_i18n( $unique ),
			number_format_i18n( $total )
		);
	}

	/**
	 * Move hCaptcha events to Trash.
	 *
	 * @param array $args Arguments.
	 *
	 * @return bool
	 */
	protected function trash_events( array $args ): bool {
		if ( ! Events::is_trash_schema_ready() ) {
			return false;
		}

		return $this->update_events_status( $args, Events::STATUS_TRASH, Events::STATUS_ACTIVE );
	}

	/**
	 * Restore hCaptcha events from Trash.
	 *
	 * @param array $args Arguments.
	 *
	 * @return bool
	 */
	protected function restore_events( array $args ): bool {
		if ( ! Events::is_trash_schema_ready() ) {
			return false;
		}

		return $this->update_events_status( $args, Events::STATUS_ACTIVE, Events::STATUS_TRASH );
	}

	/**
	 * Delete hCaptcha events permanently.
	 *
	 * @param array $args Arguments.
	 *
	 * @return bool
	 */
	protected function delete_events( array $args ): bool {
		global $wpdb;

		if ( ! Events::is_trash_schema_ready() ) {
			return false;
		}

		$ids = $args['ids'] ?? [];

		$table_name = $wpdb->prefix . Events::TABLE_NAME;
		$in         = DB::prepare_in( $ids, '%d' );

		if ( ! $ids ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM $table_name WHERE id IN($in) AND status = %s",
				Events::STATUS_TRASH
			)
		);

		return (bool) $result;
	}

	/**
	 * Update hCaptcha event status.
	 *
	 * @param array  $args        Arguments.
	 * @param string $status      New status.
	 * @param string $from_status Current status.
	 *
	 * @return bool
	 */
	private function update_events_status( array $args, string $status, string $from_status ): bool {
		global $wpdb;

		$ids = $args['ids'] ?? [];

		if ( ! $ids ) {
			return false;
		}

		$table_name     = $wpdb->prefix . Events::TABLE_NAME;
		$in             = DB::prepare_in( $ids, '%d' );
		$trashed_at_gmt = Events::STATUS_TRASH === $status ? current_time( 'mysql', true ) : null;

		if ( null === $trashed_at_gmt ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$result = $wpdb->query(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"UPDATE $table_name SET status = %s, trashed_at_gmt = NULL WHERE id IN($in) AND status = %s",
					$status,
					$from_status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$result = $wpdb->query(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"UPDATE $table_name SET status = %s, trashed_at_gmt = %s WHERE id IN($in) AND status = %s",
					$status,
					$trashed_at_gmt,
					$from_status
				)
			);
		}

		return (bool) $result;
	}
}
