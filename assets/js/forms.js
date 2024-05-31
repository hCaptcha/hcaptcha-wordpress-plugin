/* global Chart, HCaptchaFormsObject */

/**
 * @param HCaptchaFormsObject.served
 * @param HCaptchaFormsObject.servedLabel
 * @param HCaptchaFormsObject.unit
 */
document.addEventListener( 'DOMContentLoaded', function() {
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
} );
