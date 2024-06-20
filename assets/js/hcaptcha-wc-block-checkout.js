const { useState, useEffect, createElement, createRoot, render } = wp.element;

function BlockCheckout() {
	// const [ onCheckoutValidation, checkValidation ] = useState( 0 );
	const [ onCheckoutValidation, setCheckoutValidation ] = useState( 0 );

	useEffect( () => {
		let unsubscribeProcessing = () => {
		};

		const isMyCheck = false;

		if ( ! isMyCheck ) {
			// unsubscribeProcessing = onCheckoutValidation( checkValidation, 0 );
			unsubscribeProcessing = () => setCheckoutValidation( 0 );
		}

		return () => {
			if ( ! isMyCheck && typeof unsubscribeProcessing === 'function' ) {
				unsubscribeProcessing();
			}
		};
	}, [ onCheckoutValidation ] );

	return createElement( 'div', null, 'Block Checkout Component' );
}

document.addEventListener( 'DOMContentLoaded', function() {
	const target = document.querySelector( '.wp-block-woocommerce-checkout' );

	if ( target ) {
		const root = document.createElement( 'div' );
		target.appendChild( root );

		const blockCheckoutElement = createElement( BlockCheckout );

		if ( createRoot ) {
			createRoot( target ).render( blockCheckoutElement );
		} else {
			render( blockCheckoutElement, target );
		}
	}
} );
