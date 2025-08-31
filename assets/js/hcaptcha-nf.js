/* global Marionette, nfRadio, jQuery */

const hCaptchaNF = window.hCaptchaNF || ( function( window, $ ) {
	const app = {
		init() {
			// Register Ajax submit button.
			wp.hooks.addFilter(
				'hcaptcha.ajaxSubmitButton',
				'hcaptcha',
				app.isAjaxSubmitButton
			);

			document.addEventListener( 'DOMContentLoaded', app.onDomReady );
			$.ajaxPrefilter( this.ajaxPrefilter() );
			$( document ).on( 'ajaxSuccess', app.ajaxSuccessHandler );
		},

		isAjaxSubmitButton( isAjaxSubmitButton, submitButtonElement ) {
			if ( submitButtonElement.classList.contains( 'nf-element' ) ) {
				return true;
			}

			return isAjaxSubmitButton;
		},

		// Initialize Ninja Forms field controller listeners when DOM is ready.
		onDomReady() {
			// Create a Marionette controller mirroring the original behavior but scoped here.
			const HCaptchaFieldController = Marionette.Object.extend( {
				initialize() {
					// On the Form Submission's field validation.
					const submitChannel = nfRadio.channel( 'submit' );
					this.listenTo( submitChannel, 'validate:field', this.updateHcaptcha );

					// On the Field's model value change.
					const fieldsChannel = nfRadio.channel( 'fields' );
					this.listenTo( fieldsChannel, 'change:modelValue', this.updateHcaptcha );
				},

				updateHcaptcha( model ) {
					// Only validate a specific fields type.
					if ( 'hcaptcha-for-ninja-forms' !== model.get( 'type' ) ) {
						return;
					}

					// Check if the Model has a value.
					if ( model.get( 'value' ) ) {
						// Remove Error from Model.
						nfRadio.channel( 'fields' ).request(
							'remove:error',
							model.get( 'id' ),
							'required-error'
						);
					} else {
						const fieldId = model.get( 'id' );

						/**
						 * @type {HTMLTextAreaElement}
						 */
						const hcapResponse = document.querySelector(
							`div[data-field-id="${ fieldId }"] textarea[name="h-captcha-response"]`
						);

						model.set( 'value', hcapResponse?.value );
					}
				},
			} );

			// Instantiate our custom field's controller, defined above.
			window.hCaptchaFieldController = new HCaptchaFieldController();
		},

		// Register Ajax prefilter for NF submissions.
		ajaxPrefilter() {
			return function( options ) {
				const data = options.data ?? '';

				if ( typeof data !== 'string' ) {
					return;
				}

				const urlParams = new URLSearchParams( data );
				const action = urlParams.get( 'action' );

				if ( 'nf_ajax_submit' !== action ) {
					return;
				}

				const widgetName = 'hcaptcha-widget-id';
				const tokenName = 'hcap_fst_token';
				const sigName = 'hcap_hp_sig';

				const formId = JSON.parse( urlParams.get( 'formData' ) ).id;
				const $form = $( `#nf-form-${ formId }-cont` );
				const widget = $form.find( `[name="${ widgetName }"]` ).val() ?? '';
				const token = $form.find( `[name="${ tokenName }"]` ).val() ?? '';
				const sig = $form.find( `[name="${ sigName }"]` ).val() ?? '';
				const hcapHp = $form.find( `[id^="hcap_hp_"]` );

				urlParams.set( widgetName, widget );
				urlParams.set( tokenName, token );
				urlParams.set( sigName, sig );
				urlParams.set( hcapHp.attr( 'id' ) ?? '', hcapHp.val() ?? '' );

				options.data = urlParams.toString();
			};
		},

		// jQuery ajaxSuccess handler.
		ajaxSuccessHandler( event, xhr, settings ) {
			const data = settings.data ?? '';

			if ( typeof data !== 'string' ) {
				return;
			}

			const action = new URLSearchParams( data ).get( 'action' );

			if ( 'nf_ajax_submit' !== action ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaNF = hCaptchaNF;

hCaptchaNF.init();
