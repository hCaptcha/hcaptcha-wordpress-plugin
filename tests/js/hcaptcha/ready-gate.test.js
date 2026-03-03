// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/**
 * @file Tests for ReadyGate class.
 */

import ReadyGate from '../../../src/js/hcaptcha/ready-gate';

describe( 'ReadyGate', () => {
	let addEventSpy;
	let listeners;

	beforeEach( () => {
		listeners = {};
		addEventSpy = jest.spyOn( document, 'addEventListener' ).mockImplementation( ( event, handler ) => {
			listeners[ event ] = handler;
		} );
	} );

	afterEach( () => {
		addEventSpy.mockRestore();
		delete global.hcaptcha;
	} );

	test( 'resolves immediately when DOM is ready and hcaptcha is defined', async () => {
		// readyState is already 'complete' in jsdom, and hcaptcha is defined.
		global.hcaptcha = {};

		const gate = new ReadyGate();
		const callback = jest.fn();

		await gate.runWhenReady( callback );

		expect( callback ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'waits for hCaptchaOnLoad when hcaptcha is undefined', async () => {
		delete global.hcaptcha;

		const gate = new ReadyGate();
		const callback = jest.fn();

		const promise = gate.runWhenReady( callback );

		// Not resolved yet.
		await Promise.resolve();
		expect( callback ).not.toHaveBeenCalled();

		// Simulate hCaptchaOnLoad event.
		listeners.hCaptchaOnLoad();

		await promise;

		expect( callback ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'waits for DOMContentLoaded when document is loading', async () => {
		global.hcaptcha = {};

		// Override readyState to 'loading'.
		const desc = Object.getOwnPropertyDescriptor( Document.prototype, 'readyState' ) ||
			Object.getOwnPropertyDescriptor( document, 'readyState' );
		Object.defineProperty( document, 'readyState', {
			configurable: true,
			get: () => 'loading',
		} );

		const gate = new ReadyGate();
		const callback = jest.fn();

		const promise = gate.runWhenReady( callback );

		await Promise.resolve();
		expect( callback ).not.toHaveBeenCalled();

		// Simulate DOMContentLoaded.
		listeners.DOMContentLoaded();

		await promise;

		expect( callback ).toHaveBeenCalledTimes( 1 );

		// Restore readyState.
		if ( desc ) {
			Object.defineProperty( document, 'readyState', desc );
		} else {
			delete document.readyState;
		}
	} );

	test( 'waits for both DOMContentLoaded and hCaptchaOnLoad', async () => {
		delete global.hcaptcha;

		const desc = Object.getOwnPropertyDescriptor( Document.prototype, 'readyState' ) ||
			Object.getOwnPropertyDescriptor( document, 'readyState' );
		Object.defineProperty( document, 'readyState', {
			configurable: true,
			get: () => 'loading',
		} );

		const gate = new ReadyGate();
		const callback = jest.fn();

		const promise = gate.runWhenReady( callback );

		await Promise.resolve();
		expect( callback ).not.toHaveBeenCalled();

		// Fires only DOM — still not resolved.
		listeners.DOMContentLoaded();
		await Promise.resolve();
		expect( callback ).not.toHaveBeenCalled();

		// Fire hCaptchaOnLoad — now resolved.
		listeners.hCaptchaOnLoad();
		await promise;

		expect( callback ).toHaveBeenCalledTimes( 1 );

		if ( desc ) {
			Object.defineProperty( document, 'readyState', desc );
		} else {
			delete document.readyState;
		}
	} );

	test( 'ready() returns the same promise', () => {
		global.hcaptcha = {};

		const gate = new ReadyGate();

		expect( gate.ready() ).toBe( gate.ready() );
	} );

	test( 'registers DOMContentLoaded and hCaptchaOnLoad listeners with once option', () => {
		global.hcaptcha = {};

		// Use real addEventListener to check options.
		addEventSpy.mockRestore();
		const realSpy = jest.spyOn( document, 'addEventListener' );

		// noinspection JSUnusedLocalSymbols
		const gate = new ReadyGate(); // eslint-disable-line no-unused-vars

		const domCall = realSpy.mock.calls.find( ( c ) => c[ 0 ] === 'DOMContentLoaded' );
		const hcapCall = realSpy.mock.calls.find( ( c ) => c[ 0 ] === 'hCaptchaOnLoad' );

		expect( domCall ).toBeDefined();
		expect( domCall[ 2 ] ).toEqual( { once: true } );
		expect( hcapCall ).toBeDefined();
		expect( hcapCall[ 2 ] ).toEqual( { once: true } );

		realSpy.mockRestore();
	} );

	test( '_tryResolve does not throw on repeated calls after resolve', async () => {
		global.hcaptcha = {};

		const gate = new ReadyGate();

		await gate.ready();

		// Calling _tryResolve again should not throw.
		expect( () => gate._tryResolve() ).not.toThrow();
	} );
} );
