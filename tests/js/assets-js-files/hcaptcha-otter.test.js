describe( 'hCaptcha Otter', () => {
	beforeEach( () => {
		jest.resetModules();

		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.wp = global.wp;
		window.hCaptchaBindEvents = jest.fn();
		document.body.innerHTML = `
			<div id="wp-block-themeisle-blocks-form-fc0b7800">
				<input name="h-captcha-response" value="response-token">
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="hcaptcha_otter_nonce" value="nonce-value">
				<input name="hcap_fst_token" value="fst-token">
				<input name="hcap_hp_sig" value="hp-sig">
				<input id="hcap_hp_test" value="">
			</div>
		`;

		require( '../../../assets/js/hcaptcha-otter.js' );
	} );

	afterEach( () => {
		window.removeEventListener( 'hCaptchaFetch:before', window.hCaptchaOtter.fetchBefore );
		window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaOtter.fetchComplete );
		delete window.hCaptchaOtter;
		delete window.hCaptchaBindEvents;
		delete window.__hcapFetchWrapped;
		delete window.wp;
		delete global.wp;
		document.body.innerHTML = '';
	} );

	test.each( [
		'fc0b7800',
		'wp-block-themeisle-blocks-form-fc0b7800',
	] )( 'adds hCaptcha fields to Otter form_data for formId %s', ( formId ) => {
		const body = new FormData();

		body.set(
			'form_data',
			JSON.stringify(
				{
					payload: {
						formId,
					},
				},
			),
		);

		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:before', {
				detail: {
					args: [
						'/otter/v1/form/frontend',
						{ body },
					],
				},
			} ),
		);

		const formData = JSON.parse( body.get( 'form_data' ) );

		expect( formData[ 'h-captcha-response' ] ).toBe( 'response-token' );
		expect( formData[ 'hcaptcha-widget-id' ] ).toBe( 'widget-id' );
		expect( formData.hcaptcha_otter_nonce ).toBe( 'nonce-value' );
		expect( formData.hcap_fst_token ).toBe( 'fst-token' );
		expect( formData.hcap_hp_sig ).toBe( 'hp-sig' );
		expect( formData.hcap_hp_test ).toBe( '' );
	} );

	test( 'binds events again after Otter fetch complete', () => {
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [
						'/otter/v1/form/frontend',
					],
				},
			} ),
		);

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
