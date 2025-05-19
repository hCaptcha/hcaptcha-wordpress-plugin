/* global jQuery */

import { helper } from './hcaptcha-helper.js';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const data = options.data ?? '';

		if ( typeof data !== 'string' ) {
			return;
		}

		const $node = $( '.uael-login-form' );

		helper.addHCaptchaData(
			options,
			'uael_login_form_submit',
			'hcaptcha_login_nonce',
			$node,
		);
	} );

	$( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
		const params = new URLSearchParams( settings.data );

		if ( params.get( 'action' ) !== 'uael_login_form_submit' ) {
			return;
		}

		const response = JSON.parse( xhr.responseText );
		const errors = [ 'incorrect_password', 'invalid_username', 'invalid_email' ];
		const data = response?.data ?? '';

		if ( ! response?.success && data && ! errors.includes( data ) ) {
			$( '.elementor-hcaptcha' )
				.after(
					'<span class="uael-register-field-message"><span class="uael-loginform-error">' +
					response.data +
					'</span></span>'
				);
		}

		window.hCaptchaBindEvents();
	} );
}( jQuery ) );
