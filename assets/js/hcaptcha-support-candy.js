/* global jQuery */

/**
 * Move the FST field into the form serialized by Support Candy.
 *
 * hCaptcha binds to the outer .wpsc-body container because Support Candy
 * replaces its form dynamically. The generic binder therefore adds the FST
 * field to that container, while Support Candy submits only the inner form.
 *
 * @return {void}
 */
const moveFSTTokenToForm = () => {
	const body = document.querySelector( 'div.wpsc-body' );
	const form = body?.querySelector( 'form.wpsc-create-ticket' );
	const token = body?.querySelector( 'input[name="hcap_fst_token"]' );

	if ( form && token && ! form.contains( token ) ) {
		form.prepend( token );
	}
};

document.addEventListener( 'hCaptchaAfterBindEvents', moveFSTTokenToForm );
document.addEventListener( 'click', function( event ) {
	if ( event.target.closest( '#wpsc-ct-submit' ) ) {
		moveFSTTokenToForm();
	}
}, true );

/**
 * Get the Support Candy action from AJAX request data.
 *
 * @param {FormData|string|Object} data AJAX request data.
 * @return {string} AJAX action.
 */
const getAction = ( data ) => {
	if ( data instanceof FormData ) {
		return data.get( 'action' ) ?? '';
	}

	if ( 'string' === typeof data ) {
		return new URLSearchParams( data ).get( 'action' ) ?? '';
	}

	return data?.action ?? '';
};

wp.hooks.addFilter(
	'hcaptcha.formSelector',
	'hcaptcha',
	( formSelector ) => {
		return formSelector.replace( /(form.*?),/, '$1:not(.wpsc-create-ticket),' ) + ', div.wpsc-body';
	},
);

wp.hooks.addFilter(
	'hcaptcha.submitButtonSelector',
	'hcaptcha',
	( submitButtonSelector ) => {
		return submitButtonSelector + ', button.wpsc-button.primary';
	},
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
	},
);

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	if ( getAction( settings.data ) !== 'wpsc_get_ticket_form' ) {
		return;
	}

	window.hCaptchaBindEvents();
	moveFSTTokenToForm();
} );

jQuery( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
	if ( getAction( settings.data ) !== 'wpsc_set_ticket_form' ) {
		return;
	}

	if ( 'function' === typeof window.hCaptchaFST?.getToken ) {
		window.hCaptchaFST.getToken();
	}
} );
