<?php
/**
 * Class Test_Checkout_Validation
 *
 * Tests for WooCommerce checkout ASCII validation
 *
 * Note: ASCII validation is implemented in JavaScript (assets/js/checkout-ascii-validation.js)
 * These tests verify the JavaScript file exists and contains the correct validation logic.
 *
 * @package RCP_Content_Filter
 */

class Test_Checkout_Validation extends WP_UnitTestCase {

	/**
	 * Test that validation JavaScript file exists
	 */
	public function test_validation_js_file_exists() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$this->assertFileExists( $js_file, 'Checkout validation JavaScript file should exist' );
	}

	/**
	 * Test JavaScript contains ASCII validation function
	 */
	public function test_js_has_ascii_validation() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'hasNonAsciiChars', $js_content );
		$this->assertStringContainsString( '/[^\\x00-\\x7F]/', $js_content, 'Should have regex for non-ASCII detection' );
	}

	/**
	 * Test JavaScript contains address field validation
	 */
	public function test_js_has_address_validation() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'hasDisallowedAddressChars', $js_content );
		$this->assertStringContainsString( 'validateValue', $js_content );
	}

	/**
	 * Test JavaScript validates on blur and input events
	 */
	public function test_js_has_event_listeners() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'on(\'blur.rcf-validation\'', $js_content );
		$this->assertStringContainsString( 'on(\'input.rcf-validation\'', $js_content );
		$this->assertStringContainsString( 'on(\'paste.rcf-validation\'', $js_content );
	}

	/**
	 * Test JavaScript prevents form submission on validation error
	 */
	public function test_js_prevents_invalid_submission() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'checkout_place_order', $js_content );
		$this->assertStringContainsString( 'validateAllFields', $js_content );
	}

	/**
	 * Test JavaScript handles email fields differently
	 */
	public function test_js_handles_email_fields() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'EMAIL_FIELDS', $js_content );
		$this->assertStringContainsString( 'isEmailField', $js_content );
	}

	/**
	 * Test JavaScript shows error messages
	 */
	public function test_js_shows_error_messages() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'showFieldError', $js_content );
		$this->assertStringContainsString( 'removeFieldError', $js_content );
		$this->assertStringContainsString( 'rcf-validation-error', $js_content );
	}

	/**
	 * Test that validation error message is localized
	 */
	public function test_validation_uses_localized_message() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'rcfCheckoutValidation.errorMessage', $js_content );
	}

	/**
	 * Test JavaScript reinitializes after AJAX updates
	 */
	public function test_js_reinitializes_on_ajax() {
		$js_file = RCP_FILTER_PLUGIN_DIR . 'assets/js/checkout-ascii-validation.js';
		$js_content = file_get_contents( $js_file );

		$this->assertStringContainsString( 'updated_checkout', $js_content );
		$this->assertStringContainsString( 'initValidation', $js_content );
	}
}
