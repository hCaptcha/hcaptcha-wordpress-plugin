/* global jQuery */

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const data = options.data;
		let nonceName = '';

		if ( data.startsWith( 'action=et_pb_submit_subscribe_form' ) ) {
			nonceName = 'hcaptcha_divi_email_optin_nonce';
		}

		if ( ! nonceName ) {
			return;
		}

		const $node = $( '.et_pb_newsletter_form form' );
		let response = $node.find( '[name="h-captcha-response"]' ).val();
		response = response ? response : '';
		let id = $node.find( '[name="hcaptcha-widget-id"]' ).val();
		id = id ? id : '';
		let nonce = $node.find( '[name="' + nonceName + '"]' ).val();
		nonce = nonce ? nonce : '';
		options.data +=
			'&h-captcha-response=' + response + '&hcaptcha-widget-id=' + id + '&' + nonceName + '=' + nonce;
	} );
}( jQuery ) );
