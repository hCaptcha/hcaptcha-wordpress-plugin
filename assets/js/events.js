/* global Chart, HCaptchaEventsObject */

/**
 * @param HCaptchaEventsObject.failed
 * @param HCaptchaEventsObject.failedLabel
 * @param HCaptchaEventsObject.succeed
 * @param HCaptchaEventsObject.succeedLabel
 * @param HCaptchaEventsObject.unit
 */
document.addEventListener( 'DOMContentLoaded', function() {
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
} );
