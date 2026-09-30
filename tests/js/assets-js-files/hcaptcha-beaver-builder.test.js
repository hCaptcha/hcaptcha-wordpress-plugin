// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Beaver Builder', () => {
	let ajaxPrefilterCallback;
	let ajaxCompleteCallback;
	let settingsFormInitCallback;
	let toggleRecaptcha;
	let jqXHR;
	const options = {
		data: '',
	};

	beforeEach( () => {
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		// Mock jQuery.ajaxPrefilter
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );
		jqXHR = {
			always: jest.fn( ( callback ) => {
				ajaxCompleteCallback = callback;
			} ),
		};
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		toggleRecaptcha = jest.fn();
		window.FLBuilder = {
			addHook: jest.fn( ( hook, callback ) => {
				if ( 'settings-form-init' === hook ) {
					settingsFormInitCallback = callback;
				}
			} ),
			_moduleHelpers: {},
		};

		document.body.innerHTML = `
	      <div class="fl-node-123" data-node="123">
	        <input type="hidden" name="h-captcha-response" value="responseValue">
	        <input type="hidden" name="hcaptcha-widget-id" value="widgetIdValue">
	        <input type="hidden" name="hcaptcha_beaver_builder_nonce" value="nonceValue">
	        <input type="hidden" name="hcaptcha_login_nonce" value="loginNonceValue">
	        <input type="text" id="hcap_hp_dynamic" name="hcap_hp_dynamic" value="">
	        <input type="hidden" name="hcap_hp_sig" value="hpSignatureValue">
	        <input type="hidden" name="hcap_fst_token" value="fstTokenValue">
	        <div class="fl-recaptcha"><div class="fl-grecaptcha"></div></div>
	      </div>
	      <form class="fl-builder-settings" data-node="123" data-type="contact-form">
	        <select name="recaptcha_toggle">
	          <option value="show">Show</option>
	          <option value="hide" selected>Hide</option>
	        </select>
	      </form>
	    `;

		require( '../../../assets/js/hcaptcha-beaver-builder.js' );
	} );

	test( 'appends h-captcha-response and hcaptcha_beaver_builder_nonce when data starts with action=fl_builder_email', () => {
		options.data = 'action=fl_builder_email&node_id=123';
		ajaxPrefilterCallback( options, {}, jqXHR );
		expect( options.data ).toContain( 'h-captcha-response=responseValue' );
		expect( options.data ).toContain( 'hcaptcha-widget-id=widgetIdValue' );
		expect( options.data ).toContain( 'hcaptcha_beaver_builder_nonce=nonceValue' );
		expect( options.data ).toContain( 'hcap_hp_dynamic=' );
		expect( options.data ).toContain( 'hcap_hp_sig=hpSignatureValue' );
		expect( options.data ).toContain( 'hcap_fst_token=fstTokenValue' );
	} );

	test( 'appends h-captcha-response and hcaptcha_login_nonce when data starts with action=fl_builder_login_form_submit', () => {
		options.data = 'action=fl_builder_login_form_submit&node_id=123';
		ajaxPrefilterCallback( options, {}, jqXHR );
		expect( options.data ).toContain( 'h-captcha-response=responseValue' );
		expect( options.data ).toContain( 'hcaptcha-widget-id=widgetIdValue' );
		expect( options.data ).toContain( 'hcaptcha_login_nonce=loginNonceValue' );
		expect( options.data ).toContain( 'hcap_hp_dynamic=' );
		expect( options.data ).toContain( 'hcap_hp_sig=hpSignatureValue' );
		expect( options.data ).toContain( 'hcap_fst_token=fstTokenValue' );
	} );

	test( 'does not append anything when data does not start with any expected action', () => {
		options.data = 'action=other_action&node_id=123';
		ajaxPrefilterCallback( options, {}, jqXHR );
		expect( options.data ).not.toContain( 'h-captcha-response' );
		expect( options.data ).not.toContain( 'hcaptcha_beaver_builder_nonce' );
		expect( options.data ).not.toContain( 'hcaptcha_login_nonce' );
		expect( jqXHR.always ).not.toHaveBeenCalled();
	} );

	test( 'does not append anything when data is not a string', () => {
		options.data = {};
		ajaxPrefilterCallback( options, {}, jqXHR );
		expect( typeof options.data ).not.toBe( 'string' );
		expect( options.data ).toEqual( {} );
		expect( jqXHR.always ).not.toHaveBeenCalled();
	} );

	test.each( [
		'fl_builder_email',
		'fl_builder_login_form_submit',
	] )( 'refreshes the FST token after the %s AJAX request completes', ( action ) => {
		options.data = `action=${ action }&node_id=123`;
		ajaxPrefilterCallback( options, {}, jqXHR );

		expect( jqXHR.always ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();

		ajaxCompleteCallback();

		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'allows an AJAX request to complete when the FST script is unavailable', () => {
		options.data = 'action=fl_builder_email&node_id=123';
		ajaxPrefilterCallback( options, {}, jqXHR );
		delete window.hCaptchaFST;

		expect( ajaxCompleteCallback ).not.toThrow();
	} );

	test( 'does not load Beaver reCAPTCHA when its field is hidden', () => {
		window.FLBuilder._moduleHelpers[ 'contact-form' ] = {
			_toggleReCaptcha: toggleRecaptcha,
		};
		settingsFormInitCallback();
		window.FLBuilder._moduleHelpers[ 'contact-form' ]._toggleReCaptcha();

		expect( toggleRecaptcha ).not.toHaveBeenCalled();
		expect( $( '.fl-recaptcha' ).css( 'display' ) ).toBe( 'none' );
	} );

	test( 'keeps Beaver reCAPTCHA behavior when its field is shown', () => {
		$( 'select[name="recaptcha_toggle"]' ).val( 'show' );
		window.FLBuilder._moduleHelpers[ 'contact-form' ] = {
			_toggleReCaptcha: toggleRecaptcha,
		};
		settingsFormInitCallback();
		window.FLBuilder._moduleHelpers[ 'contact-form' ]._toggleReCaptcha();

		expect( toggleRecaptcha ).toHaveBeenCalledTimes( 1 );
	} );
} );
