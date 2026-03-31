<?php
/**
 * DelayedScript class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\DelayedScript;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class DelayedScript
 */
class DelayedScript {

	/**
	 * Create a delayed script.
	 *
	 * @param string $js    js code to wrap in setTimeout().
	 * @param int    $delay Delay in ms. Negative means no delay, wait for user interaction.
	 *
	 * @return string
	 * @noinspection JSUnusedAssignment
	 */
	public static function create( string $js, int $delay = -1 ): string {
		/* language=JS */
		$js = "
	( () => {
		'use strict';

		let loaded = false,
			scrolled = false,
			timerId;

		function load() {
			if ( loaded ) {
				return;
			}

			loaded = true;
			clearTimeout( timerId );

			window.removeEventListener( 'touchstart', load );
			document.body.removeEventListener( 'mouseenter', load );
			document.body.removeEventListener( 'click', load );
			window.removeEventListener( 'keydown', load );
			window.removeEventListener( 'scroll', scrollHandler );

$js
		}

		function scrollHandler() {
			if ( ! scrolled ) {
				// Ignore the first scroll event, which can be on page load.
				scrolled = true;
				return;
			}

			load();
		}

		document.addEventListener( 'hCaptchaBeforeAPI', function() {
			// noinspection JSAnnotator
			const delay = $delay;

			if ( delay >= 0 ) {
				timerId = setTimeout( load, delay );
			}

			const options = { passive: true };

			window.addEventListener( 'touchstart', load, options );
			document.body.addEventListener( 'mouseenter', load );
			document.body.addEventListener( 'click', load );
			window.addEventListener( 'keydown', load );
			window.addEventListener( 'scroll', scrollHandler, options );
		} );
	} )();
";

		return "<script>\n" . HCaptcha::js_minify( $js ) . "\n</script>\n";
	}

	/**
	 * Enqueue a script or load it via interact() if the delay API selector is set.
	 *
	 * @param string|array $handles Script handles to register and enqueue.
	 *
	 * @return void
	 */
	public static function enqueue( $handles ): void {
		$handles = (array) $handles;

		/**
		 * Filters delay API selector.
		 *
		 * When set, the hcaptcha.js script will be loaded only when the specified element is visible.
		 * This can improve page load performance by deferring the API script until it's necessary.
		 *
		 * @param string $delay_api_selector CSS selector of the element to observe.
		 */
		$delay_api_selector = trim( (string) apply_filters( 'hcap_delay_api_selector', '' ) );

		if ( $delay_api_selector ) {
			self::interact( $delay_api_selector, $handles );

			return;
		}

		foreach ( $handles as $handle ) {
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Print an inline script that loads scripts by handles when a given element becomes visible.
	 *
	 * Scripts are loaded sequentially in the order the handles are provided.
	 *
	 * @param string   $selector CSS selector of the element to observe.
	 * @param string[] $handles  Script handles registered in WordPress.
	 *
	 * @return void
	 */
	public static function interact( string $selector, array $handles ): void {
		$wp_scripts = wp_scripts();
		$sources    = [];

		foreach ( $handles as $handle ) {
			$obj = $wp_scripts->registered[ $handle ] ?? null;
			$src = $obj->src ?? '';
			$ver = $obj->ver ?? '';

			// Make URL absolute if needed.
			if ( $src && 0 !== strpos( $src, 'http' ) ) {
				$src = $wp_scripts->base_url . $src;
			}

			if ( $ver ) {
				$src = add_query_arg( 'ver', $ver, $src );
			}

			$sources[] = $src;
		}

		$selector_js = wp_json_encode( $selector );
		$sources_js  = wp_json_encode( $sources );

		/* language=JS */
		wp_print_inline_script_tag(
			"
	( () => {
		'use strict';
	
		// noinspection JSAnnotator
		const selector = $selector_js;
		const el       = document.querySelector( selector );
	
		if ( ! el ) {
			return;
		}
	
		// noinspection JSAnnotator
		const sources = $sources_js;
	
		function loadScript( index ) {
			if ( index >= sources.length ) {
				return;
			}
	
			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement( 'script' );
	
			s.type   = 'text/javascript';
			s.src    = sources[index];
			s.onload = function() {
				loadScript( index + 1 );
			};
	
			t.parentNode.insertBefore( s, t );
		}
	
		const observer = new IntersectionObserver( function( entries ) {
			if ( entries[0].isIntersecting ) {
				observer.disconnect();
				loadScript( 0 );
			}
		} );
	
		observer.observe( el );
	} )();
"
		);
	}

	/**
	 * Launch script specified by a source url.
	 *
	 * @param array $args  Arguments.
	 * @param int   $delay Delay in ms. Negative means no delay, wait for user interaction.
	 *
	 * @noinspection JSUnusedLocalSymbols
	 */
	public static function launch( array $args, int $delay = -1 ): void {
		unset( $args['id'], $args['async'] );

		/* language=JS */
		$js = "
			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement( 'script' );
			s.type  = 'text/javascript';
			s.id = 'hcaptcha-api';
";

		$js = trim( $js, " \n\r" );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $args as $key => $arg ) {
			if ( 'data' === $key ) {
				foreach ( $arg as $data_key => $data_arg ) {
					$js .= "\t\t\ts.setAttribute( 'data-' + '$data_key', '$data_arg' );\n";
				}
				continue;
			}

			$js .= "\n\t\t\ts['$key'] = '$arg';";
		}

		/* language=JS */
		$js .= '
			s.async = true;
			t.parentNode.insertBefore( s, t );
';

		$js = trim( $js, " \n\r" );

		echo self::create( $js, $delay );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
