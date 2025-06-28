/* global jQuery, HCaptchaFluentFormObject */

/**
 * @param HCaptchaFluentFormObject.noticeLabel
 * @param HCaptchaFluentFormObject.noticeDescription
 */

const fluentForm = window.hCaptchaFluentForm || ( function( document, window, $ ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		// Init app.
		init() {
			$( app.ready );
		},

		ready() {
			if ( ! app.getLocationHref().includes( 'page=fluent_forms_settings' ) ) {
				return;
			}

			const updateHCaptchaWrap = () => {
				const $hCaptchaWrap = $( '.ff_hcaptcha_wrap' );

				if ( $hCaptchaWrap.length === 0 ) {
					return;
				}

				$hCaptchaWrap.find( '.ff_card_head h5' )
					.html( HCaptchaFluentFormObject.noticeLabel ).css( 'display', 'block' );
				$hCaptchaWrap.find( '.ff_card_head p' ).first()
					.html( HCaptchaFluentFormObject.noticeDescription ).css( 'display', 'block' );
			};

			const observeHCaptchaWrap = ( mutationList ) => {
				for ( const mutation of mutationList ) {
					[ ...mutation.addedNodes ].map( ( node ) => {
						if ( ! ( node.classList !== undefined && node.classList.contains( 'ff_hcaptcha_wrap' ) ) ) {
							return node;
						}

						updateHCaptchaWrap();

						return node;
					} );
				}
			};

			const config = {
				childList: true,
				subtree: true,
			};
			const observer = new MutationObserver( observeHCaptchaWrap );

			updateHCaptchaWrap();
			observer.observe( document.querySelector( '#ff_global_settings_option_app' ), config );
		},

		getLocationHref() {
			return window.location.href;
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaFluentForm = fluentForm;

fluentForm.init();
