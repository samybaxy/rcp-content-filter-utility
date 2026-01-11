<?php
/**
 * JetEngine Profile Builder Flat URLs - SAFE VERSION
 *
 * Removes base path from JetEngine Profile Builder subpage URLs.
 * Converts /console/academy/ to /academy/ while maintaining functionality.
 *
 * @package    RCP_Content_Filter
 * @subpackage Includes
 * @since      1.0.60
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RCF_JetEngine_Flat_URLs
 *
 * ULTRA-SAFE implementation with extensive error handling.
 */
class RCF_JetEngine_Flat_URLs {

	/**
	 * Singleton instance
	 *
	 * @var RCF_JetEngine_Flat_URLs|null
	 */
	private static ?self $instance = null;

	/**
	 * Base page ID
	 *
	 * @var int
	 */
	private int $base_page_id = 0;

	/**
	 * Base page slug
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
	 * Whether initialization succeeded
	 *
	 * @var bool
	 */
	private bool $initialized = false;

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
		// Defensive: Only initialize if JetEngine is active
		if ( ! $this->is_jetengine_active() ) {
			return;
		}

		// Defensive: Only initialize if Profile Builder module exists
		if ( ! $this->is_profile_builder_active() ) {
			return;
		}

		// Register hooks
		try {
			// Register query var EARLY
			add_filter( 'query_vars', array( $this, 'register_query_vars' ), 1 );

			// Hook into JetEngine Profile Builder
			add_filter( 'jet-engine/profile-builder/rewrite-rules', array( $this, 'add_flat_rewrite_rules' ), 10, 2 );
			add_filter( 'jet-engine/profile-builder/subpage-url', array( $this, 'remove_base_from_url' ), 10, 3 );

			// DON'T modify main query - let WordPress handle it naturally
			// This was causing the crashes

			// Flush rewrite rules once
			add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );

			$this->initialized = true;
		} catch ( Exception $e ) {
			// Silently fail - don't break the site
			$this->initialized = false;
		}
	}

	/**
	 * Check if JetEngine is active
	 *
	 * @return bool
	 */
	private function is_jetengine_active(): bool {
		return class_exists( 'Jet_Engine' ) && function_exists( 'jet_engine' );
	}

	/**
	 * Check if Profile Builder module is active
	 *
	 * @return bool
	 */
	private function is_profile_builder_active(): bool {
		try {
			if ( ! function_exists( 'jet_engine' ) ) {
				return false;
			}

			$jet_engine = jet_engine();
			if ( ! $jet_engine || ! isset( $jet_engine->modules ) ) {
				return false;
			}

			$profile_builder = $jet_engine->modules->get_module( 'profile-builder' );
			return ! empty( $profile_builder );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register query var
	 *
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public function register_query_vars( array $vars ): array {
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
		// Defensive: Validate config
		if ( empty( $config ) || empty( $config['account_page_id'] ) ) {
			return $rules;
		}

		try {
			// Get base page information
			$this->base_page_id = absint( $config['account_page_id'] );
			$base_page          = get_post( $this->base_page_id );

			if ( ! $base_page || $base_page->post_status !== 'publish' ) {
				return $rules;
			}

			$this->base_slug = $base_page->post_name;

			// Get subpages
			$subpages = ! empty( $config['subpages'] ) ? $config['subpages'] : array();

			if ( empty( $subpages ) || ! is_array( $subpages ) ) {
				return $rules;
			}

			// Cache subpage slugs
			$this->subpage_slugs = array();

			// Add flat URL rewrite rules for each subpage
			foreach ( $subpages as $subpage ) {
				if ( empty( $subpage['slug'] ) ) {
					continue;
				}

				$subpage_slug = sanitize_title( $subpage['slug'] );
				$this->subpage_slugs[] = $subpage_slug;

				// Create rewrite rules that WordPress can handle natively
				// Use the original console/subpage pattern as the target
				$rules[ "^{$subpage_slug}/?$" ] = "index.php?pagename={$this->base_slug}/{$subpage_slug}";
				$rules[ "^{$subpage_slug}/page/([0-9]+)/?$" ] = "index.php?pagename={$this->base_slug}/{$subpage_slug}&paged=\$matches[1]";
			}

			return $rules;
		} catch ( Exception $e ) {
			// Defensive: If anything fails, return original rules
			return $rules;
		}
	}

	/**
	 * Remove base path from subpage URLs in Profile Menu
	 *
	 * @param string $url     The original URL.
	 * @param array  $subpage Subpage configuration.
	 * @param array  $config  Full configuration.
	 * @return string Modified URL.
	 */
	public function remove_base_from_url( string $url, array $subpage, array $config ): string {
		try {
			// Defensive: Validate inputs
			if ( empty( $config['account_page_id'] ) || empty( $subpage['slug'] ) ) {
				return $url;
			}

			// Get base page info if not already set
			if ( empty( $this->base_slug ) ) {
				$this->base_page_id = absint( $config['account_page_id'] );
				$base_page          = get_post( $this->base_page_id );

				if ( $base_page ) {
					$this->base_slug = $base_page->post_name;
				}
			}

			// Defensive: If no base slug, return original
			if ( empty( $this->base_slug ) ) {
				return $url;
			}

			$subpage_slug = sanitize_title( $subpage['slug'] );

			// Remove base slug from URL
			$pattern = '#/' . preg_quote( $this->base_slug, '#' ) . '/' . preg_quote( $subpage_slug, '#' ) . '/?#';
			$new_url = preg_replace( $pattern, '/' . $subpage_slug . '/', $url );

			// Defensive: If regex failed, return original
			return $new_url !== null ? $new_url : $url;

		} catch ( Exception $e ) {
			// Defensive: Return original URL if anything fails
			return $url;
		}
	}

	/**
	 * Flush rewrite rules once per version
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		try {
			$flushed_version = get_option( 'rcf_jetengine_rewrite_flushed' );

			if ( $flushed_version === RCP_FILTER_VERSION ) {
				return;
			}

			flush_rewrite_rules();
			update_option( 'rcf_jetengine_rewrite_flushed', RCP_FILTER_VERSION );
		} catch ( Exception $e ) {
			// Silently fail
		}
	}

	/**
	 * Check if initialized successfully
	 *
	 * @return bool
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}

	/**
	 * Get base page ID
	 *
	 * @return int
	 */
	public function get_base_page_id(): int {
		return $this->base_page_id;
	}

	/**
	 * Get base slug
	 *
	 * @return string
	 */
	public function get_base_slug(): string {
		return $this->base_slug;
	}

	/**
	 * Get subpage slugs
	 *
	 * @return array
	 */
	public function get_subpage_slugs(): array {
		return $this->subpage_slugs;
	}
}
