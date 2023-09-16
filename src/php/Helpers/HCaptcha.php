<?php
/**
 * HCaptcha class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Class HCaptcha.
 */
class HCaptcha {
	const HCAPTCHA_WIDGET_ID = 'hcaptcha-widget-id';

	/**
	 * Default widget id.
	 *
	 * @var array
	 */
	private static $default_id = [
		'source'  => [],
		'form_id' => 0,
	];

	/**
	 * Get hCaptcha form.
	 *
	 * @param array $args Arguments.
	 *
	 * @return string
	 */
	public static function form( array $args = [] ): string {
		ob_start();
		self::form_display( $args );

		return (string) ob_get_clean();
	}

	/**
	 * Display hCaptcha form.
	 *
	 * @param array $args Arguments.
	 */
	public static function form_display( array $args = [] ) {
		$settings          = hcaptcha()->settings();
		$hcaptcha_site_key = $settings->get_site_key();
		$hcaptcha_theme    = $settings->get( 'theme' );
		$hcaptcha_size     = $settings->get( 'size' );
		$allowed_sizes     = [ 'normal', 'compact', 'invisible' ];

		$args = wp_parse_args(
			$args,
			[
				'action'  => '', // Action name for wp_nonce_field.
				'name'    => '', // Nonce name for wp_nonce_field.
				'auto'    => false, // Whether a form has to be auto-verified.
				'size'    => $hcaptcha_size, // The hCaptcha widget size.
				'id'      => [], // hCaptcha widget id.
				/**
				 * Example of id:
				 * [
				 *   'source'  => ['gravityforms/gravityforms.php'],
				 *   'form_id' => 23
				 * ]
				 */
				'protect' => true,
			]
		);

		if ( $args['id'] ) {
			$id            = (array) $args['id'];
			$id['source']  = (array) ( $id['source'] ?? [] );
			$id['form_id'] = $id['form_id'] ?? 0;

			/**
			 * Filters the protection status of a form.
			 *
			 * @param bool       $value   The protection status of a form.
			 * @param string[]   $source  The source of the form (plugin, theme, WordPress Core).
			 * @param int|string $form_id Form id.
			 */
			if (
				! $args['protect'] ||
				! apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] )
			) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				$encoded_id = base64_encode( wp_json_encode( $id ) );
				$widget_id  = $encoded_id . '-' . wp_hash( $encoded_id );
				?>
				<input
					type="hidden"
					class="<?php echo esc_attr( self::HCAPTCHA_WIDGET_ID ); ?>"
					name="<?php echo esc_attr( self::HCAPTCHA_WIDGET_ID ); ?>"
					value="<?php echo esc_attr( $widget_id ); ?>">
				<?php

				hcaptcha()->form_shown = true;

				return;
			}
		}

		$args['auto'] = filter_var( $args['auto'], FILTER_VALIDATE_BOOLEAN );
		$args['size'] = in_array( $args['size'], $allowed_sizes, true ) ? $args['size'] : $hcaptcha_size;

		?>
		<div
			class="h-captcha"
			data-sitekey="<?php echo esc_attr( $hcaptcha_site_key ); ?>"
			data-theme="<?php echo esc_attr( $hcaptcha_theme ); ?>"
			data-size="<?php echo esc_attr( $args['size'] ); ?>"
			data-auto="<?php echo $args['auto'] ? 'true' : 'false'; ?>">
		</div>
		<?php

		if ( ! empty( $args['action'] ) && ! empty( $args['name'] ) ) {
			wp_nonce_field( $args['action'], $args['name'] );
		}

		hcaptcha()->form_shown = true;
	}

	/**
	 * Whether form protection is enabled/disabled via hCaptcha widget id.
	 *
	 * Return false(protection disabled) in only one case:
	 * when $_POST['hcaptcha-widget-id'] contains encoded id with proper hash
	 * and hcap_protect_form filter confirms that form referenced in widget id is not protected.
	 *
	 * @return bool
	 */
	public static function is_protection_enabled(): bool {
		// Nonce is checked in hcaptcha_verify_post().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$widget_id = isset( $_POST[ self::HCAPTCHA_WIDGET_ID ] ) ?
			filter_var( wp_unslash( $_POST[ self::HCAPTCHA_WIDGET_ID ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $widget_id ) {
			return true;
		}

		list( $encoded_id, $hash ) = explode( '-', $widget_id );

		$id = wp_parse_args(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			(array) json_decode( base64_decode( $encoded_id ), true ),
			self::$default_id
		);

		return ! (
			wp_hash( $encoded_id ) === $hash &&
			! apply_filters( 'hcap_protect_form', true, $id['source'], $id['form_id'] )
		);
	}

	/**
	 * Get hcaptcha widget id from $_POST.
	 *
	 * @return array
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	public static function get_widget_id(): array {
		// Nonce is checked in hcaptcha_verify_post().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$widget_id = isset( $_POST[ self::HCAPTCHA_WIDGET_ID ] ) ?
			filter_var( wp_unslash( $_POST[ self::HCAPTCHA_WIDGET_ID ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $widget_id ) {
			return self::$default_id;
		}

		list( $encoded_id, $hash ) = explode( '-', $widget_id );

		return wp_parse_args(
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			(array) json_decode( base64_decode( $encoded_id ), true ),
			self::$default_id
		);
	}

	/**
	 * Get source which class serves.
	 *
	 * @param string $class_name Class name.
	 *
	 * @return array
	 */
	public static function get_class_source( string $class_name ): array {
		foreach ( hcaptcha()->modules as $module ) {
			if ( in_array( $class_name, (array) $module[2], true ) ) {
				$source = $module[1];

				// For WP Core (empty $source string), return option value.
				return '' === $source ? [ 'WordPress' ] : (array) $source;
			}
		}

		return [];
	}

	/**
	 * Get hCaptcha plugin notice.
	 *
	 * @return string[]
	 * @noinspection HtmlUnknownTarget
	 */
	public static function get_hcaptcha_plugin_notice(): array {
		$url                   = admin_url( 'options-general.php?page=hcaptcha&tab=general' );
		$notice['label']       = esc_html__( 'hCaptcha plugin is active', 'hcaptcha-for-forms-and-more' );
		$notice['description'] = wp_kses_post(
			sprintf(
			/* translators: 1: link to the General setting page */
				__( 'When hCaptcha plugin is active and integration is on, hCaptcha settings must be modified on the %1$s.', 'hcaptcha-for-forms-and-more' ),
				sprintf(
					'<a href="%s" target="_blank">General settings page</a>',
					esc_url( $url )
				)
			)
		);

		return $notice;
	}

	/**
	 * Retrieves the number of times a filter has been applied during the current request.
	 *
	 * Introduced in WP 6.1.0.
	 *
	 * @global int[] $wp_filters Stores the number of times each filter was triggered.
	 *
	 * @param string $hook_name The name of the filter hook.
	 * @return int The number of times the filter hook has been applied.
	 */
	public static function did_filter( string $hook_name ): int {
		global $wp_filters;

		return $wp_filters[ $hook_name ] ?? 0;
	}
}
