/* global HCaptchaFSTObject */

/**
 * @param HCaptchaFSTObject.absPath
 * @param HCaptchaFSTObject.ajaxPath
 * @param HCaptchaFSTObject.ajaxUrl
 * @param HCaptchaFSTObject.fstToken
 * @param HCaptchaFSTObject.issueTokenAction
 */

document.addEventListener( 'hCaptchaLoaded', function() {
	( async function() {
		const bodyClassName = document.body.className;
		let postId = bodyClassName.match( /post-id-(\d+)/ )?.[ 1 ] ?? '';
		postId = bodyClassName.match( /page-id-(\d+)/ )?.[ 1 ] ?? postId;
		const formBody = new URLSearchParams();

		formBody.set( 'action', HCaptchaFSTObject.issueTokenAction );
		formBody.set( 'absPath', HCaptchaFSTObject.absPath );
		formBody.set( 'ajaxPath', HCaptchaFSTObject.ajaxPath );
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
} );
