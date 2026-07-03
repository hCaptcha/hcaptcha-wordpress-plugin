// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha wpDiscuz comment', () => {
	let domReadyCallback;
	let originalAddEventListener;
	let originalMutationObserver;
	let observers;

	function loadWpDiscuz( html = '<div id="wpd-threads"></div>' ) {
		jest.resetModules();
		document.body.innerHTML = html;
		domReadyCallback = null;
		observers = [];
		originalAddEventListener = document.addEventListener.bind( document );
		jest.spyOn( document, 'addEventListener' ).mockImplementation( ( eventName, callback, options ) => {
			if ( eventName === 'DOMContentLoaded' ) {
				domReadyCallback = callback;

				return undefined;
			}

			return originalAddEventListener( eventName, callback, options );
		} );
		originalMutationObserver = window.MutationObserver;
		window.MutationObserver = jest.fn( function( callback ) {
			this.callback = callback;
			this.observe = jest.fn();
			observers.push( this );
		} );
		global.MutationObserver = window.MutationObserver;
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-wpdiscuz-comment.js' );
	}

	beforeEach( () => {
		loadWpDiscuz();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		window.MutationObserver = originalMutationObserver;
		global.MutationObserver = originalMutationObserver;
		delete window.wp;
		delete global.wp;
		delete window.hCaptchaBindEvents;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'registers wpDiscuz ajax submit buttons and observes added forms', () => {
		domReadyCallback();
		const callback = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );
		const matchingNode = document.createElement( 'form' );
		const otherNode = document.createElement( 'form' );
		const textNode = document.createTextNode( 'text' );

		submitButton.classList.add( 'wc_comm_submit' );
		expect( callback( false, submitButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
		expect( observers[ 0 ].observe ).toHaveBeenCalledWith(
			document.getElementById( 'wpd-threads' ),
			{
				childList: true,
				subtree: true,
			},
		);

		matchingNode.className = 'wpd-form';
		matchingNode.innerHTML = '<div class="h-captcha"></div>';
		otherNode.className = 'wpd-form';

		observers[ 0 ].callback( [ { addedNodes: [ textNode, otherNode, matchingNode ] } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'returns when wpDiscuz threads container is absent', () => {
		loadWpDiscuz( '<div id="other"></div>' );

		expect( () => domReadyCallback() ).not.toThrow();
		expect( observers ).toHaveLength( 0 );
	} );

	test( 'rebinds hCaptcha after wpDiscuz add-comment ajax success only', () => {
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=wpdAddComment' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
