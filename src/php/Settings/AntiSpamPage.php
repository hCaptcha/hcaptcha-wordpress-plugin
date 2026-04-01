<?php
/**
 * AntiSpamPage class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Settings;

use HCaptcha\Admin\MaxMindDb;
use HCaptcha\AntiSpam\AntiSpam;
use HCaptcha\AntiSpam\DisposableEmail;
use HCaptcha\Helpers\Request;
use KAGG\Settings\Abstracts\SettingsBase;

/**
 * Class AntiSpamPage
 *
 * Settings page "Anti-Spam".
 */
class AntiSpamPage extends PluginSettingsBase {

	/**
	 * Admin script and style handle.
	 */
	public const HANDLE = 'hcaptcha-anti-spam';

	/**
	 * Script localization object.
	 */
	public const OBJECT = 'HCaptchaAntiSpamObject';

	/**
	 * Check IPs ajax action.
	 */
	public const CHECK_IPS_ACTION = 'hcaptcha-anti-spam-check-ips';

	/**
	 * Bot Detection section id.
	 */
	public const SECTION_BOT_DETECTION = 'bot-detection';

	/**
	 * Access Control section id.
	 */
	public const SECTION_ACCESS_CONTROL = 'access-control';

	/**
	 * Login Protection section id.
	 */
	public const SECTION_LOGIN_PROTECTION = 'login-protection';

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	protected function page_title(): string {
		return __( 'Anti-Spam', 'hcaptcha-for-forms-and-more' );
	}

	/**
	 * Get section title.
	 *
	 * @return string
	 */
	protected function section_title(): string {
		return 'anti-spam';
	}

	/**
	 * Init class hooks.
	 *
	 * @return void
	 */
	protected function init_hooks(): void {
		parent::init_hooks();

		add_action( 'wp_ajax_' . self::CHECK_IPS_ACTION, [ $this, 'check_ips' ] );

		add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'maybe_load_maxmind_db' ], 20, 2 );
		add_filter( 'pre_update_site_option_' . $this->option_name(), [ $this, 'maybe_load_maxmind_db' ], 20, 2 );

		add_filter( 'pre_update_option_' . $this->option_name(), [ $this, 'maybe_toggle_disposable_email' ], 20, 2 );
		add_filter( 'pre_update_site_option_' . $this->option_name(), [ $this, 'maybe_toggle_disposable_email' ], 20, 2 );
	}

	/**
	 * Init form fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'set_min_submit_time'   => [
				'label'   => __( 'Token and Honeypot', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_BOT_DETECTION,
				'options' => [
					'on' => __( 'Set Minimum Time', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Set a minimum amount of time a user must spend on a form before submitting.', 'hcaptcha-for-forms-and-more' ),
			],
			'min_submit_time'       => [
				'label'   => __( 'Minimum Time to Submit the Form, sec', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_BOT_DETECTION,
				'default' => 2,
				'min'     => 1,
				'helper'  => __( 'Set a minimum amount of time a user must spend on a form before submitting.', 'hcaptcha-for-forms-and-more' ),
			],
			'honeypot'              => [
				'type'    => 'checkbox',
				'section' => self::SECTION_BOT_DETECTION,
				'options' => [
					'on' => __( 'Enable Honeypot Field', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Add a honeypot field to submitted forms for early bot prevention.', 'hcaptcha-for-forms-and-more' ),
			],
			'antispam'              => [
				'label'   => __( 'Anti-Spam Check', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_BOT_DETECTION,
				'options' => [
					'on' => __( 'Enable Anti-Spam Check', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Enable anti-spam check of submitted forms.', 'hcaptcha-for-forms-and-more' ),
			],
			'antispam_provider'     => [
				'label'   => __( 'Anti-Spam Provider', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'select',
				'section' => self::SECTION_BOT_DETECTION,
				'options' => AntiSpam::get_supported_providers(),
				'helper'  => __( 'Select anti-spam provider.', 'hcaptcha-for-forms-and-more' ),
			],
			'disposable_email'      => [
				'label'   => __( 'Disposable Emails', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'checkbox',
				'section' => self::SECTION_BOT_DETECTION,
				'options' => [
					'on' => __( 'Block Disposable Emails', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Block form submissions from disposable and temporary email addresses.', 'hcaptcha-for-forms-and-more' ),
			],
			'blacklisted_ips'       => [
				'label'   => __( 'Denylisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_ACCESS_CONTROL,
				'helper'  => __( 'Block form sending from listed IP addresses. Please specify one IP, range, or CIDR per line.', 'hcaptcha-for-forms-and-more' ),
			],
			'whitelisted_ips'       => [
				'label'   => __( 'Allowlisted IPs', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'textarea',
				'section' => self::SECTION_ACCESS_CONTROL,
				'helper'  => __( 'Do not show hCaptcha for listed IP addresses. Please specify one IP, range, or CIDR per line.', 'hcaptcha-for-forms-and-more' ),
			],
			'blacklisted_countries' => [
				'label'   => __( 'Denylisted Countries', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'multiple',
				'options' => [],
				'section' => self::SECTION_ACCESS_CONTROL,
				'helper'  => __( 'Block form sending from selected countries.', 'hcaptcha-for-forms-and-more' ),
			],
			'whitelisted_countries' => [
				'label'   => __( 'Allowlisted Countries', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'multiple',
				'options' => [],
				'section' => self::SECTION_ACCESS_CONTROL,
				'helper'  => __( 'Do not show hCaptcha for users from selected countries.', 'hcaptcha-for-forms-and-more' ),
			],
			'maxmind_key'           => [
				'label'        => __( 'MaxMind License Key', 'hcaptcha-for-forms-and-more' ),
				'type'         => 'password',
				'autocomplete' => 'off',
				'section'      => self::SECTION_ACCESS_CONTROL,
				'helper'       => __( 'Needed to automatically download the GeoLite2 Country database for country allowlist/denylist checks.', 'hcaptcha-for-forms-and-more' ),
			],
			'login_limit'           => [
				'label'   => __( 'Login Attempts Before hCaptcha', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_LOGIN_PROTECTION,
				'default' => 0,
				'min'     => 0,
				'helper'  => __( 'Maximum number of failed login attempts before showing hCaptcha.', 'hcaptcha-for-forms-and-more' ),
			],
			'login_interval'        => [
				'label'   => __( 'Failed Login Attempts Interval, min', 'hcaptcha-for-forms-and-more' ),
				'type'    => 'number',
				'section' => self::SECTION_LOGIN_PROTECTION,
				'default' => 15,
				'min'     => 1,
				'helper'  => __( 'Time interval in minutes when failed login attempts are counted.', 'hcaptcha-for-forms-and-more' ),
			],
			'hide_login_errors'     => [
				'type'    => 'checkbox',
				'section' => self::SECTION_LOGIN_PROTECTION,
				'options' => [
					'on' => __( 'Hide Login Errors', 'hcaptcha-for-forms-and-more' ),
				],
				'helper'  => __( 'Avoid specifying errors like "invalid username" or "invalid password" to limit information exposure to attackers.', 'hcaptcha-for-forms-and-more' ),
			],
		];

		if ( ! AntiSpam::get_supported_providers() ) {
			unset( $this->form_fields['antispam'], $this->form_fields['antispam_provider'] );
		}
	}

	/**
	 * Setup settings fields.
	 */
	public function setup_fields(): void {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		$settings      = hcaptcha()->settings();
		$maxmind_key   = $settings ? $settings->get( 'maxmind_key' ) : '';
		$country_names = $this->get_country_names();

		$this->form_fields['blacklisted_countries']['options'] = $country_names;
		$this->form_fields['whitelisted_countries']['options'] = $country_names;

		if ( '' === $maxmind_key ) {
			$this->form_fields['blacklisted_countries']['disabled'] = true;
			$this->form_fields['whitelisted_countries']['disabled'] = true;
		}

		parent::setup_fields();
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 *
	 * @return void
	 */
	public function section_callback( array $arguments ): void {
		switch ( $arguments['id'] ) {
			case self::SECTION_BOT_DETECTION:
				$this->print_header();

				?>
				<div id="hcaptcha-message"></div>
				<?php

				$this->print_section_header( $arguments['id'], __( 'Bot Detection', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_ACCESS_CONTROL:
				$this->print_section_header( $arguments['id'], __( 'Access Control', 'hcaptcha-for-forms-and-more' ) );
				break;
			case self::SECTION_LOGIN_PROTECTION:
				$this->print_section_header( $arguments['id'], __( 'Login Protection', 'hcaptcha-for-forms-and-more' ) );
				break;
			default:
				break;
		}
	}

	/**
	 * Enqueue class scripts.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {
		$settings                     = hcaptcha()->settings();
		$maxmind_key                  = $settings ? $settings->get( 'maxmind_key' ) : '';
		$countries_search_placeholder = $maxmind_key
			? __( 'Search countries...', 'hcaptcha-for-forms-and-more' )
			: __( 'Set MaxMind License Key first', 'hcaptcha-for-forms-and-more' );

		$choices_handle   = self::HANDLE . '-choices';
		$countries_handle = self::HANDLE . '-countries';

		wp_enqueue_script(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/anti-spam$this->min_suffix.js",
			[ 'jquery', static::PREFIX . '-' . SettingsBase::HANDLE ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_enqueue_script(
			$choices_handle,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/choices/choices.min.js',
			[],
			'v11.2.0',
			true
		);

		wp_enqueue_script(
			$countries_handle,
			constant( 'HCAPTCHA_URL' ) . "/assets/js/anti-spam-countries$this->min_suffix.js",
			[ 'jquery', $choices_handle ],
			constant( 'HCAPTCHA_VERSION' ),
			true
		);

		wp_localize_script(
			$countries_handle,
			'HCaptchaAntiSpamCountriesObject',
			[
				'searchPlaceholder' => $countries_search_placeholder,
				'searchAriaLabel'   => __( 'Search countries', 'hcaptcha-for-forms-and-more' ),
			]
		);

		/* translators: 1: Provider name. */
		$provider_error = __( '%1$s anti-spam provider is not configured.', 'hcaptcha-for-forms-and-more' );

		wp_localize_script(
			self::HANDLE,
			self::OBJECT,
			[
				'ajaxUrl'                         => admin_url( 'admin-ajax.php' ),
				'checkIPsAction'                  => self::CHECK_IPS_ACTION,
				'checkIPsNonce'                   => wp_create_nonce( self::CHECK_IPS_ACTION ),
				'configuredAntiSpamProviders'     => AntiSpam::get_configured_providers(),
				'configuredAntiSpamProviderError' => $provider_error,
			]
		);

		wp_enqueue_style(
			$choices_handle,
			constant( 'HCAPTCHA_URL' ) . '/assets/lib/choices/choices.min.css',
			[],
			'v11.2.0'
		);

		wp_enqueue_style(
			self::HANDLE,
			constant( 'HCAPTCHA_URL' ) . "/assets/css/anti-spam$this->min_suffix.css",
			[ static::PREFIX . '-' . SettingsBase::HANDLE, $choices_handle ],
			constant( 'HCAPTCHA_VERSION' )
		);
	}

	/**
	 * Ajax action to check IPs.
	 *
	 * @return void
	 */
	public function check_ips(): void {
		$this->run_checks( self::CHECK_IPS_ACTION );

		// Nonce is checked by check_ajax_referer() in run_checks().
		$ips     = Request::filter_input( INPUT_POST, 'ips' );
		$ips_arr = explode( ' ', $ips );

		foreach ( $ips_arr as $key => $ip ) {
			$ip = trim( $ip );

			if ( ! $this->is_valid_ip_or_range( $ip ) ) {
				wp_send_json_error(
					esc_html__( 'Invalid IP or CIDR range:', 'hcaptcha-for-forms-and-more' ) .
					' ' . esc_html( $ip )
				);

				// For testing purposes.
				return;
			}

			$ips_arr[ $key ] = $ip;
		}

		wp_send_json_success();
	}

	/**
	 * Handle MaxMind DB key activation/deactivation.
	 *
	 * @param mixed $value     New option value.
	 * @param mixed $old_value Old option value.
	 *
	 * @return mixed
	 */
	public function maybe_load_maxmind_db( $value, $old_value ) {
		$maxmind_key     = $value['maxmind_key'] ?? '';
		$old_maxmind_key = $old_value['maxmind_key'] ?? '';

		if ( $maxmind_key === $old_maxmind_key ) {
			return $value;
		}

		$maxmind_db_obj = hcaptcha()->get( MaxMindDb::class );

		if ( $maxmind_key ) {
			$maxmind_db_obj->activate( $maxmind_key );
		} else {
			$maxmind_db_obj->deactivate();
		}

		return $value;
	}

	/**
	 * Handle disposable email toggle activation/deactivation.
	 *
	 * @param mixed $value     New option value.
	 * @param mixed $old_value Old option value.
	 *
	 * @return mixed
	 */
	public function maybe_toggle_disposable_email( $value, $old_value ) {
		$disposable_email     = $value['disposable_email'][0] ?? '';
		$old_disposable_email = $old_value['disposable_email'][0] ?? '';

		if ( $disposable_email === $old_disposable_email ) {
			return $value;
		}

		$disposable_email_obj = hcaptcha()->get( DisposableEmail::class );

		if ( 'on' === $disposable_email ) {
			$disposable_email_obj->activate();
		} else {
			$disposable_email_obj->deactivate();
		}

		return $value;
	}

	/**
	 * Print section header.
	 *
	 * @param string $id    Section id.
	 * @param string $title Section title.
	 *
	 * @return void
	 */
	private function print_section_header( string $id, string $title ): void {
		$open  = $this->get_section_open_status( $id );
		$class = $open ? '' : ' closed';

		?>
		<h3 class="togglable hcaptcha-section-<?php echo esc_attr( $id ); ?><?php echo esc_attr( $class ); ?>">
			<span class="hcaptcha-section-header-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<span class="hcaptcha-section-header-toggle">
			</span>
		</h3>
		<?php
	}

	/**
	 * Validate IP or CIDR range.
	 *
	 * @param string $input Input to validate.
	 *
	 * @return bool
	 */
	private function is_valid_ip_or_range( string $input ): bool {
		$input = trim( $input );

		// Check for a single IP (IPv4 or IPv6).
		if ( filter_var( $input, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		// Check CIDR-range.
		if ( strpos( $input, '/' ) !== false ) {
			[ $ip, $prefix ] = explode( '/', $input, 2 );

			// Check that the prefix is correct.
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) && filter_var( $prefix, FILTER_VALIDATE_INT ) !== false ) {
				$prefix = (int) $prefix;

				if (
					( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && $prefix >= 0 && $prefix <= 32 ) ||
					( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && $prefix >= 0 && $prefix <= 128 )
				) {
					return true;
				}
			}

			return false;
		}

		// Check the range of 'IP-IP' type.
		if ( strpos( $input, '-' ) !== false ) {
			[ $ip_start, $ip_end ] = explode( '-', $input, 2 );

			$ip_start = trim( $ip_start );
			$ip_end   = trim( $ip_end );

			if ( filter_var( $ip_start, FILTER_VALIDATE_IP ) && filter_var( $ip_end, FILTER_VALIDATE_IP ) ) {
				// Make sure that both IPs are of the same type (IPv4/IPv6).
				if (
					( filter_var( $ip_start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $ip_end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) ||
					( filter_var( $ip_start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $ip_end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) )
				) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get country names.
	 *
	 * @return array
	 */
	private function get_country_names(): array {
		static $country_names;

		if ( $country_names ) {
			return $country_names;
		}

		// Country codes are according ISO 3166-1 alpha-2.
		$country_names = [
			'AD' => __( 'Andorra', 'hcaptcha-for-forms-and-more' ),
			'AE' => __( 'United Arab Emirates', 'hcaptcha-for-forms-and-more' ),
			'AF' => __( 'Afghanistan', 'hcaptcha-for-forms-and-more' ),
			'AG' => __( 'Antigua and Barbuda', 'hcaptcha-for-forms-and-more' ),
			'AI' => __( 'Anguilla', 'hcaptcha-for-forms-and-more' ),
			'AL' => __( 'Albania', 'hcaptcha-for-forms-and-more' ),
			'AM' => __( 'Armenia', 'hcaptcha-for-forms-and-more' ),
			'AO' => __( 'Angola', 'hcaptcha-for-forms-and-more' ),
			'AR' => __( 'Argentina', 'hcaptcha-for-forms-and-more' ),
			'AS' => __( 'American Samoa', 'hcaptcha-for-forms-and-more' ),
			'AT' => __( 'Austria', 'hcaptcha-for-forms-and-more' ),
			'AU' => __( 'Australia', 'hcaptcha-for-forms-and-more' ),
			'AW' => __( 'Aruba', 'hcaptcha-for-forms-and-more' ),
			'AX' => __( 'Aland Islands', 'hcaptcha-for-forms-and-more' ),
			'AZ' => __( 'Azerbaijan', 'hcaptcha-for-forms-and-more' ),
			'BA' => __( 'Bosnia and Herzegovina', 'hcaptcha-for-forms-and-more' ),
			'BB' => __( 'Barbados', 'hcaptcha-for-forms-and-more' ),
			'BD' => __( 'Bangladesh', 'hcaptcha-for-forms-and-more' ),
			'BE' => __( 'Belgium', 'hcaptcha-for-forms-and-more' ),
			'BF' => __( 'Burkina Faso', 'hcaptcha-for-forms-and-more' ),
			'BG' => __( 'Bulgaria', 'hcaptcha-for-forms-and-more' ),
			'BH' => __( 'Bahrain', 'hcaptcha-for-forms-and-more' ),
			'BI' => __( 'Burundi', 'hcaptcha-for-forms-and-more' ),
			'BJ' => __( 'Benin', 'hcaptcha-for-forms-and-more' ),
			'BL' => __( 'St. Barthelemy', 'hcaptcha-for-forms-and-more' ),
			'BM' => __( 'Bermuda', 'hcaptcha-for-forms-and-more' ),
			'BN' => __( 'Brunei', 'hcaptcha-for-forms-and-more' ),
			'BO' => __( 'Bolivia', 'hcaptcha-for-forms-and-more' ),
			'BQ' => __( 'Bonaire, Sint Eustatius and Saba', 'hcaptcha-for-forms-and-more' ),
			'BR' => __( 'Brazil', 'hcaptcha-for-forms-and-more' ),
			'BS' => __( 'Bahamas', 'hcaptcha-for-forms-and-more' ),
			'BT' => __( 'Bhutan', 'hcaptcha-for-forms-and-more' ),
			'BW' => __( 'Botswana', 'hcaptcha-for-forms-and-more' ),
			'BY' => __( 'Belarus', 'hcaptcha-for-forms-and-more' ),
			'BZ' => __( 'Belize', 'hcaptcha-for-forms-and-more' ),
			'CA' => __( 'Canada', 'hcaptcha-for-forms-and-more' ),
			'CC' => __( 'Cocos (Keeling) Islands', 'hcaptcha-for-forms-and-more' ),
			'CD' => __( 'Congo (DRC)', 'hcaptcha-for-forms-and-more' ),
			'CF' => __( 'Central African Republic', 'hcaptcha-for-forms-and-more' ),
			'CG' => __( 'Congo', 'hcaptcha-for-forms-and-more' ),
			'CH' => __( 'Switzerland', 'hcaptcha-for-forms-and-more' ),
			'CI' => __( 'Cote d\'Ivoire', 'hcaptcha-for-forms-and-more' ),
			'CK' => __( 'Cook Islands', 'hcaptcha-for-forms-and-more' ),
			'CL' => __( 'Chile', 'hcaptcha-for-forms-and-more' ),
			'CM' => __( 'Cameroon', 'hcaptcha-for-forms-and-more' ),
			'CN' => __( 'China', 'hcaptcha-for-forms-and-more' ),
			'CO' => __( 'Colombia', 'hcaptcha-for-forms-and-more' ),
			'CR' => __( 'Costa Rica', 'hcaptcha-for-forms-and-more' ),
			'CU' => __( 'Cuba', 'hcaptcha-for-forms-and-more' ),
			'CV' => __( 'Cabo Verde', 'hcaptcha-for-forms-and-more' ),
			'CW' => __( 'Curacao', 'hcaptcha-for-forms-and-more' ),
			'CX' => __( 'Christmas Island', 'hcaptcha-for-forms-and-more' ),
			'CY' => __( 'Cyprus', 'hcaptcha-for-forms-and-more' ),
			'CZ' => __( 'Czechia', 'hcaptcha-for-forms-and-more' ),
			'DE' => __( 'Germany', 'hcaptcha-for-forms-and-more' ),
			'DJ' => __( 'Djibouti', 'hcaptcha-for-forms-and-more' ),
			'DK' => __( 'Denmark', 'hcaptcha-for-forms-and-more' ),
			'DM' => __( 'Dominica', 'hcaptcha-for-forms-and-more' ),
			'DO' => __( 'Dominican Republic', 'hcaptcha-for-forms-and-more' ),
			'DZ' => __( 'Algeria', 'hcaptcha-for-forms-and-more' ),
			'EC' => __( 'Ecuador', 'hcaptcha-for-forms-and-more' ),
			'EE' => __( 'Estonia', 'hcaptcha-for-forms-and-more' ),
			'EG' => __( 'Egypt', 'hcaptcha-for-forms-and-more' ),
			'ER' => __( 'Eritrea', 'hcaptcha-for-forms-and-more' ),
			'ES' => __( 'Spain', 'hcaptcha-for-forms-and-more' ),
			'ET' => __( 'Ethiopia', 'hcaptcha-for-forms-and-more' ),
			'FI' => __( 'Finland', 'hcaptcha-for-forms-and-more' ),
			'FJ' => __( 'Fiji', 'hcaptcha-for-forms-and-more' ),
			'FK' => __( 'Falkland Islands', 'hcaptcha-for-forms-and-more' ),
			'FM' => __( 'Micronesia', 'hcaptcha-for-forms-and-more' ),
			'FO' => __( 'Faroe Islands', 'hcaptcha-for-forms-and-more' ),
			'FR' => __( 'France', 'hcaptcha-for-forms-and-more' ),
			'GA' => __( 'Gabon', 'hcaptcha-for-forms-and-more' ),
			'GB' => __( 'United Kingdom', 'hcaptcha-for-forms-and-more' ),
			'GD' => __( 'Grenada', 'hcaptcha-for-forms-and-more' ),
			'GE' => __( 'Georgia', 'hcaptcha-for-forms-and-more' ),
			'GF' => __( 'French Guiana', 'hcaptcha-for-forms-and-more' ),
			'GG' => __( 'Guernsey', 'hcaptcha-for-forms-and-more' ),
			'GH' => __( 'Ghana', 'hcaptcha-for-forms-and-more' ),
			'GI' => __( 'Gibraltar', 'hcaptcha-for-forms-and-more' ),
			'GL' => __( 'Greenland', 'hcaptcha-for-forms-and-more' ),
			'GM' => __( 'Gambia', 'hcaptcha-for-forms-and-more' ),
			'GN' => __( 'Guinea', 'hcaptcha-for-forms-and-more' ),
			'GP' => __( 'Guadeloupe', 'hcaptcha-for-forms-and-more' ),
			'GQ' => __( 'Equatorial Guinea', 'hcaptcha-for-forms-and-more' ),
			'GR' => __( 'Greece', 'hcaptcha-for-forms-and-more' ),
			'GT' => __( 'Guatemala', 'hcaptcha-for-forms-and-more' ),
			'GU' => __( 'Guam', 'hcaptcha-for-forms-and-more' ),
			'GW' => __( 'Guinea-Bissau', 'hcaptcha-for-forms-and-more' ),
			'GY' => __( 'Guyana', 'hcaptcha-for-forms-and-more' ),
			'HK' => __( 'Hong Kong SAR', 'hcaptcha-for-forms-and-more' ),
			'HN' => __( 'Honduras', 'hcaptcha-for-forms-and-more' ),
			'HR' => __( 'Croatia', 'hcaptcha-for-forms-and-more' ),
			'HT' => __( 'Haiti', 'hcaptcha-for-forms-and-more' ),
			'HU' => __( 'Hungary', 'hcaptcha-for-forms-and-more' ),
			'ID' => __( 'Indonesia', 'hcaptcha-for-forms-and-more' ),
			'IE' => __( 'Ireland', 'hcaptcha-for-forms-and-more' ),
			'IL' => __( 'Israel', 'hcaptcha-for-forms-and-more' ),
			'IM' => __( 'Isle of Man', 'hcaptcha-for-forms-and-more' ),
			'IN' => __( 'India', 'hcaptcha-for-forms-and-more' ),
			'IO' => __( 'British Indian Ocean Territory', 'hcaptcha-for-forms-and-more' ),
			'IQ' => __( 'Iraq', 'hcaptcha-for-forms-and-more' ),
			'IR' => __( 'Iran', 'hcaptcha-for-forms-and-more' ),
			'IS' => __( 'Iceland', 'hcaptcha-for-forms-and-more' ),
			'IT' => __( 'Italy', 'hcaptcha-for-forms-and-more' ),
			'JE' => __( 'Jersey', 'hcaptcha-for-forms-and-more' ),
			'JM' => __( 'Jamaica', 'hcaptcha-for-forms-and-more' ),
			'JO' => __( 'Jordan', 'hcaptcha-for-forms-and-more' ),
			'JP' => __( 'Japan', 'hcaptcha-for-forms-and-more' ),
			'KE' => __( 'Kenya', 'hcaptcha-for-forms-and-more' ),
			'KG' => __( 'Kyrgyzstan', 'hcaptcha-for-forms-and-more' ),
			'KH' => __( 'Cambodia', 'hcaptcha-for-forms-and-more' ),
			'KI' => __( 'Kiribati', 'hcaptcha-for-forms-and-more' ),
			'KM' => __( 'Comoros', 'hcaptcha-for-forms-and-more' ),
			'KN' => __( 'St. Kitts and Nevis', 'hcaptcha-for-forms-and-more' ),
			'KP' => __( 'North Korea', 'hcaptcha-for-forms-and-more' ),
			'KR' => __( 'Korea', 'hcaptcha-for-forms-and-more' ),
			'KW' => __( 'Kuwait', 'hcaptcha-for-forms-and-more' ),
			'KY' => __( 'Cayman Islands', 'hcaptcha-for-forms-and-more' ),
			'KZ' => __( 'Kazakhstan', 'hcaptcha-for-forms-and-more' ),
			'LA' => __( 'Laos', 'hcaptcha-for-forms-and-more' ),
			'LB' => __( 'Lebanon', 'hcaptcha-for-forms-and-more' ),
			'LC' => __( 'St. Lucia', 'hcaptcha-for-forms-and-more' ),
			'LI' => __( 'Liechtenstein', 'hcaptcha-for-forms-and-more' ),
			'LK' => __( 'Sri Lanka', 'hcaptcha-for-forms-and-more' ),
			'LR' => __( 'Liberia', 'hcaptcha-for-forms-and-more' ),
			'LS' => __( 'Lesotho', 'hcaptcha-for-forms-and-more' ),
			'LT' => __( 'Lithuania', 'hcaptcha-for-forms-and-more' ),
			'LU' => __( 'Luxembourg', 'hcaptcha-for-forms-and-more' ),
			'LV' => __( 'Latvia', 'hcaptcha-for-forms-and-more' ),
			'LY' => __( 'Libya', 'hcaptcha-for-forms-and-more' ),
			'MA' => __( 'Morocco', 'hcaptcha-for-forms-and-more' ),
			'MC' => __( 'Monaco', 'hcaptcha-for-forms-and-more' ),
			'MD' => __( 'Moldova', 'hcaptcha-for-forms-and-more' ),
			'ME' => __( 'Montenegro', 'hcaptcha-for-forms-and-more' ),
			'MF' => __( 'St. Martin', 'hcaptcha-for-forms-and-more' ),
			'MG' => __( 'Madagascar', 'hcaptcha-for-forms-and-more' ),
			'MH' => __( 'Marshall Islands', 'hcaptcha-for-forms-and-more' ),
			'MK' => __( 'North Macedonia', 'hcaptcha-for-forms-and-more' ),
			'ML' => __( 'Mali', 'hcaptcha-for-forms-and-more' ),
			'MM' => __( 'Myanmar', 'hcaptcha-for-forms-and-more' ),
			'MN' => __( 'Mongolia', 'hcaptcha-for-forms-and-more' ),
			'MO' => __( 'Macao SAR', 'hcaptcha-for-forms-and-more' ),
			'MP' => __( 'Northern Mariana Islands', 'hcaptcha-for-forms-and-more' ),
			'MQ' => __( 'Martinique', 'hcaptcha-for-forms-and-more' ),
			'MR' => __( 'Mauritania', 'hcaptcha-for-forms-and-more' ),
			'MS' => __( 'Montserrat', 'hcaptcha-for-forms-and-more' ),
			'MT' => __( 'Malta', 'hcaptcha-for-forms-and-more' ),
			'MU' => __( 'Mauritius', 'hcaptcha-for-forms-and-more' ),
			'MV' => __( 'Maldives', 'hcaptcha-for-forms-and-more' ),
			'MW' => __( 'Malawi', 'hcaptcha-for-forms-and-more' ),
			'MX' => __( 'Mexico', 'hcaptcha-for-forms-and-more' ),
			'MY' => __( 'Malaysia', 'hcaptcha-for-forms-and-more' ),
			'MZ' => __( 'Mozambique', 'hcaptcha-for-forms-and-more' ),
			'NA' => __( 'Namibia', 'hcaptcha-for-forms-and-more' ),
			'NC' => __( 'New Caledonia', 'hcaptcha-for-forms-and-more' ),
			'NE' => __( 'Niger', 'hcaptcha-for-forms-and-more' ),
			'NF' => __( 'Norfolk Island', 'hcaptcha-for-forms-and-more' ),
			'NG' => __( 'Nigeria', 'hcaptcha-for-forms-and-more' ),
			'NI' => __( 'Nicaragua', 'hcaptcha-for-forms-and-more' ),
			'NL' => __( 'Netherlands', 'hcaptcha-for-forms-and-more' ),
			'NO' => __( 'Norway', 'hcaptcha-for-forms-and-more' ),
			'NP' => __( 'Nepal', 'hcaptcha-for-forms-and-more' ),
			'NR' => __( 'Nauru', 'hcaptcha-for-forms-and-more' ),
			'NU' => __( 'Niue', 'hcaptcha-for-forms-and-more' ),
			'NZ' => __( 'New Zealand', 'hcaptcha-for-forms-and-more' ),
			'OM' => __( 'Oman', 'hcaptcha-for-forms-and-more' ),
			'PA' => __( 'Panama', 'hcaptcha-for-forms-and-more' ),
			'PE' => __( 'Peru', 'hcaptcha-for-forms-and-more' ),
			'PF' => __( 'French Polynesia', 'hcaptcha-for-forms-and-more' ),
			'PG' => __( 'Papua New Guinea', 'hcaptcha-for-forms-and-more' ),
			'PH' => __( 'Philippines', 'hcaptcha-for-forms-and-more' ),
			'PK' => __( 'Pakistan', 'hcaptcha-for-forms-and-more' ),
			'PL' => __( 'Poland', 'hcaptcha-for-forms-and-more' ),
			'PM' => __( 'St. Pierre and Miquelon', 'hcaptcha-for-forms-and-more' ),
			'PN' => __( 'Pitcairn Islands', 'hcaptcha-for-forms-and-more' ),
			'PR' => __( 'Puerto Rico', 'hcaptcha-for-forms-and-more' ),
			'PS' => __( 'Palestinian Authority', 'hcaptcha-for-forms-and-more' ),
			'PT' => __( 'Portugal', 'hcaptcha-for-forms-and-more' ),
			'PW' => __( 'Palau', 'hcaptcha-for-forms-and-more' ),
			'PY' => __( 'Paraguay', 'hcaptcha-for-forms-and-more' ),
			'QA' => __( 'Qatar', 'hcaptcha-for-forms-and-more' ),
			'RE' => __( 'Reunion', 'hcaptcha-for-forms-and-more' ),
			'RO' => __( 'Romania', 'hcaptcha-for-forms-and-more' ),
			'RS' => __( 'Serbia', 'hcaptcha-for-forms-and-more' ),
			'RU' => __( 'Russia', 'hcaptcha-for-forms-and-more' ),
			'RW' => __( 'Rwanda', 'hcaptcha-for-forms-and-more' ),
			'SA' => __( 'Saudi Arabia', 'hcaptcha-for-forms-and-more' ),
			'SB' => __( 'Solomon Islands', 'hcaptcha-for-forms-and-more' ),
			'SC' => __( 'Seychelles', 'hcaptcha-for-forms-and-more' ),
			'SD' => __( 'Sudan', 'hcaptcha-for-forms-and-more' ),
			'SE' => __( 'Sweden', 'hcaptcha-for-forms-and-more' ),
			'SG' => __( 'Singapore', 'hcaptcha-for-forms-and-more' ),
			'SH' => __( 'St Helena, Ascension, Tristan da Cunha', 'hcaptcha-for-forms-and-more' ),
			'SI' => __( 'Slovenia', 'hcaptcha-for-forms-and-more' ),
			'SJ' => __( 'Svalbard and Jan Mayen', 'hcaptcha-for-forms-and-more' ),
			'SK' => __( 'Slovakia', 'hcaptcha-for-forms-and-more' ),
			'SL' => __( 'Sierra Leone', 'hcaptcha-for-forms-and-more' ),
			'SM' => __( 'San Marino', 'hcaptcha-for-forms-and-more' ),
			'SN' => __( 'Senegal', 'hcaptcha-for-forms-and-more' ),
			'SO' => __( 'Somalia', 'hcaptcha-for-forms-and-more' ),
			'SR' => __( 'Suriname', 'hcaptcha-for-forms-and-more' ),
			'SS' => __( 'South Sudan', 'hcaptcha-for-forms-and-more' ),
			'ST' => __( 'Sao Tome and Principe', 'hcaptcha-for-forms-and-more' ),
			'SV' => __( 'El Salvador', 'hcaptcha-for-forms-and-more' ),
			'SX' => __( 'Sint Maarten', 'hcaptcha-for-forms-and-more' ),
			'SY' => __( 'Syria', 'hcaptcha-for-forms-and-more' ),
			'SZ' => __( 'Eswatini', 'hcaptcha-for-forms-and-more' ),
			'TC' => __( 'Turks and Caicos Islands', 'hcaptcha-for-forms-and-more' ),
			'TD' => __( 'Chad', 'hcaptcha-for-forms-and-more' ),
			'TG' => __( 'Togo', 'hcaptcha-for-forms-and-more' ),
			'TH' => __( 'Thailand', 'hcaptcha-for-forms-and-more' ),
			'TJ' => __( 'Tajikistan', 'hcaptcha-for-forms-and-more' ),
			'TK' => __( 'Tokelau', 'hcaptcha-for-forms-and-more' ),
			'TL' => __( 'Timor-Leste', 'hcaptcha-for-forms-and-more' ),
			'TM' => __( 'Turkmenistan', 'hcaptcha-for-forms-and-more' ),
			'TN' => __( 'Tunisia', 'hcaptcha-for-forms-and-more' ),
			'TO' => __( 'Tonga', 'hcaptcha-for-forms-and-more' ),
			'TR' => __( 'Turkiye', 'hcaptcha-for-forms-and-more' ),
			'TT' => __( 'Trinidad and Tobago', 'hcaptcha-for-forms-and-more' ),
			'TV' => __( 'Tuvalu', 'hcaptcha-for-forms-and-more' ),
			'TW' => __( 'Taiwan', 'hcaptcha-for-forms-and-more' ),
			'TZ' => __( 'Tanzania', 'hcaptcha-for-forms-and-more' ),
			'UA' => __( 'Ukraine', 'hcaptcha-for-forms-and-more' ),
			'UG' => __( 'Uganda', 'hcaptcha-for-forms-and-more' ),
			'UM' => __( 'U.S. Outlying Islands', 'hcaptcha-for-forms-and-more' ),
			'US' => __( 'United States', 'hcaptcha-for-forms-and-more' ),
			'UY' => __( 'Uruguay', 'hcaptcha-for-forms-and-more' ),
			'UZ' => __( 'Uzbekistan', 'hcaptcha-for-forms-and-more' ),
			'VA' => __( 'Vatican City', 'hcaptcha-for-forms-and-more' ),
			'VC' => __( 'St. Vincent and Grenadines', 'hcaptcha-for-forms-and-more' ),
			'VE' => __( 'Venezuela', 'hcaptcha-for-forms-and-more' ),
			'VG' => __( 'British Virgin Islands', 'hcaptcha-for-forms-and-more' ),
			'VI' => __( 'U.S. Virgin Islands', 'hcaptcha-for-forms-and-more' ),
			'VN' => __( 'Vietnam', 'hcaptcha-for-forms-and-more' ),
			'VU' => __( 'Vanuatu', 'hcaptcha-for-forms-and-more' ),
			'WF' => __( 'Wallis and Futuna', 'hcaptcha-for-forms-and-more' ),
			'WS' => __( 'Samoa', 'hcaptcha-for-forms-and-more' ),
			'XK' => __( 'Kosovo', 'hcaptcha-for-forms-and-more' ),
			'YE' => __( 'Yemen', 'hcaptcha-for-forms-and-more' ),
			'YT' => __( 'Mayotte', 'hcaptcha-for-forms-and-more' ),
			'ZA' => __( 'South Africa', 'hcaptcha-for-forms-and-more' ),
			'ZM' => __( 'Zambia', 'hcaptcha-for-forms-and-more' ),
			'ZW' => __( 'Zimbabwe', 'hcaptcha-for-forms-and-more' ),
		];

		asort( $country_names );

		return $country_names;
	}
}
