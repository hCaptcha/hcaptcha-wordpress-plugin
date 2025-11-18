/* global jQuery, HCaptchaOnboardingObject */

/**
 * @param HCaptchaOnboardingObject.ajaxUrl
 * @param HCaptchaOnboardingObject.currentStep
 * @param HCaptchaOnboardingObject.generalUrl
 * @param HCaptchaOnboardingObject.i18n
 * @param HCaptchaOnboardingObject.i18n.close
 * @param HCaptchaOnboardingObject.i18n.letsGo
 * @param HCaptchaOnboardingObject.i18n.ratingBody
 * @param HCaptchaOnboardingObject.i18n.ratingCta
 * @param HCaptchaOnboardingObject.i18n.ratingTitle
 * @param HCaptchaOnboardingObject.i18n.ratingUrl
 * @param HCaptchaOnboardingObject.i18n.steps
 * @param HCaptchaOnboardingObject.i18n.videoBody
 * @param HCaptchaOnboardingObject.i18n.videoCta
 * @param HCaptchaOnboardingObject.i18n.videoTitle
 * @param HCaptchaOnboardingObject.i18n.welcomeBody
 * @param HCaptchaOnboardingObject.i18n.welcomeTitle
 * @param HCaptchaOnboardingObject.iconAnimatedUrl
 * @param HCaptchaOnboardingObject.integrationsUrl
 * @param HCaptchaOnboardingObject.page
 * @param HCaptchaOnboardingObject.selectors
 * @param HCaptchaOnboardingObject.selectors.general.antispam
 * @param HCaptchaOnboardingObject.selectors.general.check_config
 * @param HCaptchaOnboardingObject.selectors.general.force
 * @param HCaptchaOnboardingObject.selectors.general.mode
 * @param HCaptchaOnboardingObject.selectors.general.save
 * @param HCaptchaOnboardingObject.selectors.general.site_key
 * @param HCaptchaOnboardingObject.selectors.integrations.integrations_list
 * @param HCaptchaOnboardingObject.selectors.integrations.save
 * @param HCaptchaOnboardingObject.stepParam
 * @param HCaptchaOnboardingObject.steps
 * @param HCaptchaOnboardingObject.updateAction
 * @param HCaptchaOnboardingObject.updateNonce
 * @param HCaptchaOnboardingObject.videoUrl
 */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const onboarding = function( $ ) {
	'use strict';

	const cfg = HCaptchaOnboardingObject;

	// Steps by page
	const stepsByPage = {
		general: [ 1, 2, 3, 4, 5, 6 ],
		integrations: [ 7, 8 ],
	};

	// Targets map per step
	const targets = {
		1: { page: 'general', selector: cfg.selectors.general.site_key },
		2: { page: 'general', selector: cfg.selectors.general.mode },
		3: { page: 'general', selector: cfg.selectors.general.check_config },
		4: { page: 'general', selector: cfg.selectors.general.force },
		5: { page: 'general', selector: cfg.selectors.general.antispam },
		6: { page: 'general', selector: cfg.selectors.general.save },
		7: { page: 'integrations', selector: cfg.selectors.integrations.integrations_list },
		8: { page: 'integrations', selector: cfg.selectors.integrations.save },
	};
	let $tooltip;

	// Utilities
	function postUpdate( value ) {
		return $.post( cfg.ajaxUrl, {
			action: cfg.updateAction,
			nonce: cfg.updateNonce,
			value,
		} );
	}

	function stepNumber( stepStr ) {
		// 'step 3' -> 3
		const m = /step\s(\d+)/.exec( stepStr || '' );

		return m ? parseInt( m[ 1 ], 10 ) : 1;
	}

	function nextStep( current ) {
		const n = stepNumber( current );

		return 'step ' + ( n + 1 );
	}

	function inArray( needle, arr ) {
		return arr.indexOf( needle ) !== -1;
	}

	// Build floating panel (bottom-right)
	function buildPanel( current ) {
		/* language=HTML */
		const $panel = $( '<div class="hcap-onb-panel" aria-live="polite"></div>' );

		/* language=HTML */
		const $header = $( '<div class="hcap-onb-header"></div>' );
		const $close = $( '<button type="button" class="hcap-onb-close" aria-label="' + cfg.i18n.close + '"></button>' );

		$close.on( 'click', function() {
			postUpdate( 'completed' ).always( function() {
				$panel.remove();
				removeTooltip();
			} );
		} );

		/* language=HTML */
		const $titleWrap = $( '<div class="hcap-onb-title"></div>' );

		if ( cfg.iconAnimatedUrl ) {
			/* language=HTML */
			const $img = $( `<img class="hcap-onb-icon" alt="" aria-hidden="true" src="${ cfg.iconAnimatedUrl }" />` );

			$titleWrap.append( $img );
		}

		/* language=HTML */
		$titleWrap.append( $( '<span class="hcap-onb-title-text"></span>' ).text( cfg.i18n.steps ) );
		$header.append( $titleWrap ).append( $close );

		/* language=HTML */
		const $list = $( '<ol class="hcap-onb-list"></ol>' );

		for ( let i = 1; i <= 8; i++ ) {
			/* language=HTML */
			const $li = $( '<li />' )
				.text( cfg.steps[ i ] )
				.attr( 'data-step', i )
				.attr( 'role', 'button' )
				.attr( 'tabindex', '0' )
				.addClass( 'hcap-onb-step' );

			if ( 'step ' + i === current ) {
				$li.addClass( 'current' );
			}

			// Gray out steps not on this page for clarity
			if ( ! inArray( i, stepsByPage[ cfg.page ] ) ) {
				$li.addClass( 'other-page' );
			}

			$list.append( $li );
		}

		$panel.append( $header ).append( $list );
		$( 'body' ).append( $panel );

		return $panel;
	}

	// Welcome popup (step 1 on General)
	function buildWelcomeModal() {
		/* language=HTML */
		const $overlay = $( '<div class="hcap-onb-modal-overlay" />' );

		/* language=HTML */
		const $modal = $( '<div class="hcap-onb-modal" role="dialog" aria-modal="true" />' );

		/* language=HTML */
		const $head = $( '<div class="hcap-onb-modal-head"></div>' );

		if ( cfg.iconAnimatedUrl ) {
			/* language=HTML */
			$head.append( $( `<img class="hcap-onb-icon" alt="" aria-hidden="true" src="${ cfg.iconAnimatedUrl }" />` ) );
		}

		/* language=HTML */
		$head.append( $( '<h2 class="hcap-onb-modal-title"></h2>' ).text( cfg.i18n.welcomeTitle ) );

		/* language=HTML */
		const $body = $( '<div class="hcap-onb-modal-body"></div>' )
			.append( $( '<p></p>' ).text( cfg.i18n.welcomeBody ) );

		/* language=HTML */
		const $actions = $( '<div class="hcap-onb-modal-actions"></div>' );

		/* language=HTML */
		const $go = $( '<button type="button" class="button button-primary hcap-onb-go"></button>' )
			.text( cfg.i18n.letsGo );
		$actions.append( $go );

		$modal.append( $head, $body, $actions );
		$( 'body' ).append( $overlay, $modal );

		function dismiss() {
			$overlay.remove();
			$modal.remove();
		}

		$overlay.on( 'click', function() {
			// Click outside closes and proceeds
			dismiss();
			proceedFromWelcome();
		} );

		$go.on( 'click', function() {
			dismiss();
			proceedFromWelcome();
		} );

		// Focus primary action
		setTimeout( function() {
			try {
				$go.trigger( 'focus' );
			} catch ( e ) {
			}
		}, 0 );
	}

	// Build video popup (uses the same modal styling as Welcome)
	function buildVideoModal() {
		/* language=HTML */
		const $overlay = $( '<div class="hcap-onb-modal-overlay" />' );

		/* language=HTML */
		const $modal = $( '<div class="hcap-onb-modal video" role="dialog" aria-modal="true" />' );

		/* language=HTML */
		const $head = $( '<div class="hcap-onb-modal-head"></div>' );

		/* language=HTML */
		$head.append( $( '<h2 class="hcap-onb-modal-title"></h2>' ).text( cfg.i18n.videoTitle ) );

		// Convert the provided URL to embed URL (supports YouTube and youtube.com/watch?v=)
		function toEmbedUrl( url ) {
			try {
				const u = String( url || '' );
				let id = '';

				if ( u.indexOf( 'youtu.be/' ) !== -1 ) {
					id = u.split( 'youtu.be/' )[ 1 ].split( /[?&#]/ )[ 0 ];
				} else if ( u.indexOf( 'watch?v=' ) !== -1 ) {
					id = u.split( 'watch?v=' )[ 1 ].split( /[?&#]/ )[ 0 ];
				}

				if ( ! id ) {
					return u;
				}

				return 'https://www.youtube.com/embed/' + encodeURIComponent( id ) + '?autoplay=1&rel=0';
			} catch ( e ) {
				return String( url || '' );
			}
		}

		const src = toEmbedUrl( cfg.videoUrl );

		/* language=HTML */
		const $body = $( '<div class="hcap-onb-modal-body"></div>' );

		/* language=HTML */
		const $wrap = $( '<div class="hcap-onb-video-wrap"></div>' );

		/* language=HTML */
		const $iframe = $( `<iframe src="${ src }" title="YouTube video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>` );

		$wrap.append( $iframe );
		$body.append( $wrap );

		/* language=HTML */
		const $actions = $( '<div class="hcap-onb-modal-actions"></div>' );

		/* language=HTML */
		const $close = $( '<button type="button" class="button button-primary hcap-onb-video-close"></button>' ).text( cfg.i18n.close );

		$actions.append( $close );
		$modal.append( $head, $body, $actions );
		$( 'body' ).append( $overlay, $modal );

		function dismiss() {
			// Remove iframe to stop playback
			$overlay.remove();
			$modal.remove();
		}

		$overlay.on( 'click', dismiss );
		$close.on( 'click', dismiss );

		setTimeout( function() {
			try {
				$close.trigger( 'focus' );
			} catch ( e ) {
				// no-op
			}
		}, 0 );
	}

	// Build congrats/rating popup shown after completion (step 8)
	function buildCongratsModal( onDismiss ) {
		/* language=HTML */
		const $overlay = $( '<div class="hcap-onb-modal-overlay" />' );

		/* language=HTML */
		const $modal = $( '<div class="hcap-onb-modal" role="dialog" aria-modal="true" />' );

		/* language=HTML */
		const headEl = document.createElement( 'div' );

		headEl.className = 'hcap-onb-modal-head';

		const h2El = document.createElement( 'h2' );

		h2El.className = 'hcap-onb-modal-title';
		h2El.appendChild( document.createTextNode( cfg.i18n.ratingTitle ) );
		headEl.appendChild( h2El );

		const $head = $( headEl );

		/* language=HTML */
		const $body = $( '<div class="hcap-onb-modal-body"></div>' )
			.append( $( '<p></p>' ).text( cfg.i18n.ratingBody ) );

		/* language=HTML */
		const $actions = $( '<div class="hcap-onb-modal-actions"></div>' );

		/* language=HTML */
		const $rate = $( '<a class="button button-primary" target="_blank" rel="noopener noreferrer"></a>' )
			.text( cfg.i18n.ratingCta )
			.attr( 'href', cfg.ratingUrl );

		$actions.append( $rate );
		$modal.append( $head, $body, $actions );
		$( 'body' ).append( $overlay, $modal );

		function dismiss() {
			$overlay.remove();
			$modal.remove();
			if ( typeof onDismiss === 'function' ) {
				onDismiss();
			}
		}

		$overlay.on( 'click', dismiss );
		$rate.on( 'click', function() {
			// Let the link open in a new tab, also close modal immediately
			setTimeout( dismiss, 0 );
		} );

		setTimeout( function() {
			try {
				$rate.trigger( 'focus' );
			} catch ( e ) {
				// no-op
			}
		}, 0 );
	}

	function proceedFromWelcome() {
		// After welcome, render the panel and show step 1 tooltip
		const current = cfg.currentStep || 'step 1';

		buildPanel( current );
		showStep( 1 );
	}

	function removeTooltip() {
		if ( $tooltip ) {
			$tooltip.remove();
			$tooltip = null;
		}

		$( '.hcap-onb-highlight' ).removeClass( 'hcap-onb-highlight' );
	}

	// Show a tooltip near a target element
	function showStep( step ) {
		removeTooltip();

		const t = targets[ step ];

		if ( ! t || t.page !== cfg.page ) {
			// This step is for another page – show panel only.
			return;
		}

		const $target = $( t.selector ).first();

		if ( $target.length === 0 ) {
			return; // No target found on this page
		}

		// If the target is inside a settings section table, make sure the section is expanded.
		// Find the nearest table and h3 before it.
		// If h3 has a "closed" class - click on it to open the section before positioning the tooltip.
		try {
			const $table = $target.closest( 'table' );

			if ( $table.length ) {
				const $heading = $table.prevAll( 'h3' ).first();

				if ( $heading.length && $heading.hasClass( 'closed' ) ) {
					$heading.trigger( 'click' );
				}
			}
		} catch ( e ) {
			// no-op
		}

		// Scroll to an element: always center the target smoothly and position the tooltip a bit later.
		try {
			const el = $target.get( 0 );
			if ( el && typeof el.scrollIntoView === 'function' ) {
				el.scrollIntoView( { behavior: 'smooth', block: 'center', inline: 'nearest' } );
			}
		} catch ( e ) {
			// no-op
		}

		/* language=HTML */
		const $done = $( '<button type="button" class="button button-primary hcap-onb-done"></button>' ).text( cfg.i18n.done );

		$done.on( 'click', function() {
			const next = nextStep( 'step ' + step );

			if ( step === 6 && cfg.page === 'general' ) {
				// Last General step → go Integrations
				cfg.currentStep = next; // 'step 7'
				postUpdate( next ).always( function() {
					window.location.href = cfg.integrationsUrl;
				} );

				return;
			}

			if ( step >= 8 ) {
				// Completed via the Done button — show congrats popup
				postUpdate( 'completed' ).always( function() {
					buildCongratsModal( function() {
						removeTooltip();
						$( '.hcap-onb-panel' ).remove();
					} );
				} );

				return;
			}

			postUpdate( next ).always( function() {
				cfg.currentStep = next;

				// Update panel highlighting: mark a new current
				$( '.hcap-onb-panel .hcap-onb-list li' ).removeClass( 'current' ).eq( step )
					.addClass( 'current' );
				removeTooltip();
				showStep( step + 1 );
			} );
		} );

		/* language=HTML */
		$tooltip = $( '<div class="hcap-onb-tip" role="dialog"></div>' );

		const tipText = cfg.steps[ step ];

		/* language=HTML */
		$tooltip.append( $( '<div class="hcap-onb-tip-text"></div>' ).text( tipText ) );

		// For step 1 add a second line with a video link
		if ( step === 1 ) {
			/* language=HTML */
			const $sub = $( '<div></div>' ).addClass( 'hcap-onb-tip-text' );

			/* language=HTML */
			const $a = $( '<a></a>' ).attr( 'href', '#' ).addClass( 'hcap-onb-video-link' ).text( cfg.i18n.videoCta );

			$sub.append( $a );
			$tooltip.append( $sub );

			$a.on( 'click', function( e ) {
				e.preventDefault();
				buildVideoModal();
			} );

			$a.on( 'keydown', function( e ) {
				if ( e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' ) {
					e.preventDefault();
					buildVideoModal();
				}
			} );
		}

		$tooltip.append( $done );

		$( 'body' ).append( $tooltip );

		// Position near target with viewport overflow handling (left/right + vertical clamping)
		setTimeout( function() {
			const off = $target.offset();
			const tW = $tooltip.outerWidth();
			const tH = $tooltip.outerHeight();
			const $win = $( window );
			const winW = $win.width();
			const margin = 10;
			const targetW = $target.outerWidth();
			const targetH = $target.outerHeight();

			// Always place the tooltip to the right of the element
			let left = off.left + targetW + margin;
			let top = off.top - ( tH / 2 ) + ( targetH / 2 );
			const sideClass = 'side-right';

			// If the tooltip goes beyond the right edge, shift it so that its right edge is 10 px from the screen border
			const maxLeft = winW - tW - margin;

			if ( left > maxLeft ) {
				left = maxLeft;
			}

			if ( top < 10 ) {
				top = 10;
			}

			$tooltip.addClass( sideClass ).css( {
				top: Math.round( top ) + 'px',
				left: Math.round( left ) + 'px',
			} );

			// Gentle highlight of target element
			$target.addClass( 'hcap-onb-highlight' );
		}, 180 );
	}

	// Re-position tooltip on window resize so it stays near the target element.
	$( window ).on( 'resize.hcapOnb', function() {
		if ( ! $tooltip ) {
			return;
		}

		const n = stepNumber( cfg.currentStep || 'step 1' );
		const t = targets[ n ];

		// Only reposition if current step belongs to this page and has a target here.
		if ( ! t || t.page !== cfg.page ) {
			return;
		}

		showStep( n );
	} );

	// Initialization (executed on DOM ready via jQuery(document).ready(onboarding))
	const current = cfg.currentStep || 'step 1';

	// Navigation helper must be available regardless of early returns below
	function goToStep( n ) {
		const isGeneral = inArray( n, stepsByPage.general );
		const base = isGeneral ? cfg.generalUrl : cfg.integrationsUrl;
		const sep = base.indexOf( '?' ) === -1 ? '?' : '&';
		const param = cfg.stepParam;

		window.location.href = base + sep + encodeURIComponent( param ) + '=' + encodeURIComponent( n );
	}

	// Bind delegated handlers before any early returns, so clicks work after the Welcome popup
	$( document ).off( '.hcapOnbNav' );

	$( document ).on( 'click.hcapOnbNav', '.hcap-onb-list li.hcap-onb-step', function( e ) {
		e.preventDefault();

		const n = parseInt( $( this ).attr( 'data-step' ), 10 );

		if ( n ) {
			goToStep( n );
		}
	} );

	$( document ).on( 'keydown.hcapOnbNav', '.hcap-onb-list li.hcap-onb-step', function( e ) {
		if ( e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' ) {
			e.preventDefault();

			const n = parseInt( $( this ).attr( 'data-step' ), 10 );

			if ( n ) {
				goToStep( n );
			}
		}
	} );

	// Focus management: Esc closes tooltip (not panel)
	$( document ).on( 'keydown.hcapOnb', function( e ) {
		if ( e.key === 'Escape' ) {
			removeTooltip();
		}
	} );

	// If we just saved on the last General step, redirect to Integrations
	try {
		if ( cfg.page === 'general' && stepNumber( cfg.currentStep || '' ) === 7 ) {
			if ( window.sessionStorage && sessionStorage.getItem( 'hcapOnbGoIntegrations' ) === '1' ) {
				sessionStorage.removeItem( 'hcapOnbGoIntegrations' );
				window.location.href = cfg.integrationsUrl;
			}
		}
	} catch ( e ) {
	}

	// Determine current numeric step
	const num = stepNumber( current );

	// If we are on General and at step 1, show the Welcome modal first
	if ( cfg.page === 'general' && num === 1 ) {
		buildWelcomeModal();

		return;
	}

	// Do not render the main panel if the current step belongs to another page
	if ( stepsByPage[ cfg.page ] && ! inArray( num, stepsByPage[ cfg.page ] ) ) {
		return;
	}

	// Otherwise, build panel and show the tooltip
	buildPanel( current );
	showStep( num );

	// Also advance on real Save for steps 6 (General) and 8 (Integrations)
	// For step 8 we first show the congrats popup, then proceed with submit.
	let hcapOnbSubmitting = false;

	$( document ).on( 'submit', '#hcaptcha-options', function( e ) {
		const n = stepNumber( cfg.currentStep || 'step 1' );

		if ( n === 6 && cfg.page === 'general' ) {
			const next = 'step 7';

			cfg.currentStep = next;
			postUpdate( next );

			try {
				if ( window.sessionStorage ) {
					sessionStorage.setItem( 'hcapOnbGoIntegrations', '1' );
				}
			} catch ( err2 ) {
				// no-op
			}
		} else if ( n === 8 && cfg.page === 'integrations' ) {
			if ( hcapOnbSubmitting ) {
				return; // allow natural submit a second time
			}

			// Prevent immediate submit; show congrats, then proceed
			e.preventDefault();
			cfg.currentStep = 'completed';

			postUpdate( 'completed' ).always( function() {
				buildCongratsModal( function() {
					// After closing the modal, submit the form at once
					hcapOnbSubmitting = true;

					try {
						$( '#hcaptcha-options' ).get( 0 ).submit();
					} catch ( err ) {
						// fallback: trigger a click on the `submit` button
						$( '#hcaptcha-options [type="submit"]' ).first().trigger( 'click' );
					}
				} );
			} );
		}
		// allow normal form submit for other cases
	} );
};

window.hCaptchaOnboarding = onboarding;

jQuery( document ).ready( onboarding );
