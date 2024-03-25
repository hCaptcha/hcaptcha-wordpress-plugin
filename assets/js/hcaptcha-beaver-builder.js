/* global jQuery */

import { helper } from './hcaptcha-helper.js';

wp.hooks.addFilter(
	'hcaptcha.formSelector',
	'hcaptcha',
	( formSelector ) => {
		return formSelector + ', div.fl-login-form';
	}
);

wp.hooks.addFilter(
	'hcaptcha.submitButtonSelector',
	'hcaptcha',
	( submitButtonSelector ) => {
		return submitButtonSelector + ', a.fl-button';
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
		const nodeId = urlParams.get( 'node_id' );
		const $node = $( '[data-node=' + nodeId + ']' );

		helper.addHCaptchaData(
			options,
			'fl_builder_email',
			'hcaptcha_beaver_builder_nonce',
			$node
		);

		helper.addHCaptchaData(
			options,
			'fl_builder_login_form_submit',
			'hcaptcha_login_nonce',
			$node
		);
	} );
}( jQuery ) );
