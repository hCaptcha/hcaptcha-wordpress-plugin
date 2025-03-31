/* global hCaptchaBindEvents */

document.addEventListener( 'DOMContentLoaded', function() {
	[ ...document.querySelectorAll( '.wpcf7' ) ].map( ( form ) => {
		form.addEventListener( 'wpcf7invalid', hCaptchaBindEvents, false );
		form.addEventListener( 'wpcf7spam', hCaptchaBindEvents, false );
		form.addEventListener( 'wpcf7mailsent', hCaptchaBindEvents, false );
		form.addEventListener( 'wpcf7mailfailed', hCaptchaBindEvents, false );
		form.addEventListener( 'wpcf7submit', hCaptchaBindEvents, false );

		return form;
	} );
} );
