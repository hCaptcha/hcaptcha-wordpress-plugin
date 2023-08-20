/* global jQuery, HCaptchaQuformObject */

/**
 * @param HCaptchaQuformObject.noticeLabel
 * @param HCaptchaQuformObject.noticeDescription
 */
jQuery( document ).ready( function( $ ) {
	if ( ! window.location.href.includes( 'page=quform.settings' ) ) {
		return;
	}

	const settingSelector = '.qfb-setting';
	const $hcaptchaHeading = $( '.qfb-icon.qfb-icon-hand-paper-o' ).closest( '.qfb-settings-heading' );

	const html = $hcaptchaHeading.html();
	const text = $hcaptchaHeading.text();

	$hcaptchaHeading.html( html.replace( text, HCaptchaQuformObject.noticeLabel ) );
	$hcaptchaHeading
		.next( 'p' ).html( HCaptchaQuformObject.noticeDescription )
		.next( settingSelector ).hide()
		.next( settingSelector ).hide();
} );

jQuery( document ).ready( function( $ ) {
	const blockHCaptchaSettings = () => {
		if ( $provider.val() === 'hcaptcha' ) {
			$size.hide();
			$theme.hide();
			$lang.hide();

			// Remove label and description which can be here from the previous opening of the captcha field settings panel.
			$( '.' + noticeLabelClass ).remove();
			$( '.' + noticeDescriptionClass ).remove();

			$( descriptionHtml ).insertAfter( $provider );
			$( labelHtml ).insertAfter( $provider );
		} else {
			$size.show();
			$theme.show();
			$lang.show();

			$( '.' + noticeLabelClass ).remove();
			$( '.' + noticeDescriptionClass ).remove();
		}
	};

	const providerId = 'qfb_recaptcha_provider';
	const $provider = $( '#' + providerId );
	const settingSelector = '.qfb-setting';
	const $size = $( '#qfb_recaptcha_size' ).closest( settingSelector );
	const $theme = $( '#qfb_recaptcha_theme' ).closest( settingSelector );
	const $lang = $( '#qfb_hcaptcha_lang' ).closest( settingSelector );
	const noticeLabelClass = 'hcaptcha-notice-label';
	const noticeDescriptionClass = 'hcaptcha-notice-description';
	const labelHtml = '<div class="qfb-setting-label ' + noticeLabelClass + '" style="float:none;">' +
		'<label>' + HCaptchaQuformObject.noticeLabel + '</label></div>';
	const descriptionHtml = '<div class="qfb-setting-inner ' + noticeDescriptionClass + '">' +
		HCaptchaQuformObject.noticeDescription + '</div>';

	if ( ! window.location.href.includes( 'page=quform.forms' ) ) {
		return;
	}

	// We need observer for the first opening of the captcha field settings panel.
	const observer = new MutationObserver( blockHCaptchaSettings );

	observer.observe(
		document.getElementById( providerId ).closest( settingSelector ),
		{
			attributes: true,
		}
	);

	$provider.on( 'change', blockHCaptchaSettings );
} );
