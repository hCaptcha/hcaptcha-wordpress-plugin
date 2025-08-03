/* global jQuery, hCaptchaBindEvents */

import { helper } from './hcaptcha-helper.js';

const customerReviews = window.hCaptchaCustomerReviews || ( function( document, window, $ ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		init() {
			wp.hooks.addFilter(
				'hcaptcha.formSelector',
				'hcaptcha',
				( formSelector ) => {
					return formSelector + ', div#tab-reviews, div#tab-cr_qna, div.cr-qna-list-inl-answ';
				},
			);

			wp.hooks.addFilter(
				'hcaptcha.submitButtonSelector',
				'hcaptcha',
				( submitButtonSelector ) => {
					return submitButtonSelector + ', button.cr-review-form-submit';
				},
			);

			$( app.ready );
		},

		ready() {
			$( document ).on(
				'click',
				'#tab-title-reviews a, #tab-title-cr_qna a, ' +
				'button.cr-review-form-continue.cr-review-form-error',
				function() {
					hCaptchaBindEvents();
				},
			);

			$.ajaxPrefilter( function( options ) {
				const data = options.data ?? '';

				if ( typeof data !== 'string' ) {
					return;
				}

				const urlParams = new URLSearchParams( data );
				const action = urlParams.get( 'action' );
				let $node;

				switch ( action ) {
					case 'cr_submit_review':
						$node = $( '#review_form' );
						break;
					case 'cr_new_qna':
						const questionID = urlParams.get( 'questionID' );

						$node = questionID ? $( `[data-question="${ questionID }"]` ) : $( '#cr_qna' );
						break;
					default:
						return;
				}

				helper.addHCaptchaData(
					options,
					action,
					'hcaptcha_customer_reviews_nonce',
					$node,
				);
			} );
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaCustomerReviews = customerReviews;

customerReviews.init();
