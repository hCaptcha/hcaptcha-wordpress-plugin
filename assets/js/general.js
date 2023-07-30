/* global jQuery, HCaptchaGeneralObject */

const general = function( $ ) {
	const msgSelector = '#hcaptcha-message';
	const $message = $( msgSelector );

	function clearMessage() {
		$message.removeClass();
		$message.html( '' );
	}

	function showMessage( message, msgClass ) {
		$message.removeClass();
		$message.addClass( msgClass + ' notice settings-error is-dismissible' );
		$message.html( `<p>${ message }</p>` );
		$message.show();
	}

	function showSuccessMessage( response ) {
		showMessage( response, 'notice-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'notice-error' );
	}

	function hCaptchaReset() {
		$( '#hcaptcha-options .h-captcha' ).empty();
		window.hCaptchaBindEvents();
	}

	$( '#check_config' ).on( 'click', function( event ) {
		event.preventDefault();
		clearMessage();

		const data = {
			action: HCaptchaGeneralObject.action,
			nonce: HCaptchaGeneralObject.nonce,
			'h-captcha-response': $( 'textarea[name="h-captcha-response"]' ).val(),
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		$.post( {
			url: HCaptchaGeneralObject.ajaxUrl,
			data,
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );
					return;
				}

				showSuccessMessage( response.data );
			} )
			.fail( function( response ) {
				showErrorMessage( response.statusText );
			} )
			.always( function() {
				hCaptchaReset();
			} );
	} );
};

window.hCaptchaGeneral = general;

jQuery( document ).ready( general );
