<?php
/**
 * Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPDiscuz;

use WP_User;

/**
 * Class Form.
 */
class Form {

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wpdiscuz_form_render', [ $this, 'add_hcaptcha' ], 10, 3 );
		add_filter( 'preprocess_comment', [ $this, 'verify' ], 9 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 11 );
	}

	/**
	 * Replaces reCaptcha field by hCaptcha in wpDiscuz form.
	 *
	 * @param string        $output         Output.
	 * @param int|string    $comments_count Comments count.
	 * @param WP_User|false $current_user   Current user.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hcaptcha( $output, $comments_count, $current_user ) {
		if ( ! preg_match( "/id='wpdiscuz-recaptcha-(.+?)'/", $output, $m ) ) {
			return $output;
		}

		$unique_id = $m[1];

		ob_start();
		?>
		<div class="wpd-field-hcaptcha wpdiscuz-item">
			<div class="wpdiscuz-hcaptcha" id='wpdiscuz-hcaptcha-<?php echo esc_attr( $unique_id ); ?>'></div>
			<?php hcap_form_display(); ?>
			<div class="clearfix"></div>
		</div>
		<?php
		$form = ob_get_clean();

		return preg_replace( '/<div class="wpd-field-captcha wpdiscuz-item"(.+?<\/div>){3}/s', $form, $output );
	}

	/**
	 * Verify request.
	 *
	 * @param array $comment_data Comment data.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
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

	/**
	 * Dequeue recaptcha script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_dequeue_script( 'wpdiscuz-google-recaptcha' );
		wp_deregister_script( 'wpdiscuz-google-recaptcha' );
	}
}
