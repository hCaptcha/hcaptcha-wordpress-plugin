import { helper } from './hcaptcha-helper.js';

const hCaptchaEssentialBlocks = window.hCaptchaEssentialBlocks || ( function( window ) {
	const action = 'eb_form_submit';

	const app = {
		init() {
			wp.hooks.addFilter(
				'hcaptcha.ajaxSubmitButton',
				'hcaptcha',
				( isAjaxSubmitButton, submitButtonElement ) => {
					if ( submitButtonElement.classList.contains( 'eb-form-submit-button' ) ) {
						return true;
					}

					return isAjaxSubmitButton;
				}
			);

			// Install global fetch event wrapper (idempotent).
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:before', app.fetchBefore );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
		},

		fetchBefore( event ) {
			const args = event?.detail?.args ?? [];
			const config = args[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			if ( body.get( 'action' ) !== action ) {
				return;
			}

			const formDataRaw = body.get( 'form_data' );
			if ( ! formDataRaw ) {
				return;
			}

			let formData;
			try {
				formData = JSON.parse( formDataRaw );
			} catch ( e ) {
				return;
			}

			// The EB payload contains hcaptcha-widget-id which we can use to find the form.
			const widgetId = formData?.[ 'hcaptcha-widget-id' ] ?? '';
			const widgetInput = widgetId ? document.querySelector( `input[name="hcaptcha-widget-id"][value="${ widgetId }"]` ) : null;
			const form = widgetInput?.closest?.( 'form' ) || null;
			if ( ! form ) {
				return;
			}

			// Field names.
			const responseName = 'h-captcha-response';
			const widgetName = 'hcaptcha-widget-id';
			const nonceName = 'hcaptcha_essential_blocks_nonce';
			const tokenName = 'hcap_fst_token';
			const sigName = 'hcap_hp_sig';

			const response = form.querySelector( `[name="${ responseName }"]` )?.value ?? '';
			const widget = form.querySelector( `[name="${ widgetName }"]` )?.value ?? '';
			const nonce = form.querySelector( `[name="${ nonceName }"]` )?.value ?? '';
			const token = form.querySelector( `[name="${ tokenName }"]` )?.value ?? '';
			const sig = form.querySelector( `[name="${ sigName }"]` )?.value ?? '';
			const hcapHp = form.querySelector( `[id^="hcap_hp_"]` );

			if ( ! Object.prototype.hasOwnProperty.call( formData, responseName ) ) {
				formData[ responseName ] = response;
			}

			if ( ! Object.prototype.hasOwnProperty.call( formData, widgetName ) ) {
				formData[ widgetName ] = widget;
			}

			if ( ! Object.prototype.hasOwnProperty.call( formData, nonceName ) ) {
				formData[ nonceName ] = nonce;
			}

			if ( token ) {
				formData[ tokenName ] = token;
			}

			if ( sig ) {
				formData[ sigName ] = sig;
			}

			if ( hcapHp ) {
				formData[ hcapHp.id ] = hcapHp.value ?? '';
			}

			body.set( 'form_data', JSON.stringify( formData ) );
			config.body = body;
			args[ 1 ] = config;
			event.detail.args = args;
		},

		fetchComplete( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			if ( body.get( 'action' ) !== action ) {
				return;
			}

			if ( typeof window.hCaptchaBindEvents === 'function' ) {
				window.hCaptchaBindEvents();
			}
		},
	};

	return app;
}( window ) );

window.hCaptchaEssentialBlocks = hCaptchaEssentialBlocks;

hCaptchaEssentialBlocks.init();
