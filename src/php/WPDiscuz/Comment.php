<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;
use WP_User;

/**
 * Class Form.
 */
class Comment extends Base {
	/**
	 * Native comment reCAPTCHA flags.
	 */
	protected const RECAPTCHA_OPTIONS = [ 'showForGuests', 'showForUsers' ];

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		parent::init_hooks();

		add_filter( 'wpdiscuz_form_render', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_filter( 'preprocess_comment', [ $this, 'verify' ], 9 );
		add_action( 'wp_head', [ $this, 'print_inline_styles' ], 20 );
	}

	/**
	 * Add hCaptcha to wpDiscuz form.
	 *
	 * @param string|mixed  $output         Output.
	 * @param int|string    $comments_count Comments count.
	 * @param WP_User|false $current_user   Current user.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, $comments_count, $current_user ): string {
		global $post;

		$args = [
			'id' => $this->get_id( $post->ID ?? 0 ),
		];

		ob_start();

		?>
		<div class="wpd-field-hcaptcha wpdiscuz-item">
			<div class="wpdiscuz-hcaptcha"></div>
			<?php HCaptcha::form_display( $args ); ?>
			<div class="clearfix"></div>
		</div>
		<?php

		$form = ob_get_clean();

		$pattern     = '/<div class="(wpd-form-col-.+?)">(\s*?)<div class="wc-field-submit">/m';
		$replacement = '<div class="$1 wpd-form-col-hcaptcha">$2' . $form . '<div class="wc-field-submit">';

		return preg_replace( $pattern, $replacement, (string) $output );
	}

	/**
	 * Verify request.
	 *
	 * @param array|mixed $comment_data Comment data.
	 *
	 * @return array|mixed
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify( $comment_data ) {
		// Nonce is checked by wpDiscuz.

		// Match the action selected by WordPress admin-ajax, including query actions.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? wp_unslash( $_REQUEST['action'] ) : '';

		if ( ! ( 'wpdAddComment' === $action && wp_doing_ajax() ) ) {
			return $comment_data;
		}

		$wp_discuz = wpDiscuz();

		remove_filter( 'preprocess_comment', [ $wp_discuz, 'validateRecaptcha' ] );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$post_id     = isset( $comment_data['comment_post_ID'] ) && is_scalar( $comment_data['comment_post_ID'] ) ? (int) $comment_data['comment_post_ID'] : 0;
		$expected_id = $this->get_id( $post_id );
		$result      = API::verify(
			EntryData::with_emails(
				[
					'expected_id'        => $expected_id,
					'h-captcha-response' => $hcaptcha_response,
					'data'               => EntryData::from_array( $comment_data, [] ),
				],
				[ $comment_data['comment_author_email'] ?? '' ]
			)
		);

		if ( null === $result ) {
			return $comment_data;
		}

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_die( esc_html( $result ) );
	}

	/**
	 * Get the widget identity for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array
	 */
	private function get_id( int $post_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( static::class ),
			'form_id' => $post_id,
		];
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
	.wpd-form-col-hcaptcha {
		min-width: 302px;
	}

	.wpd-field-hcaptcha .h-captcha {
		margin-left: auto;
	}
';

		HCaptcha::css_display( $css );
	}
}
