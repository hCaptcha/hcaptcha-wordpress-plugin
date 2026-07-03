/* global jQuery, Chart, hCaptchaSettingsBase, HCaptchaListPageBaseObject, HCaptchaEventsObject */

/**
 * @param HCaptchaEventsObject.ajaxUrl
 * @param HCaptchaEventsObject.bulkAction
 * @param HCaptchaEventsObject.bulkNonce
 * @param HCaptchaEventsObject.bulkMessage
 * @param HCaptchaEventsObject.failed
 * @param HCaptchaEventsObject.failedLabel
 * @param HCaptchaEventsObject.succeed
 * @param HCaptchaEventsObject.succeedLabel
 * @param HCaptchaEventsObject.unit
 */

/**
 * Events page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const events = function( $ ) {
	const storageKey = 'hcaptchaEventsView';

	function getStoredView() {
		try {
			return window.localStorage?.getItem( storageKey ) ?? '';
		} catch ( e ) {
			// Local storage can be unavailable in private browsing modes.
			if ( e ) {
				return '';
			}

			return '';
		}
	}

	function setStoredView( view ) {
		try {
			window.localStorage?.setItem( storageKey, view );
		} catch ( e ) {
			// Local storage can be unavailable in private browsing modes.
			if ( e ) {
				e.toString();
			}
		}
	}

	function initDashboardToggle() {
		const wrap = document.getElementById( 'hcaptcha-events-chart' );
		const button = document.getElementById( 'hcaptcha-events-toggle' );
		const chartPanel = wrap?.querySelector( '.hcaptcha-events-chart-panel' );
		const dashboard = document.getElementById( 'hcaptcha-events-dashboard' );
		const icon = button?.querySelector( '.hcaptcha-events-toggle-icon' );

		if ( ! wrap || ! button || ! chartPanel || ! dashboard ) {
			return;
		}

		function setView( view, persist = true ) {
			const showDashboard = view === 'dashboard';
			const nextIcon = showDashboard ? button.dataset.chartIcon : button.dataset.dashboardIcon;
			const nextAriaLabel = showDashboard ? button.dataset.showChartLabel : button.dataset.showDashboardLabel;

			wrap.classList.toggle( 'is-dashboard', showDashboard );
			chartPanel.hidden = showDashboard;
			dashboard.hidden = ! showDashboard;

			if ( icon && nextIcon ) {
				icon.className = [ 'dashicons', nextIcon, 'hcaptcha-events-toggle-icon' ].join( ' ' );
			}

			button.setAttribute( 'aria-label', nextAriaLabel );
			button.setAttribute( 'title', nextAriaLabel );
			button.setAttribute( 'aria-expanded', showDashboard ? 'true' : 'false' );

			if ( persist ) {
				setStoredView( showDashboard ? 'dashboard' : 'chart' );
			}
		}

		setView( getStoredView() === 'dashboard' ? 'dashboard' : 'chart', false );

		button.addEventListener( 'click', () => {
			setView( dashboard.hidden ? 'dashboard' : 'chart' );
		} );
	}

	function initChart() {
		const ctx = document.getElementById( 'eventsChart' );

		new Chart( ctx, {
			type: 'bar',
			data: {
				datasets: [
					{
						label: HCaptchaEventsObject.succeedLabel,
						data: HCaptchaEventsObject.succeed,
						borderWidth: 1,
					},
					{
						label: HCaptchaEventsObject.failedLabel,
						data: HCaptchaEventsObject.failed,
						borderWidth: 1,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: {
						type: 'time',
						time: {
							displayFormats: {
								millisecond: 'HH:mm:ss',
								second: 'HH:mm:ss',
								minute: 'HH:mm',
								hour: 'HH:mm',
								day: 'dd.MM.yyyy',
								week: 'dd.MM.yyyy',
								month: 'dd.MM.yyyy',
								quarter: 'dd.MM.yyyy',
								year: 'dd.MM.yyyy',
							},
							tooltipFormat: 'dd.MM.yyyy HH:mm',
							unit: HCaptchaEventsObject.unit,
						},
					},
					y: {
						beginAtZero: true,
						ticks: {
							precision: 0,
						},
					},
				},
			},
		} );
	}

	function handleBulkAction( event ) {
		event.preventDefault();

		const form = event.target.closest( 'form' );
		const formData = new FormData( form );

		const bulk = formData.get( 'action' );

		if ( bulk === '-1' ) {
			hCaptchaSettingsBase.showErrorMessage( HCaptchaListPageBaseObject.noAction );

			return;
		}

		const ids = formData.getAll( 'bulk-checkbox[]' );

		if ( ! ids.length ) {
			hCaptchaSettingsBase.showErrorMessage( HCaptchaListPageBaseObject.noItems );

			return;
		}

		const data = {
			action: HCaptchaEventsObject.bulkAction,
			nonce: HCaptchaEventsObject.bulkNonce,
			bulk,
			ids: JSON.stringify( ids ),
		};

		$.post( {
			url: HCaptchaEventsObject.ajaxUrl,
			data,
			beforeSend: () => hCaptchaSettingsBase.showSuccessMessage( HCaptchaListPageBaseObject.DoingBulk ),
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					hCaptchaSettingsBase.showErrorMessage( response.data );

					return;
				}

				window.location.reload();
			} )
			.fail(
				function( response ) {
					hCaptchaSettingsBase.showErrorMessage( response.statusText );
				},
			);
	}

	initChart();
	initDashboardToggle();

	hCaptchaSettingsBase.showSuccessMessage( HCaptchaEventsObject.bulkMessage );
	document.getElementById( 'do-action' )?.addEventListener( 'click', handleBulkAction );
};

window.hCaptchaEvents = events;

jQuery( document ).ready( events );
