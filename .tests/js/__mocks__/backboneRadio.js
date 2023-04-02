const submitChannel = {
	listenTo: jest.fn(),
};

const fieldsChannel = {
	listenTo: jest.fn(),
	request: jest.fn(),
};

Backbone.Radio = {
	channel: jest.fn((channelName) => {
		if (channelName === 'submit') {
			return submitChannel;
		}
		if (channelName === 'fields') {
			return fieldsChannel;
		}
	}),
};
