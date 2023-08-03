/* global jQuery */

const hCaptchaWPForo = function( $ ) {
	$( '.wpforo-section .add_wpftopic:not(.not_reg_user)' ).click( function() {
		window.hCaptchaBindEvents();
	} );
};

window.hCaptchaWPForo = hCaptchaWPForo;

jQuery( document ).ready( hCaptchaWPForo );
