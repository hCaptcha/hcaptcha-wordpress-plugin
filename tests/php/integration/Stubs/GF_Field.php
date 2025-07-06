<?php
/**
 * Gravity Forms GF_Field stub file
 *
 * @package HCaptcha\Tests
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpIllegalPsrClassPathInspection */

/**
 * Class GF_Field
 */
class GF_Field {

	/**
	 * Id.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Constructor.
	 *
	 * @param array $data Data.
	 */
	public function __construct( array $data = [] ) {}

	/**
	 * Determine if the current location is the entry detail page.
	 *
	 * @return bool
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function is_entry_detail() {
		return false;
	}

	/**
	 * Determine if the current location is the form editor.
	 *
	 * @return bool
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection ReturnTypeCanBeDeclaredInspection
	 */
	public function is_form_editor() {
		return true;
	}
}
