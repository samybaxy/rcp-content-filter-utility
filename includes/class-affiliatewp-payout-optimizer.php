<?php
/**
 * AffiliateWP Payout Preview Performance Optimizer
 *
 * Fixes timeout issues with large datasets (many affiliates, long date ranges) by:
 * 1. Batch-loading affiliate names before rendering (eliminates expensive JOINs to wp_users)
 * 2. Pre-warming WordPress object caches to prevent N+1 query patterns
 * 3. Using a single optimized query to get unique affiliate IDs
 *
 * Performance Impact:
 * - Reduces affiliate name lookups from N individual queries to 1 batch query
 * - Prevents wp_users JOIN on every affiliate (the main bottleneck)
 * - Handles thousands of affiliates without timeout
 *
 * @package    RCP_Content_Filter
 * @subpackage Includes
 * @since      1.0.55
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RCF_AffiliateWP_Payout_Optimizer
 *
 * Optimizes the AffiliateWP payout preview page to prevent timeouts
 * with large datasets (many affiliates, long date ranges).
 */
class RCF_AffiliateWP_Payout_Optimizer {

	/**
	 * Singleton instance
	 *
	 * @var RCF_AffiliateWP_Payout_Optimizer|null
	 */
	private static ?self $instance = null;

	/**
	 * Tracks whether caches have been pre-warmed
	 *
	 * @var bool
	 */
	private bool $caches_warmed = false;

	/**
	 * Number of affiliates that were cached
	 *
	 * @var int
	 */
	private int $cached_count = 0;

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
		// Hook into the payout preview page load (early priority to pre-warm caches)
		// Use priority 5 to run before most other admin_init hooks
		add_action( 'admin_init', array( $this, 'maybe_optimize_payout_preview' ), 5 );
	}

	/**
	 * Check if we're on the payout preview page and optimize if needed
	 *
	 * @return void
	 */
	public function maybe_optimize_payout_preview(): void {
		// Verify AffiliateWP is loaded and available
		if ( ! function_exists( 'affiliate_wp' ) || ! affiliate_wp() ) {
			return;
		}

		// Check if we're on the AffiliateWP payouts page with preview action
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only optimization, no data modification
		if ( ! isset( $_GET['page'] ) || 'affiliate-wp-payouts' !== sanitize_key( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only optimization, no data modification
		if ( ! isset( $_GET['action'] ) || 'preview_payout' !== sanitize_key( $_GET['action'] ) ) {
			return;
		}

		// Verify user has permission to view payouts
		if ( ! current_user_can( 'manage_payouts' ) ) {
			return;
		}

		// Get the date range and affiliate parameters
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only optimization
		$start_raw = ! empty( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only optimization
		$end_raw = ! empty( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only optimization
		$user_name = ! empty( $_REQUEST['user_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_name'] ) ) : '';

		// Convert dates from MM/DD/YYYY (form format) to YYYY-MM-DD (MySQL format)
		$start = $this->convert_date_to_mysql( $start_raw );
		$end   = $this->convert_date_to_mysql( $end_raw );

		// Determine if we're filtering by a specific affiliate
		$affiliate_id = 0;
		if ( $user_name ) {
			$affiliate = affwp_get_affiliate( $user_name );
			if ( $affiliate && isset( $affiliate->ID ) ) {
				$affiliate_id = absint( $affiliate->ID );
			}
		}

		// Log that we detected the preview page
		if ( $this->should_log() ) {
			error_log( sprintf(
				'[AffiliateWP Payout Optimizer] Detected preview_payout page - from: %s, to: %s, affiliate: %s',
				$start ?: '(all dates)',
				$end ?: '(all dates)',
				$affiliate_id > 0 ? $affiliate_id : '(all affiliates)'
			) );
		}

		// Pre-warm affiliate caches BEFORE the preview.php file loads
		$this->prewarm_caches_for_date_range( $start, $end, $affiliate_id );

		// Mark as warmed to prevent duplicate work
		$this->caches_warmed = true;
	}

	/**
	 * Pre-warm affiliate caches for the given date range
	 *
	 * This eliminates N+1 queries by batch-loading all affiliate data upfront.
	 *
	 * @param string $start        Start date (Y-m-d format or empty)
	 * @param string $end          End date (Y-m-d format or empty)
	 * @param int    $affiliate_id Specific affiliate ID or 0 for all
	 * @return void
	 */
	private function prewarm_caches_for_date_range( string $start, string $end, int $affiliate_id ): void {
		// Skip if already warmed this request
		if ( $this->caches_warmed ) {
			return;
		}

		// Verify AffiliateWP tables are accessible
		if ( ! isset( affiliate_wp()->referrals ) || ! isset( affiliate_wp()->affiliates ) ) {
			return;
		}

		global $wpdb;

		$referrals_table = affiliate_wp()->referrals->table_name;

		// Verify table exists
		if ( ! $referrals_table || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $referrals_table ) ) !== $referrals_table ) {
			return;
		}

		// Build parameterized query to get unique affiliate IDs for unpaid referrals
		$where_clauses = array( "status = 'unpaid'" );
		$where_values  = array();

		if ( $start ) {
			$where_clauses[] = 'date >= %s';
			$where_values[]  = $start;
		}

		if ( $end ) {
			$where_clauses[] = 'date <= %s';
			$where_values[]  = $end;
		}

		if ( $affiliate_id > 0 ) {
			$where_clauses[] = 'affiliate_id = %d';
			$where_values[]  = $affiliate_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Build the query - limit to 10,000 to prevent memory issues
		$query = "SELECT DISTINCT affiliate_id FROM {$referrals_table} WHERE {$where_sql} LIMIT 10000";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, ...$where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above when values exist
		$affiliate_ids = $wpdb->get_col( $query );

		if ( empty( $affiliate_ids ) || ! is_array( $affiliate_ids ) ) {
			return;
		}

		// Cast all IDs to integers for safety
		$affiliate_ids = array_map( 'absint', $affiliate_ids );
		$affiliate_ids = array_filter( $affiliate_ids ); // Remove any zeros

		if ( empty( $affiliate_ids ) ) {
			return;
		}

		// Batch-load all affiliate objects and names into cache
		$this->batch_load_affiliates( $affiliate_ids );

		// Store count for logging
		$this->cached_count = count( $affiliate_ids );

		// Log the optimization when debug logging is enabled
		// Uses WP_DEBUG_LOG (not WP_DEBUG_DISPLAY) so it logs to file without showing on screen
		if ( $this->should_log() ) {
			error_log( sprintf(
				'[AffiliateWP Payout Optimizer] Pre-warmed caches for %d affiliates (date range: %s to %s)',
				$this->cached_count,
				$start ?: 'beginning',
				$end ?: 'now'
			) );
		}
	}

	/**
	 * Batch-load affiliate data into WordPress object cache
	 *
	 * This pre-fetches affiliate objects and names for all affiliates,
	 * eliminating individual database queries during the render loop.
	 *
	 * @param array $affiliate_ids Array of affiliate IDs (integers)
	 * @return void
	 */
	private function batch_load_affiliates( array $affiliate_ids ): void {
		if ( empty( $affiliate_ids ) ) {
			return;
		}

		global $wpdb;

		// Verify tables are accessible
		$affiliates_table = affiliate_wp()->affiliates->table_name ?? '';
		$users_table      = $wpdb->users;

		if ( ! $affiliates_table ) {
			return;
		}

		// Batch size to prevent memory issues with very large datasets
		$batch_size = 500;
		$batches    = array_chunk( $affiliate_ids, $batch_size );

		// Cache TTL in seconds (1 hour - matches AffiliateWP's HOUR_IN_SECONDS usage)
		$cache_ttl = HOUR_IN_SECONDS;

		foreach ( $batches as $batch ) {
			if ( empty( $batch ) ) {
				continue;
			}

			$count        = count( $batch );
			$placeholders = implode( ',', array_fill( 0, $count, '%d' ) );

			// Query to load affiliate names (the main bottleneck we're optimizing)
			// Only cache names - let AffiliateWP handle affiliate object creation
			// to avoid type conflicts with AffWP\Affiliate class
			$query = $wpdb->prepare(
				"SELECT a.affiliate_id, u.display_name
				FROM {$affiliates_table} a
				INNER JOIN {$users_table} u ON a.user_id = u.ID
				WHERE a.affiliate_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$batch
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above
			$results = $wpdb->get_results( $query );

			if ( empty( $results ) || ! is_array( $results ) ) {
				continue;
			}

			$names_cached = 0;
			foreach ( $results as $row ) {
				if ( ! isset( $row->affiliate_id ) || ! isset( $row->display_name ) ) {
					continue;
				}

				$aff_id = absint( $row->affiliate_id );

				// Cache affiliate name using the exact key AffiliateWP expects
				// See: class-affiliates-db.php line 517
				// This is the main optimization - prevents N+1 JOIN queries
				if ( '' !== $row->display_name ) {
					$cache_key = "affwp_affiliate_name_{$aff_id}";
					wp_cache_set( $cache_key, $row->display_name, 'affiliates', $cache_ttl );
					++$names_cached;
				}
			}

			// Log batch progress
			if ( $this->should_log() ) {
				error_log( sprintf(
					'[AffiliateWP Payout Optimizer] Batch cached %d affiliate names',
					$names_cached
				) );
			}
		}
	}

	/**
	 * Get the number of affiliates that were cached
	 *
	 * Useful for debugging and performance monitoring.
	 *
	 * @return int
	 */
	public function get_cached_count(): int {
		return $this->cached_count;
	}

	/**
	 * Check if caches have been warmed this request
	 *
	 * @return bool
	 */
	public function is_warmed(): bool {
		return $this->caches_warmed;
	}

	/**
	 * Convert date from various formats to MySQL format (YYYY-MM-DD)
	 *
	 * Handles common formats like:
	 * - MM/DD/YYYY (US format from datepicker)
	 * - DD/MM/YYYY (EU format)
	 * - YYYY-MM-DD (already MySQL format)
	 * - Various strtotime-parseable formats
	 *
	 * @param string $date_string Date string to convert
	 * @return string MySQL formatted date (YYYY-MM-DD) or empty string on failure
	 */
	private function convert_date_to_mysql( string $date_string ): string {
		if ( empty( $date_string ) ) {
			return '';
		}

		// If already in MySQL format (YYYY-MM-DD), return as-is
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_string ) ) {
			return $date_string;
		}

		// Try to parse the date using strtotime (handles many formats)
		$timestamp = strtotime( $date_string );

		if ( false === $timestamp || $timestamp < 0 ) {
			// strtotime failed, try manual parsing for MM/DD/YYYY format
			if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date_string, $matches ) ) {
				// Assume MM/DD/YYYY format (US format used by AffiliateWP datepicker)
				$month = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
				$day   = str_pad( $matches[2], 2, '0', STR_PAD_LEFT );
				$year  = $matches[3];

				return "{$year}-{$month}-{$day}";
			}

			// Could not parse the date
			if ( $this->should_log() ) {
				error_log( sprintf(
					'[AffiliateWP Payout Optimizer] Warning: Could not parse date "%s"',
					$date_string
				) );
			}
			return '';
		}

		// Convert timestamp to MySQL format
		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Determine if we should log optimizer activity
	 *
	 * Logs when:
	 * - RCF_PAYOUT_OPTIMIZER_DEBUG constant is true (explicit enable), OR
	 * - WP_DEBUG and WP_DEBUG_LOG are both true (standard WordPress debug logging)
	 *
	 * @return bool
	 */
	private function should_log(): bool {
		// Allow explicit enable/disable via constant
		if ( defined( 'RCF_PAYOUT_OPTIMIZER_DEBUG' ) ) {
			return (bool) RCF_PAYOUT_OPTIMIZER_DEBUG;
		}

		// Default: log when WP debug logging is enabled
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG );
	}
}
