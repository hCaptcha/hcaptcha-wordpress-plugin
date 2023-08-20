/**
 * Ninja Forms controller file.
 */

/* global hcaptcha, Marionette, Backbone */

// On Document Ready.
document.addEventListener( 'DOMContentLoaded', function() {
	const HCaptchaFieldController = Marionette.Object.extend( {
		initialize() {
			// On the Form Submission's field validation.
			const submitChannel = Backbone.Radio.channel( 'submit' );
			this.listenTo( submitChannel, 'validate:field', this.updateHcaptcha );
			this.listenTo( submitChannel, 'validate:field', this.updateHcaptcha );

			// On the Field's model value change.
			const fieldsChannel = Backbone.Radio.channel( 'fields' );
			this.listenTo( fieldsChannel, 'change:modelValue', this.updateHcaptcha );
		},

		updateHcaptcha( model ) {
			// Only validate a specific fields type.
			if ( 'hcaptcha-for-ninja-forms' !== model.get( 'type' ) ) {
				return;
			}

			// Check if Model has a value.
			if ( model.get( 'value' ) ) {
				// Remove Error from Model.
				Backbone.Radio.channel( 'fields' ).request(
					'remove:error',
					model.get( 'id' ),
					'required-error'
				);
			} else {
				const fieldId = model.get( 'id' );
				const widget = document.querySelector( '.h-captcha[data-fieldId="' + fieldId + '"] iframe' );

				if ( ! widget ) {
					return;
				}

				const widgetId = widget.dataset.hcaptchaWidgetId;
				const hcapResponse = hcaptcha.getResponse( widgetId );
				model.set( 'value', hcapResponse );
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
		const data = options.data;

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
