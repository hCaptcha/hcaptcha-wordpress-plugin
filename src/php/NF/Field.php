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
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
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
	protected $_templates = self::NAME;

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

		foreach ( [ self::TYPE, $this->_name ] as $type ) {
			if ( ! in_array( $type, $hidden_field_types, true ) ) {
				$hidden_field_types[] = $type;
			}
		}

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
		$form_id   = (int) ( $data['id'] ?? 0 );
		$form      = Ninja_Forms()->form( $form_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated_at = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT updated_at FROM {$wpdb->prefix}nf3_forms WHERE id = %d",
				$form_id
			)
		);

		$entry = [
			'h-captcha-response' => $response,
			'form_date_gmt'      => $updated_at,
			'data'               => [],
			'expected_id'        => $this->get_expected_id( $form_id ),
		];

		$name   = [];
		$emails = [];

		// Ninja Forms validates and saves the target form's server fields, not extra client IDs.
		foreach ( $form->get_fields() as $form_field ) {
			if ( is_object( $form_field ) ) {
				$id       = $form_field->get_id();
				$settings = $form_field->get_settings();
			} elseif ( is_array( $form_field ) && isset( $form_field['id'], $form_field['settings'] ) ) {
				$id       = $form_field['id'];
				$settings = $form_field['settings'];
			} else {
				continue;
			}

			if ( ! is_array( $settings ) || ! isset( $fields[ $id ]['value'] ) ) {
				continue;
			}

			$key   = $settings['key'];
			$type  = $settings['type'];
			$label = $settings['label'];
			$value = $fields[ $id ]['value'];

			if ( 'email' === $type ) {
				$emails[] = $value;
			}

			if ( ! EntryData::is_content_field_type( (string) $type ) || EntryData::has_sensitive_field( (string) $key, (string) $label ) ) {
				continue;
			}

			if ( 'name' === $key ) {
				$name[] = $value;
			}

			EntryData::add_field( $entry['data'], (string) $label, $value, (string) $id );
		}

		EntryData::add_name( $entry['data'], $name );

		return EntryData::with_emails( $entry, $emails );
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array
	 */
	private function get_expected_id( int $form_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( NF::class ),
			'form_id' => $form_id,
		];
	}
}
