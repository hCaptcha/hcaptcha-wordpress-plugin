<?php
/**
 * ListTable class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use HCaptcha\Settings\ListPageBase;

/**
 * List events in the table.
 */
class EventsTable extends TableBase {

	/**
	 * Singular table name.
	 */
	protected const SINGULAR = 'event';

	/**
	 * Plural table name.
	 */
	protected const PLURAL = 'events';

	/**
	 * Items per page option.
	 */
	protected const ITEMS_PER_PAGE = 'hcaptcha_events_per_page';

	/**
	 * Date and time formats.
	 *
	 * @var array
	 */
	private $datetime_format = [];

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->datetime_format = [
			'date' => get_option( 'date_format' ),
			'time' => get_option( 'time_format' ),
		];

		$this->columns = [
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Source', 'hcaptcha-for-forms-and-more' ),
			'form_id'     => __( 'Form Id', 'hcaptcha-for-forms-and-more' ),
			'ip'          => __( 'IP', 'hcaptcha-for-forms-and-more' ),
			'user_agent'  => __( 'User Agent', 'hcaptcha-for-forms-and-more' ),
			'error_codes' => __( 'Errors', 'hcaptcha-for-forms-and-more' ),
			'date_gmt'    => __( 'Date', 'hcaptcha-for-forms-and-more' ),
		];

		parent::init();
	}

	/**
	 * Retrieve the table's sortable columns.
	 *
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns(): array {

		return [
			'name'     => [
				'name',
				false,
				__( 'Source', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Source.', 'hcaptcha-for-forms-and-more' ),
			],
			'form_id'  => [
				'form_id',
				false,
				__( 'Form Id', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Form Id.', 'hcaptcha-for-forms-and-more' ),
			],
			'date_gmt' => [
				'date_gmt',
				false,
				__( 'Date GMT', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Date GMT.', 'hcaptcha-for-forms-and-more' ),
			],
		];
	}

	/**
	 * Fetch and set up the final data for the table.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$hidden                = get_hidden_columns( $this->screen );
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $this->columns, $hidden, $sortable ];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		$order   = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'DESC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'date_gmt';
		$date    = isset( $_GET['date'] )
			? filter_input( INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			: ''; // We need filter_input here to keep delimiter intact.
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dates        = explode( ListPageBase::TIMESPAN_DELIMITER, $date );
		$dates        = array_filter( array_map( 'trim', $dates ) );
		$column_slugs = str_replace( [ 'cb', 'name' ], [ 'id', 'source' ], array_keys( $this->columns ) );
		$per_page     = $this->get_items_per_page( self::ITEMS_PER_PAGE, $this->per_page_default );
		$offset       = ( $paged - 1 ) * $per_page;
		$args         = [
			'columns' => $column_slugs,
			'offset'  => $offset,
			'limit'   => $per_page,
			'order'   => $order,
			'orderby' => $orderby,
			'dates'   => $dates,
		];

		$events      = Events::get_events( $args );
		$this->items = $events['items'];
		$total_items = $events['total'];

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'total_pages' => ceil( $total_items / $per_page ),
				'per_page'    => $per_page,
			]
		);
	}

	/**
	 * Column IP.
	 *
	 * @param object $item Item.
	 *
	 * @noinspection PhpUnused PhpUnused.
	 */
	protected function column_ip( object $item ): string {
		return $this->excerpt( $item->ip );
	}

	/**
	 * Column User Agent.
	 *
	 * @param object $item Item.
	 *
	 * @noinspection PhpUnused PhpUnused.
	 */
	protected function column_user_agent( object $item ): string {
		return $this->excerpt( $item->user_agent );
	}

	/**
	 * Column Error Codes.
	 *
	 * @param object $item Item.
	 *
	 * @noinspection PhpUnused PhpUnused.
	 */
	protected function column_error_codes( object $item ): string {
		$error_codes = (array) json_decode( $item->error_codes, true );
		$errors      = hcap_get_error_messages();
		$message_arr = [];

		foreach ( $error_codes as $error_code ) {
			if ( array_key_exists( $error_code, $errors ) ) {
				$message_arr[] = $errors[ $error_code ];
			}
		}

		if ( ! $message_arr ) {
			return '';
		}

		return $this->excerpt( implode( '; ', $message_arr ) );
	}

	/**
	 * Column Date.
	 *
	 * @param object $item Item.
	 *
	 * @noinspection PhpUnused PhpUnused.
	 */
	protected function column_date_gmt( object $item ): string {
		$date    = $item->date_gmt;
		$wp_date = wp_date( $this->datetime_format['date'] . ' ' . $this->datetime_format['time'], strtotime( $date ) );

		return sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( $date ),
			esc_html( $wp_date )
		);
	}
}
