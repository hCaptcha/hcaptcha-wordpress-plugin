// noinspection JSUnresolvedFunction,JSUnresolvedVariable

// Mock ReadyGate so that runWhenReady executes the callback immediately
jest.mock( '../../../src/js/hcaptcha/ready-gate', () => {
	// noinspection JSUnusedGlobalSymbols
	return jest.fn().mockImplementation( () => ( {
		runWhenReady: ( cb ) => cb(),
	} ) );
} );

import HCaptcha from '../../../src/js/hcaptcha/hcaptcha.js';

jest.mock( '../../../src/js/hcaptcha/hcaptcha.js', () => {
	const mockHCaptcha = {
		getWidgetId: jest.fn(),
		reset: jest.fn(),
		bindEvents: jest.fn(),
		addSyncedEventListener: jest.fn(),
		submit: jest.fn(),
	};
	return jest.fn( () => mockHCaptcha );
} );

// Import app.js after mocks so that the mocked ReadyGate is used
import '../../../src/js/hcaptcha/app.js';

describe( 'app.js', () => {
	let hCaptcha;

	beforeEach( () => {
		// Update instance and reset mock counters between tests
		hCaptcha = new HCaptcha();
		jest.clearAllMocks();
		global.hCaptcha = hCaptcha;
	} );

	test( 'hCaptchaGetWidgetId should call getWidgetId with the given element', () => {
		const mockEl = {};
		window.hCaptchaGetWidgetId( mockEl );
		expect( hCaptcha.getWidgetId ).toHaveBeenCalledWith( mockEl );
	} );

	test( 'hCaptchaReset should call reset with the given element', () => {
		const mockEl = {};
		window.hCaptchaReset( mockEl );
		expect( hCaptcha.reset ).toHaveBeenCalledWith( mockEl );
	} );

	test( 'hCaptchaBindEvents should call bindEvents and dispatch Before/After events', () => {
		let beforeCalled = false;
		let afterCalled = false;

		document.addEventListener( 'hCaptchaBeforeBindEvents', () => {
			beforeCalled = true;
		} );

		document.addEventListener( 'hCaptchaAfterBindEvents', () => {
			afterCalled = true;
		} );

		window.hCaptchaBindEvents();

		expect( beforeCalled ).toBe( true );
		expect( hCaptcha.bindEvents ).toHaveBeenCalled();
		expect( afterCalled ).toBe( true );
	} );

	test( 'hCaptchaSubmit should call submit', () => {
		window.hCaptchaSubmit();
		expect( hCaptcha.submit ).toHaveBeenCalled();
	} );

	test( 'hCaptchaOnLoad should subscribe and trigger hCaptchaLoaded after hCaptchaAfterBindEvents', () => {
		let loadedCalled = false;

		document.addEventListener( 'hCaptchaLoaded', () => {
			loadedCalled = true;
		} );

		window.hCaptchaOnLoad();

		// Simulate bindEvents completion
		document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );

		expect( loadedCalled ).toBe( true );
	} );

	test( 'Integration: onLoad + bindEvents dispatch Before, call bindEvents and trigger Loaded', () => {
		window.hCaptchaOnLoad();

		let beforeCalled = false;
		let loadedCalled = false;

		document.addEventListener( 'hCaptchaBeforeBindEvents', () => {
			beforeCalled = true;
		} );

		document.addEventListener( 'hCaptchaLoaded', () => {
			loadedCalled = true;
		} );

		// Start the binding flow (runWhenReady will immediately invoke the callback due to the mock)
		window.hCaptchaBindEvents();

		expect( beforeCalled ).toBe( true );
		expect( hCaptcha.bindEvents ).toHaveBeenCalled();
		expect( loadedCalled ).toBe( true );
	} );
} );
