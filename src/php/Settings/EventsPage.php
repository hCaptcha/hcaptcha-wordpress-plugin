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
			<canvas id="eventsChart" aria-label="The hCaptcha Events Chart" role="img">
				<p>
					<?php esc_html_e( 'Your browser does not support the canvas element.', 'hcaptcha-for-forms-and-more' ); ?>
				</p>
			</canvas>
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
