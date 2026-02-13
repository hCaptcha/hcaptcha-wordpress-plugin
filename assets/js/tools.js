/* global jQuery, hCaptchaSettingsBase, HCaptchaToolsObject */

/**
 * @param HCaptchaToolsObject.ajaxUrl
 * @param HCaptchaToolsObject.exportAction
 * @param HCaptchaToolsObject.exportFailed
 * @param HCaptchaToolsObject.exportNonce
 * @param HCaptchaToolsObject.importAction
 * @param HCaptchaToolsObject.importFailed
 * @param HCaptchaToolsObject.importNonce
 * @param HCaptchaToolsObject.selectJsonFile
 * @param HCaptchaToolsObject.toggleSectionAction
 * @param HCaptchaToolsObject.toggleSectionNonce
 */

/**
 * Tools settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const tools = function( $ ) {
	// Export settings.
	$( '#hcaptcha-export-btn' ).on( 'click', function( e ) {
		e.preventDefault();

		const $btn = $( this );
		const includeKeys = $( '#include_keys' ).is( ':checked' ) ? 'on' : '';

		$btn.prop( 'disabled', true );
		hCaptchaSettingsBase.clearMessage();

		$.ajax( {
			url: HCaptchaToolsObject.ajaxUrl,
			type: 'POST',
			data: {
				action: HCaptchaToolsObject.exportAction,
				nonce: HCaptchaToolsObject.exportNonce,
				include_keys: includeKeys,
			},
			success( response ) {
				if ( response && response.success !== false ) {
					const data = JSON.stringify( response, null, 4 );
					const blob = new Blob( [ data ], { type: 'application/json' } );
					const url = window.URL.createObjectURL( blob );
					const a = document.createElement( 'a' );
					const date = new Date().toISOString().slice( 0, 10 );

					a.href = url;
					a.download = 'hcaptcha-settings-' + date + '.json';
					document.body.appendChild( a );
					a.click();
					window.URL.revokeObjectURL( url );
					document.body.removeChild( a );
				} else {
					const message = ( response.data && response.data.message )
						? response.data.message
						: HCaptchaToolsObject.exportFailed;

					hCaptchaSettingsBase.showErrorMessage( message );
				}
			},
			error() {
				hCaptchaSettingsBase.showErrorMessage( HCaptchaToolsObject.exportFailed );
			},
			complete() {
				$btn.prop( 'disabled', false );
			},
		} );
	} );

	// Import settings.
	$( '#hcaptcha-import-btn' ).on( 'click', function( e ) {
		e.preventDefault();

		const $fileInput = $( '#hcaptcha-import-file' );
		const file = $fileInput[ 0 ].files[ 0 ];

		if ( ! file ) {
			hCaptchaSettingsBase.showErrorMessage( HCaptchaToolsObject.selectJsonFile );

			return;
		}

		const formData = new FormData();
		formData.append( 'action', HCaptchaToolsObject.importAction );
		formData.append( 'nonce', HCaptchaToolsObject.importNonce );
		formData.append( 'import_file', file );

		const $btn = $( this );

		$btn.prop( 'disabled', true );
		hCaptchaSettingsBase.clearMessage();

		$.ajax( {
			url: HCaptchaToolsObject.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success( response ) {
				if ( response.success ) {
					hCaptchaSettingsBase.showSuccessMessage( response.data.message );
				} else {
					const message = ( response.data && response.data.message )
						? response.data.message
						: HCaptchaToolsObject.importFailed;

					hCaptchaSettingsBase.showErrorMessage( message );
				}
			},
			error() {
				hCaptchaSettingsBase.showErrorMessage( HCaptchaToolsObject.importFailed );
			},
			complete() {
				$btn.prop( 'disabled', false );
				$fileInput.val( '' );
			},
		} );
	} );

	// Custom file upload button.
	const $input = $( '#hcaptcha-import-file' );
	const $label = $( '.hcaptcha-file-name' );

	$input.on( 'change', function() {
		if ( this.files && this.files.length > 0 ) {
			$label.text( this.files[ 0 ].name );
			$label.addClass( 'is-selected' );
		} else {
			$label.text( $label.data( 'empty' ) );
			$label.removeClass( 'is-selected' );
		}
	} );
};

window.hCaptchaTools = tools;

jQuery( document ).ready( tools );
