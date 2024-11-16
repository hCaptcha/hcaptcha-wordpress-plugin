/* global HCaptchaGeneralObject */

/**
 * @param HCaptchaGeneralObject.getShortcodeHTMLAction
 * @param HCaptchaGeneralObject.getShortcodeHTMLNonce
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const fields = document.querySelector( 'div.mc4wp-form-fields' );

	if ( ! fields ) {
		return;
	}

	const formId = fields.closest( 'form' ).dataset.id;
	let abortController = new AbortController();

	fields.addEventListener( 'mc4wp-refresh', function() {
		const search = /\[hcaptcha[^\]]*]/g;
		const matches = fields.innerHTML.match( search );

		if ( ! matches ) {
			return;
		}

		// Abort the previous request if it is still in progress.
		abortController.abort();
		abortController = new AbortController();

		const formData = new FormData();

		formData.append( 'action', HCaptchaGeneralObject.getShortcodeHTMLAction );
		formData.append( 'nonce', HCaptchaGeneralObject.getShortcodeHTMLNonce );
		formData.append( 'formId', formId );
		formData.append( 'shortcode', matches[ 0 ] );

		fetch( HCaptchaGeneralObject.ajaxUrl, {
			method: 'POST',
			body: formData,
			signal: abortController.signal,
		} )
			.then( ( response ) => response.json() )
			.then( ( json ) => {
				if ( json.success ) {
					fields.innerHTML = fields.innerHTML.replace( search, json.data );
				}
			} )
			.catch( ( error ) => {
				if ( error.name === 'AbortError' ) {
					// eslint-disable-next-line no-console
					console.log( 'Fetch aborted' );
				} else {
					// eslint-disable-next-line no-console
					console.error( 'Error:', error );
				}
			} );
	} );
} );
