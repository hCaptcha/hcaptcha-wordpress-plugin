/* global jQuery, HCaptchaForminatorObject */

/**
 * @param HCaptchaForminatorObject.noticeLabel
 * @param HCaptchaForminatorObject.noticeDescription
 * @param window.hCaptchaForminator
 */

const forminator = window.hCaptchaForminator || ( function( document, window, $ ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		// Init app.
		init() {
			document.addEventListener( 'DOMContentLoaded', app.loaded );
			$( app.ready );
			$( document ).on( 'ajaxSuccess', app.ajaxSuccessHandler );
		},

		// DOMContentLoaded handler.
		loaded() {
			if ( ! app.getLocationHref().includes( 'page=forminator-cform' ) ) {
				return;
			}

			const config = {
				attributes: true,
				subtree: true,
			};

			const callback = ( mutationList ) => {
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
							row.querySelector( '.sui-settings-label' ).innerHTML = HCaptchaForminatorObject.noticeLabel;
							row.querySelector( '.sui-description' ).innerHTML = HCaptchaForminatorObject.noticeDescription;
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
		},

		// jQuery ready handler.
		ready() {
			if ( ! app.getLocationHref().includes( 'page=forminator-settings' ) ) {
				return;
			}

			const $hcaptchaTab = $( '#hcaptcha-tab' );

			$hcaptchaTab.find( '.sui-settings-label' ).first()
				.html( HCaptchaForminatorObject.noticeLabel ).css( 'display', 'block' );
			$hcaptchaTab.find( '.sui-description' ).first()
				.html( HCaptchaForminatorObject.noticeDescription ).css( 'display', 'block' );
		},

		// jQuery ajaxSuccess handler.
		ajaxSuccessHandler( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== 'forminator_load_form' ) {
				return;
			}

			window.hCaptchaBindEvents();
		},

		getLocationHref() {
			return window.location.href;
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaForminator = forminator;

forminator.init();
