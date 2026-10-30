<?php
/**
 * Contact5EmailPolicyTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Divi;

use ET\Builder\FrontEnd\BlockParser\BlockParser;
use ET\Builder\FrontEnd\BlockParser\BlockParserBlock;
use ET\Builder\FrontEnd\BlockParser\BlockParserStore;
use ET\Builder\Packages\ModuleLibrary\ContactField\ContactFieldModule;
use ET\Builder\Packages\ModuleLibrary\ContactForm\ContactFormHandler;
use HCaptcha\Divi\Contact;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Compare policy projection with the installed Divi 5 parser and handler.
 *
 * @group divi
 */
class Contact5EmailPolicyTest extends HCaptchaWPTestCase {

	/**
	 * Load native parser classes without activating the Divi theme.
	 *
	 * @return void
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function setUp(): void {
		parent::setUp();

		$theme = $this->get_native_theme();
		if ( ! $theme ) {
			self::markTestSkipped( 'The installed Divi 5 native parser is required.' );
		}

		require_once $theme . '/includes/builder-5/server/vendor/autoload.php';
		if ( ! class_exists( 'ET_Builder_Module_Order' ) ) {
			require_once $theme . '/includes/builder/class-et-builder-module-order.php';
		}

		if ( ! function_exists( 'et_core_is_fb_enabled' ) ) {
			$core = get_theme_root() . '/Divi/core/functions.php';
			// The native D5 Conditions proxy uses the shared legacy core function.
			require_once is_file( $core ) ? $core : $theme . '/core/functions.php';
		}
		add_filter( 'divi_module_library_should_register_all_d5_modules', '__return_true' );
		BlockParserStore::reset();
		BlockParserStore::reset_order_index();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		set_transient( 'hcaptcha_disposable_email_blocklist', [ 'sec063-disposable.test' => true ] );
	}

	/**
	 * Restore the native store and request method.
	 *
	 * @return void
	 * @noinspection PhpUndefinedMethodInspection
	 */
	public function tearDown(): void {
		if ( class_exists( BlockParserStore::class, false ) ) {
			BlockParserStore::reset();
			BlockParserStore::reset_order_index();
		}
		$_SERVER['REQUEST_METHOD'] = 'GET';

		parent::tearDown();
	}

	/**
	 * Locate the native Divi 5 installation used only for integration context.
	 *
	 * @return string
	 */
	private function get_native_theme(): string {
		foreach ( [ 'Divi', '.Divi_5', 'Divi_5' ] as $slug ) {
			$theme = get_theme_root() . '/' . $slug;
			if ( is_file( $theme . '/includes/builder-5/server/Packages/ModuleLibrary/ContactForm/ContactFormHandler.php' ) ) {
				return $theme;
			}
		}

		return '';
	}

	/**
	 * A forged submit counter and honeypot cannot skip a Divi 5 nonce-backed submission.
	 *
	 * @return void
	 */
	public function test_sec050_native_order_index_and_nonce(): void {
		$module = $this->parse_native_form( [] );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$order_index = $module->orderIndex;
		unset( $_POST[ 'et_pb_contactform_submit_' . $order_index ] );
		$_POST['et_pb_contactform_submit_999']              = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_999']               = 'bot';
		$_POST[ 'et_pb_contact_et_number_' . $order_index ] = 'bot';

		// Divi 5 reaches the native submission hook using its nonce, even without the submit marker.
		self::assertSame( [], $this->get_native_fields( $module ) );

		$subject = new Contact();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$store_instance = $module->storeInstance;
		$subject->verify_5(
			[],
			[
				'name'          => 'divi/contact-form',
				'id'            => $module->id,
				'storeInstance' => $store_instance,
			]
		);

		self::assertSame( $order_index, $this->get_protected_property( $subject, 'render_count' ) );
		self::assertSame( 'on', $this->get_protected_property( $subject, 'captcha' ) );
		self::assertSame( 'on', $module->attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] );

		$error   = null;
		$capture = static function ( array $values, bool $native_error ) use ( &$error ): void {
			$error = $native_error;
			throw new RuntimeException( 'sec050-native-handler-stopped' );
		};
		add_action( 'et_pb_contact_form_submit', $capture, PHP_INT_MAX, 2 );
		try {
			new ContactFormHandler( $module->id, $store_instance );
			self::fail( 'Native validated-field hook was not reached.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'sec050-native-handler-stopped', $exception->getMessage() );
		} finally {
			remove_action( 'et_pb_contact_form_submit', $capture, PHP_INT_MAX );
		}

		self::assertTrue( $error );
	}

	/**
	 * A genuine hCaptcha result still permits the nonce-backed Divi 5 submission.
	 *
	 * @return void
	 */
	public function test_sec050_native_nonce_with_valid_hcaptcha(): void {
		$this->prepare_verify_post( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );
		$module = $this->parse_native_form( [] );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$order_index = $module->orderIndex;
		unset( $_POST[ 'et_pb_contactform_submit_' . $order_index ] );
		$_POST['et_pb_contactform_submit_999'] = 'et_contact_proccess';
		$_POST['et_pb_contact_et_number_999']  = 'bot';

		$subject                               = new Contact();
		$id                                    = $this->set_method_accessibility( $subject, 'get_expected_id' )->invoke( $subject );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$store_instance = $module->storeInstance;
		$subject->verify_5(
			[],
			[
				'name'          => 'divi/contact-form',
				'id'            => $module->id,
				'storeInstance' => $store_instance,
			]
		);

		self::assertSame( 'off', $this->get_protected_property( $subject, 'captcha' ) );
		self::assertSame( 'off', $module->attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] );
		self::assertSame( [], $this->get_native_fields( $module ) );
	}

	/**
	 * Enforce policy through verify_5 and compare every email to native accepted fields.
	 *
	 * @param array $definitions Stored native field definitions.
	 * @param bool  $blocked     Whether effective native email is disposable.
	 *
	 * @dataProvider dp_sec063_native_projection
	 * @return void
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 * @noinspection UnnecessaryAssertionInspection
	 */
	public function test_sec063_native_projection( array $definitions, bool $blocked ): void {
		$this->prepare_verify_post( 'hcaptcha_divi_cf_nonce', 'hcaptcha_divi_cf' );
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		self::assertTrue( hcaptcha()->settings()->is_on( 'disposable_email' ) );
		$module = $this->parse_native_form( $definitions );
		self::assertInstanceOf( BlockParserBlock::class, $module );
		self::assertStringEndsWith( 'ContactFormHandler.php', ( new ReflectionClass( ContactFormHandler::class ) )->getFileName() );
		$native = $this->get_native_fields( $module );
		$emails = [];
		foreach ( $native as $field ) {
			if ( 'email' === $field['type'] && '' !== $field['value'] ) {
				$emails[] = $field['value'];
			}
		}

		$subject                               = new Contact();
		$id                                    = $this->set_method_accessibility( $subject, 'get_expected_id' )->invoke( $subject );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( $id );
		$api_result                            = 'verification did not run';
		$capture                               = static function ( $result ) use ( &$api_result ) {
			$api_result = $result;

			return $result;
		};
		add_filter( 'hcap_verify_request', $capture, PHP_INT_MAX );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$store_instance = $module->storeInstance;
		$subject->verify_5(
			[],
			[
				'name'          => 'divi/contact-form',
				'id'            => $module->id,
				'storeInstance' => $store_instance,
			]
		);
		remove_filter( 'hcap_verify_request', $capture, PHP_INT_MAX );
		$entry = $this->set_method_accessibility( $subject, 'get_entry' )->invoke( $subject, [] );

		self::assertSame( $blocked ? hcap_get_error_messages()['disposable-email'] : null, $api_result );
		self::assertSame( $emails, EntryData::get_emails( $entry ) );
		self::assertSame( $blocked ? 'on' : 'off', $this->get_protected_property( $subject, 'captcha' ) );
		self::assertSame( $blocked ? 'on' : 'off', $module->attrs['module']['advanced']['spamProtection']['desktop']['value']['enabled'] );
	}

	/**
	 * Parse saved native block attributes and populate their native unique POST names.
	 *
	 * @param array $definitions Stored native field definitions.
	 *
	 * @return BlockParserBlock
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function parse_native_form( array $definitions ): BlockParserBlock {
		$attrs   = [
			'module' => [
				'advanced' => [
					'spamProtection' => [
						'desktop' => [
							'value' => [
								'enabled'         => 'off',
								'useBasicCaptcha' => 'off',
							],
						],
					],
				],
			],
		];
		$content = '<!-- wp:divi/contact-form ' . wp_json_encode( $attrs ) . ' -->';
		foreach ( $definitions as $field ) {
			$content .= '<!-- wp:divi/contact-field ' . wp_json_encode( $this->get_field_attrs( $field ) ) . ' /-->';
		}
		$content .= '<!-- /wp:divi/contact-form -->';
		$parser   = new BlockParser();
		$blocks   = $parser->parse( $content );
		$module   = BlockParserStore::get( $blocks[0]['id'], $parser->get_store_instance() );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$store_instance = $module->storeInstance;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$order_index = $module->orderIndex;
		foreach ( BlockParserStore::get_children( $module->id, $store_instance ) as $index => $field ) {
			$name           = ContactFieldModule::get_field_unique_id( $field->id, $store_instance );
			$_POST[ $name ] = wp_slash( $definitions[ $index ]['value'] );
		}
		$_POST[ 'et_pb_contactform_submit_' . $order_index ]              = 'et_contact_proccess';
		$_POST[ '_wpnonce-et-pb-contact-form-submitted-' . $order_index ] = wp_create_nonce( 'et-pb-contact-form-submit-' . $order_index );

		return $module;
	}

	/**
	 * Construct native stored field attributes.
	 *
	 * @param array $field Fixture field.
	 *
	 * @return array
	 */
	private function get_field_attrs( array $field ): array {
		$attrs = [
			'fieldItem' => [
				'advanced'     => [
					'id'              => [ 'desktop' => [ 'value' => $field['id'] ] ],
					'type'            => [ 'desktop' => [ 'value' => $field['type'] ] ],
					'required'        => [ 'desktop' => [ 'value' => 'off' ] ],
					'checkboxOptions' => [ 'desktop' => [ 'value' => [ [ 'value' => 'visible option' ], [ 'value' => 'other' ] ] ] ],
				],
				'innerContent' => [ 'desktop' => [ 'value' => $field['label'] ?? 'Payment contact' ] ],
			],
		];
		if ( isset( $field['rules'] ) ) {
			$attrs['conditionalLogic'] = [
				'advanced'     => [
					'enable'   => [ 'desktop' => [ 'value' => 'on' ] ],
					'relation' => [ 'desktop' => [ 'value' => $field['all'] ?? 'off' ] ],
				],
				'innerContent' => [ 'desktop' => [ 'value' => $field['rules'] ] ],
			];
		}

		return $attrs;
	}

	/**
	 * Capture the actual validated hook map before native mail side effects.
	 *
	 * @param BlockParserBlock $module Native parsed module.
	 *
	 * @return array
	 */
	private function get_native_fields( BlockParserBlock $module ): array {
		$fields  = null;
		$capture = static function ( array $values, bool $error ) use ( &$fields ): void {
			self::assertFalse( $error, 'Fixture must be accepted by native validation before email policy.' );
			$fields = $values;
			throw new RuntimeException( 'sec063-native-fields-captured' );
		};
		add_action( 'et_pb_contact_form_submit', $capture, PHP_INT_MAX, 2 );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native parser property.
		$store_instance = $module->storeInstance;
		try {
			new ContactFormHandler( $module->id, $store_instance );
			self::fail( 'Native validated-field hook was not reached.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'sec063-native-fields-captured', $exception->getMessage() );
		} finally {
			remove_action( 'et_pb_contact_form_submit', $capture, PHP_INT_MAX );
		}

		return $fields;
	}

	/**
	 * Native block and conditional visibility policy cases.
	 *
	 * @return array
	 */
	public function dp_sec063_native_projection(): array {
		$permanent  = [
			'id'    => 'billing_address',
			'type'  => 'email',
			'value' => 'reader@example.com',
			'label' => 'Payment contact',
		];
		$disposable = [
			'id'    => 'CONTACT_ADDRESS',
			'type'  => 'email',
			'value' => 'person@sec063-disposable.test',
			'label' => 'Другой адрес',
		];
		$spoof      = [
			'id'    => 'email',
			'type'  => 'input',
			'value' => 'spoof@sec063-disposable.test',
		];
		$cases      = [
			'custom labels multiple blocked' => [ [ $permanent, $disposable, $spoof ], true ],
			'text email spoof allowed'       => [ [ $permanent, $spoof ], false ],
			'duplicate input wins'           => [
				[
					$disposable,
					[
						'id'    => 'contact_address',
						'type'  => 'input',
						'value' => 'person@sec063-disposable.test',
					],
				],
				false,
			],
			'duplicate email wins'           => [
				[
					$spoof,
					[
						'id'    => 'EMAIL',
						'type'  => 'email',
						'value' => 'person@sec063-disposable.test',
					],
				],
				true,
			],
			'duplicate permanent wins'       => [
				[
					$disposable,
					[
						'id'    => 'contact_address',
						'type'  => 'email',
						'value' => 'reader@example.com',
					],
				],
				false,
			],
		];
		$conditions = [
			'is visible'                        => [ 'yes', 'is', 'yes', true ],
			'is hidden'                         => [ 'no', 'is', 'yes', false ],
			'native strict numeric rule hidden' => [ '12', 'is', 12, false ],
			'is not visible'                    => [ 'no', 'is not', 'yes', true ],
			'is not hidden'                     => [ 'yes', 'is not', 'yes', false ],
			'greater visible'                   => [ '12', 'is greater', '10', true ],
			'less hidden'                       => [ '12', 'is less', '10', false ],
			'less visible'                      => [ '2', 'is less', '10', true ],
			'contains escaped literal'          => [ 'a.b[1]', 'contains', '.b[1]', true ],
			'does not contain visible'          => [ 'no', 'does not contain', 'yes', true ],
			'empty visible'                     => [ '', 'is empty', '', true ],
			'not empty visible'                 => [ 'yes', 'is not empty', '', true ],
			'unknown selector native default'   => [ '', 'unknown selector', '', true ],
		];
		foreach ( $conditions as $name => $case ) {
			$field          = $disposable;
			$field['rules'] = [
				[
					'field'     => 'DISPLAY',
					'condition' => $case[1],
					'value'     => $case[2],
				],
			];
			$cases[ $name ] = [
				[
					[
						'id'    => 'display',
						'type'  => 'input',
						'value' => $case[0],
					],
					$field,
					$permanent,
					$spoof,
				],
				$case[3],
			];
		}
		foreach ( [
			'all hidden'  => [ 'on', false ],
			'any visible' => [ 'off', true ],
		] as $name => $case ) {
			$field          = $disposable;
			$field['all']   = $case[0];
			$field['rules'] = [
				[
					'field'     => 'choice',
					'condition' => 'contains',
					'value'     => 'visible option',
				],
				[
					'field'     => 'missing',
					'condition' => 'is not empty',
					'value'     => '',
				],
			];
			$cases[ $name ] = [
				[
					[
						'id'    => 'choice',
						'type'  => 'checkbox',
						'value' => 'visible+option,other',
					],
					$field,
					$permanent,
				],
				$case[1],
			];
		}
		foreach ( [
			'empty all visible' => [ 'on', true ],
			'empty any hidden'  => [ 'off', false ],
		] as $name => $case ) {
			$field          = $disposable;
			$field['rules'] = [];
			$field['all']   = $case[0];
			$cases[ $name ] = [ [ $field, $permanent ], $case[1] ];
		}

		return $cases;
	}
}
