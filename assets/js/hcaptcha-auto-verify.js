/* globals HCaptchaAutoVerifyObject, hCaptchaBindEvents */

document.addEventListener( 'DOMContentLoaded', () => {
	/**
	 * Get a readable error without injecting response markup into the page.
	 *
	 * @param {Response} response Server response.
	 * @return {Promise<string>} Error message.
	 */
	async function getErrorMessage( response ) {
		const body = ( await response.text() ).trim();

		if ( ! body ) {
			return HCaptchaAutoVerifyObject.errorMsg;
		}

		if ( response.headers?.get( 'content-type' )?.includes( 'application/json' ) ) {
			try {
				const data = JSON.parse( body );
				const message = data?.data?.message ?? data?.data ?? data?.message;

				return typeof message === 'string' && message ? message : HCaptchaAutoVerifyObject.errorMsg;
			} catch {
				return HCaptchaAutoVerifyObject.errorMsg;
			}
		}

		if ( ! body.startsWith( '<' ) ) {
			return body;
		}

		const documentResponse = new DOMParser().parseFromString( body, 'text/html' );
		const wpDieMessage = documentResponse.querySelector( '.wp-die-message' );

		if ( wpDieMessage ) {
			return wpDieMessage.textContent.trim() || HCaptchaAutoVerifyObject.errorMsg;
		}

		if ( /<(?:!doctype|html|head|body|style|title)\b/i.test( body ) ) {
			return HCaptchaAutoVerifyObject.errorMsg;
		}

		return documentResponse.body.textContent.trim() || HCaptchaAutoVerifyObject.errorMsg;
	}

	/**
	 * Identify the standard WordPress comment endpoint.
	 *
	 * @param {HTMLFormElement} form Form.
	 * @return {boolean} Whether this is a WordPress comment form.
	 */
	function isWordPressCommentForm( form ) {
		const action = new URL( form.action, window.location.href );

		return action.pathname.endsWith( '/wp-comments-post.php' ) &&
			!! form.querySelector( 'input[name="comment_post_ID"]' );
	}

	[ ...document.querySelectorAll( 'form' ) ].forEach( ( formElement ) => {
		if ( ! formElement.querySelector( 'h-captcha[data-ajax="true"]' ) ) {
			return;
		}

		let submitting = false;

		formElement.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			event.stopPropagation();

			if ( submitting ) {
				return;
			}

			submitting = true;

			let resultContainer = formElement.previousElementSibling;

			if ( ! resultContainer?.matches( 'p.autoverify-result' ) ) {
				resultContainer = document.createElement( 'p' );
				resultContainer.classList.add( 'autoverify-result' );
				formElement.parentNode.insertBefore( resultContainer, formElement );
			}

			resultContainer.setAttribute( 'role', 'status' );
			resultContainer.textContent = HCaptchaAutoVerifyObject.submittingMsg;
			formElement.setAttribute( 'aria-busy', 'true' );

			const formData = new FormData( formElement );
			const submitter = event.submitter;

			if ( submitter?.name ) {
				formData.append( submitter.name, submitter.value );
			}

			const isCommentForm = isWordPressCommentForm( formElement );
			let succeeded = false;

			try {
				const response = await fetch( formElement.action, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				} );

				if ( isCommentForm && response.ok && response.redirected && response.url ) {
					succeeded = true;
					window.location.assign( response.url );

					return;
				}

				if ( ! response.ok || isCommentForm ) {
					resultContainer.setAttribute( 'role', 'alert' );
					resultContainer.textContent = await getErrorMessage( response );

					return;
				}

				succeeded = true;
				resultContainer.textContent = HCaptchaAutoVerifyObject.successMsg;
				hCaptchaBindEvents();
			} catch {
				resultContainer.setAttribute( 'role', 'alert' );
				resultContainer.textContent = HCaptchaAutoVerifyObject.networkErrorMsg;
			} finally {
				if ( ! succeeded ) {
					window.hCaptchaReset?.( formElement );
					await window.hCaptchaFST?.getToken();

					if ( submitter?.dataset.hCaptchaDisabled === '1' ) {
						submitter.disabled = false;
						submitter.dataset.hCaptchaDisabled = '0';
					}
				}

				formElement.removeAttribute( 'aria-busy' );
				submitting = false;
			}
		} );
	} );
} );
