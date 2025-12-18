/**
 * Jest Setup File
 *
 * Sets up global mocks and utilities for testing
 */

// Mock jQuery
global.jQuery = global.$ = require('jquery');

// Mock WordPress localized script data
global.rcfLoqateConfig = {
	apiKey: 'TEST-API-KEY',
	enabled: true,
	allowedCountries: 'USA,GBR,CAN',
	geolocationEnabled: false,
	geolocationRadius: 100,
	geolocationMaxItems: 5,
	allowManualEntry: true,
	validateEmail: true,
	validatePhone: false,
	billingAddressFields: {
		search: 'billing_address_1',
		populate: ['billing_address_2', 'billing_city', 'billing_state', 'billing_postcode'],
		country: 'billing_country',
		email: 'billing_email',
		phone: 'billing_phone'
	},
	shippingAddressFields: {
		search: 'shipping_address_1',
		populate: ['shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode'],
		country: 'shipping_country',
		email: 'shipping_email',
		phone: 'shipping_phone'
	},
	debug: true
};

global.rcfCheckoutValidation = {
	fieldsToValidate: [
		'billing_first_name',
		'billing_last_name',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city'
	],
	errorMessage: 'Use Roman/English characters only (A–Z, 0–9)'
};

global.rcfAffiliateWP = {
	debug: false
};

// Mock Loqate SDK
global.pca = {
	fieldMode: {
		SEARCH: 1,
		POPULATE: 2
	},
	Address: class MockLoqateAddress {
		constructor(fields, config) {
			this.fields = fields;
			this.config = config;
			this.listeners = {};
		}

		listen(event, callback) {
			if (!this.listeners[event]) {
				this.listeners[event] = [];
			}
			this.listeners[event].push(callback);
		}

		trigger(event, data) {
			if (this.listeners[event]) {
				this.listeners[event].forEach(cb => cb(data));
			}
		}

		close() {
			// Mock close
		}

		unload() {
			// Mock unload
		}
	}
};

// Mock console methods to reduce noise in tests
global.console = {
	...console,
	log: jest.fn(),
	warn: jest.fn(),
	error: jest.fn(),
	group: jest.fn(),
	groupEnd: jest.fn()
};

// Setup DOM
document.body.innerHTML = '';

// Helper to reset DOM before each test
beforeEach(() => {
	document.body.innerHTML = '';
	jest.clearAllMocks();
});
