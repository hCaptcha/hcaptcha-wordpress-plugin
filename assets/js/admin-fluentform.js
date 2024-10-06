/* global jQuery, HCaptchaFluentFormObject */

/**
 * @param HCaptchaFluentFormObject.noticeLabel
 * @param HCaptchaFluentFormObject.noticeDescription
 */
jQuery( document ).ready( function( $ ) {
	if ( ! window.location.href.includes( 'page=fluent_forms_settings' ) ) {
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

	const settingsApp = document.querySelector( '#ff_global_settings_option_app' );
	const config = {
		childList: true,
		subtree: true,
	};
	const observer = new MutationObserver( observeHCaptchaWrap );

	updateHCaptchaWrap();
	observer.observe( settingsApp, config );
} );
