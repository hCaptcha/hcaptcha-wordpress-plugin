document.addEventListener( 'DOMContentLoaded', function() {
	const formSelector = '.ffc_conv_form';
	const qFormSelector = '.ffc_conv_form .q-form';
	const qForms = document.querySelectorAll( qFormSelector );

	if ( qForms.length === 0 ) {
		return;
	}

	let submitBtn = null;
	const config = {
		attributes: true,
		attributeOldValue: true,
	};

	const isSubmitVisible = ( el ) => {
		const qForm = el.closest( qFormSelector );

		if ( qForm === null ) {
			return false;
		}

		submitBtn = qForm.querySelector( '.ff-btn' );

		if ( submitBtn === null ) {
			return false;
		}

		return submitBtn.offsetParent !== null;
	};

	const hasOwnHCaptcha = ( el ) => {
		const form = el.closest( formSelector );

		return form.querySelector( '#hcaptcha-container' ) !== null;
	};

	// eslint-disable-next-line no-unused-vars
	const callback = ( mutationList, observer ) => {
		for ( const mutation of mutationList ) {
			if (
				! (
					mutation.type === 'attributes' &&
					mutation.attributeName === 'class' &&
					mutation.oldValue && mutation.oldValue.includes( 'q-is-inactive' )
				)
			) {
				continue;
			}

			if ( ! isSubmitVisible( mutation.target ) ) {
				return;
			}

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

			return;
		}
	};

	[ ...qForms ].map( ( qForm ) => {
		if ( hasOwnHCaptcha( qForm ) ) {
			return qForm;
		}

		const observer = new MutationObserver( callback );
		observer.observe( qForm, config );
		return qForm;
	} );
} );

const { fetch: originalFetch } = window;

// Intercept fluent form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
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
