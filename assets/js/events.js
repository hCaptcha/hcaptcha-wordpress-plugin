/* global Chart, HCaptchaEventsObject */

/**
 * @param HCaptchaEventsObject.passed
 * @param HCaptchaEventsObject.failed
 * @param HCaptchaEventsObject.unit
 */
document.addEventListener( 'DOMContentLoaded', function() {
	const ctx = document.getElementById( 'eventsChart' );

	new Chart( ctx, {
		type: 'bar',
		data: {
			datasets: [
				{
					label: '# of passed events',
					data: HCaptchaEventsObject.passed,
					borderWidth: 1,
				},
				{
					label: '# of failed events',
					data: HCaptchaEventsObject.failed,
					borderWidth: 1,
				},
			],
		},
		options: {
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
