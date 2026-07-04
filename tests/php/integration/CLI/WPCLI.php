<?php
/**
 * WP_CLI test double.
 *
 * @package HCaptcha\Tests
 */

if ( ! class_exists( 'WP_CLI', false ) ) {
	// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps -- Mirrors the WP-CLI class name.
	/**
	 * Minimal WP_CLI test double.
	 */
	class WP_CLI {
		/**
		 * Registered commands.
		 *
		 * @var array
		 */
		public static array $commands = [];

		/**
		 * Success messages.
		 *
		 * @var string[]
		 */
		public static array $success_messages = [];

		/**
		 * Warning messages.
		 *
		 * @var string[]
		 */
		public static array $warning_messages = [];

		/**
		 * Error messages.
		 *
		 * @var string[]
		 */
		public static array $error_messages = [];

		/**
		 * Reset recorded state.
		 *
		 * @return void
		 */
		public static function reset(): void {
			self::$commands         = [];
			self::$success_messages = [];
			self::$warning_messages = [];
			self::$error_messages   = [];
		}

		/**
		 * Register a command.
		 *
		 * @param string $name    Command name.
		 * @param mixed  $command Command handler.
		 *
		 * @return void
		 */
		public static function add_command( string $name, $command ): void {
			self::$commands[ $name ] = $command;
		}

		/**
		 * Record a success message.
		 *
		 * @param string $message Message.
		 *
		 * @return void
		 */
		public static function success( string $message ): void {
			self::$success_messages[] = $message;
		}

		/**
		 * Record a warning message.
		 *
		 * @param string $message Message.
		 *
		 * @return void
		 */
		public static function warning( string $message ): void {
			self::$warning_messages[] = $message;
		}

		/**
		 * Record an error message and stop execution.
		 *
		 * @param string $message Message.
		 *
		 * @return void
		 * @throws \HCaptcha\Tests\Integration\CLI\WPCLIExitException Test double exception.
		 */
		public static function error( string $message ): void {
			self::$error_messages[] = $message;

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test double keeps the exact CLI error message.
			throw new \HCaptcha\Tests\Integration\CLI\WPCLIExitException( $message );
		}
	}
	// phpcs:enable Squiz.Classes.ValidClassName.NotCamelCaps
}
