/* global jQuery */

wp.hooks.addFilter(
	'hcaptcha.formSelector',
	'hcaptcha',
	( formSelector ) => {
		return formSelector.replace( /(form.*?),/, '$1:not(.wpsc-create-ticket),' ) + ', div.wpsc-body';
	}
);

wp.hooks.addFilter(
	'hcaptcha.submitButtonSelector',
	'hcaptcha',
	( submitButtonSelector ) => {
		return submitButtonSelector + ', button.wpsc-button.primary';
	}
);

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if (
			submitButtonElement.classList.contains( 'wpsc-button' ) &&
			submitButtonElement.classList.contains( 'primary' )
		) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'wpsc_get_ticket_form' ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
