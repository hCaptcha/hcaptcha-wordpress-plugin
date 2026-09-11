/* global jQuery */

( function( $ ) {
	// Replay the click so Gravity Forms and custom onclick handlers run.
	wp.hooks.addFilter(
		'hcaptcha.ajaxSubmitButton',
		'hcaptcha',
		( isAjaxSubmitButton, submitButtonElement ) => {
			return submitButtonElement.id.startsWith( 'gform_submit_button_' ) || isAjaxSubmitButton;
		},
	);

	$( document ).on( 'gform_post_render', function( e, formId ) {
		const $form = $( '#gform_' + formId );

		if ( ! $form ) {
			return;
		}

		if ( ! $form.attr( 'target' ) ) {
			// Not an ajax form.
			return;
		}

		window.hCaptchaBindEvents();
	} );
}( jQuery ) );
