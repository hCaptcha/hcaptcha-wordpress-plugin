/* global jQuery */

import { helper } from './hcaptcha-helper.js';

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.classList.contains( 'passster-submit' ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

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
