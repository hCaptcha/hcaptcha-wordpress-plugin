/* global jQuery */

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const data = options.data;
		let nonceName = '';

		if ( data.startsWith( 'action=mailpoet' ) ) {
			nonceName = 'hcaptcha_mailpoet_nonce';
		}

		if ( ! nonceName ) {
			return;
		}

		const urlParams = new URLSearchParams( data );
		const formId = urlParams.get( 'data[form_id]' );
		const $form = $( 'input[name="data[form_id]"][value=' + formId + ']' ).parent( 'form' );
		let response = $form.find( '[name="h-captcha-response"]' ).val();
		response = response ? response : '';
		let id = $form.find( '[name="hcaptcha-widget-id"]' ).val();
		id = id ? id : '';
		let nonce = $form.find( '[name="' + nonceName + '"]' ).val();
		nonce = nonce ? nonce : '';
		options.data +=
			'&h-captcha-response=' + response + '&hcaptcha-widget-id=' + id + '&' + nonceName + '=' + nonce;
	} );
}( jQuery ) );
