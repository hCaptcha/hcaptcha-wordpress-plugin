<?php
/**
 * BlockParserTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\Kadence;

use HCaptcha\Kadence\BlockParser;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;

/**
 * Test Kadence BlockParser.
 *
 * @group kadence
 * @group kadence-block-parser
 */
class BlockParserTest extends HCaptchaWPTestCase {

	/**
	 * Set up test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		BlockParser::$form_id = 0;
	}

	/**
	 * Test parse() method.
	 *
	 * @return void
	 */
	public function test_parse(): void {
		$subject = new BlockParser();

		// No blocks.
		$document = 'some text';

		self::assertNull( $subject->parse( $document )[0]['blockName'] );
		self::assertEquals( 0, BlockParser::$form_id );

		// Some block.
		$document = '<!-- wp:some/some {"id":123} --><div></div><!-- /wp:some/some -->';

		self::assertSame( 'some/some', $subject->parse( $document )[0]['blockName'] );
		self::assertEquals( 0, BlockParser::$form_id );

		// Advanced form block.
		$document = '<!-- wp:kadence/advanced-form {"id":123} --><div class="wp-block-kadence-advanced-form"></div><!-- /wp:kadence/advanced-form -->';

		self::assertEquals( 'kadence/advanced-form', $subject->parse( $document )[0]['blockName'] );
		self::assertEquals( 123, BlockParser::$form_id );

		// Advanced form block with hCaptcha.
		$document = '<!-- wp:kadence/advanced-form {"id":456} --><!-- wp:kadence/advanced-form-captcha {"type":"hcaptcha"} --><div class="wp-block-kadence-advanced-form-captcha"></div><!-- /wp:kadence/advanced-form-captcha --><!-- /wp:kadence/advanced-form -->';
		$result   = $subject->parse( $document );

		self::assertEquals( 'kadence/advanced-form', $result[0]['blockName'] );
		self::assertEquals( 456, BlockParser::$form_id );
		self::assertEmpty( $result[0]['innerBlocks'] );
	}
}
