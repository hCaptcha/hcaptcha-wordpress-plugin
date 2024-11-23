wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.classList.contains( 'wp-block-button__link' ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

const { fetch: originalFetch } = window;

// Intercept Otter form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	/**
	 * @param {FormData} body
	 * @param {string}   formData.payload.formId
	 */
	const body = config.body;
	const inputName = 'h-captcha-response';
	const widgetName = 'hcaptcha-widget-id';
	const nonceName = 'hcaptcha_otter_nonce';
	const formData = JSON.parse( body.get( 'form_data' ) );

	if ( resource.includes( '/otter/v1/form/frontend' ) && ! formData.hasOwnProperty( inputName ) ) {
		const form = document.getElementById( formData.payload.formId );

		/**
		 * @type {HTMLTextAreaElement}
		 */
		const hCaptchaResponse = form.querySelector( '[name="' + inputName + '"]' );

		/**
		 * @type {HTMLInputElement}
		 */
		const id = form.querySelector( '[name="' + widgetName + '"]' );

		/**
		 * @type {HTMLInputElement}
		 */
		const nonce = form.querySelector( '[name="' + nonceName + '"]' );

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
