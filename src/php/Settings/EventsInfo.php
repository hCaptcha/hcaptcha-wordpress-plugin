<?php
/**
 * EventsInfo class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\Events\ListTable;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class EventsInfo
 *
 * Settings page "Events".
 */
class EventsInfo extends PluginSettingsBase {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'hcaptcha-events';

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Events', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'events';
	}

	/**
	 * Get tab name.
	 *
	 * @return string
	 */
	protected function tab_name(): string {
		return 'events';
	}

	/**
	 * Enqueue class scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/events$this->min_prefix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function section_callback( array $arguments ) {
		?>
		<h2>
			<?php echo esc_html( $this->page_title() ); ?>
		</h2>
		<div id="hcaptcha-events-wrap">
			<?php
			$list_table = new ListTable();

			$list_table->prepare_items();
			$list_table->display();
			?>
		</div>
		<?php
	}
}
