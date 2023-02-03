<?php
/**
 * Base class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\UM;

use HCaptcha\Abstracts\LoginBase;

/**
 * Class Base
 */
abstract class Base extends LoginBase {

	/**
	 * Field key.
	 */
	const KEY = 'hcaptcha';

	/**
	 * UM action.
	 */
	const UM_ACTION = '';

	/**
	 * UM mode.
	 */
	const UM_MODE = '';

	/**
	 * Field key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * UM mode.
	 *
	 * @var string
	 */
	private $um_mode;

	/**
	 * The hCaptcha action.
	 *
	 * @var string
	 */
	private $hcaptcha_action;

	/**
	 * The hCaptcha nonce.
	 *
	 * @var string
	 */
	private $hcaptcha_nonce;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->key             = self::KEY;
		$this->um_mode         = static::UM_MODE;
		$this->hcaptcha_action = "hcaptcha_um_$this->um_mode";
		$this->hcaptcha_nonce  = "hcaptcha_um_{$this->um_mode}_nonce";

		parent::__construct();
	}

	/**
	 * Init hooks.
	 */
	protected function init_hooks() {
		add_filter( 'um_get_form_fields', [ $this, 'add_captcha' ], 100 );
		add_filter( "um_{$this->key}_form_edit_field", [ $this, 'display_captcha' ], 10, 2 );
		add_action( static::UM_ACTION, [ $this, 'verify' ] );
	}

	/**
	 * Add hCaptcha to form fields.
	 *
	 * @param array $fields Form fields.
	 *
	 * @return array
	 */
	public function add_captcha( $fields ) {
		$um = UM();

		if ( ! $um ) {
			return $fields;
		}

		if ( static::UM_MODE !== $um->fields()->set_mode ) {
			return $fields;
		}

		$fields       = $fields ?: [];
		$max_position = 0;
		$in_row       = '_um_row_1';
		$in_sub_row   = '0';
		$in_column    = '1';
		$in_group     = '';

		foreach ( $fields as $field ) {
			if ( ! isset( $field['position'] ) ) {
				continue;
			}

			if ( $field['position'] <= $max_position ) {
				continue;
			}

			$max_position = $field['position'];
			$in_row       = $field['in_row'];
			$in_sub_row   = $field['in_sub_row'];
			$in_column    = $field['in_column'];
			$in_group     = $field['in_group'];
		}

		$fields[ self::KEY ] = [
			'title'        => __( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
			'metakey'      => self::KEY,
			'type'         => self::KEY,
			'label'        => __( 'hCaptcha', 'hcaptcha-for-forms-and-more' ),
			'required'     => 0,
			'public'       => 0,
			'editable'     => 0,
			'account_only' => true,
			'position'     => (string) ( $max_position + 1 ),
			'in_row'       => $in_row,
			'in_sub_row'   => $in_sub_row,
			'in_column'    => $in_column,
			'in_group'     => $in_group,
		];

		return $fields;
	}

	/**
	 * Display hCaptcha.
	 *
	 * @param string $output Output.
	 * @param string $mode   Mode.
	 *
	 * @return string
	 * @noinspection PhpUnnecessaryCurlyVarSyntaxInspection
	 */
	public function display_captcha( $output, $mode ) {
		if ( $this->um_mode !== $mode || '' !== $output ) {
			return $output;
		}

		$output = "<div class=\"um-field um-field-{$this->key}\">";

		$output .= hcap_form( $this->hcaptcha_action, $this->hcaptcha_nonce );
		$output .= '</div>';

		$um = UM();

		if ( ! $um ) {
			return $output;
		}

		$fields = $um->fields();

		if ( $fields->is_error( self::KEY ) ) {
			$output .= $fields->field_error( $fields->show_error( self::KEY ) );
		}

		return $output;
	}

	/**
	 * Verify hCaptcha.
	 *
	 * @param array $args Form arguments.
	 *
	 * @return void
	 */
	public function verify( $args ) {
		$um = UM();

		if ( ! $um || ! isset( $args['mode'] ) || $this->um_mode !== $args['mode'] ) {
			return;
		}

		$error_message = hcaptcha_get_verify_message(
			$this->hcaptcha_nonce,
			$this->hcaptcha_action
		);

		if ( null === $error_message ) {
			return;
		}

		$um->form()->add_error( self::KEY, $error_message );
	}
}
