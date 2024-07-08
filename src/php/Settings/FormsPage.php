<?php
/**
 * FormsPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

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
	 * Init class hooks.
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'kagg_settings_header', [ $this, 'date_picker_display' ] );
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
}
