document.addEventListener( 'DOMContentLoaded', function() {
	const link = document.querySelector( 'li#wp-admin-bar-hcaptcha-menu-hcaptcha-general a' );

	if ( ! link ) {
		return;
	}

	const href = link.getAttribute( 'href' ) || '';

	if ( href.indexOf( 'playground' ) === -1 ) {
		return;
	}

	const bar = document.getElementById( 'wpadminbar' );

	if ( ! bar ) {
		return;
	}

	bar.style.marginTop = '4px';
} );
