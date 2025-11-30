/* global jQuery, HCaptchaWhatsNewObject */

/**
 * @typedef {Object} HCaptchaWhatsNewObject
 * @property {string} ajaxUrl         The URL to send AJAX requests to.
 * @property {string} markShownAction The action to mark the popup as shown.
 * @property {string} markShownNonce  The nonce for the mark the popup as shown action.
 * @property {string} whatsNewParam   The GET parameter for forcing What's New popup.
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
		// If the page was opened with the GET parameter `whats_new`, skip marking as shown.
		// Return a resolved promise so callers can still chain `.always()` safely.
		try {
			const params = new URLSearchParams( window.location.search );

			if ( params.has( HCaptchaWhatsNewObject.whatsNewParam ) ) {
				return jQuery.Deferred().resolve().promise();
			}
		} catch ( e ) {
			// In environments without URLSearchParams, silently ignore and proceed.
		}

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

		// Reuse markShown to record the state, then open the link.
		// Use always() to proceed regardless of network result, matching UX expectations.
		markShown().always( function() {
			window.open( href, '_blank' );
		} );
	} );

	$( document ).on( 'click', '#hcaptcha-whats-new-link', function( e ) {
		e.preventDefault();

		document.body.style.overflow = 'hidden';
		$modal.fadeIn( 200 ).show().css( 'display', 'flex' );

		// Some hack. Without it, background filter is not applied.
		$modal.find( '.hcaptcha-whats-new-modal-bg' ).hide().show( 200 );
	} );
};

jQuery( document ).ready( whatsNew );
