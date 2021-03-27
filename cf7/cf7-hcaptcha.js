document.addEventListener( 'DOMContentLoaded', function() {
	var hCaptchaCF7 = document.querySelector( '.wpcf7' );

	var hCaptchaResetCF7 = function( event ) {
		hcaptcha.reset();
	}

	hCaptchaCF7.addEventListener( 'wpcf7invalid', hCaptchaResetCF7, false );
	hCaptchaCF7.addEventListener( 'wpcf7spam', hCaptchaResetCF7, false );
	hCaptchaCF7.addEventListener( 'wpcf7mailsent', hCaptchaResetCF7, false );
	hCaptchaCF7.addEventListener( 'wpcf7mailfailed', hCaptchaResetCF7, false );
	hCaptchaCF7.addEventListener( 'wpcf7submit', hCaptchaResetCF7, false );
} );
