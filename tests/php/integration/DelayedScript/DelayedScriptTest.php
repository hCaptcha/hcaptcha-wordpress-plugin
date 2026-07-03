<?php
/**
 * DelayedScriptTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\DelayedScript;

use HCaptcha\DelayedScript\DelayedScript;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test DelayedScriptTest class.
 *
 * @group delayed-script
 */
class DelayedScriptTest extends HCaptchaWPTestCase {

	/**
	 * Reset the static state after each test.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function tearDown(): void {
		remove_all_filters( 'hcap_delay_api_selector' );

		$prop = ( new ReflectionClass( DelayedScript::class ) )->getProperty( 'launched' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );

		parent::tearDown();
	}

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
				// Ignore the first scroll event, which can be on page load.
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

			const options = { passive: true };

			window.addEventListener( 'touchstart', load, options );
			document.body.addEventListener( 'mouseenter', load );
			document.body.addEventListener( 'click', load );
			window.addEventListener( 'keydown', load );
			window.addEventListener( 'scroll', scrollHandler, options );
		} );
	} )();
JS;

		$expected = "<script>\n$expected\n</script>\n";

		self::assertSame( $expected, DelayedScript::create( $js ) );

		$expected = str_replace( '3000', '-1', $expected );

		self::assertSame( $expected, DelayedScript::create( $js, - 1 ) );
	}

	/**
	 * Test create() with a custom delay API event.
	 *
	 * @noinspection BadExpressionStatementJS
	 * @noinspection JSUnusedLocalSymbols
	 */
	public function test_create_with_delay_api_event(): void {
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

		$js              = "\t\t\tconst some = 1;";
		$delay_api_event = 'hcap:load-api';

		$expected = <<<'JS'
	( () => {
		'use strict';

		// noinspection JSAnnotator
		const delayApiEvent = "hcap:load-api";
		let loaded = false;

		function load() {
			if ( loaded ) {
				return;
			}

			loaded = true;
			document.removeEventListener( delayApiEvent, load );

			const some = 1;
		}

		document.addEventListener( 'hCaptchaBeforeAPI', function() {
			document.addEventListener( delayApiEvent, load );
		} );
	} )();
JS;

		$expected = "<script>\n$expected\n</script>\n";
		$output   = DelayedScript::create( $js, 3000, $delay_api_event );

		self::assertSame( $expected, $output );
		self::assertStringNotContainsString( 'setTimeout', $output );
		self::assertStringNotContainsString( 'touchstart', $output );
		self::assertStringNotContainsString( 'mouseenter', $output );
		self::assertStringNotContainsString( 'keydown', $output );
		self::assertStringNotContainsString( 'scrollHandler', $output );
	}

	/**
	 * Test enqueue() without a delay API selector.
	 *
	 * @return void
	 */
	public function test_enqueue_without_delay_api_selector(): void {
		$handle = 'hcap-delayed-script-enqueue';

		wp_register_script( $handle, 'https://example.com/enqueue.js', [], '1.0', false );

		DelayedScript::enqueue( $handle );

		self::assertTrue( wp_script_is( $handle ) );
	}

	/**
	 * Test enqueue() with a delay API selector.
	 *
	 * @return void
	 */
	public function test_enqueue_with_delay_api_selector(): void {
		$handle = 'hcap-delayed-script-observe';

		add_filter(
			'hcap_delay_api_selector',
			static function (): string {
				return ' .delayed-form ';
			}
		);

		wp_register_script( $handle, 'https://example.com/observe.js', [], '1.0', false );

		DelayedScript::enqueue( $handle );

		self::assertFalse( wp_script_is( $handle ) );

		ob_start();
		DelayedScript::print_observation_script();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hCaptchaObserve( ".delayed-form", ', $output );
		self::assertStringContainsString( 'https:\/\/example.com\/observe.js?ver=1.0', $output );
	}

	/**
	 * Test print_observation_script() without launched scripts.
	 *
	 * @return void
	 */
	public function test_print_observation_script_without_launched_scripts(): void {
		ob_start();
		DelayedScript::print_observation_script();
		$output = (string) ob_get_clean();

		self::assertSame( '', $output );
	}
	/**
	 * Test observe().
	 *
	 * @noinspection JSUnresolvedReference
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_observe(): void {
		global $wp_scripts;

		// Register fake scripts.
		wp_register_script( 'fake-script-a', 'https://example.com/a.js', [], '1.0', false );
		wp_register_script( 'fake-script-b', '/relative/b.js', [], '2.0', false );
		wp_register_script( 'fake-script-c', 'https://example.com/c.js', [], '3.0', false );

		$selector = '.my-form';

		$base_url = $wp_scripts->base_url;
		$source_a = 'https://example.com/a.js?ver=1.0';
		$source_b = $base_url . '/relative/b.js?ver=2.0';
		$source_c = 'https://example.com/c.js?ver=3.0';

		$method = new ReflectionMethod( DelayedScript::class, 'observe' );
		$method->setAccessible( true );

		// First call with A and B.
		$method->invoke( null, $selector, [ 'fake-script-a', 'fake-script-b' ] );

		// Second call with same handles: already launched, no change to state.
		$method->invoke( null, $selector, [ 'fake-script-a', 'fake-script-b' ] );

		// Third call with A and C: only C is new and gets added.
		$method->invoke( null, $selector, [ 'fake-script-a', 'fake-script-c' ] );

		// Capture output from the deferred print (all calls accumulated).
		ob_start();
		DelayedScript::print_observation_script();
		$output = ob_get_clean();

		// Main script must be present.
		self::assertStringContainsString( 'hCaptchaObserve', $output );

		// All three unique sources in order, no duplicates from the repeated call.
		$sources_js = wp_json_encode(
			[
				'fake-script-a' => [
					'src'  => $source_a,
					'type' => '',
				],
				'fake-script-b' => [
					'src'  => $source_b,
					'type' => '',
				],
				'fake-script-c' => [
					'src'  => $source_c,
					'type' => '',
				],
			]
		);
		self::assertStringContainsString( 'hCaptchaObserve( ".my-form", ' . $sources_js . ' );', $output );
	}

	/**
	 * Test observe() ignores handles without a source.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_observe_ignores_handles_without_source(): void {
		$method = new ReflectionMethod( DelayedScript::class, 'observe' );
		$method->setAccessible( true );

		$method->invoke( null, '.missing-script-form', 'missing-script' );

		self::assertSame( [], $this->get_launched() );
	}

	/**
	 * Test print_observation_script() skips scripts that cannot be printed.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_print_observation_script_skips_unprintable_scripts(): void {
		$handle = 'hcap-delayed-script-enqueued';

		wp_register_script( $handle, 'https://example.com/enqueued.js', [], '1.0', false );
		wp_enqueue_script( $handle );

		$this->set_launched(
			[
				'.skip-form' => [
					$handle,
					'missing-script',
				],
			]
		);

		ob_start();
		DelayedScript::print_observation_script();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'hCaptchaObserve = function', $output );
		self::assertStringNotContainsString( '".skip-form"', $output );
		self::assertStringNotContainsString( 'https:\/\/example.com\/enqueued.js?ver=1.0', $output );
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
				// Ignore the first scroll event, which can be on page load.
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

			const options = { passive: true };

			window.addEventListener( 'touchstart', load, options );
			document.body.addEventListener( 'mouseenter', load );
			document.body.addEventListener( 'click', load );
			window.addEventListener( 'keydown', load );
			window.addEventListener( 'scroll', scrollHandler, options );
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

	/**
	 * Test launch() with data attributes.
	 *
	 * @noinspection BadExpressionStatementJS
	 */
	public function test_launch_with_data_attributes(): void {
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

		ob_start();
		DelayedScript::launch(
			[
				'src'   => 'https://js.hcaptcha.com/1/api.js',
				'id'    => 'ignored-id',
				'async' => false,
				'data'  => [
					'sitekey' => 'site-key',
					'theme'   => 'dark',
				],
			]
		);
		$output = (string) ob_get_clean();

		self::assertStringContainsString( "s.setAttribute( 'data-' + 'sitekey', 'site-key' );", $output );
		self::assertStringContainsString( "s.setAttribute( 'data-' + 'theme', 'dark' );", $output );
		self::assertStringContainsString( "s['src'] = 'https://js.hcaptcha.com/1/api.js';", $output );
		self::assertStringNotContainsString( 'ignored-id', $output );
		self::assertStringNotContainsString( "s['async']", $output );
	}
	/**
	 * Test launch() with a custom delay API event.
	 *
	 * @noinspection BadExpressionStatementJS
	 */
	public function test_launch_with_delay_api_event(): void {
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

		// noinspection JSAnnotator
		const delayApiEvent = "hcap:load-api";
		let loaded = false;

		function load() {
			if ( loaded ) {
				return;
			}

			loaded = true;
			document.removeEventListener( delayApiEvent, load );

			const t = document.getElementsByTagName( 'script' )[0];
			const s = document.createElement( 'script' );
			s.type  = 'text/javascript';
			s.id = 'hcaptcha-api';
			s['src'] = 'https://js.hcaptcha.com/1/api.js';
			s.async = true;
			t.parentNode.insertBefore( s, t );
		}

		document.addEventListener( 'hCaptchaBeforeAPI', function() {
			document.addEventListener( delayApiEvent, load );
		} );
	} )();
JS;

		$expected = "<script>\n$expected\n</script>\n";

		ob_start();
		DelayedScript::launch( [ 'src' => 'https://js.hcaptcha.com/1/api.js' ], 3000, 'hcap:load-api' );
		$output = ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertStringNotContainsString( 'setTimeout', $output );
		self::assertStringNotContainsString( 'touchstart', $output );
		self::assertStringNotContainsString( 'mouseenter', $output );
		self::assertStringNotContainsString( 'keydown', $output );
		self::assertStringNotContainsString( 'scrollHandler', $output );
	}
	/**
	 * Get launched scripts.
	 *
	 * @return array
	 * @throws ReflectionException ReflectionException.
	 */
	private function get_launched(): array {
		$prop = ( new ReflectionClass( DelayedScript::class ) )->getProperty( 'launched' );
		$prop->setAccessible( true );

		return (array) $prop->getValue();
	}

	/**
	 * Set launched scripts.
	 *
	 * @param array $launched Launched scripts.
	 *
	 * @return void
	 * @throws ReflectionException ReflectionException.
	 */
	private function set_launched( array $launched ): void {
		$prop = ( new ReflectionClass( DelayedScript::class ) )->getProperty( 'launched' );
		$prop->setAccessible( true );
		$prop->setValue( null, $launched );
	}
}
