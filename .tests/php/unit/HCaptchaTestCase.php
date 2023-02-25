<?php
/**
 * HCaptchaTestCase class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpLanguageLevelInspection */
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class HCaptchaTestCase
 */
abstract class HCaptchaTestCase extends TestCase {

	/**
	 * Setup test
	 */
	public function setUp(): void {
		FunctionMocker::setUp();
		parent::setUp();
		WP_Mock::setUp();
	}

	/**
	 * End test
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
		FunctionMocker::tearDown();
	}

	/**
	 * Get an object protected property.
	 *
	 * @param object $object        Object.
	 * @param string $property_name Property name.
	 *
	 * @return mixed
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function get_protected_property( $object, $property_name ) {
		$reflection_class = new ReflectionClass( $object );

		$property = $reflection_class->getProperty( $property_name );
		$property->setAccessible( true );
		$value = $property->getValue( $object );
		$property->setAccessible( false );

		return $value;
	}

	/**
	 * Set an object protected property.
	 *
	 * @param object $object        Object.
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property vale.
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_protected_property( $object, $property_name, $value ) {
		$reflection_class = new ReflectionClass( $object );

		$property = $reflection_class->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
		$property->setAccessible( false );
	}

	/**
	 * Set an object protected method accessibility.
	 *
	 * @param object $object      Object.
	 * @param string $method_name Property name.
	 * @param bool   $accessible  Property vale.
	 *
	 * @return ReflectionMethod
	 *
	 * @throws ReflectionException Reflection exception.
	 */
	protected function set_method_accessibility( $object, $method_name, $accessible = true ) {
		$reflection_class = new ReflectionClass( $object );

		$method = $reflection_class->getMethod( $method_name );
		$method->setAccessible( $accessible );

		return $method;
	}

	/**
	 * Get test settings.
	 *
	 * @return array
	 */
	protected function get_test_settings() {
		return [
			'wp_status'                    =>
				[
					0 => 'lost_pass',
					1 => 'password_protected',
					2 => 'register',
				],
			'acfe_status'                  =>
				[ 0 => 'form' ],
			'avada_status'                 =>
				[ 0 => 'form' ],
			'bbp_status'                   =>
				[
					0 => 'new_topic',
					1 => 'reply',
				],
			'beaver_builder_status'        =>
				[
					0 => 'contact',
					1 => 'login',
				],
			'bp_status'                    =>
				[
					0 => 'create_group',
					1 => 'registration',
				],
			'cf7_status'                   =>
				[ 0 => 'form' ],
			'divi_status'                  =>
				[
					0 => 'comment',
					1 => 'contact',
					2 => 'login',
				],
			'download_manager_status'      =>
				[ 0 => 'button' ],
			'elementor_pro_status'         =>
				[ 0 => 'form' ],
			'fluent_status'                =>
				[ 0 => 'form' ],
			'forminator_status'            =>
				[],
			'gravity_status'               =>
				[ 0 => 'form' ],
			'jetpack_status'               =>
				[ 0 => 'contact' ],
			'kadence_status'               =>
				[ 0 => 'form' ],
			'mailchimp_status'             =>
				[ 0 => 'form' ],
			'memberpress_status'           =>
				[ 0 => 'register' ],
			'ninja_status'                 =>
				[ 0 => 'form' ],
			'otter_status'                 =>
				[ 0 => 'form' ],
			'quform_status'                =>
				[ 0 => 'form' ],
			'sendinblue_status'            =>
				[ 0 => 'form' ],
			'subscriber_status'            =>
				[ 0 => 'form' ],
			'ultimate_member_status'       =>
				[
					0 => 'login',
					1 => 'lost_pass',
					2 => 'register',
				],
			'woocommerce_status'           =>
				[
					0 => 'checkout',
					1 => 'login',
					2 => 'lost_pass',
					3 => 'order_tracking',
					4 => 'register',
				],
			'woocommerce_wishlists_status' =>
				[ 0 => 'create_list' ],
			'wpforms_status'               =>
				[
					0 => 'lite',
					1 => 'pro',
				],
			'wpdiscuz_status'              =>
				[ 0 => 'comment_form' ],
			'wpforo_status'                =>
				[
					0 => 'new_topic',
					1 => 'reply',
				],
			'woocommerce_wishlist_status'  =>
				[ 0 => 'create_list' ],
			'api_key'                      => 'some API key',
			'secret_key'                   => 'some secret key',
			'theme'                        => 'light',
			'size'                         => 'normal',
			'language'                     => 'en',
			'custom_themes'                =>
				[],
			'off_when_logged_in'           =>
				[],
			'recaptcha_compat_off'         =>
				[],
			'custom_theme_params'          => '{
   "theme": "dark",
   "size": "compact"
}',
			'config_params'                => '',
			'whitelisted_ips'              => '4444444.777.2
220.45.45.1
',
			'_network_wide'                => [],
			'mode'                         => 'test:publisher',
			'site_key'                     => 'some site key',
			'delay'                        => '0',
			'login_limit'                  => '2',
			'login_interval'               => '15',
		];
	}

	/**
	 * Get test form fields.
	 *
	 * @return array
	 */
	protected function get_test_form_fields() {
		return [
			'wp_status'                    =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'WP Core',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'comment'            => 'Comment Form',
							'login'              => 'Login Form',
							'lost_pass'          => 'Lost Password Form',
							'password_protected' => 'Post/Page Password Form',
							'register'           => 'Register Form',
						],
				],
			'acfe_status'                  =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'ACF Extended',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'ACF Extended Form',
						],
				],
			'avada_status'                 =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Avada',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Avada Form',
						],
				],
			'bbp_status'                   =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'bbPress',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'new_topic' => 'New Topic Form',
							'reply'     => 'Reply Form',
						],
				],
			'beaver_builder_status'        =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Beaver Builder',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'contact' => 'Contact Form',
							'login'   => 'Login Form',
						],
				],
			'bp_status'                    =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'BuddyPress',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'create_group' => 'Create Group Form',
							'registration' => 'Registration Form',
						],
				],
			'cf7_status'                   =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Contact Form 7',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'divi_status'                  =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Divi',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'comment' => 'Divi Comment Form',
							'contact' => 'Divi Contact Form',
							'login'   => 'Divi Login Form',
						],
				],
			'download_manager_status'      =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Download Manager',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'button' => 'Button',
						],
				],
			'elementor_pro_status'         =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Elementor Pro',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'fluent_status'                =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Fluent Forms',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'forminator_status'            =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Forminator',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'gravity_status'               =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Gravity Forms',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'jetpack_status'               =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Jetpack',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'contact' => 'Contact Form',
						],
				],
			'kadence_status'               =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Kadence',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Kadence Form',
						],
				],
			'mailchimp_status'             =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Mailchimp for WP',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'memberpress_status'           =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'MemberPress',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'register' => 'Registration Form',
						],
				],
			'ninja_status'                 =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Ninja Forms',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'otter_status'                 =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Otter Blocks',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'quform_status'                =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Quform',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'sendinblue_status'            =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Sendinblue',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'subscriber_status'            =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Subscriber',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'form' => 'Form',
						],
				],
			'ultimate_member_status'       =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'Ultimate Member',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'login'     => 'Login Form',
							'lost_pass' => 'Lost Password Form',
							'register'  => 'Register Form',
						],
				],
			'woocommerce_status'           =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'WooCommerce',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'checkout'       => 'Checkout Form',
							'login'          => 'Login Form',
							'lost_pass'      => 'Lost Password Form',
							'order_tracking' => 'Order Tracking Form',
							'register'       => 'Registration Form',
						],
				],
			'woocommerce_wishlists_status' =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'WooCommerce Wishlists',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'create_list' => 'Create List Form',
						],
				],
			'wpforms_status'               =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'WPForms',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'lite' => 'Lite',
							'pro'  => 'Pro',
						],
				],
			'wpdiscuz_status'              =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'WPDiscuz',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'comment_form' => 'Comment Form',
						],
				],
			'wpforo_status'                =>
				[
					'default'  => '',
					'disabled' => false,
					'field_id' => '',
					'label'    => 'WPForo',
					'section'  => '',
					'title'    => '',
					'type'     => 'checkbox',
					'options'  =>
						[
							'new_topic' => 'New Topic Form',
							'reply'     => 'Reply Form',
						],
				],
		];
	}
}
