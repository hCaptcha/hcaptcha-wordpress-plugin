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
	 * Create delayed script.
	 *
	 * @param string $js    js code to wrap in setTimeout().
	 * @param int    $delay Delay in ms.
	 *
	 * @return string
	 * @noinspection JSUnusedAssignment
	 */
	public static function create( string $js, int $delay = 3000 ): string {
		$js = <<<JS
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
			document.removeEventListener( 'mouseenter', load );
			document.removeEventListener( 'click', load );
			window.removeEventListener( 'load', delayedLoad );

$js
		}

		function scrollHandler() {
			if ( ! scrolled ) {
				// Ignore first scroll event, which can be on page load.
				scrolled = true;
				return;
			}

			window.removeEventListener( 'scroll', scrollHandler );
			load();
		}

		function delayedLoad() {
			window.addEventListener( 'scroll', scrollHandler );
			// noinspection JSAnnotator
			const delay = $delay;

			if ( delay >= 0 ) {
				setTimeout( load, delay );
			}
		}

		window.addEventListener( 'touchstart', load );
		document.addEventListener( 'mouseenter', load );
		document.addEventListener( 'click', load );
		window.addEventListener( 'load', delayedLoad );
	} )();
JS;

		return "<script>\n" . HCaptcha::js_minify( $js ) . "\n</script>\n";
	}

	/**
	 * Launch script specified by source url.
	 *
	 * @param array $args  Arguments.
	 * @param int   $delay Delay in ms.
	 */
	public static function launch( array $args, int $delay = 3000 ) {
		$js = <<<JS
			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement('script');
			s.type  = 'text/javascript';
			s.id = 'hcaptcha-api';
JS;

		$js = "$js\n";

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $args as $key => $arg ) {
			if ( 'data' === $key ) {
				foreach ( $arg as $data_key => $data_arg ) {
					$js .= "\t\t\ts.dataset.$data_key = '$data_arg';\n";
				}
				continue;
			}

			$js .= "\t\t\ts['$key'] = '$arg';\n";
		}

		$js .= <<<JS
			s.async = true;
			t.parentNode.insertBefore( s, t );
JS;

		echo self::create( $js, $delay );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
