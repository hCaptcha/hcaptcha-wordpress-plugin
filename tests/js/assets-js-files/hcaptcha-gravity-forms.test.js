// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';
import { createHooks } from '@wordpress/hooks';
import HCaptcha from '../../../src/js/hcaptcha/hcaptcha.js';

global.jQuery = $;
global.$ = $;

beforeEach( () => {
	global.wp = { hooks: createHooks() };
} );

afterEach( () => {
	delete global.wp;
	delete global.hcaptcha;
	delete window.gform;
	document.body.innerHTML = '';
} );

describe( 'hCaptcha Gravity Forms frontend', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-gravity-forms.js' );
	} );

	afterEach( () => {
		$( document ).off( 'gform_post_render' );
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'rebinds hCaptcha only for ajax-rendered Gravity Forms', () => {
		document.body.innerHTML = `
			<form id="gform_1"></form>
			<form id="gform_2" target="gform_ajax_frame_2"></form>
		`;

		$( document ).trigger( 'gform_post_render', [ 1 ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'gform_post_render', [ 2 ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'input', 'invisible', '' ],
		[ 'button', 'invisible', 'gform_ajax_frame_40' ],
		[ 'input', 'normal', 'gform_ajax_frame_40' ],
		[ 'button', 'normal', '' ],
	] )( 'runs Gravity Forms and custom onclick handlers after %s verification (%s, target=%s)', ( tag, size, target ) => {
		document.body.innerHTML = `
			<div class="gform_wrapper">
				<form id="gform_40" target="${ target }">
					<div class="h-captcha" data-size="${ size }" data-force="${ size === 'normal' }"></div>
				</form>
			</div>
		`;
		const form = document.getElementById( 'gform_40' );
		const button = document.createElement( tag );
		button.type = 'submit';
		button.id = 'gform_submit_button_40';
		button.setAttribute( 'onclick', `
			window.gform.submission.handleButtonClick(this);
			var formEl = this.closest('.gform_wrapper');
			let div = document.createElement('div');
			div.classList.add('gform_yelp_spinner');
			formEl.append(div);
			formEl.classList.add('submitting');
		` );
		form.appendChild( button );
		form.addEventListener( 'submit', ( event ) => event.preventDefault() );
		const requestSubmit = jest.spyOn( form, 'requestSubmit' );
		const handleButtonClick = jest.fn();
		window.gform = { submission: { handleButtonClick } };
		global.hcaptcha = { execute: jest.fn() };
		const hCaptcha = new HCaptcha();
		hCaptcha.setParams( { size } );
		jest.spyOn( hCaptcha, 'render' ).mockImplementation( ( element ) => {
			element.innerHTML = '<textarea name="h-captcha-response"></textarea>';
			return 'widget-40';
		} );
		hCaptcha.bindEvents();

		button.click();

		expect( global.hcaptcha.execute ).toHaveBeenCalledTimes( 1 );
		expect( handleButtonClick ).not.toHaveBeenCalled();
		expect( document.querySelector( '.gform_yelp_spinner' ) ).toBeNull();

		form.querySelector( '[name="h-captcha-response"]' ).value = 'verified-token';
		hCaptcha.callback( 'verified-token' );

		expect( handleButtonClick ).toHaveBeenCalledTimes( 1 );
		expect( handleButtonClick ).toHaveBeenCalledWith( button );
		expect( document.querySelectorAll( '.gform_yelp_spinner' ) ).toHaveLength( 1 );
		expect( form.parentElement.classList.contains( 'submitting' ) ).toBe( true );
		expect( global.hcaptcha.execute ).toHaveBeenCalledTimes( 1 );
		expect( requestSubmit ).not.toHaveBeenCalled();
		expect( button.disabled ).toBe( false );
	} );

	test.each( [ false, true ] )( 'preserves other forms ajax submit status (%s)', ( isAjaxSubmitButton ) => {
		const button = document.createElement( 'button' );
		button.type = 'submit';
		button.id = 'other_submit_button_40';

		expect( wp.hooks.applyFilters( 'hcaptcha.ajaxSubmitButton', isAjaxSubmitButton, button ) )
			.toBe( isAjaxSubmitButton );
	} );
} );

describe( 'hCaptcha Gravity Forms null form branch', () => {
	afterEach( () => {
		global.jQuery = $;
		window.jQuery = $;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'returns when the form lookup fails', () => {
		jest.resetModules();
		const onMock = jest.fn();
		const jQueryStub = jest.fn( ( selector ) => {
			if ( selector === document ) {
				return {
					on: onMock,
				};
			}

			return null;
		} );

		global.jQuery = jQueryStub;
		window.jQuery = jQueryStub;
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-gravity-forms.js' );

		const handler = onMock.mock.calls[ 0 ][ 1 ];

		expect( () => handler( {}, 10 ) ).not.toThrow();
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );
} );
