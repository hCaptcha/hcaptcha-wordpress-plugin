<?php
/**
 * EventsPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

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
	protected $list_table;

	/**
	 * Succeed events.
	 *
	 * @var array
	 */
	protected $succeed;

	/**
	 * Failed events.
	 *
	 * @var array
	 */
	protected $failed;

	/**
	 * Init class hooks.
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_ajax_' . self::BULK_ACTION, [ $this, 'bulk_action' ] );
	}

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

		$this->allowed = ( $settings->is_on( 'statistics' ) && $settings->is_pro() );

		if ( ! $this->allowed ) {
			return;
		}

		$this->list_table = new EventsTable( (string) get_plugin_page_hook( $this->option_page(), $this->parent_slug ) );

		$this->prepare_chart_data();
	}

	/**
	 * Ajax callback for bulk actions.
	 *
	 * @return void
	 */
	public function bulk_action(): void {
		$this->run_checks( self::BULK_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$bulk = isset( $_POST['bulk'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk'] ) ) : '';
		$ids  = isset( $_POST['ids'] )
			? (array) json_decode( sanitize_text_field( wp_unslash( $_POST['ids'] ) ), true )
			: [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( 'trash' === $bulk ) {
			if ( ! $this->delete_hcaptcha_events( $ids ) ) {
				wp_send_json_error( __( 'Failed to delete the selected items.', 'hcaptcha-for-forms-and-more' ) );
			}

			wp_send_json_success();

			// For testing purposes.
			return;
		}

		wp_send_json_error( __( 'Invalid bulk action.', 'hcaptcha-for-forms-and-more' ) );
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

		?>
		<div id="hcaptcha-message"></div>
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
	 * Delete hCaptcha events by IDs.
	 *
	 * @param array $ids Array of event IDs to delete.
	 *
	 * @return bool
	 */
	private function delete_hcaptcha_events( array $ids ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hcaptcha_events';

		$in = DB::prepare_in( $ids, '%d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM $table_name WHERE id IN($in)"
			)
		);

		return (bool) $result;
	}
}
