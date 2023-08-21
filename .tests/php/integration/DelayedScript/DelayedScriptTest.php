<?php
/**
 * DelayedScriptTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\DelayedScript;

use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

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
	 */
	public function test_create() {
		$js = 'some js script';

		$expected = '		<script>
			( () => {
				\'use strict\';

				let loaded = false,
					scrolled = false,
					timerId;

				function load() {
					if ( loaded ) {
						return;
					}

					loaded = true;
					clearTimeout( timerId );

					window.removeEventListener( \'touchstart\', load );
					document.removeEventListener( \'mouseenter\', load );
					document.removeEventListener( \'click\', load );
					window.removeEventListener( \'load\', delayedLoad );

					some js script				}

				function scrollHandler() {
					if ( ! scrolled ) {
						// Ignore first scroll event, which can be on page load.
						scrolled = true;
						return;
					}

					window.removeEventListener( \'scroll\', scrollHandler );
					load();
				}

				function delayedLoad() {
					window.addEventListener( \'scroll\', scrollHandler );
					const delay = 3000;

					if ( delay >= 0 ) {
						setTimeout( load, delay );
					}
				}

				window.addEventListener( \'touchstart\', load );
				document.addEventListener( \'mouseenter\', load );
				document.addEventListener( \'click\', load );
				window.addEventListener( \'load\', delayedLoad );
			} )();
		</script>

		';

		self::assertSame( $expected, DelayedScript::create( $js ) );

		$expected = str_replace( '3000', '-1', $expected );

		self::assertSame( $expected, DelayedScript::create( $js, - 1 ) );
	}

	/**
	 * Test launch().
	 *
	 * @noinspection BadExpressionStatementJS
	 */
	public function test_launch() {
		$expected = '		<script>
			( () => {
				\'use strict\';

				let loaded = false,
					scrolled = false,
					timerId;

				function load() {
					if ( loaded ) {
						return;
					}

					loaded = true;
					clearTimeout( timerId );

					window.removeEventListener( \'touchstart\', load );
					document.removeEventListener( \'mouseenter\', load );
					document.removeEventListener( \'click\', load );
					window.removeEventListener( \'load\', delayedLoad );

							const t = document.getElementsByTagName( \'script\' )[0];
		const s = document.createElement(\'script\');
		s.type  = \'text/javascript\';
		s.id = \'hcaptcha-api\';
		s[\'src\'] = \'https://js.hcaptcha.com/1/api.js\';
		s.async = true;
		t.parentNode.insertBefore( s, t );
						}

				function scrollHandler() {
					if ( ! scrolled ) {
						// Ignore first scroll event, which can be on page load.
						scrolled = true;
						return;
					}

					window.removeEventListener( \'scroll\', scrollHandler );
					load();
				}

				function delayedLoad() {
					window.addEventListener( \'scroll\', scrollHandler );
					const delay = 3000;

					if ( delay >= 0 ) {
						setTimeout( load, delay );
					}
				}

				window.addEventListener( \'touchstart\', load );
				document.addEventListener( \'mouseenter\', load );
				document.addEventListener( \'click\', load );
				window.addEventListener( \'load\', delayedLoad );
			} )();
		</script>

		';

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
