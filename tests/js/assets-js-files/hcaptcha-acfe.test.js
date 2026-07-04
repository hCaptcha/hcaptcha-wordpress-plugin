// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha ACFE', () => {
	let hcaptchaParams;
	let ajaxPrefilterCallback;

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

	window.hCaptchaBindEvents = jest.fn();

	// Mock ACFE reCAPTCHA field type.
	const acfeRecaptchaField = function() {};
	const nativeInitialize = jest.fn();
	const nativeRender = jest.fn();
	const nativeReset = jest.fn();
	const nativeOnInvalidField = jest.fn();

	acfeRecaptchaField.prototype.initialize = nativeInitialize;
	acfeRecaptchaField.prototype.render = nativeRender;
	acfeRecaptchaField.prototype.reset = nativeReset;
	acfeRecaptchaField.prototype.onInvalidField = nativeOnInvalidField;

	window.acf = {
		getFieldType: jest.fn( () => acfeRecaptchaField ),
		val: jest.fn( ( $el, value ) => {
			$el.val( value );
		} ),
	};
	global.acf = window.acf;

	// Mock window.hCaptchaOnLoad
	window.hCaptchaOnLoad = jest.fn();
	const originalOnLoad = window.hCaptchaOnLoad;

	// Provide a minimal jQuery stub for the asset under test
	const jqOn = jest.fn();
	const jqFn = jest.fn( ( arg ) => ( {
		on: jqOn,
		val: jest.fn( ( value ) => {
			if ( arg && 'value' in arg ) {
				arg.value = value;
			}
		} ),
	} ) );

	jqFn.ajaxPrefilter = jest.fn( ( callback ) => {
		ajaxPrefilterCallback = callback;
	} );

	global.jQuery = jqFn;
	window.jQuery = jqFn;

	document.body.innerHTML = '<div class="acfe-field-recaptcha"><input type="hidden" id="acf-field_test" value=""></div>';

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

	test( 'blocks native ACFE reCAPTCHA script requests', () => {
		const jqXHR = { abort: jest.fn() };

		ajaxPrefilterCallback(
			{ url: 'https://www.google.com/recaptcha/api.js?render=explicit' },
			{},
			jqXHR,
		);

		expect( jqFn.ajaxPrefilter ).toHaveBeenCalled();
		expect( jqXHR.abort ).toHaveBeenCalled();
	} );

	test( 'allows unrelated script requests', () => {
		const jqXHR = { abort: jest.fn() };

		ajaxPrefilterCallback(
			{ url: 'https://example.com/script.js' },
			{},
			jqXHR,
		);

		expect( jqXHR.abort ).not.toHaveBeenCalled();
	} );

	test( 'disables native ACFE reCAPTCHA field controller', () => {
		expect( window.acf.getFieldType ).toHaveBeenCalledWith( 'acfe_recaptcha' );
		expect( acfeRecaptchaField.prototype.initialize ).not.toBe( nativeInitialize );
		expect( acfeRecaptchaField.prototype.render ).not.toBe( nativeRender );
		expect( acfeRecaptchaField.prototype.reset ).not.toBe( nativeReset );
		expect( acfeRecaptchaField.prototype.onInvalidField ).not.toBe( nativeOnInvalidField );
	} );

	test( 'syncs submitted token into ACFE hidden field', () => {
		const token = 'submitted-token';
		const input = document.getElementById( 'acf-field_test' );

		window.acf.val.mockClear();
		input.value = '';
		document.dispatchEvent( new CustomEvent( 'hCaptchaSubmitted', { detail: { token } } ) );

		expect( window.acf.val ).toHaveBeenCalled();
		expect( input.value ).toBe( token );

		window.acf.val.mockClear();
		document.dispatchEvent( new CustomEvent( 'hCaptchaSubmitted' ) );

		expect( window.acf.val ).toHaveBeenCalled();
		expect( input.value ).toBe( '' );
	} );

	test( 'removes restored native reCAPTCHA widget on pageshow', () => {
		document.body.innerHTML =
			'<div class="acfe-field-recaptcha">' +
			'<div class="g-recaptcha"><iframe title="reCAPTCHA" src="https://www.google.com/recaptcha/api2/anchor"></iframe></div>' +
			'<iframe title="Widget containing checkbox for hCaptcha security challenge" src="https://js.hcaptcha.com/1/api.js"></iframe>' +
			'<input type="hidden" id="acf-field_test" value="">' +
			'</div>';

		window.hCaptchaBindEvents.mockClear();
		window.dispatchEvent( new Event( 'pageshow' ) );

		expect( document.querySelector( '.g-recaptcha' ) ).toBeNull();
		expect( document.querySelector( 'iframe[title*="hCaptcha"]' ) ).not.toBeNull();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalled();
	} );

	test( 'removes standalone native reCAPTCHA badges', () => {
		document.body.innerHTML = '<div class="acfe-field-recaptcha"><div class="grecaptcha-badge"></div></div>';

		window.hCaptchaACFE.removeRecaptchaNodes();

		expect( document.querySelector( '.grecaptcha-badge' ) ).toBeNull();
	} );

	test( 'direct callbacks and fallback handlers can be invoked', () => {
		expect( () => acfeRecaptchaField.prototype.initialize() ).not.toThrow();
		expect( () => acfeRecaptchaField.prototype.render() ).not.toThrow();
		expect( () => acfeRecaptchaField.prototype.reset() ).not.toThrow();
		expect( () => acfeRecaptchaField.prototype.onInvalidField() ).not.toThrow();

		const callback = jest.fn();
		window.hCaptchaACFE.updateHidden( 'direct-token', callback );
		expect( callback ).toHaveBeenCalledWith( 'direct-token' );

		window.acf = {};
		global.acf = window.acf;
		document.body.innerHTML = '<div class="acfe-field-recaptcha"><input type="hidden" id="acf-field_test" value=""></div>';
		window.hCaptchaACFE.updateHidden( 'fallback-token' );
		expect( document.getElementById( 'acf-field_test' ).value ).toBe( 'fallback-token' );

		delete window.hCaptchaBindEvents;
		expect( () => window.hCaptchaACFE.ajaxCompleteHandler() ).not.toThrow();
	} );

	test( 'prefilter and node cleanup cover fallback source shapes', () => {
		const jqXHR = { abort: jest.fn() };

		ajaxPrefilterCallback(
			{},
			{ url: 'https://www.recaptcha.net/recaptcha/api.js?render=explicit' },
			jqXHR,
		);
		expect( jqXHR.abort ).toHaveBeenCalled();

		const otherXHR = { abort: jest.fn() };
		ajaxPrefilterCallback( {}, {}, otherXHR );
		expect( otherXHR.abort ).not.toHaveBeenCalled();

		document.body.innerHTML = '<div class="acfe-field-recaptcha"><iframe src="https://www.google.com/recaptcha/api2/anchor"></iframe></div>';
		window.hCaptchaACFE.removeRecaptchaNodes();
		expect( document.querySelector( 'iframe' ) ).toBeNull();

		document.body.innerHTML = '<div class="acfe-field-recaptcha"><iframe title="reCAPTCHA"></iframe></div>';
		window.hCaptchaACFE.removeRecaptchaNodes();
		expect( document.querySelector( 'iframe' ) ).toBeNull();
	} );

	test( 'ignores recaptcha iframe when widget container is unavailable', () => {
		const originalQuerySelectorAll = document.querySelectorAll.bind( document );
		const fakeIframe = {
			getAttribute: jest.fn( ( name ) => name === 'src' ? 'https://www.google.com/recaptcha/api2/anchor' : '' ),
			closest: jest.fn( () => null ),
			parentElement: null,
		};

		jest.spyOn( document, 'querySelectorAll' ).mockImplementation( ( selector ) => {
			if ( selector === '.acfe-field-recaptcha iframe' ) {
				return [ fakeIframe ];
			}

			if ( selector === '.acfe-field-recaptcha .g-recaptcha, .acfe-field-recaptcha .grecaptcha-badge' ) {
				return [];
			}

			return originalQuerySelectorAll( selector );
		} );

		expect( () => window.hCaptchaACFE.removeRecaptchaNodes() ).not.toThrow();
		expect( fakeIframe.closest ).toHaveBeenCalledWith( '.g-recaptcha' );

		document.querySelectorAll.mockRestore();
	} );
} );

describe( 'hCaptcha ACFE fresh init branches', () => {
	function makeJQueryStub( withPrefilter = true ) {
		const jq = jest.fn( () => ( {
			on: jest.fn(),
			val: jest.fn(),
		} ) );

		if ( withPrefilter ) {
			jq.ajaxPrefilter = jest.fn();
		}

		return jq;
	}

	function loadFreshAcfe( { withPrefilter = true, acfObject = undefined } = {} ) {
		jest.resetModules();
		delete window.hCaptchaACFE;
		const jq = makeJQueryStub( withPrefilter );
		global.jQuery = jq;
		window.jQuery = jq;
		global.acf = acfObject;
		window.acf = acfObject;
		window.hCaptcha = {
			getParams: jest.fn( () => ( {} ) ),
			setParams: jest.fn(),
		};
		window.hCaptchaOnLoad = jest.fn();
		document.body.innerHTML = '';

		require( '../../../assets/js/hcaptcha-acfe.js' );

		return jq;
	}

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	test( 'init tolerates missing ajaxPrefilter and missing acf object', () => {
		expect( () => loadFreshAcfe( { withPrefilter: false } ) ).not.toThrow();
	} );

	test( 'disableRecaptchaField returns when field type is unavailable', () => {
		const acfObject = {
			getFieldType: jest.fn( () => null ),
		};

		expect( () => loadFreshAcfe( { acfObject } ) ).not.toThrow();
		expect( acfObject.getFieldType ).toHaveBeenCalledWith( 'acfe_recaptcha' );
	} );

	test( 'uses existing app object when it is already present', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaACFE = existingApp;
		global.jQuery = makeJQueryStub();
		window.jQuery = global.jQuery;

		require( '../../../assets/js/hcaptcha-acfe.js' );

		expect( window.hCaptchaACFE ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
