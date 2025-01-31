/* global jQuery, Chart, hCaptchaSettingsBase, HCaptchaListPageBaseObject, HCaptchaFormsObject */

/**
 * @param HCaptchaFormsObject.served
 * @param HCaptchaFormsObject.servedLabel
 * @param HCaptchaFormsObject.unit
 * @param HCaptchaListPageBaseObject.ajaxUrl
 * @param HCaptchaListPageBaseObject.bulkAction
 * @param HCaptchaListPageBaseObject.bulkNonce
 * @param HCaptchaListPageBaseObject.noAction
 * @param HCaptchaListPageBaseObject.noItems
 * @param HCaptchaListPageBaseObject.DoingBulk
 */

/**
 * General settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const forms = function( $ ) {
	const headerBarSelector = '.hcaptcha-header-bar';
	const msgSelector = '#hcaptcha-message';
	let $message = $( msgSelector );

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

	function clearMessage() {
		$message.remove();
		// Concat below to avoid an inspection message.
		$( '<div id="hcaptcha-message">' + '</div>' ).insertAfter( headerBarSelector );
		$message = $( msgSelector );
	}

	function showMessage( message = '', msgClass = '' ) {
		message = message === undefined ? '' : String( message );

		if ( ! message ) {
			return;
		}

		clearMessage();
		$message.addClass( msgClass + ' notice is-dismissible' );

		const messageLines = message.split( '\n' ).map( function( line ) {
			return `<p>${ line }</p>`;
		} );

		$message.html( messageLines.join( '' ) );

		$( document ).trigger( 'wp-updates-notice-added' );

		$( 'html, body' ).animate(
			{
				scrollTop: $message.offset().top - hCaptchaSettingsBase.getStickyHeight(),
			},
			1000,
		);
	}

	function showSuccessMessage( message = '' ) {
		showMessage( message, 'notice-success' );
	}

	function showErrorMessage( message = '' ) {
		showMessage( message, 'notice-error' );
	}

	function handleFormSubmit( event ) {
		event.preventDefault();

		const form = event.target.closest( 'form' );
		const formData = new FormData( form );

		const bulk = formData.get( 'action' );

		if ( bulk === '-1' ) {
			showErrorMessage( HCaptchaListPageBaseObject.noAction );

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
			showErrorMessage( HCaptchaListPageBaseObject.noItems );

			return;
		}

		const data = {
			action: HCaptchaListPageBaseObject.bulkAction,
			nonce: HCaptchaListPageBaseObject.bulkNonce,
			bulk,
			ids: JSON.stringify( ids ),
		};

		$.post( {
			url: HCaptchaListPageBaseObject.ajaxUrl,
			data,
			beforeSend: () => showSuccessMessage( HCaptchaListPageBaseObject.DoingBulk ),
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					showErrorMessage( response.data );

					return;
				}

				window.location.reload();
			} )
			.fail(
				function( response ) {
					showErrorMessage( response.statusText );
				},
			);
	}

	initChart();
	document.getElementById( 'doaction' ).addEventListener( 'click', handleFormSubmit );
};

window.hCaptchaGeneral = forms;

jQuery( document ).ready( forms );
