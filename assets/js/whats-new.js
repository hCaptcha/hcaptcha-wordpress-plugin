/* global jQuery, HCaptchaWhatsNewObject */

/**
 * @typedef {Object} HCaptchaWhatsNewObject
 * @property {string} ajaxUrl           The URL to send AJAX requests to.
 * @property {string} markShownAction   The action to mark the popup as shown.
 * @property {string} markShownNonce    The nonce for the mark the popup as shown action.
 * @property {string} renderPopupAction The action to render a selected What's New popup.
 * @property {string} renderPopupNonce  The nonce for the render a selected What's New popup action.
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

	function getModal() {
		return $( '#hcaptcha-whats-new-modal' );
	}

	/** @type {jQuery} */
	const $initialModal = getModal();

	if ( ! $initialModal.length ) {
		return;
	}

	if ( $initialModal.css( 'display' ) === 'flex' ) {
		document.body.style.overflow = 'hidden';
	}

	function done() {
		closePopup();
		markShown();
	}

	function closePopup() {
		getModal().fadeOut( 200, function() {
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

		// Return the jqXHR so callers may chain callbacks if needed.
		return $.post( {
			url: HCaptchaWhatsNewObject.ajaxUrl,
			data,
		} );
	}

	function closeVersionDropdown() {
		const $control = getModal().find( '.hcaptcha-whats-new-version-control' );

		$control.removeClass( 'is-open' );
		$control.find( '.hcaptcha-whats-new-version-toggle' ).attr( 'aria-expanded', 'false' );
		$control.find( '.hcaptcha-whats-new-version-list' ).attr( 'hidden', 'hidden' );
	}

	function toggleVersionDropdown() {
		const $control = getModal().find( '.hcaptcha-whats-new-version-control' );
		const isOpen = $control.hasClass( 'is-open' );
		const $list = $control.find( '.hcaptcha-whats-new-version-list' );

		$control.toggleClass( 'is-open', ! isOpen );
		$control.find( '.hcaptcha-whats-new-version-toggle' ).attr( 'aria-expanded', String( ! isOpen ) );

		if ( isOpen ) {
			$list.attr( 'hidden', 'hidden' );
			return;
		}

		$list.removeAttr( 'hidden' );
	}

	function replacePopup( html ) {
		const $response = $( '<div>' ).append( $.parseHTML( html ) );
		const $newModal = $response.find( '#hcaptcha-whats-new-modal' );
		if ( ! $newModal.length ) {
			return false;
		}

		const $newLightbox = $response.find( '#hcaptcha-lightbox-modal' );
		const $currentLightbox = $( '#hcaptcha-lightbox-modal' );

		getModal().replaceWith( $newModal );

		if ( $newLightbox.length ) {
			if ( $currentLightbox.length ) {
				$currentLightbox.replaceWith( $newLightbox );
			} else {
				$newModal.after( $newLightbox );
			}
		}

		document.body.style.overflow = 'hidden';
		getModal().hide().fadeIn( 200 ).show().css( 'display', 'flex' );

		// Some hack. Without it, a background filter is not applied.
		getModal().find( '.hcaptcha-whats-new-modal-bg' ).hide().show( 200 );

		return true;
	}

	function restorePopup( $modal ) {
		document.body.style.overflow = 'hidden';
		$modal.show().css( 'display', 'flex' );
	}

	function loadPopup( $link ) {
		const version = $link.data( 'version' );

		if ( ! version ) {
			return;
		}

		const $currentModal = getModal();

		closeVersionDropdown();

		$currentModal.fadeOut( 200, function() {
			$.post( {
				url: HCaptchaWhatsNewObject.ajaxUrl,
				data: {
					action: HCaptchaWhatsNewObject.renderPopupAction,
					nonce: HCaptchaWhatsNewObject.renderPopupNonce,
					version,
				},
			} )
				.done( function( response ) {
					if ( response && response.success && response.data && replacePopup( response.data.html ) ) {
						return;
					}

					restorePopup( $currentModal );
				} )
				.fail( function() {
					restorePopup( $currentModal );
				} );
		} );
	}

	$( document ).on( 'click', '#hcaptcha-whats-new-close, .hcaptcha-whats-new-modal-bg', function() {
		done();
	} );

	$( document ).on( 'keydown', function( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}

		closeVersionDropdown();
		done();
	} );

	$( document ).on( 'click', '#hcaptcha-whats-new-modal .hcaptcha-whats-new-version-toggle', function( e ) {
		e.preventDefault();
		e.stopPropagation();

		toggleVersionDropdown();
	} );

	$( document ).on( 'click', '#hcaptcha-whats-new-modal .hcaptcha-whats-new-version-list a', function( e ) {
		e.preventDefault();
		e.stopPropagation();

		loadPopup( $( this ) );
	} );

	$( document ).on( 'click', function( e ) {
		if ( $( e.target ).closest( '.hcaptcha-whats-new-version-control' ).length ) {
			return;
		}

		closeVersionDropdown();
	} );

	$( document ).on( 'click', '#hcaptcha-whats-new-modal .hcaptcha-whats-new-button a', function( e ) {
		e.preventDefault();
		e.stopImmediatePropagation();

		const $btn = $( this );
		const href = $btn.attr( 'href' );

		// Reuse markShown to record the state, then open the link.
		// Use always() to proceed regardless of network result, matching UX expectations.
		markShown().always( function() {
			window.open( href, '_blank' );
		} );
	} );

	$( document ).on( 'click', '#hcaptcha-whats-new-link', function( e ) {
		e.preventDefault();

		document.body.style.overflow = 'hidden';
		getModal().fadeIn( 200 ).show().css( 'display', 'flex' );

		// Some hack. Without it, a background filter is not applied.
		getModal().find( '.hcaptcha-whats-new-modal-bg' ).hide().show( 200 );
	} );
};

window.hCaptchaWhatsNew = whatsNew;

jQuery( document ).ready( whatsNew );
