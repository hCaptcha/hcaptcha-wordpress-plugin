<?php
/**
 * TableBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

// If this file is called directly, abort.
use HCaptcha\Helpers\Utils;
use HCaptcha\Settings\ListPageBase;
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
	 * Status query argument.
	 */
	protected const STATUS_QUERY_ARG = 'event_status';

	/**
	 * Default number of forms to show per page.
	 *
	 * @var int
	 */
	public int $per_page_default = 20;

	/**
	 * Plugin page hook.
	 *
	 * @var string
	 */
	protected string $plugin_page_hook = '';

	/**
	 * Plugins installed.
	 *
	 * @var array[]
	 */
	protected array $plugins = [];

	/**
	 * Columns.
	 *
	 * @var array
	 */
	protected array $columns = [];

	/**
	 * Current event's status.
	 *
	 * @var string
	 */
	protected string $status = Events::STATUS_ACTIVE;

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
		if ( ! Events::is_trash_schema_ready() ) {
			return [];
		}

		if ( Events::STATUS_TRASH === $this->get_status() ) {
			return [
				'restore' => __( 'Restore', 'hcaptcha-for-forms-and-more' ),
				'delete'  => __( 'Delete Permanently', 'hcaptcha-for-forms-and-more' ),
			];
		}

		return [
			'trash' => __( 'Move to Trash', 'hcaptcha-for-forms-and-more' ),
		];
	}

	/**
	 * Get views.
	 *
	 * @return array
	 * @noinspection HtmlUnknownTarget
	 * @noinspection HtmlUnknownAttribute
	 */
	protected function get_views(): array {
		if ( ! Events::is_trash_schema_ready() ) {
			return [];
		}

		$counts = Events::get_status_counts(
			[
				'dates' => $this->get_dates(),
			]
		);

		$views = [
			Events::STATUS_ACTIVE => __( 'All', 'hcaptcha-for-forms-and-more' ),
			Events::STATUS_TRASH  => __( 'Trash', 'hcaptcha-for-forms-and-more' ),
		];

		foreach ( $views as $status => &$label ) {
			$url   = $this->get_status_url( $status );
			$count = $counts[ $status ] ?? 0;
			$class = $status === $this->get_status() ? ' class="current" aria-current="page"' : '';
			$label = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$class,
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		unset( $label );

		return $views;
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
		$source = Utils::json_decode_arr( $item->source );

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

	/**
	 * Get current status.
	 *
	 * @return string
	 */
	protected function get_status(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET[ self::STATUS_QUERY_ARG ] )
			? sanitize_key( wp_unslash( $_GET[ self::STATUS_QUERY_ARG ] ) )
			: Events::STATUS_ACTIVE;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->status = in_array( $status, [ Events::STATUS_ACTIVE, Events::STATUS_TRASH ], true )
			? $status
			: Events::STATUS_ACTIVE;

		return $this->status;
	}

	/**
	 * Get dates from the request.
	 *
	 * @return array
	 */
	protected function get_dates(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$date = isset( $_GET['date'] )
			// We need filter_input here to keep the delimiter intact.
			? filter_input( INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dates = explode( ListPageBase::TIMESPAN_DELIMITER, $date );

		return array_filter( array_map( 'trim', $dates ) );
	}

	/**
	 * Get status view URL.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function get_status_url( string $status ): string {
		$args = [
			'paged' => false,
		];

		if ( Events::STATUS_ACTIVE === $status ) {
			$args[ self::STATUS_QUERY_ARG ] = false;
		} else {
			$args[ self::STATUS_QUERY_ARG ] = $status;
		}

		return add_query_arg( $args );
	}
}
