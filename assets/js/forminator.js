/* global jQuery, HCaptchaForminatorObject */

/**
 * @param HCaptchaForminatorObject.notificationLabel
 * @param HCaptchaForminatorObject.notificationDescription
 */
jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'forminator_load_form' ) {
		return;
	}

	window.hCaptchaBindEvents();
} );

document.addEventListener( 'DOMContentLoaded', function() {
	const config = {
		attributes: true,
		childList: true,
		subtree: true,
		attributeOldValue: true,
	};

	// eslint-disable-next-line no-unused-vars
	const callback = ( mutationList, observer ) => {
		for ( const mutation of mutationList ) {
			if (
				! (
					mutation.type === 'attributes' &&
					mutation.target.id === 'forminator-field-hcaptcha_size'
				)
			) {
				continue;
			}

			const hCaptchaButton = document.querySelectorAll( '#forminator-modal-body--captcha .sui-tabs-content .sui-tabs-menu .sui-tab-item' )[ 1 ];

			if ( hCaptchaButton === undefined || ! hCaptchaButton.classList.contains( 'active' ) ) {
				return;
			}

			const content = hCaptchaButton.closest( '.sui-tab-content' );

			const rows = content.querySelectorAll( '.sui-box-settings-row' );

			[ ...rows ].map( ( row, index ) => {
				if ( index === 1 ) {
					row.querySelector( '.sui-settings-label' ).innerHTML = HCaptchaForminatorObject.notificationLabel;
					row.querySelector( '.sui-description' ).innerHTML = HCaptchaForminatorObject.notificationDescription;
					row.querySelector( '.sui-form-field' ).style.display = 'none';
				}

				if ( index > 1 ) {
					row.style.display = 'none';
				}

				return row;
			} );

			return;
		}
	};

	const observer = new MutationObserver( callback );
	observer.observe( document.body, config );
} );
