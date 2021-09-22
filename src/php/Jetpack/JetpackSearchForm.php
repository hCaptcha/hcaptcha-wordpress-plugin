<?php
/**
 * JetpackSearchForm class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Jetpack;

/**
 * Class JetpackSearchForm
 */
class JetpackSearchForm extends JetpackBase {

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		parent::init_hooks();

		add_action( 'the_widget', [ $this, 'the_widget' ], 10, 3 );
	}

	/**
	 * Fires before rendering the requested widget.
	 *
	 * @param string $widget   The widget's class name.
	 * @param array  $instance The current widget instance's settings.
	 * @param array  $args     An array of the widget's sidebar arguments.
	 */
	public function the_widget( $widget, $instance, $args ) {
		if ( 'Jetpack_Search_Widget' === $widget ) {
			add_filter( 'render_block_core/legacy-widget', [ $this, 'search_widget' ], 10, 2 );
			remove_action( 'the_widget', [ $this, 'the_widget' ] );
		}
	}

	/**
	 * Filters the content of a single block.
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block         The full block, including name and attributes.
	 */
	public function search_widget( $block_content, $block ) {
		if (
			preg_match(
				'~<div class="jetpack-search-form">[\s\S]*?<form [\s\S]*?(</form>)[\s\S]*?</div>~',
				$block_content,
				$matches
			)
		) {
			$replace =
				'[hcaptcha]' .
				wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false ) .
				$matches[1];

			$block_content = str_replace( $matches[1], $replace, $block_content );

			remove_filter( 'render_block_core/legacy-widget', [ $this, 'search_widget' ] );
		}

		return $block_content;
	}

	/**
	 * Add hCaptcha to Jetpack contact form.
	 *
	 * @param string $content Content.
	 *
	 * @return string|string[]|null
	 */
	public function jetpack_form( $content ) {
		// Jetpack search form.
		return preg_replace_callback(
			'~(?:<div class="jetpack-search-form"[\s\S]*)?(<input type="submit"[\s\S]*>)[\s\S]*</div>~U',
			[ $this, 'search_callback' ],
			$content
		);
	}

	/**
	 * Add hCaptcha shortcode to the provided shortcode for Jetpack block contact form.
	 *
	 * @param array $matches Matches.
	 *
	 * @return string
	 */
	public function search_callback( $matches ) {
		$replace = $matches[1] . wp_nonce_field( 'hcaptcha_jetpack', 'hcaptcha_jetpack_nonce', true, false );

		if ( ! preg_match( '~\[hcaptcha]~', $matches[0] ) ) {
			$replace = '[hcaptcha]' . $replace;
		}

		return str_replace(
			$matches[1],
			$replace,
			$matches[0]
		);
	}
}
