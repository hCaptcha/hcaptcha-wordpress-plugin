<?php
/**
 * JetpackTestCase class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Jetpack;

use Automattic\Jetpack\Forms\ContactForm\Contact_Form_Plugin as JetpackFormPlugin;
use Automattic\Jetpack\Forms\Jetpack_Forms as JetpackForms;
use HCaptcha\Tests\Integration\HCaptchaPluginWPTestCase;
use Jetpack;

/**
 * Base test case for the live Jetpack plugin.
 */
abstract class JetpackTestCase extends HCaptchaPluginWPTestCase {

	/**
	 * Plugin relative path.
	 *
	 * @var string
	 */
	protected static $plugin = 'jetpack/jetpack.php';

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'jetpack_offline_mode', '__return_true' );

		if ( ! Jetpack::activate_module( 'contact-form', false, false ) ) {
			self::fail( 'Cannot activate the live Jetpack Forms module.' );
		}

		// Restore live Forms hooks removed by WordPress test isolation.
		JetpackForms::load_contact_form();

		$jetpack_form_plugin = JetpackFormPlugin::init();

		$jetpack_form_plugin->add_shortcode();
		add_filter( 'jetpack_contact_form_is_spam', [ $jetpack_form_plugin, 'is_spam_blocklist' ], 10, 2 );
	}

	/**
	 * Tear down the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'jetpack_offline_mode', '__return_true' );

		parent::tearDown();
	}
}
