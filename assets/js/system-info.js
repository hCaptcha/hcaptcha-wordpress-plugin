/* global HCaptchaSystemInfoObject */

document.addEventListener( 'DOMContentLoaded', function() {
	document.querySelector( '#hcaptcha-system-info-wrap .helper' ).addEventListener(
		'click',
		function() {
			const systemInfoTextArea = document.getElementById( 'hcaptcha-system-info' );

			navigator.clipboard.writeText( systemInfoTextArea.value ).then(
				() => {
					// Clipboard successfully set.
				},
				() => {
					// Clipboard write failed.
				},
			);

			// noinspection JSUnresolvedVariable
			const message = HCaptchaSystemInfoObject.copiedMsg;

			// eslint-disable-next-line no-alert
			alert( message );
		},
	);
} );
