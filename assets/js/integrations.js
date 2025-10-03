/* global jQuery, hCaptchaSettingsBase, HCaptchaIntegrationsObject, kaggDialog */

/**
 * @param HCaptchaIntegrationsObject.CancelBtnText
 * @param HCaptchaIntegrationsObject.OKBtnText
 * @param HCaptchaIntegrationsObject.action
 * @param HCaptchaIntegrationsObject.activatePluginMsg
 * @param HCaptchaIntegrationsObject.activateThemeMsg
 * @param HCaptchaIntegrationsObject.ajaxUrl
 * @param HCaptchaIntegrationsObject.deactivatePluginMsg
 * @param HCaptchaIntegrationsObject.deactivateThemeMsg
 * @param HCaptchaIntegrationsObject.defaultTheme
 * @param HCaptchaIntegrationsObject.installPluginMsg
 * @param HCaptchaIntegrationsObject.installThemeMsg
 * @param HCaptchaIntegrationsObject.nonce
 * @param HCaptchaIntegrationsObject.onlyOneThemeMsg
 * @param HCaptchaIntegrationsObject.selectThemeMsg
 * @param HCaptchaIntegrationsObject.themes
 * @param HCaptchaIntegrationsObject.unexpectedErrorMsg
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
	const $showAntispamCoverage = $( '#show_antispam_coverage_1' );

	function clearMessage() {
		$message.remove();
		// Concat to avoid an inspection message.
		$( '<div id="hcaptcha-message">' + '</div>' ).insertAfter( '.hcaptcha-header-bar' );
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
		return $table.is( jQuery( '.form-table' ).eq( 1 ) );
	}

	function swapThemes( activate, entity, newTheme ) {
		if ( entity !== 'theme' ) {
			return;
		}

		const $tables = $( '.form-table' );
		const $fromTable = $tables.eq( activate ? 1 : 2 );
		const dataLabel = activate ? '' : '[data-label="' + newTheme + '"]';

		const $img = $fromTable.find( '.hcaptcha-integrations-logo img[data-entity="theme"]' + dataLabel );

		if ( ! $img.length ) {
			return;
		}

		const $toTable = $tables.eq( activate ? 2 : 1 );
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

	// Setup antispam helper
	function setupHelper( $label ) {
		let $helper = $label.next( '.helper' );

		// If a helper doesn't exist immediately after, insert it
		if ( ! $helper.length ) {
			$helper = $( document.createElement( 'span' ) ).addClass( 'helper' );
			$label.after( $helper );
		}

		// Rebuild helper icons based on helper data-* attributes.
		// The helper may have several data-antispam-* attributes (e.g., data-antispam-honeypot, data-antispam-fst).
		// We insert corresponding <img> nodes like <img class="antispam-honeypot">, <img class="antispam-fst"> inside the helper.
		( function populateHelperIcons() {
			// Remove previously added antispam icons to avoid duplicates.
			$helper.find( 'i[class^="antispam"]' ).remove();

			const attrs = $label.get( 0 )?.attributes ?? [];
			const classes = [];

			for ( let i = 0; i < attrs.length; i++ ) {
				const name = attrs[ i ].name;

				// Ignore 'data-antispam' as it is a general marker for the helper.
				if ( name.indexOf( 'data-antispam-' ) === 0 ) {
					// Convert attribute name to class name by stripping the 'data-' prefix.
					const className = name.replace( /^data-/, '' );

					if ( classes.indexOf( className ) === -1 ) {
						classes.push( className );
					}
				}
			}

			// Append images for each discovered class.
			classes.forEach( function( cls ) {
				const $icon = $( document.createElement( 'i' ) ).addClass( cls );

				$helper.prepend( $icon );
			} );
		}() );

		return $helper;
	}

	function setupHelpers() {
		const checked = $showAntispamCoverage.prop( 'checked' );

		// Find all labels that declare the antispam marker
		$( 'label[data-antispam]' ).each( function() {
			const $helper = setupHelper( $( this ) );

			if ( checked ) {
				$helper.css( 'display', 'inline-flex' );
			} else {
				$helper.hide();
			}
		} );
	}

	// Handle Show Antispam Coverage checkbox change: insert/clear helper spans after honeypot labels
	$showAntispamCoverage.on( 'change', function() {
		setupHelpers();
	} );

	$( '.form-table img' ).on( 'click', function( event ) {
		function maybeInstallEntity( confirmation ) {
			if ( ! confirmation ) {
				return;
			}

			installEntity();
		}

		function maybeToggleActivation( confirmation ) {
			if ( ! confirmation ) {
				return;
			}

			toggleActivation();
		}

		function getSelectedTheme() {
			/**
			 * @type {HTMLSelectElement}
			 */
			const select = document.querySelector( '.kagg-dialog select' );

			if ( ! select ) {
				return '';
			}

			return select.value ?? '';
		}

		function updateActivationStati( stati ) {
			const $tables = $( '.form-table' );

			for ( const [ key, status ] of Object.entries( stati ) ) {
				if ( key === '1' ) {
					continue;
				}

				const statusClass = 'hcaptcha-integrations-' + key.replace( /_/g, '-' );
				const $tr = $( `tr.${ statusClass }` );
				const $logo = $tr.find( '.hcaptcha-integrations-logo' );
				const currStatus = isActiveTable( $tr.closest( '.form-table' ) );

				if ( status ) {
					$logo.attr( 'data-installed', true );
				}

				if ( currStatus !== status ) {
					const $toTable = $tables.eq( status ? 1 : 2 );
					const alt = $logo.find( 'img' ).attr( 'alt' );

					insertIntoTable( $toTable, alt, $tr );
				}
			}
		}

		function installEntity() {
			toggleActivation( true );
		}

		function toggleActivation( install = false ) {
			let actionClass = activate ? 'on' : 'off';
			actionClass = install ? 'install' : actionClass;

			const newTheme = getSelectedTheme();
			const data = {
				action: HCaptchaIntegrationsObject.action,
				nonce: HCaptchaIntegrationsObject.nonce,
				install,
				activate,
				entity,
				status,
				newTheme,
			};

			$tr.addClass( actionClass );

			// noinspection JSVoidFunctionReturnValueUsed
			$.post( {
				url: HCaptchaIntegrationsObject.ajaxUrl,
				data,
			} )
				/**
				 * @param {Object} response.data
				 * @param {Object} response.data.defaultTheme
				 * @param {Object} response.data.message
				 * @param {Object} response.data.stati
				 * @param {Object} response.data.themes
				 * @param {Object} response.success
				 */
				.done( function( response ) {
					if ( response.success === undefined ) {
						showUnexpectedErrorMessage();

						return;
					}

					if ( response.data.themes !== undefined ) {
						HCaptchaIntegrationsObject.themes = response.data.themes;
						HCaptchaIntegrationsObject.defaultTheme = response.data.defaultTheme;
					}

					if ( ! response.success ) {
						const message = response.data?.message ?? response.data;

						showErrorMessage( message );

						return;
					}

					const $table = $( '.form-table' ).eq( activate ? 1 : 2 );

					swapThemes( activate, entity, newTheme );
					insertIntoTable( $table, alt, $tr );
					showSuccessMessage( response.data.message );
					updateActivationStati( response.data.stati );

					$( 'html, body' ).animate(
						{
							scrollTop: $tr.offset().top - hCaptchaSettingsBase.getStickyHeight(),
						},
						1000
					);
				} )
				.fail(
					/**
					 * @param {Object} response
					 */
					function( response ) {
						showErrorMessage( response.statusText );
					}
				)
				.always( function() {
					$tr.removeClass( 'install on off' );
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
		const match = $tr.attr( 'class' ).match( /hcaptcha-integrations-([a-z0-9-]+)/ );
		const status = match ? match[ 1 ] : '';

		const $fieldset = $tr.find( 'fieldset' );
		let title;
		let content = '';
		let activate;

		if ( $fieldset.attr( 'disabled' ) ) {
			title = entity === 'plugin'
				? HCaptchaIntegrationsObject.activatePluginMsg
				: HCaptchaIntegrationsObject.activateThemeMsg;
			activate = true;
		} else {
			if ( entity === 'plugin' ) {
				title = HCaptchaIntegrationsObject.deactivatePluginMsg;
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

		const $logo = $tr.find( '.hcaptcha-integrations-logo' );

		if ( $logo.attr( 'data-installed' ) === 'false' ) {
			if ( event.ctrlKey ) {
				installEntity();

				return;
			}

			title = entity === 'plugin'
				? HCaptchaIntegrationsObject.installPluginMsg
				: HCaptchaIntegrationsObject.installThemeMsg;

			title = title.replace( '%s', alt );

			kaggDialog.confirm( {
				title,
				content,
				type: 'install',
				buttons: {
					ok: {
						text: HCaptchaIntegrationsObject.OKBtnText,
					},
					cancel: {
						text: HCaptchaIntegrationsObject.CancelBtnText,
					},
				},
				onAction: maybeInstallEntity,
			} );

			return;
		}

		if ( event.ctrlKey ) {
			toggleActivation();

			return;
		}

		title = title.replace( '%s', alt );

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
			const $img = $( '.hcaptcha-integrations-logo img' );
			let $trFirst = null;

			$img.each( function( i, el ) {
				const $el = $( el );

				if ( $el.data( 'entity' ) === 'core' ) {
					return;
				}

				const $tr = $el.closest( 'tr' );

				if ( $el.data( 'label' ).toLowerCase().includes( search ) ) {
					$tr.show();
					$trFirst = $trFirst ?? $tr;
				} else {
					$tr.hide();
				}
			} );

			if ( ! $trFirst ) {
				return;
			}

			const scrollTop = $trFirst.offset().top + $trFirst.outerHeight() - $( window ).height() + 5;

			$( 'html' ).stop().animate(
				{ scrollTop },
				1000
			);
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

	setupHelpers();
};

window.hCaptchaIntegrations = integrations;

jQuery( document ).ready( integrations );
