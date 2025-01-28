/* globals HCaptchaAutoVerifyObject */

document.addEventListener( 'DOMContentLoaded', () => {
	const formSelector = 'form';

	[ ...document.querySelectorAll( formSelector ) ].map( ( formElement ) => {
		const hCaptchaAjaxSelector = 'h-captcha[data-ajax="true"]';
		const hCaptcha = formElement.querySelector( hCaptchaAjaxSelector );

		if ( ! hCaptcha ) {
			return formElement;
		}

		formElement.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			event.stopPropagation();

			/**
			 * @type {HTMLElement}
			 */
			const currentTarget = event.currentTarget;

			/**
			 * @type {HTMLFormElement} formElement
			 */
			const currentFormElement = currentTarget.closest( formSelector );
			let resultContainer = currentFormElement.previousElementSibling;
			const autoverifyResultClass = 'autoverify-result';
			const autoverifyResultTag = 'p';

			if ( resultContainer && resultContainer.matches( autoverifyResultTag + '.' + autoverifyResultClass ) ) {
				resultContainer.innerHTML = '';
			} else {
				resultContainer = document.createElement( autoverifyResultTag );
				resultContainer.classList.add( autoverifyResultClass );
				currentFormElement.parentNode.insertBefore( resultContainer, currentFormElement );
			}

			const formData = new FormData( currentFormElement );

			try {
				const response = await fetch( currentFormElement.action, {
					method: 'POST',
					body: formData,
				} );

				if ( ! response.ok ) {
					resultContainer.innerHTML = await response.text();

					return;
				}

				resultContainer.innerHTML = HCaptchaAutoVerifyObject.successMsg;
			} catch ( error ) {
				resultContainer.innerHTML = error;
			}

			const currentHCaptcha = currentFormElement.querySelector( hCaptchaAjaxSelector );

			window.hCaptchaReset( currentHCaptcha );
		} );

		return formElement;
	} );
} );
