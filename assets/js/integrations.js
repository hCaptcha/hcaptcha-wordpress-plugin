/* global jQuery, HCaptchaIntegrationsObject, kaggDialog */

/**
 * @param HCaptchaIntegrationsObject.ajaxUrl
 * @param HCaptchaIntegrationsObject.action
 * @param HCaptchaIntegrationsObject.nonce
 * @param HCaptchaIntegrationsObject.activateMsg
 * @param HCaptchaIntegrationsObject.deactivateMsg
 * @param HCaptchaIntegrationsObject.activateThemeMsg
 * @param HCaptchaIntegrationsObject.deactivateThemeMsg
 * @param HCaptchaIntegrationsObject.selectThemeMsg
 * @param HCaptchaIntegrationsObject.onlyOneThemeMsg
 * @param HCaptchaIntegrationsObject.unexpectedErrorMsg
 * @param HCaptchaIntegrationsObject.OKBtnText
 * @param HCaptchaIntegrationsObject.CancelBtnText
 * @param HCaptchaIntegrationsObject.themes
 * @param HCaptchaIntegrationsObject.defaultTheme
 */

/**
 * The Integrations Admin Page script.
 *
 * @param {jQuery} $ The jQuery instance.
 */
const integrations = function( $ ) {
	const msgSelector = '#hcaptcha-message';
	let $message = $( msgSelector );
	const $wpWrap = $( '#wpwrap' );
	const $adminmenuwrap = $( '#adminmenuwrap' );
	const $search = $( '#hcaptcha-integrations-search' );

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
		$fixed.css( 'top', $wpWrap.position().top );
		$fixed.css( 'z-index', '999999' );

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

	function showUnexpectedErrorMessage() {
		showMessage( HCaptchaIntegrationsObject.unexpectedErrorMsg, 'notice-error' );
	}

	function isActiveTable( $table ) {
		return $table.is( jQuery( '.form-table' ).eq( 0 ) );
	}

	function swapThemes( activate, entity, newTheme ) {
		if ( entity !== 'theme' ) {
			return;
		}

		const $tables = $( '.form-table' );
		const $fromTable = $tables.eq( activate ? 0 : 1 );
		const dataLabel = activate ? '' : '[data-label="' + newTheme + '"]';

		const $img = $fromTable.find( '.hcaptcha-integrations-logo img[data-entity="theme"]' + dataLabel );

		if ( ! $img.length ) {
			return;
		}

		const $toTable = $tables.eq( activate ? 1 : 0 );
		const $tr = $img.closest( 'tr' );

		insertIntoTable( $toTable, $img.attr( 'data-label' ), $tr );
	}

	function insertIntoTable( $table, key, $element ) {
		let inserted = false;
		const lowerKey = key.toLowerCase();

		const disable = ! isActiveTable( $table );
		const $fieldset = $element.find( 'fieldset' );

		$fieldset.attr( 'disabled', disable );
		$fieldset.find( 'input' ).attr( 'disabled', disable );

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
		function maybeToggleActivation( confirmation ) {
			if ( ! confirmation ) {
				return;
			}

			toggleActivation();
		}

		function getSelectedTheme() {
			const select = document.querySelector( '.kagg-dialog select' );

			if ( ! select ) {
				return '';
			}

			return select.value ?? '';
		}

		function toggleActivation() {
			const activateClass = activate ? 'on' : 'off';
			const newTheme = getSelectedTheme();
			const data = {
				action: HCaptchaIntegrationsObject.action,
				nonce: HCaptchaIntegrationsObject.nonce,
				activate,
				entity,
				status,
				newTheme,
			};

			$tr.addClass( activateClass );

			// noinspection JSVoidFunctionReturnValueUsed
			$.post( {
				url: HCaptchaIntegrationsObject.ajaxUrl,
				data,
			} )
				.done( function( response ) {
					if ( response.success === undefined ) {
						showUnexpectedErrorMessage();

						return;
					}

					if ( response.data.themes !== undefined ) {
						window.HCaptchaIntegrationsObject.themes = response.data.themes;
						window.HCaptchaIntegrationsObject.defaultTheme = response.data.defaultTheme;
					}

					if ( ! response.success ) {
						showErrorMessage( response.data.message );

						return;
					}

					const $table = $( '.form-table' ).eq( activate ? 0 : 1 );
					const top = $wpWrap.position().top;

					swapThemes( activate, entity, newTheme );
					insertIntoTable( $table, alt, $tr );
					showSuccessMessage( response.data.message );

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
		}

		event.preventDefault();
		clearMessage();

		const $target = $( event.target );
		let entity = $target.data( 'entity' );
		entity = entity ? entity : '';

		if ( -1 === $.inArray( entity, [ 'core', 'theme', 'plugin' ] ) ) {
			// Wrong entity type.
			return;
		}

		if ( -1 !== $.inArray( entity, [ 'core' ] ) ) {
			// Cannot activate/deactivate WP Core.
			return;
		}

		let alt = $target.attr( 'alt' );
		alt = alt ? alt : '';
		alt = alt.replace( ' Logo', '' );

		const $tr = $target.closest( 'tr' );
		let status = $tr.attr( 'class' );
		status = status.replace( 'hcaptcha-integrations-', '' );

		const $fieldset = $tr.find( 'fieldset' );
		let title;
		let content = '';
		let activate;

		if ( $fieldset.attr( 'disabled' ) ) {
			title = entity === 'plugin'
				? HCaptchaIntegrationsObject.activateMsg
				: HCaptchaIntegrationsObject.activateThemeMsg;
			activate = true;
		} else {
			if ( entity === 'plugin' ) {
				title = HCaptchaIntegrationsObject.deactivateMsg;
			} else {
				title = HCaptchaIntegrationsObject.deactivateThemeMsg;
				content = '<p>' + HCaptchaIntegrationsObject.selectThemeMsg + '</p>';
				content += '<select>';

				for ( const slug in HCaptchaIntegrationsObject.themes ) {
					const selected = slug === HCaptchaIntegrationsObject.defaultTheme ? ' selected="selected"' : '';

					content += `<option value="${ slug }"${ selected }>${ HCaptchaIntegrationsObject.themes[ slug ] }</option>`;
				}

				content += '</select>';
			}

			activate = false;
		}

		if (
			-1 !== $.inArray( entity, [ 'theme' ] ) &&
			! activate &&
			Object.keys( HCaptchaIntegrationsObject.themes ).length === 0
		) {
			// Cannot deactivate a theme when it is the only one on the site.
			kaggDialog.confirm( {
				title: HCaptchaIntegrationsObject.onlyOneThemeMsg,
				content: '',
				type: 'info',
				buttons: {
					ok: {
						text: HCaptchaIntegrationsObject.OKBtnText,
					},
				},
			} );

			return;
		}

		title = title.replace( '%s', alt );

		if ( event.ctrlKey ) {
			toggleActivation();
			return;
		}

		kaggDialog.confirm( {
			title,
			content,
			type: activate ? 'activate' : 'deactivate',
			buttons: {
				ok: {
					text: HCaptchaIntegrationsObject.OKBtnText,
				},
				cancel: {
					text: HCaptchaIntegrationsObject.CancelBtnText,
				},
			},
			onAction: maybeToggleActivation,
		} );
	} );

	const debounce = ( func, delay ) => {
		let debounceTimer;

		return function() {
			const context = this;
			const args = arguments;
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( () => func.apply( context, args ), delay );
		};
	};

	$search.on( 'input', debounce(
		function() {
			const search = $search.val().trim().toLowerCase();
			const $logo = $( '.hcaptcha-integrations-logo img' );

			$logo.each( function( i, el ) {
				const $el = $( el );

				if ( $el.data( 'entity' ) === 'core' ) {
					return;
				}

				const $tr = $el.closest( 'tr' );

				if ( $el.data( 'label' ).toLowerCase().includes( search ) ) {
					$tr.show();
				} else {
					$tr.hide();
				}
			} );
		},
		100
	) );

	$( '#hcaptcha-options' ).keydown(
		function( e ) {
			if ( $( e.target ).is( $search ) && e.which === 13 ) {
				e.preventDefault();
			}
		}
	);
};

window.hCaptchaIntegrations = integrations;

jQuery( document ).ready( integrations );
