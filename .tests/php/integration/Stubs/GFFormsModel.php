<?php
/**
 * Gravity Forms GFFormsModel stub file
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

/**
 * Class GFFormsModel
 */
class GFFormsModel {

	/**
	 * Constructor.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array|null
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public static function get_form_meta( int $form_id ) {
		return [];
	}

	/**
	 * Returns the field object for the requested field or input ID from the supplied or specified form.
	 *
	 * @param array|int  $form_or_id The Form Object or ID.
	 * @param string|int $field_id   The field or input ID.
	 *
	 * @return GF_Field|null
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function get_field( $form_or_id, $field_id ) {
		return null;
	}
}
