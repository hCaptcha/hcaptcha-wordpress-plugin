<?php
/**
 * EssentialBlocksDetectorTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Unit\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;
use HCaptcha\MigrationWizard\Detectors\EssentialBlocksDetector;
use HCaptcha\Tests\Unit\HCaptchaTestCase;
use Mockery;
use WP_Mock;

/**
 * Test EssentialBlocksDetector class.
 *
 * @group migration-wizard
 */
class EssentialBlocksDetectorTest extends HCaptchaTestCase {

	/**
	 * Remove the database mock after each test.
	 */
	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		parent::tearDown();
	}

	/**
	 * Test source identity.
	 */
	public function test_source_identity(): void {
		$detector = new EssentialBlocksDetector();

		self::assertSame( 'essential-blocks/essential-blocks.php', $detector->get_source_plugin() );
		self::assertSame( 'Essential Blocks', $detector->get_source_name() );
	}

	/**
	 * Test applicability when Essential Blocks is active.
	 */
	public function test_is_applicable_when_active(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [ 'essential-blocks/essential-blocks.php' ] );

		self::assertTrue( ( new EssentialBlocksDetector() )->is_applicable() );
	}

	/**
	 * Test applicability when Essential Blocks is inactive.
	 */
	public function test_is_applicable_when_inactive(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'active_plugins', [] )
			->andReturn( [] );

		self::assertFalse( ( new EssentialBlocksDetector() )->is_applicable() );
	}

	/**
	 * Mock the query for candidate form posts.
	 *
	 * @param array $post_ids Candidate post IDs.
	 */
	private function mock_candidate_posts( array $post_ids ): void {
		global $wpdb;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wpdb        = Mockery::mock( 'wpdb' );
		$wpdb->posts = 'wp_posts';
		$wpdb->shouldReceive( 'esc_like' )
			->once()
			->with( '<!-- wp:essential-blocks/form' )
			->andReturn( 'form-marker' );
		$wpdb->shouldReceive( 'esc_like' )
			->once()
			->with( 'recaptcha' )
			->andReturn( 'recaptcha-marker' );
		$wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				"SELECT ID FROM wp_posts WHERE post_status IN ('publish', 'private', 'future') AND post_content LIKE %s AND post_content LIKE %s",
				'%form-marker%',
				'%recaptcha-marker%'
			)
			->andReturn( 'prepared-query' );
		$wpdb->shouldReceive( 'get_col' )
			->once()
			->with( 'prepared-query' )
			->andReturn( $post_ids );
	}

	/**
	 * Mock a candidate post and its parsed blocks.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $content Post content.
	 * @param array $blocks  Parsed blocks.
	 */
	private function mock_post( int $post_id, $content, array $blocks = [] ): void {
		WP_Mock::userFunction( 'get_post_field' )
			->once()
			->with( 'post_content', $post_id, 'raw' )
			->andReturn( $content );

		if ( is_string( $content ) ) {
			WP_Mock::userFunction( 'parse_blocks' )
				->once()
				->with( $content )
				->andReturn( $blocks );
		}
	}

	/**
	 * Test detection with no candidate posts.
	 */
	public function test_detect_with_no_candidate_posts(): void {
		$this->mock_candidate_posts( [] );

		self::assertSame( [], ( new EssentialBlocksDetector() )->detect() );
	}

	/**
	 * Test a reCAPTCHA block directly inside a form.
	 */
	public function test_detect_direct_recaptcha_block(): void {
		$this->mock_candidate_posts( [ '12' ] );
		$this->mock_post(
			12,
			'direct-form',
			[
				[
					'blockName'   => 'essential-blocks/form',
					'innerBlocks' => [ [ 'blockName' => 'essential-blocks/recaptcha' ] ],
				],
			]
		);

		$results = ( new EssentialBlocksDetector() )->detect();

		self::assertCount( 1, $results );
		self::assertSame(
			[
				'provider'              => 'recaptcha',
				'source_plugin'         => 'essential-blocks/essential-blocks.php',
				'source_name'           => 'Essential Blocks',
				'surface'               => 'essential_blocks_form',
				'surface_label'         => 'Essential Blocks Form',
				'confidence'            => DetectionResult::CONFIDENCE_HIGH,
				'support_status'        => DetectionResult::STATUS_SUPPORTED,
				'hcaptcha_option_key'   => 'essential_blocks_status',
				'hcaptcha_option_value' => 'form',
				'notes'                 => 'An Essential Blocks form contains a Google reCAPTCHA block. Remove that block from each form after enabling hCaptcha.',
				'is_migratable'         => true,
			],
			$results[0]->to_array()
		);
	}

	/**
	 * Test a reCAPTCHA block nested inside another form block.
	 */
	public function test_detect_nested_recaptcha_block(): void {
		$this->mock_candidate_posts( [ 13 ] );
		$this->mock_post(
			13,
			'nested-recaptcha',
			[
				[
					'blockName'   => 'essential-blocks/form',
					'innerBlocks' => [
						[
							'blockName'   => 'core/group',
							'innerBlocks' => [ [ 'blockName' => 'essential-blocks/ReCaptcha' ] ],
						],
					],
				],
			]
		);

		self::assertCount( 1, ( new EssentialBlocksDetector() )->detect() );
	}

	/**
	 * Test an Essential Blocks form nested inside another block.
	 */
	public function test_detect_nested_form(): void {
		$this->mock_candidate_posts( [ 14 ] );
		$this->mock_post(
			14,
			'nested-form',
			[
				[
					'blockName'   => 'core/group',
					'innerBlocks' => [
						[
							'blockName'   => 'essential-blocks/form',
							'innerBlocks' => [ [ 'blockName' => 'essential-blocks/recaptcha' ] ],
						],
					],
				],
			]
		);

		self::assertCount( 1, ( new EssentialBlocksDetector() )->detect() );
	}

	/**
	 * Ignore reCAPTCHA outside an Essential Blocks form.
	 */
	public function test_detect_ignores_recaptcha_outside_form(): void {
		$this->mock_candidate_posts( [ 15 ] );
		$this->mock_post(
			15,
			'recaptcha-outside-form',
			[
				[ 'blockName' => 'essential-blocks/recaptcha' ],
				[
					'blockName'   => 'essential-blocks/form',
					'innerBlocks' => [ [ 'blockName' => 'core/paragraph' ] ],
				],
			]
		);

		self::assertSame( [], ( new EssentialBlocksDetector() )->detect() );
	}

	/**
	 * Ignore invalid post content and continue to the next candidate.
	 */
	public function test_detect_skips_non_string_content(): void {
		$this->mock_candidate_posts( [ 16, 17 ] );
		$this->mock_post( 16, null );
		$this->mock_post(
			17,
			'valid-form',
			[
				[
					'blockName'   => 'essential-blocks/form',
					'innerBlocks' => [ [ 'blockName' => 'essential-blocks/recaptcha' ] ],
				],
			]
		);

		self::assertCount( 1, ( new EssentialBlocksDetector() )->detect() );
	}

	/**
	 * Ignore malformed block names and missing inner block lists.
	 */
	public function test_detect_ignores_malformed_blocks(): void {
		$this->mock_candidate_posts( [ 18 ] );
		$this->mock_post(
			18,
			'malformed-form',
			[
				[ 'innerBlocks' => [] ],
				[
					'blockName'   => 'essential-blocks/form',
					'innerBlocks' => [ [ 'blockName' => [ 'recaptcha' ] ] ],
				],
			]
		);

		self::assertSame( [], ( new EssentialBlocksDetector() )->detect() );
	}
}
