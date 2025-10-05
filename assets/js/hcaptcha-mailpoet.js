/* global jQuery */

const hCaptchaMailPoet = window.hCaptchaMailPoet || ( function( window, $ ) {
	const app = {
		init() {
			$.ajaxPrefilter( app.ajaxPrefilter() );
			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		ajaxPrefilter() {
			return function( options ) {
				const data = options.data ?? '';

				if ( typeof data !== 'string' ) {
					return;
				}

				const urlParams = new URLSearchParams( data );
				const action = urlParams.get( 'action' );

				if ( 'mailpoet' !== action ) {
					return;
				}

				// eslint-disable-next-line @wordpress/no-global-active-element
				const eventTarget = options.context || document.activeElement;
				const $form = $( eventTarget.closest( 'form' ) );

				// Field names.
				const responseName = 'h-captcha-response';
				const widgetName = 'hcaptcha-widget-id';
				const nonceName = 'hcaptcha_mailpoet_nonce';
				const tokenName = 'hcap_fst_token';
				const sigName = 'hcap_hp_sig';

				const response = $form.find( `[name="${ responseName }"]` ).val() ?? '';
				const widget = $form.find( `[name="${ widgetName }"]` ).val() ?? '';
				const nonce = $form.find( `[name="${ nonceName }"]` ).val() ?? '';
				const token = $form.find( `[name="${ tokenName }"]` ).val() ?? '';
				const sig = $form.find( `[name="${ sigName }"]` ).val() ?? '';
				const $hcapHp = $form.find( `[id^="hcap_hp_"]` );

				urlParams.set( responseName, response );
				urlParams.set( widgetName, widget );
				urlParams.set( nonceName, nonce );
				urlParams.set( tokenName, token );
				urlParams.set( sigName, sig );

				if ( $hcapHp.length ) {
					urlParams.set( $hcapHp.attr( 'id' ) ?? '', $hcapHp.val() ?? '' );
				}

				options.data = urlParams.toString();
			};
		},

		ajaxCompleteHandler( event, xhr, settings ) {
			const data = settings?.data ?? '';

			if ( typeof data !== 'string' ) {
				return;
			}

			const action = new URLSearchParams( data ).get( 'action' );

			if ( 'mailpoet' !== action ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaMailPoet = hCaptchaMailPoet;

hCaptchaMailPoet.init();
