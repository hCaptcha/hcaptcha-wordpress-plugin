/* global HCaptchaJetpackObject */

wp.hooks.addFilter(
	'hcaptcha.formSelector',
	'hcaptcha',
	( formSelector ) => {
		return formSelector + ', div.jetpack-contact-form';
	}
);

document.addEventListener( 'hCaptchaBeforeBindEvents', function() {
	const buttons = [ ...document.querySelectorAll( '.wp-block .jetpack-contact-form .wp-block-jetpack-button' ) ];

	buttons.map( ( button ) => {
		const newElement = document.createElement( 'div' );
		newElement.innerHTML = HCaptchaJetpackObject.hCaptcha;
		button.parentNode.insertBefore( newElement, button );

		return button;
	} );
} );
