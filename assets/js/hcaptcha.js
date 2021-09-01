/* global hcaptcha, hCaptcha */

window.hCaptchaSubmit = function( token ) {
	hCaptcha.form.submit();
};

document.addEventListener( 'DOMContentLoaded', function() {

	/**
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 * @returns string
	 */
	const hCaptchaGetWidgetId = function( el ) {
		return el.getElementsByClassName( 'h-captcha' )[ 0 ].getElementsByTagName( 'iframe' )[ 0 ].dataset.hcaptchaWidgetId;
	};

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	const hCaptchaValidate = function( event ) {
		event.preventDefault();
		hCaptcha.form = event.target.closest( 'form' );
		hcaptcha.execute( hCaptchaGetWidgetId( event.target.parentElement.parentElement ) );
	};

	hCaptcha.forms.map( selector => {
		const submitButton = document.querySelector( selector + ' input[type="submit"]' );

		if ( null === submitButton ) {
			return;
		}

		submitButton.addEventListener( 'click', hCaptchaValidate, false );
	} );
} );
