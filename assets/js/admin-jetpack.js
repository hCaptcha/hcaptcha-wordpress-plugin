/* global HCaptchaJetpackObject */

/**
 * @param HCaptchaJetpackObject.hCaptcha
 * @param window.hCaptchaJetpack
 */

const jetpack = window.hCaptchaJetpack || ( function( document, window, wp ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		// Init app.
		init() {
			app.addFormSelectorFilter();
			document.addEventListener( 'hCaptchaBeforeBindEvents', app.beforeBindEvents );
		},

		// Add filter to include Jetpack contact forms in the form selector.
		addFormSelectorFilter() {
			wp.hooks.addFilter(
				'hcaptcha.formSelector',
				'hcaptcha',
				( formSelector ) => {
					return formSelector + ', div.jetpack-contact-form';
				}
			);
		},

		// Handle hCaptchaBeforeBindEvents event.
		beforeBindEvents() {
			const buttons = [ ...document.querySelectorAll( '.wp-block .jetpack-contact-form .wp-block-jetpack-button' ) ];

			buttons.map( ( button ) => {
				const newElement = document.createElement( 'div' );
				newElement.innerHTML = HCaptchaJetpackObject.hCaptcha;
				button.parentNode.insertBefore( newElement, button );

				return button;
			} );
		},
	};

	return app;
}( document, window, wp ) );

window.hCaptchaJetpack = jetpack;

jetpack.init();
