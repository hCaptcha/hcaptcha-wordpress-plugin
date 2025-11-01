// noinspection JSUnresolvedFunction,JSUnresolvedVariable
/* eslint-disable no-console */
import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock settings base minimal API used by general.js messages
global.hCaptchaSettingsBase = {
	getStickyHeight: () => 50,
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

// kaggDialog mock
beforeEach( () => {
	global.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg?.onAction?.( true ) ) };
} );

function getDom() {
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
	<form class="hcaptcha-general">
		<input id="submit" type="submit" />
		<input id="check_config" type="button" />
		<input id="reset_notifications" type="button" />
		<select name="hcaptcha_settings[mode]">
			<option value="live">live</option>
			<option value="test_pub">test_pub</option>
			<option value="test_ent_safe">test_ent_safe</option>
		</select>
		<input name="hcaptcha_settings[site_key]" value="live-key" />
		<input name="hcaptcha_settings[secret_key]" value="secret" />
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
		// Default $.post mock: call the beforeSend method then resolve success
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
		expect( $site.attr( 'disabled' ) ).toBe( 'disabled' );
		expect( $secret.attr( 'disabled' ) ).toBe( 'disabled' );
		expect( hCaptcha.setParams ).toHaveBeenCalled();

		$mode.val( 'live' ).trigger( 'change' );
		expect( $site.attr( 'disabled' ) ).toBeUndefined();
		expect( $secret.attr( 'disabled' ) ).toBeUndefined();
	} );

	test( 'applyCustomThemes: bad JSON disables submit and shows error', () => {
		bootGeneral();
		const $cfg = $( "textarea[name='hcaptcha_settings[config_params]']" );
		$cfg.val( '{bad json' ).trigger( 'blur' );
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
		$cfg.val( '{"foo":1}' ).trigger( 'blur' );
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
