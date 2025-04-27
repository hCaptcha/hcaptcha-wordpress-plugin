<?php
/**
 * Privacy class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

/**
 * Class Privacy.
 *
 * Show a privacy message in the admin.
 */
class Privacy {

	/**
	 * Privacy tab name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name = hcaptcha()->settings()->get_plugin_name();

		$this->init();
	}

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_init', [ $this, 'add_privacy_message' ] );
	}

	/**
	 * Adds the privacy message on WC privacy page.
	 */
	public function add_privacy_message(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = $this->get_privacy_message();

		if ( ! $content ) {
			return;
		}

		wp_add_privacy_policy_content( $this->name, $content );
	}

	/**
	 * Add privacy policy content for the privacy policy page.
	 */
	public function get_privacy_message(): string {
		ob_start();

		?>
		<div class="wp-suggested-text">
			<p class="privacy-policy-tutorial">
				<?php esc_html_e( 'We use the hCaptcha service to protect forms on our website from spam and automated bots. hCaptcha provides a verification mechanism to distinguish between human users and bots.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					__(
						'We use the hCaptcha security service (hereinafter "hCaptcha") on our website.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'This service is provided by Intuition Machines, Inc., a Delaware US Corporation ("IMI").',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'hCaptcha is used to check whether user actions on our online service (such as submitting a login or contact form) meet our security requirements.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'To do this, hCaptcha analyzes the behavior of the website or mobile app visitor based on various characteristics.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'This analysis starts automatically as soon as the website or mobile app visitor enters a part of the website or app with hCaptcha enabled.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'For the analysis, hCaptcha evaluates various information (e.g. IP address, how long the visitor has been on the website or app, or mouse movements made by the user).',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'The data collected during the analysis will be forwarded to IMI.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'hCaptcha analysis in the "invisible mode" may take place completely in the background.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'Website or app visitors are not advised that such an analysis is taking place if the user is not shown a challenge.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'Data processing is based on Art. 6(1)(b) of the GDPR: the processing of personal data is necessary for the performance of a contract to which the website visitor is party (for example, the website terms)',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'or in order to take steps at the request of the website visitor prior to entering into a contract.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'Our online service (including our website, mobile apps, and any other apps or other forms of access offered by us) needs to ensure that it is interacting with a human, not a bot,',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'and that activities performed by the user are not related to fraud or abuse.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'In addition, processing may also be based on Art. 6(1)(f) of the GDPR: our online service has a legitimate interest in protecting the service from abusive automated crawling, spam,',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'and other forms of abuse that can harm our service or other users of our service.',
						'hcaptcha-for-forms-and-more'
					) . ' ' . __(
						'IMI acts as a "data processor" acting on behalf of its customers as defined under the GDPR, and a "service provider" for the purposes of the California Consumer Privacy Act (CCPA).',
						'hcaptcha-for-forms-and-more'
					) . ' ' . sprintf(
					/* translators: 1: privacy link, 2: terms link. */
						__(
							'For more information about hCaptcha’s privacy policy and terms of use, please visit the following links: %1$s and %2$s.',
							'hcaptcha-for-forms-and-more'
						),
						'<a href="https://www.hcaptcha.com/privacy" target="_blank">https://www.hcaptcha.com/privacy</a>',
						'<a href="https://www.hcaptcha.com/terms" target="_blank">https://www.hcaptcha.com/terms</a>'
					)
				);
				?>
			</p>
		</div>
		<?php

		$content = ob_get_clean();

		return (string) apply_filters( 'hcap_privacy_policy_content', $content );
	}
}
