<?php
/**
 * PHPUnit Bootstrap
 *
 * Minimal bootstrap with WordPress function mocks for unit testing
 *
 * @package RCP_Content_Filter
 */

// Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

define( 'WPINC', 'wp-includes' );
define( 'WP_CONTENT_DIR', dirname( __DIR__, 2 ) );
define( 'WP_PLUGIN_DIR', dirname( __DIR__, 1 ) );
define( 'WP_DEBUG', true );

// Plugin constants
if ( ! defined( 'RCP_FILTER_VERSION' ) ) {
	define( 'RCP_FILTER_VERSION', '1.0.39' );
}
if ( ! defined( 'RCP_FILTER_PLUGIN_DIR' ) ) {
	define( 'RCP_FILTER_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'RCP_FILTER_PLUGIN_URL' ) ) {
	define( 'RCP_FILTER_PLUGIN_URL', 'http://localhost/wp-content/plugins/rcp-content-filter-utility/' );
}

// Mock WordPress functions needed for tests
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://localhost/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		$file = wp_normalize_path( $file );
		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$file = preg_replace( '#^' . preg_quote( $plugin_dir, '#' ) . '/|^#', '', $file );
		return trim( $file, '/' );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}
		return $path;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook ) {
		return false;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook ) {
		return false;
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $callback, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $hook, $callback, $priority = 10 ) {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		return null;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_test_options;
		return $_test_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $_test_options;
		$_test_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $_test_options;
		unset( $_test_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() {
		return true;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true;
	}
}

if ( ! function_exists( 'class_exists' ) ) {
	function class_exists( $class, $autoload = true ) {
		return \class_exists( $class, $autoload );
	}
}

// Mock Restrict Content Pro functions for testing
if ( ! function_exists( 'rcp_user_can_access' ) ) {
	function rcp_user_can_access( $user_id = 0, $post_id = 0 ) {
		return true; // Mock as accessible for tests
	}
}

if ( ! function_exists( 'rcp_is_restricted_content' ) ) {
	function rcp_is_restricted_content( $post_id = 0 ) {
		return false; // Mock as not restricted for tests
	}
}

if ( ! function_exists( 'rcp_get_subscription_levels' ) ) {
	function rcp_get_subscription_levels( $status = 'active' ) {
		return array(); // Return empty array for tests
	}
}

if ( ! function_exists( 'rcp_get_membership_levels' ) ) {
	function rcp_get_membership_levels() {
		return array(); // Return empty array for tests
	}
}

// Mock WP_Query class
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $query_vars = array();
		public $is_admin = false;

		public function __construct( $args = array() ) {
			$this->query_vars = $args;
		}

		public function get( $var, $default = '' ) {
			return $this->query_vars[ $var ] ?? $default;
		}

		public function set( $var, $value ) {
			$this->query_vars[ $var ] = $value;
		}

		public function is_admin() {
			return $this->is_admin;
		}
	}
}

// Initialize test options storage
global $_test_options;
$_test_options = array();

// Load plugin files
require_once dirname( __DIR__ ) . '/rcp-content-filter-utility.php';

// Manually initialize plugin classes (since plugins_loaded won't fire in tests)
if ( class_exists( 'RCP_Content_Filter' ) ) {
	RCP_Content_Filter::get_instance();

	// Create class alias for tests (tests use RCP_Content_Filter_Utility)
	if ( ! class_exists( 'RCP_Content_Filter_Utility' ) ) {
		class_alias( 'RCP_Content_Filter', 'RCP_Content_Filter_Utility' );
	}
}

// Load and initialize Loqate class
if ( file_exists( dirname( __DIR__ ) . '/includes/class-loqate-address-capture.php' ) ) {
	require_once dirname( __DIR__ ) . '/includes/class-loqate-address-capture.php';
}

// Load and initialize LearnPress fix class
if ( file_exists( dirname( __DIR__ ) . '/includes/class-learnpress-elementor-fix.php' ) ) {
	require_once dirname( __DIR__ ) . '/includes/class-learnpress-elementor-fix.php';
}

// Base test case class for WordPress tests
if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	/**
	 * Base test case class
	 */
	abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {

		/**
		 * Set up test
		 */
		public function setUp(): void {
			parent::setUp();
			// Clear test options
			global $_test_options;
			$_test_options = array();
		}

		/**
		 * Tear down test
		 */
		public function tearDown(): void {
			// Clean up
			global $_test_options;
			$_test_options = array();
			parent::tearDown();
		}
	}
}
