<?php
/**
 * FormsTable class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use WP_List_Table;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

// IMPORTANT NOTICE:
// This line is needed to prevent fatal errors in the third-party plugins.
// We know that Jetpack (probably others also) can load WP classes during cron jobs.
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * List forms in the table.
 */
class FormsTable extends WP_List_Table {

	/**
	 * Number of forms to show per page.
	 *
	 * @var int
	 */
	public $per_page = 20;

	/**
	 * Served events.
	 *
	 * @var array
	 */
	public $served = [];

	/**
	 * Columns.
	 *
	 * @var array
	 */
	private $columns;

	/**
	 * Plugins installed.
	 *
	 * @var array[]
	 */
	private $plugins;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'form',
				'plural'   => 'forms',
				'screen'   => 'forms',
			]
		);

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		$this->columns = [
			'source'  => __( 'Source', 'hcaptcha-for-forms-and-more' ),
			'form_id' => __( 'Form Id', 'hcaptcha-for-forms-and-more' ),
			'served'  => __( 'Served', 'hcaptcha-for-forms-and-more' ),
		];

		$this->plugins = get_plugins();
	}

	/**
	 * Retrieve the table columns.
	 *
	 * @return array Array of all the list table columns.
	 */
	public function get_columns(): array {
		return $this->columns;
	}

	/**
	 * Retrieve the table's sortable columns.
	 *
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns(): array {

		return [
			'source'  => [ 'source', false ],
			'form_id' => [ 'form_id', false ],
			'served'  => [ 'served', false ],
		];
	}

	/**
	 * Fetch and set up the final data for the table.
	 */
	public function prepare_items() {
		$hidden                = [];
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $this->columns, $hidden, $sortable ];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		$order   = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'ASC';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'source';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = $this->get_items_per_page( 'hcaptcha_forms_per_page', $this->per_page );
		$offset   = ( $paged - 1 ) * $per_page;
		$args     = [
			'offset'  => $offset,
			'limit'   => $per_page,
			'order'   => $order,
			'orderby' => $orderby,
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

	/**
	 * Column Source.
	 *
	 * @param object $item Item.
	 *
	 * @noinspection PhpUnused PhpUnused.
	 */
	protected function column_source( $item ): string {
		$source = (array) json_decode( $item->source, true );

		foreach ( $source as &$slug ) {
			if ( 'WordPress' === $slug ) {
				continue;
			}

			if ( false === strpos( $slug, '/' ) ) {
				continue;
			}

			$slug = isset( $this->plugins[ $slug ] ) ? $this->plugins[ $slug ]['Name'] : $slug;
		}

		unset( $slug );

		return $this->excerpt( implode( ', ', $source ), 15 );
	}

	/**
	 * Column default.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column name.
	 */
	protected function column_default( $item, $column_name ): string {
		return (string) $item->$column_name;
	}

	/**
	 * Excerpt text.
	 *
	 * @param string $text   Text.
	 * @param int    $length Excerpt length.
	 *
	 * @return string
	 */
	private function excerpt( string $text, int $length = 35 ): string {
		$excerpt = mb_substr( $text, 0, $length );

		ob_start();

		?>
		<span class="hcaptcha-excerpt"><?php echo esc_html( $excerpt ); ?>
			<span class="hcaptcha-hide"><?php echo esc_html( $text ); ?></span>
		</span>
		<?php

		return ob_get_clean();
	}
}
