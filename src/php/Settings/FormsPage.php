<?php
/**
 * FormsPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Events\Events;
use HCaptcha\Admin\Events\FormsTable;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class FormsPage.
 *
 * Settings page "Forms".
 */
class FormsPage extends ListPageBase {

	/**
	 * Admin script handle.
	 */
	public const HANDLE = 'hcaptcha-forms';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaFormsObject';

	/**
	 * Bulk ajax action.
	 */
	public const BULK_ACTION = 'hcaptcha-forms-bulk';

	/**
	 * ListTable instance.
	 *
	 * @var FormsTable
	 */
	protected $list_table;

	/**
	 * Served events.
	 *
	 * @var array
	 */
	protected $served;

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
	public function tab_name(): string {
		return 'Forms';
	}

	/**
	 * Admin init.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		$this->allowed = hcaptcha()->settings()->is_on( 'statistics' );

		if ( ! $this->allowed ) {
			return;
		}

		$this->list_table = new FormsTable( (string) get_plugin_page_hook( $this->option_page(), $this->parent_slug ) );

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
			constant( 'HCAPTCHA_URL' ) . "/assets/css/forms$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);

		if ( ! $this->allowed ) {
			return;
		}

		parent::admin_enqueue_scripts();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/forms$this->min_suffix.js",
			[ 'chart', 'chart-adapter-date-fns' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'bulkAction'  => self::BULK_ACTION,
				'bulkNonce'   => wp_create_nonce( self::BULK_ACTION ),
				'bulkMessage' => $this->get_clean_transient(),
				'served'      => $this->served,
				'servedLabel' => __( 'Served', 'hcaptcha-for-forms-and-more' ),
				'unit'        => $this->unit,
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

			$message = sprintf(
			/* translators: 1: Statistics link. */
				__( 'Want to see forms statistics? Please turn on the %1$s on the General settings page.', 'hcaptcha-for-forms-and-more' ),
				sprintf(
				/* translators: 1: Statistics switch link, 2: Statistics switch text. */
					'<a href="%1$s" target="_blank">%2$s</a>',
					$statistics_url,
					__( 'Statistics switch', 'hcaptcha-for-forms-and-more' )
				)
			);

			?>
			<div class="hcaptcha-forms-sample-bg"></div>

			<div class="hcaptcha-forms-sample-text">
				<p><?php esc_html_e( 'It is an example of the Forms page.', 'hcaptcha-for-forms-and-more' ); ?></p>
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
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
				<?php
				if ( count( $this->list_table->served ) >= Events::SERVED_LIMIT ) {
					echo '<div id="hcaptcha-chart-message">';
					printf(
					/* translators: 1: Number of elements. */
						esc_html__( 'The chart is limited to displaying a maximum of %1$s elements.', 'hcaptcha-for-forms-and-more' ),
						esc_html( number_format_i18n( Events::SERVED_LIMIT ) )
					);
					echo '</div>';
				}
				?>
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
	protected function prepare_chart_data(): void {
		$this->served = [];

		$this->list_table->prepare_items();

		if ( ! $this->list_table->served ) {
			return;
		}

		$date_format = $this->get_date_format( $this->list_table->served );

		foreach ( $this->list_table->served as $item ) {
			$time_gmt = strtotime( $item->date_gmt );
			$date     = wp_date( $date_format, $time_gmt );

			$this->served[ $date ] = $this->served[ $date ] ?? 0;

			++$this->served[ $date ];
		}
	}

	/**
	 * Delete hCaptcha events by forms.
	 *
	 * @param array $args Arguments.
	 *
	 * @return bool
	 */
	protected function delete_events( array $args ): bool {
		global $wpdb;

		$ids   = $args['ids'] ?? [];
		$dates = $args['dates'] ?? [];
		$dates = $dates ?: Events::get_default_dates();
		$dates = Events::prepare_gmt_dates( $dates );

		$table_name = $wpdb->prefix . Events::TABLE_NAME;
		$conditions = [];
		$values     = [];

		if ( ! $ids ) {
			return false;
		}

		foreach ( $ids as $item ) {
			$conditions[] = '(source = %s AND form_id = %s)';
			$values[]     = $item['source'];
			$values[]     = $item['formId'];
		}

		$where_clause = implode( ' OR ', $conditions );
		$where_clause = "($where_clause) AND date_gmt BETWEEN %s AND %s";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				"DELETE FROM $table_name WHERE $where_clause",
				...$values,
				...$dates
			)
		);

		return (bool) $result;
	}
}
