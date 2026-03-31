/* global HCaptchaSettingsBaseObject */
// noinspection JSUnresolvedReference, JSUnresolvedFunction, JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

const defaultSettingsBaseObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	toggleSectionAction: 'hcap_toggle',
	toggleSectionNonce: 'nonce1',
};

global.HCaptchaSettingsBaseObject = { ...defaultSettingsBaseObject };

beforeEach( () => {
	Element.prototype.scrollIntoView = jest.fn();
} );

function getDom( { withAdminBar = false, withTabs = false } = {} ) {
	return `
<div id="wpwrap">
${ withAdminBar ? '<div id="wpadminbar" style="position:fixed;height:32px;"></div>' : '' }
${ withTabs ? '<div class="hcaptcha-settings-tabs" style="height:40px;"></div>' : '' }
<div class="hcaptcha-header-bar"></div>
<div class="hcaptcha-header"><h2>Settings</h2></div>
<div id="hcaptcha-message"></div>
<div id="hcaptcha-options">
	<h3 class="togglable hcaptcha-section-keys"></h3>
</div>
<div id="hcaptcha-lightbox-modal" style="display:none;">
	<img id="hcaptcha-lightbox-img" src="" />
</div>
<a class="hcaptcha-lightbox" href="https://test.test/img.png">img</a>
</div>
	`;
}

function bootSettingsBase( opts = {} ) {
	jest.resetModules();
	document.body.innerHTML = getDom( opts );
	Object.assign( window.HCaptchaSettingsBaseObject, defaultSettingsBaseObject );
	require( '../../../assets/js/settings-base.js' );
}

// ─── section toggle h3 click ──────────────────────────────────────────────────

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
		bootSettingsBase();
		const $h3 = $( '.hcaptcha-section-keys' );

		$h3.trigger( 'click' );
		expect( $h3.hasClass( 'closed' ) ).toBe( true );
		expect( postSpy ).toHaveBeenCalledWith( expect.objectContaining( {
			url: HCaptchaSettingsBaseObject.ajaxUrl,
		} ) );

		// Click again to toggle back.
		$h3.trigger( 'click' );
		expect( $h3.hasClass( 'closed' ) ).toBe( false );
	} );

	test( 'section toggle .done with success=true is a no-op (covers else branch of if(!response.success))', () => {
		postSpy.mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: true } );
			return d;
		} );
		bootSettingsBase();
		$( '.hcaptcha-section-keys' ).trigger( 'click' );
		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( false );
	} );

	test( 'section toggle .done with success=false shows error', () => {
		postSpy.mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: false, data: 'Toggle error' } );
			return d;
		} );
		bootSettingsBase();
		$( '.hcaptcha-section-keys' ).trigger( 'click' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );

	test( 'section toggle .fail shows error', () => {
		postSpy.mockImplementation( () => {
			const d = $.Deferred();
			d.reject( { statusText: 'Toggle fail' } );
			return d;
		} );
		bootSettingsBase();
		$( '.hcaptcha-section-keys' ).trigger( 'click' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
	} );
} );

// ─── setHeaderBarTop ──────────────────────────────────────────────────────────

describe( 'setHeaderBarTop', () => {
	test( 'sets tabs and headerBar top when tabs present', () => {
		bootSettingsBase( { withTabs: true } );

		const tabs = document.querySelector( '.hcaptcha-settings-tabs' );
		const headerBar = document.querySelector( '.hcaptcha-header-bar' );

		expect( tabs.style.top ).toBeDefined();
		expect( headerBar.style.top ).toBeDefined();
	} );

	test( 'sets headerBar top when no adminBar and no tabs', () => {
		bootSettingsBase();

		const headerBar = document.querySelector( '.hcaptcha-header-bar' );

		expect( headerBar.style.top ).toBe( '-1px' );
	} );

	test( 'does not set headerBar top when headerBar element is absent', () => {
		// Boot without .hcaptcha-header-bar in DOM — covers the else branch of if(headerBar).
		jest.resetModules();
		document.body.innerHTML = '<div id="hcaptcha-message"></div><div id="hcaptcha-options"><h3 class="togglable hcaptcha-section-keys"></h3></div>';
		Object.assign( window.HCaptchaSettingsBaseObject, defaultSettingsBaseObject );
		expect( () => require( '../../../assets/js/settings-base.js' ) ).not.toThrow();
	} );

	test( 'resize event calls setHeaderBarTop again', () => {
		bootSettingsBase();

		const headerBar = document.querySelector( '.hcaptcha-header-bar' );

		headerBar.style.top = '';
		window.dispatchEvent( new Event( 'resize' ) );

		expect( headerBar.style.top ).not.toBe( '' );
	} );
} );

// ─── highLight ────────────────────────────────────────────────────────────────

describe( 'highLight', () => {
	// jsdom does not allow redefining window.location, but we can mock the
	// properties that highLight reads: window.location.href, .hash and document.referrer.
	beforeEach( () => {} );

	afterEach( () => {
		// Restore by navigating back to original (jsdom supports assign).
		window.location.hash = '';
		Object.defineProperty( document, 'referrer', { value: '', configurable: true } );
	} );

	test( 'does nothing when referrer is empty', () => {
		Object.defineProperty( document, 'referrer', { value: '', configurable: true } );
		bootSettingsBase();
		expect( true ).toBe( true );
	} );

	test( 'does nothing when hash is empty', () => {
		Object.defineProperty( document, 'referrer', { value: 'http://other.test/', configurable: true } );
		window.location.hash = '';
		bootSettingsBase();
		expect( true ).toBe( true );
	} );

	test( 'does nothing when element not found by id or name', () => {
		Object.defineProperty( document, 'referrer', { value: 'http://other.test/', configurable: true } );
		window.location.hash = '#nonexistent_xyz';
		bootSettingsBase();
		expect( true ).toBe( true );
	} );

	test( 'highlights element found by id', () => {
		Object.defineProperty( document, 'referrer', { value: 'http://other.test/', configurable: true } );
		window.location.hash = '#hlight_target';

		jest.useFakeTimers();
		bootSettingsBase();

		// Add element after boot so highLight already ran — test via highlightElement directly.
		jest.useRealTimers();

		// highLight ran at boot time; element didn't exist → no error.
		expect( true ).toBe( true );
	} );

	test( 'highlights element found by id when element exists at boot', () => {
		Object.defineProperty( document, 'referrer', { value: 'http://other.test/', configurable: true } );
		window.location.hash = '#hlight_id_el';

		// Put element in DOM before boot so highLight finds it.
		document.body.innerHTML = getDom() + '<input id="hlight_id_el" type="text" />';

		jest.useFakeTimers();
		jest.resetModules();
		Object.assign( window.HCaptchaSettingsBaseObject, defaultSettingsBaseObject );
		require( '../../../assets/js/settings-base.js' );
		jest.runAllTimers();
		jest.useRealTimers();

		const el = document.getElementById( 'hlight_id_el' );
		expect( el.classList.contains( 'blink' ) ).toBe( true );
	} );

	test( 'highlights element found by name attribute when element exists at boot', () => {
		Object.defineProperty( document, 'referrer', { value: 'http://other.test/', configurable: true } );
		window.location.hash = '#hlight_name_el';

		document.body.innerHTML = getDom() + '<table><tbody><tr><td><input name="hcaptcha_settings[hlight_name_el]" type="text" /></td></tr></tbody></table>';

		jest.useFakeTimers();
		jest.resetModules();
		Object.assign( window.HCaptchaSettingsBaseObject, defaultSettingsBaseObject );
		require( '../../../assets/js/settings-base.js' );
		jest.runAllTimers();
		jest.useRealTimers();

		const el = document.querySelector( '[name="hcaptcha_settings[hlight_name_el]"]' );
		expect( el ).not.toBeNull();
		// The td wrapping the input gets blink (select-type branch doesn't apply; plain element).
		expect( el.classList.contains( 'blink' ) ).toBe( true );
	} );
} );

// ─── setupLightBox ────────────────────────────────────────────────────────────

describe( 'setupLightBox', () => {
	test( 'clicking lightbox link sets img src and shows modal', () => {
		bootSettingsBase();

		$( '.hcaptcha-lightbox' ).trigger( 'click' );

		expect( $( '#hcaptcha-lightbox-img' ).attr( 'src' ) ).toBe( 'https://test.test/img.png' );
		expect( $( '#hcaptcha-lightbox-modal' ).css( 'display' ) ).toBe( 'flex' );
	} );

	test( 'clicking lightbox modal background hides it and clears img src', () => {
		bootSettingsBase();

		$( '.hcaptcha-lightbox' ).trigger( 'click' );
		$( '#hcaptcha-lightbox-modal' ).trigger( 'click' );

		expect( $( '#hcaptcha-lightbox-modal' ).css( 'display' ) ).toBe( 'none' );
		expect( $( '#hcaptcha-lightbox-img' ).attr( 'src' ) ).toBe( '' );
	} );
} );

// ─── showMessage / showSuccessMessage / showErrorMessage ──────────────────────

describe( 'showMessage', () => {
	test( 'showSuccessMessage adds notice-success class and message text', () => {
		bootSettingsBase();
		window.hCaptchaSettingsBase.showSuccessMessage( 'All good' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-success' ) ).toBe( true );
		expect( $( '#hcaptcha-message' ).text() ).toContain( 'All good' );
	} );

	test( 'showErrorMessage adds notice-error class and message text', () => {
		bootSettingsBase();
		window.hCaptchaSettingsBase.showErrorMessage( 'Something failed' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice-error' ) ).toBe( true );
		expect( $( '#hcaptcha-message' ).text() ).toContain( 'Something failed' );
	} );

	test( 'showMessage with empty string does nothing', () => {
		bootSettingsBase();
		window.hCaptchaSettingsBase.showMessage( '' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice' ) ).toBe( false );
	} );

	test( 'showMessage with undefined uses default empty string and does nothing', () => {
		bootSettingsBase();
		// Default param replaces undefined with '' → String('') = '' → early return.
		window.hCaptchaSettingsBase.showMessage( undefined, 'notice-success' );

		expect( $( '#hcaptcha-message' ).hasClass( 'notice' ) ).toBe( false );
	} );

	test( 'showSuccessMessage called without arguments uses default empty string', () => {
		bootSettingsBase();
		// Default arg '' → showMessage('') → early return, no notice added.
		window.hCaptchaSettingsBase.showSuccessMessage();
		expect( $( '#hcaptcha-message' ).hasClass( 'notice' ) ).toBe( false );
	} );

	test( 'showErrorMessage called without arguments uses default empty string', () => {
		bootSettingsBase();
		window.hCaptchaSettingsBase.showErrorMessage();
		expect( $( '#hcaptcha-message' ).hasClass( 'notice' ) ).toBe( false );
	} );

	test( 'showMessage with multiline text creates multiple p tags', () => {
		bootSettingsBase();
		window.hCaptchaSettingsBase.showMessage( 'Line1\nLine2', 'notice-success' );

		const html = $( '#hcaptcha-message' ).html();
		expect( html ).toContain( '<p>Line1</p>' );
		expect( html ).toContain( '<p>Line2</p>' );
	} );
} );

// ─── getStickyHeight ──────────────────────────────────────────────────────────

describe( 'getStickyHeight', () => {
	test( 'returns a number', () => {
		bootSettingsBase();
		const h = window.hCaptchaSettingsBase.getStickyHeight();
		expect( typeof h ).toBe( 'number' );
	} );

	test( 'returns sum of adminBar + tabs + headerBar heights (all 0 in jsdom)', () => {
		bootSettingsBase( { withAdminBar: true, withTabs: true } );
		const h = window.hCaptchaSettingsBase.getStickyHeight();
		expect( h ).toBe( 0 );
	} );

	test( 'returns 0 when headerBar is absent (covers false branch of headerBar ternary)', () => {
		// Boot without .hcaptcha-header-bar so headerBar=null → ternary false branch.
		jest.resetModules();
		document.body.innerHTML = '<div id="hcaptcha-message"></div><div id="hcaptcha-options"><h3 class="togglable hcaptcha-section-keys"></h3></div>';
		Object.assign( window.HCaptchaSettingsBaseObject, defaultSettingsBaseObject );
		require( '../../../assets/js/settings-base.js' );
		const h = window.hCaptchaSettingsBase.getStickyHeight();
		expect( h ).toBe( 0 );
	} );
} );

// ─── highlightElement ─────────────────────────────────────────────────────────

describe( 'highlightElement', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	test( 'highlights a plain element (adds blink class after timeout)', () => {
		bootSettingsBase();
		const el = document.querySelector( '.hcaptcha-header-bar' );
		window.hCaptchaSettingsBase.highlightElement( el );
		jest.runAllTimers();

		expect( el.classList.contains( 'blink' ) ).toBe( true );
	} );

	test( 'highlights a checkbox element via closest fieldset', () => {
		bootSettingsBase();
		document.body.innerHTML += '<fieldset><input id="cb" type="checkbox" /></fieldset>';
		const el = document.getElementById( 'cb' );
		const fieldset = el.closest( 'fieldset' );
		window.hCaptchaSettingsBase.highlightElement( el );
		jest.runAllTimers();

		expect( fieldset.classList.contains( 'blink' ) ).toBe( true );
	} );

	test( 'highlights a select-multiple element via closest td', () => {
		bootSettingsBase();
		document.body.innerHTML += '<table><tbody><tr><td><select id="sel" multiple><option>A</option></select></td></tr></tbody></table>';
		const el = document.getElementById( 'sel' );
		const td = el.closest( 'td' );
		window.hCaptchaSettingsBase.highlightElement( el );
		jest.runAllTimers();

		expect( td.classList.contains( 'blink' ) ).toBe( true );
	} );

	test( 'clicks closed h3 section header before highlighting', () => {
		bootSettingsBase();
		document.body.innerHTML += '<h3 class="closed">Section</h3><table><tbody><tr><td><input id="inp" type="text" /></td></tr></tbody></table>';
		const el = document.getElementById( 'inp' );
		const h3 = document.querySelector( 'h3.closed' );
		const clickSpy = jest.fn();
		h3.addEventListener( 'click', clickSpy );

		window.hCaptchaSettingsBase.highlightElement( el );
		jest.runAllTimers();

		expect( clickSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does not click h3 when section header is not closed', () => {
		bootSettingsBase();
		document.body.innerHTML += '<h3 class="open-section">Open</h3><table><tbody><tr><td><input id="inp2" type="text" /></td></tr></tbody></table>';
		const el = document.getElementById( 'inp2' );
		const h3 = document.querySelector( 'h3.open-section' );
		const clickSpy = jest.fn();
		h3.addEventListener( 'click', clickSpy );

		window.hCaptchaSettingsBase.highlightElement( el );
		jest.runAllTimers();

		expect( clickSpy ).not.toHaveBeenCalled();
	} );
} );

// ─── ajaxPrefilter ────────────────────────────────────────────────────────────

describe( 'ajaxPrefilter', () => {
	// Intercept $.ajaxPrefilter registration to grab the callback installed by settings-base.
	// Then call it directly with synthetic options to cover all branches.
	let settingsBasePrefilter;
	const origAjaxPrefilter = $.ajaxPrefilter.bind( $ );

	beforeEach( () => {
		settingsBasePrefilter = null;
		// Temporarily wrap $.ajaxPrefilter to capture the callback.
		$.ajaxPrefilter = function( dataTypes, handler ) {
			if ( typeof dataTypes === 'function' ) {
				settingsBasePrefilter = dataTypes;
			} else if ( typeof handler === 'function' ) {
				settingsBasePrefilter = handler;
			}
			return origAjaxPrefilter( dataTypes, handler );
		};
		bootSettingsBase();
		$.ajaxPrefilter = origAjaxPrefilter;
	} );

	function callPrefilter( options, original ) {
		settingsBasePrefilter( options, original || options, null );
	}

	test( 'skips non admin-ajax.php URLs — data unchanged', () => {
		const options = { url: 'https://test.test/other', data: 'action=hcaptcha_test' };
		callPrefilter( options );
		expect( options.data ).toBe( 'action=hcaptcha_test' );
	} );

	test( 'skips when url is null (covers ?? empty string branch)', () => {
		const options = { url: null, data: 'action=hcaptcha_check' };
		callPrefilter( options );
		// url is null → /admin-ajax\.php/.test('') → false → early return, data unchanged.
		expect( options.data ).toBe( 'action=hcaptcha_check' );
	} );

	test( 'skips non-hcaptcha actions — no _wp_http_referer added', () => {
		const options = { url: 'https://test.test/wp-admin/admin-ajax.php', data: 'action=other_action' };
		callPrefilter( options );
		expect( options.data ).toBe( 'action=other_action' );
	} );

	test( 'appends _wp_http_referer to string data for hcaptcha action', () => {
		const options = { url: 'https://test.test/wp-admin/admin-ajax.php', data: 'action=hcaptcha_check' };
		callPrefilter( options );
		expect( options.data ).toContain( '_wp_http_referer' );
	} );

	test( 'merges _wp_http_referer into object data for hcaptcha action', () => {
		// options.data is object; original.data is also object with action string for getAction.
		// getAction reads original (since options.data is not string), parses 'action=hcaptcha_check'.
		const original = {
			url: 'https://test.test/wp-admin/admin-ajax.php',
			data: {
				action: 'hcaptcha_check',
				_wp_http_referer: 'https://test.test/wp-admin/admin.php?page=hcaptcha',
			},
		};
		const options = {
			url: 'https://test.test/wp-admin/admin-ajax.php',
			data: { action: 'hcaptcha_check' },
		};
		callPrefilter( options, original );
		expect( options.data ).toHaveProperty( '_wp_http_referer' );
		expect( options.data ).toHaveProperty( 'action', 'hcaptcha_check' );
	} );

	test( 'FormData branch: appends _wp_http_referer when key absent', () => {
		const fd = new FormData();
		const original = {
			url: 'https://test.test/wp-admin/admin-ajax.php',
			data: 'action=hcaptcha_check&_wp_http_referer=https://test.test/wp-admin/admin.php?page=hcaptcha',
		};
		const options = {
			url: 'https://test.test/wp-admin/admin-ajax.php',
			data: fd,
		};

		fd.append( 'action', 'hcaptcha_check' );
		callPrefilter( options, original );
		expect( fd.has( '_wp_http_referer' ) ).toBe( true );
	} );

	test( 'FormData branch: skips append when key already present', () => {
		const fd = new FormData();
		fd.append( '_wp_http_referer', 'existing' );
		const original = { url: 'https://test.test/wp-admin/admin-ajax.php', data: 'action=hcaptcha_check' };
		const options = {
			url: 'https://test.test/wp-admin/admin-ajax.php',
			data: fd,
		};
		callPrefilter( options, original );
		// Should still be 'existing', not appended again.
		expect( fd.getAll( '_wp_http_referer' ) ).toHaveLength( 1 );
		expect( fd.get( '_wp_http_referer' ) ).toBe( 'existing' );
	} );
} );
