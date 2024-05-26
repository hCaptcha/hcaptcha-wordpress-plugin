<?php
/**
 * ListPageBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Helpers\Utils;

/**
 * Class ListPageBase.
 *
 * Settings page with a list.
 */
abstract class ListPageBase extends PluginSettingsBase {
	const SETTINGS_LIST_PAGE_BASE_HANDLE = 'settings-list-page-base';

	/**
	 * Get suggested data format from items array.
	 *
	 * @param array $items Items array.
	 *
	 * @return string
	 */
	protected function get_date_format( array $items ): string {
		$gmt_offset = (int) get_option( 'gmt_offset' ) * constant( 'HOUR_IN_SECONDS' );
		$max_time   = 0;
		$min_time   = PHP_INT_MAX;

		foreach ( $items as $item ) {
			$time     = strtotime( $item->date_gmt ) + $gmt_offset;
			$max_time = max( $time, $max_time );
			$min_time = min( $time, $min_time );
		}

		$time_diff = $max_time - $min_time;

		if ( $time_diff < constant( 'MINUTE_IN_SECONDS' ) ) {
			$date_format = 'Y-m-d H:i:s';
		} elseif ( $time_diff < constant( 'DAY_IN_SECONDS' ) ) {
			$date_format = 'Y-m-d H:i';
		} else {
			$date_format = 'Y-m-d';
		}

		return $date_format;
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			'chart',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/chartjs/chart.umd.min.js',
			[],
			'v4.4.2',
			true
		);

		wp_enqueue_script(
			'chart-adapter-date-fns',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/chartjs/chartjs-adapter-date-fns.bundle.min.js',
			[ 'chart' ],
			'v3.0.0',
			true
		);

		wp_enqueue_style(
			'flatpickr',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/flatpickr/flatpickr.min.css',
			[],
			'4.6.13'
		);

		wp_enqueue_script(
			'flatpickr',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/flatpickr/flatpickr.min.js',
			[],
			'4.6.13',
			true
		);

		wp_enqueue_script(
			self::SETTINGS_LIST_PAGE_BASE_HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/settings-list-page-base$min.js",
			[],
			HCAPTCHA_VERSION,
			true
		);

		wp_localize_script(
			self::SETTINGS_LIST_PAGE_BASE_HANDLE,
			'HCaptchaFlatPickerObject',
			[
				'delimiter' => ' - ',
				'locale'    => Utils::get_language_code(),
			]
		);
	}

	/**
	 * Display datepicker element.
	 *
	 * @return void
	 */
	protected function date_picker_display() {
		$chosen_filter = 'Last 30 days';
		$value         = '2024-04-21 - 2024-05-21';
		$choices       = [
			'<label class="">Today<input type="radio" aria-hidden="true" name="timespan" value="2024-05-21 - 2024-05-21" ></label>',
			'<label class="">Yesterday<input type="radio" aria-hidden="true" name="timespan" value="2024-05-20 - 2024-05-21" ></label>',
			'<label class="">Last 7 days<input type="radio" aria-hidden="true" name="timespan" value="2024-05-14 - 2024-05-21" ></label>',
			'<label class="is-selected">Last 30 days<input type="radio" aria-hidden="true" name="timespan" value="2024-04-21 - 2024-05-21" checked="checked"></label>',
			'<label class="">Last 90 days<input type="radio" aria-hidden="true" name="timespan" value="2024-02-21 - 2024-05-21" ></label>',
			'<label class="">Last 1 year<input type="radio" aria-hidden="true" name="timespan" value="2023-05-22 - 2024-05-21" ></label>',
			'<label class="">Custom<input type="radio" aria-hidden="true" name="timespan" value="custom" ></label>',
		];

		// An array of allowed HTML elements and attributes for the datepicker choices.
		$choices_allowed_html = [
			'li'    => [],
			'label' => [],
			'input' => [
				'type'        => [],
				'name'        => [],
				'value'       => [],
				'checked'     => [],
				'aria-hidden' => [],
			],
		];

		?>
		<div class="hcaptcha-filter">
			<button id="hcaptcha-datepicker-popover-button" class="button" role="button" aria-haspopup="true">
				<?php echo esc_html( $chosen_filter ); ?>
			</button>
			<div class="hcaptcha-datepicker-popover">
				<div class="hcaptcha-datepicker-popover-content">
					<ul class="hcaptcha-datepicker-choices"
						aria-label="<?php esc_attr_e( 'Datepicker options', 'hcaptcha-for-forms-and-more' ); ?>"
						aria-orientation="vertical">
						<?php echo wp_kses( '<li>' . implode( '</li><li>', $choices ) . '</li>', $choices_allowed_html ); ?>
					</ul>
					<div class="hcaptcha-datepicker-calendar">
						<label for="hcaptcha-datepicker">
							<input
								type="text"
								name="date"
								tabindex="-1"
								aria-hidden="true"
								id="hcaptcha-datepicker"
								value="<?php echo esc_attr( $value ); ?>">
						</label>
					</div>
					<div class="hcaptcha-datepicker-action">
						<button class="button-secondary" type="reset">
							<?php esc_html_e( 'Cancel', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
						<button class="button-primary hcaptcha-btn-blue" type="submit">
							<?php esc_html_e( 'Apply', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
