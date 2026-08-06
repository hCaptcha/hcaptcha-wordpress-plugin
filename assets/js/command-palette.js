/**
 * Register hCaptcha commands in the WordPress Command Palette.
 */
const commandPalette = function() {
	const config = window.HCaptchaCommandPaletteObject || {};
	const commands = Array.isArray( config.commands ) ? config.commands : [];
	const commandsStore = window.wp?.commands?.store;
	const dispatch = window.wp?.data?.dispatch;

	if ( ! commands.length || ! commandsStore || ! dispatch ) {
		return;
	}

	const { registerCommand } = dispatch( commandsStore );

	if ( typeof registerCommand !== 'function' ) {
		return;
	}

	commands.forEach( ( command ) => {
		if ( ! command.name || ! command.label || ! command.url ) {
			return;
		}

		registerCommand( {
			name: command.name,
			label: command.label,
			searchLabel: command.searchLabel || command.label,
			category: command.category || 'view',
			keywords: Array.isArray( command.keywords ) ? command.keywords : [],
			callback: ( { close } = {} ) => {
				if ( typeof close === 'function' ) {
					close();
				}

				commandPalette.navigate( command.url );
			},
		} );
	} );
};

commandPalette.navigate = function( url ) {
	window.location.href = url;
};

window.hCaptchaCommandPalette = commandPalette;
document.addEventListener( 'DOMContentLoaded', commandPalette );
