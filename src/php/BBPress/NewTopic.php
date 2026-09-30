<?php
/**
 * New Topic class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BBPress;

use HCaptcha\Helpers\EntryData;

/**
 * Class New Topic.
 */
class NewTopic extends Base {

	/**
	 * Nonce action.
	 */
	protected const ACTION = 'hcaptcha_bbp_new_topic';

	/**
	 * Nonce name.
	 */
	protected const NAME = 'hcaptcha_bbp_new_topic_nonce';

	/**
	 * Add captcha hook.
	 */
	protected const ADD_CAPTCHA_HOOK = 'bbp_theme_after_topic_form_content';

	/**
	 * Verify hook.
	 */
	protected const VERIFY_HOOK = 'bbp_new_topic_pre_extras';

	/**
	 * Get the new topic's submitted fields.
	 *
	 * @return array
	 */
	protected function get_entry(): array {
		$entry         = parent::get_entry();
		$entry['data'] = EntryData::from_post(
			[
				'email'   => 'bbp_anonymous_email',
				'name'    => 'bbp_anonymous_name',
				'title'   => 'bbp_topic_title',
				'message' => 'bbp_topic_content',
			]
		);

		return $entry;
	}
}
