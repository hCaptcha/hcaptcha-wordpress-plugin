<?php
/**
 * Comment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Error;

/**
 * Class Comment
 */
class Comment {

	/**
	 * Nonce action.
	 */
	private const ACTION = 'hcaptcha_comment';

	/**
	 * Nonce name.
	 */
	private const NONCE = 'hcaptcha_comment_nonce';

	/**
	 * Add captcha to the form.
	 *
	 * @var bool
	 */
	protected $active;

	/**
	 * Verification result.
	 *
	 * @var string|null
	 */
	protected $result;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->active = hcaptcha()->settings()->is( 'wp_status', 'comment' );

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'comment_form_submit_field', [ $this, 'add_captcha' ], 10, 2 );
		add_filter( 'preprocess_comment', [ $this, 'verify' ], - PHP_INT_MAX );
		add_filter( 'pre_comment_approved', [ $this, 'pre_comment_approved' ], 20, 2 );
	}

	/**
	 * Add captcha.
	 *
	 * @param string|mixed $submit_field HTML markup for the 'submit' field.
	 * @param array        $comment_args Arguments passed to comment_form().
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_captcha( $submit_field, array $comment_args ): string {
		$submit_field = (string) $submit_field;
		$post_id      = 0;

		if (
			preg_match(
				"<input type='hidden' name='comment_post_ID' value='(.+)?' id='comment_post_ID' />",
				$submit_field,
				$m
			)
		) {
			$post_id = $m[1];
		}

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => [
				'source'  => HCaptcha::get_class_source( __CLASS__ ),
				'form_id' => $post_id,
			],
		];

		if (
			! $this->active ||
			false !== strpos( $submit_field, 'et_pb_submit' )
		) {
			// If not active or Divi comment form, just add a signature.
			$args['protect'] = false;
		}

		$form = HCaptcha::form( $args );

		return $form . $submit_field;
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
			return $comment_data;
		}

		// Override poor IP detection by WP Core and make sure that IP is the same in the 'comments' table and in the 'hcaptcha_events' table.
		$comment_data['comment_author_IP'] = hcap_get_user_ip();

		$this->result = hcaptcha_get_verify_message_html( self::NONCE, self::ACTION );

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
	 * @param int|string|WP_Error $approved    The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param array               $commentdata Comment data.
	 *
	 * @return int|string|WP_Error
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function pre_comment_approved( $approved, array $commentdata ) {
		if ( null === $this->result ) {
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
