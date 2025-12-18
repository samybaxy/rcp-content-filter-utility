<?php
/**
 * Class Test_Loqate_Integration
 *
 * Tests for Loqate Address Capture integration
 *
 * @package RCP_Content_Filter
 */

class Test_Loqate_Integration extends WP_UnitTestCase {

	/**
	 * Test Loqate class exists
	 */
	public function test_loqate_class_exists() {
		$this->assertTrue( class_exists( 'RCF_Loqate_Address_Capture' ) );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_loqate_singleton() {
		$instance1 = RCF_Loqate_Address_Capture::get_instance();
		$instance2 = RCF_Loqate_Address_Capture::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test API key retrieval from constant
	 */
	public function test_api_key_from_constant() {
		if ( ! defined( 'LOQATE_API_KEY' ) ) {
			define( 'LOQATE_API_KEY', 'TEST-API-KEY-12345' );
		}

		$instance = RCF_Loqate_Address_Capture::get_instance();

		// Use reflection to access private method
		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'get_api_key' );
		$method->setAccessible( true );

		$api_key = $method->invoke( $instance );

		$this->assertEquals( 'TEST-API-KEY-12345', $api_key );
	}

	/**
	 * Test API key retrieval from option
	 */
	public function test_api_key_from_option() {
		update_option( 'rcf_loqate_api_key', 'OPTION-API-KEY-67890' );

		$instance = RCF_Loqate_Address_Capture::get_instance();

		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'get_api_key' );
		$method->setAccessible( true );

		$api_key = $method->invoke( $instance );

		// Should use option if no constant
		$this->assertNotEmpty( $api_key );

		delete_option( 'rcf_loqate_api_key' );
	}

	/**
	 * Test masked API key display
	 */
	public function test_masked_api_key() {
		if ( defined( 'LOQATE_API_KEY' ) ) {
			// If constant is defined, test with the constant value
			$instance = RCF_Loqate_Address_Capture::get_instance();
			$masked = $instance->get_masked_api_key();

			// Should show first 4 chars + asterisks
			$this->assertNotEmpty( $masked );
			$this->assertStringContainsString( '*', $masked );
		} else {
			// Test with option
			update_option( 'rcf_loqate_api_key', 'AA11-BB22-CC33-DD44' );

			$instance = RCF_Loqate_Address_Capture::get_instance();
			$masked = $instance->get_masked_api_key();

			// Should show first 4 chars + asterisks
			$this->assertStringStartsWith( 'AA11', $masked );
			$this->assertStringContainsString( '*', $masked );

			delete_option( 'rcf_loqate_api_key' );
		}
	}

	/**
	 * Test integration status
	 */
	public function test_integration_status() {
		$status = RCF_Loqate_Address_Capture::get_status();

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'enabled', $status );
		$this->assertArrayHasKey( 'api_key_set', $status );
		$this->assertArrayHasKey( 'woocommerce', $status );
	}

	/**
	 * Test geolocation options caching
	 */
	public function test_geolocation_options_cached() {
		update_option( 'rcf_loqate_geolocation_enabled', true );
		update_option( 'rcf_loqate_geolocation_radius', 150 );

		$instance = RCF_Loqate_Address_Capture::get_instance();

		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'get_cached_options' );
		$method->setAccessible( true );

		$options = $method->invoke( $instance );

		$this->assertIsArray( $options );
		$this->assertArrayHasKey( 'rcf_loqate_geolocation_enabled', $options );
		$this->assertEquals( 150, $options['rcf_loqate_geolocation_radius'] );

		delete_option( 'rcf_loqate_geolocation_enabled' );
		delete_option( 'rcf_loqate_geolocation_radius' );
	}

	/**
	 * Test country restriction option
	 */
	public function test_country_restriction() {
		update_option( 'rcf_loqate_allowed_countries', 'USA,GBR,CAN' );

		$instance = RCF_Loqate_Address_Capture::get_instance();

		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'get_cached_options' );
		$method->setAccessible( true );

		$options = $method->invoke( $instance );

		$this->assertEquals( 'USA,GBR,CAN', $options['rcf_loqate_allowed_countries'] );

		delete_option( 'rcf_loqate_allowed_countries' );
	}

	/**
	 * Test field mapping configuration
	 */
	public function test_field_mapping() {
		$instance = RCF_Loqate_Address_Capture::get_instance();

		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'get_billing_field_mapping' );
		$method->setAccessible( true );

		$mapping = $method->invoke( $instance );

		$this->assertIsArray( $mapping );
		$this->assertArrayHasKey( 'search', $mapping );
		$this->assertArrayHasKey( 'populate', $mapping );
		$this->assertEquals( 'billing_address_1', $mapping['search'] );
	}
}
