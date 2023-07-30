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
		$message.addClass( msgClass );
		$message.html( `<p>${ message }</p>` );
		$message.show();
	}

	function showSuccessMessage( response ) {
		showMessage( response, 'hcaptcha-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'hcaptcha-error' );
	}

	$( '#check_config' ).on( 'click', function( event ) {
		event.preventDefault();
		clearMessage();

		const data = {
			action: HCaptchaGeneralObject.action,
			nonce: HCaptchaGeneralObject.nonce,
		};

		// noinspection JSVoidFunctionReturnValueUsed
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
			} );
	} );
};

window.hCaptchaGeneral = general;

jQuery( document ).ready( general );
