<?php
/**
 * EssentialBlocksDetector class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard\Detectors;

use HCaptcha\MigrationWizard\DetectionResult;

/**
 * Detects reCAPTCHA inner blocks in Essential Blocks forms.
 */
class EssentialBlocksDetector extends AbstractDetector {

	/**
	 * Essential Blocks plugin slug.
	 */
	private const PLUGIN_SLUG = 'essential-blocks/essential-blocks.php';

	/**
	 * Get the source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return self::PLUGIN_SLUG;
	}

	/**
	 * Get the source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return 'Essential Blocks';
	}

	/**
	 * Check if Essential Blocks is active.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return $this->is_plugin_active( self::PLUGIN_SLUG );
	}

	/**
	 * Detect forms containing a reCAPTCHA inner block.
	 *
	 * @return DetectionResult[]
	 */
	public function detect(): array {
		if ( ! $this->has_recaptcha_form() ) {
			return [];
		}

		return [
			$this->build_result(
				'recaptcha',
				'essential_blocks_form',
				DetectionResult::CONFIDENCE_HIGH,
				'An Essential Blocks form contains a Google reCAPTCHA block. Remove that block from each form after enabling hCaptcha.'
			),
		];
	}

	/**
	 * Find a saved Essential Blocks form containing a reCAPTCHA block.
	 *
	 * @return bool
	 */
	private function has_recaptcha_form(): bool {
		global $wpdb;

		$form_marker      = '%' . $wpdb->esc_like( '<!-- wp:essential-blocks/form' ) . '%';
		$recaptcha_marker = '%' . $wpdb->esc_like( 'recaptcha' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_status IN ('publish', 'private', 'future') AND post_content LIKE %s AND post_content LIKE %s",
				$form_marker,
				$recaptcha_marker
			)
		);

		foreach ( $post_ids as $post_id ) {
			$content = get_post_field( 'post_content', (int) $post_id, 'raw' );

			if ( is_string( $content ) && $this->contains_recaptcha_form( parse_blocks( $content ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Search parsed blocks for an Essential Blocks form with reCAPTCHA.
	 *
	 * @param array $blocks Parsed blocks.
	 *
	 * @return bool
	 */
	private function contains_recaptcha_form( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			$inner_blocks = $block['innerBlocks'] ?? [];

			if ( 'essential-blocks/form' === ( $block['blockName'] ?? '' ) && $this->contains_recaptcha_block( $inner_blocks ) ) {
				return true;
			}

			if ( $this->contains_recaptcha_form( $inner_blocks ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Search a form's inner blocks for reCAPTCHA.
	 *
	 * @param array $blocks Parsed inner blocks.
	 *
	 * @return bool
	 */
	private function contains_recaptcha_block( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			if ( is_string( $block_name ) && false !== stripos( $block_name, 'recaptcha' ) ) {
				return true;
			}

			if ( $this->contains_recaptcha_block( $block['innerBlocks'] ?? [] ) ) {
				return true;
			}
		}

		return false;
	}
}
