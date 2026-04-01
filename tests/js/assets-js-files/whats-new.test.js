// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* eslint-disable no-unused-vars */
import $ from 'jquery';

global.jQuery = $;
global.$ = $;

const defaultWhatsNewObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	markShownAction: 'hcap_mark_whats_new_shown',
	markShownNonce: 'nonce-mark-shown',
	whatsNewParam: 'whats_new',
};

global.HCaptchaWhatsNewObject = { ...defaultWhatsNewObject };

function getDom( { withModal = true, display = 'none' } = {} ) {
	if ( ! withModal ) {
		return '';
	}

	return `
<div id="hcaptcha-whats-new-modal" style="display:${ display }">
	<div class="hcaptcha-whats-new-modal-bg"></div>
	<button id="hcaptcha-whats-new-close">Close</button>
	<span id="hcaptcha-whats-new-version">4.20.0</span>
	<div class="hcaptcha-whats-new-button">
		<a href="https://example.com">Read more</a>
	</div>
</div>
<a id="hcaptcha-whats-new-link" href="#">What's New</a>
	`.trim();
}

function bootWhatsNew( domOptions = {}, objectOverrides = {} ) {
	jest.resetModules();
	$( document ).off();
	document.body.innerHTML = getDom( domOptions );
	global.HCaptchaWhatsNewObject = { ...defaultWhatsNewObject, ...objectOverrides };
	require( '../../../assets/js/whats-new.js' );
	window.hCaptchaWhatsNew( $ );
}

describe( 'whats-new.js', () => {
	let postSpy;
	let fadeOutSpy;
	let fadeInSpy;
	let openSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const deferred = $.Deferred();
			deferred.resolve();
			return deferred.promise();
		} );
		fadeOutSpy = jest.spyOn( $.fn, 'fadeOut' ).mockImplementation( function( duration, callback ) {
			if ( typeof callback === 'function' ) {
				callback.call( this[ 0 ] );
			}
			return this;
		} );
		fadeInSpy = jest.spyOn( $.fn, 'fadeIn' ).mockImplementation( function() {
			return this;
		} );
		openSpy = jest.spyOn( window, 'open' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
		fadeOutSpy.mockRestore();
		fadeInSpy.mockRestore();
		openSpy.mockRestore();
	} );

	test( 'returns early when modal element is absent', () => {
		bootWhatsNew( { withModal: false } );
		$( document ).trigger( $.Event( 'keydown', { key: 'Escape' } ) );
		expect( fadeOutSpy ).not.toHaveBeenCalled();
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'sets body overflow to hidden when modal display is flex', () => {
		bootWhatsNew( { display: 'flex' } );
		expect( document.body.style.overflow ).toBe( 'hidden' );
	} );

	test( 'does not set body overflow when modal display is not flex', () => {
		document.body.style.overflow = '';
		bootWhatsNew( { display: 'none' } );
		expect( document.body.style.overflow ).toBe( '' );
	} );

	test( 'close button click calls closePopup and markShown with correct data', () => {
		bootWhatsNew();
		$( '#hcaptcha-whats-new-close' ).trigger( 'click' );
		expect( fadeOutSpy ).toHaveBeenCalledWith( 200, expect.any( Function ) );
		expect( postSpy ).toHaveBeenCalledWith(
			expect.objectContaining( {
				url: defaultWhatsNewObject.ajaxUrl,
				data: expect.objectContaining( {
					action: defaultWhatsNewObject.markShownAction,
					nonce: defaultWhatsNewObject.markShownNonce,
					version: '4.20.0',
				} ),
			} )
		);
	} );

	test( 'modal background click calls done()', () => {
		bootWhatsNew();
		$( '.hcaptcha-whats-new-modal-bg' ).trigger( 'click' );
		expect( fadeOutSpy ).toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
	} );

	test( 'fadeOut callback resets body overflow and sets modal display to none', () => {
		bootWhatsNew();
		document.body.style.overflow = 'hidden';
		$( '#hcaptcha-whats-new-close' ).trigger( 'click' );
		expect( document.body.style.overflow ).toBe( '' );
		const modal = document.getElementById( 'hcaptcha-whats-new-modal' );
		expect( modal.style.display ).toBe( 'none' );
	} );

	test( 'Escape keydown triggers done()', () => {
		bootWhatsNew();
		$( document ).trigger( $.Event( 'keydown', { key: 'Escape' } ) );
		expect( fadeOutSpy ).toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
	} );

	test( 'non-Escape keydown does not trigger done()', () => {
		bootWhatsNew();
		$( document ).trigger( $.Event( 'keydown', { key: 'Tab' } ) );
		expect( fadeOutSpy ).not.toHaveBeenCalled();
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'button link click prevents default, posts markShown and opens link', () => {
		bootWhatsNew();
		const event = $.Event( 'click' );
		const preventDefaultSpy = jest.spyOn( event, 'preventDefault' );
		$( '.hcaptcha-whats-new-button a' ).trigger( event );
		expect( preventDefaultSpy ).toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
		expect( openSpy ).toHaveBeenCalledWith( 'https://example.com', '_blank' );
	} );

	test( 'whats-new link click prevents default, sets overflow and calls fadeIn', () => {
		bootWhatsNew();
		const event = $.Event( 'click' );
		const preventDefaultSpy = jest.spyOn( event, 'preventDefault' );
		$( '#hcaptcha-whats-new-link' ).trigger( event );
		expect( preventDefaultSpy ).toHaveBeenCalled();
		expect( document.body.style.overflow ).toBe( 'hidden' );
		expect( fadeInSpy ).toHaveBeenCalledWith( 200 );
	} );

	test( 'markShown skips $.post when whatsNewParam is present in URL', () => {
		const hasSpy = jest.spyOn( URLSearchParams.prototype, 'has' ).mockReturnValue( true );
		bootWhatsNew();
		$( '#hcaptcha-whats-new-close' ).trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
		hasSpy.mockRestore();
	} );

	test( 'button link click still opens link when whatsNewParam is in URL', () => {
		const hasSpy = jest.spyOn( URLSearchParams.prototype, 'has' ).mockReturnValue( true );
		bootWhatsNew();
		$( '.hcaptcha-whats-new-button a' ).trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
		expect( openSpy ).toHaveBeenCalledWith( 'https://example.com', '_blank' );
		hasSpy.mockRestore();
	} );

	test( 'markShown handles URLSearchParams error and falls through to $.post', () => {
		const OriginalURLSearchParams = global.URLSearchParams;
		global.URLSearchParams = function() {
			throw new Error( 'not available' );
		};
		bootWhatsNew();
		$( '#hcaptcha-whats-new-close' ).trigger( 'click' );
		expect( postSpy ).toHaveBeenCalled();
		global.URLSearchParams = OriginalURLSearchParams;
	} );
} );
