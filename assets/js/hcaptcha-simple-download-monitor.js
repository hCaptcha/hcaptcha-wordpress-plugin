/* global jQuery */

import { helper } from './hcaptcha-helper.js';

/**
 * Add hCaptcha fields to a download URL.
 *
 * @param {jQuery} $item Download item.
 * @param {string} href  Download URL.
 *
 * @return {string} Download URL with hCaptcha fields.
 */
export function getDownloadUrl( $item, href ) {
	const location = new URL( href, window.location.href );
	const nonceName = 'hcaptcha_simple_download_monitor_nonce';
	const hCaptchaData = helper.getHCaptchaData( $item, nonceName );

	for ( const [ name, value ] of Object.entries( hCaptchaData ) ) {
		location.searchParams.set( name, value );
	}

	return location.toString();
}

/**
 * Refresh form tokens after returning from a failed download request.
 *
 * @param {PageTransitionEvent} event Page transition event.
 */
export function pageshowHandler( event ) {
	if ( ! event.persisted ) {
		return;
	}

	document.querySelectorAll( 'div.sdm_download_item' ).forEach( ( item ) => {
		if ( typeof window.hCaptchaReset === 'function' ) {
			window.hCaptchaReset( item );
		}

		item.querySelectorAll( 'textarea[name="h-captcha-response"], textarea[name="g-recaptcha-response"]' )
			.forEach( ( response ) => {
				response.value = '';
			} );
	} );

	if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
		window.hCaptchaFST.getToken();
	}
}

( function( $ ) {
	window.addEventListener( 'pageshow', pageshowHandler );

	$( 'a.sdm_download' ).on( 'click', function( e ) {
		e.preventDefault();

		const $item = $( e.currentTarget ).closest( 'div.sdm_download_item' );

		window.location.href = getDownloadUrl( $item, e.currentTarget.href );
	} );
}( jQuery ) );
