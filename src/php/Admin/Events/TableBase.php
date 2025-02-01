<?php
/**
 * TableBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

// If this file is called directly, abort.
use WP_List_Table;

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
 * Class TableBase.
 */
abstract class TableBase extends WP_List_Table {

	/**
	 * Default number of forms to show per page.
	 *
	 * @var int
	 */
	public $per_page_default = 20;

	/**
	 * Plugin page hook.
	 *
	 * @var string
	 */
	protected $plugin_page_hook;

	/**
	 * Plugins installed.
	 *
	 * @var array[]
	 */
	protected $plugins;

	/**
	 * Columns.
	 *
	 * @var array
	 */
	protected $columns;

	/**
	 * Class constructor.
	 *
	 * @param string $plugin_page_hook Plugin page hook.
	 */
	public function __construct( string $plugin_page_hook ) {
		parent::__construct(
			[
				'singular' => static::SINGULAR,
				'plural'   => static::PLURAL,
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
	public function init(): void {
		$this->plugins = get_plugins();

		add_action( 'load-' . $this->plugin_page_hook, [ $this, 'add_screen_option' ] );
		add_filter( 'set_screen_option_' . static::ITEMS_PER_PAGE, [ $this, 'set_screen_option' ], 10, 3 );

		set_screen_options();
	}

	/**
	 * Add screen options.
	 *
	 * @return void
	 */
	public function add_screen_option(): void {
		$args = [
			'label'   => __( 'Number of items per page:', 'hcaptcha-for-forms-and-more' ),
			'default' => $this->per_page_default,
			'option'  => static::ITEMS_PER_PAGE,
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
	 * Get bulk actions.
	 *
	 * @global string $comment_status
	 *
	 * @return array
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	protected function get_bulk_actions() {
		$actions = [];

		$actions['trash'] = __( 'Delete', 'hcaptcha-for-forms-and-more' );

		return $actions;
	}

	/**
	 * Generate content for the checkbox column.
	 *
	 * @param object $item The current item.
	 * @return string The checkbox HTML.
	 */
	protected function column_cb( $item ): string {
		$id = isset( $item->id ) ? (int) $item->id : 0;

		return sprintf(
			'<input type="checkbox" name="bulk-checkbox[]" value="%d" />',
			$id
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
	protected function column_name( object $item ): string {
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

		return $this->excerpt( implode( ', ', $source ), 15, $item->source );
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
	 * @param string $source Source.
	 *
	 * @return string
	 */
	protected function excerpt( string $text, int $length = 35, string $source = '' ): string {
		$excerpt = mb_substr( $text, 0, $length );

		ob_start();

		?>
		<span class="hcaptcha-excerpt" data-source="<?php echo esc_attr( $source ); ?>">
			<?php echo esc_html( $excerpt ); ?>
			<span class="hcaptcha-hide"><?php echo esc_html( $text ); ?></span>
		</span>
		<?php

		return ob_get_clean();
	}
}
