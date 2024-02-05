/* global jQuery */

const wc = function( $ ) {
	$( document.body ).on( 'checkout_error', () => window.hCaptchaBindEvents() );
	$( document.body ).on( 'updated_checkout', () => window.hCaptchaBindEvents() );
};

window.hCaptchaWC = wc;

jQuery( document ).ready( wc );
