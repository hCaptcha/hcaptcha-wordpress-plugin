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
	function initChart() {
		const ctx = document.getElementById( 'eventsChart' );
		const aspectRatio = window.innerWidth > 600 ? 3 : 2;

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
				maintainAspectRatio: true,
				aspectRatio,
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
	hCaptchaSettingsBase.showSuccessMessage( HCaptchaEventsObject.bulkMessage );
	document.getElementById( 'doaction' ).addEventListener( 'click', handleBulkAction );
};

window.hCaptchaForms = events;

jQuery( document ).ready( events );
