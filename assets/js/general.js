/* global jQuery, hCaptcha, HCaptchaGeneralObject */

/**
 * @param HCaptchaGeneralObject.ajaxUrl
 * @param HCaptchaGeneralObject.checkConfigAction
 * @param HCaptchaGeneralObject.nonce
 * @param HCaptchaGeneralObject.modeLive
 * @param HCaptchaGeneralObject.modeTestPublisher
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetected
 * @param HCaptchaGeneralObject.siteKey
 * @param HCaptchaGeneralObject.modeTestPublisherSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey
 * @param HCaptchaGeneralObject.checkConfigNotice
 * @param HCaptchaMainObject.params
 */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const general = function( $ ) {
	const msgSelector = '#hcaptcha-message';
	let $message = $( msgSelector );
	const $form = $( 'form.hcaptcha-general' );
	const $siteKey = $( '[name="hcaptcha_settings[site_key]"]' );
	const $secretKey = $( '[name="hcaptcha_settings[secret_key]"]' );
	const $theme = $( '[name="hcaptcha_settings[theme]"]' );
	const $size = $( '[name="hcaptcha_settings[size]"]' );
	const $language = $( '[name="hcaptcha_settings[language]"]' );
	const $mode = $( '[name="hcaptcha_settings[mode]"]' );
	const $customThemes = $( '[name="hcaptcha_settings[custom_themes][]"]' );
	const $configParams = $( '[name="hcaptcha_settings[config_params]"]' );
	const $submit = $form.find( '#submit' );
	const modes = {};
	const siteKeyInitVal = $siteKey.val();
	const secretKeyInitVal = $secretKey.val();

	modes[ HCaptchaGeneralObject.modeLive ] = HCaptchaGeneralObject.siteKey;
	modes[ HCaptchaGeneralObject.modeTestPublisher ] = HCaptchaGeneralObject.modeTestPublisherSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser ] = HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseBotDetected ] = HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey;

	function clearMessage() {
		$message.remove();
		$( '<div id="hcaptcha-message"></div>' ).insertAfter( '#hcaptcha-options h2' );
		$message = $( msgSelector );
	}

	function showMessage( message, msgClass ) {
		$message.removeClass();
		$message.addClass( msgClass + ' notice is-dismissible' );
		const messageLines = message.split( '\n' ).map( function( line ) {
			return `<p>${ line }</p>`;
		} );
		$message.html( messageLines.join( '' ) );

		$( document ).trigger( 'wp-updates-notice-added' );

		const $wpwrap = $( '#wpwrap' );
		const top = $wpwrap.position().top;

		$( 'html, body' ).animate(
			{
				scrollTop: $message.offset().top - top - parseInt( $message.css( 'margin-bottom' ) ),
			},
			1000
		);
	}

	function showSuccessMessage( response ) {
		showMessage( response, 'notice-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'notice-error' );
	}

	function hCaptchaUpdate( params ) {
		const updatedParams = Object.assign( hCaptcha.getParams(), params );
		hCaptcha.setParams( updatedParams );

		const sampleHCaptcha = document.querySelector( '#hcaptcha-options .h-captcha' );
		sampleHCaptcha.innerHTML = '';

		for ( const key in params ) {
			sampleHCaptcha.setAttribute( `data-${ key }`, `${ params[ key ] }` );
		}

		hCaptcha.bindEvents();
	}

	function applyCustomThemes() {
		let configParamsJson = $configParams.val().trim();
		let configParams;

		configParamsJson = configParamsJson ? configParamsJson : null;

		try {
			configParams = JSON.parse( configParamsJson );
		} catch ( e ) {
			$configParams.css( 'background-color', '#ffabaf' );
			$submit.attr( 'disabled', true );
			showErrorMessage( 'Bad JSON!' );

			return;
		}

		if ( ! $customThemes.prop( 'checked' ) ) {
			configParams = {
				sitekey: $siteKey.val(),
				theme: $theme.val(),
				size: $size.val(),
				hl: $language.val(),
			};
		}

		hCaptchaUpdate( configParams );
	}

	function checkConfig() {
		clearMessage();
		$submit.attr( 'disabled', true );

		const data = {
			action: HCaptchaGeneralObject.checkConfigAction,
			nonce: HCaptchaGeneralObject.nonce,
			mode: $mode.val(),
			siteKey: $siteKey.val(),
			secretKey: $secretKey.val(),
			'h-captcha-response': $( 'textarea[name="h-captcha-response"]' ).val(),
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		return $.post( {
			url: HCaptchaGeneralObject.ajaxUrl,
			data,
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );
					return;
				}

				showSuccessMessage( response.data );
				$submit.attr( 'disabled', false );
			} )
			.fail( function( response ) {
				showErrorMessage( response.statusText );
			} )
			.always( function() {
				hCaptchaUpdate( {} );
			} );
	}

	function checkCredentialsChange() {
		if ( $siteKey.val() === siteKeyInitVal && $secretKey.val() === secretKeyInitVal ) {
			clearMessage();
			$submit.attr( 'disabled', false );
		} else {
			showErrorMessage( HCaptchaGeneralObject.checkConfigNotice );
			$submit.attr( 'disabled', true );
		}
	}

	$( '#check_config' ).on( 'click', function( event ) {
		event.preventDefault();

		checkConfig();
	} );

	$siteKey.on( 'change', function( e ) {
		const sitekey = $( e.target ).val();
		hCaptchaUpdate( { sitekey } );
		checkCredentialsChange();
	} );

	$secretKey.on( 'change', function() {
		checkCredentialsChange();
	} );

	$theme.on( 'change', function( e ) {
		const theme = $( e.target ).val();
		hCaptchaUpdate( { theme } );
	} );

	$size.on( 'change', function( e ) {
		const $invisibleNotice = $( '#hcaptcha-invisible-notice' );
		const size = $( e.target ).val();

		if ( 'invisible' === size ) {
			$invisibleNotice.show();
		} else {
			$invisibleNotice.hide();
		}

		hCaptchaUpdate( { size } );
	} );

	$language.on( 'change', function( e ) {
		const hl = $( e.target ).val();
		hCaptchaUpdate( { hl } );
	} );

	$mode.on( 'change', function( e ) {
		const mode = $( e.target ).val();

		if ( ! modes.hasOwnProperty( mode ) ) {
			return;
		}

		if ( mode === HCaptchaGeneralObject.modeLive ) {
			$siteKey.attr( 'disabled', false );
			$secretKey.attr( 'disabled', false );
		} else {
			$siteKey.attr( 'disabled', true );
			$secretKey.attr( 'disabled', true );
		}

		const sitekey = modes[ mode ];
		hCaptchaUpdate( { sitekey } );
	} );

	$customThemes.on( 'change', function() {
		applyCustomThemes();
	} );

	$configParams.on( 'blur', function() {
		applyCustomThemes();
	} );

	$configParams.on( 'focus', function() {
		$configParams.css( 'background-color', 'unset' );
		$submit.attr( 'disabled', false );
	} );
};

window.hCaptchaGeneral = general;

jQuery( document ).ready( general );
