// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha ACFE', () => {
	let hcaptchaParams;

	function initParams() {
		return {
			callback: jest.fn(),
			'error-callback': jest.fn(),
			'expired-callback': jest.fn(),
		};
	}

	// Init params
	hcaptchaParams = initParams();

	// Mock window.hCaptcha object and methods
	window.hCaptcha = {
		getParams: jest.fn( () => hcaptchaParams ),
		setParams: jest.fn( ( params ) => {
			hcaptchaParams = params;
		} ),
	};

	// Mock window.hCaptchaOnLoad
	window.hCaptchaOnLoad = jest.fn();
	const originalOnLoad = window.hCaptchaOnLoad;

	// Provide a minimal jQuery stub for the asset under test
	const jqOn = jest.fn();
	const jqFn = jest.fn( () => ( { on: jqOn } ) );

	global.jQuery = jqFn;
	window.jQuery = jqFn;

	require( '../../../assets/js/hcaptcha-acfe.js' );

	afterEach( () => {
		// Initialize hcaptchaParams
		hcaptchaParams = initParams();
	} );

	test( 'sets custom callbacks and calls original hCaptchaOnLoad', () => {
		window.hCaptchaOnLoad();

		const params = window.hCaptcha.getParams();
		params.callback();
		params[ 'error-callback' ]();
		params[ 'expired-callback' ]();

		expect( window.hCaptcha.getParams ).toHaveBeenCalled();
		expect( window.hCaptcha.setParams ).toHaveBeenCalled();
		expect( originalOnLoad ).toHaveBeenCalled();
	} );
} );
