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

use FluentForm\App\Models\Form as FluentForm;
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
	 * @param FluentForm|stdClass $form    Form object.
	 * @param string              $element Element name.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function hasElement( $form, string $element ): bool {
		return false;
	}
}
