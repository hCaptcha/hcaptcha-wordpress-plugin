/* global gform, GetFieldsByType, HCaptchaGravityFormsObject */

/**
 * @param HCaptchaGravityFormsObject.onlyOne
 */

window.SetDefaultValues_hcaptcha = function( field ) {
	field.inputs = null;
	field.displayOnly = true;
	field.label = 'hCaptcha';
	field.labelPlacement = 'hidden_label';

	return field;
};

document.addEventListener( 'DOMContentLoaded', function() {
	gform.addFilter(
		'gform_form_editor_can_field_be_added', ( value, type ) => {
			if ( type === 'hcaptcha' && GetFieldsByType( [ 'hcaptcha' ] ).length > 0 ) {
				// eslint-disable-next-line no-alert
				alert( HCaptchaGravityFormsObject.onlyOne );
				return false;
			}

			return value;
		} );
} );
