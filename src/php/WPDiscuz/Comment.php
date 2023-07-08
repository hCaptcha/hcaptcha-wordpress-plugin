<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

use HCaptcha\Helpers\HCaptcha;
use WP_User;

/**
 * Class Form.
 */
class Comment extends Base {

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		parent::init_hooks();

		add_action( 'wpdiscuz_form_render', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_filter( 'preprocess_comment', [ $this, 'verify' ], 9 );
	}

	/**
	 * Add hCaptcha to wpDiscuz form.
	 *
	 * @param string        $output         Output.
	 * @param int|string    $comments_count Comments count.
	 * @param WP_User|false $current_user   Current user.
	 *
	 * @return string
	 */
	public function add_hcaptcha( $output, $comments_count, $current_user ) {
		global $post;

		$args = [
			'id' => [
				'source'  => HCaptcha::get_class_source( static::class ),
				'form_id' => $post ? $post->ID : 0,
			],
		];

		ob_start();
		?>
		<div class="wpd-field-hcaptcha wpdiscuz-item">
			<div class="wpdiscuz-hcaptcha" id='wpdiscuz-hcaptcha'></div>
			<?php HCaptcha::form_display( $args ); ?>
			<div class="clearfix"></div>
		</div>
		<?php
		$form = ob_get_clean();

		$search = '<div class="wc-field-submit">';

		return str_replace( $search, $form . $search, $output );
	}

	/**
	 * Verify request.
	 *
	 * @param array $comment_data Comment data.
	 *
	 * @return array
	 * @noinspection PhpUndefinedFunctionInspection
	 * @noinspection ForgottenDebugOutputInspection
	 */
	public function verify( $comment_data ) {
		$wp_discuz = wpDiscuz();

		remove_filter( 'preprocess_comment', [ $wp_discuz, 'validateRecaptcha' ], 10 );

		// Nonce is checked by wpDiscuz.

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hcaptcha_response = isset( $_POST['h-captcha-response'] ) ?
			filter_var( wp_unslash( $_POST['h-captcha-response'] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ) :
			'';

		$result = hcaptcha_request_verify( $hcaptcha_response );

		if ( null === $result ) {
			return $comment_data;
		}

		unset( $_POST['h-captcha-response'], $_POST['g-recaptcha-response'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_die( esc_html( $result ) );
	}
}
