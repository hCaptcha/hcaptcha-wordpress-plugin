<?php
/**
 * AntiSpam class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\AntiSpam;

/**
 * Class AntiSpam.
 */
class AntiSpam {
	/**
	 * AntiSpam provider.
	 *
	 * @var object
	 */
	private static $provider;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$provider = new Akismet();

		if ( ! $provider::is_configured() ) {
			return;
		}

		self::$provider = $provider;
	}

	/**
	 * Verify if a form entry is spam.
	 *
	 * @param array $entry The entry data to verify.
	 *
	 * @return string|null Returns null if verification is successful, or string error message otherwise.
	 */
	public static function verify( array $entry ): ?string {
		if ( ! self::$provider ) {
			return null;
		}

		return self::$provider->verify( $entry );
	}
}
