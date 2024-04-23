const { fetch: originalFetch } = window;

// Intercept Essential Blocks form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	// @param {FormData} body
	const body = config.body;
	const inputName = 'h-captcha-response';
	const formData = JSON.parse( body.get( 'form_data' ) );

	if ( 'eb_form_submit' === body.get( 'action' ) ) {
		const widgetId = formData[ 'hcaptcha-widget-id' ];
		const widget = document.querySelector( 'input[value="' + widgetId + '"]' );
		const hCaptchaResponse = widget.closest( 'form' ).querySelector( '[name="' + inputName + '"]' );

		if ( hCaptchaResponse ) {
			formData[ inputName ] = hCaptchaResponse.value;
		}

		body.set( 'form_data', JSON.stringify( formData ) );
		config.body = body;
	}

	// noinspection JSCheckFunctionSignatures
	return await originalFetch( resource, config );
};
