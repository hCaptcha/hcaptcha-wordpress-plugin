<?php
/**
 * Create group class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\BuddyPress;

use HCaptcha\Helpers\HCaptcha;

/**
 * Class Create Group.
 */
class CreateGroup {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_bp_create_group';

	/**
	 * Nonce name.
	 */
	private const NAME = 'hcaptcha_bp_create_group_nonce';

	/**
	 * Create Group constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'bp_after_group_details_creation_step', [ $this, 'add_captcha' ] );
		add_action( 'groups_group_before_save', [ $this, 'verify' ] );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add captcha to the group form.
	 *
	 * @return void
	 */
	public function add_captcha(): void {
		echo '<div class="hcap_buddypress_group_form">';

		$args = [
			'action' => self::ACTION,
			'name'   => self::NAME,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => 'create_group',
			],
		];

		HCaptcha::form_display( $args );

		echo '</div>';
	}

	/**
	 * Verify group form captcha.
	 *
	 * @param mixed $bp_group BuddyPress group.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUndefinedFunctionInspection
	 */
	public function verify( $bp_group ): bool {
		if ( ! bp_is_group_creation_step( 'group-details' ) ) {
			return false;
		}

		$error_message = hcaptcha_get_verify_message( self::NAME, self::ACTION );

		if ( null !== $error_message ) {
			bp_core_add_message( $error_message, 'error' );
			bp_core_redirect(
				bp_get_root_url() . '/' . bp_get_groups_root_slug() . '/create/step/group-details/'
			);

			return false;
		}

		return true;
	}

	/**
	 * Print inline styles.
	 *
	 * @return void
	 * @noinspection CssUnusedSymbol
	 */
	public function print_inline_styles(): void {
		/* language=CSS */
		$css = '
	#buddypress .h-captcha {
		margin-top: 15px;
	}
';
		HCaptcha::css_display( $css );
	}
}
