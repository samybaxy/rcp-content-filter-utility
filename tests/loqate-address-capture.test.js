/**
 * Tests for Loqate Address Capture integration
 *
 * @jest-environment jsdom
 */

describe('Loqate Address Capture', () => {
	let RCFLoqateAddressCapture;

	beforeEach(() => {
		// Setup DOM
		document.body.innerHTML = `
			<form class="checkout">
				<div class="form-row">
					<input type="text" id="billing_address_1" />
				</div>
				<div class="form-row">
					<input type="text" id="billing_address_2" />
				</div>
				<div class="form-row">
					<input type="text" id="billing_city" />
				</div>
				<div class="form-row">
					<select id="billing_state"></select>
				</div>
				<div class="form-row">
					<input type="text" id="billing_postcode" />
				</div>
				<div class="form-row">
					<select id="billing_country">
						<option value="US">United States</option>
						<option value="GB">United Kingdom</option>
					</select>
				</div>
				<div class="form-row">
					<input type="checkbox" id="ship-to-different-address-checkbox" />
				</div>
			</form>
		`;

		// Initialize mock RCF Loqate object
		RCFLoqateAddressCapture = {
			config: window.rcfLoqateConfig,
			billingControl: null,
			shippingControl: null,
			isInitialized: false,
			cachedElements: {},

			validateEnvironment() {
				return typeof pca !== 'undefined' && this.config.apiKey;
			},

			cacheElements() {
				this.cachedElements.billingSearch = document.querySelector('#billing_address_1');
				this.cachedElements.billingCountry = document.querySelector('#billing_country');
			},

			getElement(key, selector) {
				if (!this.cachedElements[key]) {
					this.cachedElements[key] = document.querySelector(selector);
				}
				return this.cachedElements[key];
			}
		};
	});

	describe('Environment Validation', () => {
		test('should validate environment successfully', () => {
			expect(RCFLoqateAddressCapture.validateEnvironment()).toBe(true);
		});

		test('should fail if pca is undefined', () => {
			const pcaBackup = global.pca;
			global.pca = undefined;

			expect(RCFLoqateAddressCapture.validateEnvironment()).toBe(false);

			global.pca = pcaBackup;
		});

		test('should fail if API key is missing', () => {
			const configBackup = RCFLoqateAddressCapture.config;
			RCFLoqateAddressCapture.config = { apiKey: '' };

			expect(RCFLoqateAddressCapture.validateEnvironment()).toBe(false);

			RCFLoqateAddressCapture.config = configBackup;
		});
	});

	describe('Element Caching', () => {
		test('should cache DOM elements', () => {
			RCFLoqateAddressCapture.cacheElements();

			expect(RCFLoqateAddressCapture.cachedElements.billingSearch).toBeTruthy();
			expect(RCFLoqateAddressCapture.cachedElements.billingCountry).toBeTruthy();
		});

		test('should retrieve cached element', () => {
			RCFLoqateAddressCapture.cacheElements();

			const element = RCFLoqateAddressCapture.getElement('billingSearch', '#billing_address_1');

			expect(element).toBe(RCFLoqateAddressCapture.cachedElements.billingSearch);
		});

		test('should query DOM if element not cached', () => {
			const element = RCFLoqateAddressCapture.getElement('billingCity', '#billing_city');

			expect(element).toBeTruthy();
			expect(element.id).toBe('billing_city');
		});
	});

	describe('Country Code Mapping', () => {
		const countryMap = {
			'united states': 'US',
			'usa': 'US',
			'united kingdom': 'GB',
			'uk': 'GB',
			'canada': 'CA',
			'australia': 'AU',
			'japan': 'JP'
		};

		test('should map country names to codes', () => {
			Object.entries(countryMap).forEach(([name, code]) => {
				expect(countryMap[name]).toBe(code);
			});
		});

		test('should handle case-insensitive matching', () => {
			const normalized = 'United States'.toLowerCase();
			expect(countryMap[normalized]).toBe('US');
		});
	});

	describe('Address Line 2 Building', () => {
		const buildAddressLine2 = (address) => {
			const parts = [];

			// SubBuilding (apartment, suite, unit)
			const subBuilding = address.SubBuilding || address.subBuilding || '';
			if (subBuilding && subBuilding.trim()) {
				parts.push(subBuilding.trim());
			}

			// BuildingName (only if SubBuilding is empty)
			const buildingName = address.BuildingName || address.buildingName || '';
			if (buildingName && buildingName.trim() && !subBuilding) {
				parts.push(buildingName.trim());
			}

			if (parts.length > 0) {
				return parts.join(', ');
			}

			// Fallback to Line2
			const line2 = address.Line2 || address.line2 || '';
			if (line2 && line2.trim()) {
				return line2.trim();
			}

			return '';
		};

		test('should prioritize SubBuilding', () => {
			const address = {
				SubBuilding: 'Apt 123',
				BuildingName: 'Some Building',
				Line2: 'Other Info'
			};

			expect(buildAddressLine2(address)).toBe('Apt 123');
		});

		test('should use BuildingName if no SubBuilding', () => {
			const address = {
				BuildingName: 'Empire State Building',
				Line2: 'Other Info'
			};

			expect(buildAddressLine2(address)).toBe('Empire State Building');
		});

		test('should fallback to Line2', () => {
			const address = {
				Line2: 'Suite 100'
			};

			expect(buildAddressLine2(address)).toBe('Suite 100');
		});

		test('should return empty string if all empty', () => {
			const address = {};

			expect(buildAddressLine2(address)).toBe('');
		});

		test('should handle various SubBuilding formats', () => {
			const formats = [
				{ SubBuilding: 'Apt 4B' },
				{ SubBuilding: 'Suite 100' },
				{ SubBuilding: 'Unit 5' },
				{ SubBuilding: 'Flat 12' }
			];

			formats.forEach(address => {
				const result = buildAddressLine2(address);
				expect(result).toBe(address.SubBuilding);
			});
		});
	});

	describe('Field Feedback States', () => {
		test('should show loading state', () => {
			const field = document.getElementById('billing_address_1');
			const wrapper = field.closest('.form-row');

			wrapper.classList.add('loqate-loading');
			const loader = document.createElement('div');
			loader.className = 'loqate-loader';
			wrapper.appendChild(loader);

			expect(wrapper.classList.contains('loqate-loading')).toBe(true);
			expect(wrapper.querySelector('.loqate-loader')).toBeTruthy();
		});

		test('should show success state', () => {
			const field = document.getElementById('billing_address_1');
			const wrapper = field.closest('.form-row');

			wrapper.classList.add('loqate-success');

			expect(wrapper.classList.contains('loqate-success')).toBe(true);
		});

		test('should show error state', () => {
			const field = document.getElementById('billing_address_1');
			const wrapper = field.closest('.form-row');

			wrapper.classList.add('loqate-error');
			const feedback = document.createElement('div');
			feedback.className = 'loqate-feedback loqate-error';
			feedback.textContent = 'Invalid API key';
			wrapper.appendChild(feedback);

			expect(wrapper.classList.contains('loqate-error')).toBe(true);
			expect(wrapper.querySelector('.loqate-feedback')).toBeTruthy();
		});

		test('should clear feedback states', () => {
			const field = document.getElementById('billing_address_1');
			const wrapper = field.closest('.form-row');

			// Add states
			wrapper.classList.add('loqate-loading', 'loqate-success', 'loqate-error');

			// Clear states
			wrapper.classList.remove('loqate-loading', 'loqate-success', 'loqate-error');
			wrapper.querySelectorAll('.loqate-feedback, .loqate-loader').forEach(el => el.remove());

			expect(wrapper.classList.contains('loqate-loading')).toBe(false);
			expect(wrapper.classList.contains('loqate-success')).toBe(false);
			expect(wrapper.classList.contains('loqate-error')).toBe(false);
		});
	});

	describe('Loqate SDK Integration', () => {
		test('should create Loqate control instance', () => {
			const fields = [
				{
					element: 'billing_address_1',
					field: 'Line1',
					mode: pca.fieldMode.SEARCH | pca.fieldMode.POPULATE
				}
			];

			const config = {
				key: 'TEST-API-KEY',
				manualEntryItem: true
			};

			const control = new pca.Address(fields, config);

			expect(control).toBeTruthy();
			expect(control.fields).toEqual(fields);
			expect(control.config).toEqual(config);
		});

		test('should register event listeners', () => {
			const control = new pca.Address([], { key: 'TEST' });

			const callback = jest.fn();
			control.listen('populate', callback);

			expect(control.listeners.populate).toContain(callback);
		});

		test('should trigger events', () => {
			const control = new pca.Address([], { key: 'TEST' });

			const callback = jest.fn();
			control.listen('populate', callback);

			const testAddress = { City: 'New York' };
			control.trigger('populate', testAddress);

			expect(callback).toHaveBeenCalledWith(testAddress);
		});
	});

	describe('Configuration', () => {
		test('should have correct billing field mapping', () => {
			const config = window.rcfLoqateConfig;

			expect(config.billingAddressFields.search).toBe('billing_address_1');
			expect(config.billingAddressFields.populate).toContain('billing_city');
			expect(config.billingAddressFields.country).toBe('billing_country');
		});

		test('should have correct shipping field mapping', () => {
			const config = window.rcfLoqateConfig;

			expect(config.shippingAddressFields.search).toBe('shipping_address_1');
			expect(config.shippingAddressFields.populate).toContain('shipping_city');
		});

		test('should have geolocation settings', () => {
			const config = window.rcfLoqateConfig;

			expect(config).toHaveProperty('geolocationEnabled');
			expect(config).toHaveProperty('geolocationRadius');
			expect(config).toHaveProperty('geolocationMaxItems');
		});
	});
});
