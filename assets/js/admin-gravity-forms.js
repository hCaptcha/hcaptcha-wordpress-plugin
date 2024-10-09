/* global jQuery, gform, GetFieldsByType, HCaptchaGravityFormsObject, kaggDialog */

/**
 * @param HCaptchaGravityFormsObject.OKBtnText
 * @param HCaptchaGravityFormsObject.noticeDescription
 * @param HCaptchaGravityFormsObject.noticeLabel
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

	/**
	 * Add hCaptcha settings to the GF settings.
	 */
	function addHCaptchaSettings() {
		const $nav = $( 'nav.gform-settings__navigation' );

		if ( ! $nav.length ) {
			return;
		}

		const $firstChild = $nav.children().first();
		const hCaptchaNav = $firstChild.clone();

		hCaptchaNav.attr( 'href', '#' ).removeClass().addClass( 'hcaptcha-nav' );
		hCaptchaNav.find( 'span.icon i' ).removeClass().addClass( 'gform-icon gform-icon--hcaptcha' );
		hCaptchaNav.find( 'span.label' ).text( 'hCaptcha' );

		$firstChild.after( hCaptchaNav );
	}

	$( document ).on( 'click', 'a.hcaptcha-nav', function( event ) {
		event.preventDefault();

		const hCaptchaNav = $( this );
		hCaptchaNav.addClass( 'active' ).siblings().removeClass( 'active' );

		const legend =
			'<legend class="gform-settings-panel__title gform-settings-panel__title--header">' +
			HCaptchaGravityFormsObject.noticeLabel +
			'</legend>';
		const panel =
			'<div class="gform-settings-panel__content">' +
			'<div class="gform-kitchen-sink gform-settings-description">' +
			HCaptchaGravityFormsObject.noticeDescription +
			'</div>' +
			'</div>';
		const tabSettings = $(
			'<fieldset class="gform-settings-panel gform-settings-panel--full gform-settings-panel--with-title">' +
			legend +
			panel +
			'</fieldset>'
		);

		$( '.gform-settings__content' ).html( tabSettings ); // Update the content.
	} );

	addHCaptchaSettings();
} );
