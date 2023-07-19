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

		return ob_get_clean();
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
			 * @param string     $value   The protection status of a form.
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
		$callback     = 'invisible' === $args['size'] ? 'data-callback="hCaptchaSubmit"' : '';

		?>
		<div
			class="h-captcha"
			data-sitekey="<?php echo esc_attr( $hcaptcha_site_key ); ?>"
			data-theme="<?php echo esc_attr( $hcaptcha_theme ); ?>"
			data-size="<?php echo esc_attr( $args['size'] ); ?>"
			<?php echo wp_kses_post( $callback ); ?>
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
	 * @param string $class Class name.
	 *
	 * @return array
	 */
	public static function get_class_source( string $class ): array {
		foreach ( hcaptcha()->modules as $module ) {
			if ( in_array( $class, (array) $module[2], true ) ) {
				$source = $module[1];

				// For WP Core (empty $source string), return option value.
				return '' === $source ? [ 'WordPress' ] : (array) $source;
			}
		}

		return [];
	}
}
