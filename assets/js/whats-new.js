/* global jQuery, HCaptchaWhatsNewObject */

/**
 * @typedef {Object} HCaptchaWhatsNewObject
 * @property {string} ajaxUrl         The URL to send AJAX requests to.
 * @property {string} markShownAction The action to mark the popup as shown.
 * @property {string} markShownNonce  The nonce for the mark the popup as shown action.
 */

/**
 * What's New logic.
 *
 * @param {Object} $ jQuery instance.
 */
const whatsNew = ( $ ) => {
	/**
	 * @typedef {jQuery} jQuery
	 * @property {Function} fadeOut Function to fade out the modal.
	 */

	/** @type {jQuery} */
	const $modal = $( '#hcaptcha-whats-new-modal' );

	if ( ! $modal.length ) {
		return;
	}

	if ( $modal.css( 'display' ) === 'flex' ) {
		document.body.style.overflow = 'hidden';
	}

	function done() {
		closePopup();
		markShown();
	}

	function closePopup() {
		$modal.fadeOut( 200, function() {
			document.body.style.overflow = '';
			$( this ).css( 'display', 'none' );
		} );
	}

	function markShown() {
		const data = {
			action: HCaptchaWhatsNewObject.markShownAction,
			nonce: HCaptchaWhatsNewObject.markShownNonce,
			version: $( '#hcaptcha-whats-new-version' ).text(),
		};

		$.post( {
			url: HCaptchaWhatsNewObject.ajaxUrl,
			data,
		} );
	}

	$( document ).on( 'click', '#hcaptcha-whats-new-close, .hcaptcha-whats-new-modal-bg', function() {
		done();
	} );

	$( document ).on( 'keydown', function( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}

		done();
	} );

	$( document ).on( 'click', '.hcaptcha-whats-new-button a', function( e ) {
		e.preventDefault();

		const $btn = $( this );
		const href = $btn.attr( 'href' );
		const data = {
			action: HCaptchaWhatsNewObject.markShownAction,
			nonce: HCaptchaWhatsNewObject.markShownNonce,
			version: $( '#hcaptcha-whats-new-version' ).text(),
		};

		$.post( {
			url: HCaptchaWhatsNewObject.ajaxUrl,
			data,
			success() {
				window.location.href = href;
			},
		} );
	} );

	$( document ).on( 'click', '#hcaptcha-whats-new-link', function( e ) {
		e.preventDefault();

		document.body.style.overflow = 'hidden';
		$modal.fadeIn( 200 ).show().css( 'display', 'flex' );

		// Some hack. Without it, background filter is not applied.
		$modal.find( '.hcaptcha-whats-new-modal-bg' ).hide().show( 200 );
	} );

	// Lightbox for images.
	$( document ).on( 'click', '.hcaptcha-lightbox', function( e ) {
		e.preventDefault();

		const imgSrc = $( this ).attr( 'href' );

		$( '#hcaptcha-lightbox-img' ).attr( 'src', imgSrc );
		$( '#hcaptcha-lightbox-modal' ).css( 'display', 'flex' );
	} );

	// Close lightbox by click on the background.
	$( '#hcaptcha-lightbox-modal' ).on( 'click', function() {
		$( this ).css( 'display', 'none' );
		$( '#hcaptcha-lightbox-img' ).attr( 'src', '' );
	} );
};

jQuery( document ).ready( whatsNew );
