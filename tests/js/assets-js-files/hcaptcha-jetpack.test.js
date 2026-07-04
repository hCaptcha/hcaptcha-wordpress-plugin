// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Jetpack frontend', () => {
	let helperMock;

	function loadJetpack() {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaJetpack;

		require( '../../../assets/js/hcaptcha-jetpack.js' );
	}

	function fetchCompleteEvent( body ) {
		return new CustomEvent( 'hCaptchaFetch:complete', {
			detail: {
				args: [
					'/wp-admin/admin-ajax.php',
					{ body },
				],
			},
		} );
	}

	beforeEach( () => {
		loadJetpack();
	} );

	afterEach( () => {
		if ( window.hCaptchaJetpack ) {
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaJetpack.fetchComplete );
		}
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaJetpack;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'installs fetch events and rebinds after Jetpack form fetch complete only', () => {
		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );

		window.hCaptchaJetpack.fetchComplete();
		window.hCaptchaJetpack.fetchComplete( fetchCompleteEvent( 'not-form-data' ) );

		const otherAction = new FormData();
		otherAction.set( 'action', 'other_action' );
		window.hCaptchaJetpack.fetchComplete( fetchCompleteEvent( otherAction ) );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		const body = new FormData();
		body.set( 'action', 'grunion-contact-form' );
		window.hCaptchaJetpack.fetchComplete( fetchCompleteEvent( body ) );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaJetpack = existingApp;

		require( '../../../assets/js/hcaptcha-jetpack.js' );

		expect( window.hCaptchaJetpack ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
