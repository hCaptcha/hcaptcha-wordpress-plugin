/* global jQuery, hCaptchaBindEvents, elementorFrontend */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'elementor_pro_forms_send_form' ) {
		return;
	}

	hCaptchaBindEvents();
} );

const hcaptchaElementorPro = function() {
	if ( 'undefined' === typeof elementorFrontend ) {
		return;
	}

	wp.hooks.addFilter(
		'hcaptcha.params',
		'hcaptcha',
		() => {
			// noinspection JSUnresolvedReference
			return window?.parent?.HCaptchaMainObject?.params ?? '';
		},
	);

	wp.hooks.addFilter(
		'hcaptcha.ajaxSubmitButton',
		'hcaptcha',
		( isAjaxSubmitButton, submitButtonElement ) => {
			if ( submitButtonElement.closest( '.elementor-form' ) ) {
				return true;
			}

			return isAjaxSubmitButton;
		},
	);
};

window.hCaptchaElementorPro = hcaptchaElementorPro;

jQuery( document ).ready( hcaptchaElementorPro );
