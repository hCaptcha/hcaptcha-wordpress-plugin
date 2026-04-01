/* global jQuery, hCaptchaSettingsBase, HCaptchaAntiSpamObject */

/**
 * @param HCaptchaAntiSpamObject.ajaxUrl
 * @param HCaptchaAntiSpamObject.checkIPsAction
 * @param HCaptchaAntiSpamObject.checkIPsNonce
 * @param HCaptchaAntiSpamObject.configuredAntiSpamProviderError
 * @param HCaptchaAntiSpamObject.configuredAntiSpamProviders
 */

/**
 * Anti-Spam settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const antiSpam = function( $ ) {
	const $form = $( 'form.hcaptcha-anti-spam' );
	const $blacklistedIPs = $( '#blacklisted_ips' );
	const $whitelistedIPs = $( '#whitelisted_ips' );
	const $antiSpamProvider = $( '[name="hcaptcha_settings[antispam_provider]"]' );
	const $submit = $form.find( '#submit' );
	const dataErrorBgColor = '#fcf0f0';
	const hcaptchaLoading = 'hcaptcha-loading';

	checkAntiSpamProvider();

	/**
	 * Check if the anti-spam provider is configured.
	 */
	function checkAntiSpamProvider() {
		const provider = $antiSpamProvider.val();

		if ( ! provider ) {
			return;
		}

		const configuredProviders = HCaptchaAntiSpamObject.configuredAntiSpamProviders;

		if ( configuredProviders.includes( provider ) ) {
			return;
		}

		const errorMsg = HCaptchaAntiSpamObject.configuredAntiSpamProviderError.replace(
			'%1$s',
			provider
		);

		hCaptchaSettingsBase.showErrorMessage( errorMsg );
	}

	// Check IPs.
	function checkIPs( $el ) {
		const ips = $el.val();

		if ( ips.trim() === '' ) {
			return;
		}

		hCaptchaSettingsBase.clearMessage();
		$submit.attr( 'disabled', true );

		const data = {
			action: HCaptchaAntiSpamObject.checkIPsAction,
			nonce: HCaptchaAntiSpamObject.checkIPsNonce,
			ips,
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		return $.post( {
			url: HCaptchaAntiSpamObject.ajaxUrl,
			data,
			beforeSend: () => $el.parent().addClass( hcaptchaLoading ),
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					$el.css( 'background-color', dataErrorBgColor );
					hCaptchaSettingsBase.showErrorMessage( response.data );

					return;
				}

				$el.css( 'background-color', '' );
				$submit.attr( 'disabled', false );
			} )
			.fail(
				/**
				 * @param {Object} response
				 */
				function( response ) {
					hCaptchaSettingsBase.showErrorMessage( response.statusText );
				},
			)
			.always( function() {
				$el.parent().removeClass( hcaptchaLoading );
			} );
	}

	// Anti-spam provider change.
	$antiSpamProvider.on( 'change', function() {
		checkAntiSpamProvider();
	} );

	// On IPs change.
	$blacklistedIPs.add( $whitelistedIPs ).on( 'blur', function() {
		checkIPs( $( this ) );
	} );
};

window.hCaptchaAntiSpam = antiSpam;

jQuery( document ).ready( antiSpam );
