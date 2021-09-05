/**
 * Ninja Forms controller file.
 *
 * @package hcaptcha-wp
 */

/* global hcaptcha */

const HCaptchaFieldController = Marionette.Object.extend(
	{
		initialize: function() {
			// On the Form Submission's field validation.
			const submitChannel = Backbone.Radio.channel( 'submit' );
			this.listenTo( submitChannel, 'validate:field', this.updateHcaptcha );

			// On the Field's model value change.
			const fieldsChannel = Backbone.Radio.channel( 'fields' );
			this.listenTo( fieldsChannel, 'change:modelValue', this.updateHcaptcha );
		},

		updateHcaptcha: function( model ) {
			// Only validate a specific fields type.
			if ( 'hcaptcha-for-ninja-forms' !== model.get( 'type' ) ) {
				return;
			}

			// Check if Model has a value.
			if ( model.get( 'value' ) ) {
				// Remove Error from Model.
				Backbone.Radio.channel( 'fields' ).request( 'remove:error', model.get( 'id' ), 'required-error' );
			} else {
				const hcap_response = hcaptcha.getResponse();
				model.set( 'value', hcap_response );
			}
		}
	}
);

// On Document Ready.
document.addEventListener( 'DOMContentLoaded', function() {
	// Instantiate our custom field's controller, defined above.
	new HCaptchaFieldController();
} );
