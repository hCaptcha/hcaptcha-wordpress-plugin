<?php
/**
 * HCaptchaTestCase class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable PHPCompatibility.FunctionDeclarations.NewReturnTypeDeclarations.voidFound

namespace HCaptcha\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class HCaptchaTestCase
 */
abstract class HCaptchaTestCase extends TestCase {

	/**
	 * Setup test
	 */
	public function setUp(): void {
		FunctionMocker::setUp();
		parent::setUp();
		WP_Mock::setUp();
	}

	/**
	 * End test
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
		FunctionMocker::tearDown();
	}

	/**
	 * Get an object protected property.
	 *
	 * @param object $object        Object.
	 * @param string $property_name Property name.
	 *
	 * @return mixed
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function get_protected_property( $object, $property_name ) {
		$reflection_class = new ReflectionClass( $object );

		$property = $reflection_class->getProperty( $property_name );
		$property->setAccessible( true );
		$value = $property->getValue( $object );
		$property->setAccessible( false );

		return $value;
	}

	/**
	 * Set an object protected property.
	 *
	 * @param object $object        Object.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property vale.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_protected_property( $object, $property_name, $value ) {
		$reflection_class = new ReflectionClass( $object );

		$property = $reflection_class->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
		$property->setAccessible( false );
	}

	/**
	 * Set an object protected method accessibility.
	 *
	 * @param object $object      Object.
	 * @param string $method_name Property name.
	 * @param bool   $accessible  Property vale.
	 *
	 * @return ReflectionMethod
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_method_accessibility( $object, $method_name, $accessible = true ) {
		$reflection_class = new ReflectionClass( $object );

		$method = $reflection_class->getMethod( $method_name );
		$method->setAccessible( $accessible );

		return $method;
	}
}
