// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* eslint-disable no-unused-vars */

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

const defaultPlaygroundObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	action: 'hcap_update_menu',
	nonce: 'nonce-playground',
};

global.HCaptchaPlaygroundObject = { ...defaultPlaygroundObject };

/**
 * Return the LocationImpl instance backing window.location.
 *
 * @return {Object} The actual LocationImpl instance used by jsdom.
 */
function getLocationImplInstance() {
	for ( const sym of Object.getOwnPropertySymbols( window.location ) ) {
		const val = window.location[ sym ];

		if ( val && typeof val._locationObjectSetterNavigate === 'function' ) {
			return val;
		}
	}

	throw new Error( 'LocationImpl instance not found on window.location' );
}

/**
 * Spy on the LocationImpl prototype hostname getter to control window.location.hostname.
 *
 * @param {string} hostname Hostname to return.
 * @return {jest.SpyInstance} Active spy instance.
 */
function setCurrentHostname( hostname ) {
	const proto = Object.getPrototypeOf( getLocationImplInstance() );

	return jest.spyOn( proto, 'hostname', 'get' ).mockReturnValue( hostname );
}

function getDom( { withAdminBar = true } = {} ) {
	return `<div id="wpwrap">${ withAdminBar ? '<div id="wpadminbar"></div>' : '' }</div>`;
}

function bootPlayground( domOptions = {}, objectOverrides = {}, preRequireHook = null ) {
	jest.resetModules();
	$( document ).off();
	delete window.hCaptchaPlayground;
	document.body.innerHTML = getDom( domOptions );
	global.HCaptchaPlaygroundObject = { ...defaultPlaygroundObject, ...objectOverrides };

	if ( preRequireHook ) {
		preRequireHook();
	}

	require( '../../../assets/js/playground.js' );
}

describe( 'playground.js', () => {
	let postSpy;
	let selfDescriptor;

	beforeEach( () => {
		jest.clearAllMocks();
		selfDescriptor = Object.getOwnPropertyDescriptor( window, 'self' );
		postSpy = jest.spyOn( $, 'post' );
	} );

	afterEach( () => {
		postSpy.mockRestore();
		if ( selfDescriptor ) {
			Object.defineProperty( window, 'self', selfDescriptor );
		}
	} );

	// ── window.hCaptchaPlayground already defined ────────────────────────────

	test( 'already defined: IIFE is skipped, existing init() is called', () => {
		const fakeApp = { init: jest.fn() };
		window.hCaptchaPlayground = fakeApp;
		jest.resetModules();
		require( '../../../assets/js/playground.js' );
		expect( fakeApp.init ).toHaveBeenCalledTimes( 1 );
		delete window.hCaptchaPlayground;
	} );

	// ── fixMenu ──────────────────────────────────────────────────────────────

	test( 'fixMenu: not in iframe → adminBar unchanged', () => {
		// In jsdom window.self === window.top → inIframe = false.
		bootPlayground();
		expect( document.getElementById( 'wpadminbar' ).style.marginTop ).toBe( '' );
	} );

	test( 'fixMenu: hostname null → falls back to empty string, no style change', () => {
		// Covers the right-hand side of the `??` operator on hostname.
		const hostnameSpy = setCurrentHostname( null );
		bootPlayground();
		expect( document.getElementById( 'wpadminbar' ).style.marginTop ).toBe( '' );
		hostnameSpy.mockRestore();
	} );

	test( 'fixMenu: in iframe but wrong hostname → adminBar unchanged', () => {
		Object.defineProperty( window, 'self', { get: () => ( {} ), configurable: true } );
		bootPlayground();
		expect( document.getElementById( 'wpadminbar' ).style.marginTop ).toBe( '' );
	} );

	test( 'fixMenu: in iframe + playground.wordpress.net + adminBar → sets marginTop 4px', () => {
		Object.defineProperty( window, 'self', { get: () => ( {} ), configurable: true } );
		const hostnameSpy = setCurrentHostname( 'playground.wordpress.net' );
		bootPlayground();
		expect( document.getElementById( 'wpadminbar' ).style.marginTop ).toBe( '4px' );
		hostnameSpy.mockRestore();
	} );

	test( 'fixMenu: in iframe + playground.wordpress.net + no adminBar → no error', () => {
		Object.defineProperty( window, 'self', { get: () => ( {} ), configurable: true } );
		const hostnameSpy = setCurrentHostname( 'playground.wordpress.net' );
		expect( () => bootPlayground( { withAdminBar: false } ) ).not.toThrow();
		hostnameSpy.mockRestore();
	} );

	test( 'fixMenu: cross-origin exception → inIframe=true, sets marginTop 4px', () => {
		const hostnameSpy = setCurrentHostname( 'playground.wordpress.net' );
		bootPlayground( {}, {}, () => {
			Object.defineProperty( window, 'self', {
				get: () => {
					throw new Error( 'cross-origin' );
				},
				configurable: true,
			} );
		} );
		expect( document.getElementById( 'wpadminbar' ).style.marginTop ).toBe( '4px' );
		hostnameSpy.mockRestore();
	} );

	// ── ajaxSuccessHandler ───────────────────────────────────────────────────

	test( 'ajaxSuccessHandler: wrong action → updateMenu not called', () => {
		bootPlayground();
		const updateMenuSpy = jest.spyOn( window.hCaptchaPlayground, 'updateMenu' );
		window.hCaptchaPlayground.ajaxSuccessHandler( {}, {}, { data: 'action=something-else' } );
		expect( updateMenuSpy ).not.toHaveBeenCalled();
		updateMenuSpy.mockRestore();
	} );

	test( 'ajaxSuccessHandler: correct action → updateMenu called', () => {
		bootPlayground();
		postSpy.mockReturnValue( { done() {
			return this;
		} } );
		const updateMenuSpy = jest.spyOn( window.hCaptchaPlayground, 'updateMenu' );
		window.hCaptchaPlayground.ajaxSuccessHandler( {}, {}, { data: 'action=hcaptcha-integrations-activate' } );
		expect( updateMenuSpy ).toHaveBeenCalledTimes( 1 );
		updateMenuSpy.mockRestore();
	} );

	// ── updateMenu ───────────────────────────────────────────────────────────

	test( 'updateMenu: posts correct payload', () => {
		bootPlayground();
		postSpy.mockReturnValue( { done() {
			return this;
		} } );
		window.hCaptchaPlayground.updateMenu();
		expect( postSpy ).toHaveBeenCalledWith( {
			url: defaultPlaygroundObject.ajaxUrl,
			data: {
				action: defaultPlaygroundObject.action,
				nonce: defaultPlaygroundObject.nonce,
			},
		} );
	} );

	test( 'updateMenu: response.success=false → hrefs not updated', () => {
		bootPlayground();
		document.body.innerHTML += '<div id="wp-admin-bar-item1"><a href="old">Link</a></div>';
		postSpy.mockImplementation( () => ( {
			done( cb ) {
				cb( { success: false, data: [ { id: 'item1', href: '/new' } ] } );
				return this;
			},
		} ) );
		window.hCaptchaPlayground.updateMenu();
		expect( document.querySelector( '#wp-admin-bar-item1 a' ).getAttribute( 'href' ) ).toBe( 'old' );
	} );

	test( 'updateMenu: response.success=true → hrefs updated for each item', () => {
		bootPlayground();
		document.body.innerHTML += `
			<div id="wp-admin-bar-item1"><a href="old1">Link 1</a></div>
			<div id="wp-admin-bar-item2"><a href="old2">Link 2</a></div>
		`;
		postSpy.mockImplementation( () => ( {
			done( cb ) {
				cb( {
					success: true,
					data: [
						{ id: 'item1', href: '/new1' },
						{ id: 'item2', href: '/new2' },
					],
				} );
				return this;
			},
		} ) );
		window.hCaptchaPlayground.updateMenu();
		expect( document.querySelector( '#wp-admin-bar-item1 a' ).getAttribute( 'href' ) ).toBe( '/new1' );
		expect( document.querySelector( '#wp-admin-bar-item2 a' ).getAttribute( 'href' ) ).toBe( '/new2' );
	} );
} );
