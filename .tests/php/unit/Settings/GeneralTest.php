<?php
/**
 * GeneralTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Unit\Settings;

use HCaptcha\Settings\Abstracts\SettingsBase;
use HCaptcha\Settings\General;
use HCaptcha\Tests\Unit\Stubs\Settings\Tables;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use ReflectionException;
use tad\FunctionMocker\FunctionMocker;
use WP_Mock;

/**
 * Class GeneralTest
 *
 * @group settings
 * @group settings-general
 */
class GeneralTest extends HCaptchaTestCase {

	/**
	 * Test screen_id().
	 */
	public function test_screen_id() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		self::assertSame( 'settings_page_hcaptcha', $subject->screen_id() );
	}

	/**
	 * Test option_group().
	 */
	public function test_option_group() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'option_group';
		self::assertSame( 'hcaptcha_group', $subject->$method() );
	}

	/**
	 * Test option_page().
	 */
	public function test_option_page() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'option_page';
		self::assertSame( 'hcaptcha', $subject->$method() );
	}

	/**
	 * Test option_name().
	 */
	public function test_option_name() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'option_name';
		self::assertSame( 'hcaptcha_settings', $subject->$method() );
	}

	/**
	 * Test page_title().
	 */
	public function test_page_title() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'page_title';
		self::assertSame( 'General', $subject->$method() );
	}

	/**
	 * Test menu_title().
	 */
	public function test_menu_title() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'menu_title';
		self::assertSame( 'hCaptcha', $subject->$method() );
	}

	/**
	 * Test section_title().
	 */
	public function test_section_title() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		$method = 'section_title';
		self::assertSame( 'general', $subject->$method() );
	}

	/**
	 * Test init_form_fields()
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_init_form_fields() {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		$expected = $this->get_test_general_form_fields();

		$subject->init_form_fields();
		self::assertSame( $expected, $this->get_protected_property( $subject, 'form_fields' ) );
	}

	/**
	 * Test section_callback().
	 *
	 * @param string $section_id Section id.
	 * @param string $expected   Expected.
	 *
	 * @return void
	 * @dataProvider dp_test_section_callback
	 */
	public function test_section_callback( $section_id, $expected ) {
		$subject = Mockery::mock( General::class )->makePartial()->shouldAllowMockingProtectedMethods();

		WP_Mock::passthruFunction( 'wp_kses_post' );

		ob_start();
		$subject->section_callback( [ 'id' => $section_id ] );
		self::assertSame( $expected, ob_get_clean() );
	}

	/**
	 * Data provider for test_section_callback().
	 *
	 * @return array
	 */
	public function dp_test_section_callback() {
		return [
			'keys'       => [
				General::SECTION_KEYS,
				'				<h2>
					General				</h2>
				<p>
					To use <a href="https://www.hcaptcha.com/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank">hCaptcha</a>, please register <a href="https://www.hcaptcha.com/signup-interstitial/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=sk" target="_blank">here</a> to get your site and secret keys.				</p>
						<h3 class="hcaptcha-section-keys">Keys</h3>
		',
			],
			'appearance' => [
				General::SECTION_APPEARANCE,
				'		<h3 class="hcaptcha-section-appearance">Appearance</h3>
		',
			],
			'custom'     => [
				General::SECTION_CUSTOM,
				'		<h3 class="hcaptcha-section-custom">Custom</h3>
		',
			],
			'other'      => [
				General::SECTION_OTHER,
				'		<h3 class="hcaptcha-section-other">Other</h3>
		',
			],
			'wrong'      => [ 'wrong', '' ],
		];
	}

	/**
	 * Test admin_enqueue_scripts().
	 *
	 * @throws ReflectionException ReflectionException.
	 */
	public function test_admin_enqueue_scripts() {
		$plugin_url     = 'http://test.test/wp-content/plugins/hcaptcha-wordpress-plugin';
		$plugin_version = '1.0.0';
		$min_prefix     = '.min';

		$subject = Mockery::mock( General::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->with()->andReturn( true );
		$this->set_protected_property( $subject, 'min_prefix', $min_prefix );

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

		WP_Mock::userFunction( 'wp_enqueue_style' )
			->with(
				General::HANDLE,
				$plugin_url . "/assets/css/general$min_prefix.css",
				[ SettingsBase::HANDLE ],
				$plugin_version
			)
			->once();

		$subject->admin_enqueue_scripts();
	}

	/**
	 * Test admin_enqueue_scripts() not on own screen.
	 */
	public function test_admin_enqueue_scripts_not_on_own_screen() {
		$subject = Mockery::mock( General::class )->makePartial();
		$subject->shouldAllowMockingProtectedMethods();
		$subject->shouldReceive( 'is_options_screen' )->with()->andReturn( false );

		WP_Mock::userFunction( 'wp_enqueue_style' )->never();

		$subject->admin_enqueue_scripts();
	}
}
