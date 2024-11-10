<?php
/**
 * AdvancedBlockParserTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Kadence\AdvancedBlockParser;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Kadence AdvancedBlockParser.
 *
 * @group kadence
 * @group kadence-advanced-block-parser
 */
class AdvancedBlockParserTest extends HCaptchaWPTestCase {

	/**
	 * Test parse() method.
	 *
	 * @return void
	 */
	public function test_parse(): void {
		$subject = new AdvancedBlockParser();

		// No blocks.
		$document = 'some text';

		self::assertNull( $subject->parse( $document )[0]['blockName'] );
		self::assertEquals( 0, AdvancedBlockParser::$form_id );

		// Some block.
		$document = '<!-- wp:some/some {"id":123} --><div></div><!-- /wp:some/some -->';

		self::assertSame( 'some/some', $subject->parse( $document )[0]['blockName'] );
		self::assertEquals( 0, AdvancedBlockParser::$form_id );

		// Advanced form block.
		$document = '<!-- wp:kadence/advanced-form {"id":123} --><div class="wp-block-kadence-advanced-form"></div><!-- /wp:kadence/advanced-form -->';

		self::assertEquals( 'kadence/advanced-form', $subject->parse( $document )[0]['blockName'] );
		self::assertEquals( 123, AdvancedBlockParser::$form_id );

		// Advanced form block with hCaptcha.
		$document = '<!-- wp:kadence/advanced-form {"id":456} --><!-- wp:kadence/advanced-form-captcha {"type":"hcaptcha"} --><div class="wp-block-kadence-advanced-form-captcha"></div><!-- /wp:kadence/advanced-form-captcha --><!-- /wp:kadence/advanced-form -->';
		$result   = $subject->parse( $document );

		self::assertEquals( 'kadence/advanced-form', $result[0]['blockName'] );
		self::assertEquals( 456, AdvancedBlockParser::$form_id );
		self::assertEmpty( $result[0]['innerBlocks'] );
	}
}
