// noinspection JSUnresolvedReference

global.fetch = require( 'jest-fetch-mock' );

global.ajaxurl = 'http://ajax-url';

// Polyfill TextEncoder and TextDecoder for the Jest testing environment if not already available
if ( typeof global.TextEncoder === 'undefined' ) {
	const { TextEncoder, TextDecoder } = require( 'util' );
	global.TextEncoder = TextEncoder;
	global.TextDecoder = TextDecoder;
}
