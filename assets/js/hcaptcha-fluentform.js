document.addEventListener( 'DOMContentLoaded', function() {
	const convFormSelector = '.ffc_conv_form';
	const convForms = document.querySelectorAll( convFormSelector );

	if ( convForms.length === 0 ) {
		return;
	}

	let submitBtn = null;
	let submitBtnVisible;
	const config = {
		attributes: true,
		childList: true,
		subtree: true,
		attributeOldValue: true,
	};

	const isSubmitVisible = ( mutation ) => {
		const form = mutation.target.closest( convFormSelector );

		if ( form === null ) {
			return false;
		}

		submitBtn = form.querySelector( '.ff-btn' );

		if ( submitBtn === null ) {
			return false;
		}

		return submitBtn.offsetParent !== null;
	};

	// eslint-disable-next-line no-unused-vars
	const callback = ( mutationList, observer ) => {
		for ( const mutation of mutationList ) {
			if (
				mutation.type !== 'attributes' ||
				! mutation.target.classList.contains( 'q-form' ) ||
				! mutation.target.classList.contains( 'f-focused' )
			) {
				continue;
			}

			const visible = isSubmitVisible( mutation );

			if ( visible !== submitBtnVisible ) {
				submitBtnVisible = visible;

				if ( visible ) {
					const hCaptchaClass = 'h-captcha';
					const hCaptchaHiddenClass = 'h-captcha-hidden';
					const hiddenCaptcha = document.getElementsByClassName( hCaptchaHiddenClass )[ 0 ];

					if ( hiddenCaptcha && submitBtn ) {
						const hCaptcha = hiddenCaptcha.cloneNode( true );

						const form = document.createElement( 'form' );
						form.setAttribute( 'method', 'POST' );

						// Wrap submit button by form.
						submitBtn.parentNode.insertBefore( form, submitBtn );
						form.appendChild( submitBtn );

						submitBtn.before( hCaptcha );

						hCaptcha.classList.remove( hCaptchaHiddenClass );
						hCaptcha.classList.add( hCaptchaClass );
						hCaptcha.style.display = 'block';

						window.hCaptchaBindEvents();
					}
				}
			}

			return;
		}
	};

	[ ...convForms ].map( ( form ) => {
		const observer = new MutationObserver( callback );
		observer.observe( form, config );
		return form;
	} );
} );

const { fetch: originalFetch } = window;

// Intercept fluent form fetch to add hCaptcha data.
window.fetch = async( ...args ) => {
	const [ resource, config ] = args;

	// @param {FormData} body
	const body = config.body;
	const formId = body.get( 'form_id' );
	const inputName = 'h-captcha-response';
	const widgetName = 'hcaptcha-widget-id';
	let data = body.get( 'data' );

	if ( 'fluentform_submit' === body.get( 'action' ) && ! data.includes( inputName ) ) {
		const hCaptchaResponse =
			document.querySelector( '.ff_conv_app_' + formId + ' [name="' + inputName + '"]' );
		const id =
			document.querySelector( '.ff_conv_app_' + formId + ' [name="' + widgetName + '"]' );

		if ( hCaptchaResponse ) {
			data = data + '&' + inputName + '=' + hCaptchaResponse.value;
		}

		if ( id ) {
			data = data + '&' + widgetName + '=' + id.value;
		}

		body.set( 'data', data );
		config.body = body;
	}

	// noinspection JSCheckFunctionSignatures
	return await originalFetch( resource, config );
};
