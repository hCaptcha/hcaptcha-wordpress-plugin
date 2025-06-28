<?php
/**
 * ProviderBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\AntiSpam;

/**
 * Class ProviderBase.
 */
abstract class ProviderBase {
	/**
	 * Has the provider been configured with a valid API key?
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 */
	abstract public static function is_configured(): bool;

	/**
	 * Verify entry.
	 *
	 * @param array $entry Entry data.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	abstract public function verify( array $entry ): ?string;
}
