<?php
/**
 * Plugin Name: RCP Content Filter Utility
 * Plugin URI: https://example.com/
 * Description: Filters out restricted content from post grids based on Restrict Content Pro membership levels
 * Version: 1.0.43
 * Author: samybaxy
 * Text Domain: rcp-content-filter
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 8.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
if ( ! defined( 'RCP_FILTER_VERSION' ) ) {
	define( 'RCP_FILTER_VERSION', '1.0.43' ); // Fixed Loqate re-initialization after address selection
}
if ( ! defined( 'RCP_FILTER_PLUGIN_FILE' ) ) {
	define( 'RCP_FILTER_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'RCP_FILTER_PLUGIN_DIR' ) ) {
	define( 'RCP_FILTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RCP_FILTER_PLUGIN_URL' ) ) {
	define( 'RCP_FILTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Check if Restrict Content Pro is active
function rcf_check_dependencies() {
    if ( ! function_exists( 'rcp_user_can_access' ) || ! function_exists( 'rcp_is_restricted_content' ) ) {
        add_action( 'admin_notices', 'rcf_dependency_notice' );
        return false;
    }
    return true;
}

// Display admin notice if RCP is not active
function rcf_dependency_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e( 'RCP Content Filter Utility requires Restrict Content Pro to be installed and activated.', 'rcp-content-filter' ); ?></p>
    </div>
    <?php
}

// Main plugin class
class RCP_Content_Filter {

    private static ?self $instance = null;
    private array $settings = [];

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
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    public function init(): void {
        // Check dependencies
        if ( ! rcf_check_dependencies() ) {
            return;
        }

        // Load settings with proper defaults
        $defaults = [
            'enabled_post_types' => [],
            'custom_post_types' => [], // For manually added CPTs
            'filter_priority' => 10,
            'hide_method' => 'remove', // Always use simple remove method
            'enable_learnpress_fix' => false // LearnPress Elementor fix
        ];

        $saved_settings = get_option( 'rcf_settings', [] );
        $this->settings = wp_parse_args( $saved_settings, $defaults );

        // Add hooks for filtering
        add_action( 'pre_get_posts', array( $this, 'adjust_query_for_restrictions' ), 5 );
        add_filter( 'the_posts', array( $this, 'filter_posts' ), $this->get_filter_priority(), 2 );
        add_filter( 'found_posts', array( $this, 'adjust_found_posts' ), 10, 2 );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Admin hooks
        if ( is_admin() ) {
            require_once RCP_FILTER_PLUGIN_DIR . 'admin/class-admin.php';
            new RCP_Content_Filter_Admin( $this );
        }
    }

    /**
     * Get filter priority from settings
     *
     * @return int
     */
    private function get_filter_priority(): int {
        return ! empty( $this->settings['filter_priority'] ) ? intval( $this->settings['filter_priority'] ) : 10;
    }

    /**
     * Check if post type should be filtered
     *
     * @param string $post_type Post type to check
     * @return bool
     */
    private function should_filter_post_type( string $post_type ): bool {
        // Check both enabled and custom post types
        $all_filtered_types = array_merge(
            $this->settings['enabled_post_types'] ?? [],
            $this->settings['custom_post_types'] ?? []
        );

        if ( empty( $all_filtered_types ) ) {
            return false;
        }

        return in_array( $post_type, $all_filtered_types, true );
    }


    /**
     * Adjust query to fetch more posts to compensate for filtered ones
     *
     * @param WP_Query $query The query object
     * @return void
     */
    public function adjust_query_for_restrictions( $query ) {
        // Only on frontend
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // Skip on checkout, cart, and account pages
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            return;
        }
        if ( function_exists( 'is_cart' ) && is_cart() ) {
            return;
        }
        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
            return;
        }

        // Skip if RCP functions not available
        if ( ! function_exists( 'rcp_user_can_access' ) || ! function_exists( 'rcp_is_restricted_content' ) ) {
            return;
        }

        // Check if this is a query we should modify
        // This includes main queries, AJAX queries, and archive queries
        $should_modify = false;

        // Check for main query
        if ( $query->is_main_query() && ! is_singular() ) {
            $should_modify = true;
        }

        // Check for AJAX requests (like load more)
        if ( wp_doing_ajax() ) {
            $should_modify = true;
        }

        // Check for archive pages
        if ( $query->is_archive() || $query->is_home() || $query->is_search() ) {
            $should_modify = true;
        }

        if ( ! $should_modify ) {
            return;
        }

        // Check if we're querying filtered post types
        $post_type = $query->get( 'post_type' );

        if ( empty( $post_type ) ) {
            $post_type = 'post';
        }

        if ( is_array( $post_type ) ) {
            $should_adjust = false;
            foreach ( $post_type as $type ) {
                if ( $this->should_filter_post_type( $type ) ) {
                    $should_adjust = true;
                    break;
                }
            }
            if ( ! $should_adjust ) {
                return;
            }
        } else {
            if ( ! $this->should_filter_post_type( $post_type ) ) {
                return;
            }
        }

        // Store original posts per page for later use
        $posts_per_page = $query->get( 'posts_per_page' );
        if ( $posts_per_page > 0 && $posts_per_page < 100 ) {
            // Store the original value
            $query->set( 'rcf_original_posts_per_page', $posts_per_page );
            // Fetch extra posts to compensate for filtered ones
            // We'll fetch 3x the requested amount to ensure we have enough after filtering
            $query->set( 'posts_per_page', $posts_per_page * 3 );
            // Also set a flag to indicate this query was modified
            $query->set( 'rcf_query_modified', true );
        }
    }

    /**
     * Filter posts array after query
     *
     * @param array $posts Array of post objects
     * @param WP_Query $query The query object
     * @return array Filtered array of posts
     */
    public function filter_posts( $posts, $query = null ) {
        // Only filter on frontend
        if ( is_admin() ) {
            return $posts;
        }

        // Skip if RCP functions not available
        if ( ! function_exists( 'rcp_user_can_access' ) || ! function_exists( 'rcp_is_restricted_content' ) ) {
            return $posts;
        }

        // Don't filter if no posts
        if ( empty( $posts ) ) {
            return $posts;
        }

        $filtered_posts = [];
        $current_user_id = get_current_user_id();

        // Get the original posts per page if it was modified
        $original_posts_per_page = null;
        if ( $query && is_object( $query ) ) {
            $original_posts_per_page = $query->get( 'rcf_original_posts_per_page' );
        }

        foreach ( $posts as $post ) {
            // Check if we should filter this post type
            if ( ! $this->should_filter_post_type( $post->post_type ) ) {
                $filtered_posts[] = $post;
                continue;
            }

            // For guests (user ID 0), check if content is restricted
            if ( $current_user_id === 0 ) {
                // If content is not restricted, guests can see it
                if ( ! rcp_is_restricted_content( $post->ID ) ) {
                    $filtered_posts[] = $post;
                }
            } else {
                // For logged-in users, check if they have access
                if ( rcp_user_can_access( $current_user_id, $post->ID ) ) {
                    $filtered_posts[] = $post;
                }
            }

            // If we have the original posts per page and reached the limit, stop
            if ( $original_posts_per_page && count( $filtered_posts ) >= $original_posts_per_page ) {
                // Only break if all post types in the result should be filtered
                $all_filtered = true;
                foreach ( $posts as $p ) {
                    if ( ! $this->should_filter_post_type( $p->post_type ) ) {
                        $all_filtered = false;
                        break;
                    }
                }
                if ( $all_filtered ) {
                    break;
                }
            }
        }

        // Trim to original requested amount if we have more
        if ( $original_posts_per_page && count( $filtered_posts ) > $original_posts_per_page ) {
            $filtered_posts = array_slice( $filtered_posts, 0, $original_posts_per_page );
        }

        return $filtered_posts;
    }


    /**
     * Adjust found posts count for pagination
     *
     * @param int $found_posts The number of posts found
     * @param WP_Query $query The query object
     * @return int
     */
    public function adjust_found_posts( $found_posts, $query ) {
        // Only on frontend
        if ( is_admin() ) {
            return $found_posts;
        }

        // Check if we modified this query
        if ( ! $query->get( 'rcf_original_posts_per_page' ) ) {
            return $found_posts;
        }

        // For now, return the found posts as-is
        // This could be enhanced to provide more accurate counts
        return $found_posts;
    }

    /**
     * Get settings
     *
     * @return array Plugin settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update settings
     *
     * @param array $settings New settings array
     * @return void
     */
    public function update_settings( $settings ) {
        $this->settings = wp_parse_args( $settings, [
            'enabled_post_types' => [],
            'custom_post_types' => [],
            'filter_priority' => 10,
            'hide_method' => 'remove',  // Always use remove method for simplicity
            'enable_learnpress_fix' => false
        ] );

        update_option( 'rcf_settings', $this->settings );
    }

    /**
     * Enqueue frontend scripts
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        // AffiliateWP registration form enhancement - only load on affiliate registration page
        if ( ! is_admin() && $this->is_affiliate_registration_page() ) {
            wp_enqueue_script(
                'rcf-affiliatewp-registration',
                RCP_FILTER_PLUGIN_URL . 'admin/js/affiliatewp-registration.js',
                array( 'jquery' ),
                RCP_FILTER_VERSION,
                true
            );

            // Pass data to JavaScript if needed
            wp_localize_script(
                'rcf-affiliatewp-registration',
                'rcfAffiliateWP',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'rcf_affiliatewp_nonce' ),
                    'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG
                )
            );
        }

        // LearnPress next button control
        if ( $this->is_learnpress_page() ) {
            wp_enqueue_script(
                'rcf-learnpress-next-button',
                RCP_FILTER_PLUGIN_URL . 'assets/js/learnpress-next-button-control.js',
                array(),
                RCP_FILTER_VERSION,
                true
            );
        }
    }

    /**
     * Check if current page is the AffiliateWP registration page
     *
     * @return bool
     */
    private function is_affiliate_registration_page(): bool {
        // Check if AffiliateWP is active
        if ( ! function_exists( 'affiliate_wp' ) ) {
            return false;
        }

        // Check for AffiliateWP registration shortcode or page slug
        global $post;

        // Method 1: Check for affiliate registration shortcode
        if ( $post && has_shortcode( $post->post_content, 'affiliate_registration' ) ) {
            return true;
        }

        // Method 2: Check common affiliate registration page slugs
        if ( is_page( array( 'affiliate-registration', 'affiliate-signup', 'register-affiliate', 'become-an-affiliate' ) ) ) {
            return true;
        }

        // Method 3: Check if URL contains 'affiliate' and 'register'
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( stripos( $uri, 'affiliate' ) !== false && stripos( $uri, 'register' ) !== false ) {
            return true;
        }

        return false;
    }

    /**
     * Check if current page is a LearnPress course or lesson page
     *
     * @return bool
     */
    private function is_learnpress_page(): bool {
        // Check if LearnPress is active
        if ( ! class_exists( 'LearnPress' ) ) {
            return false;
        }

        // Primary check: URL pattern for course context
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Check for course context URLs: /courses/{slug}/lessons/{slug}
        if ( strpos( $uri, '/courses/' ) !== false && strpos( $uri, '/lessons/' ) !== false ) {
            return true;
        }

        // Check for direct course URLs: /courses/{slug}
        if ( strpos( $uri, '/courses/' ) !== false ) {
            return true;
        }

        // Check for direct lesson URLs: /lessons/{slug}
        if ( strpos( $uri, '/lessons/' ) !== false ) {
            return true;
        }

        // Fallback: Check if we're on a course page using WordPress conditionals
        if ( is_singular( 'lp_course' ) ) {
            return true;
        }

        // Check if we're on a lesson page
        if ( is_singular( 'lp_lesson' ) ) {
            return true;
        }

        // Check if we're on a quiz page
        if ( is_singular( 'lp_quiz' ) ) {
            return true;
        }

        return false;
    }

}

/**
 * AffiliateWP Safety Hook: Reject referrals for failed/cancelled/refunded orders
 *
 * Automatically rejects AffiliateWP referrals when WooCommerce orders are
 * marked as failed, cancelled, or refunded. This prevents affiliates from
 * receiving commissions for orders that were not completed successfully.
 *
 * @param int $order_id WooCommerce order ID
 * @return void
 */
function bl_reject_affwp_referral_on_bad_status( $order_id ) {
    // Check if AffiliateWP is active
    if ( ! function_exists( 'affwp_get_referral_by' ) ) {
        return;
    }

    // Get the referral associated with this order
    $ref = affwp_get_referral_by( 'reference', $order_id, 'woocommerce' );

    // If referral exists and is not already paid, reject it
    if ( $ref && 'paid' !== $ref->status ) {
        affwp_set_referral_status( $ref, 'rejected' );

        // Add order note for tracking
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->add_order_note( sprintf(
                'AffiliateWP referral #%d auto-rejected due to order status.',
                $ref->ID
            ) );
        }
    }
}

// Hook into WooCommerce order status changes
add_action( 'woocommerce_order_status_failed', 'bl_reject_affwp_referral_on_bad_status', 10, 1 );
add_action( 'woocommerce_order_status_cancelled', 'bl_reject_affwp_referral_on_bad_status', 10, 1 );
add_action( 'woocommerce_order_status_refunded', 'bl_reject_affwp_referral_on_bad_status', 10, 1 );


/**
 * Provide default Partner+ product ID lookup
 *
 * Automatically looks up the product ID using the slug 'partner-plus'.
 * Can be overridden by applying the filter with a different value.
 *
 * @param int $product_id The product ID (default 0)
 * @return int The Partner+ product ID
 */
function bl_get_default_partner_product_id( $product_id ) {
    // If already set via filter, use that value
    if ( $product_id > 0 ) {
        return $product_id;
    }

    // Look up product by slug 'partner-plus'
    $product_slug = 'partner-plus';
    $products = get_posts( array(
        'post_type'   => 'product',
        'name'        => $product_slug,
        'post_status' => 'publish',
        'numberposts' => 1,
        'fields'      => 'ids',
    ) );

    if ( ! empty( $products ) ) {
        return absint( $products[0] );
    }

    return 0;
}
add_filter( 'bl_partner_plus_product_id', 'bl_get_default_partner_product_id', 5, 1 );


/**
 * Auto-create affiliate on Partner+ product purchase
 *
 * Automatically activates an affiliate account and assigns parent affiliate
 * when a customer purchases the Partner+ product. This bypasses the manual
 * registration form requirement.
 *
 * @param int $order_id The WooCommerce order ID
 * @return void
 */
function bl_auto_create_affiliate_on_partner_purchase( $order_id ) {
    // Bail if AffiliateWP is not active
    if ( ! function_exists( 'affiliate_wp' ) ) {
        return;
    }

    // Bail if required functions don't exist
    if ( ! function_exists( 'affwp_get_affiliate_id' ) || ! function_exists( 'affwp_add_affiliate' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Required AffiliateWP functions not available' );
        }
        return;
    }

    // Get the order with error handling
    try {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[BL Auto Affiliate] Invalid order object for ID: ' . $order_id );
            }
            return;
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Exception getting order #' . $order_id . ': ' . $e->getMessage() );
        }
        return;
    }

    // Check if already processed (prevent duplicate runs)
    $already_processed = $order->get_meta( '_bl_auto_affiliate_processed', true );
    if ( $already_processed ) {
        return;
    }

    // Get customer user ID
    $user_id = $order->get_customer_id();
    if ( ! $user_id ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Order #' . $order_id . ': No customer user ID found (guest order?)' );
        }
        return;
    }

    // Check if user is already an affiliate
    $existing_affiliate_id = affwp_get_affiliate_id( $user_id );
    if ( $existing_affiliate_id ) {
        return;
    }

    // Get and validate the Partner+ product ID
    // Uses filter with default lookup for slug 'partner-plus'
    $partner_product_id = apply_filters( 'bl_partner_plus_product_id', 0 );
    $partner_product_id = absint( $partner_product_id );

    if ( $partner_product_id <= 0 ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Partner+ product not found. Ensure product with slug "partner-plus" exists, or use filter: bl_partner_plus_product_id' );
        }
        return;
    }

    // Check if order contains the Partner+ product
    $has_partner_product = false;
    try {
        foreach ( $order->get_items() as $item ) {
            $product_id = absint( $item->get_product_id() );
            $variation_id = absint( $item->get_variation_id() );

            if ( $product_id === $partner_product_id || $variation_id === $partner_product_id ) {
                $has_partner_product = true;
                break;
            }
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Error getting order items: ' . $e->getMessage() );
        }
        return;
    }

    if ( ! $has_partner_product ) {
        return;
    }

    // CRITICAL: Store thank you URL transient IMMEDIATELY for Partner+ orders
    // This MUST happen before any role changes or automation triggers
    // The hijack function will redirect to this URL if user lands on partnership console
    $thankyou_url = $order->get_checkout_order_received_url();
    set_transient( 'bl_force_thankyou_redirect_' . $user_id, $thankyou_url, 300 ); // 5 minute expiry

    // Get referring affiliate ID (parent) from order meta ONLY
    // CRITICAL: Do NOT use affiliate_wp()->tracking->get_affiliate_id()
    // That gets the CURRENT visitor's referrer, not the original purchaser's
    $parent_affiliate_id = absint( $order->get_meta( '_affwp_affiliate_id', true ) );

    // Validate parent affiliate exists and is active
    if ( $parent_affiliate_id > 0 && function_exists( 'affwp_get_affiliate' ) ) {
        $parent_affiliate = affwp_get_affiliate( $parent_affiliate_id );

        if ( ! $parent_affiliate || $parent_affiliate->status !== 'active' ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(
                    '[BL Auto Affiliate] Order #' . $order_id . ': Parent affiliate #' . $parent_affiliate_id .
                    ' is invalid or inactive. Proceeding without parent.'
                );
            }
            $parent_affiliate_id = 0; // Don't use invalid parent
        }
    }

    // Get user data for affiliate creation
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Order #' . $order_id . ': User #' . $user_id . ' not found' );
        }
        return;
    }

    // Prepare affiliate data
    $affiliate_data = array(
        'user_id'       => $user_id,
        'status'        => 'active',
        'payment_email' => $user->user_email,
        'rate'          => '', // Uses global rate
        'rate_type'     => '', // Uses global rate type
    );

    // Allow customization of affiliate data
    $affiliate_data = apply_filters( 'bl_auto_affiliate_data', $affiliate_data, $user_id, $order_id, $parent_affiliate_id );

    // Create the affiliate with error handling
    try {
        $new_affiliate_id = affwp_add_affiliate( $affiliate_data );
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Order #' . $order_id . ': Exception creating affiliate: ' . $e->getMessage() );
        }

        try {
            $order->add_order_note(
                'Failed to auto-create affiliate account. Exception: ' . $e->getMessage()
            );
        } catch ( Exception $note_exception ) {
            // Silently fail if we can't add note
        }
        return;
    }

    if ( ! $new_affiliate_id ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Order #' . $order_id . ': Failed to create affiliate for user #' . $user_id );
        }

        try {
            $order->add_order_note(
                'Failed to auto-create affiliate account. User may already have an account or there was an error.'
            );
        } catch ( Exception $e ) {
            // Silently fail if we can't add note
        }
        return;
    }

    // Success! Mark as processed
    // Mark order as processed to prevent duplicate runs
    try {
        $order->update_meta_data( '_bl_auto_affiliate_processed', current_time( 'mysql' ) );
        $order->save();
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[BL Auto Affiliate] Warning: Could not save processed flag: ' . $e->getMessage() );
        }
    }

    // Add success note to order
    try {
        $order->add_order_note(
            sprintf(
                'Affiliate account #%d automatically created for customer.',
                $new_affiliate_id
            )
        );
    } catch ( Exception $e ) {
        // Silently fail if we can't add note
    }

    // Connect to parent affiliate if one exists
    if ( $parent_affiliate_id > 0 && function_exists( 'affiliate_wp_mtc' ) ) {
        try {
            // Use Multi-Tier Commissions to connect child to parent
            affiliate_wp_mtc()->network->connect_to_referrer(
                $new_affiliate_id,      // Child affiliate
                $parent_affiliate_id    // Parent affiliate
            );

            // Add note to order
            try {
                $order->add_order_note(
                    sprintf(
                        'Affiliate #%d connected to parent affiliate #%d.',
                        $new_affiliate_id,
                        $parent_affiliate_id
                    )
                );
            } catch ( Exception $e ) {
                // Silently fail if we can't add note
            }

        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(
                    '[BL Auto Affiliate] Order #' . $order_id . ': Failed to connect affiliate #' . $new_affiliate_id .
                    ' to parent #' . $parent_affiliate_id . ': ' . $e->getMessage()
                );
            }

            // Add error note to order
            try {
                $order->add_order_note(
                    sprintf(
                        'Warning: Could not connect affiliate #%d to parent #%d. Error: %s',
                        $new_affiliate_id,
                        $parent_affiliate_id,
                        $e->getMessage()
                    )
                );
            } catch ( Exception $note_exception ) {
                // Silently fail if we can't add note
            }
        }
    } elseif ( $parent_affiliate_id > 0 && function_exists( 'affwp_update_affiliate_meta' ) ) {
        // No Multi-Tier Commissions plugin, use direct meta update
        try {
            affwp_update_affiliate_meta( $new_affiliate_id, 'parent_affiliate_id', $parent_affiliate_id );

            try {
                $order->add_order_note(
                    sprintf(
                        'Affiliate #%d parent set to #%d (via metadata).',
                        $new_affiliate_id,
                        $parent_affiliate_id
                    )
                );
            } catch ( Exception $e ) {
                // Silently fail if we can't add note
            }

        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log(
                    '[BL Auto Affiliate] Order #' . $order_id . ': Failed to set parent meta: ' . $e->getMessage()
                );
            }

            try {
                $order->add_order_note(
                    sprintf(
                        'Warning: Affiliate #%d created but could not set parent #%d. Error: %s',
                        $new_affiliate_id,
                        $parent_affiliate_id,
                        $e->getMessage()
                    )
                );
            } catch ( Exception $note_exception ) {
                // Silently fail if we can't add note
            }
        }
    } else {
        // No parent affiliate found
        try {
            $order->add_order_note( 'Affiliate account created without a parent (no referral detected).' );
        } catch ( Exception $e ) {
            // Silently fail if we can't add note
        }
    }

    // Change user role from "Partner Plus Pending" to "Partner Plus"
    $user = get_userdata( $user_id );
    if ( $user ) {
        if ( in_array( 'partner-plus-pending', $user->roles, true ) ) {
            $user->remove_role( 'partner-plus-pending' );
            $user->add_role( 'partner-plus' ); // This may trigger automation redirect

            try {
                $order->add_order_note( 'User role changed from Partner Plus Pending to Partner Plus.' );
            } catch ( Exception $e ) {
                // Silently fail if we can't add note
            }
        }
    }

    // Clear the cart after successful affiliate creation
    if ( function_exists( 'WC' ) && WC()->cart ) {
        WC()->cart->empty_cart();
    }

    // Fire action for extensibility
    do_action( 'bl_after_auto_create_affiliate', $new_affiliate_id, $parent_affiliate_id, $user_id, $order_id );
}

// ENABLED: Auto-creation on checkout (eliminates need for activation button)
// Runs silently in background - does NOT interfere with redirects
add_action( 'woocommerce_order_status_completed', 'bl_auto_create_affiliate_on_partner_purchase', 999, 1 );
add_action( 'woocommerce_order_status_processing', 'bl_auto_create_affiliate_on_partner_purchase', 999, 1 );


/**
 * Force thank you page for Partner+ orders
 *
 * Overrides any redirect to partnership console and shows WooCommerce thank you page.
 * Customers need to see order confirmation before being sent elsewhere.
 *
 * @param string   $return_url The return URL
 * @param WC_Order $order      The order object
 * @return string The thank you page URL
 */
function bl_force_partner_thankyou_page( $return_url, $order ) {
    // Safety check
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return $return_url;
    }

    // Check if this order contains Partner+ product
    $partner_product_id = apply_filters( 'bl_partner_plus_product_id', 0 );
    if ( $partner_product_id <= 0 ) {
        return $return_url;
    }

    $has_partner_product = false;
    foreach ( $order->get_items() as $item ) {
        $product_id = absint( $item->get_product_id() );
        $variation_id = absint( $item->get_variation_id() );

        if ( $product_id === $partner_product_id || $variation_id === $partner_product_id ) {
            $has_partner_product = true;
            break;
        }
    }

    // Only override for Partner+ orders
    if ( ! $has_partner_product ) {
        return $return_url;
    }

    // Force thank you page URL
    $thankyou_url = $order->get_checkout_order_received_url();

    return $thankyou_url;
}
add_filter( 'woocommerce_get_return_url', 'bl_force_partner_thankyou_page', PHP_INT_MAX, 2 );


/**
 * Catch and override ALL wp_redirect calls for Partner+ orders
 *
 * This intercepts redirects to partnership console and forces thank you page instead.
 * Works in ALL contexts (AJAX, background, frontend) by checking for stored transient.
 */
function bl_intercept_wp_redirect( $location, $status ) {
    // Check if redirecting to partnership console
    if ( stripos( $location, '/console/partnership' ) !== false || stripos( $location, 'partnership' ) !== false ) {

        // METHOD 1: Check if current user has a stored thank you URL transient
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $thankyou_url = get_transient( 'bl_force_thankyou_redirect_' . $user_id );

            if ( $thankyou_url ) {
                return $thankyou_url;
            }
        }

        // METHOD 2: Check if there's an order key in the URL (for thank you page context)
        if ( isset( $_GET['key'] ) ) {
            $order_key = sanitize_text_field( $_GET['key'] );
            $order_id = wc_get_order_id_by_order_key( $order_key );
            $order = wc_get_order( $order_id );

            if ( $order ) {
                $partner_product_id = apply_filters( 'bl_partner_plus_product_id', 0 );

                $has_partner_product = false;
                foreach ( $order->get_items() as $item ) {
                    $product_id = absint( $item->get_product_id() );
                    $variation_id = absint( $item->get_variation_id() );

                    if ( $product_id === $partner_product_id || $variation_id === $partner_product_id ) {
                        $has_partner_product = true;
                        break;
                    }
                }

                if ( $has_partner_product ) {
                    $thankyou_url = $order->get_checkout_order_received_url();

                    return $thankyou_url;
                }
            }
        }
    }

    return $location;
}
// Hook into ALL WordPress redirect methods at maximum priority
add_filter( 'wp_redirect', 'bl_intercept_wp_redirect', PHP_INT_MAX, 2 );
add_filter( 'wp_safe_redirect', 'bl_intercept_wp_redirect', PHP_INT_MAX, 2 );
add_filter( 'wp_safe_redirect_fallback', 'bl_intercept_wp_redirect', PHP_INT_MAX, 2 );


/**
 * Remove RCP/Automator redirects on Partner+ thank you page
 */
function bl_prevent_partner_redirects() {
    // Only on thank you page
    if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
        return;
    }

    // Get the order ID from URL
    global $wp;
    if ( empty( $wp->query_vars['order-received'] ) ) {
        return;
    }

    $order_id = absint( $wp->query_vars['order-received'] );
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    // Check if this is a Partner+ order
    $partner_product_id = apply_filters( 'bl_partner_plus_product_id', 0 );
    if ( $partner_product_id <= 0 ) {
        return;
    }

    $has_partner_product = false;
    foreach ( $order->get_items() as $item ) {
        $product_id = absint( $item->get_product_id() );
        $variation_id = absint( $item->get_variation_id() );

        if ( $product_id === $partner_product_id || $variation_id === $partner_product_id ) {
            $has_partner_product = true;
            break;
        }
    }

    // Only prevent redirects for Partner+ orders
    if ( ! $has_partner_product ) {
        return;
    }

    // Remove common redirect hooks
    remove_all_filters( 'rcp_registration_redirect_url' );
    remove_all_filters( 'rcp_after_registration_redirect' );
    remove_all_filters( 'wp_redirect' );
    remove_all_filters( 'wp_safe_redirect' );

    // Also clear cart as backup (in case it wasn't cleared during order processing)
    if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
        WC()->cart->empty_cart();
    }
}
add_action( 'template_redirect', 'bl_prevent_partner_redirects', 1 );


/**
 * Block JavaScript/Meta refresh redirects on Partner+ thank you page
 */
function bl_block_js_redirects_on_thankyou() {
    // Only on thank you page
    if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
        return;
    }

    // Get the order ID from URL
    global $wp;
    if ( empty( $wp->query_vars['order-received'] ) ) {
        return;
    }

    $order_id = absint( $wp->query_vars['order-received'] );
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return;
    }

    // Check if this is a Partner+ order
    $partner_product_id = apply_filters( 'bl_partner_plus_product_id', 0 );
    if ( $partner_product_id <= 0 ) {
        return;
    }

    $has_partner_product = false;
    foreach ( $order->get_items() as $item ) {
        $product_id = absint( $item->get_product_id() );
        $variation_id = absint( $item->get_variation_id() );

        if ( $product_id === $partner_product_id || $variation_id === $partner_product_id ) {
            $has_partner_product = true;
            break;
        }
    }

    // Only block for Partner+ orders
    if ( ! $has_partner_product ) {
        return;
    }

    // Output JavaScript to block any redirect attempts
    ?>
    <script type="text/javascript">
    (function() {
        console.log('[BL Partner+] Blocking all redirects on thank you page');

        // Block window.location changes
        var originalLocation = window.location.href;
        Object.defineProperty(window, 'location', {
            get: function() {
                return {
                    href: originalLocation,
                    assign: function() { console.log('[BL Partner+] Blocked location.assign()'); },
                    replace: function() { console.log('[BL Partner+] Blocked location.replace()'); },
                    reload: function() { window.location.reload(); }
                };
            },
            set: function(val) {
                console.log('[BL Partner+] Blocked redirect to: ' + val);
                return originalLocation;
            }
        });

        // Remove any meta refresh tags
        var metaTags = document.querySelectorAll('meta[http-equiv="refresh"]');
        metaTags.forEach(function(tag) {
            console.log('[BL Partner+] Removing meta refresh tag');
            tag.remove();
        });

        // Monitor and block setTimeout/setInterval redirects
        var originalSetTimeout = window.setTimeout;
        window.setTimeout = function(fn, delay) {
            if (typeof fn === 'string' && (fn.includes('location') || fn.includes('redirect'))) {
                console.log('[BL Partner+] Blocked setTimeout redirect');
                return;
            }
            return originalSetTimeout.apply(this, arguments);
        };

        console.log('[BL Partner+] Thank you page protection active');
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'bl_block_js_redirects_on_thankyou', 1 );


/**
 * AGGRESSIVE: Hijack partnership console page load and redirect to thank you page
 *
 * This runs on 'wp' hook at priority 1 (VERY early, after query is parsed).
 * If we detect the user is landing on the partnership console page AND they have
 * a stored thank you URL (meaning they just completed a Partner+ order), we
 * immediately redirect them to the thank you page.
 *
 * This is more aggressive than filter-based interception because it catches
 * the page load AFTER the automation has already redirected, but BEFORE any
 * content is rendered.
 *
 * @since 1.0.0
 */
function bl_hijack_partnership_console_for_partner_orders() {
    // Only run on frontend
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }

    // Check if user is logged in
    if ( ! is_user_logged_in() ) {
        return;
    }

    // Check if we're on the partnership console page
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $is_partnership_console = ( stripos( $current_url, '/console/partnership' ) !== false );

    if ( ! $is_partnership_console ) {
        return;
    }

    // Check if this user has a pending Partner+ thank you redirect
    $user_id = get_current_user_id();
    $thankyou_url = get_transient( 'bl_force_thankyou_redirect_' . $user_id );

    if ( $thankyou_url ) {
        // Delete the transient so we only redirect once
        delete_transient( 'bl_force_thankyou_redirect_' . $user_id );

        // FORCE redirect to thank you page
        wp_redirect( $thankyou_url );
        exit; // Critical: Stop all execution
    }
}
// Run on 'wp' hook at priority 1 (after query is parsed, before template loads)
add_action( 'wp', 'bl_hijack_partnership_console_for_partner_orders', 1 );


/**
 * FALLBACK: Also try to intercept on init hook (even earlier)
 *
 * This runs before 'wp' hook and checks for the partnership console URL pattern.
 * This is a belt-and-suspenders approach to catch the redirect at multiple points.
 *
 * @since 1.0.0
 */
function bl_force_thankyou_before_automations() {
    // Only run on frontend, not in admin or AJAX
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }

    // Check if user is logged in
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_id = get_current_user_id();
    $thankyou_url = get_transient( 'bl_force_thankyou_redirect_' . $user_id );

    // If we have a stored thank you URL, check if we can redirect
    if ( $thankyou_url ) {
        // Check if the current request is for partnership console
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $is_partnership_console = ( stripos( $current_url, '/console/partnership' ) !== false );

        if ( $is_partnership_console ) {
            // Delete the transient so we only redirect once
            delete_transient( 'bl_force_thankyou_redirect_' . $user_id );

            // Use wp_redirect (not wp_safe_redirect) to allow any URL
            wp_redirect( $thankyou_url );
            exit; // Critical: Stop all execution to prevent automations from firing
        }
    }
}
// Run at priority 1 (very early) to intercept before automations
add_action( 'init', 'bl_force_thankyou_before_automations', 1 );


/**
 * Hijack JetFormBuilder's _reset_pass_link Macro
 *
 * Replaces JetFormBuilder's default reset password link (which gets mangled by
 * WP Engine LinkShield) with a clean, hardcoded URL that bypasses link rewriting.
 *
 * Usage: Use the existing %_reset_pass_link% macro in JetFormBuilder emails
 *
 * @since 1.0.0
 */

/**
 * Generate clean reset password URL bypassing LinkShield
 *
 * @param WP_User $user User object
 * @return string Clean reset password URL
 */
function rcf_generate_clean_reset_url( $user ) {
    if ( ! $user || ! is_a( $user, 'WP_User' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[RCF Reset URL] Invalid user object provided' );
        }
        return 'https://biolimitless.com/wp-login.php?action=lostpassword';
    }

    // Get reset key from JetFormBuilder context OR generate fresh
    $key = null;

    // Try to get from JetFormBuilder context first
    if ( function_exists( 'jet_fb_action_handler' ) && jet_fb_action_handler() ) {
        $key = jet_fb_action_handler()->get_context( 'reset_key' );
    }

    // Generate fresh key if not in context
    if ( empty( $key ) ) {
        $key = get_password_reset_key( $user );
    }

    if ( is_wp_error( $key ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[RCF Reset URL] Error generating reset key: ' . $key->get_error_message() );
        }
        return 'https://biolimitless.com/wp-login.php?action=lostpassword';
    }

    // Generate perfect clean URL - hardcoded domain to bypass LinkShield
    $reset_url = add_query_arg( array(
        'action' => 'rp',
        'key'    => $key,
        'login'  => rawurlencode( $user->user_login )
    ), 'https://biolimitless.com/wp-login.php' );

    return esc_url( $reset_url );
}

/**
 * Hijack WordPress's password reset URL generation
 *
 * This intercepts the core WordPress function that generates reset URLs,
 * which is what JetFormBuilder ultimately uses.
 */
add_filter( 'retrieve_password_message', function( $message, $key, $user_login, $user_data ) {
    // Check if message contains a reset URL
    if ( strpos( $message, 'action=rp' ) !== false ) {
        // Generate our clean URL
        $clean_url = rcf_generate_clean_reset_url( $user_data );

        // Replace any wp-login URLs with our clean version
        $message = preg_replace(
            '#https?://[^\s<]+wp-login\.php\?action=rp[^\s<]*#i',
            $clean_url,
            $message
        );
    }

    return $message;
}, 999, 4 );

/**
 * Hijack the network_site_url filter when generating reset links
 *
 * JetFormBuilder uses network_site_url or site_url to build the reset link.
 * We intercept this and force our hardcoded domain.
 */
add_filter( 'site_url', function( $url, $path, $scheme ) {
    // Only hijack when building wp-login.php reset URLs
    if ( strpos( $path, 'wp-login.php' ) !== false && strpos( $path, 'action=rp' ) !== false ) {
        // Force our hardcoded domain
        $url = str_replace( parse_url( $url, PHP_URL_HOST ), 'biolimitless.com', $url );

        // Also remove any LinkShield domains
        $url = preg_replace( '#url\d+\.biolimitless\.com#i', 'biolimitless.com', $url );
    }

    return $url;
}, 999, 3 );

/**
 * Hijack network_site_url as well (for multisite compatibility)
 */
add_filter( 'network_site_url', function( $url, $path, $scheme ) {
    // Only hijack when building wp-login.php reset URLs
    if ( strpos( $path, 'wp-login.php' ) !== false && strpos( $path, 'action=rp' ) !== false ) {
        // Force our hardcoded domain
        $url = str_replace( parse_url( $url, PHP_URL_HOST ), 'biolimitless.com', $url );

        // Also remove any LinkShield domains
        $url = preg_replace( '#url\d+\.biolimitless\.com#i', 'biolimitless.com', $url );
    }

    return $url;
}, 999, 3 );

/**
 * Additional hook: Intercept reset password email body
 *
 * This is a backup method that directly modifies the email body before sending,
 * ensuring the clean URL is used even if macro replacement doesn't fire.
 */
add_filter( 'jet-form-builder/custom-action/reset-user-password/email-body', function( $body, $form_id, $user ) {
    // Check if the body contains the _reset_pass_link macro or a LinkShield URL
    if ( strpos( $body, 'url5758.biolimitless.com' ) !== false ||
         strpos( $body, 'upn=' ) !== false ) {

        // Generate our clean URL
        $clean_url = rcf_generate_clean_reset_url( $user );

        // Replace any LinkShield URLs with our clean URL
        $body = preg_replace(
            '#https?://[^/]*url5758\.biolimitless\.com[^\s<]+#i',
            $clean_url,
            $body
        );
    }

    return $body;
}, 999, 3 ); // Highest priority to run after JetFormBuilder

/**
 * CRITICAL: Intercept wp_mail to prevent LinkShield URL rewriting
 *
 * WP Engine's LinkShield rewrites URLs at the SMTP layer, AFTER WordPress generates emails.
 * We need to intercept wp_mail() right before sending to replace URLs with our hardcoded version.
 */
add_filter( 'wp_mail', function( $args ) {
    // Check if this is a password reset email
    if ( isset( $args['message'] ) &&
         ( strpos( $args['message'], 'reset your password' ) !== false ||
           strpos( $args['message'], 'Reset your password' ) !== false ||
           strpos( $args['message'], 'reset' ) !== false ||
           strpos( $args['message'], 'action=rp' ) !== false ) ) {

        // Method 1: Extract reset key and login from existing wp-login URL
        if ( preg_match( '#wp-login\.php\?action=rp&key=([^&\s<"\']+)&login=([^\s<&"\']+)#i', $args['message'], $matches ) ) {
            $key = $matches[1];
            $login = urldecode( $matches[2] );

            // Generate our clean, hardcoded URL
            $clean_url = add_query_arg( array(
                'action' => 'rp',
                'key'    => $key,
                'login'  => rawurlencode( $login )
            ), 'https://biolimitless.com/wp-login.php' );

            // Replace ALL wp-login reset URLs
            $args['message'] = preg_replace(
                '#https?://[^\s<"\']*wp-login\.php\?action=rp[^\s<"\']*#i',
                $clean_url,
                $args['message']
            );
        }

        // Method 2: Also search for any LinkShield URLs and replace them
        $linkshield_pattern = '#https?://url\d+\.biolimitless\.com/[^\s<"\']+#i';
        if ( preg_match( $linkshield_pattern, $args['message'] ) ) {
            // Try to extract key and login from somewhere in the message
            if ( preg_match( '#key=([^&\s<"\']+)#i', $args['message'], $key_match ) &&
                 preg_match( '#login=([^\s<&"\']+)#i', $args['message'], $login_match ) ) {

                $key = $key_match[1];
                $login = urldecode( $login_match[1] );

                $clean_url = add_query_arg( array(
                    'action' => 'rp',
                    'key'    => $key,
                    'login'  => rawurlencode( $login )
                ), 'https://biolimitless.com/wp-login.php' );

                // Replace LinkShield URLs
                $args['message'] = preg_replace(
                    $linkshield_pattern,
                    $clean_url,
                    $args['message']
                );
            }
        }
    }

    return $args;
}, 999 );


/**
 * ============================================================================
 * WooCommerce Checkout Field Validation - ASCII/Romaji Only
 * ============================================================================
 *
 * Restricts checkout fields to ASCII characters only (A-Z, 0-9, basic punctuation)
 * Blocks Kanji, Hiragana, Katakana, and other Unicode characters for international
 * shipping compatibility.
 *
 * Uses dual validation:
 * - JavaScript: Real-time validation as user types
 * - PHP: Backend validation on checkout submission
 *
 * @since 1.0.0
 */

/**
 * Validate that a string contains only ASCII characters
 *
 * Allowed characters:
 * - Roman letters: A-Z, a-z
 * - Arabic numerals: 0-9
 * - Standard punctuation: space, hyphen, period, comma, apostrophe, #, /, (, ), &, +, _, %
 * - Email-only: @ (only when $is_email = true)
 *
 * Blocks:
 * - Kanji (漢字)
 * - Hiragana (ひらがな)
 * - Katakana (カタカナ)
 * - All other Unicode/non-ASCII characters
 *
 * @param string $value The value to validate
 * @param bool $is_email Whether this is an email field (allows @ symbol)
 * @return bool True if valid (ASCII only), false if contains non-ASCII
 */
function rcf_is_ascii_only( $value, $is_email = false ) {
	if ( empty( $value ) ) {
		return true; // Empty values are handled by required field validation
	}

	// First check: Reject any non-ASCII/multi-byte characters (kanji, hiragana, katakana, emoji, etc.)
	// ASCII range is 0x00-0x7F
	if ( preg_match( '/[^\x00-\x7F]/', $value ) ) {
		return false; // Contains non-ASCII characters
	}

	// For email fields, we only care about non-ASCII characters, so we're done
	if ( $is_email ) {
		return true;
	}

	// For non-email fields, also check pattern to disallow @ and other special chars
	$pattern = '/^[A-Za-z0-9\s\-.,\'\/#()&+_%]+$/';
	return preg_match( $pattern, $value ) === 1;
}

/**
 * Get user-friendly field name for error messages
 *
 * @param string $field_key The WooCommerce field key (e.g., 'billing_first_name')
 * @return string User-friendly field name
 */
function rcf_get_field_label( $field_key ) {
	$labels = array(
		'billing_first_name'  => 'Billing First Name',
		'billing_last_name'   => 'Billing Last Name',
		'billing_company'     => 'Billing Company',
		'billing_address_1'   => 'Billing Address Line 1',
		'billing_address_2'   => 'Billing Address Line 2',
		'billing_city'        => 'Billing City',
		'billing_state'       => 'Billing State',
		'billing_postcode'    => 'Billing Postcode',
		'billing_email'       => 'Billing Email Address',
		'billing_phone'       => 'Billing Phone Number',
		'shipping_first_name' => 'Shipping First Name',
		'shipping_last_name'  => 'Shipping Last Name',
		'shipping_company'    => 'Shipping Company',
		'shipping_address_1'  => 'Shipping Address Line 1',
		'shipping_address_2'  => 'Shipping Address Line 2',
		'shipping_city'       => 'Shipping City',
		'shipping_state'      => 'Shipping State',
		'shipping_postcode'   => 'Shipping Postcode',
		'shipping_phone'      => 'Shipping Phone Number',
	);

	return isset( $labels[ $field_key ] ) ? $labels[ $field_key ] : $field_key;
}

/**
 * Validate WooCommerce checkout fields for ASCII-only characters
 *
 * Hooks into WooCommerce checkout validation to ensure all address and name
 * fields contain only ASCII characters compatible with international shipping.
 *
 * @param array    $data   Posted checkout data
 * @param WP_Error $errors WooCommerce errors object
 * @return void
 */
function rcf_validate_checkout_fields_ascii( $data, $errors ) {
	// Fields to validate (account, name, address, email, and phone fields)
	$fields_to_validate = array(
		// Account fields
		'account_username',
		// Billing name and address fields
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_state',
		'billing_postcode',
		// Billing email field - validate for ASCII + @ only
		'billing_email',
		// Billing phone field
		'billing_phone',
		// Shipping name and address fields
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_state',
		'shipping_postcode',
		// Shipping email field - validate for ASCII + @ only
		'shipping_email',
		// Shipping phone field
		'shipping_phone',
	);

	foreach ( $fields_to_validate as $field_key ) {
		// Skip if field is not set
		if ( ! isset( $data[ $field_key ] ) ) {
			continue;
		}

		$value = $data[ $field_key ];

		// Skip validation for empty optional fields
		if ( empty( $value ) ) {
			continue;
		}

		// Check if this is an email field
		$is_email = in_array( $field_key, array( 'billing_email', 'shipping_email' ), true );

		// Validate ASCII-only (email fields allow @ symbol)
		if ( ! rcf_is_ascii_only( $value, $is_email ) ) {
			$field_label = rcf_get_field_label( $field_key );

			$errors->add(
				'validation',
				sprintf(
					'<strong>%s</strong>: Use Roman/English characters only (A–Z, 0–9). Alternative characters not supported.',
					$field_label
				)
			);
		}
	}
}
add_action( 'woocommerce_after_checkout_validation', 'rcf_validate_checkout_fields_ascii', 10, 2 );

/**
 * Make phone fields required on WooCommerce checkout
 *
 * @param array $fields Billing or shipping fields array
 * @return array Modified fields array with phone marked as required
 */
function rcf_make_phone_required( $fields ) {
	if ( isset( $fields['billing_phone'] ) ) {
		$fields['billing_phone']['required'] = true;
		$fields['billing_phone']['label'] = 'Phone'; // Update label to remove "(optional)"
	}
	return $fields;
}
add_filter( 'woocommerce_billing_fields', 'rcf_make_phone_required', 10, 1 );

/**
 * Make shipping phone field required
 *
 * @param array $fields Shipping fields array
 * @return array Modified fields array with phone marked as required
 */
function rcf_make_shipping_phone_required( $fields ) {
	if ( isset( $fields['shipping_phone'] ) ) {
		$fields['shipping_phone']['required'] = true;
		$fields['shipping_phone']['label'] = 'Phone'; // Update label to remove "(optional)"
	}
	return $fields;
}
add_filter( 'woocommerce_shipping_fields', 'rcf_make_shipping_phone_required', 10, 1 );

/**
 * Update Address Line 2 placeholder to include "Building"
 *
 * Modifies the placeholder text for address_2 field (both billing and shipping)
 * to read "Building, Apartment, suite, unit, etc. (optional)" instead of just
 * "Apartment, suite, unit, etc. (optional)".
 *
 * This aligns with Loqate's SubBuilding field which can contain building names,
 * apartment numbers, suite numbers, unit designations, etc.
 *
 * @param array $fields Default address fields
 * @return array Modified address fields
 * @since 1.0.39
 */
function rcf_update_address_2_placeholder( $fields ) {
	if ( isset( $fields['address_2'] ) ) {
		$fields['address_2']['placeholder'] = __( 'Building, Apartment, suite, unit, etc. (optional)', 'rcp-content-filter' );
	}
	return $fields;
}
add_filter( 'woocommerce_default_address_fields', 'rcf_update_address_2_placeholder', 10, 1 );

/**
 * Uncheck "Ship to different address?" by default on WooCommerce checkout
 *
 * @param bool $checked Default checked state
 * @return bool Modified checked state (false to uncheck by default)
 */
function rcf_uncheck_ship_to_different_address( $checked ) {
	// Check if the feature is enabled in settings
	$settings = get_option( 'rcf_settings', [] );

	if ( empty( $settings['uncheck_ship_to_different_address'] ) ) {
		return $checked; // Feature disabled, return original state
	}

	// Return false to uncheck by default
	// JavaScript will auto-check if shipping data exists
	return false;
}
add_filter( 'woocommerce_ship_to_different_address_checked', 'rcf_uncheck_ship_to_different_address', 10, 1 );

/**
 * Enqueue JavaScript for ship-to-different-address auto-checking
 *
 * Loads script that auto-checks the checkbox if shipping data exists
 *
 * @return void
 */
function rcf_enqueue_shipping_address_script() {
	$settings = get_option( 'rcf_settings', [] );

	if ( empty( $settings['uncheck_ship_to_different_address'] ) ) {
		return;
	}

	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	wp_enqueue_script(
		'rcf-shipping-address-control',
		RCP_FILTER_PLUGIN_URL . 'assets/js/shipping-address-control.js',
		array( 'jquery' ),
		RCP_FILTER_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'rcf_enqueue_shipping_address_script' );

/**
 * Add inline CSS to hide shipping address fields by default (NOT the checkbox)
 * This prevents flash of visible content before JavaScript runs
 */
function rcf_add_shipping_address_css() {
	// Check if feature is enabled
	$settings = get_option( 'rcf_settings', [] );

	if ( empty( $settings['uncheck_ship_to_different_address'] ) ) {
		return;
	}

	// Only load on checkout page
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	// Add inline CSS to hide only the shipping ADDRESS FIELDS initially
	// This CSS does NOT use !important so JavaScript can override it
	?>
	<style id="rcf-shipping-control-css">
		/* Hide shipping address fields initially - JS will show/hide based on checkbox */
		.woocommerce-shipping-fields .shipping_address {
			display: none;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'rcf_add_shipping_address_css', 999 );

/**
 * Enqueue JavaScript for real-time checkout field validation
 *
 * Loads the validation script only on the WooCommerce checkout page
 * for real-time user feedback as they type.
 *
 * @return void
 */
function rcf_enqueue_checkout_validation_script() {
	// Only load on checkout page
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	wp_enqueue_script(
		'rcf-checkout-validation',
		RCP_FILTER_PLUGIN_URL . 'assets/js/checkout-ascii-validation.js',
		array( 'jquery' ),
		RCP_FILTER_VERSION,
		true
	);

	// Pass configuration to JavaScript
	wp_localize_script(
		'rcf-checkout-validation',
		'rcfCheckoutValidation',
		array(
			'errorMessage' => 'Use Roman/English characters only (A–Z, 0–9). Alternative characters not supported.',
			'fieldsToValidate' => array(
				// Account fields
				'account_username',
				// Billing name and address fields
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				// Billing email field
				'billing_email',
				// Billing phone field
				'billing_phone',
				// Shipping name and address fields
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
				// Shipping email field
				'shipping_email',
				// Shipping phone field
				'shipping_phone',
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rcf_enqueue_checkout_validation_script' );


// Initialize plugin
add_action( 'plugins_loaded', function(): void {
    RCP_Content_Filter::get_instance();
}, 10 );

// Initialize LearnPress fix after plugins loaded
add_action( 'plugins_loaded', function(): void {
    // Load the LearnPress fix class file
    require_once RCP_FILTER_PLUGIN_DIR . 'includes/class-learnpress-elementor-fix.php';

    // Check if the fix is enabled in settings
    $settings = get_option( 'rcf_settings', [] );
    if ( ! empty( $settings['enable_learnpress_fix'] ) ) {
        // Initialize the fix - targets Thim Elementor Kit's template system
        RCF_LearnPress_Elementor_Fix::get_instance();
    }
}, 20 );

// Initialize Loqate Address Capture integration
add_action( 'plugins_loaded', function(): void {
    // Load Loqate Address Capture class
    require_once RCP_FILTER_PLUGIN_DIR . 'includes/class-loqate-address-capture.php';

    // Initialize Loqate integration (singleton)
    RCF_Loqate_Address_Capture::get_instance();
}, 20 );


/**
 * Capture DNA kit serials from Rapid's 'products' payload when they hit AST endpoint
 *
 * When shipping companies (e.g., Rapid) submit shipment tracking data to the
 * Advanced Shipment Tracking API endpoint, this function intercepts the request
 * and extracts DNA kit serial IDs from the products payload.
 *
 * Payload Structure:
 * {
 *     "order_id": "25225",
 *     "date_shipped": "2025-11-21",
 *     "tracking_number": "ZZ12347",
 *     "custom_tracking_link": "...",
 *     "custom_tracking_provider": "DHL",
 *     "tracking_provider": "DHL",
 *     "products": {
 *         "6141": "T12349"                    // Single kit
 *         "6137": "T12345,T12346,T12347"      // Multiple kits (comma-separated)
 *     }
 * }
 *
 * Handles:
 * - Single kit per line item
 * - Multiple kits per line item (comma-separated)
 * - Multiple line items (merges all)
 * - Multiple API calls (appends new unique IDs only)
 *
 * Stores as JetEngine repeater format: dna_kit_ids meta with array of { dna_kit_id: ... } objects
 *
 * @param WP_REST_Response|WP_Error $response The response from the API call
 * @param array                     $handler  The handler that processed the request
 * @param WP_REST_Request           $request  The request object
 * @return WP_REST_Response|WP_Error Unmodified response
 */
add_filter( 'rest_request_after_callbacks', 'biolimitless_capture_rapid_dna_kit_ids', 10, 3 );

function biolimitless_capture_rapid_dna_kit_ids( $response, $handler, $request ) {

    // Only fire on POST to AST's add-tracking endpoint
    if ( $request->get_method() !== 'POST' ) {
        return $response;
    }

    $route = $request->get_route();
    if ( ! preg_match( '#^/wc-shipment-tracking/v3/orders/(\d+)/shipment-trackings?$#', $route, $matches ) ) {
        return $response;
    }

    $order_id = (int) $matches[1];

    // Only continue if the API call was successful
    // AST returns HTTP 200 with successful response, but check for obvious errors
    if ( is_wp_error( $response ) || ( isset( $response->data['code'] ) && strpos( $response->data['code'], 'rest_' ) === 0 && $response->data['code'] !== 'rest_success' ) ) {
        return $response;
    }

    // Get payload (covers both application/json and form-data)
    $params = $request->get_json_params();
    if ( ! $params ) {
        $params = $request->get_body_params();
    }
    if ( ! $params ) {
        $params = $request->get_params();
    }

    if ( empty( $params['products'] ) || ! is_array( $params['products'] ) ) {
        return $response;
    }

    $products = $params['products'];
    $new_kit_ids = array();

    // Collect all kit IDs from all line items
    foreach ( $products as $line_item_id => $kits_str ) {
        if ( ! is_string( $kits_str ) ) {
            continue;
        }

        // Split comma-separated kit IDs and trim each
        $kits = array_map( 'trim', explode( ',', $kits_str ) );
        foreach ( $kits as $kit ) {
            if ( $kit !== '' ) {
                $new_kit_ids[] = sanitize_text_field( $kit );
            }
        }
    }

    // Remove duplicates within this batch
    $new_kit_ids = array_unique( $new_kit_ids );

    if ( empty( $new_kit_ids ) ) {
        return $response;
    }

    // Prepare repeater rows for JetEngine
    $rows_to_add = array();
    foreach ( $new_kit_ids as $kit ) {
        $rows_to_add[] = array( 'dna_kit_id' => $kit );
    }

    // Get existing repeater data
    $existing = get_post_meta( $order_id, 'dna_kit_ids', true );
    if ( ! is_array( $existing ) ) {
        $existing = array();
    }

    // Remove any rows with empty dna_kit_id (leftover from previous failed attempts)
    $existing = array_filter( $existing, function( $row ) {
        return ! empty( $row['dna_kit_id'] );
    } );

    // Merge new rows with existing, preventing duplicates
    foreach ( $rows_to_add as $new_row ) {
        $duplicate = false;
        foreach ( $existing as $row ) {
            if ( isset( $row['dna_kit_id'] ) && $row['dna_kit_id'] === $new_row['dna_kit_id'] ) {
                $duplicate = true;
                break;
            }
        }
        if ( ! $duplicate ) {
            $existing[] = $new_row;
        }
    }

    // Re-index array to ensure JetEngine renders correctly
    $existing = array_values( $existing );

    // Save repeater data – JetEngine expects exactly this format
    update_post_meta( $order_id, 'dna_kit_ids', $existing );

    // Log for debugging (only if WP_DEBUG enabled)
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            '[BL DNA Kit Capture] Order #%d: Captured %d unique kit IDs. Total stored: %d',
            $order_id,
            count( $new_kit_ids ),
            count( $existing )
        ) );
    }

    return $response;
}