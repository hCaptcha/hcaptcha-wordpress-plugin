/* global jQuery, HCaptchaIntegrationsObject */

const integrations = function( $ ) {
	const msgSelector = '#hcaptcha-message';
	const $message = $( msgSelector );
	const $wpwrap = $( '#wpwrap' );
	const $adminmenuwrap = $( '#adminmenuwrap' );

	function clearMessage() {
		$message.removeClass();
		$message.html( '' );
	}

	function showMessage( message, msgClass ) {
		$message.removeClass();
		$message.addClass( msgClass );
		$message.html( `<p>${ message }</p>` );

		const $fixed = $message.clone();

		$message.css( 'visibility', 'hidden' );

		$fixed.css( 'margin', '0px' );
		$fixed.css( 'top', $wpwrap.position().top );
		$fixed.css( 'left', $adminmenuwrap.width() );
		$fixed.width( $( window ).width() - $adminmenuwrap.width() );
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
		showMessage( response, 'hcaptcha-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'hcaptcha-error' );
	}

	function insertIntoTable( $table, key, $element ) {
		let inserted = false;

		$table
			.find( 'tbody' )
			.children()
			.each( function( i, el ) {
				if ( $( el ).attr( 'class' ) > key ) {
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

				$tr.find( 'fieldset' ).attr( 'disabled', ! activate );
				showSuccessMessage( response.data );
				insertIntoTable( $table, 'hcaptcha-integrations-' + status, $tr );
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
