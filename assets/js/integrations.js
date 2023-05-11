/* global jQuery, HCaptchaIntegrationsObject */

const integrations = function( $ ) {
	const msgSelector = '#hcaptcha-integrations-message';

	function clearMessage() {
		$( msgSelector ).removeClass();
		$( msgSelector ).html( '' );
	}

	function showMessage( message, msgClass ) {
		$( msgSelector ).removeClass();
		$( msgSelector ).addClass( msgClass );
		$( msgSelector ).html( `<p>${ message }</p>` );
	}

	function showSuccessMessage( response ) {
		showMessage( response, 'hcaptcha-integrations-success' );
	}

	function showErrorMessage( response ) {
		showMessage( response, 'hcaptcha-integrations-error' );
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
		if ( ! confirm( msg.replace( '%s', alt ) ) ) {
			return;
		}

		const data = {
			action: HCaptchaIntegrationsObject.action,
			nonce: HCaptchaIntegrationsObject.nonce,
			activate,
			status,
		};

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

				$tr.find( 'fieldset' ).attr( 'disabled', ! activate );
				showSuccessMessage( response.data );
				insertIntoTable( $table, 'hcaptcha-integrations-' + status, $tr );
			} )
			.fail( function( response ) {
				showErrorMessage( response.statusText );
			} );
	} );
};

window.hCaptchaIntegrations = integrations;

jQuery( document ).ready( integrations );
