<?php
/**
 * JetEngine Profile Builder Flat URLs
 *
 * Removes base path from JetEngine Profile Builder subpage URLs.
 * Converts /console/academy/ to /academy/ while maintaining functionality.
 *
 * @package    RCP_Content_Filter
 * @subpackage Includes
 * @since      1.0.58
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RCF_JetEngine_Flat_URLs
 *
 * Implements flat URL structure for JetEngine Profile Builder subpages.
 *
 * FIXED VERSION - Properly handles WordPress post loading and query vars.
 */
class RCF_JetEngine_Flat_URLs {

	/**
	 * Singleton instance
	 *
	 * @var RCF_JetEngine_Flat_URLs|null
	 */
	private static ?self $instance = null;

	/**
	 * Base page ID (e.g., the "Console" page)
	 *
	 * @var int
	 */
	private int $base_page_id = 0;

	/**
	 * Base page slug (e.g., "console")
	 *
	 * @var string
	 */
	private string $base_slug = '';

	/**
	 * Cached subpage slugs
	 *
	 * @var array
	 */
	private array $subpage_slugs = array();

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
		// Only initialize if JetEngine is active
		if ( ! $this->is_jetengine_active() ) {
			return;
		}

		// Register custom query var EARLY (before rewrite rules)
		add_filter( 'query_vars', array( $this, 'register_query_vars' ), 1 );

		// Hook into JetEngine Profile Builder rewrite rules
		add_filter( 'jet-engine/profile-builder/rewrite-rules', array( $this, 'add_flat_rewrite_rules' ), 10, 2 );

		// Modify subpage URLs in Profile Menu widget
		add_filter( 'jet-engine/profile-builder/subpage-url', array( $this, 'remove_base_from_url' ), 10, 3 );

		// CRITICAL: Ensure WordPress loads the correct page for our rewrite rules
		add_action( 'pre_get_posts', array( $this, 'fix_main_query' ), 1 );

		// Flush rewrite rules on activation
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Check if JetEngine is active and Profile Builder is available
	 *
	 * @return bool
	 */
	private function is_jetengine_active(): bool {
		return class_exists( 'Jet_Engine' ) && function_exists( 'jet_engine' );
	}

	/**
	 * Register custom query var for JetEngine account pages
	 *
	 * This is CRITICAL - WordPress needs to know about the jet_account_page query var
	 * before our rewrite rules try to use it.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function register_query_vars( array $vars ): array {
		// Only add if not already present
		if ( ! in_array( 'jet_account_page', $vars, true ) ) {
			$vars[] = 'jet_account_page';
		}
		return $vars;
	}

	/**
	 * Add flat rewrite rules for JetEngine subpages
	 *
	 * @param array $rules  Existing rewrite rules.
	 * @param array $config JetEngine Profile Builder configuration.
	 * @return array Modified rewrite rules.
	 */
	public function add_flat_rewrite_rules( array $rules, array $config ): array {
		// Ensure we have configuration data
		if ( empty( $config ) || empty( $config['account_page_id'] ) ) {
			return $rules;
		}

		// Get base page information
		$this->base_page_id = absint( $config['account_page_id'] );
		$base_page          = get_post( $this->base_page_id );

		if ( ! $base_page ) {
			return $rules;
		}

		$this->base_slug = $base_page->post_name;

		// Get all subpages from configuration
		$subpages = ! empty( $config['subpages'] ) ? $config['subpages'] : array();

		if ( empty( $subpages ) ) {
			return $rules;
		}

		// Cache subpage slugs for later use
		$this->subpage_slugs = array();

		// Add flat URL rewrite rules for each subpage
		foreach ( $subpages as $subpage ) {
			if ( empty( $subpage['slug'] ) ) {
				continue;
			}

			$subpage_slug = sanitize_title( $subpage['slug'] );
			$this->subpage_slugs[] = $subpage_slug;

			// IMPROVED: Use pagename instead of page_id for better WordPress compatibility
			// This ensures WordPress properly loads the post object
			$rules[ "^{$subpage_slug}/?$" ] = "index.php?pagename={$this->base_slug}&jet_account_page={$subpage_slug}";
			$rules[ "^{$subpage_slug}/page/([0-9]+)/?$" ] = "index.php?pagename={$this->base_slug}&jet_account_page={$subpage_slug}&paged=\$matches[1]";
		}

		return $rules;
	}

	/**
	 * Fix main query to load the correct page
	 *
	 * CRITICAL FIX: This ensures WordPress properly loads the base page post object
	 * when a flat URL is accessed. Without this, $wp_query->post is null.
	 *
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function fix_main_query( $query ) {
		// Only process main query on frontend
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		// Check if this is a JetEngine account page request
		$jet_account_page = get_query_var( 'jet_account_page' );

		if ( empty( $jet_account_page ) ) {
			return;
		}

		// Verify this is a valid subpage slug
		if ( ! in_array( $jet_account_page, $this->subpage_slugs, true ) ) {
			return;
		}

		// Get JetEngine Profile Builder config to find base page
		if ( ! function_exists( 'jet_engine' ) || ! jet_engine()->modules ) {
			return;
		}

		$profile_builder = jet_engine()->modules->get_module( 'profile-builder' );
		if ( ! $profile_builder || ! method_exists( $profile_builder, 'get_config' ) ) {
			return;
		}

		$config = $profile_builder->get_config();
		if ( empty( $config['account_page_id'] ) ) {
			return;
		}

		$base_page_id = absint( $config['account_page_id'] );

		// CRITICAL: Force WordPress to load the base page
		// This ensures $wp_query->post is set before JetEngine tries to access it
		$query->set( 'page_id', $base_page_id );
		$query->set( 'post_type', 'page' );
		$query->is_page = true;
		$query->is_singular = true;
		$query->is_single = false;
		$query->is_home = false;
		$query->is_archive = false;
	}

	/**
	 * Remove base path from JetEngine Profile Builder subpage URLs
	 *
	 * This filter modifies the URLs generated by the Profile Menu widget.
	 *
	 * @param string $url     The original URL with base path.
	 * @param array  $subpage Subpage configuration.
	 * @param array  $config  Full Profile Builder configuration.
	 * @return string Modified URL without base path.
	 */
	public function remove_base_from_url( string $url, array $subpage, array $config ): string {
		// Ensure we have configuration
		if ( empty( $config['account_page_id'] ) || empty( $subpage['slug'] ) ) {
			return $url;
		}

		// Get base page information if not already set
		if ( empty( $this->base_page_id ) ) {
			$this->base_page_id = absint( $config['account_page_id'] );
			$base_page          = get_post( $this->base_page_id );

			if ( $base_page ) {
				$this->base_slug = $base_page->post_name;
			}
		}

		// If we don't have a base slug, return original URL
		if ( empty( $this->base_slug ) ) {
			return $url;
		}

		$subpage_slug = sanitize_title( $subpage['slug'] );

		// Remove the base slug from the URL
		// Convert: https://example.com/console/academy/ → https://example.com/academy/
		$pattern = '#/' . preg_quote( $this->base_slug, '#' ) . '/' . preg_quote( $subpage_slug, '#' ) . '/?#';
		$url     = preg_replace( $pattern, '/' . $subpage_slug . '/', $url );

		return $url;
	}

	/**
	 * Flush rewrite rules if needed
	 *
	 * Only flushes once per plugin version update to avoid performance issues.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		// Check if we've already flushed for this version
		$flushed_version = get_option( 'rcf_jetengine_rewrite_flushed' );

		if ( $flushed_version === RCP_FILTER_VERSION ) {
			return;
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Mark as flushed for this version
		update_option( 'rcf_jetengine_rewrite_flushed', RCP_FILTER_VERSION );
	}

	/**
	 * Get current base page ID
	 *
	 * @return int
	 */
	public function get_base_page_id(): int {
		return $this->base_page_id;
	}

	/**
	 * Get current base slug
	 *
	 * @return string
	 */
	public function get_base_slug(): string {
		return $this->base_slug;
	}

	/**
	 * Get cached subpage slugs
	 *
	 * @return array
	 */
	public function get_subpage_slugs(): array {
		return $this->subpage_slugs;
	}
}
