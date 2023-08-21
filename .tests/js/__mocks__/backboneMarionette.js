// Mock Backbone
// noinspection JSUnresolvedReference

const Backbone = {
	Radio: {
		channel() {
			return {
				request: jest.fn(),
				trigger: jest.fn(),
			};
		},
	},
};

// Custom base class for Marionette.Object
class MarionetteBase {
	constructor() {
		this.listenTo = jest.fn();
	}
}

// Mock Marionette
const Marionette = {
	Object: class extends MarionetteBase {
		static extend( protoProps ) {
			const parent = this;
			const child = function( ...args ) {
				return Reflect.construct( parent, args, this.constructor );
			};
			child.prototype = Object.create( parent.prototype );
			child.prototype.constructor = child;

			for ( const prop in protoProps ) {
				child.prototype[ prop ] = protoProps[ prop ];
			}

			return child;
		}
	},
};

global.Backbone = Backbone;
global.Marionette = Marionette;
