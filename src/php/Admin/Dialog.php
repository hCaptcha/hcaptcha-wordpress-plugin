<?php
/**
 * Dialog class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

/**
 * Dialog class.
 */
class Dialog {

	/**
	 * Admin script handle.
	 */
	const HANDLE = 'kagg-dialog';

	/**
	 * Init class.
	 */
	public function init() {
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Print confirmation popup.
	 *
	 * @return void
	 */
	public function print_confirmation_popup() {
		?>
		<div class="kagg-dialog">
			<div class="kagg-dialog-bg">
			</div>
			<div class="kagg-dialog-container">
				<div class="kagg-dialog-box">
					<div class="kagg-dialog-content"></div>
					<div class="kagg-dialog-buttons">
						<button type="button" class="btn btn-ok">
							<?php esc_html_e( 'Ok', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
						<button type="button" class="btn btn-cancel">
							<?php esc_html_e( 'Cancel', 'hcaptcha-for-forms-and-more' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/kagg-dialog$min.js",
			[ 'jquery' ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/kagg-dialog$min.css",
			[],
			constant( 'HCAPTCHA_VERSION' )
		);
	}
}
