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
	 * Priority of the print observation script hook.
	 */
	private const OBSERVATION_SCRIPT_PRIORITY = PHP_INT_MAX - 1000;

	/**
	 * List of scripts already launched.
	 *
	 * @var array
	 */
	private static array $launched = [];

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
	 * Enqueue a script or load it via observe() if the delay API selector is set.
	 *
	 * @param string $handle Script handle to register and enqueue.
	 *
	 * @return void
	 */
	public static function enqueue( string $handle ): void {
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
			self::observe( $delay_api_selector, $handle );

			return;
		}

		wp_enqueue_script( $handle );
	}

	/**
	 * Print observation script.
	 *
	 * Defines the global `hCaptchaObserve( selector, scripts )` function used by small per-call scripts.
	 *
	 * @return void
	 * @noinspection JSUnnecessarySemicolon
	 * @noinspection CommaExpressionJS
	 * @noinspection JSUnresolvedReference
	 */
	public static function print_observation_script(): void {
		if ( ! self::$launched ) {
			return;
		}

		/* language=JS */
		$data =
			"
	hCaptchaObserve = function( selector, scripts ) {
		'use strict';

		scripts = Object.entries( scripts )
			.filter( function( [ handle ] ) {
				return ! document.getElementById( handle + '-js' );
			} )
			.map( function( [ handle, script ] ) {
				return { handle: handle, src: script.src, type: script.type };
			} );

		if ( ! scripts.length ) {
			return;
		}

		const observed = new Set();

		const intersectionObserver = new IntersectionObserver( function( entries ) {
			if ( entries.some( function( e ) { return e.isIntersecting; } ) ) {
				launch();
			}
		} );

		const mutationObserver = new MutationObserver( function() {
			observeNewEls( document.querySelectorAll( selector ) );
		} );

		observeNewEls( document.querySelectorAll( selector ) );
		mutationObserver.observe( document.body, { childList: true, subtree: true } );

		function loadScript( scripts, index = 0 ) {
			if ( index >= scripts.length ) {
				return;
			}

			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement( 'script' );

			s.id     = scripts[index].handle + '-js';
			s.type   = scripts[index].type || 'text/javascript';
			s.src    = scripts[index].src;
			s.onload = function() {
				loadScript( scripts, index + 1 );
			};

			t.parentNode.insertBefore( s, t );
		}

		function launch() {
			const toLoad = scripts.filter( function( script ) {
				return ! document.getElementById( script.handle + '-js' );
			} );

			if ( ! toLoad.length ) {
				return;
			}

			loadScript( toLoad );
		}

		function observeNewEls( els ) {
			els.forEach( function( el ) {
				if ( ! observed.has( el ) ) {
					observed.add( el );
					intersectionObserver.observe( el );
				}
			} );
		}
	};
";

		foreach ( self::$launched as $selector => $handles ) {
			$scripts = [];

			foreach ( $handles as $handle ) {
				if ( ! wp_script_is( $handle, 'registered' ) || wp_script_is( $handle ) ) {
					// Don't print the script if it's already registered and enqueued.
					continue;
				}

				$obj = self::get_script_obj( $handle );

				if ( $obj->src ) {
					$scripts[ $handle ] = [
						'src'  => $obj->src,
						'type' => $obj->extra['type'] ?? '',
					];
				}
			}

			if ( ! $scripts ) {
				continue;
			}

			$selector_js = wp_json_encode( $selector );
			$scripts_js  = wp_json_encode( $scripts );

			$data .=
				/* language=JS */
				"
	hCaptchaObserve( $selector_js, $scripts_js );
";
		}

		wp_print_inline_script_tag( $data );
	}

	/**
	 * Print an inline script that loads scripts by handles when a given element becomes visible.
	 *
	 * Scripts are loaded sequentially in the order the handles are provided.
	 * The main observer logic is printed once; each call emits a small script
	 * that passes selector and sources to the global `hCaptchaObserve` function.
	 *
	 * @param string          $selector CSS selector of the element to observe.
	 * @param string|string[] $handles  Script handles registered in WordPress.
	 *
	 * @return void
	 * @noinspection JSUnresolvedReference
	 * @noinspection CommaExpressionJS
	 */
	private static function observe( string $selector, $handles ): void {
		$handles = (array) $handles;

		$launching = [];

		foreach ( $handles as $handle ) {
			$obj = self::get_script_obj( $handle );

			if ( ! $obj->src ) {
				continue;
			}

			// Include dependencies first.
			self::observe( $selector, $obj->deps );

			// Include script in current handles.
			$launching[] = $handle;
		}

		if ( ! $launching ) {
			return;
		}

		$launched  = self::$launched[ $selector ] ?? [];
		$launching = array_values( array_diff( $launching, $launched ) );

		array_push( $launched, ...$launching );

		self::$launched[ $selector ] = $launched;

		// Print the main script later. Multiple add_action calls result in one hook added.
		add_action( 'wp_print_footer_scripts', [ __CLASS__, 'print_observation_script' ], self::OBSERVATION_SCRIPT_PRIORITY );
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

	/**
	 * Get a script object.
	 *
	 * @param string $handle Script handle.
	 *
	 * @return object
	 */
	private static function get_script_obj( string $handle ): object {
		$wp_scripts = wp_scripts();
		$obj        = clone( $wp_scripts->registered[ $handle ] ?? (object) [] );

		$obj->src  = $obj->src ?? '';
		$obj->deps = $obj->deps ?? [];
		$obj->ver  = $obj->ver ?? '';

		// Make URL absolute if needed.
		if ( $obj->src && 0 !== strpos( $obj->src, 'http' ) ) {
			$obj->src = $wp_scripts->base_url . $obj->src;
		}

		if ( $obj->ver ) {
			$obj->src = add_query_arg( 'ver', $obj->ver, $obj->src );
		}

		return $obj;
	}
}
