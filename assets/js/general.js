/* global jQuery, lodash, hCaptcha, hCaptchaSettingsBase, HCaptchaGeneralObject, kaggDialog */

/**
 * @param HCaptchaGeneralObject.ajaxUrl
 * @param HCaptchaGeneralObject.activeState
 * @param HCaptchaGeneralObject.badJSONError
 * @param HCaptchaGeneralObject.checkConfigAction
 * @param HCaptchaGeneralObject.checkConfigNonce
 * @param HCaptchaGeneralObject.checkConfigNotice
 * @param HCaptchaGeneralObject.checkingConfigMsg
 * @param HCaptchaGeneralObject.completeHCaptchaContent
 * @param HCaptchaGeneralObject.completeHCaptchaTitle
 * @param HCaptchaGeneralObject.configMustBeObject
 * @param HCaptchaGeneralObject.focusState
 * @param HCaptchaGeneralObject.hexValue
 * @param HCaptchaGeneralObject.hoverState
 * @param HCaptchaGeneralObject.invalidJSON
 * @param HCaptchaGeneralObject.lastValidPreview
 * @param HCaptchaGeneralObject.mainState
 * @param HCaptchaGeneralObject.modeLive
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetected
 * @param HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser
 * @param HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey
 * @param HCaptchaGeneralObject.modeTestPublisher
 * @param HCaptchaGeneralObject.modeTestPublisherSiteKey
 * @param HCaptchaGeneralObject.reportState
 * @param HCaptchaGeneralObject.selectedState
 * @param HCaptchaGeneralObject.siteKey
 * @param HCaptchaGeneralObject.unsavedChanges
 * @param HCaptchaGeneralObject.validJSON
 */

/* eslint-disable no-console */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const general = function( $ ) {
	const adminNoticesSelector = '#hcaptcha-admin-notices';
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
	const $configParams = $( '[name="hcaptcha_settings[config_params]"]' );
	const $themeEditorLauncher = $( '.hcaptcha-theme-editor-launcher' );
	const $themeEditor = $( '.hcaptcha-theme-editor' );
	const themeEditorPreviewOnly = 'true' === $themeEditor.attr( 'data-theme-editor-preview-only' );
	const $themeEditorStatus = $( '.hcaptcha-theme-editor-dirty' );
	const $themeEditorJSONStatus = $themeEditor.find( '.hcaptcha-theme-editor-json-status' );
	const $themeEditorJSONError = $themeEditor.find( '.hcaptcha-theme-editor-json-error' );
	const $themeEditorPreviewNote = $themeEditor.find( '[data-theme-editor-preview-note]' );
	const $themeEditorPreviewDefaultContent = $themeEditorPreviewNote.contents().clone();
	const $themeEditorOpen = $themeEditorLauncher.find( '[data-theme-editor-open]' );
	const $enterpriseInputs = $( '.hcaptcha-section-enterprise + table input' );
	const $recaptchaCompatOff = $( '[name="hcaptcha_settings[recaptcha_compat_off][]"]' );
	const $submit = $form.find( '#submit' );
	const modes = {};
	let siteKeyInitVal = $siteKey.val();
	let secretKeyInitVal = $secretKey.val();
	let enterpriseInitValues = getEnterpriseValues();
	let defaultTheme = {};
	let lastValidConfigParams = {};
	let initialNormalizedConfigParams = null;
	let initialCustomThemes = false;
	let activeThemeGroup = 'palette';
	let pendingHCaptchaParams = null;
	let hCaptchaApiReady = false;

	modes[ HCaptchaGeneralObject.modeLive ] = HCaptchaGeneralObject.siteKey;
	modes[ HCaptchaGeneralObject.modeTestPublisher ] = HCaptchaGeneralObject.modeTestPublisherSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseSafeEndUser ] = HCaptchaGeneralObject.modeTestEnterpriseSafeEndUserSiteKey;
	modes[ HCaptchaGeneralObject.modeTestEnterpriseBotDetected ] = HCaptchaGeneralObject.modeTestEnterpriseBotDetectedSiteKey;

	let credentialsChanged = false;
	let enterpriseSettingsChanged = false;

	let consoleLogs = [];

	interceptConsoleLogs();
	initDisabledKeyInputs();

	function interceptConsoleLogs() {
		consoleLogs = [];

		const systemLog = console.log;
		const systemWarn = console.warn;
		const systemInfo = console.info;
		const systemError = console.error;
		const systemClear = console.clear;

		/* istanbul ignore next */
		// eslint-disable-next-line no-unused-vars
		console.log = function( message ) {
			consoleLogs.push( [ 'Console log:', arguments ] );
			systemLog.apply( console, arguments );
		};

		/* istanbul ignore next */
		// eslint-disable-next-line no-unused-vars
		console.warn = function( message ) {
			consoleLogs.push( [ 'Console warn:', arguments ] );
			systemWarn.apply( console, arguments );
		};

		/* istanbul ignore next */
		// eslint-disable-next-line no-unused-vars
		console.info = function( message ) {
			consoleLogs.push( [ 'Console info:', arguments ] );
			systemInfo.apply( console, arguments );
		};

		/* istanbul ignore next */
		// eslint-disable-next-line no-unused-vars
		console.error = function( message ) {
			consoleLogs.push( [ 'Console error:', arguments ] );
			systemError.apply( console, arguments );
		};

		/* istanbul ignore next */
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
			// Extract strings only (some JS functions push objects to the console).
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
		$( '<div id="hcaptcha-message">' + '</div>' ).insertAfter( adminNoticesSelector );
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

		$( 'html, body' ).stop().animate(
			{
				scrollTop: $message.offset().top - hCaptchaSettingsBase.getStickyHeight(),
			},
			1000,
		);
	}

	function showSuccessMessage( message = '' ) {
		showMessage( message, 'notice-success' );
	}

	function showErrorMessage( message = '' ) {
		showMessage( message, 'notice-error' );
	}

	function getHCaptchaThemeBackground( theme ) {
		if ( ! isObject( theme ) ) {
			return '';
		}

		const checkboxFill = getNestedValue( theme, [ 'component', 'checkbox', 'main', 'fill' ] );

		if ( typeof checkboxFill === 'string' && checkboxFill ) {
			return checkboxFill;
		}

		const mode = getNestedValue( theme, [ 'palette', 'mode' ] ) || theme.mode || 'light';
		const greyShade = 'dark' === mode ? '800' : '100';
		const paletteFill = getNestedValue( theme, [ 'palette', 'grey', greyShade ] );

		if ( typeof paletteFill === 'string' && paletteFill ) {
			return paletteFill;
		}

		return 'dark' === mode ? '#333333' : '#FAFAFA';
	}

	function hCaptchaUpdate( params = {} ) {
		if ( ! hCaptchaApiReady ) {
			pendingHCaptchaParams = Object.assign( {}, pendingHCaptchaParams || {}, params );

			return;
		}

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

		const themeBackground = getHCaptchaThemeBackground( globalParams.theme );

		if ( $sampleHCaptcha[ 0 ] ) {
			if ( themeBackground ) {
				$sampleHCaptcha[ 0 ].style.setProperty( '--hcaptcha-theme-editor-background', themeBackground );
			} else {
				$sampleHCaptcha[ 0 ].style.removeProperty( '--hcaptcha-theme-editor-background' );
			}
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

	function isObject( value ) {
		return value !== null && typeof value === 'object' && ! Array.isArray( value );
	}

	function deepMerge( target, source ) {
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

	function deepClone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	function themeValuesMatch( value, defaultValue ) {
		if ( typeof value === 'string' && typeof defaultValue === 'string' ) {
			return value.toLowerCase() === defaultValue.toLowerCase();
		}

		return value === defaultValue;
	}

	function pruneDefaultThemeValues( theme, defaults ) {
		Object.keys( theme ).forEach( ( key ) => {
			const value = theme[ key ];
			const defaultValue = defaults[ key ];

			if ( isObject( value ) && isObject( defaultValue ) ) {
				pruneDefaultThemeValues( value, defaultValue );

				if ( 0 === Object.keys( value ).length ) {
					delete theme[ key ];
				}

				return;
			}

			if ( themeValuesMatch( value, defaultValue ) ) {
				delete theme[ key ];
			}
		} );
	}

	function sortConfigValue( value ) {
		if ( Array.isArray( value ) ) {
			return value.map( sortConfigValue );
		}

		if ( ! isObject( value ) ) {
			return value;
		}

		return Object.keys( value ).sort().reduce( ( result, key ) => {
			result[ key ] = sortConfigValue( value[ key ] );

			return result;
		}, {} );
	}

	function normalizeConfigParams( configParams ) {
		const normalized = deepClone( configParams );

		if ( isObject( normalized.theme ) ) {
			const defaultMode = getNestedValue( defaultTheme, [ 'palette', 'mode' ] ) || 'light';
			const mode = getNestedValue( normalized.theme, [ 'palette', 'mode' ] ) || defaultMode;

			pruneDefaultThemeValues( normalized.theme, getDefaultThemeForMode( mode ) );

			if ( mode !== defaultMode ) {
				setNestedValue( normalized.theme, [ 'palette', 'mode' ], mode );
			}

			if ( 0 === Object.keys( normalized.theme ).length ) {
				delete normalized.theme;
			}
		}

		return sortConfigValue( normalized );
	}

	function getDefaultThemeForMode( mode ) {
		const theme = deepClone( defaultTheme );

		if ( 'dark' !== mode ) {
			return theme;
		}

		return deepMerge( theme, {
			palette: {
				mode: 'dark',
				text: {
					body: '#F5F5F5',
					heading: '#F5F5F5',
				},
			},
			component: {
				checkbox: {
					main: {
						border: '#F5F5F5',
						fill: '#333333',
					},
					hover: {
						fill: '#555555',
					},
				},
				challenge: {
					main: {
						border: '#555555',
						fill: '#333333',
					},
					hover: {
						fill: '#555555',
					},
				},
				modal: {
					main: {
						border: '#555555',
						fill: '#333333',
					},
					hover: {
						fill: '#555555',
					},
				},
				breadcrumb: {
					main: {
						fill: '#555555',
					},
				},
				button: {
					main: {
						fill: '#555555',
						icon: '#F5F5F5',
						text: '#F5F5F5',
					},
					hover: {
						fill: '#919191',
					},
				},
				list: {
					main: {
						border: '#555555',
						fill: '#333333',
					},
				},
				listItem: {
					main: {
						fill: '#333333',
						line: '#555555',
						text: '#F5F5F5',
					},
					hover: {
						fill: '#555555',
					},
					selected: {
						fill: '#555555',
					},
				},
				input: {
					main: {
						border: '#919191',
						fill: '#333333',
					},
					focus: {
						border: '#F5F5F5',
						fill: '#555555',
					},
				},
				radio: {
					main: {
						border: '#BFBFBF',
						check: '#555555',
						file: '#555555',
					},
				},
				task: {
					main: {
						fill: '#555555',
					},
				},
				slider: {
					main: {
						bar: '#919191',
					},
				},
			},
		} );
	}

	function parseConfigParams() {
		const configParamsJSON = $configParams.val().trim();
		const configParams = configParamsJSON ? JSON.parse( configParamsJSON ) : {};

		if ( ! isObject( configParams ) ) {
			throw new TypeError( HCaptchaGeneralObject.configMustBeObject );
		}

		return configParams;
	}

	function getNestedValue( object, path ) {
		return path.reduce( ( value, key ) => {
			return isObject( value ) && Object.prototype.hasOwnProperty.call( value, key )
				? value[ key ]
				: undefined;
		}, object );
	}

	function setNestedValue( object, path, value ) {
		let target = object;

		path.forEach( ( key, index ) => {
			if ( index === path.length - 1 ) {
				target[ key ] = value;
				return;
			}

			if ( ! isObject( target[ key ] ) ) {
				target[ key ] = {};
			}

			target = target[ key ];
		} );
	}

	function humanizeThemeKey( key ) {
		const words = String( key )
			.replace( /([a-z0-9])([A-Z])/g, '$1 $2' )
			.replace( /[_-]+/g, ' ' );

		return words.charAt( 0 ).toUpperCase() + words.slice( 1 );
	}

	function formatThemeFieldLabel( path ) {
		const stateLabels = {
			active: HCaptchaGeneralObject.activeState,
			focus: HCaptchaGeneralObject.focusState,
			hover: HCaptchaGeneralObject.hoverState,
			main: HCaptchaGeneralObject.mainState,
			report: HCaptchaGeneralObject.reportState,
			selected: HCaptchaGeneralObject.selectedState,
		};
		const parts = path.map( ( key ) => stateLabels[ key ] || humanizeThemeKey( key ) );

		return parts.join( ' - ' );
	}

	function flattenThemeLeaves( object, path = [], result = [] ) {
		Object.keys( object || {} ).forEach( ( key ) => {
			const value = object[ key ];
			const valuePath = path.concat( key );

			if ( isObject( value ) ) {
				flattenThemeLeaves( value, valuePath, result );
			} else {
				result.push( { path: valuePath, value } );
			}
		} );

		return result;
	}

	function getActiveThemeGroupPath() {
		return activeThemeGroup.split( '--' );
	}

	function getMergedTheme() {
		const customTheme = isObject( lastValidConfigParams.theme )
			? lastValidConfigParams.theme
			: {};
		const mode = getNestedValue( customTheme, [ 'palette', 'mode' ] ) || 'light';

		return deepMerge( getDefaultThemeForMode( mode ), deepClone( customTheme ) );
	}

	function getThemePreviewValue( theme, path, fallback ) {
		const value = getNestedValue( theme, path );

		return typeof value === 'string' ? value : fallback;
	}

	function renderThemePreview() {
		const $stage = $themeEditor.find( '[data-theme-editor-preview-stage]' );

		if ( ! $stage.length ) {
			return;
		}

		const customTheme = isObject( lastValidConfigParams.theme )
			? lastValidConfigParams.theme
			: {};
		const configuredMode = getNestedValue( customTheme, [ 'palette', 'mode' ] );
		const mode = configuredMode || 'light';
		const logoMode = configuredMode ? mode : 'dark';
		const theme = getMergedTheme();
		const isDark = 'dark' === mode;
		const widgetFillFallback = getThemePreviewValue(
			customTheme,
			[ 'palette', 'grey', isDark ? '800' : '100' ],
			isDark ? '#333333' : '#FAFAFA',
		);
		const widgetBorderFallback = getThemePreviewValue(
			customTheme,
			[ 'palette', 'grey', isDark ? '200' : '300' ],
			isDark ? '#F5F5F5' : '#E0E0E0',
		);
		const previewValues = {
			'--hcap-palette-heading': getThemePreviewValue( theme, [ 'palette', 'text', 'heading' ], '#555555' ),
			'--hcap-palette-body': getThemePreviewValue( theme, [ 'palette', 'text', 'body' ], '#555555' ),
			'--hcap-palette-primary': getThemePreviewValue( theme, [ 'palette', 'primary', 'main' ], '#00838F' ),
			'--hcap-checkbox-fill': getThemePreviewValue( customTheme, [ 'component', 'checkbox', 'main', 'fill' ], widgetFillFallback ),
			'--hcap-checkbox-border': getThemePreviewValue( customTheme, [ 'component', 'checkbox', 'main', 'border' ], widgetBorderFallback ),
			'--hcap-widget-checkbox-fill': getThemePreviewValue( customTheme, [ 'palette', 'grey', '100' ], '#FAFAFA' ),
			'--hcap-widget-checkbox-border': getThemePreviewValue(
				customTheme,
				[ 'palette', 'grey', isDark ? '200' : '700' ],
				isDark ? '#F5F5F5' : '#555555',
			),
			'--hcap-widget-label': getThemePreviewValue( customTheme, [ 'palette', 'text', 'body' ], '#333333' ),
			'--hcap-widget-brand': getThemePreviewValue(
				customTheme,
				[ 'palette', 'grey', isDark ? '200' : '700' ],
				isDark ? '#F5F5F5' : '#555555',
			),
			'--hcap-challenge-fill': getThemePreviewValue( theme, [ 'component', 'challenge', 'main', 'fill' ], '#FAFAFA' ),
			'--hcap-challenge-border': getThemePreviewValue( theme, [ 'component', 'challenge', 'main', 'border' ], '#E0E0E0' ),
			'--hcap-modal-fill': getThemePreviewValue( theme, [ 'component', 'modal', 'main', 'fill' ], '#FFFFFF' ),
			'--hcap-modal-border': getThemePreviewValue( theme, [ 'component', 'modal', 'main', 'border' ], '#E0E0E0' ),
			'--hcap-breadcrumb-fill': getThemePreviewValue( theme, [ 'component', 'breadcrumb', 'main', 'fill' ], '#F5F5F5' ),
			'--hcap-breadcrumb-active': getThemePreviewValue( theme, [ 'component', 'breadcrumb', 'active', 'fill' ], '#00838F' ),
			'--hcap-button-fill': getThemePreviewValue( theme, [ 'component', 'button', 'main', 'fill' ], '#FFFFFF' ),
			'--hcap-button-icon': getThemePreviewValue( theme, [ 'component', 'button', 'main', 'icon' ], '#555555' ),
			'--hcap-list-fill': getThemePreviewValue( theme, [ 'component', 'list', 'main', 'fill' ], '#FFFFFF' ),
			'--hcap-list-border': getThemePreviewValue( theme, [ 'component', 'list', 'main', 'border' ], '#D7D7D7' ),
			'--hcap-list-item-fill': getThemePreviewValue( theme, [ 'component', 'listItem', 'main', 'fill' ], '#FFFFFF' ),
			'--hcap-list-item-line': getThemePreviewValue( theme, [ 'component', 'listItem', 'main', 'line' ], '#F5F5F5' ),
			'--hcap-list-item-text': getThemePreviewValue( theme, [ 'component', 'listItem', 'main', 'text' ], '#555555' ),
			'--hcap-list-item-selected': getThemePreviewValue( theme, [ 'component', 'listItem', 'selected', 'fill' ], '#E0E0E0' ),
			'--hcap-radio-file': getThemePreviewValue( theme, [ 'component', 'radio', 'main', 'file' ], '#F5F5F5' ),
			'--hcap-radio-border': getThemePreviewValue( theme, [ 'component', 'radio', 'main', 'border' ], '#919191' ),
			'--hcap-radio-check': getThemePreviewValue( theme, [ 'component', 'radio', 'selected', 'check' ], '#00838F' ),
			'--hcap-task-fill': getThemePreviewValue( theme, [ 'component', 'task', 'main', 'fill' ], '#F5F5F5' ),
			'--hcap-task-selected': getThemePreviewValue( theme, [ 'component', 'task', 'selected', 'border' ], '#00838F' ),
			'--hcap-task-report': getThemePreviewValue( theme, [ 'component', 'task', 'report', 'border' ], '#EB5757' ),
			'--hcap-prompt-fill': getThemePreviewValue( theme, [ 'component', 'prompt', 'main', 'fill' ], '#00838F' ),
			'--hcap-prompt-border': getThemePreviewValue( theme, [ 'component', 'prompt', 'main', 'border' ], '#00838F' ),
			'--hcap-prompt-text': getThemePreviewValue( theme, [ 'component', 'prompt', 'main', 'text' ], '#FFFFFF' ),
			'--hcap-skip-fill': getThemePreviewValue( theme, [ 'component', 'skipButton', 'main', 'fill' ], '#919191' ),
			'--hcap-skip-border': getThemePreviewValue( theme, [ 'component', 'skipButton', 'main', 'border' ], '#919191' ),
			'--hcap-skip-text': getThemePreviewValue( theme, [ 'component', 'skipButton', 'main', 'text' ], '#FFFFFF' ),
			'--hcap-verify-fill': getThemePreviewValue( theme, [ 'component', 'verifyButton', 'main', 'fill' ], '#00838F' ),
			'--hcap-verify-border': getThemePreviewValue( theme, [ 'component', 'verifyButton', 'main', 'border' ], '#00838F' ),
			'--hcap-verify-text': getThemePreviewValue( theme, [ 'component', 'verifyButton', 'main', 'text' ], '#FFFFFF' ),
			'--hcap-slider-bar': getThemePreviewValue( theme, [ 'component', 'slider', 'main', 'bar' ], '#C4C4C4' ),
			'--hcap-slider-handle': getThemePreviewValue( theme, [ 'component', 'slider', 'main', 'handle' ], '#0F8390' ),
		};

		$stage
			.attr( 'data-theme-preview-mode', mode )
			.attr( 'data-theme-preview-logo-mode', logoMode )
			.css( previewValues );
		$stage.find( '[data-theme-mock-logo]' ).each( function() {
			const $logo = $( this );

			$logo.prop( 'hidden', logoMode !== $logo.attr( 'data-theme-mock-logo' ) );
		} );
	}

	function setThemePreviewView( view ) {
		$themeEditor.find( '[data-theme-preview-view]' ).each( function() {
			const $button = $( this );
			const isActive = view === $button.attr( 'data-theme-preview-view' );

			$button.toggleClass( 'is-active', isActive );
			$button.attr( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		$themeEditor.find( '[data-theme-preview-pane]' ).each( function() {
			const $pane = $( this );
			const isActive = view === $pane.attr( 'data-theme-preview-pane' );

			$pane.prop( 'hidden', ! isActive );
		} );
	}

	function renderThemeNavigation() {
		const $componentNav = $themeEditor.find( '[data-theme-editor-component-nav]' );
		const components = isObject( defaultTheme.component ) ? defaultTheme.component : {};

		$componentNav.empty();

		Object.keys( components ).forEach( ( component ) => {
			const $button = $( '<button>', {
				class: 'hcaptcha-theme-editor-nav-button',
				text: humanizeThemeKey( component ),
				type: 'button',
			} ).attr( 'data-theme-group', `component--${ component }` );

			$componentNav.append( $button );
		} );
	}

	function createThemeColorRow( leaf, groupPath ) {
		const fullPath = groupPath.concat( leaf.path );
		const fieldPath = `theme.${ fullPath.join( '.' ) }`;
		const value = String( leaf.value );
		const colorValue = /^#[0-9a-f]{6}$/i.test( value ) ? value : '#000000';
		const $row = $( '<div>', { class: 'hcaptcha-theme-editor-color-row' } );
		const $label = $( '<div>' );
		const $controls = $( '<div>', { class: 'hcaptcha-theme-editor-color-controls' } );
		const $color = $( '<input>', {
			class: 'hcaptcha-theme-editor-color',
			type: 'color',
			value: colorValue,
		} ).attr( {
			'aria-label': formatThemeFieldLabel( leaf.path ),
			'data-theme-color-path': fullPath.join( '--' ),
		} );
		const $hex = $( '<input>', {
			class: 'hcaptcha-theme-editor-hex',
			type: 'text',
			value: value.toUpperCase(),
		} ).attr( {
			'aria-label': `${ formatThemeFieldLabel( leaf.path ) } ${ HCaptchaGeneralObject.hexValue }`,
			'data-theme-hex-path': fullPath.join( '--' ),
		} );

		$( '<span>', {
			class: 'hcaptcha-theme-editor-color-label',
			text: formatThemeFieldLabel( leaf.path ),
		} ).appendTo( $label );
		$( '<code>', {
			class: 'hcaptcha-theme-editor-color-path',
			text: fieldPath,
		} ).appendTo( $label );

		$controls.append( $color, $hex );
		$row.append( $label, $controls );

		return $row;
	}

	function renderThemeGroup() {
		if ( ! $themeEditor.length ) {
			return;
		}

		const mergedTheme = getMergedTheme();
		const groupPath = getActiveThemeGroupPath();
		const group = getNestedValue( mergedTheme, groupPath ) || {};
		const groupTitle = humanizeThemeKey( groupPath[ groupPath.length - 1 ] );
		const $fields = $themeEditor.find( '[data-theme-editor-fields]' );
		const leaves = flattenThemeLeaves( group ).filter( ( leaf ) => {
			return ! ( 'palette' === activeThemeGroup && 'mode' === leaf.path.join( '--' ) );
		} );

		$themeEditor.find( '[data-theme-editor-group-title]' ).text( groupTitle );
		$themeEditor.find( '[data-theme-editor-group-description]' ).text( `theme.${ groupPath.join( '.' ) }` );
		$themeEditor.find( '[data-theme-editor-mode-field]' ).toggle( 'palette' === activeThemeGroup );
		$themeEditor.find( '[data-theme-editor-mode]' ).val( getNestedValue( mergedTheme, [ 'palette', 'mode' ] ) || 'light' );
		$fields.empty();

		leaves.forEach( ( leaf ) => {
			$fields.append( createThemeColorRow( leaf, groupPath ) );
		} );
	}

	function themeEditorHasChanges() {
		if ( initialCustomThemes !== $customThemes.prop( 'checked' ) || initialNormalizedConfigParams === null ) {
			return true;
		}

		try {
			return JSON.stringify( normalizeConfigParams( parseConfigParams() ) ) !==
				JSON.stringify( initialNormalizedConfigParams );
		} catch {
			return true;
		}
	}

	function setThemeEditorDirty( dirty ) {
		const isDirty = ! themeEditorPreviewOnly && dirty && themeEditorHasChanges();

		$themeEditorStatus
			.text( isDirty ? HCaptchaGeneralObject.unsavedChanges : '' )
			.toggleClass( 'is-dirty', isDirty )
			.prop( 'hidden', ! isDirty );
	}

	function setConfigValidationState( valid, error = '' ) {
		$configParams.attr( 'aria-invalid', valid ? 'false' : 'true' );
		$themeEditorJSONStatus
			.toggleClass( 'is-valid', valid )
			.toggleClass( 'is-invalid', ! valid )
			.text( valid ? HCaptchaGeneralObject.validJSON : HCaptchaGeneralObject.invalidJSON );
		$themeEditorJSONError.text( error );

		if ( valid ) {
			$themeEditorPreviewNote.empty().append( $themeEditorPreviewDefaultContent.clone() );
		} else {
			$themeEditorPreviewNote.text( HCaptchaGeneralObject.lastValidPreview );
		}
		$submit.prop( 'disabled', ! valid && ! themeEditorPreviewOnly && $customThemes.prop( 'checked' ) );
	}

	function getBasePreviewParams() {
		return {
			hl: $language.val(),
			sitekey: $siteKey.val(),
			size: $size.val(),
			theme: $theme.val(),
		};
	}

	function applyValidatedConfig( configParams, options = {} ) {
		const settings = Object.assign(
			{
				dirty: false,
				format: false,
				render: true,
			},
			options,
		);

		lastValidConfigParams = deepClone( configParams );

		if ( settings.format ) {
			$configParams.val( JSON.stringify( configParams, null, 2 ) );
		}

		setConfigValidationState( true );
		setThemeEditorDirty( settings.dirty );

		if ( settings.render ) {
			renderThemeGroup();
		}

		renderThemePreview();

		if ( themeEditorPreviewOnly ) {
			return;
		}

		let previewParams = getBasePreviewParams();

		if ( $customThemes.prop( 'checked' ) ) {
			previewParams = deepClone( configParams );

			if ( ! isObject( previewParams.theme ) ) {
				previewParams.theme = {};
			}
		}

		hCaptchaUpdate( previewParams );
	}

	function applyCustomThemes( params = {}, options = {} ) {
		let configParams;

		try {
			configParams = parseConfigParams();
			configParams = deepMerge( configParams, params );
		} catch ( error ) {
			setConfigValidationState( false, `${ HCaptchaGeneralObject.badJSONError }: ${ error.message }` );

			return false;
		}

		applyValidatedConfig( configParams, options );

		return true;
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
				credentialsChanged = false;

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
				},
			)
			.always( function() {
				hCaptchaUpdate();
			} );
	}

	function checkChangeCredentials() {
		if ( $siteKey.val() === siteKeyInitVal && $secretKey.val() === secretKeyInitVal ) {
			credentialsChanged = false;
			clearMessage();
		} else if ( ! credentialsChanged ) {
			credentialsChanged = true;
			showErrorMessage( HCaptchaGeneralObject.checkConfigNotice );
		}
	}

	function checkChangeEnterpriseSettings() {
		if ( JSON.stringify( getEnterpriseValues() ) === JSON.stringify( enterpriseInitValues ) ) {
			enterpriseSettingsChanged = false;
			clearMessage();
		} else if ( ! enterpriseSettingsChanged ) {
			enterpriseSettingsChanged = true;
			showErrorMessage( HCaptchaGeneralObject.checkConfigNotice );
		}
	}

	/**
	 * Set readonly and blocked state for key inputs.
	 *
	 * @param {jQuery}  $el Element to set readonly and blocked state for.
	 * @param {boolean} on  Whether to set readonly and blocked state.
	 */
	function setReadonlyBlocked( $el, on ) {
		if ( on ) {
			$el.prop( 'disabled', false )
				.attr( 'readonly', true )
				.attr( 'aria-disabled', 'true' )
				.on( 'keydown.hcaptchaHelper paste.hcaptchaHelper drop.hcaptchaHelper', ( e ) => e.preventDefault() );
		} else {
			$el.removeAttr( 'readonly' )
				.removeAttr( 'aria-disabled' )
				.off( 'keydown.hcaptchaHelper paste.hcaptchaHelper drop.hcaptchaHelper' );
		}
	}

	/**
	 * Show helper for disabled key inputs on click and hide it on blur.
	 */
	function initDisabledKeyInputs() {
		syncKeysWithMode();

		const $keys = $( '#site_key, #secret_key' );

		$keys
			.on( 'click.hcaptchaHelper', function() {
				const $input = $( this );

				// Show helper only when the input is readonly.
				if ( ! $input.is( '[readonly]' ) && $input.attr( 'aria-disabled' ) !== 'true' ) {
					return;
				}

				// Find a related helper within the same container.
				const $container = $input.parent();
				const $helper = $container.find( 'span.helper' ).first();
				const $helperContent = $container.find( 'span.helper-content' ).first();

				$helper.css( 'display', 'block' );
				$helperContent.css( 'display', 'block' );

				hCaptchaSettingsBase.highlightElement( $mode[ 0 ] );

				const onDoc = () => {
					$helper.css( 'display', 'none' );
					$helperContent.css( 'display', 'none' );
					$( document ).off( 'mousedown.hcaptchaHelper', onDoc );
				};

				$( document ).on( 'mousedown.hcaptchaHelper', onDoc );
			} )
			.on( 'keydown.hcaptchaHelper paste.hcaptchaHelper drop.hcaptchaHelper', ( e ) => {
				// Block paste, drop, and keydown events.
				const $input = $( e.currentTarget );

				if ( $input.is( '[readonly]' ) || $input.attr( 'aria-disabled' ) === 'true' ) {
					e.preventDefault();
				}
			} );
	}

	/**
	 * Sync keys with mode.
	 *
	 * @param {jQuery.Event|undefined} e Event object.
	 */
	function syncKeysWithMode( e = undefined ) {
		const mode = $mode.val();

		if ( ! modes.hasOwnProperty( mode ) ) {
			return;
		}

		if ( mode === HCaptchaGeneralObject.modeLive ) {
			setReadonlyBlocked( $siteKey, false );
			setReadonlyBlocked( $secretKey, false );
		} else {
			setReadonlyBlocked( $siteKey, true );
			setReadonlyBlocked( $secretKey, true );
		}

		// If the event is not triggered by the mode selector, skip the rest of the function.
		if ( e === undefined ) {
			return;
		}

		const sitekey = modes[ mode ];

		hCaptchaUpdate( { sitekey } );
	}

	// Test hook: expose internals for isolated unit tests
	// noinspection JSUnresolvedReference
	if ( typeof jest !== 'undefined' ) {
		// Expose only read-only references; no state is mutated here beyond normal function effects
		window.__generalTest = {
			getCleanConsoleLogs,
			interceptConsoleLogs,
			showMessage,
			showErrorMessage,
			hCaptchaUpdate,
			deepMerge,
		};
	}

	document.addEventListener( 'hCaptchaLoaded', function() {
		hCaptchaApiReady = true;
		showErrorMessage();

		if ( pendingHCaptchaParams !== null ) {
			const params = pendingHCaptchaParams;

			pendingHCaptchaParams = null;
			hCaptchaUpdate( params );
		}
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
				onAction: () => window.hCaptchaBindEvents(),
			} );

			return;
		}

		checkConfig();
	} );

	$submit.on( 'click', function( event ) {
		if ( ! ( credentialsChanged || enterpriseSettingsChanged ) ) {
			return;
		}

		event.preventDefault();

		const viewportHeight = $( window ).height();
		const buttonHeight = $checkConfig.outerHeight();
		const buttonTop = $checkConfig.offset().top;

		$( 'html, body' ).stop().animate(
			{
				scrollTop: buttonTop - viewportHeight + buttonHeight,
			},
			1000,
			function() {
				$checkConfig[ 0 ].click();
			},
		);
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

	$mode.on( 'change', syncKeysWithMode );

	function expandKeysSection() {
		const $keysSection = $( '.hcaptcha-section-keys' );

		if ( $keysSection.hasClass( 'closed' ) ) {
			$keysSection.trigger( 'click' );
			$keysSection.removeClass( 'closed' );
		}
	}

	function clampThemeEditorPosition() {
		if ( ! $themeEditor.is( ':visible' ) || window.innerWidth <= 760 ) {
			return;
		}

		const editor = $themeEditor[ 0 ];
		const bounds = editor.getBoundingClientRect();
		const left = Math.max( 8, Math.min( bounds.left, window.innerWidth - bounds.width - 8 ) );
		const top = Math.max( 40, Math.min( bounds.top, window.innerHeight - bounds.height - 8 ) );

		$themeEditor.css( {
			left: `${ left }px`,
			right: 'auto',
			top: `${ top }px`,
		} );
	}

	function openThemeEditor() {
		if ( $themeEditorOpen.prop( 'disabled' ) ) {
			return;
		}

		expandKeysSection();
		$themeEditor.prop( 'hidden', false );
		$themeEditorOpen.attr( 'aria-expanded', 'true' );
		clampThemeEditorPosition();
		$themeEditor.find( '[data-theme-editor-close]' ).trigger( 'focus' );
	}

	function closeThemeEditor() {
		const wasOpen = ! $themeEditor.prop( 'hidden' );

		$themeEditor.prop( 'hidden', true );
		$themeEditorOpen.attr( 'aria-expanded', 'false' );
		$( document ).off( '.hcaptchaThemeEditorDrag' );

		if ( wasOpen ) {
			$themeEditorOpen.trigger( 'focus' );
		}
	}

	function showRealHCaptcha() {
		expandKeysSection();

		const $sample = $( '.hcaptcha-general-sample-hcaptcha' );

		if ( ! $sample.length ) {
			return;
		}

		$( 'html, body' ).stop().animate(
			{
				scrollTop: $sample.offset().top - hCaptchaSettingsBase.getStickyHeight() - 20,
			},
			300,
		);
	}

	function initThemeEditorDragging() {
		$themeEditor.on( 'pointerdown', '[data-theme-editor-drag-handle]', function( event ) {
			if ( $( event.target ).closest( 'button' ).length || window.innerWidth <= 900 ) {
				return;
			}

			event.preventDefault();

			const bounds = $themeEditor[ 0 ].getBoundingClientRect();
			const startX = event.clientX;
			const startY = event.clientY;

			$themeEditor.css( {
				left: `${ bounds.left }px`,
				right: 'auto',
				top: `${ bounds.top }px`,
			} );

			$( document )
				.on( 'pointermove.hcaptchaThemeEditorDrag', function( moveEvent ) {
					const maxLeft = Math.max( 8, window.innerWidth - $themeEditor.outerWidth() - 8 );
					const maxTop = Math.max( 40, window.innerHeight - $themeEditor.outerHeight() - 8 );
					const left = Math.max( 8, Math.min( bounds.left + moveEvent.clientX - startX, maxLeft ) );
					const top = Math.max( 40, Math.min( bounds.top + moveEvent.clientY - startY, maxTop ) );

					$themeEditor.css( { left: `${ left }px`, top: `${ top }px` } );
				} )
				.one( 'pointerup.hcaptchaThemeEditorDrag pointercancel.hcaptchaThemeEditorDrag', function() {
					$( document ).off( '.hcaptchaThemeEditorDrag' );
					clampThemeEditorPosition();
				} );
		} );

		$( window ).on( 'resize.hcaptchaThemeEditor', lodash.debounce( function() {
			if ( window.innerWidth <= 760 ) {
				$themeEditor.css( { left: '', right: '', top: '' } );
				return;
			}

			clampThemeEditorPosition();
		}, 100 ) );
	}

	function initThemeEditor() {
		if ( ! $themeEditor.length ) {
			return;
		}

		if ( themeEditorPreviewOnly ) {
			const configParamsName = $configParams.attr( 'name' );

			if ( configParamsName && ! $themeEditorLauncher.find( '[data-theme-editor-original-config]' ).length ) {
				$( '<input>', {
					name: configParamsName,
					type: 'hidden',
					value: $configParams.val(),
				} )
					.attr( 'data-theme-editor-original-config', '' )
					.appendTo( $themeEditorLauncher );
				$configParams.removeAttr( 'name' );
			}
		}

		$themeEditor.appendTo( $form.length ? $form : document.body );

		try {
			defaultTheme = JSON.parse( $themeEditor.attr( 'data-default-theme' ) || '{}' );
		} catch {
			defaultTheme = {};
		}

		renderThemeNavigation();
		initialCustomThemes = $customThemes.prop( 'checked' );

		try {
			lastValidConfigParams = parseConfigParams();
			initialNormalizedConfigParams = normalizeConfigParams( lastValidConfigParams );
			setConfigValidationState( true );
		} catch ( error ) {
			lastValidConfigParams = {};
			initialNormalizedConfigParams = null;
			setConfigValidationState( false, `${ HCaptchaGeneralObject.badJSONError }: ${ error.message }` );
		}

		setThemeEditorDirty( false );
		renderThemeGroup();
		renderThemePreview();
		initThemeEditorDragging();
	}

	function toggleCustomThemeFields( dirty = false, updateHCaptcha = true ) {
		const isOn = $customThemes.prop( 'checked' );
		const editorEnabled = themeEditorPreviewOnly || isOn;
		const $editorControls = $themeEditor.find( 'button, input, select, textarea' );

		$editorControls.prop( 'disabled', ! editorEnabled );
		$themeEditorOpen.prop( 'disabled', ! editorEnabled );
		$themeEditorLauncher.toggleClass( 'is-disabled', ! editorEnabled );
		$themeEditor.attr( 'aria-disabled', editorEnabled ? 'false' : 'true' );

		if ( themeEditorPreviewOnly ) {
			setThemeEditorDirty( false );

			return;
		}

		if ( isOn ) {
			if ( updateHCaptcha ) {
				applyCustomThemes( {}, { dirty, render: true } );
			}
		} else {
			if ( ! $themeEditor.prop( 'hidden' ) ) {
				closeThemeEditor();
			}
			setConfigValidationState( true );
			setThemeEditorDirty( dirty );

			if ( updateHCaptcha ) {
				hCaptchaUpdate( getBasePreviewParams() );
			}
		}
	}

	initThemeEditor();
	toggleCustomThemeFields( false, false );

	$customThemes.on( 'change', function() {
		toggleCustomThemeFields( true );
	} );

	$themeEditorOpen.on( 'click', openThemeEditor );
	$themeEditor.on( 'click', '[data-theme-editor-close]', closeThemeEditor );
	$themeEditor.on( 'click', '[data-theme-editor-show-sample]', showRealHCaptcha );

	$( document ).on( 'keydown.hcaptchaThemeEditor', function( event ) {
		if ( 'Escape' === event.key && ! $themeEditor.prop( 'hidden' ) ) {
			closeThemeEditor();
		}
	} );

	$configParams.on( 'input', lodash.debounce( function() {
		setThemeEditorDirty( true );
		applyCustomThemes( {}, { dirty: true, render: true } );
	}, 300 ) );

	$themeEditor.on( 'click', '[data-theme-editor-tab]', function() {
		const $tab = $( this );
		const tabName = $tab.attr( 'data-theme-editor-tab' );

		$themeEditor.find( '[data-theme-editor-tab]' ).each( function() {
			const $candidate = $( this );
			const isActive = $candidate.is( $tab );

			$candidate.toggleClass( 'is-active', isActive );
			$candidate.attr( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		$themeEditor.find( '[data-theme-editor-pane]' ).each( function() {
			const $pane = $( this );
			const isActive = tabName === $pane.attr( 'data-theme-editor-pane' );

			$pane.toggleClass( 'is-active', isActive );
			$pane.prop( 'hidden', ! isActive );
		} );
	} );

	$themeEditor.on( 'click', '[data-theme-group]', function() {
		const $button = $( this );

		activeThemeGroup = $button.attr( 'data-theme-group' );
		$themeEditor.find( '[data-theme-group]' ).removeClass( 'is-active' );
		$button.addClass( 'is-active' );
		renderThemeGroup();

		if ( 'palette' !== activeThemeGroup ) {
			setThemePreviewView( 'component--checkbox' === activeThemeGroup ? 'widget' : 'challenge' );
		}
	} );

	function updateVisualThemeValue( path, value ) {
		const configParams = deepClone( lastValidConfigParams );

		if ( ! isObject( configParams.theme ) ) {
			configParams.theme = {};
		}

		setNestedValue( configParams.theme, path, value );
		applyValidatedConfig( configParams, { dirty: true, format: true, render: false } );
	}

	$themeEditor.on( 'input', '[data-theme-color-path]', function() {
		const $color = $( this );
		const value = $color.val().toUpperCase();
		const path = $color.attr( 'data-theme-color-path' ).split( '--' );

		$color.closest( '.hcaptcha-theme-editor-color-controls' )
			.find( '[data-theme-hex-path]' )
			.val( value )
			.attr( 'aria-invalid', 'false' );
		updateVisualThemeValue( path, value );
	} );

	$themeEditor.on( 'input', '[data-theme-hex-path]', function() {
		const $hex = $( this );
		const value = $hex.val().trim();

		if ( ! /^#[0-9a-f]{6}$/i.test( value ) ) {
			$hex.attr( 'aria-invalid', 'true' );
			return;
		}

		const path = $hex.attr( 'data-theme-hex-path' ).split( '--' );

		$hex.attr( 'aria-invalid', 'false' );
		$hex.closest( '.hcaptcha-theme-editor-color-controls' )
			.find( '[data-theme-color-path]' )
			.val( value );
		updateVisualThemeValue( path, value.toUpperCase() );
	} );

	$themeEditor.on( 'change', '[data-theme-editor-mode]', function() {
		const configParams = deepClone( lastValidConfigParams );

		if ( ! isObject( configParams.theme ) ) {
			configParams.theme = {};
		}

		pruneDefaultThemeValues( configParams.theme, defaultTheme );
		setNestedValue( configParams.theme, [ 'palette', 'mode' ], $( this ).val() );
		applyValidatedConfig( configParams, { dirty: true, format: true, render: true } );
	} );

	$themeEditor.on( 'click', '[data-theme-editor-reset-section]', function() {
		const configParams = deepClone( lastValidConfigParams );
		const groupPath = getActiveThemeGroupPath();
		const defaultGroup = getNestedValue( defaultTheme, groupPath );

		if ( ! isObject( configParams.theme ) ) {
			configParams.theme = {};
		}

		setNestedValue( configParams.theme, groupPath, deepClone( defaultGroup ) );
		applyValidatedConfig( configParams, { dirty: true, format: true, render: true } );
	} );

	$themeEditor.on( 'click', '[data-theme-editor-reset-theme]', function() {
		const configParams = deepClone( lastValidConfigParams );

		configParams.theme = deepClone( defaultTheme );
		applyValidatedConfig( configParams, { dirty: true, format: true, render: true } );
	} );

	$themeEditor.on( 'click', '[data-theme-editor-format]', function() {
		applyCustomThemes( {}, { dirty: true, format: true, render: true } );
	} );

	$themeEditor.on( 'click', '[data-theme-preview-background]', function() {
		const $button = $( this );
		const isDark = 'dark' === $button.attr( 'data-theme-preview-background' );

		$themeEditor.find( '[data-theme-preview-background]' ).removeClass( 'is-active' );
		$button.addClass( 'is-active' );
		$themeEditor.find( '[data-theme-editor-preview-stage]' ).toggleClass( 'is-dark', isDark );
	} );

	$themeEditor.on( 'click', '[data-theme-preview-view]', function() {
		setThemePreviewView( $( this ).attr( 'data-theme-preview-view' ) );
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

	// Prevent saving values of some form elements.
	$checkConfig.removeAttr( 'name' );
	$resetNotifications.removeAttr( 'name' );
};

window.hCaptchaGeneral = general;

jQuery( document ).ready( general );
