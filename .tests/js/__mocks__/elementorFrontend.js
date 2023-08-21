/* global jest, global */

export const hooks = {
	addAction: jest.fn(),
};

const elementorFrontend = {
	hooks,
};

global.elementorFrontend = elementorFrontend;

// noinspection JSUnusedGlobalSymbols
export default elementorFrontend;
