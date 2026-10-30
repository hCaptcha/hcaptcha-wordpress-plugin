<?php
/**
 * CommentBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Abstracts;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Helpers\Request;
use WP_Error;
use WP_REST_Request;

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
	protected bool $hcaptcha_shown = false;

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
	protected int $form_id = 0;

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
	 * Verify a native REST comment before WordPress processes approval.
	 *
	 * @param array|WP_Error  $prepared_comment Prepared comment or an earlier error.
	 * @param WP_REST_Request $request          REST request.
	 *
	 * @return array|WP_Error
	 */
	public function verify_rest_comment( $prepared_comment, WP_REST_Request $request ) {
		if (
			! $request->is_method( 'POST' ) ||
			'/wp/v2/comments' !== strtolower( $request->get_route() ) ||
			'note' === $request['type'] ||
			is_wp_error( $prepared_comment )
		) {
			return $prepared_comment;
		}

		$comment_data                      = wp_parse_args(
			(array) $prepared_comment,
			[
				'comment_author'       => '',
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_content'      => '',
				'comment_author_IP'    => '',
				'comment_agent'        => '',
			]
		);
		$this->form_id                     = (int) ( $comment_data['comment_post_ID'] ?? 0 );
		$comment_data['comment_author_IP'] = hcap_get_user_ip();
		$entry                             = $this->get_entry( $comment_data );
		$entry['post_data']                = $request->get_params();
		$entry['nonce_name']               = static::NONCE;
		$entry['nonce_action']             = static::ACTION;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Restore unrelated POST data after checking this REST request.
		$original_post = $_POST;

		// REST proof must come from this request, not from an outer form or batch request.
		$_POST = [];

		try {
			$this->result = API::verify( $entry );
		} finally {
			$_POST = $original_post;
		}

		if ( null !== $this->result ) {
			return new WP_Error( 'invalid_hcaptcha', (string) $this->result, [ 'status' => 400 ] );
		}

		return $prepared_comment;
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

		if ( ! Request::is_frontend() && ! $this->is_native_comment_submission() ) {
			// Skip other transports; WPDiscuz handles its own AJAX comment submissions.
			$this->result = null;

			return $comment_data;
		}

		$this->form_id = (int) ( $comment_data['comment_post_ID'] ?? 0 );
		$this->result  = HCaptcha::check_signature( static::class, $this->form_id );

		if ( true === $this->result ) {
			return $comment_data;
		}

		if ( false === $this->result ) {
			$this->result = hcap_get_error_messages()['bad-signature'];

			return $comment_data;
		}

		// Override poor IP detection by WP Core and make sure that IP is the same in the 'comments' table and in the 'hcaptcha_events' table.
		$comment_data['comment_author_IP'] = hcap_get_user_ip();

		$this->result = API::verify( $this->get_entry( $comment_data ) );

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );

		if ( null !== $this->result ) {
			// Block Akismet activity to reduce its API calls.
			remove_action( 'preprocess_comment', [ 'Akismet', 'auto_check_comment' ], 1 );
		}

		return $comment_data;
	}

	/**
	 * Whether WordPress itself is handling the submitted comment.
	 *
	 * WooCommerce may define DOING_AJAX from a query argument even when the
	 * request is still handled by the core comment submission script.
	 *
	 * @return bool
	 */
	private function is_native_comment_submission(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server-provided filesystem path must retain its separators.
		$script = $_SERVER['SCRIPT_FILENAME'] ?? '';

		if ( ! is_string( $script ) || '' === $script ) {
			return false;
		}

		$core_script    = realpath( ABSPATH . 'wp-comments-post.php' );
		$request_script = realpath( $script );

		return false !== $core_script && $core_script === $request_script;
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

		return $this->invalid_captcha_error( $approved, (string) $this->result );
	}

	/**
	 * Invalid captcha error.
	 *
	 * @param int|string|WP_Error $approved      The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 * @param string              $error_message The approval status. Accepts 1, 0, 'spam', 'trash', or WP_Error.
	 *
	 * @return WP_Error
	 */
	private function invalid_captcha_error( $approved, string $error_message = '' ): WP_Error {
		$error_message = $error_message ?: __( 'Invalid Captcha', 'hcaptcha-for-forms-and-more' );
		$approved      = is_wp_error( $approved ) ? $approved : new WP_Error();

		$approved->add( 'invalid_hcaptcha', $error_message, 400 );

		return $approved;
	}

	/**
	 * Get entry.
	 *
	 * @param array $comment_data Comment data.
	 *
	 * @return array
	 */
	private function get_entry( array $comment_data ): array {
		$post_id = $comment_data['comment_post_ID'];
		$post    = get_post( $post_id );

		$data            = EntryData::from_post( [] );
		$data['name']    = $comment_data['comment_author'];
		$data['email']   = $comment_data['comment_author_email'];
		$data['url']     = $comment_data['comment_author_url'];
		$data['content'] = $comment_data['comment_content'];
		$data['ip']      = $comment_data['comment_author_IP'];
		$data['agent']   = $comment_data['comment_agent'];

		return [
			'form_date_gmt' => $post->post_modified_gmt ?? null,
			'expected_id'   => $this->get_expected_id( (int) $post_id ),
			'data'          => $data,
		];
	}

	/**
	 * Get expected hCaptcha widget id.
	 *
	 * @param int $form_id Form id.
	 *
	 * @return array
	 */
	protected function get_expected_id( int $form_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => $form_id,
		];
	}
}
