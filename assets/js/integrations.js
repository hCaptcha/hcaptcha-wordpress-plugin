/* global jQuery, HCaptchaIntegrationsObject */

const integrations = function( $ ) {
	const msgSelector = '#hcaptcha-message';
	let $message = $( msgSelector );
	const $wpwrap = $( '#wpwrap' );
	const $adminmenuwrap = $( '#adminmenuwrap' );

	function clearMessage() {
		$message.remove();
		$( '<div id="hcaptcha-message"></div>' ).insertAfter( '#hcaptcha-options h2' );
		$message = $( msgSelector );
	}

	function showMessage( message, msgClass ) {
		$message.removeClass();
		$message.addClass( msgClass + ' notice settings-error is-dismissible' );
		$message.html( `<p>${ message }</p>` );
		$( document ).trigger( 'wp-updates-notice-added' );

		const $fixed = $message.clone();

		$message.css( 'visibility', 'hidden' );

		$fixed.css( 'margin', '0px' );
		$fixed.css( 'top', $wpwrap.position().top );

		const adminMenuWrapWidth = $adminmenuwrap.css( 'display' ) === 'block'
			? $adminmenuwrap.width()
			: 0;

		$fixed.css( 'left', adminMenuWrapWidth );
		$fixed.width( $( window ).width() - adminMenuWrapWidth );
		$fixed.css( 'position', 'fixed' );
		$( 'body' ).append( $fixed );

		setTimeout(
			() => {
				$message.css( 'visibility', 'unset' );
				$fixed.remove();
			},
			3000
		);
	}

	function showSuccessMessage( response ) {
		showMessage( response, 'notice-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'notice-error' );
	}

	function insertIntoTable( $table, key, $element ) {
		let inserted = false;
		const lowerKey = key.toLowerCase();

		$table
			.find( 'tbody' )
			.children()
			.each( function( i, el ) {
				let alt = $( el ).find( '.hcaptcha-integrations-logo img' ).attr( 'alt' );
				alt = alt ? alt : '';
				alt = alt.replace( ' Logo', '' );
				const lowerAlt = alt.toLowerCase();

				if ( lowerAlt > lowerKey ) {
					$element.insertBefore( $( el ) );
					inserted = true;
					return false;
				}
			} );

		if ( ! inserted ) {
			$table.find( 'tbody' ).append( $element );
		}
	}

	$( '.form-table img' ).on( 'click', function( event ) {
		event.preventDefault();
		clearMessage();

		const $target = $( event.target );
		let alt = $target.attr( 'alt' );
		alt = alt ? alt : '';
		alt = alt.replace( ' Logo', '' );

		if ( -1 !== $.inArray( alt, [ 'WP Core', 'Avada', 'Divi' ] ) ) {
			return;
		}

		const $tr = $target.closest( 'tr' );
		let status = $tr.attr( 'class' );
		status = status.replace( 'hcaptcha-integrations-', '' );
		const $fieldset = $tr.find( 'fieldset' );

		// noinspection JSUnresolvedVariable
		let msg = HCaptchaIntegrationsObject.deactivateMsg;
		let activate = false;

		if ( $fieldset.attr( 'disabled' ) ) {
			// noinspection JSUnresolvedVariable
			msg = HCaptchaIntegrationsObject.activateMsg;
			activate = true;
		}

		// eslint-disable-next-line no-alert
		if ( ! event.ctrlKey && ! confirm( msg.replace( '%s', alt ) ) ) {
			return;
		}

		const activateClass = activate ? 'on' : 'off';
		const data = {
			action: HCaptchaIntegrationsObject.action,
			nonce: HCaptchaIntegrationsObject.nonce,
			activate,
			status,
		};

		$tr.addClass( activateClass );

		// noinspection JSVoidFunctionReturnValueUsed
		$.post( {
			url: HCaptchaIntegrationsObject.ajaxUrl,
			data,
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );
					return;
				}

				const $table = $( '.form-table' ).eq( activate ? 0 : 1 );
				const top = $wpwrap.position().top;

				$fieldset.attr( 'disabled', ! activate );
				$fieldset.find( 'input' ).attr( 'disabled', ! activate );
				showSuccessMessage( response.data );
				insertIntoTable( $table, alt, $tr );
				$( 'html, body' ).animate(
					{
						scrollTop: $tr.offset().top - top - $message.outerHeight(),
					},
					1000
				);
			} )
			.fail( function( response ) {
				showErrorMessage( response.statusText );
			} )
			.always( function() {
				$tr.removeClass( 'on off' );
			} );
	} );
};

window.hCaptchaIntegrations = integrations;

jQuery( document ).ready( integrations );
