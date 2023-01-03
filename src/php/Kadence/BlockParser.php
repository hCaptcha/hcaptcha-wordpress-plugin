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
	 * Parses a document and returns a list of block structures
	 *
	 * When encountering an invalid parse will return a best-effort
	 * parse. In contrast to the specification parser this does not
	 * return an error on invalid inputs.
	 *
	 * @param string $document Input document being parsed.
	 * @return array[]
	 */
	public function parse( $document ) {
		$output = parent::parse( $document );

		if ( 'kadence/form' === $output[0]['blockName'] && isset( $output[0]['attrs']['recaptcha'] ) ) {
			$output[0]['attrs']['recaptcha'] = false;
		}

		return $output;
	}
}
