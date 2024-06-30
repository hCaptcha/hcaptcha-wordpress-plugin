/* global jQuery */

import { helper } from './hcaptcha-helper.js';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const action = 'brizy_submit_form';
		const params = new URLSearchParams( options.url.split( '?' )[ 1 ] );

		if ( params.get( 'action' ) !== action ) {
			return;
		}

		const data = JSON.parse( options.data.get( 'data' ) );
		const nonceName = 'hcaptcha_brizy_nonce';
		const $node = $( '.brz-form' );
		const hCaptchaData = helper.getHCaptchaData( $node, nonceName );

		// The hcaptcha-widget-id is already in the data object.
		data.push( {
			name: 'h-captcha-response',
			value: hCaptchaData.response,
			required: false,
		} );
		data.push( {
			name: nonceName,
			value: hCaptchaData.nonce,
			required: false,
		} );

		options.data.set( 'data', JSON.stringify( data ) );
	} );
}( jQuery ) );
