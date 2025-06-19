const { fetch: originalFetch } = window;

// Intercept Spectra form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	// @param {FormData} body
	const body = config.body;
	const blockId = body.get( 'block_id' );
	const inputName = 'h-captcha-response';
	const widgetName = 'hcaptcha-widget-id';
	const errorClassName = 'hcaptcha-error-message';
	const nonceName = 'hcaptcha_spectra_form_nonce';
	const formData = JSON.parse( body.get( 'form_data' ) );

	const selector = `[name="uagb-form-${ blockId }"]`;
	const style = document.createElement( 'style' );

	style.id = `hcaptcha-style-${ blockId }`;
	style.textContent = `
		${ selector } {
			display: block !important;
		}
`;

	// Remove hCaptcha error message.
	const errorMessage = document.querySelector( '.uagb-block-' + blockId + ' .' + errorClassName );

	if ( errorMessage ) {
		errorMessage.remove();
	}

	if ( 'uagb_process_forms' === body.get( 'action' ) && ! formData.hasOwnProperty( inputName ) ) {
		/**
		 * @type {HTMLTextAreaElement}
		 */
		const hCaptchaResponse = document.querySelector( '.uagb-block-' + blockId + ' [name="' + inputName + '"]' );

		/**
		 * @type {HTMLInputElement}
		 */
		const id = document.querySelector( '.uagb-block-' + blockId + ' [name="' + widgetName + '"]' );

		/**
		 * @type {HTMLInputElement}
		 */
		const nonce = document.querySelector( '.uagb-block-' + blockId + ' [name="' + nonceName + '"]' );

		if ( hCaptchaResponse ) {
			formData[ inputName ] = hCaptchaResponse.value;
		}

		if ( id ) {
			formData[ widgetName ] = id.value;
		}

		formData[ nonceName ] = nonce.value;

		body.set( 'form_data', JSON.stringify( formData ) );
		config.body = body;
	}

	const response = await originalFetch( resource, config );

	// Check if the response contains errors we're interested in.
	const responseClone = response.clone();
	const responseData = await responseClone.json().catch( () => null );

	if ( 'uagb_process_forms' === body.get( 'action' ) && typeof responseData?.data === 'string' ) {
		// We have hCaptcha error in responseData.

		const styleToAdd = document.getElementById( style.id );

		if ( ! styleToAdd ) {
			// Add a style preventing hiding the form.
			document.head.appendChild( style );
		}

		// Show an error message.
		const errorContainer = document.createElement( 'div' );
		errorContainer.className = errorClassName;
		errorContainer.textContent = responseData.data;
		errorContainer.style.color = 'red';
		errorContainer.style.padding = '10px 0';

		// Find the form container and append the error message
		const hcaptchaContainer = document.querySelector( '.uagb-block-' + blockId + ' h-captcha' );

		if ( hcaptchaContainer ) {
			hcaptchaContainer.parentNode.insertBefore( errorContainer, hcaptchaContainer );
		}

		// Set the data to 400 for Spectra.
		responseData.data = 400;

		// Return the original response despite the error.
		return response;
	}

	// Remove a style preventing hiding the form.
	const styleToRemove = document.getElementById( style.id );

	if ( styleToRemove ) {
		styleToRemove.remove();
	}

	// If no errors or not the errors we're interested in, return the original response.
	return response;
};
