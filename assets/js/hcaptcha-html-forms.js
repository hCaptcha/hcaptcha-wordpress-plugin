document.addEventListener( 'hf-submitted', function( event ) {
	const form = event.target;

	if ( ! form.matches( 'form.hf-form' ) ) {
		return;
	}

	if ( 'function' === typeof window.hCaptchaReset ) {
		window.hCaptchaReset( form );
	}

	form.querySelectorAll( '[name="h-captcha-response"], [name="g-recaptcha-response"]' )
		.forEach( ( response ) => {
			response.value = '';
		} );

	if ( 'function' === typeof window.hCaptchaFST?.getToken ) {
		window.hCaptchaFST.getToken();
	}
} );
