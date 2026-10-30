<?php
/**
 * ScoperTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit;

/**
 * Test patches applied after PHP-Scoper prefixes dependencies.
 */
class ScoperTest extends HCaptchaTestCase {

	/**
	 * Prefix only unscoped constructor callbacks, regardless of string escaping.
	 *
	 * @dataProvider dp_test_js_constructor_callback
	 *
	 * @param string $literal  Constructor class literal.
	 * @param string $expected Expected constructor class literal.
	 */
	public function test_js_constructor_callback( string $literal, string $expected ): void {
		$config  = require dirname( __DIR__, 3 ) . '/.php-scoper/hcaptcha-wordpress-plugin-scoper.php';
		$patcher = $config['patchers'][0];
		$path    = 'vendor/matthiasmullie/minify/src/JS.php';
		$content = "<?php\ncall_user_func_array(array($literal, '__construct'), func_get_args());";
		$patched = $patcher( $path, $config['prefix'], $content );

		self::assertSame(
			"<?php\ncall_user_func_array(array($expected, '__construct'), func_get_args());",
			$patched
		);
		self::assertSame( $patched, $patcher( $path, $config['prefix'], $patched ) );
		self::assertSame( $patched, $patcher( str_replace( '/', '\\', $path ), $config['prefix'], $content ) );
		self::assertSame( $content, $patcher( 'vendor/other/src/JS.php', $config['prefix'], $content ) );
	}

	/**
	 * Constructor literals emitted by different PHP-Scoper printers.
	 *
	 * @return array[]
	 */
	public function dp_test_js_constructor_callback(): array {
		$prefixed = "'HCaptcha\Vendors\MatthiasMullie\Minify\Minify'";
		$escaped  = "'HCaptcha\\\\Vendors\\\\MatthiasMullie\\\\Minify\\\\Minify'";
		$other    = "'Other\\\\Vendors\\\\MatthiasMullie\\\\Minify\\\\Minify'";

		return [
			'unscoped escaped' => [ "'\\\\MatthiasMullie\\\\Minify\\\\Minify'", $prefixed ],
			'unscoped literal' => [ "'\MatthiasMullie\Minify\Minify'", $prefixed ],
			'scoped escaped'   => [ $escaped, $escaped ],
			'scoped literal'   => [ $prefixed, $prefixed ],
			'other namespace'  => [ $other, $other ],
		];
	}
}
