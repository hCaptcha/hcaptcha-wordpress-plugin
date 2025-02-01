/* global jQuery, Chart, hCaptchaSettingsBase, HCaptchaListPageBaseObject, HCaptchaFormsObject */

/**
 * @param HCaptchaFormsObject.ajaxUrl
 * @param HCaptchaFormsObject.bulkAction
 * @param HCaptchaFormsObject.bulkNonce
 * @param HCaptchaFormsObject.served
 * @param HCaptchaFormsObject.servedLabel
 * @param HCaptchaFormsObject.unit
 * @param HCaptchaListPageBaseObject.noAction
 * @param HCaptchaListPageBaseObject.noItems
 * @param HCaptchaListPageBaseObject.DoingBulk
 */

/**
 * Forms page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const forms = function( $ ) {
	function initChart() {
		const ctx = document.getElementById( 'formsChart' );
		const aspectRatio = window.innerWidth > 600 ? 3 : 2;

		new Chart( ctx, {
			type: 'bar',
			data: {
				datasets: [
					{
						label: HCaptchaFormsObject.servedLabel,
						backgroundColor: 'rgba(2,101,147,0.5)',
						data: HCaptchaFormsObject.served,
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
							unit: HCaptchaFormsObject.unit,
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

		const ids = formData.getAll( 'bulk-checkbox[]' ).map(
			( id ) => {
				const row = form.querySelector( `input[name="bulk-checkbox[]"][value="${ id }"]` ).closest( 'tr' );
				const source = row.querySelector( 'td.name .hcaptcha-excerpt' ).dataset.source;
				const formId = row.querySelector( 'td.form_id' ).textContent;

				return { source, formId };
			},
		);

		if ( ! ids.length ) {
			hCaptchaSettingsBase.showErrorMessage( HCaptchaListPageBaseObject.noItems );

			return;
		}

		const data = {
			action: HCaptchaFormsObject.bulkAction,
			nonce: HCaptchaFormsObject.bulkNonce,
			bulk,
			ids: JSON.stringify( ids ),
		};

		$.post( {
			url: HCaptchaFormsObject.ajaxUrl,
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
	document.getElementById( 'doaction' ).addEventListener( 'click', handleBulkAction );
};

window.hCaptchaForms = forms;

jQuery( document ).ready( forms );
