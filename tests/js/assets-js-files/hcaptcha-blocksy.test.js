describe( 'hCaptcha Blocksy', () => {
	let hCaptchaBindEvents;
	let getToken;

	beforeEach( () => {
		jest.resetModules();

		hCaptchaBindEvents = jest.fn();
		window.hCaptchaBindEvents = hCaptchaBindEvents;
		getToken = jest.fn();
		window.hCaptchaFST = { getToken };

		require( '../../../assets/js/hcaptcha-blocksy.js' );
	} );

	afterEach( () => {
		window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaBlocksy.fetchComplete );
		delete window.hCaptchaBlocksy;
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
		delete window.__hcapFetchWrapped;
	} );

	test( 'hCaptchaBindEvents is called on Blocksy newsletter fetch complete', () => {
		const body = new FormData();

		body.set( 'action', 'blc_newsletter_subscribe_process_ajax_subscribe' );
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [
						'/wp-admin/admin-ajax.php',
						{ body },
					],
				},
			} ),
		);

		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'hCaptchaBindEvents is called on Blocksy waitlist fetch complete', () => {
		const body = new FormData();

		body.set( 'action', 'blc_subcribe_to_waitlist' );
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [
						'/wp-admin/admin-ajax.php',
						{ body },
					],
				},
			} ),
		);

		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'hCaptchaBindEvents is not called for other fetch actions', () => {
		const body = new FormData();

		body.set( 'action', 'other_action' );
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [
						'/wp-admin/admin-ajax.php',
						{ body },
					],
				},
			} ),
		);

		expect( hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( getToken ).not.toHaveBeenCalled();
	} );
} );
