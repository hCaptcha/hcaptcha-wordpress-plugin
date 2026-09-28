/* global jQuery */

import { helper } from './hcaptcha-helper.js';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		helper.addHCaptchaData(
			options,
			'cwginstock_product_subscribe',
			'hcaptcha_back_in_stock_notifier_nonce',
			$( '.cwginstock-subscribe-form' ),
		);
	} );
}( jQuery ) );

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'cwg_trigger_popup_ajax' ) {
		return;
	}

	const input = document.querySelector( 'input[name="cwg-product-id"][value="' + params.get( 'product_id' ) + '"]' );

	if ( ! input ) {
		return;
	}

	window.hCaptchaBindEvents();
} );

jQuery( document ).on( 'cwginstock_success_ajax cwginstock_error_ajax', function( event, data ) {
	if ( ! data?.product_id ) {
		return;
	}

	const forms = document.querySelectorAll( '.cwginstock-subscribe-form' );
	const form = Array.from( forms ).find( ( currentForm ) => {
		const productId = currentForm.querySelector( '.cwg-product-id' )?.value;

		return String( productId ) === String( data.product_id );
	} );

	if ( ! form ) {
		return;
	}

	if ( typeof window.hCaptchaReset === 'function' ) {
		window.hCaptchaReset( form );
	}

	form.querySelectorAll( '[name="h-captcha-response"], [name="g-recaptcha-response"]' ).forEach( ( response ) => {
		response.value = '';
	} );

	if ( window.hCaptchaFST ) {
		window.hCaptchaFST.getToken();
	}
} );
