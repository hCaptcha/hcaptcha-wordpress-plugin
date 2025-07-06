<?php
/**
 * ListPageBaseTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\Settings;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use HCaptcha\Settings\ListPageBase;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class ListPageBaseTest
 *
 * @group settings
 * @group settings-list-page-base
 */
class ListPageBaseTest extends HCaptchaTestCase {

	/**
	 * Tear down test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $_POST );

		parent::tearDown();
	}

	/**
	 * Test date_picker_display().
	 *
	 * @throws ReflectionException ReflectionException.
	 * @noinspection HtmlWrongAttributeValue
	 */
	public function test_date_picker_display(): void {
		$choices       = [
			'<label >Today<input type="radio" aria-hidden="true" name="timespan" value="2024-05-27 - 2024-05-27"  ></label>',
			'<label >Yesterday<input type="radio" aria-hidden="true" name="timespan" value="2024-05-26 - 2024-05-27"  ></label>',
			'<label >Last 7 days<input type="radio" aria-hidden="true" name="timespan" value="2024-05-20 - 2024-05-27"  ></label>',
			'<label class="is-selected">Last 30 days<input type="radio" aria-hidden="true" name="timespan" value="2024-04-27 - 2024-05-27"  checked=&#039;checked&#039; data-default></label>',
			'<label >Last 90 days<input type="radio" aria-hidden="true" name="timespan" value="2024-02-27 - 2024-05-27"  ></label>',
			'<label >Last 1 year<input type="radio" aria-hidden="true" name="timespan" value="2023-05-28 - 2024-05-27"  ></label>',
			'<label >Custom<input type="radio" aria-hidden="true" name="timespan" value="custom"  ></label>',
		];
		$chosen_filter = 'Last 30 days';
		$value         = '2024-04-27 - 2024-05-27';
		$expected      = '		<div class="hcaptcha-filter">
			<button id="hcaptcha-datepicker-popover-button" class="button" role="button" aria-haspopup="true">
				Last 30 days			</button>
			<div class="hcaptcha-datepicker-popover">
				<div class="hcaptcha-datepicker-popover-content">
					<ul class="hcaptcha-datepicker-choices"
						aria-label="Datepicker options"
						aria-orientation="vertical">
						<li><label >Today<input type="radio" aria-hidden="true" name="timespan" value="2024-05-27 - 2024-05-27"  ></label></li><li><label >Yesterday<input type="radio" aria-hidden="true" name="timespan" value="2024-05-26 - 2024-05-27"  ></label></li><li><label >Last 7 days<input type="radio" aria-hidden="true" name="timespan" value="2024-05-20 - 2024-05-27"  ></label></li><li><label class="is-selected">Last 30 days<input type="radio" aria-hidden="true" name="timespan" value="2024-04-27 - 2024-05-27"  checked=&#039;checked&#039; data-default></label></li><li><label >Last 90 days<input type="radio" aria-hidden="true" name="timespan" value="2024-02-27 - 2024-05-27"  ></label></li><li><label >Last 1 year<input type="radio" aria-hidden="true" name="timespan" value="2023-05-28 - 2024-05-27"  ></label></li><li><label >Custom<input type="radio" aria-hidden="true" name="timespan" value="custom"  ></label></li>					</ul>
					<div class="hcaptcha-datepicker-calendar">
						<label for="hcaptcha-datepicker">
							<input
									type="text"
									name="date"
									tabindex="-1"
									aria-hidden="true"
									id="hcaptcha-datepicker"
									value="2024-04-27 - 2024-05-27">
						</label>
					</div>
					<div class="hcaptcha-datepicker-action">
						<button class="button-secondary" type="reset">
							Cancel						</button>
						<button class="button-primary hcaptcha-btn-blue" type="submit">
							Apply						</button>
					</div>
				</div>
			</div>
		</div>
		';

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$this->set_protected_property( $subject, 'allowed', true );
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'process_datepicker_choices' )->with()
			->andReturn( [ $choices, $chosen_filter, $value ] );

		WP_Mock::passthruFunction( 'wp_kses' );

		ob_start();

		$subject->date_picker_display();

		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test date_picker_display() when not allowed.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_date_picker_display_when_not_allowed(): void {
		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$this->set_protected_property( $subject, 'allowed', false );

		ob_start();

		$subject->date_picker_display();

		self::assertSame( '', ob_get_clean() );
	}

	/**
	 * Test bulk_action().
	 *
	 * @param string|null $date Date.
	 *
	 * @return void
	 * @dataProvider dp_test_bulk_action
	 */
	public function test_bulk_action( ?string $date ): void {
		$ids   = [ 1, 2, 3 ];
		$dates = explode( ListPageBase::TIMESPAN_DELIMITER, $date );
		$dates = array_filter( array_map( 'trim', $dates ) );
		$args  = [
			'ids'   => $ids,
			'dates' => $date ? $dates : [],
		];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$ids_encoded = json_encode( $ids );

		$_POST['bulk'] = 'trash';
		$_POST['ids']  = $ids_encoded;

		if ( null !== $date ) {
			$_POST['date'] = $date;
		}

		$subject = Mockery::mock( ListPageBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' );
		$subject->shouldReceive( 'delete_events' )->with( $args )->andReturn( true );

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'set_transient' )->once()
			->with( 'hcaptcha_page_base', 'Selected items have been successfully deleted.' );
		WP_Mock::userFunction( 'wp_send_json_success' )->once()->with();

		$subject->bulk_action();
	}

	/**
	 * Data provider for test_bulk_action().
	 *
	 * @return array
	 */
	public function dp_test_bulk_action(): array {
		return [
			'No date'   => [ null ],
			'Some date' => [ '2024-04-27 - 2024-05-27' ],
		];
	}

	/**
	 * Test bulk_action() with invalid action.
	 *
	 * @return void
	 */
	public function test_bulk_action_with_invalid_action(): void {
		$subject = Mockery::mock( ListPageBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' );

		WP_Mock::userFunction( 'wp_send_json_error' )->once()->with( 'Invalid bulk action.' );

		$subject->bulk_action();
	}

	/**
	 * Test bulk_action() with delete error.
	 *
	 * @return void
	 */
	public function test_bulk_action_with_delete_error(): void {
		$args = [
			'ids'   => [],
			'dates' => [],
		];

		$_POST['bulk'] = 'trash';

		$subject = Mockery::mock( ListPageBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'run_checks' );
		$subject->shouldReceive( 'delete_events' )->with( $args )->andReturn( false );

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'wp_send_json_error' )->once()->with( 'Failed to delete the selected items.' );

		$subject->bulk_action();
	}

	/**
	 * Test get_clean_transient().
	 *
	 * @return void
	 */
	public function test_get_clean_transient(): void {
		$subject = Mockery::mock( ListPageBase::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();

		// With transient.
		$message = 'some';

		WP_Mock::userFunction( 'get_transient' )->once()->with( 'hcaptcha_page_base' )->andReturn( $message );
		WP_Mock::userFunction( 'delete_transient' )->once()->with( 'hcaptcha_page_base' );

		self::assertSame( $message, $subject->get_clean_transient() );

		// With empty transient.
		WP_Mock::userFunction( 'get_transient' )->once()->with( 'hcaptcha_page_base' )->andReturn( false );

		self::assertSame( '', $subject->get_clean_transient() );
	}

	/**
	 * Test process_datepicker_choices().
	 *
	 * @return void
	 */
	public function test_process_datepicker_choices(): void {
		$start_date    = date_create_immutable( '2024-04-27 00:00:00' );
		$end_date      = date_create_immutable( '2024-05-27 23:59:59' );
		$days          = '30';
		$timespan      = [ $start_date, $end_date, $days, 'some timespan label' ];
		$filters       = [
			0        => 'Today',
			1        => 'Yesterday',
			7        => 'Last 7 days',
			30       => 'Last 30 days',
			90       => 'Last 90 days',
			365      => 'Last 1 year',
			'custom' => 'Custom',
		];
		$choices       = [
			'<label >Today<input type="radio" aria-hidden="true" name="timespan" value="2024-05-27 - 2024-05-27"  ></label>',
			'<label >Yesterday<input type="radio" aria-hidden="true" name="timespan" value="2024-05-26 - 2024-05-27"  ></label>',
			'<label >Last 7 days<input type="radio" aria-hidden="true" name="timespan" value="2024-05-20 - 2024-05-27"  ></label>',
			'<label >Last 30 days<input type="radio" aria-hidden="true" name="timespan" value="2024-04-27 - 2024-05-27"  data-default></label>',
			'<label >Last 90 days<input type="radio" aria-hidden="true" name="timespan" value="2024-02-27 - 2024-05-27"  ></label>',
			'<label >Last 1 year<input type="radio" aria-hidden="true" name="timespan" value="2023-05-28 - 2024-05-27"  ></label>',
			'<label >Custom<input type="radio" aria-hidden="true" name="timespan" value="custom"  ></label>',
		];
		$chosen_filter = 'Last 30 days';
		$value         = '2024-04-27 - 2024-05-27';
		$expected      = [ $choices, $chosen_filter, $value ];

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'process_timespan' )->with()->andReturn( $timespan );
		$subject->shouldReceive( 'get_date_filter_choices' )->with()->andReturn( $filters );
		$subject->shouldReceive( 'get_timespan_dates' )->andReturnUsing(
			function ( $days ) use ( $end_date ) {
				if ( 'custom' === $days ) {
					return [ '', '', 'custom', 'Custom' ];
				}

				$start_date = $end_date->modify( "-$days days" );
				$choices    = [
					'0'   => 'Today',
					'1'   => 'Yesterday',
					'7'   => 'Last 7 days',
					'30'  => 'Last 30 days',
					'90'  => 'Last 90 days',
					'365' => 'Last 1 year',
				];

				return [ $start_date, $end_date, $days, $choices[ $days ] ];
			}
		);

		WP_Mock::userFunction( 'checked' )->andReturnUsing(
			function ( $checked, $current, $display ) {
				return ! $display && $checked === $current ? ' checked=\'checked\'' : '';
			}
		);

		self::assertSame( $expected, $subject->process_datepicker_choices() );
	}

	/**
	 * Test process_timespan().
	 *
	 * @return void
	 * @throws Exception Exception.
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_process_timespan(): void {
		$dates      = '2024-04-27 - 2024-05-27';
		$start_date = date_create_immutable( '2024-04-27 00:00:00' );
		$end_date   = date_create_immutable( '2024-05-27 23:59:59' );
		$expected   = [ $start_date, $end_date, '30', 'Last 30 days' ];

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		FunctionMocker::replace(
			'date_create_immutable',
			static function ( $datetime, $timezone ) {
				if ( 'now' === $datetime ) {
					$datetime = '2024-05-27';
				}

				return new DateTimeImmutable( $datetime, $timezone );
			}
		);

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name, $filter ) use ( $dates ) {
				if (
					INPUT_GET === $type &&
					'date' === $name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $dates;
				}

				return null;
			}
		);

		WP_Mock::userFunction( 'wp_timezone' )->andReturn( new DateTimeZone( date_default_timezone_get() ) );

		$actual = $subject->process_timespan();

		self::assertSame( $expected[0]->getTimestamp(), $actual[0]->getTimestamp() );
		self::assertSame( $expected[1]->getTimestamp(), $actual[1]->getTimestamp() );
		self::assertSame( $expected[2], $actual[2] );
		self::assertSame( $expected[3], $actual[3] );
	}

	/**
	 * Test process_timespan() with empty $_GET.
	 *
	 * @return void
	 */
	public function test_process_timespan_with_empty_get(): void {
		$start_date = date_create_immutable( '2024-04-27 00:00:00' );
		$end_date   = date_create_immutable( '2024-05-27 23:59:59' );
		$expected   = [ $start_date, $end_date, '30', 'Last 30 days' ];

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_timespan_dates' )
			->with( ListPageBase::DEFAULT_TIMESPAN_DAYS )->andReturn( $expected );

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name, $filter ) {
				if (
					INPUT_GET === $type &&
					'date' === $name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return null;
				}

				return 'some';
			}
		);

		self::assertSame( $expected, $subject->process_timespan() );
	}

	/**
	 * Test process_timespan() with wrong dates in $_GET.
	 *
	 * @return void
	 * @noinspection PhpVariableIsUsedOnlyInClosureInspection
	 */
	public function test_process_timespan_with_wrong_dates(): void {
		$dates      = '2024-06-27 - 2024-05-27';
		$start_date = date_create_immutable( '2024-06-27 00:00:00' );
		$end_date   = date_create_immutable( '2024-05-27 23:59:59' );
		$expected   = [ $start_date, $end_date, '30', 'Last 30 days' ];

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_timespan_dates' )
			->with( ListPageBase::DEFAULT_TIMESPAN_DAYS )->andReturn( $expected );

		FunctionMocker::replace(
			'filter_input',
			static function ( $type, $name, $filter ) use ( $dates ) {
				if (
					INPUT_GET === $type &&
					'date' === $name &&
					FILTER_SANITIZE_FULL_SPECIAL_CHARS === $filter
				) {
					return $dates;
				}

				return null;
			}
		);

		self::assertSame( $expected, $subject->process_timespan() );
	}

	/**
	 * Test maybe_validate_string_timespan().
	 *
	 * @return void
	 */
	public function test_maybe_validate_string_timespan(): void {
		$dates = '2024-04-27 - 2024-05-27';

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( $dates, $subject->maybe_validate_string_timespan( $dates ) );

		$dates    = '2024-05-27';
		$expected = $dates . ListPageBase::TIMESPAN_DELIMITER . $dates;

		self::assertSame( $expected, $subject->maybe_validate_string_timespan( $dates ) );
	}

	/**
	 * Test get_timespan_dates().
	 *
	 * @param string $days     Days.
	 * @param array  $expected Expected.
	 *
	 * @dataProvider dp_test_get_timespan_dates
	 * @return void
	 */
	public function test_get_timespan_dates( string $days, array $expected ): void {
		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		FunctionMocker::replace(
			'date_create_immutable',
			static function ( $datetime, $timezone ) {
				if ( 'now' === $datetime ) {
					$datetime = '2024-05-27';
				}

				return new DateTimeImmutable( $datetime, $timezone );
			}
		);

		$actual = $subject->get_timespan_dates( $days );

		if ( is_string( $expected[0] ) ) {
			self::assertSame( $expected[0], $actual[0] );
		} else {
			self::assertSame( $expected[0]->getTimestamp(), $actual[0]->getTimestamp() );
		}

		if ( is_string( $expected[1] ) ) {
			self::assertSame( $expected[1], $actual[1] );
		} else {
			self::assertSame( $expected[1]->getTimestamp(), $actual[1]->getTimestamp() );
		}

		self::assertSame( $expected[2], $actual[2] );
		self::assertSame( $expected[3], $actual[3] );
	}

	/**
	 * Data provider for test_get_timespan_dates().
	 *
	 * @return array
	 */
	public function dp_test_get_timespan_dates(): array {
		$start_date = date_create_immutable( '2024-04-27 00:00:00' );
		$end_date   = date_create_immutable( '2024-05-27 23:59:59' );

		return [
			[
				'30',
				[ $start_date, $end_date, '30', 'Last 30 days' ],
			],
			[
				'custom',
				[ '', '', 'custom', 'Custom' ],
			],
		];
	}

	/**
	 * Test get_date_filter_choices().
	 *
	 * @return void
	 */
	public function test_get_date_filter_choices(): void {
		$choices = [
			'0'      => 'Today',
			'1'      => 'Yesterday',
			'7'      => 'Last 7 days',
			'30'     => 'Last 30 days',
			'90'     => 'Last 90 days',
			'365'    => 'Last 1 year',
			'custom' => 'Custom',
		];

		$subject = Mockery::mock( ListPageBase::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();

		self::assertSame( $choices, $subject->get_date_filter_choices() );
		self::assertSame( [ '30', $choices['30'] ], $subject->get_date_filter_choices( '30' ) );
		self::assertSame( [ 'custom', $choices['custom'] ], $subject->get_date_filter_choices( 'some' ) );
	}
}
