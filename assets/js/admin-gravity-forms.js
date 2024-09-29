/* global jQuery, gform, GetFieldsByType, HCaptchaGravityFormsObject, kaggDialog */

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

const originalAddEventListener = EventTarget.prototype.addEventListener;

EventTarget.prototype.addEventListener = function( type, listener, options ) {
	const wrappedListener = function( event ) {
		const ignoreTypes = [ 'mouseover', 'mousemove', 'mouseout', 'pointermove', 'blur' ];

		if ( ! ignoreTypes.includes( type ) ) {
			console.log( `Event: ${ type }`, event );
		}

		return listener.apply( this, arguments );
	};

	return originalAddEventListener.call( this, type, wrappedListener, options );
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

jQuery( document ).ready( function( $ ) {
	$( document ).on( 'gform_field_added', function( event, form, field ) {
		if ( field.type === 'hcaptcha' ) {
			window.hCaptchaBindEvents();
		}
	} );
} );
