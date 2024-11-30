const { fetch: originalFetch } = window;
const checkoutButtonClass = 'wc-block-components-checkout-place-order-button';

wp.hooks.addFilter(
	'hcaptcha.submitButtonSelector',
	'hcaptcha',
	( submitButtonSelector ) => {
		return submitButtonSelector + `, .${ checkoutButtonClass }`;
	}
);

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.classList.contains( `${ checkoutButtonClass }` ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

// Intercept WC Checkout form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	if ( resource.includes( '/wc/store/v1/checkout' ) ) {
		const body = config.body;
		const widgetName = 'hcaptcha-widget-id';
		const inputName = 'h-captcha-response';
		const formData = JSON.parse( body );
		const wcCheckoutBlock = document.querySelector( 'div[data-block-name="woocommerce/checkout"]' );

		/**
		 * @type {HTMLInputElement}
		 */
		const widgetId = wcCheckoutBlock.querySelector( `[name="${ widgetName }"]` );

		/**
		 * @type {HTMLTextAreaElement}
		 */
		const hCaptchaResponse = wcCheckoutBlock.querySelector( `[name="${ inputName }"]` );

		if ( widgetId && hCaptchaResponse ) {
			formData[ widgetName ] = widgetId.value;
			formData[ inputName ] = hCaptchaResponse.value;
		}

		config.body = JSON.stringify( formData );
	}

	// noinspection JSCheckFunctionSignatures
	return await originalFetch( resource, config );
};
