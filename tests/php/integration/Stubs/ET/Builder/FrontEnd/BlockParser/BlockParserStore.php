<?php
/**
 * Stub for Divi 5 BlockParserStore.
 *
 * This stub is used in integration tests when Divi is not installed.
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace ET\Builder\FrontEnd\BlockParser;

/**
 * Class BlockParserStore.
 */
class BlockParserStore {
	/**
	 * Stored module instance.
	 *
	 * @var mixed
	 */
	public static $module;

	/**
	 * Get module from the store.
	 *
	 * @param mixed $id             Module ID.
	 * @param mixed $store_instance Store instance.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function get( $id, $store_instance ) {
		return self::$module;
	}
}
