// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

const defaultWhatsNewObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	markShownAction: 'hcap_mark_whats_new_shown',
	markShownNonce: 'nonce-mark-shown',
	renderPopupAction: 'hcap_render_whats_new_popup',
	renderPopupNonce: 'nonce-render-popup',
};

global.HCaptchaWhatsNewObject = { ...defaultWhatsNewObject };

function getDom( { withModal = true, display = 'none' } = {} ) {
	if ( ! withModal ) {
		return '';
	}

	return `
<div id="hcaptcha-whats-new-modal" data-popup-version="4.20.0" style="display:${ display }">
	<div class="hcaptcha-whats-new-modal-bg"></div>
	<button id="hcaptcha-whats-new-close">Close</button>
	<div class="hcaptcha-whats-new-version-control">
		<span id="hcaptcha-whats-new-version">4.20.0</span>
		<button class="hcaptcha-whats-new-version-toggle" aria-expanded="false"></button>
		<ul class="hcaptcha-whats-new-version-list" hidden>
			<li class="is-current"><a href="#" class="hcaptcha-whats-new-version-link" data-version="4.20.0">4.20.0</a></li>
			<li><a href="#" class="hcaptcha-whats-new-version-link" data-version="4.13.0">4.13.0</a></li>
		</ul>
	</div>
	<div class="hcaptcha-whats-new-button">
		<a href="https://example.com">Read more</a>
	</div>
</div>
<a id="hcaptcha-whats-new-link" href="#">What's New</a>
	`.trim();
}

function getPopupResponseHtml( version = '4.13.0' ) {
	return `
<div id="hcaptcha-whats-new-modal" data-popup-version="${ version }" style="display:flex">
	<div class="hcaptcha-whats-new-modal-bg"></div>
	<button id="hcaptcha-whats-new-close">Close</button>
	<div class="hcaptcha-whats-new-version-control">
		<span id="hcaptcha-whats-new-version">${ version }</span>
		<button class="hcaptcha-whats-new-version-toggle" aria-expanded="false"></button>
		<ul class="hcaptcha-whats-new-version-list" hidden>
			<li><a href="#" class="hcaptcha-whats-new-version-link" data-version="4.20.0">4.20.0</a></li>
			<li class="is-current"><a href="#" class="hcaptcha-whats-new-version-link" data-version="${ version }">${ version }</a></li>
		</ul>
	</div>
</div>
<div id="hcaptcha-lightbox-modal">
	<img id="hcaptcha-lightbox-img" src="" alt="lightbox-image">
</div>
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
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( ( options ) => {
			const deferred = $.Deferred();

			if ( options.data.action === defaultWhatsNewObject.renderPopupAction ) {
				deferred.resolve( {
					success: true,
					data: {
						html: getPopupResponseHtml( options.data.version ),
					},
				} );

				return deferred.promise();
			}

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
			} ),
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

	test( 'button link click prevents default, stops propagation, posts markShown and opens link', () => {
		bootWhatsNew();
		const documentClickSpy = jest.fn();
		const event = $.Event( 'click' );
		const preventDefaultSpy = jest.spyOn( event, 'preventDefault' );

		$( document ).on( 'click.test', documentClickSpy );
		$( '.hcaptcha-whats-new-button a' ).trigger( event );

		expect( preventDefaultSpy ).toHaveBeenCalled();
		expect( event.isImmediatePropagationStopped() ).toBe( true );
		expect( documentClickSpy ).not.toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
		expect( openSpy ).toHaveBeenCalledWith( 'https://example.com', '_blank' );

		$( document ).off( 'click.test' );
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

	test( 'version toggle opens and closes the versions list', () => {
		bootWhatsNew();
		const event = $.Event( 'click' );
		const preventDefaultSpy = jest.spyOn( event, 'preventDefault' );
		const $control = $( '.hcaptcha-whats-new-version-control' );
		const $toggle = $( '.hcaptcha-whats-new-version-toggle' );
		const $list = $( '.hcaptcha-whats-new-version-list' );

		$toggle.trigger( event );

		expect( preventDefaultSpy ).toHaveBeenCalled();
		expect( event.isPropagationStopped() ).toBe( true );
		expect( $control.hasClass( 'is-open' ) ).toBe( true );
		expect( $toggle.attr( 'aria-expanded' ) ).toBe( 'true' );
		expect( $list.attr( 'hidden' ) ).toBeUndefined();

		$toggle.trigger( 'click' );

		expect( $control.hasClass( 'is-open' ) ).toBe( false );
		expect( $toggle.attr( 'aria-expanded' ) ).toBe( 'false' );
		expect( $list.attr( 'hidden' ) ).toBe( 'hidden' );
	} );

	test( 'document click closes the versions list', () => {
		bootWhatsNew();
		const $control = $( '.hcaptcha-whats-new-version-control' );
		const $toggle = $( '.hcaptcha-whats-new-version-toggle' );
		const $list = $( '.hcaptcha-whats-new-version-list' );

		$toggle.trigger( 'click' );
		$( document ).trigger( 'click' );

		expect( $control.hasClass( 'is-open' ) ).toBe( false );
		expect( $toggle.attr( 'aria-expanded' ) ).toBe( 'false' );
		expect( $list.attr( 'hidden' ) ).toBe( 'hidden' );
	} );

	test( 'version link click loads and replaces popup via ajax', () => {
		bootWhatsNew();
		$( '.hcaptcha-whats-new-version-toggle' ).trigger( 'click' );
		$( '.hcaptcha-whats-new-version-link[data-version="4.13.0"]' ).trigger( 'click' );

		expect( postSpy ).toHaveBeenCalledWith(
			expect.objectContaining( {
				url: defaultWhatsNewObject.ajaxUrl,
				data: expect.objectContaining( {
					action: defaultWhatsNewObject.renderPopupAction,
					nonce: defaultWhatsNewObject.renderPopupNonce,
					version: '4.13.0',
				} ),
			} ),
		);
		expect( document.body.style.overflow ).toBe( 'hidden' );
		expect( $( '#hcaptcha-whats-new-modal' ).data( 'popup-version' ) ).toBe( '4.13.0' );
		expect( $( '#hcaptcha-whats-new-version' ).text() ).toBe( '4.13.0' );
		expect( $( '#hcaptcha-lightbox-modal' ).length ).toBe( 1 );
	} );
	test( 'version link without a version returns before AJAX', () => {
		bootWhatsNew();
		$( '.hcaptcha-whats-new-version-list' ).append( '<li><a href="#" class="hcaptcha-whats-new-version-link">Missing</a></li>' );
		postSpy.mockClear();

		$( '.hcaptcha-whats-new-version-link' ).last().trigger( 'click' );

		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'version load restores current popup when response cannot replace it', () => {
		postSpy.mockImplementationOnce( () => {
			const deferred = $.Deferred();
			deferred.resolve( {
				success: true,
				data: {
					html: '<div></div>',
				},
			} );

			return deferred.promise();
		} );
		bootWhatsNew();

		$( '.hcaptcha-whats-new-version-link[data-version="4.13.0"]' ).trigger( 'click' );

		expect( document.body.style.overflow ).toBe( 'hidden' );
		expect( $( '#hcaptcha-whats-new-modal' ).css( 'display' ) ).toBe( 'flex' );
	} );

	test( 'version load restores current popup when request fails', () => {
		postSpy.mockImplementationOnce( () => {
			const deferred = $.Deferred();
			deferred.reject();

			return deferred.promise();
		} );
		bootWhatsNew();

		$( '.hcaptcha-whats-new-version-link[data-version="4.13.0"]' ).trigger( 'click' );

		expect( document.body.style.overflow ).toBe( 'hidden' );
		expect( $( '#hcaptcha-whats-new-modal' ).css( 'display' ) ).toBe( 'flex' );
	} );

	test( 'replacement swaps an existing lightbox modal', () => {
		bootWhatsNew();
		document.body.insertAdjacentHTML( 'beforeend', '<div id="hcaptcha-lightbox-modal"><span>Old</span></div>' );

		$( '.hcaptcha-whats-new-version-link[data-version="4.13.0"]' ).trigger( 'click' );

		expect( $( '#hcaptcha-lightbox-modal img' ).length ).toBe( 1 );
	} );

	test( 'document click inside version control does not close the dropdown', () => {
		bootWhatsNew();
		const $control = $( '.hcaptcha-whats-new-version-control' );
		const $toggle = $( '.hcaptcha-whats-new-version-toggle' );

		$toggle.trigger( 'click' );
		$control.trigger( 'click' );

		expect( $control.hasClass( 'is-open' ) ).toBe( true );
	} );
} );
