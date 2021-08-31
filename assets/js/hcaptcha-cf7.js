/* global hcaptcha, hCaptchaCF7 */

window.hCaptchaSubmitCF7 = function( token ) {
	let event = document.createEvent( 'HTMLEvents' );
	event.initEvent( 'submit', true, true );
	event.eventName = 'submit';

	hCaptchaCF7.form.dispatchEvent( event );
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
	 * Reset hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	const hCaptchaResetCF7 = function( event ) {
		hcaptcha.reset( hCaptchaGetWidgetId( event.target ) );
	};

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	const hCaptchaValidateCF7 = function( event ) {
		event.preventDefault();
		hCaptchaCF7.form = event.target.closest( 'form' );
		hcaptcha.execute( hCaptchaGetWidgetId( event.target.parentElement.parentElement ) );
	};

	[ ...document.querySelectorAll( '.wpcf7' ) ].map( form => {
		form.addEventListener( 'wpcf7invalid', hCaptchaResetCF7, false );
		form.addEventListener( 'wpcf7spam', hCaptchaResetCF7, false );
		form.addEventListener( 'wpcf7mailsent', hCaptchaResetCF7, false );
		form.addEventListener( 'wpcf7mailfailed', hCaptchaResetCF7, false );
		form.addEventListener( 'wpcf7submit', hCaptchaResetCF7, false );

		if ( 'invisible' === hCaptchaCF7.size ) {
			form.querySelector( 'input[type="submit"]' ).addEventListener( 'click', hCaptchaValidateCF7, false );
		}
	} );
} );
