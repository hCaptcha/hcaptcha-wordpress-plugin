/* global jQuery */

import { helper } from './hcaptcha-helper.js';

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if (
			submitButtonElement.classList.contains( 'uael-login-form-submit' ) ||
			submitButtonElement.classList.contains( 'uael-register-submit' )
		) {
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

		const params = new URLSearchParams( data );
		const action = params.get( 'action' );

		if ( ! action ) {
			return;
		}

		let $node;

		switch ( action ) {
			case 'uael_login_form_submit':
				$node = $( '.uael-login-form' );

				break;
			case 'uael_register_user':
				$node = $( '.uael-registration-form' );
				break;

			default:
				return;
		}

		helper.addHCaptchaData(
			options,
			'uael_login_form_submit',
			'hcaptcha_login_nonce',
			$node,
		);

		helper.addHCaptchaData(
			options,
			'uael_register_user',
			'hcaptcha_ultimate_addons_register_nonce',
			$node,
		);
	} );

	$( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
		const params = new URLSearchParams( settings.data );
		const action = params.get( 'action' );

		if ( action !== 'uael_login_form_submit' && action !== 'uael_register_user' ) {
			return;
		}

		window.hCaptchaBindEvents();

		const response = JSON.parse( xhr.responseText );

		if ( response?.success ) {
			return;
		}

		const data = response?.data ?? '';

		/**
		 * @typedef {Object|string} data
		 * @property {string} hCaptchaError hCaptcha error
		 */
		const hCaptchaError = data?.hCaptchaError ?? '';

		if ( ! hCaptchaError ) {
			return;
		}

		$( 'h-captcha' )
			.after(
				'<span class="uael-register-field-message"><span class="uael-loginform-error">' +
				hCaptchaError +
				'</span></span>'
			);
	} );
}( jQuery ) );
