/* global jQuery */

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const nonceName = 'hcaptcha_back_in_stock_notifier_nonce';

		const $node = $( '.cwginstock-subscribe-form' );
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
