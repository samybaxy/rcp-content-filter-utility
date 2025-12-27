<?php
/**
 * Loqate Address Capture Integration for WooCommerce Checkout
 *
 * Integrates Loqate's Address Capture SDK for real-time address autocomplete
 * and validation in the WooCommerce checkout page. Supports billing and shipping
 * address fields, email and phone validation.
 *
 * Features:
 * - SubBuilding/Apt/Suite extraction for Address Line 2
 * - Cached configuration for performance
 * - Lazy initialization for shipping fields
 * - Country context handling for accurate autocomplete
 *
 * @package RCP_Content_Filter
 * @since 1.0.15
 * @updated 1.0.38 - Performance optimizations, cached config
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loqate Address Capture integration class
 *
 * Handles SDK initialization, field mapping, and real-time validation
 * for WooCommerce checkout addresses using Loqate's Address Capture service.
 */
class RCF_Loqate_Address_Capture {

	/**
	 * Singleton instance
	 *
	 * @var RCF_Loqate_Address_Capture|null
	 */
	private static ?self $instance = null;

	/**
	 * Loqate API Key
	 *
	 * @var string
	 */
	private string $api_key = '';

	/**
	 * Whether Loqate integration is enabled
	 *
	 * @var bool
	 */
	private bool $enabled = false;

	/**
	 * Cached options to reduce DB queries
	 *
	 * @var array|null
	 */
	private ?array $cached_options = null;

	/**
	 * Cached localized config
	 *
	 * @var array|null
	 */
	private ?array $cached_config = null;

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if Loqate is enabled and API key is set
		$this->api_key = $this->get_api_key();
		$this->enabled = ! empty( $this->api_key );

		// Debug logging for constructor
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[Loqate Debug] Constructor - API key: %s | Enabled: %s',
				! empty( $this->api_key ) ? 'SET' : 'NOT SET',
				$this->enabled ? 'YES' : 'NO'
			) );
		}

		if ( ! $this->enabled ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Loqate Debug] Constructor exiting early - No API key found' );
			}
			return;
		}

		// Hook into WooCommerce checkout page
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_loqate_assets' ), 20 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Loqate Debug] wp_enqueue_scripts hook registered at priority 20' );
		}
	}

	/**
	 * Get Loqate API Key from constants, options, or filters
	 *
	 * Checks in the following order:
	 * 1. LOQATE_API_KEY constant
	 * 2. rcf_loqate_api_key option
	 * 3. rcf_loqate_api_key filter
	 *
	 * @return string API key or empty string
	 */
	private function get_api_key(): string {
		// Check constant first
		if ( defined( 'LOQATE_API_KEY' ) ) {
			return sanitize_text_field( LOQATE_API_KEY );
		}

		// Check WordPress option
		$api_key = get_option( 'rcf_loqate_api_key', '' );
		if ( ! empty( $api_key ) ) {
			return sanitize_text_field( $api_key );
		}

		// Apply filter for extensibility
		$api_key = apply_filters( 'rcf_loqate_api_key', '' );
		return sanitize_text_field( $api_key );
	}

	/**
	 * Check if we're on the WooCommerce checkout page
	 * Uses multiple detection methods for reliability with logged-in users
	 *
	 * @return bool
	 */
	private function is_checkout_page(): bool {
		// Method 1: WooCommerce's is_checkout() function (most reliable when available)
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		// Method 2: Check global $wp or $wp_query for checkout page
		global $wp, $wp_query;

		// Check if current page is checkout by checking the query
		if ( isset( $wp_query->queried_object ) ) {
			$queried_object = $wp_query->queried_object;
			if ( isset( $queried_object->post_name ) && $queried_object->post_name === 'checkout' ) {
				return true;
			}
			if ( isset( $queried_object->ID ) && function_exists( 'wc_get_page_id' ) ) {
				$checkout_page_id = wc_get_page_id( 'checkout' );
				if ( $checkout_page_id > 0 && $queried_object->ID === $checkout_page_id ) {
					return true;
				}
			}
		}

		// Method 3: Check request URI for checkout page
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			// Check if URL contains /checkout or /checkout/
			if ( preg_match( '#/checkout/?(\?|$|/)#', $request_uri ) ) {
				return true;
			}
		}

		// Method 4: Check pagename query var
		if ( isset( $wp->query_vars['pagename'] ) && strpos( $wp->query_vars['pagename'], 'checkout' ) !== false ) {
			return true;
		}

		// Method 5: Check post_type and name for 'page' and 'checkout'
		if ( isset( $wp->query_vars['post_type'] ) && $wp->query_vars['post_type'] === 'page'
		     && isset( $wp->query_vars['name'] ) && $wp->query_vars['name'] === 'checkout' ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue Loqate SDK and custom initialization script
	 *
	 * @return void
	 */
	public function enqueue_loqate_assets(): void {
		// Debug logging for checkout detection
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$is_checkout = $this->is_checkout_page();
			$user_info = is_user_logged_in() ? 'logged-in (ID: ' . get_current_user_id() . ')' : 'guest';
			$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'unknown';

			error_log( sprintf(
				'[Loqate Debug] Enqueue check - Is checkout: %s | User: %s | URL: %s',
				$is_checkout ? 'YES' : 'NO',
				$user_info,
				$current_url
			) );
		}

		// Only enqueue on checkout page
		if ( ! $this->is_checkout_page() ) {
			return;
		}

		// Log successful enqueue
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Loqate Debug] Scripts enqueued successfully' );
		}

		// Enqueue Loqate SDK from CDN
		wp_enqueue_script(
			'loqate-sdk',
			'https://api.addressy.com/js/address-4.00.min.js',
			array(),
			'4.00',
			false // Load in head for early initialization
		);

		// Enqueue custom Loqate initialization script
		wp_enqueue_script(
			'rcf-loqate-init',
			RCP_FILTER_PLUGIN_URL . 'assets/js/loqate-address-capture.js',
			array( 'jquery', 'loqate-sdk' ),
			RCP_FILTER_VERSION,
			true
		);

		// Pass configuration to JavaScript
		wp_localize_script(
			'rcf-loqate-init',
			'rcfLoqateConfig',
			$this->get_localized_config()
		);

		// Register a dummy style handle to attach inline CSS
		// wp_add_inline_style() requires a style handle, not a script handle
		wp_register_style( 'rcf-loqate-styles', false );
		wp_enqueue_style( 'rcf-loqate-styles' );

		// Add inline CSS for Loqate styling
		wp_add_inline_style(
			'rcf-loqate-styles',
			$this->get_inline_css()
		);
	}

	/**
	 * Get all Loqate options in a single query for performance
	 * Caches results to avoid repeated DB queries
	 *
	 * @return array Options array
	 */
	private function get_cached_options(): array {
		if ( null !== $this->cached_options ) {
			return $this->cached_options;
		}

		global $wpdb;

		// Batch-load all Loqate options in a single query
		$option_names = array(
			'rcf_loqate_geolocation_enabled',
			'rcf_loqate_geolocation_radius',
			'rcf_loqate_geolocation_max_items',
			'rcf_loqate_allow_manual_entry',
			'rcf_loqate_validate_email',
			'rcf_loqate_validate_phone',
			'rcf_loqate_allowed_countries',
		);

		$placeholders = implode( ',', array_fill( 0, count( $option_names ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholders)",
				...$option_names
			),
			OBJECT_K
		);

		// Map results with defaults
		$defaults = array(
			'rcf_loqate_geolocation_enabled'   => false,
			'rcf_loqate_geolocation_radius'    => 100,
			'rcf_loqate_geolocation_max_items' => 5,
			'rcf_loqate_allow_manual_entry'    => true,
			'rcf_loqate_validate_email'        => true,
			'rcf_loqate_validate_phone'        => false,
			'rcf_loqate_allowed_countries'     => '',
		);

		$this->cached_options = array();
		foreach ( $defaults as $name => $default ) {
			$this->cached_options[ $name ] = isset( $results[ $name ] )
				? maybe_unserialize( $results[ $name ]->option_value )
				: $default;
		}

		return $this->cached_options;
	}

	/**
	 * Get configuration array to pass to JavaScript
	 * Uses cached options for improved performance
	 *
	 * @return array Configuration for Loqate initialization
	 */
	private function get_localized_config(): array {
		// Return cached config if available
		if ( null !== $this->cached_config ) {
			return $this->cached_config;
		}

		// Load all options in single query
		$options = $this->get_cached_options();

		/**
		 * Filter allowed countries for address capture
		 *
		 * Format: comma-separated ISO 3166-1 alpha-3 country codes
		 * Examples: "USA,GBR,CAN,AUS,DEU,FRA"
		 * Leave empty to allow all countries
		 *
		 * @param string $countries Country codes
		 */
		$allowed_countries = apply_filters( 'rcf_loqate_allowed_countries', $options['rcf_loqate_allowed_countries'] );

		/**
		 * Filter geolocation options
		 *
		 * Geolocation is DISABLED by default to provide global search results
		 * matching the official Loqate tool behavior. When disabled, searching
		 * for "2707 W Avenue" returns USA addresses. When enabled, results are
		 * biased toward the user's current location.
		 *
		 * @param array $geolocation {
		 *     @type bool $enabled Whether to enable geolocation (default: false)
		 *     @type int $radius Search radius in kilometers
		 *     @type int $max_items Maximum items to return
		 * }
		 */
		$geolocation = apply_filters(
			'rcf_loqate_geolocation_options',
			array(
				'enabled'   => (bool) $options['rcf_loqate_geolocation_enabled'],
				'radius'    => (int) $options['rcf_loqate_geolocation_radius'],
				'max_items' => (int) $options['rcf_loqate_geolocation_max_items'],
			)
		);

		/**
		 * Filter whether to allow manual address entry
		 *
		 * @param bool $allow Whether to show "Enter Manually" option
		 */
		$allow_manual_entry = apply_filters( 'rcf_loqate_allow_manual_entry', (bool) $options['rcf_loqate_allow_manual_entry'] );

		/**
		 * Filter email validation enabled status
		 *
		 * @param bool $enabled Whether to validate email fields
		 */
		$validate_email = apply_filters( 'rcf_loqate_validate_email', (bool) $options['rcf_loqate_validate_email'] );

		/**
		 * Filter phone validation enabled status
		 *
		 * @param bool $enabled Whether to validate phone fields
		 */
		$validate_phone = apply_filters( 'rcf_loqate_validate_phone', (bool) $options['rcf_loqate_validate_phone'] );

		// Build and cache config
		$this->cached_config = array(
			'apiKey'                => $this->api_key,
			'enabled'               => $this->enabled,
			'allowedCountries'      => $allowed_countries,
			'geolocationEnabled'    => $geolocation['enabled'],
			'geolocationRadius'     => $geolocation['radius'],
			'geolocationMaxItems'   => $geolocation['max_items'],
			'allowManualEntry'      => $allow_manual_entry,
			'validateEmail'         => $validate_email,
			'validatePhone'         => $validate_phone,
			'billingAddressFields'  => $this->get_billing_field_mapping(),
			'shippingAddressFields' => $this->get_shipping_field_mapping(),
			'debug'                 => defined( 'WP_DEBUG' ) && WP_DEBUG,
		);

		return $this->cached_config;
	}

	/**
	 * Get billing address field mapping
	 *
	 * Maps WooCommerce billing fields to Loqate field modes
	 *
	 * @return array Field mapping configuration
	 */
	private function get_billing_field_mapping(): array {
		/**
		 * Filter billing address field mapping
		 *
		 * @param array $fields {
		 *     @type string $search Primary search field (e.g., 'billing_address_1')
		 *     @type array $populate Fields to populate from search results
		 *     @type string $country Country field
		 * }
		 */
		return apply_filters(
			'rcf_loqate_billing_field_mapping',
			array(
				'search'   => 'billing_address_1',
				'populate' => array(
					'billing_address_2',
					'billing_city',
					'billing_state',
					'billing_postcode',
				),
				'country'  => 'billing_country',
				'email'    => 'billing_email',
				'phone'    => 'billing_phone',
			)
		);
	}

	/**
	 * Get shipping address field mapping
	 *
	 * Maps WooCommerce shipping fields to Loqate field modes
	 *
	 * @return array Field mapping configuration
	 */
	private function get_shipping_field_mapping(): array {
		/**
		 * Filter shipping address field mapping
		 *
		 * @param array $fields {
		 *     @type string $search Primary search field (e.g., 'shipping_address_1')
		 *     @type array $populate Fields to populate from search results
		 *     @type string $country Country field
		 * }
		 */
		return apply_filters(
			'rcf_loqate_shipping_field_mapping',
			array(
				'search'   => 'shipping_address_1',
				'populate' => array(
					'shipping_address_2',
					'shipping_city',
					'shipping_state',
					'shipping_postcode',
				),
				'country'  => 'shipping_country',
				'email'    => 'shipping_email',
				'phone'    => 'shipping_phone',
			)
		);
	}

	/**
	 * Get inline CSS for Loqate styling
	 *
	 * @return string CSS styles
	 */
	private function get_inline_css(): string {
		return <<<'CSS'
/* ============================================================================
   Loqate Address Capture - Modern Styling with Smooth Animations
   ============================================================================ */

/* Disable browser autocomplete */
#billing_address_1,
#shipping_address_1 {
	autocomplete: off !important;
}

/* ============================================================================
   Loqate Dropdown Styling
   ============================================================================ */

/* Main dropdown container */
.pca.pcalist {
	position: absolute !important;
	z-index: 999999 !important;
	background-color: #fff !important;
	border: 1px solid #ddd !important;
	border-radius: 8px !important;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.08) !important;
	max-height: 320px !important;
	overflow-y: auto !important;
	overflow-x: hidden !important;
	margin-top: 4px !important;
	width: auto !important;
	min-width: 320px !important;
	animation: loqateSlideIn 0.2s ease-out !important;
	opacity: 1 !important;
	transform: translateY(0) !important;
}

/* Slide-in animation */
@keyframes loqateSlideIn {
	from {
		opacity: 0;
		transform: translateY(-8px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* Dropdown items */
.pca.pcalist .pcaitem {
	padding: 12px 16px !important;
	cursor: pointer !important;
	border-bottom: 1px solid #f0f0f0 !important;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
	background-color: #fff !important;
	white-space: normal !important;
	line-height: 1.5 !important;
	font-size: 14px !important;
}

.pca.pcalist .pcaitem:hover {
	background-color: #f8f9ff !important;
	transform: translateX(2px) !important;
	border-left: 3px solid #4f46e5 !important;
	padding-left: 13px !important;
}

.pca.pcalist .pcaitem.pcaselected {
	background-color: #eef2ff !important;
	border-left: 3px solid #4f46e5 !important;
	padding-left: 13px !important;
}

.pca.pcalist .pcaitem:last-child {
	border-bottom: none !important;
	border-radius: 0 0 8px 8px !important;
}

.pca.pcalist .pcaitem:first-child {
	border-radius: 8px 8px 0 0 !important;
}

/* Address descriptions */
.pca.pcalist .pcadescription {
	color: #6b7280 !important;
	font-size: 13px !important;
	margin-left: 8px !important;
	display: block !important;
	margin-top: 2px !important;
}

/* Autocomplete container */
.pcaautocomplete {
	position: absolute !important;
	z-index: 999999 !important;
}

.pcaautocomplete:empty {
	display: none !important;
}

/* Text containers */
.pcatext {
	background-color: #fff !important;
	border: 1px solid #ddd !important;
	border-radius: 8px !important;
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}

/* Custom scrollbar - Modern design */
.pca.pcalist::-webkit-scrollbar {
	width: 6px !important;
}

.pca.pcalist::-webkit-scrollbar-track {
	background: transparent !important;
}

.pca.pcalist::-webkit-scrollbar-thumb {
	background: #d1d5db !important;
	border-radius: 3px !important;
	transition: background 0.2s ease !important;
}

.pca.pcalist::-webkit-scrollbar-thumb:hover {
	background: #9ca3af !important;
}

/* Legacy dropdown styling (for compatibility) */
.pca-address-capture-dropdown {
	border: 1px solid #ccc;
	border-top: none;
	border-radius: 0 0 4px 4px;
	background-color: #fff;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	z-index: 1000;
	max-height: 250px;
	overflow-y: auto;
}

/* Style Loqate dropdown items */
.pca-address-capture-dropdown .pca-item {
	padding: 8px 12px;
	border-bottom: 1px solid #eee;
	cursor: pointer;
	transition: background-color 0.2s ease;
}

.pca-address-capture-dropdown .pca-item:hover {
	background-color: #f5f5f5;
}

.pca-address-capture-dropdown .pca-item.pca-selected {
	background-color: #e0e0e0;
}

/* ============================================================================
   Input Field Enhancements
   ============================================================================ */

/* Ensure form row is positioned so loader icon can be absolutely positioned inside */
.woocommerce form .form-row {
	position: relative;
}

.woocommerce form .form-row input[id^="billing_address"],
.woocommerce form .form-row input[id^="shipping_address"] {
	border-color: #d1d5db;
	transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.woocommerce form .form-row input[id^="billing_address"]:focus,
.woocommerce form .form-row input[id^="shipping_address"]:focus {
	border-color: #4f46e5;
	box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
	outline: none;
}

/* ============================================================================
   Field Feedback States (Loading, Success, Error)
   ============================================================================ */

/* Loading state - with visible spinner icon */
.loqate-loading input {
	border-color: #6366f1 !important;
	padding-right: 40px !important;
}

/* Loader spinner icon (positioned inside input field) */
.loqate-loader {
	position: absolute;
	right: 12px;
	top: 50%;
	transform: translateY(-50%);
	width: 20px;
	height: 20px;
	border: 3px solid #e5e7eb;
	border-top-color: #6366f1;
	border-radius: 50%;
	animation: loqateSpinning 0.8s linear infinite;
	pointer-events: none;
	z-index: 10;
}

@keyframes loqateSpinning {
	to {
		transform: translateY(-50%) rotate(360deg);
	}
}

/* Success state */
.loqate-success input {
	border-color: #10b981 !important;
	box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

/* Error state */
.loqate-error input {
	border-color: #ef4444 !important;
	box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

/* Feedback messages */
.loqate-feedback {
	font-size: 13px;
	margin-top: 6px;
	padding: 8px 12px;
	border-radius: 6px;
	animation: loqateFeedbackSlideIn 0.3s ease-out;
	line-height: 1.4;
}

@keyframes loqateFeedbackSlideIn {
	from {
		opacity: 0;
		transform: translateY(-4px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.loqate-feedback.loqate-loading {
	color: #4f46e5;
	background-color: #eef2ff;
	border-left: 3px solid #4f46e5;
}

.loqate-feedback.loqate-success {
	color: #065f46;
	background-color: #d1fae5;
	border-left: 3px solid #10b981;
}

.loqate-feedback.loqate-error {
	color: #991b1b;
	background-color: #fee2e2;
	border-left: 3px solid #ef4444;
}

/* ============================================================================
   Legacy Compatibility Styles
   ============================================================================ */

.pca-address-capture-dropdown {
	border: 1px solid #ddd;
	border-top: none;
	border-radius: 0 0 8px 8px;
	background-color: #fff;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
	z-index: 1000;
	max-height: 280px;
	overflow-y: auto;
}

.pca-address-capture-dropdown .pca-item {
	padding: 10px 16px;
	border-bottom: 1px solid #f0f0f0;
	cursor: pointer;
	transition: all 0.2s ease;
}

.pca-address-capture-dropdown .pca-item:hover,
.pca-address-capture-dropdown .pca-item.pca-selected {
	background-color: #f8f9ff;
}
CSS;
	}

	/**
	 * Check if Loqate integration is enabled
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get Loqate API key (for debugging or admin display)
	 *
	 * @return string API key (masked for security)
	 */
	public function get_masked_api_key(): string {
		if ( empty( $this->api_key ) ) {
			return '';
		}

		$visible_chars = 4;
		$key_length = strlen( $this->api_key );

		if ( $key_length <= $visible_chars ) {
			return str_repeat( '*', $key_length );
		}

		return substr( $this->api_key, 0, $visible_chars ) . str_repeat( '*', $key_length - $visible_chars );
	}

	/**
	 * Get status information for admin display
	 *
	 * @return array Status information
	 */
	public static function get_status(): array {
		$instance = self::get_instance();

		return array(
			'enabled'      => $instance->is_enabled(),
			'api_key_set'  => ! empty( $instance->api_key ),
			'masked_key'   => $instance->get_masked_api_key(),
			'woocommerce'  => class_exists( 'WooCommerce' ),
			'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
		);
	}

	/**
	 * Debug method to check Loqate initialization and checkout detection
	 * Can be called via WP-CLI: wp eval "RCF_Loqate_Address_Capture::debug_info();"
	 *
	 * @return void
	 */
	public static function debug_info(): void {
		echo "\n=== Loqate Debug Information ===\n\n";

		$instance = self::get_instance();

		// API Key Check
		echo "1. API Key Status:\n";
		$api_key_constant = defined( 'LOQATE_API_KEY' ) ? substr( LOQATE_API_KEY, 0, 4 ) . '****' : 'Not set';
		$api_key_option = get_option( 'rcf_loqate_api_key', '' );
		$api_key_option_masked = ! empty( $api_key_option ) ? substr( $api_key_option, 0, 4 ) . '****' : 'Not set';

		echo "   - LOQATE_API_KEY constant: $api_key_constant\n";
		echo "   - rcf_loqate_api_key option: $api_key_option_masked\n";
		echo "   - Instance has API key: " . ( ! empty( $instance->api_key ) ? 'YES' : 'NO' ) . "\n";
		echo "   - Instance enabled: " . ( $instance->enabled ? 'YES' : 'NO' ) . "\n\n";

		// Hook Registration Check
		echo "2. Hook Registration:\n";
		global $wp_filter;
		$hook_registered = false;
		if ( isset( $wp_filter['wp_enqueue_scripts'] ) ) {
			foreach ( $wp_filter['wp_enqueue_scripts']->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) ) {
						$obj = $callback['function'][0];
						$method = $callback['function'][1];
						if ( is_object( $obj ) && get_class( $obj ) === 'RCF_Loqate_Address_Capture' ) {
							echo "   - Hook registered at priority $priority: " . get_class( $obj ) . "::$method\n";
							$hook_registered = true;
						}
					}
				}
			}
		}
		if ( ! $hook_registered ) {
			echo "   - NO HOOK REGISTERED (wp_enqueue_scripts)\n";
		}
		echo "\n";

		// Checkout Detection Check
		echo "3. Checkout Detection:\n";
		echo "   - is_checkout() available: " . ( function_exists( 'is_checkout' ) ? 'YES' : 'NO' ) . "\n";
		if ( function_exists( 'is_checkout' ) ) {
			echo "   - is_checkout() result: " . ( is_checkout() ? 'TRUE' : 'FALSE' ) . "\n";
		}
		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_id = wc_get_page_id( 'checkout' );
			echo "   - Checkout page ID: $checkout_id\n";
			echo "   - Current page ID: " . get_the_ID() . "\n";
		}
		global $wp, $wp_query;
		echo "   - Current URL: " . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'N/A' ) . "\n";
		if ( isset( $wp_query->queried_object ) ) {
			$obj = $wp_query->queried_object;
			echo "   - Queried object post_name: " . ( isset( $obj->post_name ) ? $obj->post_name : 'N/A' ) . "\n";
		}
		echo "\n";

		// User Status
		echo "4. User Status:\n";
		echo "   - Logged in: " . ( is_user_logged_in() ? 'YES' : 'NO' ) . "\n";
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			echo "   - User ID: " . $user->ID . "\n";
			echo "   - User roles: " . implode( ', ', $user->roles ) . "\n";
		}
		echo "\n";

		// WooCommerce Status
		echo "5. WooCommerce Status:\n";
		echo "   - WooCommerce active: " . ( class_exists( 'WooCommerce' ) ? 'YES' : 'NO' ) . "\n";
		if ( function_exists( 'wc_get_checkout_url' ) ) {
			echo "   - Checkout URL: " . wc_get_checkout_url() . "\n";
		}
		echo "\n";

		echo "=== End Debug Information ===\n\n";
	}
}
