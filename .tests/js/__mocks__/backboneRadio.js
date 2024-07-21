// noinspection JSUnresolvedVariable

/* global nfRadio */

const submitChannel = {
	listenTo: jest.fn(),
};

const fieldsChannel = {
	listenTo: jest.fn(),
	request: jest.fn(),
};

nfRadio = {
	channel: jest.fn( ( channelName ) => {
		if ( channelName === 'submit' ) {
			return submitChannel;
		}
		if ( channelName === 'fields' ) {
			return fieldsChannel;
		}
	} ),
};
