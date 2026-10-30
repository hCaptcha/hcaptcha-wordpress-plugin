<?php
/**
 * UnreadableImportStream class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Abilities;

/**
 * Report a readable regular file while refusing its eventual read.
 */
class UnreadableImportStream {
	/**
	 * Return file metadata to the read-import preflight checks.
	 *
	 * @param string $path  Stream path.
	 * @param int    $flags Stat flags.
	 *
	 * @return array
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function url_stat( string $path, int $flags ): array {
		$mode = 0100444;

		return [
			'mode' => $mode,
			2      => $mode,
			'size' => 1,
			7      => 1,
		];
	}

	/**
	 * Simulate a read failure after file checks have passed.
	 *
	 * @param string      $path        Stream path.
	 * @param string      $mode        Open mode.
	 * @param int         $options     Stream options.
	 * @param string|null $opened_path Opened path.
	 *
	 * @return bool
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function stream_open( string $path, string $mode, int $options, &$opened_path ): bool {
		return false;
	}
}
