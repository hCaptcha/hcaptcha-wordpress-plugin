/* global jQuery, hCaptcha, hCaptchaSettingsBase, HCaptchaGeneralObject, kaggDialog */

/**
 * @param HCaptchaGeneralObject.ajaxUrl
 * @param HCaptchaGeneralObject.checkConfigAction
 * @param HCaptchaGeneralObject.checkConfigNonce
 * @param HCaptchaGeneralObject.toggleSectionAction
 * @param HCaptchaGeneralObject.toggleSectionNonce
 * @param HCaptchaGeneralObject.modeLive
 * @param HCaptchaGeneralObject.modeTestPublisher
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetected
 * @param HCaptchaGeneralObject.siteKey
 * @param HCaptchaGeneralObject.modeTestPublisherSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey
 * @param HCaptchaGeneralObject.checkConfigNotice
 * @param HCaptchaGeneralObject.checkingConfigMsg
 * @param HCaptchaGeneralObject.completeHCaptchaTitle
 * @param HCaptchaGeneralObject.completeHCaptchaContent
 */

/* eslint-disable no-console */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const general = function( $ ) {
	const headerBarSelector = '.hcaptcha-header-bar';
	const msgSelector = '#hcaptcha-message';
	let $message = $( msgSelector );
	const $form = $( 'form.hcaptcha-general' );
	const $siteKey = $( '[name="hcaptcha_settings[site_key]"]' );
	const $secretKey = $( '[name="hcaptcha_settings[secret_key]"]' );
	const $sampleHCaptcha = $( '#hcaptcha-options .h-captcha' );
	const $checkConfig = $( '#check_config' );
	const $resetNotifications = $( '#reset_notifications' );
	const $theme = $( '[name="hcaptcha_settings[theme]"]' );
	const $size = $( '[name="hcaptcha_settings[size]"]' );
	const $language = $( '[name="hcaptcha_settings[language]"]' );
	const $mode = $( '[name="hcaptcha_settings[mode]"]' );
	const $customThemes = $( '[name="hcaptcha_settings[custom_themes][]"]' );
	const $customProp = $( '.hcaptcha-general-custom-prop select' );
	const $customValue = $( '.hcaptcha-general-custom-value input' );
	const $configParams = $( '[name="hcaptcha_settings[config_params]"]' );
	const $enterpriseInputs = $( '.hcaptcha-section-enterprise + table input' );
	const $recaptchaCompatOff = $( '[name="hcaptcha_settings[recaptcha_compat_off][]"]' );
	const $submit = $form.find( '#submit' );
	const modes = {};
	let siteKeyInitVal = $siteKey.val();
	let secretKeyInitVal = $secretKey.val();
	let enterpriseInitValues = getEnterpriseValues();

	modes[ HCaptchaGeneralObject.modeLive ] = HCaptchaGeneralObject.siteKey;
	modes[ HCaptchaGeneralObject.modeTestPublisher ] = HCaptchaGeneralObject.modeTestPublisherSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser ] = HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseBotDetected ] = HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey;

	let credentialsChanged = false;
	let enterpriseSettingsChanged = false;

	let consoleLogs = [];

	interceptConsoleLogs();

	function interceptConsoleLogs() {
		consoleLogs = [];

		const systemLog = console.log;
		const systemWarn = console.warn;
		const systemInfo = console.info;
		const systemError = console.error;
		const systemClear = console.clear;

		// eslint-disable-next-line no-unused-vars
		console.log = function( message ) {
			consoleLogs.push( [ 'Console log:', arguments ] );
			systemLog.apply( console, arguments );
		};

		// eslint-disable-next-line no-unused-vars
		console.warn = function( message ) {
			consoleLogs.push( [ 'Console warn:', arguments ] );
			systemWarn.apply( console, arguments );
		};

		// eslint-disable-next-line no-unused-vars
		console.info = function( message ) {
			consoleLogs.push( [ 'Console info:', arguments ] );
			systemInfo.apply( console, arguments );
		};

		// eslint-disable-next-line no-unused-vars
		console.error = function( message ) {
			consoleLogs.push( [ 'Console error:', arguments ] );
			systemError.apply( console, arguments );
		};

		console.clear = function() {
			consoleLogs = [];
			systemClear();
		};
	}

	function getCleanConsoleLogs() {
		const ignore = [
			'recaptchacompat disabled',
			'Missing sitekey - https://docs.hcaptcha.com/configuration#javascript-api',
		];
		const logs = [];

		for ( let i = 0; i < consoleLogs.length; i++ ) {
			// Extract strings only (some JS functions push objects to console).
			const consoleLog = consoleLogs[ i ];
			const type = consoleLog[ 0 ];
			const args = consoleLog[ 1 ];
			const keys = Object.keys( args );
			const lines = [];

			for ( let a = 0; a < keys.length; a++ ) {
				const arg = args[ a ];

				if ( typeof arg === 'string' && ignore.indexOf( arg ) === -1 ) {
					lines.push( [ type, arg ].join( ' ' ) );
				}
			}

			logs.push( lines.join( '\n' ) );
		}

		consoleLogs = [];

		return logs.join( '\n' );
	}

	function getValues( $inputs ) {
		const values = {};

		$inputs.each( function() {
			const $input = $( this );
			const name = $input.attr( 'name' ).replace( /hcaptcha_settings\[(.+)]/, '$1' );
			values[ name ] = $input.val();
		} );

		return values;
	}

	function getEnterpriseValues() {
		return getValues( $enterpriseInputs );
	}

	function clearMessage() {
		$message.remove();
		// Concat below to avoid an inspection message.
		$( '<div id="hcaptcha-message">' + '</div>' ).insertAfter( headerBarSelector );
		$message = $( msgSelector );
	}

	function showMessage( message = '', msgClass = '' ) {
		message = message === undefined ? '' : String( message );

		const logs = getCleanConsoleLogs();

		message += '\n' + logs;
		message = message.trim();

		if ( ! message ) {
			return;
		}

		$message.removeClass();
		$message.addClass( msgClass + ' notice is-dismissible' );

		const messageLines = message.split( '\n' ).map( function( line ) {
			return `<p>${ line }</p>`;
		} );
		$message.html( messageLines.join( '' ) );

		$( document ).trigger( 'wp-updates-notice-added' );

		$( 'html, body' ).animate(
			{
				scrollTop: $message.offset().top - hCaptchaSettingsBase.getStickyHeight(),
			},
			1000
		);
	}

	function showSuccessMessage( message = '' ) {
		showMessage( message, 'notice-success' );
	}

	function showErrorMessage( message = '' ) {
		showMessage( message, 'notice-error' );
	}

	function hCaptchaUpdate( params = {} ) {
		const globalParams = Object.assign( {}, hCaptcha.getParams(), params );
		const isCustomThemeActive = $customThemes.prop( 'checked' );
		const isModeLive = 'live' === $mode.val();

		if ( isCustomThemeActive && isModeLive ) {
			$sampleHCaptcha.attr( 'data-theme', 'custom' );
		} else {
			$sampleHCaptcha.attr( 'data-theme', $theme.val() );
		}

		if (
			( isCustomThemeActive && typeof params.theme === 'object' ) ||
			( ! isCustomThemeActive && typeof params.theme !== 'object' )
		) {
			globalParams.theme = params.theme;
		} else {
			globalParams.theme = hCaptcha.getParams().theme;
		}

		hCaptcha.setParams( globalParams );

		$sampleHCaptcha.html( '' );

		for ( const key in params ) {
			if ( typeof params[ key ] === 'object' ) {
				continue;
			}

			$sampleHCaptcha.attr( `data-${ key }`, `${ params[ key ] }` );
		}

		hCaptcha.bindEvents();
	}

	function deepMerge( target, source ) {
		const isObject = ( obj ) => obj && typeof obj === 'object';

		if ( ! isObject( target ) || ! isObject( source ) ) {
			return source;
		}

		Object.keys( source ).forEach( ( key ) => {
			const targetValue = target[ key ];
			const sourceValue = source[ key ];

			if ( Array.isArray( targetValue ) && Array.isArray( sourceValue ) ) {
				target[ key ] = targetValue.concat( sourceValue );
			} else if ( isObject( targetValue ) && isObject( sourceValue ) ) {
				target[ key ] = deepMerge( Object.assign( {}, targetValue ), sourceValue );
			} else {
				target[ key ] = sourceValue;
			}
		} );

		return target;
	}

	function syncConfigParams( configParams, parentKey = '' ) {
		for ( const key in configParams ) {
			// Construct the full key path.
			const fullKey = parentKey ? `${ parentKey }--${ key }` : key;

			// If the value is an object, recursively print its keys.
			if ( typeof configParams[ key ] === 'object' && configParams[ key ] !== null ) {
				syncConfigParams( configParams[ key ], fullKey );
			} else {
				// Update the custom property selector.
				const value = configParams[ key ];
				const propKey = fullKey.replace( /theme--/g, '' );
				const newValue = `${ propKey }=${ value }`;
				const $prop = $customProp.find( `option[value*="${ propKey }="]` );

				if ( $prop.length === 1 ) {
					$prop.attr( 'value', newValue );
					if ( $prop.is( ':selected' ) ) {
						$customValue.val( value );
					}
				}
			}
		}
	}

	function applyCustomThemes( params = {} ) {
		let configParamsJson = $configParams.val().trim();
		let configParams;

		configParamsJson = configParamsJson ? configParamsJson : null;

		try {
			configParams = JSON.parse( configParamsJson );
		} catch ( ex ) {
			$configParams.css( 'background-color', '#ffabaf' );
			$submit.attr( 'disabled', true );
			showErrorMessage( 'Bad JSON!' );

			return;
		}

		configParams = deepMerge( configParams, params );

		$configParams.val( JSON.stringify( configParams, null, 2 ) );

		syncConfigParams( configParams );

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
			nonce: HCaptchaGeneralObject.checkConfigNonce,
			mode: $mode.val(),
			siteKey: $siteKey.val(),
			secretKey: $secretKey.val(),
			'h-captcha-response': $( 'textarea[name="h-captcha-response"]' ).val(),
			'hcaptcha-widget-id': $( 'input[name="hcaptcha-widget-id"]' ).val(),
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		return $.post( {
			url: HCaptchaGeneralObject.ajaxUrl,
			data,
			beforeSend: () => showSuccessMessage( HCaptchaGeneralObject.checkingConfigMsg ),
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );
					return;
				}

				siteKeyInitVal = $siteKey.val();
				secretKeyInitVal = $secretKey.val();
				enterpriseInitValues = getValues( $enterpriseInputs );
				enterpriseSettingsChanged = false;

				showSuccessMessage( response.data );
				$submit.attr( 'disabled', false );
			} )
			.fail(
				/**
				 * @param {Object} response
				 */
				function( response ) {
					showErrorMessage( response.statusText );
				}
			)
			.always( function() {
				hCaptchaUpdate();
			} );
	}

	function checkChangeCredentials() {
		if ( $siteKey.val() === siteKeyInitVal && $secretKey.val() === secretKeyInitVal ) {
			credentialsChanged = false;
			clearMessage();
			$submit.attr( 'disabled', false );
		} else if ( ! credentialsChanged ) {
			credentialsChanged = true;
			showErrorMessage( HCaptchaGeneralObject.checkConfigNotice );
			$submit.attr( 'disabled', true );
		}
	}

	function checkChangeEnterpriseSettings() {
		if ( JSON.stringify( getEnterpriseValues() ) === JSON.stringify( enterpriseInitValues ) ) {
			enterpriseSettingsChanged = false;
			clearMessage();
			$submit.attr( 'disabled', false );
		} else if ( ! enterpriseSettingsChanged ) {
			enterpriseSettingsChanged = true;
			showErrorMessage( HCaptchaGeneralObject.checkConfigNotice );
			$submit.attr( 'disabled', true );
		}
	}

	document.addEventListener( 'hCaptchaLoaded', function() {
		showErrorMessage();
	} );

	$checkConfig.on( 'click', function( event ) {
		event.preventDefault();

		// Check if hCaptcha is solved.
		if ( $( '.hcaptcha-general-sample-hcaptcha textarea[name="h-captcha-response"]' ).val() === '' ) {
			kaggDialog.confirm( {
				title: HCaptchaGeneralObject.completeHCaptchaTitle,
				content: HCaptchaGeneralObject.completeHCaptchaContent,
				type: 'info',
				buttons: {
					ok: {
						text: HCaptchaGeneralObject.OKBtnText,
					},
				},
				onAction: () => window.hCaptchaReset( document.querySelector( '.hcaptcha-general-sample-hcaptcha' ) ),
			} );

			return;
		}

		checkConfig();
	} );

	$siteKey.on( 'change', function( e ) {
		const sitekey = $( e.target ).val();

		hCaptchaUpdate( { sitekey } );
		checkChangeCredentials();
	} );

	$secretKey.on( 'change', function() {
		checkChangeCredentials();
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

	function forceHttps( host ) {
		host = host.replace( /(http|https):\/\//, '' );

		const url = new URL( 'https://' + host );

		return 'https://' + url.host;
	}

	function scriptUpdate() {
		const params = {
			onload: 'hCaptchaOnLoad',
			render: 'explicit',
		};

		if ( $recaptchaCompatOff.prop( 'checked' ) ) {
			params.recaptchacompat = 'off';
		}

		if ( $customThemes.prop( 'checked' ) ) {
			params.custom = 'true';
		}

		const enterpriseParams = {
			asset_host: 'assethost',
			endpoint: 'endpoint',
			host: 'host',
			image_host: 'imghost',
			report_api: 'reportapi',
			sentry: 'sentry',
		};

		const enterpriseValues = getEnterpriseValues();

		for ( const enterpriseParam in enterpriseParams ) {
			const value = enterpriseValues[ enterpriseParam ].trim();

			if ( value ) {
				params[ enterpriseParams[ enterpriseParam ] ] = encodeURIComponent( forceHttps( value ) );
			}
		}

		/**
		 * @param enterpriseValues.api_host
		 */
		let apiHost = enterpriseValues.api_host.trim();
		apiHost = apiHost ? apiHost : 'js.hcaptcha.com';
		apiHost = forceHttps( apiHost ) + '/1/api.js';

		const url = new URL( apiHost );

		for ( const name in params ) {
			url.searchParams.append( name, params[ name ] );
		}

		// Remove the existing API script.
		document.getElementById( 'hcaptcha-api' ).remove();
		// noinspection JSUnresolvedReference
		delete global.hcaptcha;

		// Remove sample hCaptcha.
		$sampleHCaptcha.html( '' );

		// Re-create the API script.
		const t = document.getElementsByTagName( 'head' )[ 0 ];
		const s = document.createElement( 'script' );

		s.type = 'text/javascript';
		s.id = 'hcaptcha-api';
		s.src = url.href;

		t.appendChild( s );
	}

	$enterpriseInputs.on( 'change', function() {
		scriptUpdate();
		checkChangeEnterpriseSettings();
	} );

	// Toggle a section.
	$( '.hcaptcha-general h3' ).on( 'click', function( event ) {
		const $h3 = $( event.currentTarget );

		$h3.toggleClass( 'closed' );

		const data = {
			action: HCaptchaGeneralObject.toggleSectionAction,
			nonce: HCaptchaGeneralObject.toggleSectionNonce,
			section: $h3.attr( 'class' ).replaceAll( /(hcaptcha-section-|closed)/g, '' ).trim(),
			status: ! $h3.hasClass( 'closed' ),
		};

		$.post( {
			url: HCaptchaGeneralObject.ajaxUrl,
			data,
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );
				}
			} )
			.fail(
				/**
				 * @param {Object} response
				 */
				function( response ) {
					showErrorMessage( response.statusText );
				}
			);
	} );

	// Prevent saving values of some form elements.
	$checkConfig.removeAttr( 'name' );
	$resetNotifications.removeAttr( 'name' );
	$customProp.removeAttr( 'name' );
	$customValue.removeAttr( 'name' );

	// Disable group keys.
	$customProp.find( 'option' ).each( function() {
		const $option = $( this );
		const value = $option.val().split( '=' )[ 1 ];

		if ( ! value ) {
			$option.attr( 'disabled', true );
		}
	} );

	// Clear custom value.
	$customValue.val( '' );

	// On Custom Prop change.
	$customProp.on( 'change', function() {
		const $selected = $( this ).find( 'option:selected' );
		const option = $selected.val().split( '=' );
		const key = option[ 0 ];
		const value = option[ 1 ];

		if ( key === 'palette--mode' ) {
			$customValue.attr( 'type', 'text' );
			$customValue.val( value );
		} else {
			$customValue.val( value );
			$customValue.attr( 'type', 'color' );
		}
	} );

	// On Custom Value change.
	$customValue.on( 'change', function( e ) {
		const value = $( e.target ).val();
		const $selected = $customProp.find( 'option:selected' );
		const option = $selected.val().split( '=' );
		let key = option[ 0 ];
		let params = value;

		$selected.val( key + '=' + value );

		key = 'theme--' + option[ 0 ];
		params = key.split( '--' ).reverse().reduce( function( acc, curr ) {
			const newObj = {};
			newObj[ curr ] = acc;

			return newObj;
		}, params );

		applyCustomThemes( params );
	} );
};

window.hCaptchaGeneral = general;

jQuery( document ).ready( general );
