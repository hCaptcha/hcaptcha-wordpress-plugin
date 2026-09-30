// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha ajaxStop binding', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		jest.resetModules();

		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		require( '../../../assets/js/hcaptcha-support-candy.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess ajaxComplete' );
		document.body.innerHTML = '';
		global.hCaptchaBindEvents.mockRestore();
		delete window.hCaptchaFST;
	} );

	test( 'hCaptchaBindEvents is called when ajaxStop event is triggered', () => {
		const xhr = {};
		const settings = {};

		settings.data = '?some_data&action=wpsc_get_ticket_form';
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'FST token is moved into the submitted Support Candy form', () => {
		document.body.innerHTML = `
			<div class="wpsc-body">
				<input type="hidden" name="hcap_fst_token" value="test-token">
				<form class="wpsc-create-ticket"></form>
			</div>`;

		const xhr = {};
		const settings = { data: 'action=wpsc_get_ticket_form' };

		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		const form = document.querySelector( 'form.wpsc-create-ticket' );
		const token = form.querySelector( '[name="hcap_fst_token"]' );

		expect( token ).not.toBeNull();
		expect( token.value ).toBe( 'test-token' );
	} );

	test( 'FST token is moved after a generic hCaptcha rebind', () => {
		document.body.innerHTML = `
			<div class="wpsc-body">
				<input type="hidden" name="hcap_fst_token" value="rebound-token">
				<form class="wpsc-create-ticket"></form>
			</div>`;

		document.dispatchEvent( new Event( 'hCaptchaAfterBindEvents' ) );

		const form = document.querySelector( 'form.wpsc-create-ticket' );
		const token = form.querySelector( '[name="hcap_fst_token"]' );

		expect( token ).not.toBeNull();
		expect( token.value ).toBe( 'rebound-token' );
	} );

	test( 'FST token is moved before Support Candy serializes the ticket form', () => {
		document.body.innerHTML = `
			<div class="wpsc-body">
				<input type="hidden" name="hcap_fst_token" value="submit-token">
				<form class="wpsc-create-ticket"></form>
				<button id="wpsc-ct-submit">Submit</button>
			</div>`;

		document.getElementById( 'wpsc-ct-submit' ).click();

		const form = document.querySelector( 'form.wpsc-create-ticket' );
		const token = form.querySelector( '[name="hcap_fst_token"]' );

		expect( token ).not.toBeNull();
		expect( token.value ).toBe( 'submit-token' );
	} );

	test( 'FST token is refreshed after a ticket submission completes', () => {
		const data = new FormData();

		data.set( 'action', 'wpsc_set_ticket_form' );
		$( document ).trigger( 'ajaxComplete', [ {}, { data } ] );

		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'FST token is not refreshed after an unrelated request', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, { data: { action: 'other_action' } } ] );

		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
