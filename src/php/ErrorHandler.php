<?php
/**
 * ErrorHandler class file.
 *
 * @package hcaptcha-wp
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUndefinedClassInspection */

namespace HCaptcha;

use QM_Collectors;

/**
 * ErrorHandler class.
 */
class ErrorHandler {

	/**
	 * Initialize the class.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Suppress the _load_textdomain_just_in_time() notices related the plugin for WP 6.7+.
		if ( version_compare( $GLOBALS['wp_version'], '6.7', '>=' ) ) {
			add_action( 'doing_it_wrong_run', [ $this, 'action_doing_it_wrong_run' ], 0, 3 );
			add_action( 'doing_it_wrong_run', [ $this, 'action_doing_it_wrong_run' ], 20, 3 );
			add_filter( 'doing_it_wrong_trigger_error', [ $this, 'filter_doing_it_wrong_trigger_error' ], 10, 4 );
		}
	}

	/**
	 * Action for _doing_it_wrong() calls.
	 *
	 * @param string|mixed $function_name The function that was called.
	 * @param string|mixed $message       A message explaining what has been done incorrectly.
	 * @param string|mixed $version       The version of WordPress where the message was added.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function action_doing_it_wrong_run( $function_name, $message, $version ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		global $wp_filter;

		$function_name = (string) $function_name;
		$message       = (string) $message;

		if ( ! class_exists( 'QM_Collectors' ) || ! $this->is_just_in_time_for_plugin_domain( $function_name, $message ) ) {
			return;
		}

		$qm_collector_doing_it_wrong = QM_Collectors::get( 'doing_it_wrong' );
		$current_priority            = $wp_filter['doing_it_wrong_run']->current_priority();

		if ( null === $qm_collector_doing_it_wrong || false === $current_priority ) {
			return;
		}

		switch ( $current_priority ) {
			case 0:
				remove_action( 'doing_it_wrong_run', [ $qm_collector_doing_it_wrong, 'action_doing_it_wrong_run' ] );
				break;

			case 20:
				add_action( 'doing_it_wrong_run', [ $qm_collector_doing_it_wrong, 'action_doing_it_wrong_run' ], 10, 3 );
				break;

			default:
				break;
		}
	}

	/**
	 * Filter for _doing_it_wrong() calls.
	 *
	 * @param bool|mixed   $trigger       Whether to trigger the error for _doing_it_wrong() calls. Default true.
	 * @param string|mixed $function_name The function that was called.
	 * @param string|mixed $message       A message explaining what has been done incorrectly.
	 * @param string|mixed $version       The version of WordPress where the message was added.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function filter_doing_it_wrong_trigger_error( $trigger, $function_name, $message, $version ): bool {

		$trigger       = (bool) $trigger;
		$function_name = (string) $function_name;
		$message       = (string) $message;

		return $this->is_just_in_time_for_plugin_domain( $function_name, $message ) ? false : $trigger;
	}

	/**
	 * Whether it is the just_in_time_error for plugin-related domain.
	 *
	 * @param string $function_name Function name.
	 * @param string $message       Message.
	 *
	 * @return bool
	 */
	protected function is_just_in_time_for_plugin_domain( string $function_name, string $message ): bool {
		return '_load_textdomain_just_in_time' === $function_name && false !== strpos( $message, '<code>hcaptcha-for-forms-and-more</code>' );
	}
}
