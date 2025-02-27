<?php
/**
 * ReallySimpleCaptcha form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\CF7;

use WPCF7_Submission;

/**
 * Class ReallySimpleCaptcha.
 */
class ReallySimpleCaptcha extends Base {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		if ( ! hcaptcha()->settings()->is( 'cf7_status', 'replace_rsc' ) ) {
			return;
		}

		parent::init_hooks();

		// Do not add captcha tags.
		add_action( 'wpcf7_init', [ $this, 'remove_wpcf7_add_form_tag_captcha_action' ], 0 );

		// Replace captchac/captchar shortcodes with hCaptcha.
		add_filter( 'do_shortcode_tag', [ $this, 'wpcf7_shortcode' ], 10, 4 );

		add_filter( 'hcap_cf7_has_field', [ $this, 'has_field' ], 10, 3 );
	}

	/**
	 * Remove wpcf7_add_form_tag_captcha action.
	 *
	 * @return void
	 */
	public function remove_wpcf7_add_form_tag_captcha_action(): void {
		remove_action( 'wpcf7_init', 'wpcf7_add_form_tag_captcha' );
	}

	/**
	 * Replace captcha shortcodes with hCaptcha.
	 *
	 * @param string|mixed $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function wpcf7_shortcode( $output, string $tag, $attr, array $m ): string {
		$output = (string) $output;

		if ( 'contact-form-7' !== $tag ) {
			return $output;
		}

		// Remove both captcha shortcodes. Insert cf7-hcaptcha shortcode.
		return preg_replace(
			[ '/\[captchac([^]]*)]/', '/\[captchar[^]]*]/' ],
			[ '[cf7-hcaptcha$1]', '' ],
			$output
		);
	}

	/**
	 * Check if the form has captchac/captchar fields.
	 *
	 * @param bool|mixed       $has_field  Form has field.
	 * @param WPCF7_Submission $submission Submission.
	 * @param string           $type       Field type.
	 *
	 * @return bool
	 */
	public function has_field( $has_field, WPCF7_Submission $submission, string $type ): bool {
		$has_field    = (bool) $has_field;
		$contact_form = $submission->get_contact_form();

		if ( CF7::FIELD_TYPE === $type && preg_match( '/\[captchar[^]]*]/', $contact_form->form_html() ) ) {
			$has_field = true;
		}

		return $has_field;
	}
}
