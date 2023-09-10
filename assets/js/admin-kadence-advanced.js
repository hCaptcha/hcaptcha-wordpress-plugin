/* global HCaptchaKadenceAdvancedFormObject */

// noinspection JSUnusedLocalSymbols
/**
 * @param HCaptchaKadenceAdvancedFormObject.noticeLabel
 * @param HCaptchaKadenceAdvancedFormObject.noticeDescription
 */
document.addEventListener( 'DOMContentLoaded', function() {
	const panelClass = 'components-panel__body';

	const observeEditor = ( mutationList ) => {
		for ( const mutation of mutationList ) {
			[ ...mutation.addedNodes ].map( ( node ) => {
				if ( ! ( node.classList !== undefined && node.classList.contains( panelClass ) ) ) {
					return node;
				}

				updatePanel( node );

				return node;
			} );
		}
	};

	function updatePanel( panel ) {
		const config = {
			childList: true,
			subtree: true,
		};
		const observer = new MutationObserver( observePanel );

		observer.observe( panel, config );

		updateInputs( panel );
	}

	const observePanel = ( mutationList ) => {
		for ( const mutation of mutationList ) {
			[ ...mutation.addedNodes ].map( ( node ) => {
				if ( ! ( node.hasOwnProperty( 'querySelector' ) && node.querySelector( 'button' ) ) ) {
					return node;
				}

				updateInputs( node.closest( '.' + panelClass ) );

				return node;
			} );
		}
	};

	function updateInputs( panel ) {
		const select = panel.querySelector( 'select' );

		if ( ! select ) {
			return;
		}

		const hasHCaptcha = [ ...select.options ].reduce(
			( accumulator, currentOption ) => {
				return accumulator || currentOption.value === 'hcaptcha';
			},
			false
		);

		if ( ! hasHCaptcha ) {
			return;
		}

		const inputs = panel.querySelectorAll( 'input' );

		[ ...inputs ].map( ( input ) => {
			input.disabled = false;

			return input;
		} );

		const noticeClass = 'hcaptcha-notice';
		let notice = panel.querySelector( '.' + noticeClass );

		if ( notice ) {
			notice.remove();
		}

		if ( select.value !== 'hcaptcha' ) {
			return;
		}

		notice = document.createElement( 'div' );
		notice.classList.add( noticeClass );

		const label = document.createElement( 'label' );
		label.innerHTML = HCaptchaKadenceAdvancedFormObject.noticeLabel;

		const description = document.createElement( 'p' );
		description.innerHTML = HCaptchaKadenceAdvancedFormObject.noticeDescription;

		notice.appendChild( label );
		notice.appendChild( description );

		select.closest( '.components-base-control' ).after( notice );

		[ ...inputs ].map( ( input ) => {
			input.disabled = true;

			return input;
		} );
	}

	const config = {
		childList: true,
		subtree: true,
	};
	const observer = new MutationObserver( observeEditor );

	observer.observe( document.getElementById( 'editor' ), config );
} );
