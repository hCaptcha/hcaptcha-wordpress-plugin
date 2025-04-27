<?php
/**
 * NotificationsBase class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin;

use HCaptcha\Settings\EventsPage;
use HCaptcha\Settings\FormsPage;
use HCaptcha\Settings\General;
use HCaptcha\Settings\Integrations;

/**
 * Class NotificationsBase.
 *
 * BAse class for Notifications and What's New.
 */
abstract class NotificationsBase {

	/**
	 * Prepare urls.
	 *
	 * @return array
	 */
	protected function prepare_urls(): array {
		static $urls = [];

		if ( ! $urls ) {
			$utm     = '/?r=wp&utm_source=wordpress&utm_medium=wpplugin&utm_campaign=';
			$utm_sk  = $utm . 'sk';
			$utm_not = $utm . 'not';

			$urls['general']                 = $this->tab_url( General::class );
			$urls['integrations']            = $this->tab_url( Integrations::class );
			$urls['forms']                   = $this->tab_url( FormsPage::class );
			$urls['events']                  = $this->tab_url( EventsPage::class );
			$urls['hcaptcha']                = 'https://www.hcaptcha.com' . $utm_sk;
			$urls['register']                = 'https://www.hcaptcha.com/signup-interstitial' . $utm_sk;
			$urls['pro']                     = 'https://www.hcaptcha.com/pro' . $utm_not;
			$urls['dashboard']               = 'https://dashboard.hcaptcha.com' . $utm_not;
			$urls['post_leadership']         = 'https://www.hcaptcha.com/post/hcaptcha-named-a-technology-leader-in-bot-management' . $utm_not;
			$urls['rate']                    = 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/?filter=5#new-post';
			$urls['search_integrations']     = $urls['integrations'] . '#hcaptcha-integrations-search';
			$urls['enterprise_features']     = 'https://www.hcaptcha.com/#enterprise-features' . $utm_not;
			$urls['statistics']              = $urls['general'] . '#statistics_1';
			$urls['force']                   = $urls['general'] . '#force_1';
			$urls['elementor_edit_form']     = HCAPTCHA_URL . '/assets/images/elementor-edit-form.png';
			$urls['size']                    = $urls['general'] . '#size';
			$urls['passive_mode_example']    = HCAPTCHA_URL . '/assets/images/passive-mode-example.gif';
			$urls['protect_content']         = $urls['general'] . '#protect_content_1';
			$urls['protect_content_example'] = HCAPTCHA_URL . '/assets/images/protect-content-example.gif';
		}

		return $urls;
	}

	/**
	 * Get tab url.
	 *
	 * @param string $classname Tab class name.
	 *
	 * @return string
	 */
	protected function tab_url( string $classname ): string {
		$tab = hcaptcha()->settings()->get_tab( $classname );

		return $tab ? $tab->tab_url( $tab ) : '';
	}
}
