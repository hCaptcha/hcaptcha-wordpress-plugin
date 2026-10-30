<?php
/**
 * Advanced class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Settings page "Advanced".
 */
class Advanced extends PluginSettingsBase {
	/**
	 * Admin style handle.
	 */
	public const HANDLE = 'hcaptcha-advanced';

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Advanced', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'advanced';
	}

	/**
	 * Init form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array_filter(
			General::get_all_form_fields(),
			static function ( array $field ): bool {
				return in_array(
					$field['section'],
					[
						General::SECTION_ENTERPRISE,
						General::SECTION_CONTENT,
						General::SECTION_OTHER,
						General::SECTION_STATISTICS,
					],
					true
				);
			}
		);

		$enterprise_fields = array_filter(
			$this->form_fields,
			static function ( array $field ): bool {
				return General::SECTION_ENTERPRISE === $field['section'];
			}
		);

		$this->form_fields = array_diff_key( $this->form_fields, $enterprise_fields ) + $enterprise_fields;
	}

	/**
	 * Setup settings fields.
	 *
	 * @return void
	 */
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$settings = hcaptcha()->settings();
		$license  = $settings ? $settings->get_license() : 'free';

		if ( 'enterprise' !== $license ) {
			foreach ( $this->form_fields as $field_name => $field ) {
				if ( General::SECTION_ENTERPRISE === $field['section'] ) {
					$this->form_fields[ $field_name ]['disabled'] = true;
				}
			}
		}

		parent::setup_fields();
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 * @return void
	 */
	public function section_callback( array $arguments ): void {
		$id = $arguments['id'];

		if ( General::SECTION_CONTENT === $id ) {
			$this->print_header();
		}

		$titles = [
			General::SECTION_ENTERPRISE => __( 'Enterprise', 'hcaptcha-for-forms-and-more' ),
			General::SECTION_CONTENT    => __( 'Content', 'hcaptcha-for-forms-and-more' ),
			General::SECTION_OTHER      => __( 'Other', 'hcaptcha-for-forms-and-more' ),
			General::SECTION_STATISTICS => __( 'Statistics', 'hcaptcha-for-forms-and-more' ),
		];

		if ( ! isset( $titles[ $id ] ) ) {
			return;
		}

		$title    = $titles[ $id ];
		$open     = $this->get_section_open_status( $id );
		$settings = hcaptcha()->settings();
		$license  = $settings ? $settings->get_license() : 'free';
		$disabled = General::SECTION_ENTERPRISE === $id && 'enterprise' !== $license;

		if ( $disabled ) {
			$open   = false;
			$title .= ' - ' . __( 'hCaptcha Enterprise Required', 'hcaptcha-for-forms-and-more' );
		}

		$class  = $open ? '' : ' closed';
		$class .= $disabled ? ' disabled' : '';
		?>
		<h3 class="togglable hcaptcha-section-<?php echo esc_attr( $id ); ?><?php echo esc_attr( $class ); ?>">
			<span class="hcaptcha-section-header-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<span class="hcaptcha-section-header-toggle"></span>
		</h3>
		<?php
	}

	/**
	 * Enqueue settings style.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$style_version = constant( 'HCAPTCHA_VERSION' );

		if ( '' === $this->min_suffix ) {
			$style_mtime    = filemtime( constant( 'HCAPTCHA_PATH' ) . '/assets/css/advanced.css' );
			$style_version .= false === $style_mtime ? '' : '-' . $style_mtime;
		}

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/advanced$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE ],
			$style_version
		);
	}
}
