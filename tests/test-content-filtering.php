<?php
/**
 * Class Test_Content_Filtering
 *
 * Tests for content filtering functionality
 *
 * @package RCP_Content_Filter
 */

class Test_Content_Filtering extends WP_UnitTestCase {

	/**
	 * Test plugin initialization
	 */
	public function test_plugin_initialized() {
		$this->assertTrue( class_exists( 'RCP_Content_Filter_Utility' ) );
		$this->assertInstanceOf( 'RCP_Content_Filter_Utility', RCP_Content_Filter_Utility::get_instance() );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton_pattern() {
		$instance1 = RCP_Content_Filter_Utility::get_instance();
		$instance2 = RCP_Content_Filter_Utility::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test content filtering hooks are registered
	 */
	public function test_hooks_registered() {
		$this->assertTrue( has_filter( 'the_posts' ) !== false );
		$this->assertTrue( has_action( 'pre_get_posts' ) !== false );
	}

	/**
	 * Test should_filter_post_type method
	 */
	public function test_should_filter_post_type() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		// Save current setting
		$original_setting = get_option( 'rcf_filter_post_types', array() );

		// Set post types to filter
		update_option( 'rcf_filter_post_types', array( 'post' => 1 ) );

		// Create reflection to access private method
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'should_filter_post_type' );
		$method->setAccessible( true );

		// Test filtering
		$this->assertTrue( $method->invoke( $plugin, 'post' ) );
		$this->assertFalse( $method->invoke( $plugin, 'page' ) );

		// Restore original setting
		update_option( 'rcf_filter_post_types', $original_setting );
	}

	/**
	 * Test query adjustment for restrictions
	 */
	public function test_adjust_query_for_restrictions() {
		$plugin = RCP_Content_Filter_Utility::get_instance();

		// Create a test query
		$query = new WP_Query( array(
			'post_type' => 'post',
			'posts_per_page' => 10,
		) );

		// Get original posts_per_page
		$original_ppp = $query->get( 'posts_per_page' );

		// Adjust query
		$reflection = new ReflectionClass( $plugin );
		$method = $reflection->getMethod( 'adjust_query_for_restrictions' );
		$method->setAccessible( true );
		$method->invoke( $plugin, $query );

		// Should be adjusted to 3x original
		$this->assertEquals( $original_ppp * 3, $query->get( 'posts_per_page' ) );
	}

	/**
	 * Test that admin queries are not filtered
	 */
	public function test_admin_queries_not_filtered() {
		set_current_screen( 'edit-post' );

		$query = new WP_Query( array(
			'post_type' => 'post',
		) );

		$this->assertTrue( $query->is_admin() );

		// Admin queries should not be marked for filtering
		$this->assertFalse( $query->get( 'rcf_needs_filtering' ) );

		set_current_screen( 'front' );
	}
}
