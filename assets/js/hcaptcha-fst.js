/* global HCaptchaFSTObject */

/**
 * @param HCaptchaFSTObject.ajaxUrl
 * @param HCaptchaFSTObject.issueTokenAction
 */

/**
 * The 'Form Submit Token' script.
 *
 * @param {Document} document The document instance.
 */
const fst = window.hCaptchaFST || ( function( document ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		init() {
			let hCaptchaLoaded;

			document.addEventListener( 'hCaptchaAfterBindEvents', function() {
				if ( ! hCaptchaLoaded ) {
					return;
				}

				app.getToken();
			} );

			document.addEventListener( 'hCaptchaLoaded', function() {
				app.getToken();

				hCaptchaLoaded = true;
			} );
		},

		getToken() {
			( async function() {
				const bodyClassName = document.body.className;
				let postId = bodyClassName.match( /post-id-(\d+)/ )?.[ 1 ] ?? '';
				postId = bodyClassName.match( /page-id-(\d+)/ )?.[ 1 ] ?? postId;
				const formBody = new URLSearchParams();

				formBody.set( 'action', HCaptchaFSTObject.issueTokenAction );
				formBody.set( 'postId', postId );

				const res = await fetch( HCaptchaFSTObject.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					cache: 'no-store',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
					},
					body: formBody.toString(),
				} );
				const body = await res.json();
				const token = body?.data?.token ?? '';

				document.querySelectorAll( '[name="hcap_fst_token"]' ).forEach( ( element ) => {
					element.value = token;
				} );
			}() );
		},
	};

	return app;
}( document ) );

window.hCaptchaFST = fst;

fst.init();
