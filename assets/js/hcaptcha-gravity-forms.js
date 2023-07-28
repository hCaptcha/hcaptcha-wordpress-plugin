/* global jQuery */

( function( $ ) {
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
