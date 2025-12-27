<?php
/**
 * Loqate Debug Helper
 *
 * Temporary debugging file to diagnose why Loqate doesn't enqueue on staging
 *
 * Usage: Add to wp-config.php:
 * define( 'RCF_LOQATE_FORCE_DEBUG', true );
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhanced checkout detection debugging
 */
function rcf_loqate_debug_checkout_detection() {
	// Only run if force debug is enabled
	if ( ! defined( 'RCF_LOQATE_FORCE_DEBUG' ) || ! RCF_LOQATE_FORCE_DEBUG ) {
		return;
	}

	global $wp, $wp_query;

	$debug_info = array(
		'timestamp' => current_time( 'mysql' ),
		'url' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		'user_logged_in' => is_user_logged_in(),
		'user_id' => get_current_user_id(),
		'current_action' => current_action(),
		'current_filter' => current_filter(),
	);

	// Method 1: is_checkout()
	$debug_info['method_1_is_checkout_exists'] = function_exists( 'is_checkout' );
	$debug_info['method_1_is_checkout_result'] = function_exists( 'is_checkout' ) ? is_checkout() : 'N/A';

	// Method 2: WooCommerce active
	$debug_info['woocommerce_active'] = class_exists( 'WooCommerce' );

	// Method 3: Queried object
	if ( isset( $wp_query->queried_object ) ) {
		$queried = $wp_query->queried_object;
		$debug_info['queried_object_type'] = get_class( $queried );
		$debug_info['queried_object_post_name'] = isset( $queried->post_name ) ? $queried->post_name : 'N/A';
		$debug_info['queried_object_id'] = isset( $queried->ID ) ? $queried->ID : 'N/A';

		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_page_id = wc_get_page_id( 'checkout' );
			$debug_info['wc_checkout_page_id'] = $checkout_page_id;
			$debug_info['queried_matches_checkout'] = ( isset( $queried->ID ) && $queried->ID === $checkout_page_id );
		}
	} else {
		$debug_info['queried_object'] = 'NULL';
	}

	// Method 4: URI pattern check
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$debug_info['uri_contains_checkout'] = ( strpos( $request_uri, '/checkout' ) !== false );
		$debug_info['uri_regex_match'] = preg_match( '#/checkout/?(\?|$|/)#', $request_uri );
	}

	// Method 5: Query vars
	$debug_info['wp_query_vars'] = isset( $wp->query_vars ) ? array_keys( $wp->query_vars ) : 'N/A';
	$debug_info['pagename'] = isset( $wp->query_vars['pagename'] ) ? $wp->query_vars['pagename'] : 'N/A';
	$debug_info['post_type'] = isset( $wp->query_vars['post_type'] ) ? $wp->query_vars['post_type'] : 'N/A';
	$debug_info['name'] = isset( $wp->query_vars['name'] ) ? $wp->query_vars['name'] : 'N/A';

	// Method 6: Check is_page()
	$debug_info['is_page'] = is_page();
	if ( function_exists( 'is_page' ) ) {
		$debug_info['is_page_checkout_slug'] = is_page( 'checkout' );
		$debug_info['is_page_checkout_id'] = function_exists( 'wc_get_page_id' ) ? is_page( wc_get_page_id( 'checkout' ) ) : 'N/A';
	}

	// API Key check
	$api_key = '';
	if ( defined( 'LOQATE_API_KEY' ) ) {
		$api_key = 'constant';
	} elseif ( get_option( 'rcf_loqate_api_key', '' ) ) {
		$api_key = 'option';
	}
	$debug_info['api_key_source'] = $api_key;
	$debug_info['api_key_exists'] = ! empty( $api_key );

	// Log everything
	error_log( '========== RCF LOQATE DEBUG START ==========' );
	error_log( 'Environment: ' . ( defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'unknown' ) );
	foreach ( $debug_info as $key => $value ) {
		error_log( sprintf( '[Loqate Debug] %s: %s', $key, is_array( $value ) ? json_encode( $value ) : $value ) );
	}
	error_log( '========== RCF LOQATE DEBUG END ==========' );
}

// Hook at different priorities to see when things become available
add_action( 'wp', 'rcf_loqate_debug_checkout_detection', 1 );
add_action( 'wp', 'rcf_loqate_debug_checkout_detection', 10 );
add_action( 'wp_enqueue_scripts', 'rcf_loqate_debug_checkout_detection', 5 );
add_action( 'wp_enqueue_scripts', 'rcf_loqate_debug_checkout_detection', 20 );
add_action( 'wp_enqueue_scripts', 'rcf_loqate_debug_checkout_detection', 999 );

/**
 * Force enqueue on any page with "checkout" in URL (for testing)
 */
function rcf_loqate_force_enqueue_debug() {
	if ( ! defined( 'RCF_LOQATE_FORCE_ENQUEUE' ) || ! RCF_LOQATE_FORCE_ENQUEUE ) {
		return;
	}

	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( strpos( $uri, 'checkout' ) === false ) {
		return;
	}

	error_log( '[Loqate Debug] FORCE ENQUEUE activated' );

	// Get API key
	$api_key = '';
	if ( defined( 'LOQATE_API_KEY' ) ) {
		$api_key = LOQATE_API_KEY;
	} else {
		$api_key = get_option( 'rcf_loqate_api_key', '' );
	}

	if ( empty( $api_key ) ) {
		error_log( '[Loqate Debug] FORCE ENQUEUE failed - No API key' );
		return;
	}

	// Enqueue Loqate SDK
	wp_enqueue_script(
		'loqate-sdk',
		'https://api.addressy.com/js/address-4.00.min.js',
		array(),
		'4.00',
		false
	);

	// Enqueue init script
	wp_enqueue_script(
		'rcf-loqate-init',
		RCP_FILTER_PLUGIN_URL . 'assets/js/loqate-address-capture.js',
		array( 'jquery', 'loqate-sdk' ),
		RCP_FILTER_VERSION,
		true
	);

	// Simplified config
	wp_localize_script(
		'rcf-loqate-init',
		'rcfLoqateConfig',
		array(
			'apiKey' => $api_key,
			'debug' => true,
			'billingFields' => array(
				'search' => array( 'billing_address_1' ),
				'populate' => array( 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country' )
			),
			'shippingFields' => array(
				'search' => array( 'shipping_address_1' ),
				'populate' => array( 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country' )
			)
		)
	);

	error_log( '[Loqate Debug] FORCE ENQUEUE completed' );
}
add_action( 'wp_enqueue_scripts', 'rcf_loqate_force_enqueue_debug', 9999 );
