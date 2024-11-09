<?php
/**
 * Form Fields Parser stub file
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

namespace FluentForm\App\Modules\Form;

use stdClass;

/**
 * Form Fields Parser stub.
 */
class FormFieldsParser {

	/**
	 * Reset data.
	 *
	 * @return void
	 */
	public static function resetData(): void {
	}

	/**
	 * Has element.
	 *
	 * @param stdClass $form    Form object.
	 * @param string   $element Element name.
	 *
	 * @return bool
	 */
	public static function hasElement( stdClass $form, string $element ): bool {
		return true;
	}
}
