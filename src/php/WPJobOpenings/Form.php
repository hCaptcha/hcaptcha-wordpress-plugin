<?php
/**
 * The Form class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\WPJobOpenings;

use HCaptcha\Helpers\API;
use HCaptcha\Helpers\EntryData;
use HCaptcha\Helpers\HCaptcha;

/**
 * Class Form.
 */
class Form {
	/**
	 * Script handle.
	 */
	private const HANDLE = 'hcaptcha-wp-job-openings';

	/**
	 * Nonce action.
	 */
	public const ACTION = 'hcaptcha_wp_job_openings';

	/**
	 * Nonce name.
	 */
	public const NONCE = 'hcaptcha_wp_job_openings_nonce';

	/**
	 * Form constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'before_awsm_application_form', [ $this, 'before_application_form' ] );
		add_action( 'after_awsm_application_form', [ $this, 'add_captcha' ] );
		add_action( 'awsm_job_application_submitting', [ $this, 'verify' ] );
	}

	/**
	 * Before application form.
	 *
	 * @param array|mixed $form_attrs Form attributes.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function before_application_form( $form_attrs ): void {
		ob_start();
	}

	/**
	 * Add captcha.
	 *
	 * @param array|mixed $form_attrs Form attributes.
	 *
	 * @return void
	 */
	public function add_captcha( $form_attrs ): void {
		$html = ob_get_clean();

		$this->enqueue_scripts();

		$args = [
			'action' => self::ACTION,
			'name'   => self::NONCE,
			'id'     => $this->get_id( (int) ( $form_attrs['job_id'] ?? 0 ) ),
		];

		$html = preg_replace(
			'#(<div class="awsm-job-form-group">\s*?<input type="submit")#',
			'<div class="awsm-job-form-group">' . HCaptcha::form( $args ) . "</div>\n$1",
			$html
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}

	/**
	 * Enqueue WP Job Openings script.
	 *
	 * @return void
	 */
	private function enqueue_scripts(): void {
		$min = hcap_min_suffix();

		wp_enqueue_script(
			self::HANDLE,
			HCAPTCHA_URL . "/assets/js/hcaptcha-wp-job-openings$min.js",
			[ 'jquery' ],
			HCAPTCHA_VERSION,
			true
		);
	}

	/**
	 * Verify captcha.
	 */
	public function verify(): void {
		global $awsm_response;

		// The application endpoint checks its nonce before this hook.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$job_id      = isset( $_POST['awsm_job_id'] ) && is_scalar( $_POST['awsm_job_id'] ) ? (int) $_POST['awsm_job_id'] : 0;
		$expected_id = $this->get_id( $job_id );
		// The application handler uses this native email field.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email         = isset( $_POST['awsm_applicant_email'] ) && is_scalar( $_POST['awsm_applicant_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['awsm_applicant_email'] ) ) : '';
		$error_message = API::verify_post(
			self::NONCE,
			self::ACTION,
			$expected_id,
			EntryData::with_emails( [], [ $email ] )
		);

		if ( null !== $error_message ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			$awsm_response['error'][] = esc_html( $error_message );
		}
	}

	/**
	 * Get the widget identity for a job.
	 *
	 * @param int $job_id Job ID.
	 *
	 * @return array
	 */
	private function get_id( int $job_id ): array {
		return [
			'source'  => HCaptcha::get_class_source( __CLASS__ ),
			'form_id' => $job_id,
		];
	}
}
