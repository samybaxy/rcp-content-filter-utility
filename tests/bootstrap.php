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

// WordPress database output constants
define( 'OBJECT', 'OBJECT' );
define( 'OBJECT_K', 'OBJECT_K' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );

// WordPress time constants (in seconds)
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'YEAR_IN_SECONDS', 31536000 );

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

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $hook, $priority = false ) {
		return true;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		global $_test_post_meta;
		if ( ! isset( $_test_post_meta[ $post_id ] ) ) {
			$_test_post_meta[ $post_id ] = array();
		}
		$_test_post_meta[ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $meta_key = '', $single = false ) {
		global $_test_post_meta;
		if ( ! isset( $_test_post_meta[ $post_id ] ) ) {
			return $single ? '' : array();
		}
		if ( $meta_key === '' ) {
			return $_test_post_meta[ $post_id ];
		}
		$value = $_test_post_meta[ $post_id ][ $meta_key ] ?? ( $single ? '' : array() );
		return $value;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		global $_test_post_meta;
		if ( isset( $_test_post_meta[ $post_id ][ $meta_key ] ) ) {
			unset( $_test_post_meta[ $post_id ][ $meta_key ] );
		}
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

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) {
		return false;
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout() {
		return false;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true;
	}
}

if ( ! function_exists( 'set_current_screen' ) ) {
	function set_current_screen( $screen_id ) {
		global $current_screen;
		$current_screen = (object) array( 'id' => $screen_id );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $_test_transients;
		$_test_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		global $_test_transients;
		return $_test_transients[ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		global $_test_transients;
		unset( $_test_transients[ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		return array(); // Return empty array for tests
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return true; // Always valid for tests
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test_nonce" />';
		if ( $echo ) {
			echo $html;
		}
		return $html;
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
		public $is_main_query = true;

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

		public function is_main_query() {
			return $this->is_main_query;
		}
	}
}

// Mock $wpdb global
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	class wpdb_mock {
		public $options;
		public $prefix = 'wp_';

		public function __construct() {
			$this->options = 'wp_options';
		}

		public function prepare( $query, ...$args ) {
			return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
		}

		public function get_results( $query, $output = OBJECT ) {
			// Return empty results for options queries
			return array();
		}

		public function get_var( $query, $x = 0, $y = 0 ) {
			return null;
		}

		public function get_row( $query, $output = OBJECT, $y = 0 ) {
			return null;
		}

		public function query( $query ) {
			return true;
		}

		public function insert( $table, $data, $format = null ) {
			return 1;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			return 1;
		}
	}

	$GLOBALS['wpdb'] = new wpdb_mock();
	global $wpdb;
}

// Initialize test options, transients, and post meta storage
global $_test_options, $_test_transients, $_test_post_meta;
$_test_options = array();
$_test_transients = array();
$_test_post_meta = array();

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
		 * WordPress test factory for creating test data
		 *
		 * @var object
		 */
		protected $factory;

		/**
		 * Set up test
		 */
		public function setUp(): void {
			parent::setUp();

			// Clear test options, transients, and post meta
			global $_test_options, $_test_transients, $_test_post_meta;
			$_test_options = array();
			$_test_transients = array();
			$_test_post_meta = array();

			// Initialize factory mock with callable objects
			$this->factory = new class {
				public $post;
				public $user;
				public $term;

				public function __construct() {
					$this->post = new class {
						public function create( $args = array() ) {
							return 123; // Mock post ID
						}
						public function create_and_get( $args = array() ) {
							return (object) array( 'ID' => 123, 'post_title' => 'Test Post' );
						}
					};

					$this->user = new class {
						public function create( $args = array() ) {
							return 1; // Mock user ID
						}
					};

					$this->term = new class {
						public function create( $args = array() ) {
							return 456; // Mock term ID
						}
					};
				}
			};
		}

		/**
		 * Tear down test
		 */
		public function tearDown(): void {
			// Clean up
			global $_test_options, $_test_transients, $_test_post_meta;
			$_test_options = array();
			$_test_transients = array();
			$_test_post_meta = array();
			parent::tearDown();
		}
	}
}
