/* global jQuery, hCaptchaSettingsBase, HCaptchaAntiSpamObject */

/**
 * @param HCaptchaAntiSpamObject.ajaxUrl
 * @param HCaptchaAntiSpamObject.checkIPsAction
 * @param HCaptchaAntiSpamObject.checkIPsNonce
 * @param HCaptchaAntiSpamObject.configuredAntiSpamProviderError
 * @param HCaptchaAntiSpamObject.configuredAntiSpamProviders
 * @param HCaptchaAntiSpamObject.detectCloudflareAction
 * @param HCaptchaAntiSpamObject.detectCloudflareError
 * @param HCaptchaAntiSpamObject.detectCloudflareNonce
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
	const $trustedHeadersDescription = $( '#trusted_address_headers' ).closest( 'td' ).find( 'p.description' ).last();
	const dataErrorBgColor = '#fcf0f0';
	const hcaptchaLoading = 'hcaptcha-loading';

	checkAntiSpamProvider();
	bindCloudflareDetector();

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
			provider,
		);

		hCaptchaSettingsBase.showErrorMessage( errorMsg );
	}

	/**
	 * Bind Cloudflare detection link.
	 */
	function bindCloudflareDetector() {
		$( document )
			.off( 'click.hcaptchaDetectCloudflare', 'a[href="#detect-cloudflare"]' )
			.on( 'click.hcaptchaDetectCloudflare', 'a[href="#detect-cloudflare"]', function( event ) {
				event.preventDefault();

				const $link = $( this );

				if ( $link.data( 'hcaptchaDetecting' ) ) {
					return;
				}

				const data = {
					action: HCaptchaAntiSpamObject.detectCloudflareAction,
					nonce: HCaptchaAntiSpamObject.detectCloudflareNonce,
				};

				$link.data( 'hcaptchaDetecting', true );

				$.post( {
					url: HCaptchaAntiSpamObject.ajaxUrl,
					data,
					beforeSend: () => $trustedHeadersDescription.addClass( hcaptchaLoading ),
				} )
					.done( function( response ) {
						if ( ! response.success ) {
							hCaptchaSettingsBase.showErrorMessage(
								response.data || HCaptchaAntiSpamObject.detectCloudflareError,
							);

							return;
						}

						$trustedHeadersDescription.text( response.data.message );
					} )
					.fail( function( response ) {
						hCaptchaSettingsBase.showErrorMessage(
							response.statusText || HCaptchaAntiSpamObject.detectCloudflareError,
						);
					} )
					.always( function() {
						$link.data( 'hcaptchaDetecting', false );
						$trustedHeadersDescription.removeClass( hcaptchaLoading );
					} );
			} );
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
