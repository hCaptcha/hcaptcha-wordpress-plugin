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
		const widgetId = giveWPForm.querySelector( `[name="${ widgetName }"]` );
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
	const targetNode = document.getElementById( 'root-givewp-donation-form' );

	if ( ! targetNode ) {
		return;
	}

	const observerOptions = {
		childList: true,
		subtree: true,
	};

	const observerCallback = ( mutationsList, observer ) => {
		for ( const mutation of mutationsList ) {
			if ( mutation.type === 'childList' ) {
				observer.disconnect();

				const submitSection = document.querySelector( 'button[type="submit"]' ).closest( 'section' );
				const hcaptchaForm = HCaptchaGiveWPObject?.hcaptchaForm;

				if ( ! submitSection || ! hcaptchaForm ) {
					return;
				}

				const hcaptcha = document.createElement( 'section' );

				hcaptcha.classList.add( 'givewp-layouts', 'givewp-layouts-section' );

				hcaptcha.innerHTML = JSON.parse( hcaptchaForm );

				// Prepend the new element to the parent element
				submitSection.parentElement.insertBefore( hcaptcha, submitSection );
				hCaptchaBindEvents();
			}
		}
	};

	const observer = new MutationObserver( observerCallback );
	observer.observe( targetNode, observerOptions );
} );
