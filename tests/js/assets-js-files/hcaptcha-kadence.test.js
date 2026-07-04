// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Kadence frontend', () => {
	let OriginalXMLHttpRequest;
	let originalSendMock;

	function installXHRMock() {
		OriginalXMLHttpRequest = window.XMLHttpRequest;
		originalSendMock = jest.fn();

		function MockXMLHttpRequest() {
			this.onreadystatechange = null;
			this.readyState = 0;
		}

		MockXMLHttpRequest.DONE = 4;
		MockXMLHttpRequest.prototype.send = originalSendMock;
		window.XMLHttpRequest = MockXMLHttpRequest;
		global.XMLHttpRequest = MockXMLHttpRequest;
	}

	function loadKadence() {
		jest.resetModules();
		installXHRMock();
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		window.hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = window.hCaptchaBindEvents;

		require( '../../../assets/js/hcaptcha-kadence.js' );
	}

	beforeEach( () => {
		loadKadence();
	} );

	afterEach( () => {
		window.XMLHttpRequest = OriginalXMLHttpRequest;
		global.XMLHttpRequest = OriginalXMLHttpRequest;
		delete window.wp;
		delete global.wp;
		delete window.hCaptchaBindEvents;
		delete global.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'registers Kadence submit selectors and ajax submit button detection', () => {
		const selectorFilter = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const ajaxFilter = window.wp.hooks.addFilter.mock.calls[ 1 ][ 2 ];
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		submitButton.classList.add( 'kb-forms-submit' );

		expect( selectorFilter( 'button[type="submit"]' ) ).toBe( 'button[type="submit"], button.kb-forms-submit' );
		expect( ajaxFilter( false, submitButton ) ).toBe( true );
		expect( ajaxFilter( false, otherButton ) ).toBe( false );
		expect( ajaxFilter( true, otherButton ) ).toBe( true );
	} );

	test( 'ignores XHR sends without hCaptcha response payload', () => {
		const xhr = new XMLHttpRequest();

		xhr.send( 'field=value' );
		xhr.send( new FormData() );

		expect( originalSendMock ).not.toHaveBeenCalled();
	} );

	test( 'wraps matching XHR state changes and calls original callback', () => {
		const xhr = new XMLHttpRequest();
		const originalStateChange = jest.fn();

		xhr.onreadystatechange = originalStateChange;
		xhr.send( 'h-captcha-response=token' );
		expect( originalSendMock ).toHaveBeenCalledWith( 'h-captcha-response=token' );

		xhr.readyState = 3;
		xhr.onreadystatechange( 'progress' );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( originalStateChange ).toHaveBeenCalledWith( 'progress' );

		xhr.readyState = XMLHttpRequest.DONE;
		xhr.onreadystatechange( 'done' );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( originalStateChange ).toHaveBeenCalledWith( 'done' );
	} );

	test( 'wraps matching XHR state changes without original callback', () => {
		const xhr = new XMLHttpRequest();

		xhr.send( 'h-captcha-response=token' );
		xhr.readyState = XMLHttpRequest.DONE;

		expect( () => xhr.onreadystatechange() ).not.toThrow();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
