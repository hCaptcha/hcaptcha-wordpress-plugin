/* global jQuery */

import { helper } from './hcaptcha-helper.js';

const patchRecaptchaPreview = () => {
	const builder = window.FLBuilder;
	const contactHelper = builder?._moduleHelpers?.[ 'contact-form' ];

	if (
		! contactHelper ||
		'function' !== typeof contactHelper._toggleReCaptcha ||
		contactHelper.hCaptchaRecaptchaPatched
	) {
		return;
	}

	const toggleRecaptcha = contactHelper._toggleReCaptcha;

	contactHelper._toggleReCaptcha = function( event ) {
		const $form = jQuery(
			'.fl-builder-settings[data-type="contact-form"]',
		);
		const toggle = $form.find( 'select[name="recaptcha_toggle"]' ).val();

		if ( 'hide' !== toggle ) {
			return toggleRecaptcha.call( this, event );
		}

		const nodeId = $form.attr( 'data-node' );

		jQuery( '.fl-node-' + nodeId )
			.find( '.fl-grecaptcha' )
			.parent()
			.hide();
	};
	contactHelper.hCaptchaRecaptchaPatched = true;
};

if ( window.FLBuilder?.addHook ) {
	window.FLBuilder.addHook( 'settings-form-init', patchRecaptchaPreview );
	patchRecaptchaPreview();
}

wp.hooks.addFilter(
	'hcaptcha.formSelector',
	'hcaptcha',
	( formSelector ) => {
		return formSelector + ', div.fl-login-form';
	},
);

wp.hooks.addFilter(
	'hcaptcha.submitButtonSelector',
	'hcaptcha',
	( submitButtonSelector ) => {
		return submitButtonSelector + ', a.fl-button';
	},
);

( function( $ ) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter( function( options, originalOptions, jqXHR ) {
		const data = options.data ?? '';

		if ( typeof data !== 'string' ) {
			return;
		}

		const urlParams = new URLSearchParams( data );
		const action = urlParams.get( 'action' );
		const nodeId = urlParams.get( 'node_id' );
		const $node = $( '[data-node=' + nodeId + ']' );

		helper.addHCaptchaData(
			options,
			'fl_builder_email',
			'hcaptcha_beaver_builder_nonce',
			$node,
		);

		helper.addHCaptchaData(
			options,
			'fl_builder_login_form_submit',
			'hcaptcha_login_nonce',
			$node,
		);

		if (
			[
				'fl_builder_email',
				'fl_builder_login_form_submit',
			].includes( action ) &&
			'function' === typeof jqXHR?.always
		) {
			jqXHR.always( () => {
				if ( 'function' === typeof window.hCaptchaFST?.getToken ) {
					window.hCaptchaFST.getToken();
				}
			} );
		}
	} );
}( jQuery ) );
