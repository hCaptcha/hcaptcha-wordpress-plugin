/**
 * Ninja Forms controller file.
 */

/* global Marionette, nfRadio */

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.classList.contains( 'nf-element' ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

document.addEventListener( 'DOMContentLoaded', function() {
	const HCaptchaFieldController = Marionette.Object.extend( {
		initialize() {
			// On the Form Submission's field validation.
			const submitChannel = nfRadio.channel( 'submit' );
			this.listenTo( submitChannel, 'validate:field', this.updateHcaptcha );
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
					'.h-captcha[data-fieldId="' + fieldId + '"] textarea[name="h-captcha-response"]'
				);

				model.set( 'value', hcapResponse.value );
			}
		},
	} );

	// Instantiate our custom field's controller, defined above.
	window.hCaptchaFieldController = new HCaptchaFieldController();
} );

/* global jQuery */

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const data = options.data ?? '';

		if ( ! ( typeof data === 'string' || data instanceof String ) ) {
			return;
		}

		if ( ! data.startsWith( 'action=nf_ajax_submit' ) ) {
			return;
		}

		const urlParams = new URLSearchParams( data );
		const formId = JSON.parse( urlParams.get( 'formData' ) ).id;
		const $form = $( '#nf-form-' + formId + '-cont' );
		let id = $form.find( '[name="hcaptcha-widget-id"]' ).val();
		id = id ? id : '';
		options.data += '&hcaptcha-widget-id=' + id;
	} );
}( jQuery ) );
