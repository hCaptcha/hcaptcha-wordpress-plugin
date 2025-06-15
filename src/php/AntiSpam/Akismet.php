<?php
/**
 * Akismet class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha\AntiSpam;

use Akismet as AkismetPlugin;
use HCaptcha\Helpers\Request;

/**
 * Class Akismet.
 */
class Akismet {
	/**
	 * Is the Akismet plugin activated?
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return defined( 'AKISMET_VERSION' );
	}

	/**
	 * Has the Akismet plugin been configured with a valid API key?
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		// Akismet will only allow an API key to be saved if it is a valid key.
		// We can assume that if there is an API key saved, it is valid.
		return self::is_activated() && ! empty( AkismetPlugin::get_api_key() );
	}

	/**
	 * Verify entry.
	 *
	 * @param array $entry Entry data.
	 *
	 * @return null|string Null on success, error message on failure.
	 */
	public function verify( array $entry ): ?string {
		$args = $this->get_request_args( $entry );

		// Check data with Akismet.
		$response = AkismetPlugin::http_post( AkismetPlugin::build_query( $args ), 'comment-check' );
		$status   = $response[1] ?? '';

		if ( trim( $status ) !== 'false' ) {
			// Spam found - do not save/submit data.
			return hcap_get_error_messages()['spam'];
		}

		return null;
	}

	/**
	 * Get the request arguments to be sent to Akismet.
	 *
	 * @param array $entry Entry data.
	 *
	 * @return array
	 */
	private function get_request_args( array $entry ): array { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
		$user_agent = Request::filter_input( INPUT_SERVER, 'HTTP_USER_AGENT' );
		$referer    = wp_get_referer();
		$user_id    = get_current_user_id();

		$content = '';

		foreach ( $entry['data'] as $key => $value ) {
			// Collect all values into one content line.

			$content .= $key . ': ' . $value . "\n";
		}

		$args = [
			'blog'                      => get_option( 'home' ),
			'user_ip'                   => hcap_get_user_ip() ?: null,
			'user_agent'                => $user_agent,
			'referrer'                  => $referer ?: '',
			'permalink'                 => Request::current_url(),
			'comment_type'              => 'contact-form',
			'comment_author'            => $entry['name'],
			'comment_author_email'      => $entry['email'],
			'comment_content'           => trim( $content ),
			'comment_date_gmt'          => gmdate( 'Y-m-d H:i:s' ),
			'comment_post_modified_gmt' => $entry['form_date_gmt'],
			'blog_lang'                 => get_locale(),
			'blog_charset'              => get_bloginfo( 'charset' ),
			'user_role'                 => (string) AkismetPlugin::get_user_roles( $user_id ),
		];

		return array_filter(
			$args,
			static function ( $value ) {
				return null !== $value;
			}
		);
	}
}
