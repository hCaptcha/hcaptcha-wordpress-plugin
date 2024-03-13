/* global jQuery, HCaptchaFormidableFormsObject */

/**
 * @param HCaptchaFluentFormObject.noticeLabel
 * @param HCaptchaFluentFormObject.noticeDescription
 */
jQuery( document ).ready( function( $ ) {
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
} );
