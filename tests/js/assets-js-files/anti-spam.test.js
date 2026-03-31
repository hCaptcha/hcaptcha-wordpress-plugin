// noinspection JSUnresolvedReference
// noinspection JSUnresolvedFunction, JSUnresolvedVariable

/* eslint-disable no-console */
import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Anti-Spam object defaults
const defaultAntiSpamObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	checkIPsAction: 'hcap_check_ips',
	checkIPsNonce: 'nonce2',
	configuredAntiSpamProviderError: 'Provider %1$s is not supported',
	configuredAntiSpamProviders: [],
};

global.HCaptchaAntiSpamObject = { ...defaultAntiSpamObject };

let consoleLogSpy;
let consoleWarnSpy;
let consoleInfoSpy;
let consoleErrorSpy;
let consoleClearSpy;

beforeEach( () => {
	consoleLogSpy = jest.spyOn( console, 'log' ).mockImplementation( () => {} );
	consoleWarnSpy = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	consoleInfoSpy = jest.spyOn( console, 'info' ).mockImplementation( () => {} );
	consoleErrorSpy = jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	consoleClearSpy = jest.spyOn( console, 'clear' ).mockImplementation( () => {} );
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
	return `
<html lang="en">
<body>
<div id="wpwrap">
	<div class="hcaptcha-header-bar"></div>
	<form class="hcaptcha-anti-spam">
		<input id="submit" type="submit" />

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
</body>
</html>
	`;
}

function bootAntiSpam( domOverrides = {} ) {
	jest.resetModules();
	document.body.innerHTML = getDom();
	Object.assign( window.HCaptchaAntiSpamObject, defaultAntiSpamObject, domOverrides );
	require( '../../../assets/js/settings-base.js' );
	require( '../../../assets/js/anti-spam.js' );
	window.hCaptchaAntiSpam( $ );
}

describe( 'anti-spam provider', () => {
	jest.useFakeTimers();
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( ( opts ) => {
			const d = $.Deferred();
			opts?.beforeSend?.();
			setTimeout( () => d.resolve( { success: true, data: 'OK' } ), 0 );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'anti-spam provider error is shown when provider not in allowed list', () => {
		bootAntiSpam( { configuredAntiSpamProviders: [] } );
		const sel = $( "select[name='hcaptcha_settings[antispam_provider]']" );
		sel.val( 'akismet' ).trigger( 'change' );
		expect( document.querySelectorAll( "[name='hcaptcha_settings[antispam_provider]']" ).length ).toBe( 1 );
		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );

	test( 'anti-spam provider in allowed list does not show error', () => {
		bootAntiSpam( { configuredAntiSpamProviders: [ 'none', 'akismet' ] } );
		const sel = $( "select[name='hcaptcha_settings[antispam_provider]']" );
		sel.val( 'akismet' ).trigger( 'change' );
		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( false );
	} );

	test( 'checkIPs: early return on empty, otherwise toggles loading and handles error', async () => {
		bootAntiSpam();
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
		expect( $area.css( 'background-color' ) ).toBe( 'rgb(252, 240, 240)' );
		expect( document.querySelector( '#hcaptcha-message' ).className ).toContain( 'notice-error' );
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
		bootAntiSpam();
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
		bootAntiSpam();
		$( '#blacklisted_ips' ).val( '2.2.2.2' ).trigger( 'blur' );
		jest.runAllTimers();
		await Promise.resolve();

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );
} );
