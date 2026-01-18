<?php
/**
 * Field class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpMissingFieldTypeInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\NF;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\Request;
use HCaptcha\Helpers\Utils;
use NF_Abstracts_Field;

/**
 * Class Field
 */
class Field extends NF_Abstracts_Field implements Base {

	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Field name.
	 *
	 * @var string
	 */
	protected $_name = self::NAME;

	/**
	 * Field type.
	 *
	 * @var string
	 */
	protected $_type = self::TYPE;

	/**
	 * Section.
	 *
	 * @var string
	 */
	protected $_section = 'misc';

	/**
	 * Icon.
	 *
	 * @var string
	 */
	protected $_icon = 'hand-paper-o';

	/**
	 * Templates.
	 *
	 * @var string
	 */
	protected $_templates = 'hcaptcha';

	/**
	 * Settings.
	 *
	 * @var string[]
	 */
	protected $_settings = [ 'label', 'classes' ];

	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Fields constructor.
	 *
	 * @noinspection PhpDynamicFieldDeclarationInspection
	 */
	public function __construct() {
		parent::__construct();

		$this->_nicename = __( 'hCaptcha', 'hcaptcha-for-forms-and-more' );

		add_filter( 'nf_sub_hidden_field_types', [ $this, 'hide_field_type' ] );
	}

	/**
	 * Validate form.
	 *
	 * @param array|mixed $field Field.
	 * @param mixed       $data  Data.
	 *
	 * @return null|string
	 */
	public function validate( $field, $data ): ?string {
		$response = $field['value'] ?? '';
		$fields   = $data['fields'];

		unset( $fields[ $field['id'] ] );

		return API::verify( $this->get_entry( $response, $fields ) );
	}

	/**
	 * Hide the field type.
	 *
	 * @param array|mixed $hidden_field_types Field types.
	 *
	 * @return array
	 */
	public function hide_field_type( $hidden_field_types ): array {
		$hidden_field_types = (array) $hidden_field_types;

		// Remove the native hcaptcha field by Ninja Forms plugin.
		$hidden_field_types = array_diff( $hidden_field_types, [ 'hcaptcha' ] );

		$hidden_field_types[] = $this->_name;

		return $hidden_field_types;
	}

	/**
	 * Get entry.
	 *
	 * @param string $response The hCaptcha response.
	 * @param array  $fields   Form data.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	private function get_entry( string $response, array $fields ): array {
		global $wpdb;

		$form_data = Request::filter_input( INPUT_POST, 'formData' );
		$data      = Utils::json_decode_arr( $form_data );
		$form      = Ninja_Forms()->form( $data['id'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT updated_at FROM {$wpdb->prefix}nf3_forms WHERE id = %d",
				$data['id']
			)
		);

		$entry = [
			'h-captcha-response' => $response,
			'form_date_gmt'      => $updated_at,
			'data'               => [],
		];

		$name = [];

		foreach ( $fields as $field ) {
			$id       = $field['id'];
			$settings = $form->get_field( $id )->get_settings();
			$key      = $settings['key'];
			$type     = $settings['type'];
			$label    = $settings['label'];
			$value    = $field['value'];

			if ( 'submit' === $type ) {
				continue;
			}

			if ( 'name' === $key ) {
				$name[] = $value;
			}

			if ( 'email' === $type ) {
				$entry['data']['email'] = $value;
			}

			$entry['data'][ $label ] = $value;
		}

		$entry['data']['name'] = implode( ' ', $name ) ?: null;

		return $entry;
	}
}
