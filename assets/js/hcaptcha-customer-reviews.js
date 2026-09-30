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
					return formSelector + ', div#tab-reviews, div#tab-cr_qna, div.cr-qna-list-inl-answ, div.cr-qna-new-q-form';
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
			$( document ).on( 'ajaxComplete', app.ajaxComplete );

			$( document ).on(
				'click',
				'#tab-title-reviews a, #tab-title-cr_qna a, ' +
				'button.cr-review-form-continue.cr-review-form-error, ' +
				'button.cr-qna-ask-button',
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
				let $fstNode;

				switch ( action ) {
					case 'cr_submit_review':
						$node = $( '#review_form' );
						$fstNode = $( '#tab-reviews' );
						break;
					case 'cr_new_qna':
						const questionID = urlParams.get( 'questionID' );

						$node = questionID ? $( `[data-question="${ questionID }"]` ) : $( '#cr_qna' );
						$fstNode = $( '#tab-cr_qna' );
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

				const fstToken = $fstNode.find( '[name="hcap_fst_token"]' ).val();

				if ( typeof fstToken === 'string' ) {
					const params = new URLSearchParams( options.data );

					params.set( 'hcap_fst_token', fstToken );
					options.data = params.toString();
				}
			} );
		},

		/**
		 * Refresh hCaptcha data after a protected Customer Reviews AJAX request.
		 *
		 * @param {Object} event    The event object.
		 * @param {Object} xhr      The XMLHttpRequest object.
		 * @param {Object} settings The AJAX settings object.
		 *
		 * @return {void}
		 */
		ajaxComplete( event, xhr, settings ) {
			const action = helper.getAction( settings, 'action' );

			if ( ! [ 'cr_submit_review', 'cr_new_qna' ].includes( action ) ) {
				return;
			}

			hCaptchaBindEvents();

			if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
				window.hCaptchaFST.getToken();
			}
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaCustomerReviews = customerReviews;

customerReviews.init();
