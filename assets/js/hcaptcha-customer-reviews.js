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
					return formSelector + ', div#tab-reviews';
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
			$( document ).on( 'click', '#tab-title-reviews a', function() {
				hCaptchaBindEvents();
			} );

			$.ajaxPrefilter( function( options ) {
				const $node = $( '#review_form' );

				helper.addHCaptchaData(
					options,
					'cr_submit_review',
					'hcaptcha_customer_reviews_nonce',
					$node,
				);

				// helper.addHCaptchaData(
				// 	options,
				// 	'fl_builder_login_form_submit',
				// 	'hcaptcha_login_nonce',
				// 	$node,
				// );
			} );
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaCustomerReviews = customerReviews;

customerReviews.init();
