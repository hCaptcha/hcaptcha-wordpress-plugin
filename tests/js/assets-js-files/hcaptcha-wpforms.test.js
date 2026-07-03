// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

function installJQueryReadyProxy() {
	const originalJQuery = global.jQuery || $;
	const proxy = ( selector, context ) => {
		if ( typeof selector === 'function' ) {
			proxy.readyCallback = selector;

			return originalJQuery( document );
		}

		return originalJQuery( selector, context );
	};

	Object.assign( proxy, originalJQuery );
	proxy.fn = originalJQuery.fn;
	global.jQuery = proxy;
	global.$ = proxy;
	window.jQuery = proxy;
	window.$ = proxy;

	return originalJQuery;
}

describe( 'hCaptcha WPForms', () => {
	let originalJQuery;

	beforeEach( () => {
		jest.resetModules();
		originalJQuery = installJQueryReadyProxy();
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaWPForms;

		require( '../../../assets/js/hcaptcha-wpforms.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		global.jQuery = originalJQuery;
		global.$ = originalJQuery;
		window.jQuery = originalJQuery;
		window.$ = originalJQuery;
		delete window.hCaptchaWPForms;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'registers ready callback and rebinds after WPForms ajax success only', () => {
		expect( window.hCaptchaWPForms ).toBeDefined();
		expect( global.jQuery.readyCallback ).toBeUndefined();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=wpforms_submit' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaWPForms = existingApp;

		require( '../../../assets/js/hcaptcha-wpforms.js' );

		expect( window.hCaptchaWPForms ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
