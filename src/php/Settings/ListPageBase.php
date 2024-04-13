<?php
/**
 * ListPageBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

/**
 * Class ListPageBase.
 *
 * Settings page with a list.
 */
abstract class ListPageBase extends PluginSettingsBase {

	/**
	 * Chart time unit.
	 *
	 * @var string
	 */
	protected $unit;

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

			if ( $time_diff < $time_unit[0] ) {
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
}
