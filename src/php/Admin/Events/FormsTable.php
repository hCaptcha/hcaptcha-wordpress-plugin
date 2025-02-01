<?php
/**
 * FormsTable class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use HCaptcha\Settings\ListPageBase;

/**
 * List forms in the table.
 */
class FormsTable extends TableBase {

	/**
	 * Singular table name.
	 */
	protected const SINGULAR = 'form';

	/**
	 * Plural table name.
	 */
	protected const PLURAL = 'forms';

	/**
	 * Items per page option.
	 */
	protected const ITEMS_PER_PAGE = 'hcaptcha_forms_per_page';

	/**
	 * Served events.
	 *
	 * @var array
	 */
	public $served = [];

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->columns = [
			'cb'      => '<input type="checkbox" />',
			'name'    => __( 'Source', 'hcaptcha-for-forms-and-more' ),
			'form_id' => __( 'Form Id', 'hcaptcha-for-forms-and-more' ),
			'served'  => __( 'Served', 'hcaptcha-for-forms-and-more' ),
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
			'name'    => [
				'name',
				false,
				__( 'Source', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Source.', 'hcaptcha-for-forms-and-more' ),
			],
			'form_id' => [
				'form_id',
				false,
				__( 'Form Id', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Form Id.', 'hcaptcha-for-forms-and-more' ),
			],
			'served'  => [
				'served',
				false,
				__( 'Served', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Served Count.', 'hcaptcha-for-forms-and-more' ),
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
		$order   = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'ASC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'source';
		$date    = isset( $_GET['date'] )
			? filter_input( INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			: ''; // We need filter_input here to keep delimiter intact.
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dates    = explode( ListPageBase::TIMESPAN_DELIMITER, $date );
		$dates    = array_filter( array_map( 'trim', $dates ) );
		$per_page = $this->get_items_per_page( self::ITEMS_PER_PAGE, $this->per_page_default );
		$offset   = ( $paged - 1 ) * $per_page;
		$args     = [
			'offset'  => $offset,
			'limit'   => $per_page,
			'order'   => $order,
			'orderby' => $orderby,
			'dates'   => $dates,
		];

		$forms        = Events::get_forms( $args );
		$this->items  = $forms['items'];
		$this->served = $forms['served'];
		$total_items  = $forms['total'];

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'total_pages' => ceil( $total_items / $per_page ),
				'per_page'    => $per_page,
			]
		);
	}
}
