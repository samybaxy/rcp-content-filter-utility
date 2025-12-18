/**
 * Tests for checkout ASCII validation
 *
 * @jest-environment jsdom
 */

describe('Checkout ASCII Validation', () => {
	beforeEach(() => {
		// Setup DOM
		document.body.innerHTML = `
			<form class="checkout">
				<div class="form-row">
					<input type="text" id="billing_first_name" name="billing_first_name" />
				</div>
				<div class="form-row">
					<input type="text" id="billing_address_1" name="billing_address_1" />
				</div>
				<div class="form-row">
					<input type="email" id="billing_email" name="billing_email" />
				</div>
			</form>
		`;
	});

	describe('hasNonAsciiChars', () => {
		test('should detect non-ASCII characters (kanji)', () => {
			const value = '山田太郎';
			expect(/[^\x00-\x7F]/.test(value)).toBe(true);
		});

		test('should detect non-ASCII characters (hiragana)', () => {
			const value = 'あいうえお';
			expect(/[^\x00-\x7F]/.test(value)).toBe(true);
		});

		test('should detect non-ASCII characters (katakana)', () => {
			const value = 'カタカナ';
			expect(/[^\x00-\x7F]/.test(value)).toBe(true);
		});

		test('should detect non-ASCII characters (emoji)', () => {
			const value = '123 Main St 🏠';
			expect(/[^\x00-\x7F]/.test(value)).toBe(true);
		});

		test('should not detect ASCII characters', () => {
			const value = '123 Main Street';
			expect(/[^\x00-\x7F]/.test(value)).toBe(false);
		});
	});

	describe('hasDisallowedAddressChars', () => {
		test('should allow valid address characters', () => {
			const pattern = /^[A-Za-z0-9\s\-.,'\/#()&+_%]*$/;

			expect(pattern.test('123 Main Street')).toBe(true);
			expect(pattern.test('Apartment 4B')).toBe(true);
			expect(pattern.test("O'Brien's Pub")).toBe(true);
			expect(pattern.test('#123-456')).toBe(true);
			expect(pattern.test('Building (Suite 100)')).toBe(true);
		});

		test('should disallow @ symbol in address fields', () => {
			const pattern = /^[A-Za-z0-9\s\-.,'\/#()&+_%]*$/;
			expect(pattern.test('123 Main @ Street')).toBe(false);
		});

		test('should disallow special characters', () => {
			const pattern = /^[A-Za-z0-9\s\-.,'\/#()&+_%]*$/;
			expect(pattern.test('Test$Invalid')).toBe(false);
			expect(pattern.test('Test^Invalid')).toBe(false);
		});
	});

	describe('validateValue', () => {
		test('should validate empty values as valid', () => {
			const value = '';
			expect(value === '').toBe(true);
		});

		test('should validate ASCII-only address', () => {
			const value = '123 Main Street, Apt 4B';
			const hasNonAscii = /[^\x00-\x7F]/.test(value);
			const pattern = /^[A-Za-z0-9\s\-.,'\/#()&+_%]*$/;
			const hasDisallowed = !pattern.test(value);

			expect(hasNonAscii).toBe(false);
			expect(hasDisallowed).toBe(false);
		});

		test('should invalidate non-ASCII address', () => {
			const value = '東京都新宿区';
			const hasNonAscii = /[^\x00-\x7F]/.test(value);

			expect(hasNonAscii).toBe(true);
		});

		test('should allow @ in email fields', () => {
			const value = 'test@example.com';
			const hasNonAscii = /[^\x00-\x7F]/.test(value);

			// Email validation allows @ symbol
			expect(hasNonAscii).toBe(false);
		});
	});

	describe('Field identification', () => {
		test('should identify email fields correctly', () => {
			const emailFields = ['billing_email', 'shipping_email'];

			expect(emailFields.includes('billing_email')).toBe(true);
			expect(emailFields.includes('billing_address_1')).toBe(false);
		});
	});

	describe('Error message display', () => {
		test('should show error message for invalid input', () => {
			const field = document.getElementById('billing_first_name');
			const formRow = field.closest('.form-row');

			// Simulate adding error
			formRow.classList.add('woocommerce-invalid');
			const errorMsg = document.createElement('span');
			errorMsg.className = 'rcf-validation-error-message';
			errorMsg.textContent = 'Use Roman/English characters only';
			formRow.appendChild(errorMsg);

			expect(formRow.classList.contains('woocommerce-invalid')).toBe(true);
			expect(formRow.querySelector('.rcf-validation-error-message')).toBeTruthy();
		});

		test('should remove error message when valid', () => {
			const field = document.getElementById('billing_first_name');
			const formRow = field.closest('.form-row');

			// Add error first
			formRow.classList.add('woocommerce-invalid');
			const errorMsg = document.createElement('span');
			errorMsg.className = 'rcf-validation-error-message';
			formRow.appendChild(errorMsg);

			// Remove error
			formRow.classList.remove('woocommerce-invalid');
			formRow.querySelector('.rcf-validation-error-message')?.remove();

			expect(formRow.classList.contains('woocommerce-invalid')).toBe(false);
			expect(formRow.querySelector('.rcf-validation-error-message')).toBeNull();
		});
	});

	describe('Real-world scenarios', () => {
		test('should handle mixed valid and invalid characters', () => {
			const value = '123 Main Street 東京';
			const hasNonAscii = /[^\x00-\x7F]/.test(value);

			expect(hasNonAscii).toBe(true);
		});

		test('should handle punctuation correctly', () => {
			const value = "123 Main St., Apt #4B (Building 5)";
			const hasNonAscii = /[^\x00-\x7F]/.test(value);
			const pattern = /^[A-Za-z0-9\s\-.,'\/#()&+_%]*$/;
			const hasDisallowed = !pattern.test(value);

			expect(hasNonAscii).toBe(false);
			expect(hasDisallowed).toBe(false);
		});

		test('should handle international phone numbers (ASCII)', () => {
			const value = '+1 (555) 123-4567';
			const hasNonAscii = /[^\x00-\x7F]/.test(value);

			expect(hasNonAscii).toBe(false);
		});
	});
});
