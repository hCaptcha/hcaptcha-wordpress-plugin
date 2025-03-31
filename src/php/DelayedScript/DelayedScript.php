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
				// Ignore first scroll event, which can be on page load.
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

			window.addEventListener( 'touchstart', load );
			document.body.addEventListener( 'mouseenter', load );
			document.body.addEventListener( 'click', load );
			window.addEventListener( 'keydown', load );
			window.addEventListener( 'scroll', scrollHandler );
		} );
	} )();
";

		return "<script>\n" . HCaptcha::js_minify( $js ) . "\n</script>\n";
	}

	/**
	 * Launch script specified by source url.
	 *
	 * @param array $args  Arguments.
	 * @param int   $delay Delay in ms. Negative means no delay, wait for user interaction.
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
