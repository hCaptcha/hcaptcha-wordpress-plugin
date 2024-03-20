/* global jQuery */

import { helper } from './hcaptcha-helper';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		const data = options.data ?? '';

		if ( typeof data !== 'string' ) {
			return;
		}

		const urlParams = new URLSearchParams( data );
		const area = urlParams.get( 'area' );

		helper.addHCaptchaData(
			options,
			'validate_input',
			'hcaptcha_passster_nonce',
			$( '[data-area=' + area + ']' ).closest( 'form' )
		);
	} );
}( jQuery ) );
