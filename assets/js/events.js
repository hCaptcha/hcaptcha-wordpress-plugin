/* global Chart, HCaptchaEventsObject */

/**
 * @param HCaptchaEventsObject.succeed
 * @param HCaptchaEventsObject.failed
 * @param HCaptchaEventsObject.succeedLabel
 * @param HCaptchaEventsObject.failedLabel
 */
document.addEventListener( 'DOMContentLoaded', function() {
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
			maintainAspectRatio: true,
			aspectRatio: 3,
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
