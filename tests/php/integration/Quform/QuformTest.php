<?php
/**
 * QuformTest class file.
 *
 * @package HCaptcha\Tests
 */

// phpcs:disable Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

namespace HCaptcha\Tests\Integration\Quform;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Quform\Quform;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use Mockery;
use Quform_Element;
use Quform_Element_Field;
use Quform_Element_Page;
use Quform_Form;
use tad\FunctionMocker\FunctionMocker;

/**
 * Test the Quform integration.
 *
 * @group quform
 */
class QuformTest extends HCaptchaWPTestCase {

	/**
	 * Set up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		hcaptcha()->settings()->set( 'honeypot', 'on' );
		hcaptcha()->settings()->set( 'set_min_submit_time', 'on' );
		hcaptcha()->settings()->set( 'quform_status', 'form' );
		$this->set_protected_property( hcaptcha(), 'supported_forms', null );
	}

	/**
	 * Test that the rendered form contains honeypot fields.
	 *
	 * @return void
	 */
	public function test_add_hcaptcha_contains_honeypot(): void {
		$output = '<form><div class="quform-element quform-element-submit"></div></form>';
		$output = ( new Quform() )->add_hcaptcha( $output, 'quform', [ 'id' => 1 ], [] );

		self::assertStringContainsString( 'name="hcap_hp_test"', $output );
		self::assertStringContainsString( 'name="hcap_hp_sig"', $output );
		self::assertStringContainsString( 'name="hcaptcha-widget-id"', $output );
	}

	/**
	 * Existing Quform hCaptcha markup is replaced and element IDs remain unique.
	 */
	public function test_shortcode_variants(): void {
		$subject = new Quform();
		self::assertSame( 'original', $subject->add_hcaptcha( 'original', 'other', [], [] ) );

		$embedded = '<form><div class="quform-hcaptcha" data-test="yes">old</div></form>';
		$output   = $subject->add_hcaptcha( $embedded, 'quform', [ 'id' => 1 ], [] );
		self::assertStringNotContainsString( '>old</div>', $output );
		self::assertStringContainsString( 'h-captcha', $output );

		$numbered = '<form><div class="quform-element quform-element-1_3 quform-element-submit"></div>'
			. '<div class="quform-element quform-element-submit"></div></form>';
		$output   = $subject->add_hcaptcha( $numbered, 'quform', [ 'id' => 1 ], [] );
		self::assertStringContainsString( 'quform-element-1_4', $output );
	}

	/**
	 * Quform frontend scripts replace its native hCaptcha script.
	 */
	public function test_enqueue_scripts(): void {
		$subject = new Quform();
		wp_enqueue_script( 'quform-hcaptcha', 'native.js', [], '1.0.0', true );

		try {
			$subject->enqueue_scripts();
			self::assertFalse( wp_script_is( 'quform-hcaptcha', 'registered' ) );
			self::assertStringContainsString( 'hcaptcha-quform', wp_scripts()->registered['hcaptcha-quform']->src );
		} finally {
			wp_dequeue_script( 'hcaptcha-quform' );
			wp_deregister_script( 'hcaptcha-quform' );
		}
	}

	/**
	 * The Quform editor loads its hCaptcha preview script only on its settings pages.
	 *
	 * @noinspection PhpConditionAlreadyCheckedInspection
	 */
	public function test_admin_enqueue_scripts(): void {
		$subject = new Quform();
		$subject->admin_enqueue_scripts();
		self::assertFalse( wp_script_is( 'admin-quform', 'registered' ) );

		FunctionMocker::replace( '\HCaptcha\Quform\is_admin', true );
		unset( $GLOBALS['current_screen'] );
		$subject->admin_enqueue_scripts();
		self::assertFalse( wp_script_is( 'admin-quform', 'registered' ) );
		set_current_screen( 'forms_page_quform.forms' );
		$GLOBALS['current_screen']->id = 'forms_page_quform.forms';
		try {
			$subject->admin_enqueue_scripts();
			$script = wp_scripts()->registered['admin-quform'];
			self::assertStringContainsString( 'admin-quform', $script->src );
			self::assertStringContainsString( 'HCaptchaQuformObject', $script->extra['data'] );
		} finally {
			unset( $GLOBALS['current_screen'] );
			wp_dequeue_script( 'admin-quform' );
			wp_deregister_script( 'admin-quform' );
		}
	}

	/**
	 * Admin AJAX requests without a current screen do not load editor assets.
	 */
	public function test_admin_enqueue_scripts_without_screen(): void {
		FunctionMocker::replace( '\HCaptcha\Quform\is_admin', true );
		FunctionMocker::replace( '\HCaptcha\Quform\get_current_screen' );
		( new Quform() )->admin_enqueue_scripts();

		self::assertFalse( wp_script_is( 'admin-quform', 'registered' ) );
	}

	/**
	 * Quform hCaptcha element is considered valid after the plugin verifies the form.
	 */
	public function test_element_valid(): void {
		$subject = new Quform();
		$element = Mockery::mock( Quform_Element_Field::class );
		$element->shouldReceive( 'config' )->andReturn(
			[ 'type' => 'text' ],
			[
				'type'     => 'recaptcha',
				'provider' => 'hcaptcha',
			]
		);

		self::assertFalse( $subject->element_valid( false, '', $element ) );
		self::assertTrue( $subject->element_valid( false, '', $element ) );
	}

	/**
	 * Existing Quform validation failures are preserved before hCaptcha validation.
	 */
	public function test_existing_validation_failure_is_preserved(): void {
		$form   = Mockery::mock( Quform_Form::class );
		$result = [
			'type'    => 'error',
			'message' => 'native error',
		];

		self::assertSame( $result, ( new Quform() )->verify( $result, $form ) );
	}

	/**
	 * A valid Quform submission preserves its successful result.
	 */
	public function test_valid_submission_is_preserved(): void {
		$this->prepare_verify_post( 'hcaptcha_quform_nonce', 'hcaptcha_quform' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'quform/quform.php' ],
				'form_id' => 1,
			]
		);
		$form                                  = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->once()->andReturn( 1 );
		$form->shouldReceive( 'getValues' )->once()->andReturn( [] );
		$result = [ 'type' => 'success' ];

		self::assertSame( $result, ( new Quform() )->verify( $result, $form ) );
	}

	/**
	 * Quform locates its existing hCaptcha element or derives the next element ID.
	 */
	public function test_find_quform_hcaptcha_element_id(): void {
		$subject = new Quform();
		$method  = $this->set_method_accessibility( $subject, 'get_element_id' );
		$form    = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->andReturn( 1 );
		$captcha = Mockery::mock( Quform_Element::class );
		$captcha->shouldReceive( 'config' )->andReturn(
			[
				'type'     => 'recaptcha',
				'provider' => 'hcaptcha',
				'id'       => 7,
			]
		);
		$page = Mockery::mock( Quform_Element_Page::class );
		$page->shouldReceive( 'getElements' )->andReturn( [ $captcha ] );
		$page->shouldReceive( 'getForm' )->andReturn( $form );
		self::assertSame( '1_7', $method->invoke( $subject, $page ) );

		$text = Mockery::mock( Quform_Element::class );
		$text->shouldReceive( 'config' )->andReturn( [ 'type' => 'text' ] );
		$text->shouldReceive( 'getId' )->andReturn( 7 );
		$page = Mockery::mock( Quform_Element_Page::class );
		$page->shouldReceive( 'getElements' )->andReturn( [ $text ] );
		$page->shouldReceive( 'getForm' )->andReturn( $form );
		self::assertSame( '1_8', $method->invoke( $subject, $page ) );
	}

	/**
	 * Only ordinary Quform content fields reach the anti-spam entry.
	 */
	public function test_form_entry_collects_non_sensitive_fields(): void {
		$elements = [];
		foreach ( [
			'email'    => [
				'type'  => 'email',
				'label' => 'Email',
			],
			'name'     => [
				'type'  => 'name',
				'label' => 'Name',
			],
			'password' => [
				'type'  => 'password',
				'label' => 'Password',
			],
		] as $id => $config ) {
			$element = Mockery::mock( Quform_Element::class );
			$element->shouldReceive( 'config' )->andReturn( $config );
			$element->shouldReceive( 'getId' )->andReturn( $id );
			$elements[ $id ] = $element;
		}
		$form = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->andReturn( 1 );
		$form->shouldReceive( 'getValues' )->andReturn(
			[
				'email'    => 'person@example.com',
				'name'     => [ 'Jane', 'Doe' ],
				'password' => 'secret',
				'missing'  => 'ignored',
			]
		);
		$form->shouldReceive( 'getElement' )->andReturnUsing(
			static function ( $id ) use ( $elements ) {
				return $elements[ $id ] ?? null;
			}
		);
		$subject = new Quform();
		$method  = $this->set_method_accessibility( $subject, 'get_entry' );
		$entry   = $method->invoke( $subject, $form );

		self::assertSame( 'person@example.com', $entry['email'] );
		self::assertSame( 'Jane Doe', $entry['name'] );
		self::assertArrayNotHasKey( 'Password', $entry['data'] );
	}

	/**
	 * Quform updated timestamp is included in the anti-spam entry.
	 */
	public function test_form_entry_includes_updated_timestamp(): void {
		global $wpdb;

		$original = $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Swap the database handle for this test only.
		$wpdb = Mockery::mock( $original )->makePartial();
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( '2026-10-29 12:00:00' );
		$form = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->andReturn( 1 );
		$form->shouldReceive( 'getValues' )->andReturn( [] );

		try {
			$subject = new Quform();
			$method  = $this->set_method_accessibility( $subject, 'get_entry' );
			$entry   = $method->invoke( $subject, $form );
			self::assertSame( '2026-10-29 12:00:00', $entry['form_date_gmt'] );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original database handle.
			$wpdb = $original;
		}
	}

	/**
	 * Test that a filled honeypot blocks Quform processing.
	 *
	 * @return void
	 */
	public function test_filled_honeypot_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_quform_nonce', 'hcaptcha_quform' );

		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'quform/quform.php' ],
				'form_id' => 1,
			]
		);
		$_POST['hcap_hp_test']                 = 'bot';

		$form = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->once()->andReturn( 1 );
		$form->shouldReceive( 'getValues' )->once()->andReturn( [] );
		$form->shouldReceive( 'getCurrentPage' )->once()->andReturn( null );

		$result = ( new Quform() )->verify( [], $form );

		self::assertSame( 'error', $result['type'] );
		self::assertSame( 'Anti-spam check failed.', $result['errors']['9999_9999'] );
	}

	/**
	 * A widget rendered for another Quform form is rejected before proof verification.
	 */
	public function test_foreign_form_widget_is_rejected(): void {
		$this->prepare_verify_post( 'hcaptcha_quform_nonce', 'hcaptcha_quform' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value(
			[
				'source'  => [ 'quform/quform.php' ],
				'form_id' => 2,
			]
		);

		$form = Mockery::mock( Quform_Form::class );
		$form->shouldReceive( 'getId' )->once()->andReturn( 1 );
		$form->shouldReceive( 'getValues' )->once()->andReturn( [] );
		$form->shouldReceive( 'getCurrentPage' )->once()->andReturn( null );

		$result = ( new Quform() )->verify( [], $form );

		self::assertSame( 'error', $result['type'] );
		self::assertSame( hcap_get_error_messages()['bad-signature'], $result['errors']['9999_9999'] );
	}
}
