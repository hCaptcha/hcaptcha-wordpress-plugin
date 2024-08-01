/**
 * WPDiscuz script file.
 */

/* global jQuery */

document.addEventListener( 'DOMContentLoaded', function() {
	wp.hooks.addFilter(
		'hcaptcha.ajaxSubmitButton',
		'hcaptcha',
		( isAjaxSubmitButton, submitButtonElement ) => {
			if ( submitButtonElement.classList.contains( 'wc_comm_submit' ) ) {
				return true;
			}

			return isAjaxSubmitButton;
		}
	);

	const threadsElement = document.getElementById( 'wpd-threads' );

	if ( ! threadsElement ) {
		return;
	}

	// Define the callback function for the MutationObserver.
	const observerCallback = function( mutationsList ) {
		for ( const mutation of mutationsList ) {
			[ ...mutation.addedNodes ].map( ( node ) => {
				if (
					node.nodeType === Node.ELEMENT_NODE &&
					node.classList.contains( 'wpd-form' ) &&
					node.querySelector( '.h-captcha' )
				) {
					window.hCaptchaBindEvents();
				}

				return node;
			} );
		}
	};

	// Create a MutationObserver instance.
	const observer = new MutationObserver( observerCallback );

	const config = {
		childList: true,
		subtree: true,
	};

	// Start observing the #wpd-threads element for child node additions.
	observer.observe( threadsElement, config );
} );

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'wpdAddComment' ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
