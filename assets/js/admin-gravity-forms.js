/* global gform, GetFieldsByType, HCaptchaGravityFormsObject, kaggDialog */

/**
 * @param HCaptchaGravityFormsObject.onlyOne
 * @param HCaptchaGravityFormsObject.OKBtnText
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
		'gform_form_editor_can_field_be_added',
		( value, type ) => {
			if ( type === 'hcaptcha' && GetFieldsByType( [ 'hcaptcha' ] ).length > 0 ) {
				kaggDialog.confirm( {
					title: HCaptchaGravityFormsObject.onlyOne,
					content: '',
					type: 'info',
					buttons: {
						ok: {
							text: HCaptchaGravityFormsObject.OKBtnText,
						},
					},
				} );

				return false;
			}

			return value;
		}
	);
} );
