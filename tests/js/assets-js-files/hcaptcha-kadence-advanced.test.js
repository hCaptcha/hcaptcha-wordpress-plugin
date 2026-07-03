// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Kadence Advanced frontend', () => {
	let helperMock;

	function loadKadenceAdvanced() {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaKadenceAdvanced;

		require( '../../../assets/js/hcaptcha-kadence-advanced.js' );
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
		loadKadenceAdvanced();
	} );

	afterEach( () => {
		if ( window.hCaptchaKadenceAdvanced ) {
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaKadenceAdvanced.fetchComplete );
		}
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaKadenceAdvanced;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'installs fetch events and rebinds after Kadence advanced fetch complete only', () => {
		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );

		window.hCaptchaKadenceAdvanced.fetchComplete();
		window.hCaptchaKadenceAdvanced.fetchComplete( fetchCompleteEvent( 'not-form-data' ) );

		const otherAction = new FormData();
		otherAction.set( 'action', 'other_action' );
		window.hCaptchaKadenceAdvanced.fetchComplete( fetchCompleteEvent( otherAction ) );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		const formData = new FormData();
		formData.set( 'action', 'kb_process_advanced_form_submit' );
		window.hCaptchaKadenceAdvanced.fetchComplete( fetchCompleteEvent( formData ) );

		const params = new URLSearchParams();
		params.set( 'action', 'kb_process_advanced_form_submit' );
		window.hCaptchaKadenceAdvanced.fetchComplete( fetchCompleteEvent( params ) );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaKadenceAdvanced = existingApp;

		require( '../../../assets/js/hcaptcha-kadence-advanced.js' );

		expect( window.hCaptchaKadenceAdvanced ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
