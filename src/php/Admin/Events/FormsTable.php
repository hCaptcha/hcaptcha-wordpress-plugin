<?php
/**
 * FormsTable class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

use HCaptcha\Settings\ListPageBase;
use WP_List_Table;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

if ( ! class_exists( 'WP_List_Table', false ) ) {
	// IMPORTANT NOTICE:
	// This line is needed to prevent fatal errors in the third-party plugins.
	// We know that Jetpack (probably others also) can load WP classes during cron jobs.
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List forms in the table.
 */
class FormsTable extends WP_List_Table {

	/**
	 * Forms per page option.
	 */
	const FORMS_PER_PAGE = 'hcaptcha_forms_per_page';

	/**
	 * Plugin page hook.
	 *
	 * @var string
	 */
	private $plugin_page_hook;

	/**
	 * Default number of forms to show per page.
	 *
	 * @var int
	 */
	public $per_page_default = 20;

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
	 *
	 * @param string $plugin_page_hook Plugin page hook.
	 */
	public function __construct( string $plugin_page_hook ) {
		parent::__construct(
			[
				'singular' => 'form',
				'plural'   => 'forms',
				'screen'   => $plugin_page_hook,
			]
		);

		$this->plugin_page_hook = $plugin_page_hook;

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		$this->columns = [
			'name'    => __( 'Source', 'hcaptcha-for-forms-and-more' ),
			'form_id' => __( 'Form Id', 'hcaptcha-for-forms-and-more' ),
			'served'  => __( 'Served', 'hcaptcha-for-forms-and-more' ),
		];

		$this->plugins = get_plugins();

		add_action( 'load-' . $this->plugin_page_hook, [ $this, 'add_screen_option' ] );
		add_filter( 'set_screen_option_' . self::FORMS_PER_PAGE, [ $this, 'set_screen_option' ], 10, 3 );

		set_screen_options();
	}

	/**
	 * Add screen options.
	 *
	 * @return void
	 */
	public function add_screen_option() {
		$args = [
			'label'   => __( 'Number of items per page:', 'hcaptcha-for-forms-and-more' ),
			'default' => $this->per_page_default,
			'option'  => self::FORMS_PER_PAGE,
		];

		add_screen_option( 'per_page', $args );
	}

	/**
	 * Set screen option.
	 *
	 * @param mixed  $screen_option  The value to save instead of the option value.
	 *                               Default false (to skip saving the current option).
	 * @param string $option         The option name.
	 * @param mixed  $value          The option value.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function set_screen_option( $screen_option, string $option, $value ) {
		return $value;
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
			'name'    => [
				'name',
				false,
				__( 'Source', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Source.' ),
			],
			'form_id' => [
				'form_id',
				false,
				__( 'Form Id', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Form Id.' ),
			],
			'served'  => [
				'served',
				false,
				__( 'Served', 'hcaptcha-for-forms-and-more' ),
				__( 'Table ordered by Served Count.' ),
			],
		];
	}

	/**
	 * Fetch and set up the final data for the table.
	 */
	public function prepare_items() {
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
		$per_page = $this->get_items_per_page( self::FORMS_PER_PAGE, $this->per_page_default );
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

	/**
	 * Column Source.
	 * Has 'name' slug not to be hidden.
	 * WP has no filter for special columns.
	 *
	 * @see          \WP_Screen::render_list_table_columns_preferences.
	 *
	 * @param object $item Item.
	 *
	 * @noinspection PhpUnused PhpUnused.
	 */
	protected function column_name( $item ): string {
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
