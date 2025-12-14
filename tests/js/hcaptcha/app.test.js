// noinspection JSUnresolvedFunction,JSUnresolvedVariable

// Import the app.js file to ensure global functions are defined
import '../../../src/js/hcaptcha/app.js';
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

	test( 'hCaptchaBindEvents should register synced listener', () => {
		window.hCaptchaBindEvents();
		expect( hCaptcha.addSyncedEventListener ).toHaveBeenCalled();
		// addSyncedEventListener is called without a callback.
		// It uses the global window.hCaptchaSyncedEventListenerCallback.
		expect( hCaptcha.addSyncedEventListener ).toHaveBeenCalledWith();
	} );

	test( 'hCaptchaSubmit should call submit', () => {
		window.hCaptchaSubmit();
		expect( hCaptcha.submit ).toHaveBeenCalled();
	} );

	test( 'hCaptchaOnLoad should register synced event listener via hCaptchaBindEvents', () => {
		window.hCaptchaOnLoad();
		expect( hCaptcha.addSyncedEventListener ).toHaveBeenCalled();
		expect( hCaptcha.addSyncedEventListener ).toHaveBeenCalledWith();
	} );

	test( 'hCaptchaSyncedEventListenerCallback should dispatch events, bind and trigger Loaded via onLoad handler', () => {
		// Set up onLoad, which will subscribe to hCaptchaAfterBindEvents and call hCaptchaLoaded
		window.hCaptchaOnLoad();

		let beforeCalled = false;
		let loadedCalled = false;

		document.addEventListener( 'hCaptchaBeforeBindEvents', () => {
			beforeCalled = true;
		} );

		document.addEventListener( 'hCaptchaLoaded', () => {
			loadedCalled = true;
		} );

		// Simulate "synchronous loading" — the library called the global callback
		window.hCaptchaSyncedEventListenerCallback();

		expect( beforeCalled ).toBe( true );
		expect( hCaptcha.bindEvents ).toHaveBeenCalled();
		expect( loadedCalled ).toBe( true );
	} );
} );
