import { helper } from './hcaptcha-helper.js';

const wcBlockCheckout = window.hCaptchaWCBlockCheckout || ( function( window ) {
	const app = {
		init() {
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

			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:before', app.fetchBefore );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
		},

		fetchBefore( event ) {
			const [ resource, config ] = event?.detail?.args;

			if ( ! resource.includes( '/wc/store/v1/checkout' ) ) {
				return;
			}

			let formData;

			try {
				formData = JSON.parse( config.body );
			} catch ( e ) {
				formData = {};
			}

			const widgetName = 'hcaptcha-widget-id';
			const responseInputName = 'h-captcha-response';
			const sigInputName = 'hcap_hp_sig';
			const tokenName = 'hcap_fst_token';
			const wcCheckoutBlock = document.querySelector( 'div[data-block-name="woocommerce/checkout"]' );

			/**
			 * @type {HTMLInputElement}
			 */
			const widgetId = wcCheckoutBlock.querySelector( `[name="${ widgetName }"]` );

			/**
			 * @type {HTMLTextAreaElement}
			 */
			const hCaptchaResponse = wcCheckoutBlock.querySelector( `[name="${ responseInputName }"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const hcapHp = wcCheckoutBlock.querySelector( `[id^="hcap_hp_"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const hcapSig = wcCheckoutBlock.querySelector( `[name="${ sigInputName }"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const token = wcCheckoutBlock.querySelector( `[name="${ tokenName }"]` );

			formData[ widgetName ] = widgetId?.value;
			formData[ responseInputName ] = hCaptchaResponse?.value;
			formData[ hcapHp.id ] = hcapHp?.value;
			formData[ sigInputName ] = hcapSig?.value;
			formData[ tokenName ] = token?.value;

			config.body = JSON.stringify( formData );

			event.detail.args.config = config;
		},

		fetchComplete( event ) {
			const resource = event?.detail?.args?.[ 0 ] ?? '';

			if ( ! resource.includes( '/wc/store/v1/checkout' ) ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window ) );

window.hCaptchaWCBlockCheckout = wcBlockCheckout;

wcBlockCheckout.init();
