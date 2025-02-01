<?php
/**
 * ListPageBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use DateTimeImmutable;
use Exception;

/**
 * Class ListPageBase.
 *
 * Settings page with a list.
 */
abstract class ListPageBase extends PluginSettingsBase {

	/**
	 * Chart handle.
	 */
	public const CHART_HANDLE = 'chart';

	/**
	 * Flatpickr handle.
	 */
	public const FLATPICKR_HANDLE = 'flatpickr';

	/**
	 * Base handle.
	 */
	public const HANDLE = 'settings-list-page-base';

	/**
	 * Base object.
	 */
	public const OBJECT = 'HCaptchaListPageBaseObject';

	/**
	 * Number of timespan days by default.
	 * "Last 30 Days", by default.
	 */
	public const DEFAULT_TIMESPAN_DAYS = '30';

	/**
	 * Timespan (date range) delimiter.
	 */
	public const TIMESPAN_DELIMITER = ' - ';

	/**
	 * Default date format.
	 */
	private const DATE_FORMAT = 'Y-m-d';

	/**
	 * Chart time unit.
	 *
	 * @var string
	 */
	protected $unit;

	/**
	 * The page is allowed to be shown.
	 *
	 * @var bool
	 */
	protected $allowed = false;

	/**
	 * Init class hooks.
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'kagg_settings_header', [ $this, 'date_picker_display' ] );
	}

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

		$time_units = [
			[ 1, 'second' ],
			[ constant( 'MINUTE_IN_SECONDS' ), 'minute' ],
			[ constant( 'HOUR_IN_SECONDS' ), 'hour' ],
			[ constant( 'DAY_IN_SECONDS' ), 'day' ],
			[ constant( 'WEEK_IN_SECONDS' ), 'week' ],
			[ constant( 'MONTH_IN_SECONDS' ), 'month' ],
			[ constant( 'YEAR_IN_SECONDS' ), 'year' ],
		];

		foreach ( $time_units as $index => $time_unit ) {
			$i          = max( 0, $index - 1 );
			$this->unit = $time_units[ $i ][1];

			if ( $time_diff < $time_unit[0] * 2 ) {
				break;
			}
		}

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
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::CHART_HANDLE,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/chartjs/chart.umd.min.js',
			[],
			'v4.4.2',
			true
		);

		wp_enqueue_script(
			'chart-adapter-date-fns',
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/chartjs/chartjs-adapter-date-fns.bundle.min.js',
			[ self::CHART_HANDLE ],
			'v3.0.0',
			true
		);

		wp_enqueue_style(
			self::FLATPICKR_HANDLE,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/flatpickr/flatpickr.min.css',
			[],
			'4.6.13'
		);

		wp_enqueue_script(
			self::FLATPICKR_HANDLE,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/flatpickr/flatpickr.min.js',
			[],
			'4.6.13',
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/settings-list-page-base$min.css",
			[ self::FLATPICKR_HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/settings-list-page-base$min.js",
			[ self::FLATPICKR_HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'noAction'  => __( 'Please select a bulk action.', 'hcaptcha-for-forms-and-more' ),
				'noItems'   => __( 'Please select at least one item to perform this action on.', 'hcaptcha-for-forms-and-more' ),
				'DoingBulk' => __( 'Doing bulk action...', 'hcaptcha-for-forms-and-more' ),
				'delimiter' => self::TIMESPAN_DELIMITER,
				'locale'    => $this->get_language_code(),
			]
		);
	}

	/**
	 * Display datepicker element.
	 *
	 * @return void
	 */
	public function date_picker_display(): void {
		if ( ! $this->allowed ) {
			return;
		}

		[ $choices, $chosen_filter, $value ] = $this->process_datepicker_choices();

		// An array of allowed HTML elements and attributes for the datepicker choices.
		$choices_allowed_html = [
			'li'    => [],
			'label' => [],
			'input' => [
				'type'         => [],
				'name'         => [],
				'value'        => [],
				'checked'      => [],
				'aria-hidden'  => [],
				'data-default' => [],
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

	/**
	 * Sets the timespan (or date range) for performing mysql queries.
	 *
	 * Includes:
	 * 1. A list of date filter options for the datepicker module.
	 * 2. Currently selected filter or date range values. Last "X" days, or i.e. Feb 8, 2023 - Mar 9, 2023.
	 * 3. Assigned timespan dates.
	 *
	 * @param array|null $timespan Given timespan (dates) preferably in WP timezone.
	 *
	 * @return array
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function process_datepicker_choices( $timespan = null ): array {
		// Retrieve and validate timespan if none is given.
		if ( empty( $timespan ) || ! is_array( $timespan ) ) {
			$timespan = $this->process_timespan();
		}

		[ $start_date, $end_date, $days ] = $timespan;

		$filters       = $this->get_date_filter_choices();
		$selected      = isset( $filters[ $days ] ) ? $days : 'custom';
		$value         = $this->concat_dates( $start_date, $end_date );
		$chosen_filter = 'custom' === $selected ? $value : $filters[ $selected ];
		$choices       = [];

		foreach ( $filters as $choice => $label ) {
			$timespan_dates = $this->get_timespan_dates( $choice );
			$checked        = checked( $selected, $choice, false );
			$default        = (int) self::DEFAULT_TIMESPAN_DAYS === $choice ? 'data-default' : '';
			$choices[]      = sprintf(
				'<label %s>%s<input type="radio" aria-hidden="true" name="timespan" value="%s" %s %s></label>',
				$checked ? 'class="is-selected"' : '',
				esc_html( $label ),
				esc_attr( $this->concat_dates( ...$timespan_dates ) ),
				esc_attr( $checked ),
				esc_attr( $default )
			);
		}

		return [ $choices, $chosen_filter, $value ];
	}

	/**
	 * Sets the timespan (or date range) selected.
	 *
	 * Includes:
	 * 1. Start date object in WP timezone.
	 * 2. End date object in WP timezone.
	 * 3. Number of "Last X days", if applicable, otherwise returns "custom".
	 * 4. Label associated with the selected date filter choice. @see "get_date_filter_choices".
	 *
	 * @return array
	 */
	protected function process_timespan(): array {
		$dates = (string) filter_input( INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// Return default timespan if dates are empty.
		if ( empty( $dates ) ) {
			return $this->get_timespan_dates( self::DEFAULT_TIMESPAN_DAYS );
		}

		$dates = $this->maybe_validate_string_timespan( $dates );

		[ $start_date, $end_date ] = explode( self::TIMESPAN_DELIMITER, $dates );

		// Return default timespan if the start date is more recent than the end date.
		if ( strtotime( $start_date ) > strtotime( $end_date ) ) {
			return $this->get_timespan_dates( self::DEFAULT_TIMESPAN_DAYS );
		}

		$timezone   = wp_timezone(); // Retrieve the timezone string for the site.
		$start_date = date_create_immutable( $start_date, $timezone );
		$end_date   = date_create_immutable( $end_date, $timezone );

		// Return default timespan if date creation fails.
		if ( ! $start_date || ! $end_date ) {
			// @codeCoverageIgnoreStart
			return $this->get_timespan_dates( self::DEFAULT_TIMESPAN_DAYS );
			// @codeCoverageIgnoreEnd
		}

		// Set time to 0:0:0 for start date and 23:59:59 for end date.
		$start_date = $start_date->setTime( 0, 0 );
		$end_date   = $end_date->setTime( 23, 59, 59 );

		$days_diff    = '';
		$current_date = date_create_immutable( 'now', $timezone )->setTime( 23, 59, 59 );

		// Calculate day difference only if the end date is equal to the current date.
		if ( ! $current_date->diff( $end_date )->format( '%a' ) ) {
			$days_diff = $end_date->diff( $start_date )->format( '%a' );
		}

		[ $days, $timespan_label ] = $this->get_date_filter_choices( $days_diff );

		return [
			$start_date,     // WP timezone.
			$end_date,       // WP timezone.
			$days,           // e.g., 22.
			$timespan_label, // e.g., Custom.
		];
	}

	/**
	 * Check the delimiter to see if the end date is specified.
	 * We can assume that the start and end dates are the same if the end date is missing.
	 *
	 * @param string $dates Given timespan (dates) in string. i.e. "2024-04-16 - 2024-05-16" or "2024-04-16".
	 *
	 * @return string
	 */
	protected function maybe_validate_string_timespan( string $dates ): string {
		// The '-' is used as a delimiter for the datepicker module.
		if ( false !== strpos( $dates, self::TIMESPAN_DELIMITER ) ) {
			return $dates;
		}

		return $dates . self::TIMESPAN_DELIMITER . $dates;
	}

	/**
	 * The number of days is converted to the start and end date range.
	 *
	 * @param string $days Timespan days.
	 *
	 * @return array
	 */
	protected function get_timespan_dates( string $days ): array {
		[ $timespan_key, $timespan_label ] = $this->get_date_filter_choices( $days );

		// Bail early, if the given number of days is NOT a number nor a numeric string.
		if ( ! is_numeric( $days ) ) {
			return [ '', '', $timespan_key, $timespan_label ];
		}

		$end_date   = date_create_immutable( 'now', wp_timezone() );
		$start_date = $end_date;

		if ( (int) $days > 0 ) {
			try {
				$start_date = $start_date->modify( "-$days day" );
			} catch ( Exception $e ) {
				// @codeCoverageIgnoreStart
				$start_date = $end_date;
				// @codeCoverageIgnoreEnd
			}
		}

		$start_date = $start_date->setTime( 0, 0 );
		$end_date   = $end_date->setTime( 23, 59, 59 );

		return [
			$start_date,     // WP timezone.
			$end_date,       // WP timezone.
			$timespan_key,   // i.e. 30.
			$timespan_label, // i.e. Last 30 days.
		];
	}

	/**
	 * Returns a list of date filter options for the datepicker module.
	 *
	 * @param string|null $key Optional. Key associated with available filters.
	 *
	 * @return array
	 * @noinspection PhpMissingParamTypeInspection
	 */
	protected function get_date_filter_choices( $key = null ): array {
		// Available date filters.
		$choices = [
			'0'      => esc_html__( 'Today', 'hcaptcha-for-forms-and-more' ),
			'1'      => esc_html__( 'Yesterday', 'hcaptcha-for-forms-and-more' ),
			'7'      => esc_html__( 'Last 7 days', 'hcaptcha-for-forms-and-more' ),
			'30'     => esc_html__( 'Last 30 days', 'hcaptcha-for-forms-and-more' ),
			'90'     => esc_html__( 'Last 90 days', 'hcaptcha-for-forms-and-more' ),
			'365'    => esc_html__( 'Last 1 year', 'hcaptcha-for-forms-and-more' ),
			'custom' => esc_html__( 'Custom', 'hcaptcha-for-forms-and-more' ),
		];

		// Bail early, and return the full list of options.
		if ( is_null( $key ) ) {
			return $choices;
		}

		// Return the "Custom" filter if the given key is not found.
		$key = isset( $choices[ $key ] ) ? $key : 'custom';

		return [ $key, $choices[ $key ] ];
	}

	/**
	 * Concatenate given dates into a single string.
	 * Should be like that: "2024-04-16 - 2024-05-16".
	 *
	 * @param DateTimeImmutable|mixed $start_date Start date.
	 * @param DateTimeImmutable|mixed $end_date   End date.
	 * @param int|string              $fallback   Fallback value if dates are not valid.
	 *
	 * @return string
	 */
	private function concat_dates( $start_date, $end_date, $fallback = '' ) {
		// Bail early, if the given dates are not valid.
		if ( ! ( $start_date instanceof DateTimeImmutable ) || ! ( $end_date instanceof DateTimeImmutable ) ) {
			return $fallback;
		}

		return implode(
			self::TIMESPAN_DELIMITER,
			[
				$start_date->format( self::DATE_FORMAT ),
				$end_date->format( self::DATE_FORMAT ),
			]
		);
	}

	/**
	 * Get the ISO 639-2 Language Code from user/site locale.
	 *
	 * @see   http://www.loc.gov/standards/iso639-2/php/code_list.php
	 *
	 * @return string
	 */
	private function get_language_code(): string {
		$default_lang = 'en';
		$locale       = get_user_locale();

		if ( ! empty( $locale ) ) {
			$lang = explode( '_', $locale );

			if ( ! empty( $lang ) && is_array( $lang ) ) {
				$default_lang = strtolower( $lang[0] );
			}
		}

		return $default_lang;
	}
}
