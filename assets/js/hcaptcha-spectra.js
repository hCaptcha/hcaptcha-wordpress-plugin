const { fetch: originalFetch } = window;

// Intercept Spectra form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	// @param {FormData} body
	const body = config.body;
	const blockId = body.get( 'block_id' );
	const inputName = 'h-captcha-response';
	const widgetName = 'hcaptcha-widget-id';
	const nonceName = 'hcaptcha_spectra_form_nonce';
	const formData = JSON.parse( body.get( 'form_data' ) );

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

	// noinspection JSCheckFunctionSignatures
	return await originalFetch( resource, config );
};
