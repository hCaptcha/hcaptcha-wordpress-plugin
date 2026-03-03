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

// General object defaults
const defaultGeneralObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	badJSONError: 'Bad JSON',
	checkConfigAction: 'hcap_check_config',
	checkConfigNonce: 'nonce1',
	checkConfigNotice: 'Please re-check configuration',
	checkIPsAction: 'hcap_check_ips',
	checkIPsNonce: 'nonce2',
	checkingConfigMsg: 'Checking...',
	completeHCaptchaContent: 'Please solve hCaptcha',
	completeHCaptchaTitle: 'hCaptcha needed',
	configuredAntiSpamProviderError: 'Provider %1$s is not supported',
	configuredAntiSpamProviders: [],
	modeLive: 'live',
	modeTestEnterpriseBotDetected: 'test_ent_bot',
	modeTestEnterpriseBotDetectedSiteKey: 'ent-bot-key',
	modeTestEnterpriseSafeEndUser: 'test_ent_safe',
	modeTestEnterpriseSafeEndUserSiteKey: 'ent-safe-key',
	modeTestPublisher: 'test_pub',
	modeTestPublisherSiteKey: 'pub-key',
	siteKey: 'live-key',
	OKBtnText: 'OK',
	CancelBtnText: 'Cancel',
	toggleSectionAction: 'hcap_toggle',
	toggleSectionNonce: 'nonce3',
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
		<div class="h-captcha"></div>
		<textarea name="h-captcha-response"></textarea>
		<input type="hidden" name="hcaptcha-widget-id" value="wid-1" />
	</div>
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
		<label class="hcaptcha-general-custom-prop"><select>
			<option value="palette=">palette group</option>
			<option value="palette--mode=light" selected>palette--mode=light</option>
			<option value="theme--primary=\#000000">theme--primary=#000000</option>
		</select></label>
		<label class="hcaptcha-general-custom-value"><input type="text" value="light" /></label>
		<textarea name="hcaptcha_settings[config_params]">{}</textarea>
		<label><input type="checkbox" name="hcaptcha_settings[custom_themes][]" /></label>
		<label><input type="checkbox" name="hcaptcha_settings[recaptcha_compat_off][]" /></label>

		<!-- Section toggle target -->
		<h3 class="hcaptcha-section-keys"></h3>

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

		<!-- Anti-spam provider -->
		<table><tbody><tr><td>
		<select name="hcaptcha_settings[antispam_provider]">
			<option value="none" selected>None</option>
			<option value="akismet">Akismet</option>
		</select>
		</td></tr></tbody></table>

		<!-- IP areas to trigger checkIPs() -->
		<textarea id="blacklisted_ips"></textarea>
		<textarea id="whitelisted_ips"></textarea>
	</form>
</div>
<!-- Existing API script to be replaced by scriptUpdate() -->
<script id="hcaptcha-api" src="https://js.hcaptcha.com/1/api.js"></script>
</body>
</html>
	`;
}

// Load modules after DOM is set in each test
function bootGeneral( domOverrides = {} ) {
	document.body.innerHTML = getDom();
	Object.assign( window.HCaptchaGeneralObject, defaultGeneralObject, domOverrides );
	require( '../../../assets/js/settings-base.js' );
	require( '../../../assets/js/general.js' );
	// Trigger jQuery ready
	window.hCaptchaGeneral( $ );
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
				} catch ( e ) {
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

		$( '#check_config' ).trigger( 'click' );
		await Promise.resolve();

		const msg = document.querySelector( '#hcaptcha-message' );
		expect( msg.className ).toContain( 'notice-success' );
		expect( animSpy ).toHaveBeenCalled();
		animSpy.mockRestore();
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
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		$cfg.val( '{bad json' ).trigger( 'input' );
		const submit = document.getElementById( 'submit' );
		expect( submit.getAttribute( 'disabled' ) ).toBe( 'disabled' );
		expect( $cfg.css( 'background-color' ) ).toBe( 'rgb(255, 171, 175)' );
		expect( document.querySelector( '#hcaptcha-message' ).className ).toContain( 'notice-error' );
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

	test( 'checkIPs: early return on empty, otherwise toggles loading and handles error', async () => {
		bootGeneral();
		// Early return
		const $area = $( '#blacklisted_ips' );

		$area.val( '   ' ).trigger( 'blur' );
		expect( postSpy ).toHaveBeenCalledTimes( 0 );

		// Now with value — simulate error success:false
		postSpy.mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: false, data: 'Bad IPs' } ), 0 );
			return d;
		} );

		$area.val( '1.1.1.1' ).trigger( 'blur' );
		await Promise.resolve();
		jest.runAllTimers();
		const parent = $area.parent();
		expect( parent.hasClass( 'hcaptcha-loading' ) ).toBe( false );
		expect( $area.css( 'background-color' ) ).toBe( 'rgb(255, 171, 175)' );
		expect( document.querySelector( '#hcaptcha-message' ).className ).toContain( 'notice-error' );
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

	test( 'anti-spam provider error is shown when provider not in allowed list', () => {
		bootGeneral( { configuredAntiSpamProviders: [] } );
		const sel = $( "select[name='hcaptcha_settings[antispam_provider]']" );
		sel.val( 'akismet' ).trigger( 'change' );
		// The code appends a <div> with an error to the same row
		expect( document.querySelectorAll( "[name='hcaptcha_settings[antispam_provider]']" ).length ).toBe( 1 );
		// Search for the appended div under the same row
		const tr = sel.closest( 'tr' )[ 0 ];
		expect( tr ).toBeTruthy();
		expect( tr.querySelectorAll( 'div' ).length ).toBeGreaterThan( 0 );
	} );

	test( 'credentials change disables submit and checkConfig success re-enables', async () => {
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
		expect( submit.getAttribute( 'disabled' ) ).toBe( 'disabled' );
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

describe( 'checkIPs success and fail branches', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'checkIPs .done with success=true clears bg and enables submit', async () => {
		postSpy.mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: true } ), 0 );
			return d;
		} );
		bootGeneral();
		const $area = $( '#blacklisted_ips' );
		$area.val( '1.1.1.1' ).trigger( 'blur' );
		jest.runAllTimers();
		await Promise.resolve();

		expect( $area[ 0 ].style.backgroundColor ).toBe( '' );
		expect( $( '#submit' ).attr( 'disabled' ) ).toBeUndefined();
	} );

	test( 'checkIPs .fail shows error with statusText', async () => {
		postSpy.mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.reject( { statusText: 'Server error' } ), 0 );
			return d;
		} );
		bootGeneral();
		$( '#blacklisted_ips' ).val( '2.2.2.2' ).trigger( 'blur' );
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

	test( 'reverts submit state when credentials return to initial values', () => {
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const $submit = $( '#submit' );

		// Change credentials to trigger credentialsChanged.
		$site.val( 'changed-key' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );

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

	test( 'reverts submit state when enterprise settings return to initial values', () => {
		bootGeneral();
		const $asset = $( "[name='hcaptcha_settings[asset_host]']" );
		const $submit = $( '#submit' );

		// Change enterprise input to trigger enterpriseSettingsChanged.
		$asset.val( 'changed.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );

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
		const $msg = $( '#hcaptcha-message' );
		$msg.removeClass();

		// Generate a console error so showErrorMessage has content to display.
		window.__generalTest.interceptConsoleLogs();
		console.error( 'test error from hCaptchaLoaded' );

		document.dispatchEvent( new Event( 'hCaptchaLoaded' ) );

		expect( $msg.hasClass( 'notice-error' ) ).toBe( true );
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
			} )
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
		// credentialsChanged should disable the submitting.
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );
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

describe( 'toggleCustomThemeFields and configParams focus', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
	} );

	test( 'customThemes change toggles disabled state of custom fields', () => {
		const $custom = $( "input[name='hcaptcha_settings[custom_themes][]']" );
		const $prop = $( '.hcaptcha-general-custom-prop select' );
		const $val = $( '.hcaptcha-general-custom-value input' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );

		// Initially, unchecked — fields should be disabled.
		expect( $prop.prop( 'disabled' ) ).toBe( true );
		expect( $val.prop( 'disabled' ) ).toBe( true );
		expect( $cfg.prop( 'disabled' ) ).toBe( true );

		// Check it.
		$custom.prop( 'checked', true ).trigger( 'change' );
		expect( $prop.prop( 'disabled' ) ).toBe( false );
		expect( $val.prop( 'disabled' ) ).toBe( false );
		expect( $cfg.prop( 'disabled' ) ).toBe( false );
	} );

	test( 'configParams focus resets background-color', () => {
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		$cfg.css( 'background-color', 'red' );
		$cfg.trigger( 'focus' );
		// jsdom resolves 'unset' to computed value; check the inline style directly.
		expect( $cfg[ 0 ].style.backgroundColor ).toBe( 'unset' );
	} );
} );

describe( 'section toggle h3 click', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			setTimeout( () => d.resolve( { success: true } ), 0 );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'clicking h3 toggles closed class and posts to server', () => {
		bootGeneral();
		const $h3 = $( '.hcaptcha-section-keys' );

		$h3.trigger( 'click' );
		expect( $h3.hasClass( 'closed' ) ).toBe( true );
		expect( postSpy ).toHaveBeenCalledWith( expect.objectContaining( {
			url: HCaptchaGeneralObject.ajaxUrl,
		} ) );

		// Click again to toggle back.
		$h3.trigger( 'click' );
		expect( $h3.hasClass( 'closed' ) ).toBe( false );
	} );

	test( 'section toggle .done with success=false shows error', () => {
		postSpy.mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: false, data: 'Toggle error' } );
			return d;
		} );
		bootGeneral();
		$( '.hcaptcha-section-keys' ).trigger( 'click' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );

	test( 'section toggle .fail shows error', () => {
		postSpy.mockImplementation( () => {
			const d = $.Deferred();
			d.reject( { statusText: 'Toggle fail' } );
			return d;
		} );
		bootGeneral();
		$( '.hcaptcha-section-keys' ).trigger( 'click' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );
} );

describe( 'custom prop/value change handlers', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
		// Enable custom themes.
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
	} );

	test( 'customProp change sets color type and value for non-palette-mode key', () => {
		const $prop = $( '.hcaptcha-general-custom-prop select' );
		const $val = $( '.hcaptcha-general-custom-value input' );

		// Select the color option.
		$prop.find( 'option' ).eq( 2 ).prop( 'selected', true );
		$prop.trigger( 'change' );

		expect( $val.attr( 'type' ) ).toBe( 'color' );
	} );

	test( 'customProp change sets text type for palette--mode key', () => {
		const $prop = $( '.hcaptcha-general-custom-prop select' );
		const $val = $( '.hcaptcha-general-custom-value input' );

		// Select palette--mode option.
		$prop.find( 'option' ).eq( 1 ).prop( 'selected', true );
		$prop.trigger( 'change' );

		expect( $val.attr( 'type' ) ).toBe( 'text' );
		expect( $val.val() ).toBe( 'light' );
	} );

	test( 'customValue input triggers applyCustomThemes with nested params', () => {
		const $prop = $( '.hcaptcha-general-custom-prop select' );
		const $val = $( '.hcaptcha-general-custom-value input' );

		// Select palette--mode option.
		$prop.find( 'option' ).eq( 1 ).prop( 'selected', true );
		$prop.trigger( 'change' );

		// Change value.
		$val.val( 'dark' ).trigger( 'input' );

		// Verify setParams was called (applyCustomThemes calls hCaptchaUpdate, which calls setParams).
		expect( hCaptcha.setParams ).toHaveBeenCalled();
	} );
} );

describe( 'syncConfigParams recursion and selected prop update', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		bootGeneral();
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
	} );

	test( 'applyCustomThemes with nested theme object updates option values and selected custom value', () => {
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		const $val = $( '.hcaptcha-general-custom-value input' );
		const $prop = $( '.hcaptcha-general-custom-prop select' );

		// Select a palette--mode option (which is selected by default).
		$prop.find( 'option' ).eq( 1 ).prop( 'selected', true );

		// Set config with a nested theme containing palette.mode.
		$cfg.val( '{"theme":{"palette":{"mode":"dark"}}}' ).trigger( 'input' );

		// The selected option value should be updated and $customValue should have the value.
		const selectedVal = $prop.find( 'option:selected' ).val();
		expect( selectedVal ).toContain( 'palette--mode=' );
		expect( $val.val() ).toBe( 'dark' );
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

	test( 'syncConfigParams with non-selected prop does not update customValue', () => {
		bootGeneral();
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $prop = $( '.hcaptcha-general-custom-prop select' );
		const $val = $( '.hcaptcha-general-custom-value input' );

		// Select the color option (index 2), not palette--mode.
		$prop.find( 'option' ).eq( 2 ).prop( 'selected', true );
		$prop.find( 'option' ).eq( 1 ).prop( 'selected', false );

		// Set config with palette.mode — the option exists but is NOT selected.
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		$cfg.val( '{"theme":{"palette":{"mode":"dark"}}}' ).trigger( 'input' );

		// customValue should NOT have been updated to 'dark' since palette--mode is not selected.
		expect( $val.val() ).not.toBe( 'dark' );
	} );

	test( 'applyCustomThemes with empty configParams uses null', () => {
		bootGeneral();
		$( "input[name='hcaptcha_settings[custom_themes][]']" ).prop( 'checked', true ).trigger( 'change' );
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		// Empty string → configParamsJson becomes null → JSON.parse(null) = null.
		$cfg.val( '' ).trigger( 'input' );
		// Should not crash; setParams should still be called.
		expect( hCaptcha.setParams ).toHaveBeenCalled();
	} );

	test( 'credentials changed twice does not re-show notice', () => {
		bootGeneral();
		const $site = $( "[name='hcaptcha_settings[site_key]']" );
		const $submit = $( '#submit' );

		// The first change — triggers credentialsChanged.
		$site.val( 'key1' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );

		// Second change — credentialsChanged already true, else-if skipped.
		$site.val( 'key2' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );
	} );

	test( 'enterprise settings changed twice does not re-show notice', () => {
		bootGeneral();
		const $asset = $( "[name='hcaptcha_settings[asset_host]']" );
		const $submit = $( '#submit' );

		$asset.val( 'changed1.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );

		$asset.val( 'changed2.local' ).trigger( 'change' );
		expect( $submit.attr( 'disabled' ) ).toBe( 'disabled' );
	} );

	test( 'keydown on non-readonly input is not prevented', () => {
		bootGeneral();
		$( "select[name='hcaptcha_settings[mode]']" ).val( 'live' ).trigger( 'change' );
		const $siteKey = $( '#site_key' );
		const event = $.Event( 'keydown.hcaptchaHelper' );
		$siteKey.trigger( event );
		expect( event.isDefaultPrevented() ).toBe( false );
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

	test( 'anti-spam provider in allowed list does not show error', () => {
		bootGeneral( { configuredAntiSpamProviders: [ 'akismet' ] } );
		const sel = $( "select[name='hcaptcha_settings[antispam_provider]']" );
		sel.val( 'akismet' ).trigger( 'change' );
		const tr = sel.closest( 'tr' )[ 0 ];
		expect( tr.querySelectorAll( 'div' ).length ).toBe( 0 );
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
