<?php
/**
 * CommentBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Error;

/**
 * Class CommentBase
 */
abstract class CommentBase {

	/**
	 * Signature group.
	 */
	protected const SIGNATURE_GROUP = 'comment';

	/**
	 * The hCaptcha was shown by the current class.
	 *
	 * @var bool
	 */
	protected $hcaptcha_shown = false;

	/**
	 * Verification result.
	 *
	 * @var string|bool|null
	 */
	protected $result;

	/**
	 * Form ID.
	 *
	 * @var int
	 */
	protected $form_id = 0;

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		add_action( 'hcap_signature_' . self::SIGNATURE_GROUP, [ $this, 'display_signature' ] );

		add_filter( 'preprocess_comment', [ $this, 'verify' ], - PHP_INT_MAX );
		add_filter( 'pre_comment_approved', [ $this, 'pre_comment_approved' ], 20, 2 );
	}

	/**
	 * Get signature.
	 *
	 * @return string
	 */
	public function get_signature(): string {
		return HCaptcha::get_signature( static::class, $this->form_id, $this->hcaptcha_shown );
	}

	/**
	 * Display signature.
	 *
	 * @return void
	 */
	public function display_signature(): void {
		HCaptcha::display_signature( static::class, $this->form_id, $this->hcaptcha_shown );
	}

	/**
	 * Verify comment.
	 *
	 * @param array|mixed $comment_data Comment data.
	 *
	 * @return array
	 */
	public function verify( $comment_data ): array {
		$comment_data = (array) $comment_data;

		if ( ! Request::is_frontend() ) {
			// Do not work in ajax mode. It is served by WPDiscuz.
			return $comment_data;
		}

		$this->result = HCaptcha::check_signature( static::class );

		if ( true === $this->result ) {
			return $comment_data;
		}

		if ( false === $this->result ) {
			$this->result = hcap_get_error_messages()['bad-signature'];

			return $comment_data;
		}

		// Override poor IP detection by WP Core and make sure that IP is the same in the 'comments' table and in the 'hcaptcha_events' table.
		$comment_data['comment_author_IP'] = hcap_get_user_ip();

		$this->result = API::verify_post_html( static::NONCE, static::ACTION );

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		if ( null !== $this->result ) {
			// Block Akismet activity to reduce its API calls.
			remove_action( 'preprocess_comment', [ 'Akismet', 'auto_check_comment' ], 1 );
		}

		return $comment_data;
	}

	/**
	 * Pre-approve comment.
	 *
	 * @param int|string|WP_Error $approved     The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param array               $comment_data Comment data.
	 *
	 * @return int|string|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function pre_comment_approved( $approved, array $comment_data ) {
		if ( null === $this->result || true === $this->result ) {
			return $approved;
		}

		return $this->invalid_captcha_error( $approved, $this->result );
	}

	/**
	 * Invalid captcha error.
	 *
	 * @param int|string|WP_Error $approved      The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param string              $error_message The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 *
	 * @return WP_Error
	 */
	private function invalid_captcha_error( $approved, string $error_message = '' ) {
		$error_message = $error_message ?: __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' );
		$approved      = is_wp_error( $approved ) ? $approved : new WP_Error();

		$approved->add( 'invalid_hcaptcha', $error_message, 400 );

		return $approved;
	}
}
