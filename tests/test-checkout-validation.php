<?php
/**
 * Class Test_Checkout_Validation
 *
 * Tests for WooCommerce checkout ASCII validation
 *
 * @package RCP_Content_Filter
 */

class Test_Checkout_Validation extends WP_UnitTestCase {

	/**
	 * Test ASCII validation - valid input
	 */
	public function test_ascii_validation_valid() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Valid inputs
		$this->assertTrue( $method->invoke( $plugin, '123 Main Street' ) );
		$this->assertTrue( $method->invoke( $plugin, 'Apartment 4B' ) );
		$this->assertTrue( $method->invoke( $plugin, 'New York, NY 10001' ) );
		$this->assertTrue( $method->invoke( $plugin, "O'Brien's Pub" ) );
		$this->assertTrue( $method->invoke( $plugin, '#123-456' ) );
	}

	/**
	 * Test ASCII validation - invalid input (kanji)
	 */
	public function test_ascii_validation_kanji() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Kanji characters
		$this->assertFalse( $method->invoke( $plugin, '東京都' ) );
		$this->assertFalse( $method->invoke( $plugin, '山田太郎' ) );
	}

	/**
	 * Test ASCII validation - invalid input (hiragana)
	 */
	public function test_ascii_validation_hiragana() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Hiragana characters
		$this->assertFalse( $method->invoke( $plugin, 'あいうえお' ) );
		$this->assertFalse( $method->invoke( $plugin, 'ひらがな' ) );
	}

	/**
	 * Test ASCII validation - invalid input (katakana)
	 */
	public function test_ascii_validation_katakana() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Katakana characters
		$this->assertFalse( $method->invoke( $plugin, 'カタカナ' ) );
		$this->assertFalse( $method->invoke( $plugin, 'トウキョウ' ) );
	}

	/**
	 * Test ASCII validation - invalid input (emoji)
	 */
	public function test_ascii_validation_emoji() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Emoji characters
		$this->assertFalse( $method->invoke( $plugin, '123 Main St 🏠' ) );
		$this->assertFalse( $method->invoke( $plugin, 'Hello 👋' ) );
	}

	/**
	 * Test address field character validation
	 */
	public function test_address_field_characters() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_valid_address_field' );
		$method->setAccessible( true );

		// Valid address characters
		$this->assertTrue( $method->invoke( $plugin, '123 Main St, Apt 4B' ) );
		$this->assertTrue( $method->invoke( $plugin, 'Building #5 / Suite 100' ) );
		$this->assertTrue( $method->invoke( $plugin, "McDonald's (Main Branch)" ) );

		// Invalid: @ symbol not allowed in address fields
		$this->assertFalse( $method->invoke( $plugin, '123 Main @ Street' ) );
	}

	/**
	 * Test email field allows @ symbol
	 */
	public function test_email_field_allows_at_symbol() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_valid_email_field' );
		$method->setAccessible( true );

		// @ symbol should be allowed in email fields
		$this->assertTrue( $method->invoke( $plugin, 'test@example.com' ) );
		$this->assertTrue( $method->invoke( $plugin, 'user+tag@domain.co.uk' ) );

		// But non-ASCII still rejected
		$this->assertFalse( $method->invoke( $plugin, '山田@example.com' ) );
	}

	/**
	 * Test empty values are valid
	 */
	public function test_empty_values_valid() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Empty values should be valid (optional fields)
		$this->assertTrue( $method->invoke( $plugin, '' ) );
		$this->assertTrue( $method->invoke( $plugin, null ) );
	}

	/**
	 * Test phone field requirement
	 */
	public function test_phone_field_required() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		// Check that phone requirement filter is registered
		$this->assertTrue( has_filter( 'woocommerce_billing_fields' ) !== false );
		$this->assertTrue( has_filter( 'woocommerce_shipping_fields' ) !== false );
	}

	/**
	 * Test Address Line 2 placeholder
	 */
	public function test_address_line_2_placeholder() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$fields = array(
			'address_2' => array(
				'placeholder' => '',
			),
		);

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_update_address_2_placeholder' );
		$method->setAccessible( true );

		$updated = $method->invoke( $plugin, $fields );

		$this->assertStringContainsString( 'Building', $updated['address_2']['placeholder'] );
		$this->assertStringContainsString( 'Apartment', $updated['address_2']['placeholder'] );
	}

	/**
	 * Test validation hooks registered
	 */
	public function test_validation_hooks() {
		$this->assertTrue( has_action( 'woocommerce_after_checkout_validation' ) !== false );
	}

	/**
	 * Test mixed valid and invalid characters
	 */
	public function test_mixed_characters() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'rcf_is_ascii_only' );
		$method->setAccessible( true );

		// Mix of ASCII and non-ASCII should fail
		$this->assertFalse( $method->invoke( $plugin, '123 Main Street 東京' ) );
		$this->assertFalse( $method->invoke( $plugin, 'John Smith あ' ) );
	}
}
