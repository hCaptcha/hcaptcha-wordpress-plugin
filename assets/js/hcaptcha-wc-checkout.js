/* global jQuery, hCaptchaReset */

const wc = function( $ ) {
	function reset() {
		hCaptchaReset( document.querySelector( 'form.woocommerce-checkout' ) );
	}

	$( document.body ).on( 'checkout_error', function() {
		reset();
	} );

	$( document.body ).on( 'updated_checkout', function() {
		window.hCaptchaBindEvents();
		reset();
	} );
};

window.hCaptchaWC = wc;

jQuery( document ).ready( wc );
