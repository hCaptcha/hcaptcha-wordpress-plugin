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
		$this->name = __( 'hCaptcha for WP', 'hcaptcha-for-forms-and-more' );

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
				<strong><?php esc_html_e( 'Privacy First:', 'hcaptcha-for-forms-and-more' ); ?></strong>
				<?php esc_html_e( 'hCaptcha is designed to protect user privacy. It doesn’t retain or sell personal data, unlike platforms that', 'hcaptcha-for-forms-and-more' ); ?>
				<strong>g</strong>ather, <strong>o</strong>wn, and m<strong>o</strong>netize <strong>gl</strong>obal
				b<strong>e</strong>havior.
			</p>
			<p>
				<?php esc_html_e( 'hCaptcha is designed to comply with privacy laws in every country, including GDPR, LGPD, CCPA, and more.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'For example, hCaptcha has been certified under ISO 27001 and 27701 and is enrolled in the EU-US, UK-US, and Swiss-US Data Privacy Framework for GDPR compliance.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
					/* translators: 1: certifications link, 2: GDPR link. */
						__( 'Details are available at %1$s and %2$s.', 'hcaptcha-for-forms-and-more' ),
						'<a href="https://www.hcaptcha.com/certifications" target="_blank">www.hcaptcha.com/certifications</a>',
						'<a href="https://www.hcaptcha.com/gdpr" target="_blank">www.hcaptcha.com/gdpr</a>'
					)
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'With the default configuration, this plugin does not:', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'track users by stealth;', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li><?php esc_html_e( 'write any user’s personal data to the database;', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li><?php esc_html_e( 'send any data to external servers;', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li><?php esc_html_e( 'use cookies.', 'hcaptcha-for-forms-and-more' ); ?></li>
			</ul>
			<p>
				<?php esc_html_e( 'Once you activate this plugin, the hCaptcha-answering user’s IP address and browser data may be sent to the hCaptcha service on pages where you have activated hCaptcha protection. However, hCaptcha is designed to minimize data used, process it very close to the user, and rapidly discard it after analysis.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
					/* translators: 1: hCaptcha privacy link */
						__( 'For more details, please see the hCaptcha privacy policy at %1$s.', 'hcaptcha-for-forms-and-more' ),
						'<a href="https://hcaptcha.com/privacy" target="_blank">hCaptcha.com</a>'
					)
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'If you enable the optional plugin-local statistics feature, the following additional data will be recorded to your database:', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'counts of challenge verifications per form', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li>
					<?php

					'<strong>' .
					esc_html_e( 'only if you enable this optional feature:', 'hcaptcha-for-forms-and-more' ) .
					'</strong> ' .
					esc_html_e( 'the IP address challenged on each form', 'hcaptcha-for-forms-and-more' );

					?>
				</li>
				<li>
					<?php

					'<strong>' .
					esc_html_e( 'only if you enable this optional feature:', 'hcaptcha-for-forms-and-more' ) .
					'</strong> ' .
					esc_html_e( 'the User Agent challenged on each form', 'hcaptcha-for-forms-and-more' );

					?>
				</li>
			</ul>
			<p>
				<?php esc_html_e( 'You can collect data anonymously but still distinguish sources. The hashed IP address and User Agent will be saved.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'We recommend leaving IP and User Agent recording off, which will make these statistics fully anonymous.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'If this feature is enabled, anonymized statistics on your plugin configuration, not including any end user data, will also be sent to us. This lets us see which modules and features are being used and prioritize development for them accordingly.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>

			<h2>
				<?php esc_html_e( 'What Data is Collected', 'hcaptcha-for-forms-and-more' ); ?>
			</h2>

			<p>
				<?php esc_html_e( 'When hCaptcha is active, count of challenges, plugin non-private settings and active integration names may be sent to hCaptcha\'s servers for analysis. However:', 'hcaptcha-for-forms-and-more' ); ?>
			</p>
			<ul>
				<li><?php esc_html_e( 'hCaptcha does not track users covertly;', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li><?php esc_html_e( 'it does not store personal data in the site’s database;', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li><?php esc_html_e( 'it does not use third-party cookies;', 'hcaptcha-for-forms-and-more' ); ?></li>
				<li><?php esc_html_e( 'it does not send data to external servers unless necessary.', 'hcaptcha-for-forms-and-more' ); ?></li>
			</ul>
			<p>
				<?php esc_html_e( 'The service is built with privacy as a priority and complies with GDPR, CCPA, LGPD, and other international standards. hCaptcha is ISO 27001 and 27701 certified and participates in the EU-US, UK-US, and Swiss-US Data Privacy Framework programs.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>

			<h2>
				<?php esc_html_e( 'Cookies', 'hcaptcha-for-forms-and-more' ); ?>
			</h2>

			<p>
				<?php esc_html_e( 'Plugin does not use cookies.', 'hcaptcha-for-forms-and-more' ); ?>
			</p>

			<h2>
				<?php esc_html_e( 'Who We Share Data With', 'hcaptcha-for-forms-and-more' ); ?>
			</h2>

			<p>
				<?php
				echo wp_kses_post(
					sprintf(
					/* translators: 1: hCaptcha privacy link */
						__( 'Data collected through hCaptcha is processed exclusively by the hCaptcha service. You can review their privacy policy at %1$s.', 'hcaptcha-for-forms-and-more' ),
						'<a href="https://hcaptcha.com/privacy" target="_blank">hCaptcha.com</a>'
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
