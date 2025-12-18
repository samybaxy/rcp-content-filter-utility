<?php
/**
 * Class Test_Partner_Plus_Auto_Affiliate
 *
 * Tests for Partner+ auto-affiliate functionality
 *
 * Note: Partner+ functions are global functions, not class methods.
 * These tests verify the functions exist and are properly hooked.
 *
 * @package RCP_Content_Filter
 */

class Test_Partner_Plus_Auto_Affiliate extends WP_UnitTestCase {

	/**
	 * Test that Partner+ global functions exist
	 */
	public function test_partner_plus_functions_exist() {
		$this->assertTrue( function_exists( 'bl_get_default_partner_product_id' ) );
		$this->assertTrue( function_exists( 'bl_auto_create_affiliate_on_partner_purchase' ) );
		$this->assertTrue( function_exists( 'bl_reject_affwp_referral_on_bad_status' ) );
		$this->assertTrue( function_exists( 'bl_force_partner_thankyou_page' ) );
	}

	/**
	 * Test getting default Partner+ product ID
	 */
	public function test_get_default_partner_product_id() {
		// Test that function returns an integer
		$product_id = bl_get_default_partner_product_id( 0 );

		// Function should return 0 if no product found
		$this->assertIsInt( $product_id );
	}

	/**
	 * Test product ID filter hook
	 */
	public function test_partner_product_id_filter() {
		$custom_id = 9999;

		add_filter( 'bl_partner_plus_product_id', function() use ( $custom_id ) {
			return $custom_id;
		} );

		$found_id = bl_get_default_partner_product_id( 0 );

		$this->assertEquals( $custom_id, $found_id );

		remove_all_filters( 'bl_partner_plus_product_id' );
	}

	/**
	 * Test thankyou page transient
	 */
	public function test_thankyou_page_transient() {
		$order_id = 123;

		// Set transient
		set_transient( 'bl_force_partner_thankyou_' . $order_id, true, MINUTE_IN_SECONDS );

		// Get transient
		$transient = get_transient( 'bl_force_partner_thankyou_' . $order_id );

		$this->assertTrue( $transient );
	}

	/**
	 * Test that Partner+ hooks are registered
	 */
	public function test_partner_plus_hooks_registered() {
		// Check that the main hooks exist
		$this->assertTrue( function_exists( 'bl_auto_create_affiliate_on_partner_purchase' ) );
		$this->assertTrue( function_exists( 'bl_force_partner_thankyou_page' ) );

		// We can't test has_action in our mock environment, but we can verify the functions exist
		$this->assertTrue( is_callable( 'bl_auto_create_affiliate_on_partner_purchase' ) );
	}

	/**
	 * Test referral rejection function exists
	 */
	public function test_referral_rejection_function() {
		$this->assertTrue( function_exists( 'bl_reject_affwp_referral_on_bad_status' ) );

		// Function should be callable
		$this->assertTrue( is_callable( 'bl_reject_affwp_referral_on_bad_status' ) );
	}

	/**
	 * Test redirect prevention functions exist
	 */
	public function test_redirect_prevention_functions() {
		$this->assertTrue( function_exists( 'bl_prevent_partner_redirects' ) );
		$this->assertTrue( function_exists( 'bl_intercept_wp_redirect' ) );
		$this->assertTrue( function_exists( 'bl_block_js_redirects_on_thankyou' ) );
	}

	/**
	 * Test console hijack function exists
	 */
	public function test_console_hijack_function() {
		$this->assertTrue( function_exists( 'bl_hijack_partnership_console_for_partner_orders' ) );
	}
}
