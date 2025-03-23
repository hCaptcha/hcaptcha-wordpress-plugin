/* global jQuery, hCaptcha, HCaptchaCF7Object */

/**
 * @param HCaptchaCF7Object.updateFormAction
 * @param HCaptchaCF7Object.updateFormNonce
 * @param HCaptchaCF7Object.ajaxUrl
 */

/**
 * The Integrations Admin Page script.
 *
 * @param {jQuery} $ The jQuery instance.
 */
const cf7 = function( $ ) {
	const debounce = ( func, delay ) => {
		let debounceTimer;

		return function() {
			const context = this;
			const args = arguments;
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( () => func.apply( context, args ), delay );
		};
	};

	const minifyHTML = ( html ) => {
		return html
			.replace( /\s+/g, ' ' ) // Replace multiple spaces with a single space
			.replace( />\s+</g, '><' ) // Remove spaces between tags
			.replace( /<!--.*?-->/g, '' ); // Remove comments
	};

	const observerCallback = ( mutationsList ) => {
		mutationsList.forEach( ( mutation ) => {
			if (
				mutation.target.classList.contains( 'tag-generator-dialog' ) &&
				mutation.type === 'attributes' &&
				mutation.attributeName === 'open' &&
				mutation.oldValue !== null
			) {
				// Tag inserted.
				debounce( onFormChange, 300 )();
			}
		} );
	};

	const onFormChange = () => {
		const newContent = minifyHTML( $form.val() );

		if ( newContent === content ) {
			return;
		}

		content = newContent;

		const data = {
			action: HCaptchaCF7Object.updateFormAction,
			nonce: HCaptchaCF7Object.updateFormNonce,
			shortcode: $shortcode.val(),
			form: $form.val(),
		};

		if ( ajaxRequest ) {
			ajaxRequest.abort();
		}

		ajaxRequest = $.post( {
			url: HCaptchaCF7Object.ajaxUrl,
			data,
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					return;
				}

				$live.html( response.data );
				hCaptcha.bindEvents();
			} );
	};

	const $shortcode = $( '#wpcf7-shortcode' );
	const $form = $( '#wpcf7-form' );
	let content = minifyHTML( $form.val() );
	const $live = $( '#form-live' );
	let ajaxRequest;

	// Create a MutationObserver instance.
	const observer = new MutationObserver( observerCallback );

	// Configure the observer to watch for attribute changes.
	const config = {
		attributes: true,
		subtree: true,
		attributeFilter: [ 'open' ],
		attributeOldValue: true,
	};

	// Start observing the textarea.
	observer.observe( document.querySelector( '.tag-generator-dialog' ).parentElement, config );

	$form.on( 'input', debounce( onFormChange, 300 ) );
};

window.hCaptchaCF7 = cf7;

jQuery( document ).ready( cf7 );
