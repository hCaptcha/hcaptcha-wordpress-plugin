// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Essential Blocks', () => {
	let helperMock;

	function loadEssentialBlocks() {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaEssentialBlocks;

		require( '../../../assets/js/hcaptcha-essential-blocks.js' );
	}

	function makeBody( type = 'formData' ) {
		return type === 'urlSearchParams' ? new URLSearchParams() : new FormData();
	}

	function dispatchBefore( body ) {
		const event = new CustomEvent( 'hCaptchaFetch:before', {
			detail: {
				args: [
					'/wp-json/essential-blocks/v1/form',
					{ body },
				],
			},
		} );

		window.hCaptchaEssentialBlocks.fetchBefore( event );

		return event;
	}

	beforeEach( () => {
		loadEssentialBlocks();
	} );

	afterEach( () => {
		if ( window.hCaptchaEssentialBlocks ) {
			window.removeEventListener( 'hCaptchaFetch:before', window.hCaptchaEssentialBlocks.fetchBefore );
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaEssentialBlocks.fetchComplete );
		}
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaEssentialBlocks;
		delete window.hCaptchaBindEvents;
		delete window.wp;
		delete global.wp;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'registers Essential Blocks ajax submit buttons and installs fetch events', () => {
		const callback = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		submitButton.classList.add( 'eb-form-submit-button' );

		expect( window.wp.hooks.addFilter ).toHaveBeenCalledWith(
			'hcaptcha.ajaxSubmitButton',
			'hcaptcha',
			expect.any( Function ),
		);
		expect( callback( false, submitButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'adds missing hCaptcha data to Essential Blocks form_data', () => {
		document.body.innerHTML = `
			<form>
				<input name="h-captcha-response" value="response-token">
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="hcaptcha_essential_blocks_nonce" value="nonce-value">
				<input name="hcap_fst_token" value="fst-token">
				<input name="hcap_hp_sig" value="hp-sig">
				<input id="hcap_hp_test" value="hp-value">
			</form>
		`;
		const body = makeBody();

		body.set( 'action', 'eb_form_submit' );
		body.set( 'form_data', JSON.stringify( { 'hcaptcha-widget-id': 'widget-id' } ) );

		dispatchBefore( body );

		const formData = JSON.parse( body.get( 'form_data' ) );

		expect( formData[ 'h-captcha-response' ] ).toBe( 'response-token' );
		expect( formData[ 'hcaptcha-widget-id' ] ).toBe( 'widget-id' );
		expect( formData.hcaptcha_essential_blocks_nonce ).toBe( 'nonce-value' );
		expect( formData.hcap_fst_token ).toBe( 'fst-token' );
		expect( formData.hcap_hp_sig ).toBe( 'hp-sig' );
		expect( formData.hcap_hp_test ).toBe( 'hp-value' );
	} );

	test( 'keeps existing hCaptcha fields and skips empty optional fields', () => {
		document.body.innerHTML = `
			<form>
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="h-captcha-response" value="new-response">
				<input name="hcaptcha_essential_blocks_nonce" value="new-nonce">
				<input name="hcap_fst_token" value="">
				<input name="hcap_hp_sig" value="">
			</form>
		`;
		const body = makeBody( 'urlSearchParams' );

		body.set( 'action', 'eb_form_submit' );
		body.set(
			'form_data',
			JSON.stringify( {
				'hcaptcha-widget-id': 'widget-id',
				'h-captcha-response': 'existing-response',
				hcaptcha_essential_blocks_nonce: 'existing-nonce',
			} ),
		);

		dispatchBefore( body );

		const formData = JSON.parse( body.get( 'form_data' ) );

		expect( formData[ 'h-captcha-response' ] ).toBe( 'existing-response' );
		expect( formData.hcaptcha_essential_blocks_nonce ).toBe( 'existing-nonce' );
		expect( formData ).not.toHaveProperty( 'hcap_fst_token' );
		expect( formData ).not.toHaveProperty( 'hcap_hp_sig' );
		expect( formData ).not.toHaveProperty( 'hcap_hp_test' );
	} );

	test( 'can add widget id when it is inherited by parsed payload', () => {
		document.body.innerHTML = '<form><input name="hcaptcha-widget-id" value="widget-id"></form>';
		const originalParse = JSON.parse;
		const inheritedPayload = Object.create( { 'hcaptcha-widget-id': 'widget-id' } );
		const body = makeBody();

		body.set( 'action', 'eb_form_submit' );
		body.set( 'form_data', '{}' );
		jest.spyOn( JSON, 'parse' ).mockReturnValueOnce( inheritedPayload );

		dispatchBefore( body );

		JSON.parse.mockRestore();
		const formData = originalParse( body.get( 'form_data' ) );

		expect( formData[ 'hcaptcha-widget-id' ] ).toBe( 'widget-id' );
	} );

	test( 'uses empty fallbacks when Essential Blocks form controls lack values', () => {
		const body = makeBody();
		const fakeForm = {
			querySelector: jest.fn( ( selector ) => {
				if ( selector === '[name="h-captcha-response"]' ) {
					return { value: 'response-token' };
				}

				if ( selector === '[name="hcaptcha-widget-id"]' ) {
					return {};
				}

				if ( selector === '[name="hcaptcha_essential_blocks_nonce"]' ) {
					return { value: 'nonce-value' };
				}

				if ( selector === '[id^="hcap_hp_"]' ) {
					return { id: 'hcap_hp_empty' };
				}

				return null;
			} ),
		};
		const querySelectorSpy = jest.spyOn( document, 'querySelector' ).mockReturnValueOnce( {
			closest: jest.fn( () => fakeForm ),
		} );

		body.set( 'action', 'eb_form_submit' );
		body.set( 'form_data', JSON.stringify( { 'hcaptcha-widget-id': 'widget-id' } ) );

		dispatchBefore( body );

		const formData = JSON.parse( body.get( 'form_data' ) );

		expect( querySelectorSpy ).toHaveBeenCalledWith( 'input[name="hcaptcha-widget-id"][value="widget-id"]' );
		expect( formData.hcap_hp_empty ).toBe( '' );
	} );

	test( 'ignores Essential Blocks fetches that cannot be amended', () => {
		expect( () => window.hCaptchaEssentialBlocks.fetchBefore() ).not.toThrow();
		expect( () => window.hCaptchaEssentialBlocks.fetchBefore( new CustomEvent( 'hCaptchaFetch:before' ) ) ).not.toThrow();

		window.hCaptchaEssentialBlocks.fetchBefore( new CustomEvent( 'hCaptchaFetch:before', {
			detail: {
				args: [ '/endpoint', { body: 'not-form-data' } ],
			},
		} ) );

		const otherAction = makeBody();
		otherAction.set( 'action', 'other_action' );
		dispatchBefore( otherAction );

		const missingFormData = makeBody();
		missingFormData.set( 'action', 'eb_form_submit' );
		dispatchBefore( missingFormData );

		const invalidJson = makeBody();
		invalidJson.set( 'action', 'eb_form_submit' );
		invalidJson.set( 'form_data', '{' );
		dispatchBefore( invalidJson );

		const missingWidget = makeBody();
		missingWidget.set( 'action', 'eb_form_submit' );
		missingWidget.set( 'form_data', JSON.stringify( {} ) );
		dispatchBefore( missingWidget );

		const unmatchedWidget = makeBody();
		unmatchedWidget.set( 'action', 'eb_form_submit' );
		unmatchedWidget.set( 'form_data', JSON.stringify( { 'hcaptcha-widget-id': 'missing-widget' } ) );
		dispatchBefore( unmatchedWidget );

		expect( unmatchedWidget.get( 'form_data' ) ).toBe( JSON.stringify( { 'hcaptcha-widget-id': 'missing-widget' } ) );
	} );

	test( 'ignores widget inputs that cannot resolve a parent form', () => {
		const body = makeBody();
		const querySelectorSpy = jest.spyOn( document, 'querySelector' ).mockReturnValueOnce( {
			closest: undefined,
		} );

		body.set( 'action', 'eb_form_submit' );
		body.set( 'form_data', JSON.stringify( { 'hcaptcha-widget-id': 'widget-id' } ) );

		dispatchBefore( body );

		expect( querySelectorSpy ).toHaveBeenCalledWith( 'input[name="hcaptcha-widget-id"][value="widget-id"]' );
		expect( body.get( 'form_data' ) ).toBe( JSON.stringify( { 'hcaptcha-widget-id': 'widget-id' } ) );
	} );

	test( 'rebinds hCaptcha after Essential Blocks fetch complete only', () => {
		window.hCaptchaEssentialBlocks.fetchComplete();
		window.hCaptchaEssentialBlocks.fetchComplete( new CustomEvent( 'hCaptchaFetch:complete', {
			detail: {
				args: [ '/endpoint', { body: 'not-form-data' } ],
			},
		} ) );

		const otherAction = makeBody();
		otherAction.set( 'action', 'other_action' );
		window.hCaptchaEssentialBlocks.fetchComplete( new CustomEvent( 'hCaptchaFetch:complete', {
			detail: {
				args: [ '/endpoint', { body: otherAction } ],
			},
		} ) );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		const body = makeBody( 'urlSearchParams' );
		body.set( 'action', 'eb_form_submit' );
		window.hCaptchaEssentialBlocks.fetchComplete( new CustomEvent( 'hCaptchaFetch:complete', {
			detail: {
				args: [ '/endpoint', { body } ],
			},
		} ) );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );

		delete window.hCaptchaBindEvents;
		expect( () => window.hCaptchaEssentialBlocks.fetchComplete( new CustomEvent( 'hCaptchaFetch:complete', {
			detail: {
				args: [ '/endpoint', { body } ],
			},
		} ) ) ).not.toThrow();
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaEssentialBlocks = existingApp;

		require( '../../../assets/js/hcaptcha-essential-blocks.js' );

		expect( window.hCaptchaEssentialBlocks ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
