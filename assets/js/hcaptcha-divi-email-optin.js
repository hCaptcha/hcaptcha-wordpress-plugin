/* global jQuery */

import { helper } from './hcaptcha-helper.js';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		// eslint-disable-next-line @wordpress/no-global-active-element
		let $form = $( document.activeElement ).closest( 'form' );

		$form = $form.length ? $form : $( '.et_pb_newsletter_form form' );

		helper.addHCaptchaData(
			options,
			'et_pb_submit_subscribe_form',
			'hcaptcha_divi_email_optin_nonce',
			$form
		);
	} );

	$( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
		const params = new URLSearchParams( settings.data );

		if ( params.get( 'action' ) !== 'et_pb_submit_subscribe_form' ) {
			return;
		}

		window.hCaptchaBindEvents();
	} );
}( jQuery ) );
