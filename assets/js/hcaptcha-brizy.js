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
		for ( const [ name, value ] of Object.entries( hCaptchaData ) ) {
			if ( name === 'hcaptcha-widget-id' ) {
				continue;
			}

			const existingField = data.find( ( field ) => field.name === name );

			if ( existingField ) {
				existingField.value = value;
				continue;
			}

			data.push( {
				name,
				value,
				required: false,
			} );
		}

		options.data.set( 'data', JSON.stringify( data ) );
	} );

	$( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
		const params = new URLSearchParams( settings.url.split( '?' )[ 1 ] );

		if ( params.get( 'action' ) !== 'brizy_submit_form' ) {
			return;
		}

		window.hCaptchaBindEvents();

		if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
			window.hCaptchaFST.getToken();
		}
	} );
}( jQuery ) );
