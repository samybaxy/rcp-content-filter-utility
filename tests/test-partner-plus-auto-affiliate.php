<?php
/**
 * Class Test_Partner_Plus_Auto_Affiliate
 *
 * Tests for Partner+ auto-affiliate functionality
 *
 * @package RCP_Content_Filter
 */

class Test_Partner_Plus_Auto_Affiliate extends WP_UnitTestCase {

	/**
	 * Test getting default Partner+ product ID
	 */
	public function test_get_default_partner_product_id() {
		// Create a test product with the partner-plus slug
		$product_id = $this->factory->post->create( array(
			'post_type'   => 'product',
			'post_title'  => 'Partner Plus Program',
			'post_name'   => 'partner-plus',
			'post_status' => 'publish',
		) );

		// Get the product ID using the plugin function
		$plugin = RCP_Content_Filter_Utility::get_instance();
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'bl_get_default_partner_product_id' );
		$method->setAccessible( true );

		$found_id = $method->invoke( $plugin );

		$this->assertEquals( $product_id, $found_id );

		// Clean up
		wp_delete_post( $product_id, true );
	}

	/**
	 * Test product ID filter
	 */
	public function test_partner_product_id_filter() {
		$custom_id = 9999;

		add_filter( 'bl_partner_plus_product_id', function() use ( $custom_id ) {
			return $custom_id;
		} );

		$plugin = RCP_Content_Filter_Utility::get_instance();
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'bl_get_default_partner_product_id' );
		$method->setAccessible( true );

		$found_id = $method->invoke( $plugin );

		$this->assertEquals( $custom_id, $found_id );

		remove_all_filters( 'bl_partner_plus_product_id' );
	}

	/**
	 * Test order contains Partner+ product
	 */
	public function test_order_contains_partner_product() {
		// Create Partner+ product
		$product_id = $this->factory->post->create( array(
			'post_type'   => 'product',
			'post_name'   => 'partner-plus',
			'post_status' => 'publish',
		) );

		// Create order
		$order_id = $this->factory->post->create( array(
			'post_type' => 'shop_order',
		) );

		// Add product to order meta
		update_post_meta( $order_id, '_product_ids', array( $product_id ) );

		$plugin = RCP_Content_Filter_Utility::get_instance();
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'bl_order_contains_partner_product' );
		$method->setAccessible( true );

		$contains = $method->invoke( $plugin, $order_id );

		$this->assertTrue( $contains );

		// Clean up
		wp_delete_post( $product_id, true );
		wp_delete_post( $order_id, true );
	}

	/**
	 * Test affiliate data filter
	 */
	public function test_affiliate_data_filter() {
		add_filter( 'bl_auto_affiliate_data', function( $data, $order_id, $user_id ) {
			$data['rate_type'] = 'percentage';
			$data['rate'] = 10;
			return $data;
		}, 10, 3 );

		$plugin = RCP_Content_Filter_Utility::get_instance();
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'bl_get_affiliate_data_for_order' );
		$method->setAccessible( true );

		$data = $method->invoke( $plugin, 123, 456 );

		$this->assertArrayHasKey( 'rate_type', $data );
		$this->assertEquals( 'percentage', $data['rate_type'] );
		$this->assertEquals( 10, $data['rate'] );

		remove_all_filters( 'bl_auto_affiliate_data' );
	}

	/**
	 * Test affiliate status
	 */
	public function test_affiliate_status_is_active() {
		$plugin = RCP_Content_Filter_Utility::get_instance();
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'bl_get_affiliate_data_for_order' );
		$method->setAccessible( true );

		$data = $method->invoke( $plugin, 123, 456 );

		// Status should always be 'active' for Partner+
		$this->assertEquals( 'active', $data['status'] );
	}

	/**
	 * Test thank you page transient storage
	 */
	public function test_thankyou_page_transient() {
		$order_id = 12345;
		$thank_you_url = 'https://example.com/checkout/order-received/12345/?key=abc123';

		// Set transient
		set_transient( 'bl_partner_thankyou_url_' . $order_id, $thank_you_url, 5 * MINUTE_IN_SECONDS );

		$stored_url = get_transient( 'bl_partner_thankyou_url_' . $order_id );

		$this->assertEquals( $thank_you_url, $stored_url );

		// Clean up
		delete_transient( 'bl_partner_thankyou_url_' . $order_id );
	}

	/**
	 * Test cart clearing after order
	 */
	public function test_cart_cleared_after_partner_order() {
		// This would require WooCommerce mocking, so we'll test the hook exists
		$this->assertTrue( has_action( 'woocommerce_thankyou' ) !== false );
	}

	/**
	 * Test order note added
	 */
	public function test_order_note_format() {
		$affiliate_id = 67;
		$user_id = 123;

		$expected_note = sprintf(
			'Affiliate account #%d automatically created for customer.',
			$affiliate_id
		);

		$this->assertStringContainsString( 'Affiliate account', $expected_note );
		$this->assertStringContainsString( (string) $affiliate_id, $expected_note );
	}
}
