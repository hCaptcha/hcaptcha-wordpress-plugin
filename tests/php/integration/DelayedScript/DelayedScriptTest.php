<?php
/**
 * DelayedScriptTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\DelayedScript;

use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test DelayedScriptTest class.
 *
 * @group delayed-script
 */
class DelayedScriptTest extends HCaptchaWPTestCase {

	/**
	 * Test create().
	 *
	 * @noinspection BadExpressionStatementJS
	 * @noinspection JSUnresolvedReference
	 * @noinspection JSUnusedLocalSymbols
	 */
	public function test_create(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$js = "\t\t\tconst some = 1;";

		$expected = <<<'JS'
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

			const some = 1;
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
			const delay = -1;

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
JS;

		$expected = "<script>\n$expected\n</script>\n";

		self::assertSame( $expected, DelayedScript::create( $js ) );

		$expected = str_replace( '3000', '-1', $expected );

		self::assertSame( $expected, DelayedScript::create( $js, - 1 ) );
	}

	/**
	 * Test launch().
	 *
	 * @noinspection BadExpressionStatementJS
	 */
	public function test_launch(): void {
		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				return 'SCRIPT_DEBUG' === $constant_name;
			}
		);

		FunctionMocker::replace(
			'constant',
			static function ( $name ) {
				return 'SCRIPT_DEBUG' === $name;
			}
		);

		$expected = <<<'JS'
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

			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement( 'script' );
			s.type  = 'text/javascript';
			s.id = 'hcaptcha-api';
			s['src'] = 'https://js.hcaptcha.com/1/api.js';
			s.async = true;
			t.parentNode.insertBefore( s, t );
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
			const delay = -1;

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
JS;

		$expected = "<script>\n$expected\n</script>\n";

		$src  = 'https://js.hcaptcha.com/1/api.js';
		$args = [ 'src' => $src ];

		ob_start();
		DelayedScript::launch( $args );
		self::assertSame( $expected, ob_get_clean() );

		$expected = str_replace( '3000', '-1', $expected );

		ob_start();
		DelayedScript::launch( $args, - 1 );
		self::assertSame( $expected, ob_get_clean() );
	}
}
