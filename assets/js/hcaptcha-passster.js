/* global jQuery */

import { helper } from './hcaptcha-helper.js';

let activeForm;

const refreshTokens = ( form ) => {
	if ( ! form ) {
		return;
	}

	if ( typeof window.hCaptchaReset === 'function' ) {
		window.hCaptchaReset( form );
	}

	form.querySelectorAll( '[name="h-captcha-response"], [name="g-recaptcha-response"]' ).forEach( ( response ) => {
		response.value = '';
	} );

	if ( window.hCaptchaFST ) {
		window.hCaptchaFST.getToken();
	}
};

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.classList.contains( 'passster-submit' ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	},
);

( function( $ ) {
	$( document ).on( 'click', '.passster-submit', function() {
		activeForm = this.closest( 'form' );
	} );

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
			$( '[data-area=' + area + ']' ).closest( 'form' ),
		);
	} );

	$( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
		if ( typeof settings.data !== 'string' ) {
			return;
		}

		const params = new URLSearchParams( settings.data );

		if ( 'validate_input' !== params.get( 'action' ) ) {
			return;
		}

		const form = $( '[data-area="' + params.get( 'area' ) + '"]' ).closest( 'form' )[ 0 ];

		refreshTokens( form );
	} );
}( jQuery ) );

( function() {
	const originalFetch = window.fetch;

	if ( typeof originalFetch !== 'function' ) {
		return;
	}

	window.fetch = async function( resource, options = {} ) {
		const url = typeof resource === 'string' ? resource : resource?.url ?? '';
		const isUnlockRequest = url.includes( '/passster/v1/unlock' );
		let requestOptions = options;

		if ( isUnlockRequest && activeForm && typeof options.body === 'string' ) {
			try {
				const data = JSON.parse( options.body );
				const hCaptchaData = helper.getHCaptchaData( activeForm, 'hcaptcha_passster_nonce' );

				requestOptions = {
					...options,
					body: JSON.stringify( { ...data, ...hCaptchaData } ),
				};
			} catch {
				// Pass through malformed or non-JSON request bodies unchanged.
			}
		}

		try {
			return await originalFetch.call( this, resource, requestOptions );
		} finally {
			if ( isUnlockRequest ) {
				refreshTokens( activeForm );
			}
		}
	};
}() );
