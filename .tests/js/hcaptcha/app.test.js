// noinspection JSUnresolvedFunction,JSUnresolvedVariable

// Import the app.js file to ensure global functions are defined
import '../../../src/js/hcaptcha/app.js';
import HCaptcha from '../../../src/js/hcaptcha/hcaptcha.js';

jest.mock( '../../../src/js/hcaptcha/hcaptcha.js', () => {
	const mockHCaptcha = {
		getWidgetId: jest.fn(),
		reset: jest.fn(),
		bindEvents: jest.fn(),
		submit: jest.fn(),
	};
	return jest.fn( () => mockHCaptcha );
} );

describe( 'app.js', () => {
	let hCaptcha;

	beforeEach( () => {
		hCaptcha = new HCaptcha();
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

	test( 'hCaptchaBindEvents should call bindEvents', () => {
		window.hCaptchaBindEvents();
		expect( hCaptcha.bindEvents ).toHaveBeenCalled();
	} );

	test( 'hCaptchaSubmit should call submit', () => {
		window.hCaptchaSubmit();
		expect( hCaptcha.submit ).toHaveBeenCalled();
	} );

	test( 'hCaptchaOnLoad should call bindEvents', () => {
		window.hCaptchaOnLoad();
		expect( hCaptcha.bindEvents ).toHaveBeenCalled();
	} );
} );
