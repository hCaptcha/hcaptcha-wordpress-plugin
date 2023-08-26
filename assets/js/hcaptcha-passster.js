/* global jQuery */

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const data = options.data;

		if ( ! data.startsWith( 'action=validate_input' ) ) {
			return;
		}

		const urlParams = new URLSearchParams( data );
		const area = urlParams.get( 'area' );
		const $node = $( '[data-area=' + area + ']' ).closest( 'form' );
		const nonceName = 'hcaptcha_passster_nonce';
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
