// noinspection JSUnresolvedFunction,JSUnresolvedVariable

const defaultCommandPaletteObject = {
	commands: [
		{
			name: 'hcaptcha/settings-antispampage-disposable-email-1',
			label: 'hCaptcha for WP: Block Disposable Emails',
			searchLabel: 'hCaptcha for WP: Block Disposable Emails',
			url: 'http://domain.tld/wp-admin/options-general.php?page=hcaptcha&tab=antispampage#disposable_email_1',
			category: 'view',
			keywords: [ 'hCaptcha', 'hCaptcha for WP', 'Anti-Spam', 'Disposable Emails', 'Temporary Emails' ],
		},
	],
};

function bootCommandPalette( objectOverrides = {}, wpOverrides = {} ) {
	jest.resetModules();

	const registerCommand = jest.fn();
	const dispatch = jest.fn().mockReturnValue( { registerCommand } );

	window.HCaptchaCommandPaletteObject = {
		...defaultCommandPaletteObject,
		...objectOverrides,
	};

	window.wp = {
		commands: {
			store: 'commands-store',
		},
		data: {
			dispatch,
		},
		...wpOverrides,
	};

	require( '../../../assets/js/command-palette.js' );
	window.hCaptchaCommandPalette();

	return { dispatch, registerCommand };
}

describe( 'command-palette.js', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	afterEach( () => {
		delete window.HCaptchaCommandPaletteObject;
		delete window.wp;
	} );

	test( 'registers commands from localized config', () => {
		const { dispatch, registerCommand } = bootCommandPalette();

		expect( dispatch ).toHaveBeenCalledWith( 'commands-store' );
		expect( registerCommand ).toHaveBeenCalledWith(
			expect.objectContaining( {
				name: 'hcaptcha/settings-antispampage-disposable-email-1',
				label: 'hCaptcha for WP: Block Disposable Emails',
				searchLabel: 'hCaptcha for WP: Block Disposable Emails',
				category: 'view',
				keywords: [ 'hCaptcha', 'hCaptcha for WP', 'Anti-Spam', 'Disposable Emails', 'Temporary Emails' ],
			} ),
		);
	} );

	test( 'command callback closes palette and navigates to setting anchor', () => {
		const { registerCommand } = bootCommandPalette();
		const close = jest.fn();
		const navigate = jest.fn();
		const command = registerCommand.mock.calls[ 0 ][ 0 ];

		window.hCaptchaCommandPalette.navigate = navigate;

		command.callback( { close } );

		expect( close ).toHaveBeenCalledTimes( 1 );
		expect( navigate ).toHaveBeenCalledWith(
			'http://domain.tld/wp-admin/options-general.php?page=hcaptcha&tab=antispampage#disposable_email_1',
		);
	} );

	test( 'returns early when commands package is unavailable', () => {
		const { registerCommand } = bootCommandPalette( {}, { commands: undefined } );

		expect( registerCommand ).not.toHaveBeenCalled();
	} );

	test( 'skips invalid commands', () => {
		const { registerCommand } = bootCommandPalette( {
			commands: [
				{
					name: 'hcaptcha/invalid',
					label: 'Invalid',
				},
			],
		} );

		expect( registerCommand ).not.toHaveBeenCalled();
	} );
} );
