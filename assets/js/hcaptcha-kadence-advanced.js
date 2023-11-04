window.fetch = new Proxy( window.fetch, {
	apply( actualFetch, that, args ) {
		// Forward function call to the original fetch
		const result = Reflect.apply( actualFetch, that, args );

		// noinspection JSUnusedLocalSymbols
		result.finally( () => {
			// @param {FormData} body
			const body = args[ 1 ].body;

			if ( 'kb_process_advanced_form_submit' === body.get( 'action' ) ) {
				const formId = body.get( '_kb_adv_form_post_id' );
				const form = document.querySelector( 'input[value="' + formId + '"]' ).closest( 'form' );

				window.hCaptchaReset( form );
			}
		} );

		return result;
	},
} );
