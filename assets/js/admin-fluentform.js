/* global jQuery, HCaptchaFluentFormObject */

/**
 * @param HCaptchaFluentFormObject.noticeLabel
 * @param HCaptchaFluentFormObject.noticeDescription
 */
jQuery( document ).ready( function( $ ) {
	if ( ! window.location.href.includes( 'page=fluent_forms_settings' ) ) {
		return;
	}

	const $hcaptchaWrap = $( '.ff_hcaptcha_wrap' );

	$hcaptchaWrap.find( '.ff_card_head h5' )
		.html( HCaptchaFluentFormObject.noticeLabel ).css( 'display', 'block' );
	$hcaptchaWrap.find( '.ff_card_head p' ).first()
		.html( HCaptchaFluentFormObject.noticeDescription ).css( 'display', 'block' );
} );
