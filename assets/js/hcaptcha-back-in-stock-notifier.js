/* global jQuery */

import { helper } from './hcaptcha-helper.js';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		helper.addHCaptchaData(
			options,
			'cwginstock_product_subscribe',
			'hcaptcha_back_in_stock_notifier_nonce',
			$( '.cwginstock-subscribe-form' )
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
