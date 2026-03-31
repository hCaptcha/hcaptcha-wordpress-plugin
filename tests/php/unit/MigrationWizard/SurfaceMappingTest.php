<?php
/**
 * SurfaceMappingTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard;

use HCaptcha\MigrationWizard\SurfaceMapping;
use HCaptcha\Tests\Unit\HCaptchaTestCase;

/**
 * Test SurfaceMapping class.
 *
 * @group migration-wizard
 */
class SurfaceMappingTest extends HCaptchaTestCase {

	/**
	 * Test get returns mapping for known surface.
	 *
	 * @return void
	 */
	public function test_get_known_surface(): void {
		$mapping = SurfaceMapping::get( 'wp_login' );

		self::assertIsArray( $mapping );
		self::assertSame( 'wp_status', $mapping[0] );
		self::assertSame( 'login', $mapping[1] );
		self::assertSame( 'WordPress Login', $mapping[2] );
	}

	/**
	 * Test get returns null for unknown surface.
	 *
	 * @return void
	 */
	public function test_get_unknown_surface(): void {
		self::assertNull( SurfaceMapping::get( 'nonexistent_surface' ) );
	}

	/**
	 * Test is_supported.
	 *
	 * @return void
	 */
	public function test_is_supported(): void {
		self::assertTrue( SurfaceMapping::is_supported( 'wp_login' ) );
		self::assertTrue( SurfaceMapping::is_supported( 'wc_checkout' ) );
		self::assertTrue( SurfaceMapping::is_supported( 'cf7_form' ) );
		self::assertFalse( SurfaceMapping::is_supported( 'nonexistent' ) );
	}

	/**
	 * Test get_all_surface_ids returns an array of strings.
	 *
	 * @return void
	 */
	public function test_get_all_surface_ids(): void {
		$ids = SurfaceMapping::get_all_surface_ids();

		self::assertIsArray( $ids );
		self::assertContains( 'wp_login', $ids );
		self::assertContains( 'wc_checkout', $ids );
		self::assertContains( 'cf7_form', $ids );
	}

	/**
	 * Test get_all returns a full map.
	 *
	 * @return void
	 */
	public function test_get_all(): void {
		$all = SurfaceMapping::get_all();

		self::assertIsArray( $all );
		self::assertArrayHasKey( 'wp_login', $all );
		self::assertArrayHasKey( 'wc_login', $all );
	}

	/**
	 * Test WooCommerce surfaces.
	 *
	 * @return void
	 */
	public function test_woocommerce_surfaces(): void {
		$wc_login = SurfaceMapping::get( 'wc_login' );

		self::assertSame( 'woocommerce_status', $wc_login[0] );
		self::assertSame( 'login', $wc_login[1] );

		$wc_checkout = SurfaceMapping::get( 'wc_checkout' );

		self::assertSame( 'woocommerce_status', $wc_checkout[0] );
		self::assertSame( 'checkout', $wc_checkout[1] );
	}
}
