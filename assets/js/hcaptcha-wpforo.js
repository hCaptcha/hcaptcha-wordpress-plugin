/* global jQuery */

const wpforo = function( $ ) {
	$( '.wpforo-section .add_wpftopic:not(.not_reg_user)' ).click( function() {
		window.hCaptchaBindEvents();
	} );
};

window.hCaptchaWPForo = wpforo;

jQuery( document ).ready( wpforo );
