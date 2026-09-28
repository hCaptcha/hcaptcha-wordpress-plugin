<?php
/**
 * EssentialBlocksDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\MigrationWizard;

use HCaptcha\MigrationWizard\Detectors\EssentialBlocksDetector;
use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Essential Blocks detection with saved WordPress blocks.
 *
 * @group migration-wizard
 */
class EssentialBlocksDetectorTest extends HCaptchaWPTestCase {

	/**
	 * Test source details and applicability.
	 */
	public function test_source_and_applicability(): void {
		$detector = new EssentialBlocksDetector();

		self::assertSame( 'essential-blocks/essential-blocks.php', $detector->get_source_plugin() );
		self::assertSame( 'Essential Blocks', $detector->get_source_name() );
		self::assertFalse( $detector->is_applicable() );

		update_option( 'active_plugins', [ 'essential-blocks/essential-blocks.php' ] );

		self::assertTrue( $detector->is_applicable() );
	}

	/**
	 * Test a saved form containing a reCAPTCHA inner block.
	 *
	 * @return void
	 */
	public function test_detect_saved_recaptcha_form(): void {
		$post_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Essential Blocks form',
				'post_content' => '<!-- wp:essential-blocks/form /-->' .
					'<!-- wp:essential-blocks/recaptcha /-->',
			]
		);

		self::assertIsInt( $post_id );
		self::assertSame( [], ( new EssentialBlocksDetector() )->detect() );

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => '<!-- wp:essential-blocks/form -->' .
					'<!-- wp:essential-blocks/recaptcha /-->' .
					'<!-- /wp:essential-blocks/form -->',
			]
		);

		$results = ( new EssentialBlocksDetector() )->detect();

		self::assertCount( 1, $results );
		self::assertSame( 'recaptcha', $results[0]->get_provider() );
		self::assertSame( 'essential_blocks_form', $results[0]->get_surface() );
		self::assertSame( DetectionResult::CONFIDENCE_HIGH, $results[0]->get_confidence() );
		self::assertTrue( $results[0]->is_migratable() );
		self::assertSame( 'essential_blocks_status', $results[0]->get_hcaptcha_option_key() );
		self::assertSame( 'form', $results[0]->get_hcaptcha_option_value() );
	}

	/**
	 * Test a reCAPTCHA block inside a form nested in a group.
	 */
	public function test_detect_recaptcha_in_nested_form(): void {
		$post_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Nested Essential Blocks form',
				'post_content' => '<!-- wp:group -->' .
					'<!-- wp:essential-blocks/form -->' .
					'<!-- wp:essential-blocks/recaptcha /-->' .
					'<!-- /wp:essential-blocks/form -->' .
					'<!-- /wp:group -->',
			]
		);

		self::assertIsInt( $post_id );
		self::assertCount( 1, ( new EssentialBlocksDetector() )->detect() );
	}

	/**
	 * Test that no migration is proposed without a candidate post.
	 */
	public function test_detect_without_recaptcha(): void {
		self::assertSame( [], ( new EssentialBlocksDetector() )->detect() );
	}
}
