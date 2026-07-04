// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

describe( 'hCaptcha Customer Reviews', () => {
	let helperMock;
	let prefilterCallbacks;
	let originalAjaxPrefilter;
	let originalJQuery;
	let jQueryProxy;

	function installJQueryProxy() {
		originalJQuery = global.jQuery;
		prefilterCallbacks = [];
		jQueryProxy = ( selector, context ) => {
			if ( typeof selector === 'function' ) {
				jQueryProxy.readyCallback = selector;

				return $( document );
			}

			return $( selector, context );
		};
		Object.assign( jQueryProxy, $ );
		jQueryProxy.fn = $.fn;
		originalAjaxPrefilter = $.ajaxPrefilter;
		jQueryProxy.ajaxPrefilter = jest.fn( ( callback ) => {
			prefilterCallbacks.push( callback );
		} );
		global.jQuery = jQueryProxy;
		global.$ = jQueryProxy;
		window.jQuery = jQueryProxy;
		window.$ = jQueryProxy;
	}

	function loadCustomerReviews() {
		jest.resetModules();
		helperMock = {
			addHCaptchaData: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		installJQueryProxy();
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		window.hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = window.hCaptchaBindEvents;
		delete window.hCaptchaCustomerReviews;

		require( '../../../assets/js/hcaptcha-customer-reviews.js' );
	}

	function runReady() {
		$( document ).off( 'click' );
		prefilterCallbacks = [];
		window.hCaptchaCustomerReviews.ready();

		return prefilterCallbacks[ prefilterCallbacks.length - 1 ];
	}

	beforeEach( () => {
		document.body.innerHTML = `
			<form id="review_form"></form>
			<div id="cr_qna"></div>
			<div data-question="question-1"></div>
			<div id="tab-title-reviews"><a href="#reviews">Reviews</a></div>
		`;
		loadCustomerReviews();
	} );

	afterEach( () => {
		$( document ).off();
		$.ajaxPrefilter = originalAjaxPrefilter;
		global.jQuery = originalJQuery || $;
		global.$ = originalJQuery || $;
		window.jQuery = originalJQuery || $;
		window.$ = originalJQuery || $;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaCustomerReviews;
		delete window.hCaptchaBindEvents;
		delete global.hCaptchaBindEvents;
		delete window.wp;
		delete global.wp;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'registers Customer Reviews selectors and binds clicks', () => {
		const formFilter = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const buttonFilter = window.wp.hooks.addFilter.mock.calls[ 1 ][ 2 ];

		expect( formFilter( 'form' ) ).toBe(
			'form, div#tab-reviews, div#tab-cr_qna, div.cr-qna-list-inl-answ, div.cr-qna-new-q-form',
		);
		expect( buttonFilter( 'button[type="submit"]' ) ).toBe(
			'button[type="submit"], button.cr-review-form-submit',
		);

		runReady();
		$( '#tab-title-reviews a' ).trigger( 'click' );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores ajax data that cannot contain Customer Reviews actions', () => {
		const prefilterCallback = runReady();

		prefilterCallback( {} );
		prefilterCallback( { data: { action: 'cr_submit_review' } } );
		prefilterCallback( { data: 'action=other_action' } );

		expect( helperMock.addHCaptchaData ).not.toHaveBeenCalled();
	} );

	test( 'adds hCaptcha data to review submissions', () => {
		const prefilterCallback = runReady();
		const options = {
			data: 'action=cr_submit_review',
		};

		prefilterCallback( options );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			options,
			'cr_submit_review',
			'hcaptcha_customer_reviews_nonce',
			expect.objectContaining( {
				0: document.getElementById( 'review_form' ),
				length: 1,
			} ),
		);
	} );

	test( 'adds hCaptcha data to Q&A submissions with and without question id', () => {
		const prefilterCallback = runReady();
		const questionOptions = {
			data: 'action=cr_new_qna&questionID=question-1',
		};
		const newQuestionOptions = {
			data: 'action=cr_new_qna',
		};

		prefilterCallback( questionOptions );
		prefilterCallback( newQuestionOptions );

		expect( helperMock.addHCaptchaData ).toHaveBeenNthCalledWith(
			1,
			questionOptions,
			'cr_new_qna',
			'hcaptcha_customer_reviews_nonce',
			expect.objectContaining( {
				0: document.querySelector( '[data-question="question-1"]' ),
				length: 1,
			} ),
		);
		expect( helperMock.addHCaptchaData ).toHaveBeenNthCalledWith(
			2,
			newQuestionOptions,
			'cr_new_qna',
			'hcaptcha_customer_reviews_nonce',
			expect.objectContaining( {
				0: document.getElementById( 'cr_qna' ),
				length: 1,
			} ),
		);
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaCustomerReviews = existingApp;

		require( '../../../assets/js/hcaptcha-customer-reviews.js' );

		expect( window.hCaptchaCustomerReviews ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
