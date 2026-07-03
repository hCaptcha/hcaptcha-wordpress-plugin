// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Spectra', () => {
	let helperMock;

	function loadSpectra() {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaSpectra;

		require( '../../../assets/js/hcaptcha-spectra.js' );
	}

	function eventWithBody( eventName, body, response = undefined ) {
		return new CustomEvent( eventName, {
			detail: {
				args: [ '/spectra', { body } ],
				response,
			},
		} );
	}

	beforeEach( () => {
		document.head.innerHTML = '';
		document.body.innerHTML = '';
		loadSpectra();
	} );

	afterEach( () => {
		if ( window.hCaptchaSpectra ) {
			window.removeEventListener( 'hCaptchaFetch:before', window.hCaptchaSpectra.fetchBefore );
			window.removeEventListener( 'hCaptchaFetch:success', window.hCaptchaSpectra.fetchSuccess );
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaSpectra.fetchComplete );
		}
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaSpectra;
		delete window.hCaptchaBindEvents;
		document.head.innerHTML = '';
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'installs fetch events and adds hCaptcha fields before Spectra fetch', () => {
		document.body.innerHTML = `
			<div class="uagb-block-abc">
				<div class="hcaptcha-error-message">Old error</div>
				<input name="hcaptcha-widget-id" value="widget-id">
				<textarea name="h-captcha-response">response-token</textarea>
				<input name="hcaptcha_spectra_form_nonce" value="nonce-value">
				<input id="hcap_hp_test" value="hp-value">
				<input name="hcap_hp_sig" value="hp-sig">
				<input name="hcap_fst_token" value="fst-token">
			</div>
		`;
		const body = new URLSearchParams();

		body.set( 'block_id', 'abc' );
		body.set( 'action', 'uagb_process_forms' );
		body.set( 'form_data', JSON.stringify( { email: 'person@test.test' } ) );

		window.hCaptchaSpectra.fetchBefore( eventWithBody( 'hCaptchaFetch:before', body ) );

		const formData = JSON.parse( body.get( 'form_data' ) );

		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );
		expect( document.querySelector( '.hcaptcha-error-message' ) ).toBeNull();
		expect( formData[ 'hcaptcha-widget-id' ] ).toBe( 'widget-id' );
		expect( formData[ 'h-captcha-response' ] ).toBe( 'response-token' );
		expect( formData.hcaptcha_spectra_form_nonce ).toBe( 'nonce-value' );
		expect( formData.hcap_hp_test ).toBe( 'hp-value' );
		expect( formData.hcap_hp_sig ).toBe( 'hp-sig' );
		expect( formData.hcap_fst_token ).toBe( 'fst-token' );
	} );

	test( 'uses undefined fallbacks for missing Spectra hCaptcha fields', () => {
		document.body.innerHTML = '<div class="uagb-block-empty"></div>';
		const body = new URLSearchParams();

		body.set( 'block_id', 'empty' );
		body.set( 'action', 'uagb_process_forms' );
		body.set( 'form_data', JSON.stringify( {} ) );

		window.hCaptchaSpectra.fetchBefore( eventWithBody( 'hCaptchaFetch:before', body ) );

		const formData = JSON.parse( body.get( 'form_data' ) );

		expect( formData[ 'hcaptcha-widget-id' ] ).toBeUndefined();
		expect( formData[ 'h-captcha-response' ] ).toBeUndefined();
		expect( formData.hcaptcha_spectra_form_nonce ).toBeUndefined();
		expect( formData.undefined ).toBeUndefined();
	} );

	test( 'ignores Spectra fetchBefore requests that do not need mutation', () => {
		window.hCaptchaSpectra.fetchBefore();
		window.hCaptchaSpectra.fetchBefore( eventWithBody( 'hCaptchaFetch:before', new FormData() ) );

		document.body.innerHTML = '<div class="uagb-block-abc"></div>';
		const otherAction = new URLSearchParams();
		otherAction.set( 'block_id', 'abc' );
		otherAction.set( 'action', 'other_action' );
		otherAction.set( 'form_data', JSON.stringify( {} ) );
		window.hCaptchaSpectra.fetchBefore( eventWithBody( 'hCaptchaFetch:before', otherAction ) );

		const existingResponse = new URLSearchParams();
		existingResponse.set( 'block_id', 'abc' );
		existingResponse.set( 'action', 'uagb_process_forms' );
		existingResponse.set( 'form_data', JSON.stringify( { 'h-captcha-response': 'already-there' } ) );
		window.hCaptchaSpectra.fetchBefore( eventWithBody( 'hCaptchaFetch:before', existingResponse ) );

		expect( JSON.parse( existingResponse.get( 'form_data' ) )[ 'h-captcha-response' ] ).toBe( 'already-there' );
	} );

	test( 'shows Spectra hCaptcha error messages on successful fetch response', async () => {
		document.body.innerHTML = `
			<div class="uagb-block-abc">
				<div class="hcaptcha-error-message">Previous error</div>
				<div><h-captcha></h-captcha></div>
			</div>
		`;
		const body = new URLSearchParams();
		const response = {
			clone: jest.fn( () => ( {
				json: jest.fn( () => Promise.resolve( { data: 'Captcha failed.' } ) ),
			} ) ),
		};

		body.set( 'action', 'uagb_process_forms' );
		body.set( 'block_id', 'abc' );

		await window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, response ) );
		await window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, response ) );

		expect( document.getElementById( 'hcaptcha-style-abc' ) ).not.toBeNull();
		expect( document.querySelectorAll( '.hcaptcha-error-message' ) ).toHaveLength( 1 );
		expect( document.querySelector( '.hcaptcha-error-message' ).textContent ).toBe( 'Captcha failed.' );
	} );

	test( 'ignores Spectra fetchSuccess responses without hCaptcha error data', async () => {
		const body = new URLSearchParams();
		body.set( 'action', 'uagb_process_forms' );
		body.set( 'block_id', 'abc' );

		const badJsonResponse = {
			clone: jest.fn( () => ( {
				json: jest.fn( () => Promise.reject( new Error( 'bad json' ) ) ),
			} ) ),
		};
		const objectDataResponse = {
			clone: jest.fn( () => ( {
				json: jest.fn( () => Promise.resolve( { data: { message: 'No string.' } } ) ),
			} ) ),
		};
		const ignoredActionResponse = {
			clone: jest.fn( () => ( {
				json: jest.fn( () => Promise.resolve( { data: 'Ignored.' } ) ),
			} ) ),
		};

		await expect( window.hCaptchaSpectra.fetchSuccess() ).resolves.toBeUndefined();
		await expect( window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body ) ) ).resolves.toBeUndefined();
		await expect( window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', 'not-form-data', { clone: jest.fn() } ) ) ).resolves.toBeUndefined();
		await expect( window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, badJsonResponse ) ) ).resolves.toBeUndefined();
		await expect( window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, objectDataResponse ) ) ).resolves.toBeUndefined();

		body.set( 'action', 'other_action' );
		await expect( window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, ignoredActionResponse ) ) ).resolves.toBeUndefined();
	} );

	test( 'handles Spectra error response when no hCaptcha container is present', async () => {
		document.body.innerHTML = '<div class="uagb-block-abc"></div>';
		const body = new URLSearchParams();
		const response = {
			clone: jest.fn( () => ( {
				json: jest.fn( () => Promise.resolve( { data: 'Captcha failed.' } ) ),
			} ) ),
		};

		body.set( 'action', 'uagb_process_forms' );
		body.set( 'block_id', 'abc' );

		await window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, response ) );

		expect( document.querySelector( '.hcaptcha-error-message' ) ).toBeNull();
		expect( document.getElementById( 'hcaptcha-style-abc' ) ).not.toBeNull();
	} );
	test( 'removes Spectra style and rebinds after fetch complete only', async () => {
		const body = new URLSearchParams();
		const response = {
			clone: jest.fn( () => ( {
				json: jest.fn( () => Promise.resolve( { data: 'Captcha failed.' } ) ),
			} ) ),
		};

		document.body.innerHTML = '<div class="uagb-block-abc"><h-captcha></h-captcha></div>';
		body.set( 'action', 'uagb_process_forms' );
		body.set( 'block_id', 'abc' );
		await window.hCaptchaSpectra.fetchSuccess( eventWithBody( 'hCaptchaFetch:success', body, response ) );

		window.hCaptchaSpectra.fetchComplete();
		window.hCaptchaSpectra.fetchComplete( eventWithBody( 'hCaptchaFetch:complete', new FormData() ) );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		window.hCaptchaSpectra.fetchComplete( eventWithBody( 'hCaptchaFetch:complete', body ) );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		window.hCaptchaBindEvents.mockClear();

		const otherAction = new URLSearchParams();
		otherAction.set( 'action', 'other_action' );
		window.hCaptchaSpectra.fetchComplete( eventWithBody( 'hCaptchaFetch:complete', otherAction ) );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		window.hCaptchaSpectra.fetchComplete( eventWithBody( 'hCaptchaFetch:complete', body ) );
		expect( document.getElementById( 'hcaptcha-style-abc' ) ).toBeNull();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaSpectra = existingApp;

		require( '../../../assets/js/hcaptcha-spectra.js' );

		expect( window.hCaptchaSpectra ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
