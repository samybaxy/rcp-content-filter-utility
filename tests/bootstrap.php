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

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed_args =& $args;
		} else {
			parse_str( $args, $parsed_args );
		}

		if ( is_array( $defaults ) && $defaults ) {
			return array_merge( $defaults, $parsed_args );
		}
		return $parsed_args;
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}
// Hook tracking for tests
global $_test_hooks;
$_test_hooks = array(
	'actions' => array(),
	'filters' => array()
);

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $_test_hooks;
		if ( ! isset( $_test_hooks['actions'][ $hook ] ) ) {
			$_test_hooks['actions'][ $hook ] = array();
		}
		$_test_hooks['actions'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'accepted_args' => $accepted_args
		);
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $_test_hooks;
		if ( ! isset( $_test_hooks['filters'][ $hook ] ) ) {
			$_test_hooks['filters'][ $hook ] = array();
		}
		$_test_hooks['filters'][ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'accepted_args' => $accepted_args
		);
		return true;
	}
}

if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook, $callback = false ) {
		global $_test_hooks;
		if ( ! isset( $_test_hooks['actions'][ $hook ] ) ) {
			return false;
		}
		if ( $callback === false ) {
			return ! empty( $_test_hooks['actions'][ $hook ] );
		}
		foreach ( $_test_hooks['actions'][ $hook ] as $action ) {
			if ( $action['callback'] === $callback ) {
				return $action['priority'];
			}
		}
		return false;
	}
}

if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook, $callback = false ) {
		global $_test_hooks;
		if ( ! isset( $_test_hooks['filters'][ $hook ] ) ) {
			return false;
		}
		if ( $callback === false ) {
			return ! empty( $_test_hooks['filters'][ $hook ] );
		}
		foreach ( $_test_hooks['filters'][ $hook ] as $filter ) {
			if ( $filter['callback'] === $callback ) {
				return $filter['priority'];
			}
		}
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
		global $_test_hooks;
		if ( isset( $_test_hooks['filters'][ $hook ] ) ) {
			foreach ( $_test_hooks['filters'][ $hook ] as $filter ) {
				if ( is_callable( $filter['callback'] ) ) {
					$value = call_user_func_array( $filter['callback'], array_merge( array( $value ), $args ) );
				}
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		global $_test_hooks;
		if ( isset( $_test_hooks['actions'][ $hook ] ) ) {
			foreach ( $_test_hooks['actions'][ $hook ] as $action ) {
				if ( is_callable( $action['callback'] ) ) {
					call_user_func_array( $action['callback'], $args );
				}
			}
		}
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

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( is_serialized( $data ) ) {
			return @unserialize( trim( $data ) );
		}
		return $data;
	}
}

if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) {
						return false;
					}
				} elseif ( false === strpos( $data, '"' ) ) {
					return false;
				}
			case 'a':
			case 'O':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
		}
		return false;
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
		global $current_screen;
		if ( isset( $current_screen ) && is_object( $current_screen ) ) {
			return true; // If screen is set, we're in admin
		}
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

if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return false; // Not logged in by default in tests
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0; // No user by default in tests
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		return (object) array( 'ID' => 0, 'user_login' => '', 'roles' => array() );
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
		global $_test_posts;
		if ( ! isset( $_test_posts ) ) {
			return array();
		}

		$results = array();
		foreach ( $_test_posts as $post ) {
			$match = true;

			// Filter by post_type
			if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) {
				$match = false;
			}

			// Filter by name (slug)
			if ( isset( $args['name'] ) && $post->post_name !== $args['name'] ) {
				$match = false;
			}

			// Filter by post_status
			if ( isset( $args['post_status'] ) && $post->post_status !== $args['post_status'] ) {
				$match = false;
			}

			if ( $match ) {
				$results[] = $post;
			}
		}

		// Apply limit
		if ( isset( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
			$results = array_slice( $results, 0, $args['posts_per_page'] );
		}

		return $results;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $post_id, $force_delete = false ) {
		global $_test_posts, $_test_post_meta;
		foreach ( $_test_posts as $key => $post ) {
			if ( $post->ID === $post_id ) {
				unset( $_test_posts[ $key ] );
				if ( isset( $_test_post_meta[ $post_id ] ) ) {
					unset( $_test_post_meta[ $post_id ] );
				}
				return $post;
			}
		}
		return false;
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
		public $is_archive = false;
		public $is_home = false;
		public $is_search = false;

		public function __construct( $args = array() ) {
			$this->query_vars = $args;
			// Set is_admin based on global is_admin() function
			$this->is_admin = is_admin();
		}

		public function get( $var, $default = false ) {
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

		public function is_archive() {
			return $this->is_archive;
		}

		public function is_home() {
			return $this->is_home;
		}

		public function is_search() {
			return $this->is_search;
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
			global $_test_options;

			// Check if this is an options query
			if ( strpos( $query, 'SELECT option_name, option_value' ) !== false ) {
				// Extract option names from the query
				// Query format: SELECT option_name, option_value FROM wp_options WHERE option_name IN ('opt1','opt2',...)
				preg_match_all( "/'([^']+)'/", $query, $matches );

				if ( ! empty( $matches[1] ) ) {
					$results = array();
					foreach ( $matches[1] as $option_name ) {
						if ( isset( $_test_options[ $option_name ] ) ) {
							$obj = new stdClass();
							$obj->option_name = $option_name;
							$obj->option_value = $_test_options[ $option_name ];

							// OBJECT_K means keyed by first column (option_name)
							if ( $output === OBJECT_K ) {
								$results[ $option_name ] = $obj;
							} else {
								$results[] = $obj;
							}
						}
					}
					return $results;
				}
			}

			// Return empty results for other queries
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

// Initialize test options, transients, post meta, and posts storage
global $_test_options, $_test_transients, $_test_post_meta, $_test_posts;
$_test_options = array();
$_test_transients = array();
$_test_post_meta = array();
$_test_posts = array();

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

// Mock LearnPress class for testing
if ( ! class_exists( 'LearnPress' ) ) {
	class LearnPress {
		public static function instance() {
			return new self();
		}
	}
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

			// Clear test options, transients, post meta, posts, and hooks
			global $_test_options, $_test_transients, $_test_post_meta, $_test_posts, $_test_hooks;
			$_test_options = array();
			$_test_transients = array();
			$_test_post_meta = array();
			$_test_posts = array();
			$_test_hooks = array(
				'actions' => array(),
				'filters' => array()
			);

			// Re-initialize plugins after clearing hooks
			// Call init() directly instead of via action since we just cleared all hooks
			if ( class_exists( 'RCP_Content_Filter' ) ) {
				$plugin = RCP_Content_Filter::get_instance();
				$plugin->init();
			}
			if ( class_exists( 'RCF_Loqate_Address_Capture' ) ) {
				// Reset singleton and re-create to re-register hooks
				$reflection = new ReflectionClass( 'RCF_Loqate_Address_Capture' );
				$instance_property = $reflection->getProperty( 'instance' );
				$instance_property->setAccessible( true );
				$instance_property->setValue( null, null );
				$loqate = RCF_Loqate_Address_Capture::get_instance();
			}
			if ( class_exists( 'RCF_LearnPress_Elementor_Fix' ) ) {
				// Reset singleton and re-create to re-register hooks
				$reflection = new ReflectionClass( 'RCF_LearnPress_Elementor_Fix' );
				$instance_property = $reflection->getProperty( 'instance' );
				$instance_property->setAccessible( true );
				$instance_property->setValue( null, null );
				$learnpress_fix = RCF_LearnPress_Elementor_Fix::get_instance();
			}

			// Initialize factory mock with callable objects
			$this->factory = new class {
				public $post;
				public $user;
				public $term;
				private static $next_post_id = 100;

				public function __construct() {
					$this->post = new class {
						private static $next_id = 100;

						public function create( $args = array() ) {
							global $_test_posts;
							$post_id = self::$next_id++;

							$post = (object) array(
								'ID' => $post_id,
								'post_title' => $args['post_title'] ?? 'Test Post',
								'post_name' => $args['post_name'] ?? 'test-post-' . $post_id,
								'post_type' => $args['post_type'] ?? 'post',
								'post_status' => $args['post_status'] ?? 'publish',
								'post_content' => $args['post_content'] ?? '',
							);

							$_test_posts[] = $post;
							return $post_id;
						}
						public function create_and_get( $args = array() ) {
							global $_test_posts;
							$post_id = $this->create( $args );
							foreach ( $_test_posts as $post ) {
								if ( $post->ID === $post_id ) {
									return $post;
								}
							}
							return null;
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
			global $_test_options, $_test_transients, $_test_post_meta, $_test_posts, $_test_hooks;
			$_test_options = array();
			$_test_transients = array();
			$_test_post_meta = array();
			$_test_posts = array();
			$_test_hooks = array(
				'actions' => array(),
				'filters' => array()
			);
			parent::tearDown();
		}
	}
}
