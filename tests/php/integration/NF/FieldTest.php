<?php
/**
 * FieldTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUndefinedFunctionInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\NF;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\NF\Base;
use HCaptcha\NF\Field;
use HCaptcha\NF\NF;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use NF_Database_Models_Form;
use NF_Display_Render;
use Ninja_Forms;
use ReflectionClass;
use ReflectionProperty;
use WPN_Helper;

/**
 * Test Field class.
 *
 * @group    nf
 */
class FieldTest extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'ninja-forms/ninja-forms.php';

	/**
	 * Hooks to replay after loading the plugin.
	 *
	 * @var string[]
	 */
	protected static array $plugin_load_hooks = [
		'plugins_loaded',
		'init',
	];

	/**
	 * Test rendering an hCaptcha field through the live Ninja Forms frontend.
	 */
	public function test_live_form_render(): void {
		$ninja_forms_file = wp_normalize_path( ( new ReflectionClass( Ninja_Forms::class ) )->getFileName() );

		self::assertTrue( is_plugin_active( static::$plugin ) );
		self::assertStringStartsWith( wp_normalize_path( WP_PLUGIN_DIR . '/ninja-forms/' ), $ninja_forms_file );

		new NF();

		Ninja_Forms::instance()->instantiateTranslatableObjects();

		$form_id = $this->create_ninja_form( true );
		$html    = do_shortcode( '[ninja_form id="' . $form_id . '"]' );

		self::assertStringContainsString( 'id="nf-form-' . $form_id . '-cont"', $html );
		self::assertStringContainsString( '"type":"' . Base::NAME . '"', $html );
		self::assertStringContainsString( 'class=\"h-captcha\"', $html );
		self::assertStringContainsString( 'hcaptcha-widget-id', $html );
		ob_start();
		NF_Display_Render::output_templates();
		$templates = ob_get_clean();
		self::assertStringContainsString( 'id="tmpl-nf-field-' . Base::NAME . '"', $templates );
		self::assertStringContainsString( '{{{ data.hcaptcha }}}', $templates );
	}

	/**
	 * Test a page with native and bridge fields keeps the native script.
	 */
	public function test_mixed_form_render_keeps_native_script(): void {
		$subject = new NF();
		Ninja_Forms::instance()->instantiateTranslatableObjects();

		$form_id = $this->create_ninja_form( true, true );
		WPN_Helper::delete_nf_cache( $form_id );
		Ninja_Forms()->form( $form_id )->get_fields( [], true );
		// Native render counts outlive test transactions that reuse form IDs.
		$render_count = new ReflectionProperty( NF_Display_Render::class, 'render_instance_count' );
		$render_count->setAccessible( true );
		$previous_counts = $render_count->getValue();
		$counts          = $previous_counts;
		unset( $counts[ $form_id ] );
		$render_count->setValue( null, $counts );

		try {
			$html = do_shortcode( '[ninja_form id="' . $form_id . '"]' );

			self::assertStringContainsString( '"type":"' . Base::TYPE . '"', $html );
			self::assertStringContainsString( '"type":"' . Base::NAME . '"', $html );
			ob_start();
			NF_Display_Render::output_templates();
			$templates = ob_get_clean();
			self::assertStringContainsString( 'id="tmpl-nf-field-' . Base::TYPE . '"', $templates );
			self::assertStringContainsString( 'class="nf-h-captcha"', $templates );
			self::assertStringContainsString( 'id="tmpl-nf-field-' . Base::NAME . '"', $templates );
			self::assertStringContainsString( '{{{ data.hcaptcha }}}', $templates );
			self::assertTrue( wp_script_is( 'nf-hcaptcha' ) );

			$subject->nf_captcha_script();

			self::assertTrue( wp_script_is( 'nf-hcaptcha' ) );
			self::assertTrue( wp_script_is( 'hcaptcha-nf' ) );
		} finally {
			$render_count->setValue( null, $previous_counts );
		}
	}

	/**
	 * Tear down the test.
	 */
	public function tearDown(): void {
		unset( $_POST['formData'], $_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] );
		wp_dequeue_script( 'nf-hcaptcha' );
		wp_dequeue_script( 'hcaptcha-nf' );

		parent::tearDown();
	}

	/**
	 * Test __construct().
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function test_constructor(): void {
		$subject = new Field();

		self::assertSame( 'hCaptcha', $subject->get_nicename() );
		self::assertSame( 10, has_filter( 'nf_sub_hidden_field_types', [ $subject, 'hide_field_type' ] ) );
	}

	/**
	 * Test validate().
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 * @noinspection AmbiguousMethodsCallsInArrayMappingInspection
	 */
	public function test_validate(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[ $field->get_id() ] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => 'some value',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( $field['value'] );
		$this->prepare_widget_id( $form_id );

		$subject = new Field();

		self::assertNull( $subject->validate( $field, $data ) );
	}

	/**
	 * Test validate() without a field.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection AmbiguousMethodsCallsInArrayMappingInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_validate_without_field(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[ $field->get_id() ] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => '',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( '', false );
		$this->prepare_widget_id( $form_id );

		$subject = new Field();

		self::assertSame( 'Please complete the hCaptcha.', $subject->validate( $field, $data ) );
	}

	/**
	 * Test validate() when not validated.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection AmbiguousMethodsCallsInArrayMappingInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_validate_not_validated(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[ $field->get_id() ] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => 'some value',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( $field['value'], false );
		$this->prepare_widget_id( $form_id );

		$subject = new Field();

		self::assertSame( 'The hCaptcha is invalid.', $subject->validate( $field, $data ) );
	}

	/**
	 * Test validate() when widget id is missing.
	 *
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection AmbiguousMethodsCallsInArrayMappingInspection
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	public function test_validate_missing_widget_id(): void {
		$form_id = $this->create_ninja_form();
		$form    = Ninja_Forms()->form( $form_id );
		$fields  = [];

		foreach ( $form->get_fields() as $field ) {
			$fields[ $field->get_id() ] = [
				'id'    => $field->get_id(),
				'value' => 'some value',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );

		$field          = [
			'id'    => 90,
			'value' => 'some value',
		];
		$data['fields'] = $fields;

		$this->prepare_verify_request( $field['value'] );

		$subject = new Field();

		self::assertSame( 'Bad hCaptcha signature!', $subject->validate( $field, $data ) );
	}

	/**
	 * A foreign form's email field must not replace the target email checked by anti-spam.
	 */
	public function test_validate_ignores_foreign_email_field(): void {
		$target_id  = $this->create_ninja_form( true );
		$foreign_id = $this->create_ninja_form();
		self::assertNotSame( $target_id, $foreign_id );
		$foreign          = Ninja_Forms()->form( $foreign_id );
		$foreign_email_id = 0;

		foreach ( $foreign->get_fields() as $model ) {
			if ( 'email' === $model->get_setting( 'type' ) ) {
				$foreign_email_id = $model->get_id();
			}
		}

		self::assertGreaterThan( 0, $foreign_email_id );

		[ $captcha, $fields ]        = $this->prepare_email_submission( $target_id, 'reader@sec055-disposable.test' );
		$fields[ $foreign_email_id ] = [
			'id'    => $foreign_email_id,
			'value' => 'reader@example.com',
		];

		self::assertSame(
			'Please use a permanent email address.',
			( new Field() )->validate( $captcha, [ 'fields' => $fields ] )
		);
	}

	/**
	 * Check every accepted target email regardless of client field order.
	 */
	public function test_validate_checks_all_target_email_fields(): void {
		$form_id = $this->create_ninja_form( true );
		$second  = Ninja_Forms()->form( $form_id )->field()->get();
		$second->update_setting( 'label', 'Second Email' );
		$second->update_setting( 'key', 'second_email' );
		$second->update_setting( 'type', 'email' );
		$second->update_setting( 'order', 5 );
		$second->save();

		[ $captcha, $fields ]        = $this->prepare_email_submission( $form_id, 'reader@sec055-disposable.test' );
		$fields[ $second->get_id() ] = [
			'id'    => $second->get_id(),
			'value' => 'reader@example.com',
		];

		self::assertSame(
			'Please use a permanent email address.',
			( new Field() )->validate( $captcha, [ 'fields' => $fields ] )
		);
	}

	/**
	 * A target form with a permanent email and valid proof must still submit.
	 */
	public function test_validate_accepts_permanent_native_email(): void {
		$form_id              = $this->create_ninja_form( true );
		[ $captcha, $fields ] = $this->prepare_email_submission( $form_id, 'reader@example.com' );

		self::assertNull( ( new Field() )->validate( $captcha, [ 'fields' => $fields ] ) );
	}

	/**
	 * Native filters may supply server field definitions as arrays.
	 */
	public function test_validate_checks_native_array_field_definitions(): void {
		$form_id              = $this->create_ninja_form( true );
		[ $captcha, $fields ] = $this->prepare_email_submission( $form_id, 'reader@sec055-disposable.test' );
		$as_arrays            = static function ( array $form_fields, int $filtered_form_id ) use ( $form_id ): array {
			if ( $form_id !== $filtered_form_id ) {
				return $form_fields;
			}

			foreach ( $form_fields as $id => $model ) {
				$form_fields[ $id ] = [
					'id'       => $model->get_id(),
					'settings' => $model->get_settings(),
				];
			}

			return $form_fields;
		};
		add_filter( 'ninja_forms_get_fields', $as_arrays, 10, 2 );

		try {
			self::assertSame(
				'Please use a permanent email address.',
				( new Field() )->validate( $captcha, [ 'fields' => $fields ] )
			);
		} finally {
			remove_filter( 'ninja_forms_get_fields', $as_arrays );
		}
	}

	/**
	 * Ignore malformed field definitions supplied by a native filter.
	 */
	public function test_validate_ignores_malformed_native_field_definitions(): void {
		$form_id              = $this->create_ninja_form( true );
		[ $captcha, $fields ] = $this->prepare_email_submission( $form_id, 'reader@example.com' );
		$with_invalid_field   = static function ( array $form_fields, int $filtered_form_id ) use ( $form_id ): array {
			if ( $form_id === $filtered_form_id ) {
				$form_fields[] = null;
			}

			return $form_fields;
		};

		add_filter( 'ninja_forms_get_fields', $with_invalid_field, 10, 2 );

		try {
			self::assertNull( ( new Field() )->validate( $captcha, [ 'fields' => $fields ] ) );
		} finally {
			remove_filter( 'ninja_forms_get_fields', $with_invalid_field );
		}
	}

	/**
	 * A sensitive field label cannot exempt a native email from disposable policy.
	 */
	public function test_validate_checks_native_email_with_sensitive_label(): void {
		$form_id = $this->create_ninja_form( true );

		foreach ( Ninja_Forms()->form( $form_id )->get_fields() as $model ) {
			if ( 'email' === $model->get_setting( 'type' ) ) {
				$model->update_setting( 'label', 'Payment Email' );
				$model->update_setting( 'key', 'payment_email' );
				$model->save();
			}
		}

		[ $captcha, $fields ] = $this->prepare_email_submission( $form_id, 'reader@sec055-disposable.test' );
		self::assertSame(
			'Please use a permanent email address.',
			( new Field() )->validate( $captcha, [ 'fields' => $fields ] )
		);
	}

	/**
	 * Prepare the native field map, signed widget and disposable-email policy.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $email   Submitted target email.
	 *
	 * @return array{0: array, 1: array}
	 * @noinspection JsonEncodingApiUsageInspection
	 */
	private function prepare_email_submission( int $form_id, string $email ): array {
		$fields  = [];
		$captcha = [];

		foreach ( Ninja_Forms()->form( $form_id )->get_fields() as $model ) {
			$id            = $model->get_id();
			$type          = $model->get_setting( 'type' );
			$value         = 'email' === $type ? $email : 'some value';
			$fields[ $id ] = [
				'id'    => $id,
				'value' => $value,
			];

			if ( Base::NAME === $type ) {
				$captcha = $fields[ $id ];
			}
		}

		self::assertNotEmpty( $captcha );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		$_POST['formData'] = json_encode( [ 'id' => (string) $form_id ] );
		$this->prepare_verify_request( $captcha['value'] );
		$this->prepare_widget_id( $form_id );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec055-disposable.test' => true ] );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		self::assertTrue(
			HCaptcha::is_protection_enabled(
				[
					'source'  => [ 'ninja-forms/ninja-forms.php' ],
					'form_id' => $form_id,
				]
			)
		);
		return [ $captcha, $fields ];
	}

	/**
	 * Test hide_field_type().
	 *
	 * @return void
	 */
	public function test_hide_field_type(): void {
		$hidden_field_types = [ 'some type' ];
		$expected           = [ 'some type', 'hcaptcha', 'hcaptcha-for-ninja-forms' ];

		$subject = new Field();

		self::assertSame( $expected, $subject->hide_field_type( $hidden_field_types ) );
		self::assertSame( $expected, $subject->hide_field_type( $expected ) );
	}

	/**
	 * Prepare hCaptcha widget id.
	 *
	 * @param int   $form_id Form id.
	 * @param array $id      Widget id.
	 *
	 * @return void
	 * @noinspection PhpSameParameterValueInspection
	 */
	private function prepare_widget_id( int $form_id, array $id = [] ): void {
		$id = array_merge(
			[
				'source'  => [ 'ninja-forms/ninja-forms.php' ],
				'form_id' => $form_id,
			],
			$id
		);

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
	}

	/**
	 * Create a Ninja form.
	 *
	 * @param bool $with_hcaptcha        Whether to add a bridge hCaptcha field.
	 * @param bool $with_native_hcaptcha Whether to add a native hCaptcha field.
	 *
	 * @return int
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	protected function create_ninja_form( bool $with_hcaptcha = false, bool $with_native_hcaptcha = false ): int {
		global $wpdb;

		// Create a Ninja form.
		$form = new NF_Database_Models_Form( $wpdb, 0 );

		$form->update_setting( 'title', 'Test Form' );
		$form->update_setting( 'publish_state', 1 );
		$form->save();

		$form_id = $form->get_id();

		// Add a name field.
		$field = Ninja_Forms()->form( $form_id )->field()->get();
		$field->update_setting( 'label', 'Name' );
		$field->update_setting( 'key', 'name' );
		$field->update_setting( 'type', 'textbox' );
		$field->update_setting( 'order', 1 );
		$field->save();

		if ( $with_hcaptcha ) {
			$hcaptcha_field = Ninja_Forms()->form( $form_id )->field()->get();
			$hcaptcha_field->update_setting( 'label', 'hCaptcha' );
			$hcaptcha_field->update_setting( 'key', 'hcaptcha' );
			$hcaptcha_field->update_setting( 'type', Base::NAME );
			$hcaptcha_field->update_setting( 'order', 3 );
			$hcaptcha_field->save();
		}

		if ( $with_native_hcaptcha ) {
			$native_field = Ninja_Forms()->form( $form_id )->field()->get();
			$native_field->update_setting( 'label', 'Native hCaptcha' );
			$native_field->update_setting( 'key', 'native_hcaptcha' );
			$native_field->update_setting( 'type', Base::TYPE );
			$native_field->update_setting( 'label_visibility', 'visible' );
			$native_field->update_setting( 'order', 4 );
			$native_field->save();
		}

		// Add an email field.
		$field = Ninja_Forms()->form( $form_id )->field()->get();
		$field->update_setting( 'label', 'Email' );
		$field->update_setting( 'key', 'email' );
		$field->update_setting( 'type', 'email' );
		$field->update_setting( 'order', 2 );
		$field->save();

		// Add a `submit` field.
		$submit_field = Ninja_Forms()->form( $form_id )->field()->get();
		$submit_field->update_setting( 'label', 'Отправить' );
		$submit_field->update_setting( 'type', 'submit' );
		$submit_field->update_setting( 'order', 999 );
		$submit_field->save();

		return $form_id;
	}
}
