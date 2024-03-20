/* global jQuery */

import { helper } from './hcaptcha-helper.js';

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options ) {
		helper.addHCaptchaData(
			options,
			'et_pb_submit_subscribe_form',
			'hcaptcha_divi_email_optin_nonce',
			$( '.et_pb_newsletter_form form' )
		);
	} );
}( jQuery ) );
