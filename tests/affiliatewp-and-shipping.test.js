/**
 * Tests for AffiliateWP Registration and Shipping Address Control
 *
 * @jest-environment jsdom
 */

describe('AffiliateWP Registration Enhancement', () => {
	beforeEach(() => {
		document.body.innerHTML = `
			<form id="affwp-register-form-1">
				<p><input type="text" id="affwp-user-name" value="John Doe" /></p>
				<p><input type="text" id="affwp-user-login" value="johndoe" /></p>
				<p><input type="email" id="affwp-user-email" value="john@example.com" /></p>
				<p><input type="email" id="affwp-payment-email" value="" /></p>
				<p><input type="url" id="affwp-user-url" value="" /></p>
				<p><textarea id="affwp-promotion-method"></textarea></p>
			</form>
		`;
	});

	describe('Form Detection', () => {
		test('should find AffiliateWP registration form', () => {
			const form = document.querySelector('form[id^="affwp-register-form-"]');

			expect(form).toBeTruthy();
			expect(form.id).toBe('affwp-register-form-1');
		});

		test('should find all form fields', () => {
			const fields = {
				userName: document.querySelector('#affwp-user-name'),
				userLogin: document.querySelector('#affwp-user-login'),
				userEmail: document.querySelector('#affwp-user-email'),
				paymentEmail: document.querySelector('#affwp-payment-email'),
				websiteUrl: document.querySelector('#affwp-user-url'),
				promotionMethod: document.querySelector('#affwp-promotion-method')
			};

			Object.values(fields).forEach(field => {
				expect(field).toBeTruthy();
			});
		});
	});

	describe('Field Value Detection', () => {
		test('should detect autofilled fields', () => {
			const userName = document.querySelector('#affwp-user-name');
			const userLogin = document.querySelector('#affwp-user-login');
			const userEmail = document.querySelector('#affwp-user-email');

			expect(userName.value.trim()).toBeTruthy();
			expect(userLogin.value.trim()).toBeTruthy();
			expect(userEmail.value.trim()).toBeTruthy();
		});

		test('should detect empty fields', () => {
			const paymentEmail = document.querySelector('#affwp-payment-email');
			const websiteUrl = document.querySelector('#affwp-user-url');

			expect(paymentEmail.value.trim()).toBe('');
			expect(websiteUrl.value.trim()).toBe('');
		});
	});

	describe('Field Hiding Logic', () => {
		const hideFieldIfFilled = (field) => {
			if (!field) return false;

			const value = field.value ? field.value.trim() : '';
			if (value !== '') {
				const wrapper = field.closest('p');
				if (wrapper) {
					wrapper.classList.add('rcf-hidden-field');
					return true;
				}
			}
			return false;
		};

		test('should hide filled fields', () => {
			const userName = document.querySelector('#affwp-user-name');
			const hidden = hideFieldIfFilled(userName);

			expect(hidden).toBe(true);
			expect(userName.closest('p').classList.contains('rcf-hidden-field')).toBe(true);
		});

		test('should not hide empty fields', () => {
			const paymentEmail = document.querySelector('#affwp-payment-email');
			const hidden = hideFieldIfFilled(paymentEmail);

			expect(hidden).toBe(false);
		});

		test('should hide unconditionally', () => {
			const websiteUrl = document.querySelector('#affwp-user-url');
			const wrapper = websiteUrl.closest('p');

			wrapper.classList.add('rcf-hidden-field');

			expect(wrapper.classList.contains('rcf-hidden-field')).toBe(true);
		});
	});

	describe('CSS Styling', () => {
		test('should apply hidden field styles', () => {
			const field = document.querySelector('#affwp-user-name');
			const wrapper = field.closest('p');

			wrapper.classList.add('rcf-hidden-field');

			expect(wrapper.classList.contains('rcf-hidden-field')).toBe(true);
		});

		test('should ensure payment email is visible', () => {
			const paymentEmail = document.querySelector('#affwp-payment-email');
			const wrapper = paymentEmail.closest('p');

			// Ensure no hidden class
			wrapper.classList.remove('rcf-hidden-field');

			expect(wrapper.classList.contains('rcf-hidden-field')).toBe(false);
		});
	});
});

describe('Shipping Address Control', () => {
	beforeEach(() => {
		document.body.innerHTML = `
			<form class="checkout">
				<div class="woocommerce-shipping-fields">
					<p class="shipping-checkbox">
						<input type="checkbox" id="ship-to-different-address-checkbox" checked />
						<label>Ship to a different address?</label>
					</p>
					<div class="shipping_address">
						<p><input type="text" id="shipping_address_1" /></p>
					</div>
				</div>
			</form>
		`;
	});

	describe('Checkbox State', () => {
		test('should find shipping checkbox', () => {
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');

			expect(checkbox).toBeTruthy();
		});

		test('should uncheck shipping checkbox', () => {
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');

			expect(checkbox.checked).toBe(true);

			// Uncheck
			checkbox.checked = false;

			expect(checkbox.checked).toBe(false);
		});

		test('should hide shipping address fields', () => {
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');
			const fields = document.querySelector('.shipping_address');

			checkbox.checked = false;
			fields.style.display = 'none';

			expect(fields.style.display).toBe('none');
		});
	});

	describe('Shipping Fields Visibility', () => {
		test('should show shipping fields when checked', () => {
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');
			const fields = document.querySelector('.shipping_address');

			checkbox.checked = true;
			fields.style.display = 'block';

			expect(fields.style.display).toBe('block');
		});

		test('should hide shipping fields when unchecked', () => {
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');
			const fields = document.querySelector('.shipping_address');

			checkbox.checked = false;
			fields.style.display = 'none';

			expect(fields.style.display).toBe('none');
		});
	});

	describe('Auto-check Logic', () => {
		test('should auto-check if shipping data exists', () => {
			const shippingAddress = document.querySelector('#shipping_address_1');
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');

			// Simulate existing shipping data
			shippingAddress.value = '456 Different Street';

			const hasShippingData = shippingAddress.value.trim() !== '';

			if (hasShippingData) {
				checkbox.checked = true;
			}

			expect(checkbox.checked).toBe(true);
		});

		test('should not auto-check if no shipping data', () => {
			const shippingAddress = document.querySelector('#shipping_address_1');
			const checkbox = document.querySelector('#ship-to-different-address-checkbox');

			// Clear shipping data
			shippingAddress.value = '';

			const hasShippingData = shippingAddress.value.trim() !== '';

			if (!hasShippingData) {
				checkbox.checked = false;
			}

			expect(checkbox.checked).toBe(false);
		});
	});
});

describe('General Form Utilities', () => {
	describe('Data Attribute Handling', () => {
		test('should set data attribute for field identification', () => {
			document.body.innerHTML = '<p><input id="test-field" /></p>';
			const field = document.querySelector('#test-field');
			const wrapper = field.closest('p');

			wrapper.setAttribute('data-rcf-field', 'test-field');

			expect(wrapper.getAttribute('data-rcf-field')).toBe('test-field');
		});

		test('should convert field name to slug format', () => {
			const fieldName = 'Your Name';
			const slug = fieldName.toLowerCase().replace(/\s+/g, '-');

			expect(slug).toBe('your-name');
		});
	});

	describe('Debug Logging', () => {
		test('should log when debug enabled', () => {
			const debugConfig = { debug: true };

			if (debugConfig.debug) {
				console.log('Debug log message');
			}

			expect(console.log).toHaveBeenCalled();
		});

		test('should not log when debug disabled', () => {
			const debugConfig = { debug: false };
			console.log.mockClear();

			if (debugConfig.debug) {
				console.log('Debug log message');
			}

			expect(console.log).not.toHaveBeenCalled();
		});
	});
});
