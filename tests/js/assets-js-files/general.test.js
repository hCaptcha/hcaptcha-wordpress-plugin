/* global HCaptchaGeneralObject, kaggDialog */
// noinspection JSUnresolvedReference, JSUnresolvedFunction, JSUnresolvedVariable

/* eslint-disable no-console */
import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock settings base minimal API used by general.js messages
global.hCaptchaSettingsBase = {
	getStickyHeight: () => 50,
	highlightElement: jest.fn(),
};

// Minimal hCaptcha mock used by general.js
const hCaptchaMockParams = { theme: 'light' };
const hCaptcha = {
	getParams: jest.fn( () => ( { ...hCaptchaMockParams } ) ),
	setParams: jest.fn(),
	bindEvents: jest.fn(),
};

global.hCaptcha = hCaptcha;
global.hcaptcha = {};

// General object defaults
const defaultGeneralObject = {
	activeState: 'Active',
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	badJSONError: 'Bad JSON',
	checkConfigAction: 'hcap_check_config',
	checkConfigNonce: 'nonce1',
	checkConfigNotice: 'Please re-check configuration',
	checkingConfigMsg: 'Checking...',
	completeHCaptchaContent: 'Please solve hCaptcha',
	completeHCaptchaTitle: 'hCaptcha needed',
	configMustBeObject: 'Config Params must be a JSON object.',
	focusState: 'Focus',
	hexValue: 'hex value',
	hoverState: 'Hover',
	invalidJSON: 'Invalid JSON',
	lastValidPreview: 'Showing the last valid config.',
	mainState: 'Main',
	modeLive: 'live',
	modeTestEnterpriseBotDetected: 'test_ent_bot',
	modeTestEnterpriseBotDetectedSiteKey: 'ent-bot-key',
	modeTestEnterpriseSafeEndUser: 'test_ent_safe',
	modeTestEnterpriseSafeEndUserSiteKey: 'ent-safe-key',
	modeTestPublisher: 'test_pub',
	modeTestPublisherSiteKey: 'pub-key',
	reportState: 'Report',
	selectedState: 'Selected',
	siteKey: 'live-key',
	unsavedChanges: 'Unsaved changes',
	validJSON: 'Valid JSON',
	OKBtnText: 'OK',
	CancelBtnText: 'Cancel',
};

global.HCaptchaGeneralObject = { ...defaultGeneralObject };

// WP core runs _.noConflict() so lodash is available as window.lodash, not _.
global.lodash = {
	debounce: ( func ) => func,
};

let consoleLogSpy;
let consoleWarnSpy;
let consoleInfoSpy;
let consoleErrorSpy;
let consoleClearSpy;

// kaggDialog mock
beforeEach( () => {
	window.hcaptcha = window.hcaptcha || {};
	consoleLogSpy = jest.spyOn( console, 'log' ).mockImplementation( () => {} );
	consoleWarnSpy = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	consoleInfoSpy = jest.spyOn( console, 'info' ).mockImplementation( () => {} );
	consoleErrorSpy = jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	consoleClearSpy = jest.spyOn( console, 'clear' ).mockImplementation( () => {} );
	global.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg?.onAction?.( true ) ) };
	window.hCaptchaBindEvents = jest.fn();
	// Mock scrollIntoView for jsdom.
	Element.prototype.scrollIntoView = jest.fn();
} );

afterEach( () => {
	consoleLogSpy?.mockRestore();
	consoleWarnSpy?.mockRestore();
	consoleInfoSpy?.mockRestore();
	consoleErrorSpy?.mockRestore();
	consoleClearSpy?.mockRestore();
} );

function getDom() {
	// noinspection JSUnresolvedLibraryURL
	return `
<html lang="en">
<body>
<div id="wpwrap">
	<div class="hcaptcha-header-bar"></div>
	<div id="hcaptcha-options">
		<div class="h-captcha"><iframe></iframe></div>
		<textarea name="h-captcha-response"></textarea>
		<input type="hidden" name="hcaptcha-widget-id" value="wid-1" />
	</div>
	<div id="hcaptcha-admin-notices"></div>
	<div id="hcaptcha-message"></div>
	<div class="hcaptcha-general-sample-hcaptcha">
		<textarea name="h-captcha-response"></textarea>
	</div>
	<form class="hcaptcha-general">
		<input id="submit" type="submit" />
		<input id="check_config" type="button" />
		<input id="reset_notifications" type="button" />
		<select name="hcaptcha_settings[mode]">
			<option value="live">live</option>
			<option value="test_pub">test_pub</option>
			<option value="test_ent_safe">test_ent_safe</option>
		</select>
		<span class="key-wrap"><input id="site_key" name="hcaptcha_settings[site_key]" value="live-key" /><span class="helper" style="display:none"></span><span class="helper-content" style="display:none"></span></span>
		<span class="key-wrap"><input id="secret_key" name="hcaptcha_settings[secret_key]" value="secret" /><span class="helper" style="display:none"></span><span class="helper-content" style="display:none"></span></span>
		<select name="hcaptcha_settings[theme]"><option value="light">light</option><option value="dark">dark</option></select>
		<select name="hcaptcha_settings[size]" id="size-select"><option value="normal">normal</option><option value="invisible">invisible</option></select>
		<div id="hcaptcha-invisible-notice" style="display:none"></div>
		<select name="hcaptcha_settings[language]"><option value="en">en</option></select>
		<label><input type="checkbox" name="hcaptcha_settings[custom_themes][]" /></label>
		<div class="hcaptcha-theme-editor-launcher">
			<button type="button" data-theme-editor-open aria-expanded="false">Open</button>
			<span class="hcaptcha-theme-editor-dirty" hidden></span>
		</div>
		<div class="hcaptcha-theme-editor" hidden data-theme-editor-preview-only="false" data-default-theme='{"palette":{"mode":"light","grey":{"100":"#fafafa"},"primary":{"main":"#00838f"},"warn":{"main":"#eb5757"},"text":{"heading":"#555555","body":"#555555"}},"component":{"checkbox":{"main":{"fill":"#fafafa","border":"#e0e0e0"},"hover":{"fill":"#f5f5f5"}},"button":{"main":{"fill":"#ffffff","text":"#555555"}}}}'>
			<div data-theme-editor-drag-handle>
				<span class="hcaptcha-theme-editor-dirty" hidden></span>
				<button type="button" data-theme-editor-close>Close</button>
			</div>
			<button type="button" data-theme-editor-tab="visual" class="is-active" aria-selected="true"></button>
			<button type="button" data-theme-editor-tab="json" aria-selected="false"></button>
			<div data-theme-editor-pane="visual" class="is-active">
				<nav>
					<button type="button" class="hcaptcha-theme-editor-nav-button is-active" data-theme-group="palette">Palette</button>
					<span data-theme-editor-component-nav></span>
				</nav>
				<span data-theme-editor-group-title></span>
				<span data-theme-editor-group-description></span>
				<label data-theme-editor-mode-field><select data-theme-editor-mode><option value="light">light</option><option value="dark">dark</option></select></label>
				<div data-theme-editor-fields></div>
				<button type="button" data-theme-editor-reset-section>Reset section</button>
			</div>
			<div data-theme-editor-pane="json" hidden>
				<span class="hcaptcha-theme-editor-json-status is-valid"></span>
				<button type="button" data-theme-editor-format>Format</button>
				<textarea name="hcaptcha_settings[config_params]">{}</textarea>
				<p class="hcaptcha-theme-editor-json-error"></p>
			</div>
			<div data-theme-editor-preview-stage>
				<div data-theme-preview-pane="widget">
					<img data-theme-mock-logo="light">
					<img data-theme-mock-logo="dark" hidden>
				</div>
				<div data-theme-preview-pane="challenge" hidden></div>
			</div>
			<button type="button" class="is-active" data-theme-preview-view="widget" aria-selected="true">Widget</button>
			<button type="button" data-theme-preview-view="challenge" aria-selected="false">Challenge</button>
			<button type="button" class="is-active" data-theme-preview-background="light">Light</button>
			<button type="button" data-theme-preview-background="dark">Dark</button>
			<p data-theme-editor-preview-note>Approximate challenge preview.<br>The real widget in Keys also updates live.</p>
			<button type="button" data-theme-editor-show-sample>Show real hCaptcha</button>
			<button type="button" data-theme-editor-reset-theme>Reset theme</button>
		</div>
		<label><input type="checkbox" name="hcaptcha_settings[recaptcha_compat_off][]" /></label>

		<!-- Section toggle target -->
		<h3 class="togglable hcaptcha-section-keys closed"></h3>

		<!-- Enterprise section marker + table with inputs -->
		<h3 class="hcaptcha-section-enterprise"></h3>
		<table><tbody>
			<tr><td><input name="hcaptcha_settings[asset_host]" value="assethost.local" /></td></tr>
			<tr><td><input name="hcaptcha_settings[endpoint]" value="endpoint.local" /></td></tr>
			<tr><td><input name="hcaptcha_settings[host]" value="host.local" /></td></tr>
			<tr><td><input name="hcaptcha_settings[image_host]" value="imghost.local" /></td></tr>
			<tr><td><input name="hcaptcha_settings[report_api]" value="report.local" /></td></tr>
			<tr><td><input name="hcaptcha_settings[sentry]" value="sentry.local" /></td></tr>
			<tr><td><input name="hcaptcha_settings[api_host]" value="js.hcaptcha.com" /></td></tr>
		</tbody></table>

	</form>
</div>
<!-- Existing API script to be replaced by scriptUpdate() -->
<script id="hcaptcha-api" src="https://js.hcaptcha.com/1/api.js"></script>
</body>
</html>
	`;
}

// Load modules after DOM is set in each test
function bootGeneral( domOverrides = {}, hCaptchaReady = true, initialState = {} ) {
	jest.resetModules();
	document.body.innerHTML = getDom();

	if ( Object.prototype.hasOwnProperty.call( initialState, 'customThemes' ) ) {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', initialState.customThemes );
	}

	if ( Object.prototype.hasOwnProperty.call( initialState, 'configParams' ) ) {
		$( "textarea[name='hcaptcha_settings[config_params]']" ).val( initialState.configParams );
	}

	if ( Object.prototype.hasOwnProperty.call( initialState, 'previewOnly' ) ) {
		$( '.hcaptcha-theme-editor' ).attr( 'data-theme-editor-preview-only', initialState.previewOnly ? 'true' : 'false' );
	}

	Object.assign( window.HCaptchaGeneralObject, defaultGeneralObject, domOverrides );
	require( '../../../assets/js/settings-base.js' );
	require( '../../../assets/js/general.js' );
	// Trigger jQuery ready
	window.hCaptchaGeneral( $ );

	if ( hCaptchaReady ) {
		document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded' ) );
	}
}

describe( 'general.js basics', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		// Default $.post mock: call the beforehand method, then resolve success
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( ( opts ) => {
			const d = $.Deferred();
			if ( opts && typeof opts.beforeSend === 'function' ) {
				try {
					opts.beforeSend();
				} catch {
					// ignore
				}
			}
			// emulate async
			setTimeout( () => d.resolve( { success: true, data: 'OK' } ), 0 );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'showMessage plumbing via checkConfig beforeSend: sets success class and animates', async () => {
		bootGeneral();
		// provide a solved hcaptcha so it does not open the dialog
		$( 'textarea[name="h-captcha-response"]' ).val( 'token' );
		const animSpy = jest.spyOn( $.fn, 'animate' ).mockImplementation( () => $.fn );
		const offsetSpy = jest.spyOn( $.fn, 'offset' ).mockReturnValue( { top: 0, left: 0 } );

		$( '#check_config' ).trigger( 'click' );
		await Promise.resolve();
		jest.runAllTimers();
		await Promise.resolve();

		// Re-query the last #hcaptcha-message after clearMessage() may create a new one.
		const allMsgEls = document.querySelectorAll( '#hcaptcha-message' );
		const lastMsg = allMsgEls[ allMsgEls.length - 1 ];
		expect( lastMsg.className ).toContain( 'notice-success' );
		expect( animSpy ).toHaveBeenCalled();
		animSpy.mockRestore();
		offsetSpy.mockRestore();
	} );

	test( 'size change toggles invisible notice and calls hCaptchaUpdate', () => {
		bootGeneral();
		const notice = document.getElementById( 'hcaptcha-invisible-notice' );
		expect( notice.style.display ).toBe( 'none' );
		$( '#size-select' ).val( 'invisible' ).trigger( 'change' );
		expect( $( notice ).css( 'display' ) ).not.toBe( 'none' );
		expect( hCaptcha.setParams ).toHaveBeenCalled();
	} );

	test( 'mode change disables/enables key fields and updates sitekey', () => {
		bootGeneral();
		const $mode = $( "select[name='hcaptcha_settings[mode]']" );
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const $secret = $( "[name='hcaptcha_settings[secret_key]']" );

		$mode.val( 'test_pub' ).trigger( 'change' );
		expect( $site.attr( 'readonly' ) ).toBe( 'readonly' );
		expect( $secret.attr( 'readonly' ) ).toBe( 'readonly' );
		expect( hCaptcha.setParams ).toHaveBeenCalled();

		$mode.val( 'live' ).trigger( 'change' );
		expect( $site.attr( 'readonly' ) ).toBeUndefined();
		expect( $secret.attr( 'readonly' ) ).toBeUndefined();
	} );

	test( 'applyCustomThemes: bad JSON disables submit and shows error', () => {
		bootGeneral();
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		const updateCount = hCaptcha.setParams.mock.calls.length;

		$cfg.val( '{bad json' ).trigger( 'input' );
		const submit = document.getElementById( 'submit' );

		expect( submit.disabled ).toBe( true );
		expect( $cfg.attr( 'aria-invalid' ) ).toBe( 'true' );
		expect( $( '.hcaptcha-theme-editor-json-status' ).hasClass( 'is-invalid' ) ).toBe( true );
		expect( $( '.hcaptcha-theme-editor-json-error' ).text() ).toContain( 'Bad JSON' );
		expect( hCaptcha.setParams ).toHaveBeenCalledTimes( updateCount );
	} );

	test( 'applyCustomThemes: not custom themes uses base params and calls setParams', () => {
		bootGeneral();
		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );
		$custom.prop( 'checked', false );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		$cfg.val( '{"foo":1}' ).trigger( 'input' );
		expect( hCaptcha.setParams ).toHaveBeenCalled();
		const lastCallArg = hCaptcha.setParams.mock.calls.slice( -1 )[ 0 ][ 0 ];
		expect( lastCallArg ).toEqual( expect.objectContaining( {
			sitekey: 'live-key',
			hl: 'en',
		} ) );
	} );

	test( 'scriptUpdate: rebuilds API script with params and clears sample', async () => {
		bootGeneral();
		// Enable recaptcha compat off and custom themes to get params in URL
		$( "input[name='hcaptcha_settings[recaptcha_compat_off][]']" ).prop( 'checked', true );
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true );
		// Change an enterprise input to trigger scriptUpdate
		$( "input[name='hcaptcha_settings[asset_host]']" ).val( 'http://assethost.local' ).trigger( 'change' );
		await Promise.resolve();
		const script = document.getElementById( 'hcaptcha-api' );
		expect( script ).toBeTruthy();
		expect( script.src ).toContain( 'onload=hCaptchaOnLoad' );
		expect( script.src ).toContain( 'render=explicit' );
		expect( script.src ).toContain( 'recaptchacompat=off' );
		expect( script.src ).toContain( 'custom=true' );
		// sample cleared
		expect( $( '#hcaptcha-options .h-captcha' ).html() ).toBe( '' );
	} );

	test( 'credentials change does not disable submit and checkConfig success re-enables', async () => {
		// Post resolves success
		postSpy.mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: true, data: 'OK' } ), 0 );
			return d;
		} );
		bootGeneral();
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const submit = document.getElementById( 'submit' );
		$site.val( 'other-key' ).trigger( 'change' );
		expect( submit.getAttribute( 'disabled' ) ).toBe( null );
		// provide response token to avoid dialog
		$( 'textarea[name="h-captcha-response"]' ).val( 'token' );
		$( '#check_config' ).trigger( 'click' );
		await Promise.resolve();
		jest.runAllTimers();
		expect( submit.getAttribute( 'disabled' ) ).toBe( null );
	} );
} );

describe( 'showMessage early return on empty message', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
		window.__generalTest.interceptConsoleLogs();
	} );

	test( 'returns early when message and console logs are both empty', () => {
		const $msg = $( '#hcaptcha-message' );

		// Ensure a message element has no classes initially.
		$msg.removeClass();

		window.__generalTest.showMessage( '', '' );

		// Should not have added any notice class because it returned early.
		expect( $msg.hasClass( 'notice' ) ).toBe( false );
	} );
} );

describe( 'hCaptchaUpdate branches', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'sets data-theme to custom when custom themes checked and mode is live', () => {
		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );
		const $modeSelect = $( "select[name='hcaptcha_settings[mode]']" );
		const $sample = $( '#hcaptcha-options .h-captcha' );

		$modeSelect.val( 'live' );
		$custom.prop( 'checked', true );

		window.__generalTest.hCaptchaUpdate( { theme: { palette: {} } } );

		expect( $sample.attr( 'data-theme' ) ).toBe( 'custom' );
	} );

	test( 'uses hCaptcha.getParams().theme when custom themes ON and params.theme is not object', () => {
		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );
		const $modeSelect = $( "select[name='hcaptcha_settings[mode]']" );

		$modeSelect.val( 'live' );
		$custom.prop( 'checked', true );

		// params.theme is a string, not an object — so globalParams.theme should come from getParams()
		hCaptcha.getParams.mockReturnValue( { theme: 'dark' } );

		window.__generalTest.hCaptchaUpdate( { theme: 'light' } );

		// setParams should have been called with theme from getParams() = 'dark'
		const lastCall = hCaptcha.setParams.mock.calls.slice( -1 )[ 0 ][ 0 ];
		expect( lastCall.theme ).toBe( 'dark' );
	} );

	test( 'skips object params when setting data attributes', () => {
		const $sample = $( '#hcaptcha-options .h-captcha' );

		window.__generalTest.hCaptchaUpdate( { size: 'normal', theme: { palette: {} } } );

		// 'size' (string) should be set as a data attribute.
		expect( $sample.attr( 'data-size' ) ).toBe( 'normal' );
		// 'theme' (object) should NOT be set as a data attribute.
		expect( $sample.attr( 'data-theme' ) ).not.toBe( '[object Object]' );
	} );

	test( 'updates the server-rendered placeholder with the next checkbox fill', () => {
		const $sample = $( '#hcaptcha-options .h-captcha' );

		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true );

		window.__generalTest.hCaptchaUpdate( {
			theme: {
				component: {
					checkbox: {
						main: {
							fill: '#123456',
						},
					},
				},
			},
		} );

		expect( $sample[ 0 ].style.getPropertyValue( '--hcaptcha-theme-editor-background' ) ).toBe( '#123456' );
	} );
} );

describe( 'deepMerge', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'returns source when target is not an object', () => {
		const result = window.__generalTest.deepMerge( null, { a: 1 } );
		expect( result ).toEqual( { a: 1 } );
	} );

	test( 'returns source when source is not an object', () => {
		const result = window.__generalTest.deepMerge( { a: 1 }, 'string' );
		expect( result ).toBe( 'string' );
	} );

	test( 'merges nested objects deeply', () => {
		const target = { a: { b: 1, c: 2 }, d: 3 };
		const source = { a: { b: 10, e: 5 }, f: 6 };
		const result = window.__generalTest.deepMerge( target, source );

		expect( result ).toEqual( { a: { b: 10, c: 2, e: 5 }, d: 3, f: 6 } );
	} );

	test( 'concatenates arrays', () => {
		const target = { items: [ 1, 2 ] };
		const source = { items: [ 3, 4 ] };
		const result = window.__generalTest.deepMerge( target, source );

		expect( result.items ).toEqual( [ 1, 2, 3, 4 ] );
	} );

	test( 'overwrites primitive values', () => {
		const target = { a: 1, b: 'old' };
		const source = { a: 2, b: 'new' };
		const result = window.__generalTest.deepMerge( target, source );

		expect( result ).toEqual( { a: 2, b: 'new' } );
	} );
} );

describe( 'checkConfig done/fail branches', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'checkConfig .done with success=false shows error', async () => {
		postSpy.mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: false, data: 'Invalid config' } ), 0 );
			return d;
		} );
		bootGeneral();
		$( 'textarea[name="h-captcha-response"]' ).val( 'token' );
		$( '#check_config' ).trigger( 'click' );
		jest.runAllTimers();
		await Promise.resolve();

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );

	test( 'checkConfig .fail shows error with statusText', async () => {
		postSpy.mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.reject( { statusText: 'Network error' } ), 0 );
			return d;
		} );
		bootGeneral();
		$( 'textarea[name="h-captcha-response"]' ).val( 'token' );
		$( '#check_config' ).trigger( 'click' );
		jest.runAllTimers();
		await Promise.resolve();

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );
} );

describe( 'checkChangeCredentials revert to initial', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'reverts credentialsChanged state when credentials return to initial values', () => {
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const $submit = $( '#submit' );

		// Change credentials to trigger credentialsChanged.
		$site.val( 'changed-key' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();

		// Revert to initial value.
		$site.val( 'live-key' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();
	} );
} );

describe( 'checkChangeEnterpriseSettings revert to initial', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: true } ), 0 );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'reverts enterpriseSettingsChanged state when enterprise settings return to initial values', () => {
		bootGeneral();
		const $asset = $( "[name='hcaptcha_settings[asset_host]']" );
		const $submit = $( '#submit' );

		// Change enterprise input to trigger enterpriseSettingsChanged.
		$asset.val( 'changed.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();

		// Revert to initial value.
		$asset.val( 'assethost.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();
	} );
} );

describe( 'initDisabledKeyInputs helper show/hide', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'shows helper on click when input is readonly, hides on mousedown', () => {
		// Switch to test mode to make keys readonly.
		$( "select[name='hcaptcha_settings[mode]']" ).val( 'test_pub' ).trigger( 'change' );

		const $siteKey = $( '#site_key' );
		const $helper = $siteKey.parent().find( 'span.helper' );
		const $helperContent = $siteKey.parent().find( 'span.helper-content' );

		// Click on readonly input.
		$siteKey.trigger( 'click' );
		expect( $helper.css( 'display' ) ).toBe( 'block' );
		expect( $helperContent.css( 'display' ) ).toBe( 'block' );

		// Mousedown on document hides helper.
		$( document ).trigger( 'mousedown' );
		expect( $helper.css( 'display' ) ).toBe( 'none' );
		expect( $helperContent.css( 'display' ) ).toBe( 'none' );
	} );

	test( 'does not show helper when input is not readonly', () => {
		// In live mode, keys are not readonly.
		$( "select[name='hcaptcha_settings[mode]']" ).val( 'live' ).trigger( 'change' );

		const $siteKey = $( '#site_key' );
		const $helper = $siteKey.parent().find( 'span.helper' );

		$siteKey.trigger( 'click' );
		expect( $helper.css( 'display' ) ).toBe( 'none' );
	} );

	test( 'keydown is prevented on readonly input', () => {
		$( "select[name='hcaptcha_settings[mode]']" ).val( 'test_pub' ).trigger( 'change' );

		const $siteKey = $( '#site_key' );
		const event = $.Event( 'keydown.hcaptchaHelper' );

		$siteKey.trigger( event );
		expect( event.isDefaultPrevented() ).toBe( true );
	} );
} );

describe( 'syncKeysWithMode unknown mode', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'returns early for unknown mode without calling hCaptchaUpdate', () => {
		hCaptcha.setParams.mockClear();
		$( "select[name='hcaptcha_settings[mode]']" ).val( 'unknown_mode' ).trigger( 'change' );
		// setParams should not have been called for the unknown mode change.
		expect( hCaptcha.setParams ).not.toHaveBeenCalled();
	} );
} );

describe( 'hCaptchaLoaded event', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'dispatching hCaptchaLoaded calls showErrorMessage', () => {
		// Generate a console error so showErrorMessage has content to display.
		window.__generalTest.interceptConsoleLogs();
		console.error( 'test error from hCaptchaLoaded' );

		document.dispatchEvent( new Event( 'hCaptchaLoaded' ) );

		// Re-query after clearMessage() replaces the element.
		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );
} );

describe( 'checkConfig click without solved captcha', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'opens kaggDialog when h-captcha-response is empty', () => {
		// Prevent onAction from calling hCaptchaBindEvents.
		kaggDialog.confirm = jest.fn();

		// Ensure the sample textarea is empty.
		$( '.hcaptcha-general-sample-hcaptcha textarea[name="h-captcha-response"]' ).val( '' );

		$( '#check_config' ).trigger( 'click' );

		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( {
				title: HCaptchaGeneralObject.completeHCaptchaTitle,
				content: HCaptchaGeneralObject.completeHCaptchaContent,
			} ),
		);
	} );
} );

describe( 'event handlers: secretKey, theme, language, size non-invisible', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'secretKey change triggers checkChangeCredentials', () => {
		const $secret = $( "[name='hcaptcha_settings[secret_key]']" );
		const $submit = $( '#submit' );

		$secret.val( 'new-secret' ).trigger( 'change' );
		// credentialsChanged should NOT disable the submitting anymore.
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();
	} );

	test( 'theme change calls hCaptchaUpdate', () => {
		hCaptcha.setParams.mockClear();
		$( "[name='hcaptcha_settings[theme]']" ).val( 'dark' ).trigger( 'change' );
		expect( hCaptcha.setParams ).toHaveBeenCalled();
		const lastCall = hCaptcha.setParams.mock.calls.slice( -1 )[ 0 ][ 0 ];
		expect( lastCall.theme ).toBe( 'dark' );
	} );

	test( 'language change calls hCaptchaUpdate', () => {
		hCaptcha.setParams.mockClear();
		$( "[name='hcaptcha_settings[language]']" ).val( 'en' ).trigger( 'change' );
		expect( hCaptcha.setParams ).toHaveBeenCalled();
	} );

	test( 'size change to normal hides invisible notice', () => {
		const $notice = $( '#hcaptcha-invisible-notice' );
		// First show it.
		const $size = $( '#size-select' );

		$size.val( 'invisible' ).trigger( 'change' );
		expect( $notice.css( 'display' ) ).not.toBe( 'none' );

		// Then hide it.
		$size.val( 'normal' ).trigger( 'change' );
		expect( $notice.css( 'display' ) ).toBe( 'none' );
	} );
} );

describe( 'advanced theme editor controls', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'customThemes change toggles disabled state of editor controls', () => {
		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		const $launcher = $( '.hcaptcha-theme-editor-launcher' );
		const $open = $( '[data-theme-editor-open]' );

		// Initially, unchecked — fields should be disabled.
		expect( $cfg.prop( 'disabled' ) ).toBe( true );
		expect( $launcher.hasClass( 'is-disabled' ) ).toBe( true );
		expect( $open.prop( 'disabled' ) ).toBe( true );

		// Check it.
		$custom.prop( 'checked', true ).trigger( 'change' );
		expect( $cfg.prop( 'disabled' ) ).toBe( false );
		expect( $launcher.hasClass( 'is-disabled' ) ).toBe( false );
		expect( $open.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'initializes editor controls before the hCaptcha API is ready', () => {
		const api = window.hcaptcha;

		delete window.hcaptcha;
		expect( () => bootGeneral( {}, false ) ).not.toThrow();

		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );

		$custom.prop( 'checked', true ).trigger( 'change' );
		expect( $( '[data-theme-editor-open]' ).prop( 'disabled' ) ).toBe( false );

		window.hcaptcha = api;
		hCaptcha.setParams.mockClear();
		document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded' ) );
		expect( hCaptcha.setParams ).toHaveBeenCalled();
	} );

	test( 'launcher opens a floating editor, expands Keys, and closes it', () => {
		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );
		const $editor = $( '.hcaptcha-theme-editor' );
		const $open = $( '[data-theme-editor-open]' );

		$custom.prop( 'checked', true ).trigger( 'change' );
		$open.trigger( 'click' );

		expect( $editor.parent().is( 'form.hcaptcha-general' ) ).toBe( true );
		expect( $editor.find( 'textarea' ).attr( 'name' ) ).toBe( 'hcaptcha_settings[config_params]' );
		expect( $editor.prop( 'hidden' ) ).toBe( false );
		expect( $open.attr( 'aria-expanded' ) ).toBe( 'true' );
		expect( $( '.hcaptcha-section-keys' ).hasClass( 'closed' ) ).toBe( false );

		$( '[data-theme-editor-close]' ).trigger( 'click' );
		expect( $editor.prop( 'hidden' ) ).toBe( true );
		expect( $open.attr( 'aria-expanded' ) ).toBe( 'false' );
	} );

	test( 'floating editor can be dragged by its title bar', () => {
		const $editor = $( '.hcaptcha-theme-editor' );
		const $handle = $( '[data-theme-editor-drag-handle]' );
		const widthSpy = jest.spyOn( $.fn, 'outerWidth' ).mockReturnValue( 860 );
		const heightSpy = jest.spyOn( $.fn, 'outerHeight' ).mockReturnValue( 600 );

		$editor[ 0 ].getBoundingClientRect = jest.fn( () => ( {
			height: 600,
			left: 100,
			top: 80,
			width: 860,
		} ) );
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		$( '[data-theme-editor-open]' ).trigger( 'click' );

		$handle.trigger( $.Event( 'pointerdown', { clientX: 120, clientY: 100 } ) );
		$( document ).trigger( $.Event( 'pointermove', { clientX: 220, clientY: 180 } ) );
		$( document ).trigger( 'pointerup' );

		expect( $editor.css( 'left' ) ).toBe( '156px' );
		expect( $editor.css( 'top' ) ).toBe( '160px' );

		widthSpy.mockRestore();
		heightSpy.mockRestore();
	} );

	test( 'tabs switch between visual and JSON panes', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		$( '[data-theme-editor-tab="json"]' ).trigger( 'click' );

		expect( $( '[data-theme-editor-pane="visual"]' ).prop( 'hidden' ) ).toBe( true );
		expect( $( '[data-theme-editor-pane="json"]' ).prop( 'hidden' ) ).toBe( false );
		expect( $( '[data-theme-editor-tab="json"]' ).attr( 'aria-selected' ) ).toBe( 'true' );
	} );

	test( 'component navigation renders grouped fields', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $checkboxButton = $( '[data-theme-group="component--checkbox"]' );

		expect( $checkboxButton.length ).toBe( 1 );
		$checkboxButton.trigger( 'click' );

		expect( $( '[data-theme-editor-group-title]' ).text() ).toBe( 'Checkbox' );
		expect( $( '[data-theme-editor-fields] .hcaptcha-theme-editor-color-row' ).length ).toBe( 3 );
		expect( $( '[data-theme-preview-pane="widget"]' ).prop( 'hidden' ) ).toBe( false );
	} );

	test( 'visual field labels preserve the complete property hierarchy', () => {
		const getLabels = () => $( '[data-theme-editor-fields] .hcaptcha-theme-editor-color-label' )
			.map( ( index, element ) => $( element ).text() )
			.get();

		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );

		expect( getLabels() ).toEqual( expect.arrayContaining( [
			'Grey - 100',
			'Primary - Main',
			'Warn - Main',
			'Text - Heading',
		] ) );

		$( '[data-theme-group="component--checkbox"]' ).trigger( 'click' );

		expect( getLabels() ).toEqual( expect.arrayContaining( [
			'Main - Fill',
			'Main - Border',
			'Hover - Fill',
		] ) );
	} );

	test( 'visual color change updates JSON and live preview', () => {
		const $status = $( '.hcaptcha-theme-editor-dirty' ).first();

		expect( $status.prop( 'hidden' ) ).toBe( true );
		expect( $status.text() ).toBe( '' );

		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		hCaptcha.setParams.mockClear();

		$( '[data-theme-color-path="palette--primary--main"]' ).val( '#123456' ).trigger( 'input' );

		const config = JSON.parse( $( "textarea[name='hcaptcha_settings[config_params]']" ).val() );
		const previewParams = hCaptcha.setParams.mock.calls.slice( -1 )[ 0 ][ 0 ];

		expect( config.theme.palette.primary.main ).toBe( '#123456' );
		expect( previewParams.theme.palette.primary.main ).toBe( '#123456' );
		expect( $status.text() ).toBe( 'Unsaved changes' );
		expect( $status.prop( 'hidden' ) ).toBe( false );
		expect( $status.hasClass( 'is-dirty' ) ).toBe( true );
		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-palette-primary' ) ).toBe( '#123456' );
	} );

	test( 'palette mode change uses sparse dark defaults in both previews', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		$cfg.val( JSON.stringify( {
			foo: 1,
			theme: {
				palette: {
					mode: 'light',
					grey: {
						100: '#FAFAFA',
					},
				},
				component: {
					checkbox: {
						main: {
							border: '#E0E0E0',
							fill: '#123456',
						},
					},
				},
			},
		} ) ).trigger( 'input' );
		hCaptcha.setParams.mockClear();

		$( '[data-theme-editor-mode]' ).val( 'dark' ).trigger( 'change' );

		const config = JSON.parse( $cfg.val() );
		const previewParams = hCaptcha.setParams.mock.calls.slice( -1 )[ 0 ][ 0 ];

		expect( config.foo ).toBe( 1 );
		expect( config.theme.palette ).toEqual( { mode: 'dark' } );
		expect( config.theme.component.checkbox.main ).toEqual( { fill: '#123456' } );
		expect( previewParams.theme ).toEqual( config.theme );
		expect( $( '[data-theme-editor-preview-stage]' ).attr( 'data-theme-preview-mode' ) ).toBe( 'dark' );
		expect( $( '[data-theme-editor-preview-stage]' ).attr( 'data-theme-preview-logo-mode' ) ).toBe( 'dark' );
		expect( $( '[data-theme-mock-logo="light"]' ).prop( 'hidden' ) ).toBe( true );
		expect( $( '[data-theme-mock-logo="dark"]' ).prop( 'hidden' ) ).toBe( false );
		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-checkbox-fill' ) ).toBe( '#123456' );
		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-widget-checkbox-fill' ) ).toBe( '#FAFAFA' );
		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-widget-checkbox-border' ) ).toBe( '#F5F5F5' );
		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-widget-label' ) ).toBe( '#333333' );
		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-modal-fill' ) ).toBe( '#333333' );
		expect( $( '[data-theme-editor-mode]' ).val() ).toBe( 'dark' );
	} );

	test( 'legacy config without palette mode uses the same white logo as hCaptcha', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		$cfg.val( '{"theme":{"mode":"light"}}' ).trigger( 'input' );

		expect( $( '[data-theme-editor-preview-stage]' ).attr( 'data-theme-preview-mode' ) ).toBe( 'light' );
		expect( $( '[data-theme-editor-preview-stage]' ).attr( 'data-theme-preview-logo-mode' ) ).toBe( 'dark' );
		expect( $( '[data-theme-mock-logo="light"]' ).prop( 'hidden' ) ).toBe( true );
		expect( $( '[data-theme-mock-logo="dark"]' ).prop( 'hidden' ) ).toBe( false );
	} );

	test( 'challenge components select the synthetic challenge preview', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		$( '[data-theme-group="component--button"]' ).trigger( 'click' );

		expect( $( '[data-theme-preview-pane="widget"]' ).prop( 'hidden' ) ).toBe( true );
		expect( $( '[data-theme-preview-pane="challenge"]' ).prop( 'hidden' ) ).toBe( false );
		expect( $( '[data-theme-preview-view="challenge"]' ).attr( 'aria-selected' ) ).toBe( 'true' );
	} );

	test( 'valid JSON updates visual controls and preserves unknown params', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		$cfg.val( '{"foo":1,"theme":{"palette":{"mode":"dark","primary":{"main":"#112233"}}}}' ).trigger( 'input' );

		expect( $( '[data-theme-editor-mode]' ).val() ).toBe( 'dark' );
		expect( $( '[data-theme-hex-path="palette--primary--main"]' ).val() ).toBe( '#112233' );
		expect( JSON.parse( $cfg.val() ).foo ).toBe( 1 );
	} );

	test( 'valid JSON restores the preview note line break', () => {
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		const $note = $( '[data-theme-editor-preview-note]' );

		expect( $note.find( 'br' ) ).toHaveLength( 1 );

		$cfg.val( '{bad json' ).trigger( 'input' );

		expect( $note.text() ).toBe( 'Showing the last valid config.' );
		expect( $note.find( 'br' ) ).toHaveLength( 0 );

		$cfg.val( '{}' ).trigger( 'input' );

		expect( $note.find( 'br' ) ).toHaveLength( 1 );
	} );
} );

describe( 'advanced theme editor preview-only mode', () => {
	test.each( [ false, true ] )( 'allows previewing without updating or submitting the edited config when Custom Themes is %s', ( customThemes ) => {
		const originalConfig = '{"theme":{"palette":{"primary":{"main":"#123456"}}}}';

		jest.clearAllMocks();
		bootGeneral( {}, true, {
			configParams: originalConfig,
			customThemes,
			previewOnly: true,
		} );

		const $editor = $( '.hcaptcha-theme-editor' );
		const $configParams = $editor.find( 'textarea' );
		const $preservedConfig = $( '[data-theme-editor-original-config]' );

		expect( $editor.parent().is( 'form.hcaptcha-general' ) ).toBe( true );
		expect( $editor.attr( 'aria-disabled' ) ).toBe( 'false' );
		expect( $( '[data-theme-editor-open]' ).prop( 'disabled' ) ).toBe( false );
		expect( $configParams.prop( 'disabled' ) ).toBe( false );
		expect( $configParams.attr( 'name' ) ).toBeUndefined();
		expect( $preservedConfig.attr( 'name' ) ).toBe( 'hcaptcha_settings[config_params]' );
		expect( $preservedConfig.val() ).toBe( originalConfig );

		$( '[data-theme-editor-open]' ).trigger( 'click' );
		expect( $editor.prop( 'hidden' ) ).toBe( false );

		hCaptcha.setParams.mockClear();
		$( '[data-theme-color-path="palette--primary--main"]' ).val( '#654321' ).trigger( 'input' );

		expect( $( '[data-theme-editor-preview-stage]' ).css( '--hcap-palette-primary' ) ).toBe( '#654321' );
		expect( $preservedConfig.val() ).toBe( originalConfig );
		expect( hCaptcha.setParams ).not.toHaveBeenCalled();
		expect( $( '.hcaptcha-theme-editor-dirty' ).first().prop( 'hidden' ) ).toBe( true );
	} );
} );

describe( 'remaining branch coverage', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: true } ), 0 );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'showMessage with undefined message', () => {
		bootGeneral();
		window.__generalTest.interceptConsoleLogs();
		console.error( 'some error' );
		window.__generalTest.showMessage( undefined, 'notice-error' );
		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );

	test( 'showErrorMessage with no args calls showMessage with defaults', () => {
		bootGeneral();
		window.__generalTest.interceptConsoleLogs();
		console.error( 'err' );
		window.__generalTest.showErrorMessage();
		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );

	test( 'reset section removes its overrides and preserves other params', () => {
		bootGeneral();
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		$cfg.val( '{"foo":1,"theme":{"palette":{"mode":"dark"}}}' ).trigger( 'input' );
		$( '[data-theme-editor-reset-section]' ).trigger( 'click' );

		const config = JSON.parse( $cfg.val() );

		expect( config.foo ).toBe( 1 );
		expect( config.theme.palette.mode ).toBe( 'light' );
		expect( config.theme.palette.primary.main ).toBe( '#00838f' );
	} );

	test.each( [
		[ 'section', '[data-theme-editor-reset-section]' ],
		[ 'theme', '[data-theme-editor-reset-theme]' ],
	] )( 'reset %s clears the dirty status after restoring initial defaults', ( type, resetSelector ) => {
		bootGeneral( {}, true, { customThemes: true } );
		const $status = $( '.hcaptcha-theme-editor-dirty' ).first();

		$( '[data-theme-color-path="palette--primary--main"]' ).val( '#123456' ).trigger( 'input' );

		expect( $status.text() ).toBe( 'Unsaved changes' );
		expect( $status.prop( 'hidden' ) ).toBe( false );

		$( resetSelector ).trigger( 'click' );

		expect( $status.text() ).toBe( '' );
		expect( $status.prop( 'hidden' ) ).toBe( true );
	} );

	test( 'dirty comparison normalizes JSON order, formatting, hex case, and explicit defaults', () => {
		bootGeneral( {}, true, {
			configParams: '{"z":1,"a":{"second":2,"first":1}}',
			customThemes: true,
		} );
		const $status = $( '.hcaptcha-theme-editor-dirty' ).first();
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		$cfg.val( JSON.stringify( {
			theme: {
				palette: {
					primary: {
						main: '#00838F',
					},
				},
			},
			a: {
				first: 1,
				second: 2,
			},
			z: 1,
		}, null, 2 ) ).trigger( 'input' );

		expect( $status.text() ).toBe( '' );
		expect( $status.prop( 'hidden' ) ).toBe( true );
	} );

	test( 'applyCustomThemes accepts empty Config Params as an object', () => {
		bootGeneral();
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		$cfg.val( '' ).trigger( 'input' );

		expect( hCaptcha.setParams ).toHaveBeenCalled();
		expect( hCaptcha.setParams.mock.calls.slice( -1 )[ 0 ][ 0 ].theme ).toEqual( {} );
	} );

	test( 'credentials changed twice does not re-show notice', () => {
		bootGeneral();
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const $submit = $( '#submit' );

		// The first change — triggers credentialsChanged.
		$site.val( 'key1' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();

		// Second change — credentialsChanged already true, else-if skipped.
		$site.val( 'key2' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();
	} );

	test( 'enterprise settings changed twice does not re-show notice', () => {
		bootGeneral();
		const $asset = $( "[name='hcaptcha_settings[asset_host]']" );
		const $submit = $( '#submit' );

		$asset.val( 'changed1.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();

		$asset.val( 'changed2.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBeUndefined();
	} );

	test( 'keydown on non-readonly input is not prevented', () => {
		bootGeneral();
		$( "select[name='hcaptcha_settings[mode]']" ).val( 'live' ).trigger( 'change' );
		const $siteKey = $( '#site_key' );
		const event = $.Event( 'keydown.hcaptchaHelper' );
		$siteKey.trigger( event );
		expect( event.isDefaultPrevented() ).toBe( false );
	} );
	test( 'clicking Save with changed credentials triggers checkConfig and scrolls', async () => {
		bootGeneral();
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const $submit = $( '#submit' );
		const $check = $( '#check_config' );
		// Mock animation
		const animSpy = jest.spyOn( $.fn, 'animate' ).mockImplementation( ( params, duration, callback ) => {
			if ( typeof callback === 'function' ) {
				callback();
			}
			return $.fn;
		} );
		const offsetSpy = jest.spyOn( $.fn, 'offset' ).mockReturnValue( { top: 123, left: 0 } );
		// Mock check button handler
		const checkClickSpy = jest.fn();
		$check.on( 'click', checkClickSpy );
		// Change credentials
		$site.val( 'new-key' ).trigger( 'change' );
		const event = $.Event( 'click' );
		$submit.trigger( event );
		expect( event.isDefaultPrevented() ).toBe( true );
		expect( animSpy ).toHaveBeenCalled();
		expect( checkClickSpy ).toHaveBeenCalled();
		animSpy.mockRestore();
		offsetSpy.mockRestore();
	} );

	test( 'scriptUpdate with empty enterprise values and empty api_host', () => {
		bootGeneral();
		// Clear all enterprise inputs.
		$( '.hcaptcha-section-enterprise + table input' ).each( function() {
			$( this ).val( '' );
		} );
		// Trigger enterprise change to call scriptUpdate.
		$( "[name='hcaptcha_settings[asset_host]']" ).trigger( 'change' );
		const script = document.getElementById( 'hcaptcha-api' );
		expect( script.src ).toContain( 'js.hcaptcha.com' );
	} );

	test( 'checkConfig click with solved captcha calls onAction which invokes hCaptchaBindEvents', () => {
		// Use the default kaggDialog mock that calls onAction.
		bootGeneral();
		$( '.hcaptcha-general-sample-hcaptcha textarea[name="h-captcha-response"]' ).val( '' );
		$( '#check_config' ).trigger( 'click' );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalled();
	} );
} );

// Isolated tests merged from general-console.test.js
describe( 'getCleanConsoleLogs (isolated)', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		// Boot full general to ensure hooks and test exposure are present
		bootGeneral();
		// Re-initialize the console intercept explicitly to ensure a clean buffer
		window.__generalTest.interceptConsoleLogs();
	} );

	test( 'collects only string args, prefixes with type, filters ignored phrases, and clears buffer after read', () => {
		// Emit mixed console messages
		console.log( 'hello', { foo: 1 }, 'recaptchacompat disabled', 123 );
		console.warn( 'warn-msg', [ 1, 2, 3 ] );
		console.info( 'info-msg' );
		console.error( 'err-1', 'Missing sitekey - https://docs.hcaptcha.com/configuration#javascript-api', 'err-2' );
		// A call with no string arguments should yield an empty line in the aggregated logs
		console.log( { only: 'object' } );

		const out1 = window.__generalTest.getCleanConsoleLogs();

		// Should include only the non-ignored string arguments with proper prefixes
		expect( out1 ).toContain( 'Console log: hello' );
		expect( out1 ).toContain( 'Console warn: warn-msg' );
		expect( out1 ).toContain( 'Console info: info-msg' );
		// From error: includes the first string, skips the Missing sitekey line, includes trailing string
		expect( out1 ).toContain( 'Console error: err-1' );
		expect( out1 ).toContain( 'Console error: err-2' );
		expect( out1 ).not.toContain( 'Missing sitekey - https://docs.hcaptcha.com/configuration#javascript-api' );
		expect( out1 ).not.toContain( 'recaptchacompat disabled' );

		// There should be at least one empty line for the non-string-only console call
		// Split by \n and ensure an empty string exists
		const lines = out1.split( '\n' );
		expect( lines ).toEqual( expect.arrayContaining( [ '' ] ) );

		// Later call must return empty because the buffer is cleared
		const out2 = window.__generalTest.getCleanConsoleLogs();
		expect( out2 ).toBe( '' );
	} );

	test( 'console.clear empties the internal buffer used by getCleanConsoleLogs', () => {
		console.log( 'to-be-cleared' );
		// clear should reset the internal array and call through
		console.clear();
		const out = window.__generalTest.getCleanConsoleLogs();
		expect( out ).toBe( '' );
	} );
} );

describe( 'additional general coverage', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	afterEach( () => {
		jest.useRealTimers();
		$( document ).off( 'mousedown.hcaptchaHelper' );
	} );

	test( 'showMessage can be called with default arguments', () => {
		window.__generalTest.showMessage();

		expect( $( '#hcaptcha-message' ).hasClass( 'notice' ) ).toBe( false );
	} );

	test( 'disabled key helper blocks aria-disabled events and allows editable events', () => {
		const siteKey = document.getElementById( 'site_key' );
		const ariaEvent = $.Event( 'keydown', { currentTarget: siteKey } );
		ariaEvent.preventDefault = jest.fn();
		siteKey.setAttribute( 'aria-disabled', 'true' );

		$( siteKey ).trigger( ariaEvent );
		expect( ariaEvent.preventDefault ).toHaveBeenCalled();

		siteKey.removeAttribute( 'aria-disabled' );
		const editableEvent = $.Event( 'keydown', { currentTarget: siteKey } );
		editableEvent.preventDefault = jest.fn();
		$( siteKey ).trigger( editableEvent );

		expect( editableEvent.preventDefault ).not.toHaveBeenCalled();
	} );

	test( 'submit click returns when credentials and enterprise settings are unchanged', () => {
		const event = $.Event( 'click' );
		event.preventDefault = jest.fn();

		$( '#submit' ).trigger( event );

		expect( event.preventDefault ).not.toHaveBeenCalled();
	} );
} );
