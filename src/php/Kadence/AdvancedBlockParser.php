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
class AdvancedBlockParser extends WP_Block_Parser {

	/**
	 * Form id.
	 *
	 * @var mixed
	 */
	public static $form_id = 0;

	/**
	 * Parses a document and returns a list of block structures
	 *
	 * When encountering an invalid parse will return a best-effort
	 * parse. In contrast to the specification parser, this does not
	 * return an error on invalid inputs.
	 *
	 * @param string $document Input document being parsed.
	 * @return array[]
	 */
	public function parse( $document ): array {
		$output = parent::parse( $document );

		if ( ! isset( $output[0] ) ) {
			return $output;
		}

		$block = $output[0];

		if ( ! ( isset( $block['blockName'] ) && 'kadence/advanced-form' === $block['blockName'] ) ) {
			return $output;
		}

		if ( isset( $block['attrs']['id'] ) ) {
			self::$form_id = $block['attrs']['id'];
		}

		if ( ! ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) ) {
			return $output;
		}

		foreach ( $block['innerBlocks'] as $index => $inner_block ) {
			if (
				isset( $inner_block['blockName'], $inner_block['attrs']['type'] ) &&
				'kadence/advanced-form-captcha' === $inner_block['blockName'] &&
				'hcaptcha' === $inner_block['attrs']['type']
			) {
				unset( $block['innerBlocks'][ $index ] );

				$output[0] = $block;
				break;
			}
		}

		return $output;
	}
}
