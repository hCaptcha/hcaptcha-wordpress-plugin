/* global HCaptchaSystemInfoObject, kaggDialog */

/**
 * @param HCaptchaSystemInfoObject.successMsg
 * @param HCaptchaSystemInfoObject.OKBtnText
 */

document.addEventListener( 'DOMContentLoaded', function() {
	document.querySelector( '#hcaptcha-system-info-wrap .helper' ).addEventListener(
		'click',
		function() {
			/**
			 * @type {HTMLTextAreaElement}
			 */
			const systemInfoTextArea = document.getElementById( 'hcaptcha-system-info' );
			let msg = '';

			navigator.clipboard.writeText( systemInfoTextArea.value )
				.then( () => {
					// Clipboard successfully set.
					msg = HCaptchaSystemInfoObject.successMsg;
				} )
				.catch( () => {
					// Clipboard write failed.
					msg = HCaptchaSystemInfoObject.errorMsg;
				} )
				.finally( () => {
					kaggDialog.confirm( {
						title: msg,
						content: '',
						type: 'info',
						buttons: {
							ok: {
								text: HCaptchaSystemInfoObject.OKBtnText,
							},
						},
					} );
				} );
		},
	);
} );
