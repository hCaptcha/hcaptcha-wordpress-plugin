/* global HCaptchaGiveWPObject, hCaptchaBindEvents */

/**
 * @param HCaptchaGiveWPObject.hcaptchaForm
 */

const { fetch: originalFetch } = window;

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.parentElement.classList.contains( 'givewp-layouts' ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

// Intercept GiveWP form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	const body = config.body;
	const widgetName = 'hcaptcha-widget-id';
	const inputName = 'h-captcha-response';
	const params = new URLSearchParams( resource.split( '?' )[ 1 ] ?? '' );

	if ( params.get( 'givewp-route' ) === 'donate' ) {
		const giveWPForm = document.getElementById( 'root-givewp-donation-form' );

		/**
		 * @type {HTMLInputElement}
		 */
		const widgetId = giveWPForm.querySelector( `[name="${ widgetName }"]` );

		/**
		 * @type {HTMLTextAreaElement}
		 */
		const hCaptchaResponse = giveWPForm.querySelector( `[name="${ inputName }"]` );

		if ( widgetId && hCaptchaResponse ) {
			body.set( widgetName, widgetId.value );
			body.set( inputName, hCaptchaResponse.value );
		}

		config.body = body;
	}

	// noinspection JSCheckFunctionSignatures
	return await originalFetch( resource, config );
};

// Insert hCaptcha to the GiveWP donation form.
wp.domReady( () => {
	const hcaptchaForm = HCaptchaGiveWPObject?.hcaptchaForm;

	if ( ! hcaptchaForm ) {
		// Something is wrong - no script data available.
		return;
	}

	const targetNode = document.getElementById( 'root-givewp-donation-form' );

	if ( ! targetNode ) {
		// No GiveWP form.
		return;
	}

	const observerOptions = {
		childList: true,
		subtree: true,
	};

	const observerCallback = ( mutationsList ) => {
		for ( const mutation of mutationsList ) {
			if ( mutation.type !== 'childList' ) {
				continue;
			}

			const submitButton = document.querySelector( 'button[type="submit"]' );

			if ( ! submitButton ) {
				// No "submit" button on the current form step page.
				return;
			}

			const hCaptchaElement = targetNode.querySelector( '.h-captcha' );

			if ( hCaptchaElement ) {
				// We have added the hCaptcha already.
				return;
			}

			const submitSection = submitButton ? submitButton.closest( 'section' ) : null;

			// On multistep form, submit button is not in the section.
			const submitElement = submitSection ?? submitButton;

			const hcaptcha = document.createElement( 'section' );

			hcaptcha.classList.add( 'givewp-layouts', 'givewp-layouts-section' );

			hcaptcha.innerHTML = JSON.parse( hcaptchaForm );

			// Prepend the new element to the parent element
			submitElement.parentElement.insertBefore( hcaptcha, submitElement );
			hCaptchaBindEvents();

			return;
		}
	};

	const observer = new MutationObserver( observerCallback );
	observer.observe( targetNode, observerOptions );
} );
