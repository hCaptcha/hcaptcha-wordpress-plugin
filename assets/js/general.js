/* global jQuery, HCaptchaGeneralObject */

/**
 * @param HCaptchaGeneralObject.ajaxUrl
 * @param HCaptchaGeneralObject.action
 * @param HCaptchaGeneralObject.nonce
 * @param HCaptchaGeneralObject.modeLive
 * @param HCaptchaGeneralObject.modeTestPublisher
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetected
 * @param HCaptchaGeneralObject.siteKey
 * @param HCaptchaGeneralObject.modeTestPublisherSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey
 */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const general = function( $ ) {
	const msgSelector = '#hcaptcha-message';
	const $message = $( msgSelector );
	const $language = $( '[name="hcaptcha_settings[language]"]' );
	const modes = {};

	modes[ HCaptchaGeneralObject.modeLive ] = HCaptchaGeneralObject.siteKey;
	modes[ HCaptchaGeneralObject.modeTestPublisher ] = HCaptchaGeneralObject.modeTestPublisherSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser ] = HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseBotDetected ] = HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey;

	function clearMessage() {
		$message.removeClass();
		$message.html( '' );
	}

	function showMessage( message, msgClass ) {
		$message.removeClass();
		$message.addClass( msgClass + ' notice settings-error is-dismissible' );
		$message.html( `<p>${ message }</p>` );
		$message.show();
	}

	function showSuccessMessage( response ) {
		showMessage( response, 'notice-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'notice-error' );
	}

	function hCaptchaGeneralReset() {
		document.querySelector( '#hcaptcha-options .h-captcha' ).innerHTML = '';
		window.hCaptchaBindEvents();
	}

	function apiScriptReset() {
		const id = 'hcaptcha-api';
		const api = document.getElementById( id );
		const url = new URL( api.src );
		const urlSearchParams = url.searchParams;
		const hl = $language.val();

		urlSearchParams.set( 'hl', hl );
		api.parentNode.removeChild( api );

		const s = document.createElement( 'script' );

		s.type = 'text/javascript';
		s.id = id;
		s.src = url.toString();
		s.async = true;
		s.onload = window.hCaptchaOnLoad;
		s.render = 'explicit';
		s.hl = hl;

		document.querySelector( '#hcaptcha-options .h-captcha' ).innerHTML = '';

		const t = document.getElementsByTagName( 'script' )[ 0 ];

		t.parentNode.insertBefore( s, t );
	}

	$( '#check_config' ).on( 'click', function( event ) {
		event.preventDefault();
		clearMessage();

		const data = {
			action: HCaptchaGeneralObject.action,
			nonce: HCaptchaGeneralObject.nonce,
			'h-captcha-response': $( 'textarea[name="h-captcha-response"]' ).val(),
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		$.post( {
			url: HCaptchaGeneralObject.ajaxUrl,
			data,
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );
					return;
				}

				showSuccessMessage( response.data );
			} )
			.fail( function( response ) {
				showErrorMessage( response.statusText );
			} )
			.always( function() {
				hCaptchaGeneralReset();
			} );
	} );

	$( '[name="hcaptcha_settings[theme]"]' ).on( 'change', function( e ) {
		$( '.h-captcha' ).attr( 'data-theme', $( e.target ).val() );
		hCaptchaGeneralReset();
	} );

	$( '[name="hcaptcha_settings[size]"]' ).on( 'change', function( e ) {
		const $invisibleNotice = $( '#hcaptcha-invisible-notice' );
		const size = $( e.target ).val();

		if ( 'invisible' === size ) {
			$invisibleNotice.show();
		} else {
			$invisibleNotice.hide();
		}

		$( '.h-captcha' ).attr( 'data-size', size );
		hCaptchaGeneralReset();
	} );

	$language.on( 'change', function() {
		apiScriptReset();
	} );

	$( '[name="hcaptcha_settings[mode]"]' ).on( 'change', function( e ) {
		const mode = $( e.target ).val();

		if ( ! modes.hasOwnProperty( mode ) ) {
			return;
		}

		if ( mode === HCaptchaGeneralObject.modeLive ) {
			$( '[name="hcaptcha_settings[site_key]"]' ).attr( 'disabled', false );
			$( '[name="hcaptcha_settings[secret_key]"]' ).attr( 'disabled', false );
		} else {
			$( '[name="hcaptcha_settings[site_key]"]' ).attr( 'disabled', true );
			$( '[name="hcaptcha_settings[secret_key]"]' ).attr( 'disabled', true );
		}

		$( '.h-captcha' ).attr( 'data-sitekey', modes[ mode ] );
		hCaptchaGeneralReset();
	} );
};

window.hCaptchaGeneral = general;

jQuery( document ).ready( general );
