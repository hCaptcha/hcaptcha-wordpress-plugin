/* global jQuery, HCaptchaFormidableFormsObject */

/**
 * @param HCaptchaFluentFormObject.noticeLabel
 * @param HCaptchaFluentFormObject.noticeDescription
 */

/**
 * The FormidableForms Admin Page script.
 *
 * @param {jQuery} $ The jQuery instance.
 */
const formidableForms = function( $ ) {
	if ( ! window.location.href.includes( 'page=formidable-settings' ) ) {
		return;
	}

	const $howTo = $( '#hcaptcha_settings .howto' );

	$howTo.html( HCaptchaFormidableFormsObject.noticeLabel );
	$( '<p class="howto">' + HCaptchaFormidableFormsObject.noticeDescription + '</p>' ).insertAfter( $howTo );

	$( '#hcaptcha_settings input' ).attr( {
		disabled: true,
		class: 'frm_noallow',
	} );
};

window.hCaptchaFluentForm = formidableForms;

jQuery( document ).ready( formidableForms );
