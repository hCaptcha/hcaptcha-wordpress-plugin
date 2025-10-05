import { helper } from './hcaptcha-helper.js';

const hCaptchaOtter = window.hCaptchaOtter || ( function( window ) {
	const app = {
		init() {
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

			// Install global fetch event wrapper (idempotent).
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:before', app.fetchBefore );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
		},

		fetchBefore( event ) {
			const args = event?.detail?.args ?? [];
			const resource = args[ 0 ];
			const config = args[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			const url = typeof resource === 'string' ? resource : ( resource?.url || '' );

			if ( ! url.includes( '/otter/v1/form/frontend' ) ) {
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

			const formId = formData?.payload?.formId;

			if ( ! formId ) {
				return;
			}

			const form = document.getElementById( formId );

			if ( ! form ) {
				return;
			}

			// Field names.
			const responseName = 'h-captcha-response';
			const widgetName = 'hcaptcha-widget-id';
			const nonceName = 'hcaptcha_otter_nonce';
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
			const args = event?.detail?.args ?? [];
			const resource = args[ 0 ];
			const url = typeof resource === 'string' ? resource : ( resource?.url || '' );

			if ( ! url.includes( '/otter/v1/form/frontend' ) ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window ) );

window.hCaptchaOtter = hCaptchaOtter;

hCaptchaOtter.init();
