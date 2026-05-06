<?php
/**
 * BlockParser class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Kadence;

use WP_Block_Parser;

/**
 * Class BlockParser.
 */
class BlockParser extends WP_Block_Parser {

	/**
	 * Form id.
	 *
	 * @var mixed
	 */
	public static $form_id = 0;

	/**
	 * Parses a document and returns a list of block structures
	 *
	 * When encountering an invalid content, parse will return a best-effort parse.
	 * In contrast to the specification parser, this does not return an error on invalid inputs.
	 *
	 * @param string $document Input document being parsed.
	 *
	 * @return array[]
	 */
	public function parse( $document ): array {
		$output = parent::parse( $document );

		foreach ( $output as &$block ) {
			$this->process_block( $block );
		}

		return $output;
	}

	/**
	 * Process block.
	 *
	 * @param array $block Block.
	 *
	 * @return void
	 */
	private function process_block( array &$block ): void {
		$block_name = $block['blockName'] ?? '';

		switch ( $block_name ) {
			case 'kadence/form':
				$this->process_form_block( $block );
				break;
			case 'kadence/advanced-form':
				$this->process_advanced_form_block( $block );
				break;
			default:
				break;
		}
	}

	/**
	 * Process form block.
	 *
	 * @param array $block Block.
	 *
	 * @return void
	 */
	private function process_form_block( array &$block ): void {
		// Disable reCAPTCHA.
		$block['attrs']['recaptcha'] = false;
	}

	/**
	 * Process advanced form block.
	 *
	 * @param array $block Block.
	 *
	 * @return void
	 */
	private function process_advanced_form_block( array &$block ): void {
		self::$form_id = $block['attrs']['id'] ?? 0;

		if ( ! ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		foreach ( $block['innerBlocks'] as $index => $inner_block ) {
			if (
				isset( $inner_block['blockName'], $inner_block['attrs']['type'] ) &&
				'kadence/advanced-form-captcha' === $inner_block['blockName'] &&
				'hcaptcha' === $inner_block['attrs']['type']
			) {
				unset( $block['innerBlocks'][ $index ] );

				break;
			}
		}
	}
}
