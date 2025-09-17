<?php
/**
 * SystemInfoTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpLanguageLevelInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Main;
use HCaptcha\Migrations\Migrations;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;
use HCaptcha\Settings\PluginSettingsBase;
use HCaptcha\Settings\Settings;
use HCaptcha\Settings\SystemInfo;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use KAGG\Settings\Abstracts\SettingsBase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use wpdb;
use WP_Mock;
use WP_Theme;

/**
 * Class SystemInfoTest
 *
 * @group settings
 * @group settings-system-info
 */
class SystemInfoTest extends HCaptchaTestCase {

	/**
	 * Teardown test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wpdb'], $_SERVER['SERVER_SOFTWARE'], $_SESSION );

		parent::tearDown();
	}

	/**
	 * Test page_title().
	 */
	public function test_page_title(): void {
		$subject = Mockery::mock( SystemInfo::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'page_title';

		self::assertSame( 'System Info', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title(): void {
		$subject = Mockery::mock( SystemInfo::class )->makePartial()->shouldAllowMockingProtectedMethods();
		$method  = 'section_title';

		self::assertSame( 'system-info', $subject->$method() );
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_enqueue_scripts(): void {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min_suffix     = '.min';
		$subject        = Mockery::mock( SystemInfo::class )->makePartial();

		$subject->shouldAllowMockingProtectedMethods();
		$this->set_protected_property( $subject, 'min_suffix', $min_suffix );

		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_url, $plugin_version ) {
				if ( 'HCAPTCHA_URL' === $name ) {
					return $plugin_url;
				}

				if ( 'HCAPTCHA_VERSION' === $name ) {
					return $plugin_version;
				}

				return '';
			}
		);

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				SystemInfo::DIALOG_HANDLE,
				$plugin_url . "/assets/js/kagg-dialog$min_suffix.js",
				[],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				SystemInfo::DIALOG_HANDLE,
				$plugin_url . "/assets/css/kagg-dialog$min_suffix.css",
				[],
				$plugin_version
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_script' )
			->with(
				SystemInfo::HANDLE,
				$plugin_url . "/assets/js/system-info$min_suffix.js",
				[ SystemInfo::DIALOG_HANDLE ],
				$plugin_version,
				true
			)
			->once();

		WP_Mock::userFunction( 'wp_localize_script' )
			->with(
				SystemInfo::HANDLE,
				SystemInfo::OBJECT,
				[
					'successMsg' => 'System info copied to the clipboard.',
					'errorMsg'   => 'Cannot copy info to the clipboard.',
					'OKBtnText'  => 'OK',
				]
			)
			->once();

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				SystemInfo::HANDLE,
				$plugin_url . "/assets/css/system-info$min_suffix.css",
				[ PluginSettingsBase::PREFIX . '-' . SettingsBase::HANDLE, SystemInfo::DIALOG_HANDLE ],
				$plugin_version
			)
			->once();

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Test section_callback().
	 *
	 * @return void
	 */
	public function test_section_callback(): void {
		$subject  = Mockery::mock( SystemInfo::class )->makePartial();
		$expected = '		<div class="hcaptcha-header-bar">
			<div class="hcaptcha-header">
				<h2>
					System Info				</h2>
			</div>
					</div>
				<div id="hcaptcha-system-info-wrap">
			<span class="helper">
				<span class="helper-content">Copy system info to clipboard</span>
			</span>
			<div class="dashicons-before dashicons-media-text" aria-hidden="true"></div>
			<label>
			<textarea
					id="hcaptcha-system-info"
					readonly></textarea>
			</label>
		</div>
		';

		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'get_system_info' )->once()->andReturn( '' );

		WP_Mock::passthruFunction( 'wp_kses_post' );

		ob_start();
		$subject->section_callback( [ 'id' => 'some section id' ] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Test get_system_info().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_get_system_info(): void {
		$plugin_version         = '1.0.0';
		$migrations             = [
			'2.0.0'  => 1662205373,
			'3.6.0'  => 1703257543,
			'3.10.1' => 1711985976,
		];
		$date_format            = 'd.m.Y';
		$time_format            = 'H:i';
		$site_url               = 'http://test.test';
		$home_url               = 'http://test.test';
		$wp_version             = '6.4.3';
		$theme_name             = 'Twenty Twenty-One';
		$theme_version          = '2.1';
		$front_page_id          = '62';
		$blog_page_id           = '0';
		$post_stati             = [
			'publish'           => 'publish',
			'future'            => 'future',
			'draft'             => 'draft',
			'pending'           => 'pending',
			'private'           => 'private',
			'trash'             => 'trash',
			'auto-draft'        => 'auto-draft',
			'inherit'           => 'inherit',
			'request-pending'   => 'request-pending',
			'request-confirmed' => 'request-confirmed',
			'request-failed'    => 'request-failed',
			'request-completed' => 'request-completed',
		];
		$uploads_dir            = [
			'path'    => '/var/www/test/wp-content/uploads/2024/04',
			'url'     => 'https://test.test/wp-content/uploads/2024/04',
			'basedir' => '/var/www/test/wp-content/uploads',
			'baseurl' => 'https://test.test/wp-content/uploads',
		];
		$mu_plugins             = [
			'kagg-compatibility-error-handler.php' =>
				[
					'Name'    => 'kagg-compatibility-error-handler.php',
					'Version' => '',
				],
			'kagg-shortcuts.php'                   =>
				[
					'Name'    => 'kagg-shortcuts.php',
					'Version' => '',
				],
		];
		$plugin_updates         = [];
		$plugins                = [
			'acf-extended/acf-extended.php'          => [
				'Name'    => 'Advanced Custom Fields: Extended',
				'Version' => '0.8.9.5',
			],
			'acf-extended-pro/acf-extended.php'      => [
				'Name'    => 'Advanced Custom Fields: Extended PRO',
				'Version' => '0.8.9',
			],
			'contact-form-7/wp-contact-form-7.php'   => [
				'Name'    => 'Contact Form 7',
				'Version' => '5.9.3',
			],
			'hcaptcha-wordpress-plugin/hcaptcha.php' => [
				'Name'    => 'hCaptcha for WP',
				'Version' => '3.10.1',
			],
			'woocommerce/woocommerce.php'            => [
				'Name'    => 'WooCommerce',
				'Version' => '8.7.0',
			],
		];
		$active_plugins         = [
			'contact-form-7/wp-contact-form-7.php',
			'hcaptcha-wordpress-plugin/hcaptcha.php',
		];
		$akismet_path           = 'akismet/akismet.php';
		$akismet_data           = [
			'Name'    => 'Akismet Anti-Spam',
			'Version' => '4.1.12',
		];
		$network_plugins        = [
			$akismet_path,
			'backwpup/backwpup.php',
		];
		$active_network_plugins = [
			'akismet' => [],
		];
		$server                 = 'Apache/2.4.57 (Ubuntu)';
		$expected               = "
### Begin System Info ###


-- hCaptcha Info --

Version:                              $plugin_version
Site key:                             Not set
Secret key:                           Not set
Theme:                                
Size:                                 
Language:                             Auto-detect
Mode:                                 
Custom Themes:                        Off
Config Params:                        Not set
API Host:                             
Asset Host:                           
Endpoint:                             
Host:                                 
Image Host:                           
Report API:                           
Sentry:                               
Backend:                              
Turn Off When Logged In:              Off
Disable reCAPTCHA Compatibility:      Off
Allowlisted IPs:                      Not set
Login attempts before hCaptcha:       
Failed login attempts interval, min:  
Delay showing hCaptcha, ms:           
Migrations:                           
  2.0.0:                              03.09.2022 11:42
  3.6.0:                              22.12.2023 15:05
  3.10.1:                             01.04.2024 15:39

--- Integrations header info ---

  Show Antispam Coverage:             Off

--- Active plugins and themes ---

Contact Form 7:                       
  Form Auto-Add:                      On
  Form Embed:                         Off
  Live Form in Admin:                 Off
  Replace Really Simple CAPTCHA:      Off
WP Core:                              
  Comment Form:                       Off
  Login Form:                         Off
  Lost Password Form:                 On
  Post/Page Password Form:            On
  Register Form:                      On

--- Inactive plugins and themes ---

ACF Extended:                         
  ACF Extended Form:                  On
Affiliates:                           
  Affiliates Login Form:              Off
  Affiliates Register Form:           Off
Asgaros:                              
  Form:                               Off
Avada:                                
  Avada Form:                         On
Back In Stock Notifier:               
  Back In Stock Notifier Form:        Off
bbPress:                              
  Login Form:                         Off
  Lost Password Form:                 Off
  New Topic Form:                     On
  Register Form:                      Off
  Reply Form:                         On
Beaver Builder:                       
  Contact Form:                       On
  Login Form:                         On
blocksy:                              
  Newsletter Subscribe (Free):        Off
  Product Review (Pro):               Off
  Waitlist Form (Pro):                Off
Brevo:                                
  Form:                               On
Brizy:                                
  Form:                               Off
BuddyPress:                           
  Create Group Form:                  On
  Register Form:                      On
Classified Listing:                   
  Contact Form:                       Off
  Login Form:                         Off
  Lost Password Form:                 Off
  Register Form:                      Off
CoBlocks:                             
  Form:                               Off
Colorlib Login Customizer:            
  Login Form:                         Off
  Lost Password Form:                 Off
  Register Form:                      Off
Customer Reviews:                     
  Q&A Form:                           Off
  Review Form:                        Off
Divi:                                 
  Divi Comment Form:                  On
  Divi Contact Form:                  On
  Divi Email Optin Form:              Off
  Divi Login Form:                    On
Divi Builder:                         
  Divi Builder Comment Form:          Off
  Divi Builder Contact Form:          Off
  Divi Builder Email Optin Form:      Off
  Divi Builder Login Form:            Off
Download Manager:                     
  Button:                             On
Easy Digital Downloads:               
  Checkout Form:                      Off
  Login Form:                         Off
  Lost Password Form:                 Off
  Register Form:                      Off
Elementor Pro:                        
  Form:                               On
  Login:                              Off
Essential Addons:                     
  Login:                              Off
  Register:                           Off
Essential Blocks:                     
  Form:                               Off
Events Manager:                       
  Booking:                            Off
Extra:                                
  Extra Comment Form:                 Off
  Extra Contact Form:                 Off
  Extra Email Optin Form:             Off
  Extra Login Form:                   Off
Fluent Forms:                         
  Form:                               On
Formidable Forms:                     
  Form:                               Off
Forminator:                           
  Form:                               Off
GiveWP:                               
  Form:                               On
Gravity Forms:                        
  Form Auto-Add:                      On
  Form Embed:                         Off
HTML Forms:                           
  Form:                               Off
Icegram Express:                      
  Form:                               Off
Jetpack:                              
  Contact Form:                       On
Kadence:                              
  Kadence Form:                       On
  Kadence Advanced Form:              Off
LearnDash LMS:                        
  Login Form:                         Off
  Lost Password Form:                 Off
  Register Form:                      Off
LearnPress:                           
  Checkout Form:                      Off
  Login Form:                         Off
  Register Form:                      Off
Login Signup Popup:                   
  Login Form:                         Off
  Register Form:                      Off
Mailchimp for WP:                     
  Form:                               On
MailPoet:                             
  Form:                               Off
Maintenance:                          
  Login Form:                         Off
MemberPress:                          
  Login Form:                         Off
  Register Form:                      On
Ninja Forms:                          
  Form:                               On
Otter Blocks:                         
  Form:                               On
Paid Memberships Pro:                 
  Checkout Form:                      Off
  Login Form:                         Off
Passster:                             
  Protection Form:                    Off
Password Protected:                   
  Protection Form:                    Off
Profile Builder:                      
  Login Form:                         Off
  Recover Password Form:              Off
  Register Form:                      Off
Quform:                               
  Form:                               On
Simple Basic Contact Form:            
  Form:                               Off
Simple Download Monitor:              
  Form:                               Off
Simple Membership:                    
  Login Form:                         Off
  Register Form:                      Off
  Password Reset Form:                Off
Spectra:                              
  Form:                               Off
Subscriber:                           
  Form:                               On
Support Candy:                        
  Form:                               Off
Theme My Login:                       
  Login Form:                         Off
  Lost Password Form:                 Off
  Register Form:                      Off
Tutor LMS:                            
  Checkout Form:                      Off
  Login Form:                         Off
  Lost Password Form:                 Off
  Register Form:                      Off
Ultimate Addons:                      
  Login Form:                         Off
  Register Form:                      Off
Ultimate Member:                      
  Login Form:                         On
  Lost Password Form:                 On
  Register Form:                      On
Users WP:                             
  Forgot Password Form:               Off
  Login Form:                         Off
  Register Form:                      Off
WooCommerce:                          
  Checkout Form:                      On
  Login Form:                         On
  Lost Password Form:                 On
  Order Tracking Form:                On
  Register Form:                      On
WooCommerce Germanized:               
  Return Request Form:                Off
WooCommerce Wishlists:                
  Create List Form:                   On
Wordfence:                            
  Login Form:                         Off
WP Job Openings:                      
  Form:                               Off
WPDiscuz:                             
  Comment Form:                       On
  Subscribe Form:                     Off
WPForms:                              
  Form Auto-Add:                      Off
  Form Embed:                         Off
WPForo:                               
  New Topic Form:                     On
  Reply Form:                         On

-- Site Info --

Site URL:                             $site_url
Home URL:                             $home_url
Multisite:                            Yes

-- WordPress Configuration --

Version:                              $wp_version
Language:                             en_US
User Language:                        en_US
Permalink Structure:                  /%postname%/
Active Theme:                         $theme_name $theme_version
Show On Front:                        page
Page On Front:                        Untitled (#$front_page_id)
Page For Posts:                       Unset
ABSPATH:                              /var/www/test/
Table Prefix:                         Length: 3   Status: Acceptable
WP_DEBUG:                             Enabled
Memory Limit:                         1G
Registered Post Stati:                publish, future, draft, pending, private, trash, auto-draft, inherit, request-pending, request-confirmed, request-failed, request-completed
Revisions:                            Limited to 3

-- WordPress Uploads/Constants --

WP_CONTENT_DIR:                       var/www/test/wp-content
WP_CONTENT_URL:                       https://test.test/wp-content
UPLOADS:                              Disabled
wp_uploads_dir() path:                {$uploads_dir['path']}
wp_uploads_dir() url:                 {$uploads_dir['url']}
wp_uploads_dir() basedir:             {$uploads_dir['basedir']}
wp_uploads_dir() baseurl:             {$uploads_dir['baseurl']}

-- Must-Use Plugins --

kagg-compatibility-error-handler.php: 
kagg-shortcuts.php:                   

-- WordPress Active Plugins --

Contact Form 7:                       5.9.3
hCaptcha for WP:                      3.10.1

-- WordPress Inactive Plugins --

Advanced Custom Fields: Extended:     0.8.9.5
Advanced Custom Fields: Extended PRO: 0.8.9
WooCommerce:                          8.7.0

-- Network Active Plugins --

Akismet Anti-Spam:                    4.1.12

-- Webserver Configuration --

PHP Version:                          8.2.28
MySQL Version:                        8.0.34
Webserver Info:                       Apache/2.4.57 (Ubuntu)

-- PHP Configuration --

Memory Limit:                         2G
Upload Max Size:                      2G
Post Max Size:                        2G
Upload Max Filesize:                  2G
Time Limit:                           0
Max Input Vars:                       1000
Display Errors:                       N/A

-- PHP Extensions --

cURL:                                 Supported
fsockopen:                            Supported
SOAP Client:                          Installed
Suhosin:                              Installed

-- Session Configuration --

Session:                              Enabled
Session Name:                         some session name
Cookie Path:                          some cookie path
Save Path:                            some save path
Use Cookies:                          On
Use Only Cookies:                     On

### End System Info ###

";

		$integration_fields   = $this->get_test_integrations_form_fields();
		$integration_settings = $this->get_test_settings();
		$integrations_enabled = [ 'wp_status', 'cf7_status' ];

		foreach ( $integration_fields as $key => & $field ) {
			if ( 'header' === ( $field['section'] ?? '' ) ) {
				$field['disabled'] = false;

				continue;
			}

			$field['disabled'] = ! in_array( $key, $integrations_enabled, true );
		}

		unset( $field );

		$integration_fields_sorted = $this->sort_fields( $integration_fields );

		$wpdb         = Mockery::mock( wpdb::class )->makePartial();
		$wp_theme     = Mockery::mock( WP_Theme::class )->makePartial();
		$general      = Mockery::mock( General::class )->makePartial();
		$integrations = Mockery::mock( Integrations::class )->makePartial();
		$settings     = Mockery::mock( Settings::class )->makePartial();
		$main         = Mockery::mock( Main::class )->makePartial();

		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'db_version' )->with()->andReturn( '8.0.34' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb']            = $wpdb;
		$_SERVER['SERVER_SOFTWARE'] = $server;
		$_SESSION                   = [];

		$wp_theme->shouldReceive( 'get' )->with( 'Name' )->andReturn( $theme_name );
		$wp_theme->shouldReceive( 'get' )->with( 'Version' )->andReturn( $theme_version );

		$this->set_protected_property( $integrations, 'settings', $integration_settings );
		$integrations->shouldAllowMockingProtectedMethods();
		$integrations->shouldReceive( 'form_fields' )->andReturn( $integration_fields );
		$integrations->shouldReceive( 'sort_fields' )->andReturn( $integration_fields_sorted );

		$settings->shouldReceive( 'get_tabs' )->andReturn( [ $general, $integrations ] );
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		$subject = Mockery::mock( SystemInfo::class )->makePartial();
		$method  = 'get_system_info';

		$this->set_method_accessibility( $subject, $method );

		FunctionMocker::replace(
			'defined',
			static function ( $constant_name ) {
				$defined_constants = [ 'WP_DEBUG', 'WP_CONTENT_DIR', 'WP_CONTENT_URL', 'UPLOADS' ];

				return in_array( $constant_name, $defined_constants, true );
			}
		);
		$this->replace_constant( $plugin_version );
		$this->replace_ini_get();
		FunctionMocker::replace(
			'function_exists',
			static function ( $function_name ) {
				$defined_functions = [ 'curl_init', 'fsockopen' ];

				return in_array( $function_name, $defined_functions, true );
			}
		);
		FunctionMocker::replace(
			'class_exists',
			static function ( $class_name ) {
				$defined_classes = [ 'SoapClient' ];

				return in_array( $class_name, $defined_classes, true );
			}
		);
		FunctionMocker::replace(
			'extension_loaded',
			static function ( $extension_name ) {
				$loaded_extensions = [ 'suhosin' ];

				return in_array( $extension_name, $loaded_extensions, true );
			}
		);

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );
		WP_Mock::userFunction( 'hcaptcha' )->andReturn( $main );
		WP_Mock::userFunction( 'get_option' )->with( Migrations::MIGRATED_VERSIONS_OPTION_NAME, [] )
			->andReturn( $migrations );
		WP_Mock::userFunction( 'get_option' )->with( 'date_format' )->andReturn( $date_format );
		WP_Mock::userFunction( 'get_option' )->with( 'time_format' )->andReturn( $time_format );
		WP_Mock::userFunction( 'site_url' )->with()->andReturn( $site_url );
		WP_Mock::userFunction( 'home_url' )->with()->andReturn( $home_url );
		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( true );
		WP_Mock::userFunction( 'wp_get_active_network_plugins' )->with()->andReturn( $network_plugins );
		WP_Mock::userFunction( 'get_site_option' )->with( 'active_sitewide_plugins', [] )
			->andReturn( $active_network_plugins );
		WP_Mock::userFunction( 'plugin_basename' )->andReturnUsing(
			static function ( $file ) {
				return explode( '/', $file )[0];
			}
		);
		WP_Mock::userFunction( 'get_plugin_data' )->with( $akismet_path )->andReturn( $akismet_data );
		WP_Mock::userFunction( 'wp_get_theme' )->with()->andReturn( $wp_theme );
		WP_Mock::userFunction( 'get_bloginfo' )->with( 'version' )->andReturn( $wp_version );
		WP_Mock::userFunction( 'get_locale' )->with()->andReturn( 'en_US' );
		WP_Mock::userFunction( 'get_user_locale' )->with()->andReturn( 'en_US' );
		WP_Mock::userFunction( 'get_option' )->with( 'permalink_structure' )->andReturn( '/%postname%/' );
		WP_Mock::userFunction( 'get_option' )->with( 'show_on_front' )->andReturn( 'page' );
		WP_Mock::userFunction( 'get_option' )->with( 'page_on_front' )->andReturn( $front_page_id );
		WP_Mock::userFunction( 'get_option' )->with( 'page_for_posts' )->andReturn( $blog_page_id );
		WP_Mock::userFunction( 'get_the_title' )->with( $front_page_id )->andReturn( 'Untitled' );
		WP_Mock::userFunction( 'get_the_title' )->with( $blog_page_id )->andReturn( 'Untitled' );
		WP_Mock::userFunction( 'get_post_stati' )->with()->andReturn( $post_stati );
		WP_Mock::userFunction( 'wp_upload_dir' )->with()->andReturn( $uploads_dir );
		WP_Mock::userFunction( 'get_mu_plugins' )->with()->andReturn( $mu_plugins );
		WP_Mock::userFunction( 'get_plugin_updates' )->with()->andReturn( $plugin_updates );
		WP_Mock::userFunction( 'get_plugins' )->with()->andReturn( $plugins );
		WP_Mock::userFunction( 'get_option' )->with( 'active_plugins', [] )->andReturn( $active_plugins );

		self::assertSame( $expected, $subject->$method() );
	}

	/**
	 * Test get_integrations().
	 */
	public function test_get_integrations_without_integrations_class(): void {
		$general = Mockery::mock( General::class )->makePartial();

		$tabs = [ $general ];

		$settings = Mockery::mock( Settings::class )->makePartial();
		$main     = Mockery::mock( Main::class )->makePartial();
		$subject  = Mockery::mock( SystemInfo::class )->makePartial();

		$settings->shouldReceive( 'get_tabs' )->andReturn( $tabs );
		$main->shouldReceive( 'settings' )->andReturn( $settings );

		WP_Mock::userFunction( 'hcaptcha' )->with()->once()->andReturn( $main );

		self::assertSame( [], $subject->get_integrations() );
	}

	/**
	 * Test multisite_plugins() when it is not multisite.
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_multisite_plugins_not_multisite(): void {
		$subject = Mockery::mock( SystemInfo::class )->makePartial();
		$method  = 'multisite_plugins';

		$this->set_method_accessibility( $subject, $method );

		WP_Mock::userFunction( 'is_multisite' )->with()->andReturn( false );

		self::assertSame( '', $subject->$method() );
	}

	/**
	 * Replace constant.
	 *
	 * @param string $plugin_version Plugin version.
	 *
	 * @return void
	 */
	private function replace_constant( string $plugin_version ): void {
		FunctionMocker::replace(
			'constant',
			static function ( $name ) use ( $plugin_version ) {
				if ( 'HCAPTCHA_VERSION' === $name ) {
					return $plugin_version;
				}

				if ( 'ABSPATH' === $name ) {
					return '/var/www/test/';
				}

				if ( 'WP_DEBUG' === $name ) {
					return true;
				}

				if ( 'WP_MEMORY_LIMIT' === $name ) {
					return '1G';
				}

				if ( 'WP_POST_REVISIONS' === $name ) {
					return 3;
				}

				if ( 'WP_CONTENT_DIR' === $name ) {
					return 'var/www/test/wp-content';
				}

				if ( 'WP_CONTENT_URL' === $name ) {
					return 'https://test.test/wp-content';
				}

				if ( 'PHP_VERSION' === $name ) {
					return '8.2.28';
				}

				return '';
			}
		);
	}

	/**
	 * Replace ini_get().
	 *
	 * @return void
	 */
	protected function replace_ini_get(): void {
		FunctionMocker::replace(
			'ini_get',
			static function ( $name ) {
				$sizes = [ 'memory_limit', 'upload_max_filesize', 'post_max_size' ];

				if ( in_array( $name, $sizes, true ) ) {
					return '2G';
				}

				if ( 'max_execution_time' === $name ) {
					return '0';
				}

				if ( 'max_input_vars' === $name ) {
					return '1000';
				}

				if ( 'session.name' === $name ) {
					return 'some session name';
				}

				if ( 'session.cookie_path' === $name ) {
					return 'some cookie path';
				}

				if ( 'session.save_path' === $name ) {
					return 'some save path';
				}

				if ( 'session.use_cookies' === $name ) {
					return true;
				}

				if ( 'session.use_only_cookies' === $name ) {
					return true;
				}

				return '';
			}
		);
	}
}
