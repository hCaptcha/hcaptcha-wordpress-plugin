<?php
/**
 * Origin class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Helpers;

/**
 * Class Origin.
 */
class Origin {

	/**
	 * Origin name.
	 */
	const NAME = 'hcaptcha_origin';

	/**
	 * Transient name where to store form origins.
	 */
	const TRANSIENT = 'hcaptcha_origin';

	/**
	 * Create origin.
	 *
	 * @param string $action Action.
	 * @param string $name   Nonce.
	 *
	 * @return string
	 */
	public static function create( $action = '', $name = '' ) {
		/** This filter is documented in wp-includes/pluggable.php. */
		$nonce_life = apply_filters( 'nonce_life', DAY_IN_SECONDS );
		$transient  = get_transient( self::TRANSIENT );
		$origins    = $transient ?: [];
		$time       = time();
		$new_id     = wp_hash( $time );

		foreach ( $origins as $id => $origin ) {
			if ( $time - $origin['time'] > $nonce_life ) {
				unset( $origins[ $id ] );
			}
		}

		$origins[ $new_id ] = [
			'time'   => $time,
			'action' => $action,
			'nonce'  => $name,
		];

		set_transient( self::TRANSIENT, $origins, $nonce_life );

		return (
			'<input type="hidden" id="' . self::NAME .
			'" name="' . self::NAME .
			'" value="' . $new_id . '">'
		);
	}

	/**
	 * Get origin verification data.
	 *
	 * @param string $id Origin id.
	 *
	 * @return false|array
	 */
	public static function get_verification_data( $id ) {
		$transient = get_transient( self::TRANSIENT );
		$origins   = $transient ?: [];

		return isset( $origins[ $id ] ) ? $origins[ $id ] : false;
	}

	/**
	 * Delete origin form the transient.
	 *
	 * @param string $id Origin id.
	 *
	 * @return void
	 */
	public static function delete( $id ) {
		$transient = get_transient( self::TRANSIENT );
		$origins   = $transient ?: [];

		if ( ! isset( $origins[ $id ] ) ) {
			return;
		}

		unset( $origins[ $id ] );

		set_transient(
			self::TRANSIENT,
			$origins,
			/** This filter is documented in wp-includes/pluggable.php. */
			apply_filters( 'nonce_life', DAY_IN_SECONDS )
		);
	}
}
