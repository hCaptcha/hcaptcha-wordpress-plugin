export const hooks = {
	addAction: jest.fn(),
};

const elementorFrontend = {
	hooks,
};

global.elementorFrontend = elementorFrontend;

export default elementorFrontend;
