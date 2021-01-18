<?php
/**
 * Settings page file.
 *
 * @package hcaptcha-wp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit;
	// @codeCoverageIgnoreEnd
}

hcap_display_options_page();

/**
 * Display options page.
 */
function hcap_display_options_page() {
	$updated = false;

	if (
		isset( $_POST['hcaptcha_settings_nonce'], $_POST['submit'] ) &&
		wp_verify_nonce(
			filter_var( wp_unslash( $_POST['hcaptcha_settings_nonce'] ), FILTER_SANITIZE_STRING ),
			'hcaptcha_settings'
		)
	) {
		foreach ( hcap_options() as $option_name => $option ) {
			$option_value = filter_var(
				wp_unslash( isset( $_POST[ $option_name ] ) ? $_POST[ $option_name ] : '' ),
				FILTER_SANITIZE_STRING
			);

			if ( ! $option_value && 'checkbox' === $option['type'] ) {
				$option_value = 'off';
			}

			update_option( $option_name, $option_value );
		}

		$updated = true;
	}

	?>
	<div class="wrap">
		<?php
		if ( $updated ) {
			?>
			<div id="message" class="updated fade">
				<p>
					<?php esc_html_e( 'Settings Updated', 'hcaptcha-for-forms-and-more' ); ?>
				</p>
			</div>
			<?php
		}
		?>
		<h3><?php esc_html_e( 'hCaptcha Settings', 'hcaptcha-for-forms-and-more' ); ?></h3>
		<h3>
			<?php
			echo wp_kses_post(
				__(
					'In order to use <a href="https://hCaptcha.com/?r=wp" target="_blank">hCaptcha</a> please register <a href="https://hCaptcha.com/?r=wp" target="_blank">here</a> to get your site key and secret key.',
					'hcaptcha-for-forms-and-more'
				)
			);
			?>
		</h3>
		<form method="post" action="">
			<?php hcap_display_options(); ?>
			<p>
				<input
						type="submit"
						value="<?php esc_html_e( 'Save hCaptcha Settings', 'hcaptcha-for-forms-and-more' ); ?>"
						class="button button-primary"
						name="submit"/>
			</p>
			<?php
			wp_nonce_field( 'hcaptcha_settings', 'hcaptcha_settings_nonce' );
			?>
		</form>
	</div>
	<?php
}

/**
 * Display plugin options.
 */
function hcap_display_options() {
	$options = hcap_options();

	array_walk(
		$options,
		function ( $option, $option_name ) {
			if ( 'checkbox' !== $option['type'] ) {
				hcap_display_option( $option_name, $option );
			}
		}
	);

	?>
	<strong><?php esc_html_e( 'Enable/Disable Features', 'hcaptcha-for-forms-and-more' ); ?></strong>
	<br><br>
	<?php

	array_walk(
		$options,
		function ( $option, $option_name ) {
			if ( 'checkbox' === $option['type'] ) {
				hcap_display_option( $option_name, $option );
			}
		}
	);
}

/**
 * Display an option.
 *
 * @param string $option_name Option name.
 * @param array  $option      Option.
 *
 * @todo add labels to input and select.
 */
function hcap_display_option( $option_name, $option ) {
	$option_value = get_option( $option_name );
	$description  = isset( $option['description'] ) ? $option['description'] : '';
	switch ( $option['type'] ) {
		case 'text':
		case 'password':
		case 'number':
			?>
			<strong>
				<?php echo esc_html( $option['label'] ); ?>
			</strong>
			<br><br>
			<input
					type="<?php echo esc_attr( $option['type'] ); ?>" size="50"
					id="<?php echo esc_attr( $option_name ); ?>"
					name="<?php echo esc_attr( $option_name ); ?>"
					value="<?php echo esc_html( $option_value ); ?>"/>
			<?php
			if ( $description ) {
				echo '<br>' . wp_kses_post( $description );
			}
			?>
			<br><br>
			<?php
			break;
		case 'checkbox':
			?>
			<input
					type="checkbox"
					id="<?php echo esc_attr( $option_name ); ?>"
					name="<?php echo esc_attr( $option_name ); ?>"
				<?php checked( 'on', $option_value ); ?>/>
			&nbsp;
			<span><?php echo esc_html( $option['label'] ); ?></span>
			<br><br>
			<?php
			break;
		case 'select':
			if ( ! empty( $option['options'] ) && is_array( $option['options'] ) ) {
				?>
				<strong><?php echo esc_html( $option['label'] ); ?></strong>
				<br><br>
				<select
						id="<?php echo esc_attr( $option_name ); ?>"
						name="<?php echo esc_attr( $option_name ); ?>">
					<?php
					foreach ( $option['options'] as $key => $value ) {
						?>
						<option
								value="<?php echo esc_attr( $key ); ?>"
							<?php selected( $key, $option_value ); ?>>
							<?php echo esc_html( $value ); ?>
						</option>
						<?php
					}
					?>
				</select>
				<br><br>
				<?php
			}
			break;
		default:
			break;
	}
}
