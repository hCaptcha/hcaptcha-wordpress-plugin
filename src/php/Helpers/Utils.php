<?php
/**
 * Utils class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Class Utils.
 */
class Utils {

	/**
	 * Get the ISO 639-2 Language Code from user/site locale.
	 *
	 * @see   http://www.loc.gov/standards/iso639-2/php/code_list.php
	 *
	 * @return string
	 */
	public static function get_language_code(): string {
		$default_lang = 'en';
		$locale       = get_user_locale();

		if ( ! empty( $locale ) ) {
			$lang = explode( '_', $locale );

			if ( ! empty( $lang ) && is_array( $lang ) ) {
				$default_lang = strtolower( $lang[0] );
			}
		}

		return $default_lang;
	}
}
