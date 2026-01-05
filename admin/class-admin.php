<?php
/**
 * Admin functionality for RCP Content Filter
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RCP_Content_Filter_Admin {

    private $plugin;
    private $import_results;

    /**
     * Constructor
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        // AJAX endpoints for batch user creation
        add_action( 'wp_ajax_rcf_process_user_batch', array( $this, 'ajax_process_user_batch' ) );
    }

    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'rcp-members',
            __( 'Content Filter Settings', 'rcp-content-filter' ),
            __( 'Content Filter', 'rcp-content-filter' ),
            'manage_options',
            'rcp-content-filter',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'rcf_settings_group', 'rcf_settings' );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'restrict_page_rcp-content-filter' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'rcf-admin',
            RCP_FILTER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            RCP_FILTER_VERSION
        );

        // Enqueue batch processing JavaScript
        wp_enqueue_script(
            'rcf-batch-processing',
            RCP_FILTER_PLUGIN_URL . 'admin/js/batch-processing.js',
            array( 'jquery' ),
            RCP_FILTER_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'rcf-batch-processing',
            'rcfBatchProcessing',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'rcf_batch_processing' ),
            )
        );
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if ( ! isset( $_GET['page'] ) || 'rcp-content-filter' !== $_GET['page'] ) {
            return;
        }

        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Settings saved successfully!', 'rcp-content-filter' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

        // Get current settings
        $settings = $this->plugin->get_settings();

        // Get all public post types
        $post_types = get_post_types( array( 'public' => true ), 'objects' );

        // Organize post types into groups
        $builtin_types = array();
        $custom_types = array();

        foreach ( $post_types as $post_type ) {
            if ( $post_type->name === 'attachment' ) {
                continue;
            }

            if ( $post_type->_builtin ) {
                $builtin_types[] = $post_type;
            } else {
                $custom_types[] = $post_type;
            }
        }

        // Handle form submission
        if ( isset( $_POST['rcf_nonce'] ) && wp_verify_nonce( $_POST['rcf_nonce'], 'rcf_save_settings' ) ) {
            $this->save_settings();
            $settings = $this->plugin->get_settings();

            // Redirect to avoid resubmission
            wp_redirect( admin_url( 'admin.php?page=rcp-content-filter&tab=' . $current_tab . '&settings-updated=true' ) );
            exit;
        }

        // Handle Stripe migration CSV upload
        if ( isset( $_POST['rcf_stripe_nonce'] ) && wp_verify_nonce( $_POST['rcf_stripe_nonce'], 'rcf_stripe_migration' ) ) {
            $this->process_stripe_migration();
        }

        // Handle User Import CSV upload
        if ( isset( $_POST['rcf_user_import_nonce'] ) && wp_verify_nonce( $_POST['rcf_user_import_nonce'], 'rcf_user_import' ) ) {
            $this->import_results = $this->process_user_import_csv();
        }

        // Handle AffiliateWP Import CSV upload
        if ( isset( $_POST['rcf_affiliatewp_import_nonce'] ) && wp_verify_nonce( $_POST['rcf_affiliatewp_import_nonce'], 'rcf_affiliatewp_import' ) ) {
            $this->process_affiliatewp_import();
        }

        ?>
        <div class="wrap">
            <h1><?php _e( 'RCP Content Filter Settings', 'rcp-content-filter' ); ?></h1>

            <!-- Tab Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=rcp-content-filter&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Content Filter Settings', 'rcp-content-filter' ); ?>
                </a>
                <a href="?page=rcp-content-filter&tab=loqate" class="nav-tab <?php echo $current_tab === 'loqate' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Loqate Integration', 'rcp-content-filter' ); ?>
                </a>
                <a href="?page=rcp-content-filter&tab=stripe-migration" class="nav-tab <?php echo $current_tab === 'stripe-migration' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Stripe Migration', 'rcp-content-filter' ); ?>
                </a>
                <a href="?page=rcp-content-filter&tab=user-import" class="nav-tab <?php echo $current_tab === 'user-import' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'User Import', 'rcp-content-filter' ); ?>
                </a>
                <a href="?page=rcp-content-filter&tab=affiliatewp-import" class="nav-tab <?php echo $current_tab === 'affiliatewp-import' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'AffiliateWP Import', 'rcp-content-filter' ); ?>
                </a>
            </h2>

            <?php if ( $current_tab === 'settings' ) : ?>
            <div class="rcf-settings-wrap">
                <div class="rcf-main-settings">
                    <form method="post" action="">
                        <?php wp_nonce_field( 'rcf_save_settings', 'rcf_nonce' ); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Filter Method', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio" name="rcf_settings[hide_method]" value="remove" checked>
                                            <span><?php _e( 'Simple Post Filtering', 'rcp-content-filter' ); ?></span>
                                            <p class="description"><?php _e( 'Filters out restricted posts after the query (simple and reliable method).', 'rcp-content-filter' ); ?></p>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="enable_learnpress_fix"><?php _e( 'LearnPress + Elementor Fix', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" id="enable_learnpress_fix" name="rcf_settings[enable_learnpress_fix]" value="1"
                                                <?php checked( ! empty( $settings['enable_learnpress_fix'] ) ); ?>>
                                            <span><?php _e( 'Enable LearnPress Course Context Fix', 'rcp-content-filter' ); ?></span>
                                        </label>
                                        <p class="description">
                                            <?php _e( 'Fixes Elementor templates not loading when lessons are viewed in course context URLs.', 'rcp-content-filter' ); ?><br>
                                            <strong><?php _e( 'Example:', 'rcp-content-filter' ); ?></strong><br>
                                            • <?php _e( 'Direct URL:', 'rcp-content-filter' ); ?> <code>/lessons/lesson-name/</code> - <?php _e( 'Works by default', 'rcp-content-filter' ); ?><br>
                                            • <?php _e( 'Course context URL:', 'rcp-content-filter' ); ?> <code>/courses/course-name/lessons/lesson-name/</code> - <?php _e( 'Requires this fix', 'rcp-content-filter' ); ?>
                                        </p>
                                        <?php
                                        // Display status if enabled
                                        if ( ! empty( $settings['enable_learnpress_fix'] ) && class_exists( 'RCF_LearnPress_Elementor_Fix' ) ) {
                                            $status = RCF_LearnPress_Elementor_Fix::get_status();
                                            ?>
                                            <div class="rcf-learnpress-status" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid <?php echo $status['learnpress_active'] && $status['elementor_active'] ? '#00a32a' : '#dba617'; ?>; border-radius: 2px;">
                                                <h4 style="margin: 0 0 8px 0;"><?php _e( 'Fix Status:', 'rcp-content-filter' ); ?></h4>
                                                <ul style="margin: 0; padding-left: 20px;">
                                                    <li>
                                                        <span style="color: <?php echo $status['learnpress_active'] ? '#00a32a' : '#d63638'; ?>;">●</span>
                                                        <?php _e( 'LearnPress:', 'rcp-content-filter' ); ?>
                                                        <strong><?php echo $status['learnpress_active'] ? __( 'Active', 'rcp-content-filter' ) : __( 'Not Active', 'rcp-content-filter' ); ?></strong>
                                                    </li>
                                                    <li>
                                                        <span style="color: <?php echo $status['elementor_active'] ? '#00a32a' : '#d63638'; ?>;">●</span>
                                                        <?php _e( 'Elementor:', 'rcp-content-filter' ); ?>
                                                        <strong><?php echo $status['elementor_active'] ? __( 'Active', 'rcp-content-filter' ) : __( 'Not Active', 'rcp-content-filter' ); ?></strong>
                                                    </li>
                                                    <li>
                                                        <span style="color: <?php echo $status['elementor_pro_active'] ? '#00a32a' : '#dba617'; ?>;">●</span>
                                                        <?php _e( 'Elementor Pro:', 'rcp-content-filter' ); ?>
                                                        <strong><?php echo $status['elementor_pro_active'] ? __( 'Active', 'rcp-content-filter' ) : __( 'Not Active', 'rcp-content-filter' ); ?></strong>
                                                        <?php if ( ! $status['elementor_pro_active'] ) : ?>
                                                            <em style="color: #666;">(<?php _e( 'Optional - needed for Theme Builder templates', 'rcp-content-filter' ); ?>)</em>
                                                        <?php endif; ?>
                                                    </li>
                                                    <li>
                                                        <span style="color: <?php echo $status['hooks_registered'] ? '#00a32a' : '#d63638'; ?>;">●</span>
                                                        <?php _e( 'Fix Hooks:', 'rcp-content-filter' ); ?>
                                                        <strong><?php echo $status['hooks_registered'] ? __( 'Registered', 'rcp-content-filter' ) : __( 'Not Registered', 'rcp-content-filter' ); ?></strong>
                                                    </li>
                                                </ul>
                                                <?php if ( $status['learnpress_active'] && $status['elementor_active'] && $status['hooks_registered'] ) : ?>
                                                    <p style="margin: 8px 0 0 0; padding: 8px; background: #d7f1dd; color: #00491a; border-radius: 2px;">
                                                        <strong>✓ <?php _e( 'Fix is working!', 'rcp-content-filter' ); ?></strong>
                                                        <?php _e( 'Elementor templates should now load in course context URLs.', 'rcp-content-filter' ); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="uncheck_ship_to_different_address"><?php _e( 'WooCommerce Shipping Address', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" id="uncheck_ship_to_different_address" name="rcf_settings[uncheck_ship_to_different_address]" value="1"
                                                <?php checked( ! empty( $settings['uncheck_ship_to_different_address'] ) ); ?>>
                                            <span><?php _e( 'Uncheck "Ship to different address?" by default', 'rcp-content-filter' ); ?></span>
                                        </label>
                                        <p class="description">
                                            <?php _e( 'When enabled, the "Ship to a different address?" checkbox will be unchecked by default on the checkout page.', 'rcp-content-filter' ); ?><br>
                                            <?php _e( 'It will automatically check if the customer has saved shipping information.', 'rcp-content-filter' ); ?>
                                        </p>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="filter_priority"><?php _e( 'Filter Priority', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="filter_priority" name="rcf_settings[filter_priority]"
                                        value="<?php echo esc_attr( $settings['filter_priority'] ); ?>" min="1" max="999">
                                    <p class="description">
                                        <strong><?php _e( 'What is Filter Priority?', 'rcp-content-filter' ); ?></strong><br>
                                        <?php _e( 'Controls when this plugin\'s filters run compared to other plugins:', 'rcp-content-filter' ); ?><br>
                                        • <?php _e( 'Lower numbers (1-9) = Runs BEFORE most plugins', 'rcp-content-filter' ); ?><br>
                                        • <?php _e( 'Default (10) = Standard priority', 'rcp-content-filter' ); ?><br>
                                        • <?php _e( 'Higher numbers (11+) = Runs AFTER most plugins', 'rcp-content-filter' ); ?><br>
                                        <?php _e( 'Only change if you experience conflicts with other filtering plugins.', 'rcp-content-filter' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Post Types to Filter', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <div class="rcf-post-types-container">
                                        <?php if ( ! empty( $builtin_types ) ) : ?>
                                        <div class="rcf-post-type-group">
                                            <h4><?php _e( 'Built-in Post Types', 'rcp-content-filter' ); ?></h4>
                                            <fieldset>
                                                <?php foreach ( $builtin_types as $post_type ) : ?>
                                                    <label style="display: block; margin-bottom: 8px;">
                                                        <input type="checkbox" name="rcf_settings[enabled_post_types][]"
                                                            value="<?php echo esc_attr( $post_type->name ); ?>"
                                                            <?php checked( in_array( $post_type->name, $settings['enabled_post_types'] ) ); ?>>
                                                        <strong><?php echo esc_html( $post_type->label ); ?></strong>
                                                        <code style="margin-left: 5px; font-size: 11px;"><?php echo esc_html( $post_type->name ); ?></code>
                                                    </label>
                                                <?php endforeach; ?>
                                            </fieldset>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ( ! empty( $custom_types ) ) : ?>
                                        <div class="rcf-post-type-group">
                                            <h4><?php _e( 'Custom Post Types', 'rcp-content-filter' ); ?></h4>
                                            <fieldset>
                                                <?php foreach ( $custom_types as $post_type ) : ?>
                                                    <label style="display: block; margin-bottom: 8px;">
                                                        <input type="checkbox" name="rcf_settings[enabled_post_types][]"
                                                            value="<?php echo esc_attr( $post_type->name ); ?>"
                                                            <?php checked( in_array( $post_type->name, $settings['enabled_post_types'] ) ); ?>>
                                                        <strong><?php echo esc_html( $post_type->label ); ?></strong>
                                                        <code style="margin-left: 5px; font-size: 11px;"><?php echo esc_html( $post_type->name ); ?></code>
                                                        <?php if ( ! empty( $post_type->description ) ) : ?>
                                                            <br><span class="description" style="margin-left: 25px;"><?php echo esc_html( $post_type->description ); ?></span>
                                                        <?php endif; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </fieldset>
                                        </div>
                                        <?php else : ?>
                                        <div class="rcf-post-type-group">
                                            <p class="description"><?php _e( 'No custom post types found. Custom post types like "resource", "product", etc. will appear here when registered.', 'rcp-content-filter' ); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="margin-top: 15px;">
                                        <button type="button" class="button" onclick="rcfToggleAll(true)"><?php _e( 'Select All', 'rcp-content-filter' ); ?></button>
                                        <button type="button" class="button" onclick="rcfToggleAll(false)"><?php _e( 'Deselect All', 'rcp-content-filter' ); ?></button>
                                        <button type="button" class="button" onclick="rcfToggleCustom()"><?php _e( 'Toggle Custom Types', 'rcp-content-filter' ); ?></button>
                                    </div>

                                    <p class="description" style="margin-top: 15px;">
                                        <?php _e( 'Select which post types should have restricted content filtered from archive pages and grids.', 'rcp-content-filter' ); ?><br>
                                        <?php _e( 'This includes your custom post types like "resource", "product", "event", etc.', 'rcp-content-filter' ); ?>
                                    </p>

                                    <script>
                                    function rcfToggleAll(checked) {
                                        document.querySelectorAll('input[name="rcf_settings[enabled_post_types][]"]').forEach(function(el) {
                                            el.checked = checked;
                                        });
                                    }
                                    function rcfToggleCustom() {
                                        var customGroup = document.querySelectorAll('.rcf-post-type-group')[1];
                                        if (customGroup) {
                                            customGroup.querySelectorAll('input[type="checkbox"]').forEach(function(el) {
                                                el.checked = !el.checked;
                                            });
                                        }
                                    }
                                    </script>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="custom_post_types"><?php _e( 'Additional Post Types', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="custom_post_types" name="rcf_settings[custom_post_types]"
                                        value="<?php echo esc_attr( implode( ', ', $settings['custom_post_types'] ?? [] ) ); ?>"
                                        placeholder="<?php esc_attr_e( 'e.g., resource, event, portfolio', 'rcp-content-filter' ); ?>"
                                        class="regular-text">
                                    <p class="description">
                                        <?php _e( 'Manually add post type names separated by commas. Use this for:', 'rcp-content-filter' ); ?><br>
                                        • <?php _e( 'Post types that are not showing in the list above', 'rcp-content-filter' ); ?><br>
                                        • <?php _e( 'Post types registered by themes/plugins after this settings page loads', 'rcp-content-filter' ); ?><br>
                                        • <?php _e( 'Future post types you plan to add', 'rcp-content-filter' ); ?><br>
                                        <strong><?php _e( 'Example:', 'rcp-content-filter' ); ?></strong> <code>resource, team_member, testimonial</code>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary"
                                value="<?php esc_attr_e( 'Save Settings', 'rcp-content-filter' ); ?>">
                            <button type="button" class="button" onclick="if(confirm('<?php esc_attr_e( 'Clear all cached restriction data?', 'rcp-content-filter' ); ?>')) { document.getElementById('clear-cache').value = '1'; this.form.submit(); }">
                                <?php _e( 'Clear Cache', 'rcp-content-filter' ); ?>
                            </button>
                            <input type="hidden" id="clear-cache" name="rcf_clear_cache" value="">
                        </p>
                    </form>
                </div>

                <div class="rcf-sidebar">
                    <div class="rcf-info-box">
                        <h3><?php _e( 'How It Works', 'rcp-content-filter' ); ?></h3>
                        <p><?php _e( 'This plugin automatically filters out content that users do not have access to based on their Restrict Content Pro membership levels.', 'rcp-content-filter' ); ?></p>
                        <ul>
                            <li><?php _e( 'Works on archive pages, home pages, and post grids', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Respects all RCP restriction settings', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Caches results for better performance', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Automatically clears cache when memberships change', 'rcp-content-filter' ); ?></li>
                        </ul>
                    </div>

                    <div class="rcf-info-box">
                        <h3><?php _e( 'Important Notes', 'rcp-content-filter' ); ?></h3>
                        <ul>
                            <li><?php _e( 'This affects frontend display only', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Admin users always see all content', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Does not affect single post/page views', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Clear cache after major content changes', 'rcp-content-filter' ); ?></li>
                        </ul>
                    </div>

                    <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
                    <div class="rcf-info-box rcf-debug">
                        <h3><?php _e( 'Debug Info', 'rcp-content-filter' ); ?></h3>
                        <p><strong><?php _e( 'Current User ID:', 'rcp-content-filter' ); ?></strong> <?php echo get_current_user_id(); ?></p>
                        <p><strong><?php _e( 'Active Post Types:', 'rcp-content-filter' ); ?></strong></p>
                        <code><?php echo implode( ', ', $settings['enabled_post_types'] ); ?></code>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ( $current_tab === 'loqate' ) : ?>
            <?php $this->render_loqate_tab(); ?>
            <?php elseif ( $current_tab === 'stripe-migration' ) : ?>
            <?php $this->render_stripe_migration_tab(); ?>
            <?php elseif ( $current_tab === 'user-import' ) : ?>
            <?php $this->render_user_import_tab(); ?>
            <?php elseif ( $current_tab === 'affiliatewp-import' ) : ?>
            <?php $this->render_affiliatewp_import_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Stripe Migration tab
     */
    private function render_stripe_migration_tab() {
        ?>
        <div class="rcf-settings-wrap" style="margin-top: 20px;">
            <div class="rcf-main-settings">
                <h2><?php _e( 'Stripe Customer & Source ID Migration', 'rcp-content-filter' ); ?></h2>
                <p><?php _e( 'Upload a CSV file to update Stripe customer and source IDs in the database.', 'rcp-content-filter' ); ?></p>

                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field( 'rcf_stripe_migration', 'rcf_stripe_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="stripe_csv"><?php _e( 'CSV File', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <input type="file" name="stripe_csv" id="stripe_csv" accept=".csv" required>
                                <p class="description">
                                    <?php _e( 'Upload a CSV file with the following columns:', 'rcp-content-filter' ); ?><br>
                                    <code>customer_id_old, source_id_old, customer_id_new, source_id_new</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dry_run"><?php _e( 'Dry Run', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="dry_run" id="dry_run" value="1" checked>
                                    <?php _e( 'Preview changes without updating database (recommended for first run)', 'rcp-content-filter' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit_stripe_migration" class="button button-primary" value="<?php esc_attr_e( 'Upload and Process CSV', 'rcp-content-filter' ); ?>">
                    </p>
                </form>

                <div class="rcf-info-box" style="margin-top: 30px;">
                    <h3><?php _e( 'How It Works', 'rcp-content-filter' ); ?></h3>
                    <ol>
                        <li><?php _e( 'The CSV file should contain four columns: customer_id_old, source_id_old, customer_id_new, source_id_new', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'The plugin will find all postmeta records where:', 'rcp-content-filter' ); ?>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <li><code>meta_key = '_stripe_customer_id'</code> <?php _e( 'and', 'rcp-content-filter' ); ?> <code>meta_value = customer_id_old</code></li>
                                <li><code>meta_key = '_stripe_source_id'</code> <?php _e( 'and', 'rcp-content-filter' ); ?> <code>meta_value = source_id_old</code></li>
                            </ul>
                        </li>
                        <li><?php _e( 'These records will be updated with the corresponding new IDs', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Use "Dry Run" mode first to preview changes before making actual updates', 'rcp-content-filter' ); ?></li>
                    </ol>
                </div>

                <div class="rcf-info-box" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
                    <h3 style="color: #856404;"><?php _e( '⚠️ Important Notes', 'rcp-content-filter' ); ?></h3>
                    <ul>
                        <li><?php _e( 'Always backup your database before running this migration', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Test with dry run mode first to verify the changes', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Empty source_id fields in the CSV will be skipped', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'The migration results will be displayed below after processing', 'rcp-content-filter' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render User Import tab
     *
     * @since 1.x.x
     */
    private function render_user_import_tab() {
        // Handle clear results request
        if ( isset( $_GET['clear_import'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'rcf_clear_import' ) ) {
            delete_transient( 'rcf_user_import_' . get_current_user_id() );
            $this->cleanup_batch_options();
            wp_redirect( admin_url( 'admin.php?page=rcp-content-filter&tab=user-import' ) );
            exit;
        }

        // Get import results from either fresh upload or transient
        $import_results = $this->import_results;
        if ( empty( $import_results ) ) {
            $import_results = get_transient( 'rcf_user_import_' . get_current_user_id() );
        }

        // Check if batch processing is in progress (using options for reliability on WP Engine)
        $batch_progress = get_option( 'rcf_user_batch_progress_' . get_current_user_id() );

        ?>
        <div class="rcf-settings-wrap" style="margin-top: 20px;">
            <div class="rcf-main-settings">
                <h2><?php _e( 'User Import', 'rcp-content-filter' ); ?></h2>

                <?php if ( ! empty( $batch_progress ) && $batch_progress['status'] === 'processing' ) : ?>
                    <!-- Batch Processing Progress -->
                    <div id="rcf-batch-progress-container" class="notice notice-info" style="margin: 20px 0; padding: 20px; border-left: 4px solid #0073aa;" data-batch-active="true">
                        <h3 style="margin-top: 0;"><?php _e( 'Creating WordPress Users with Customer Role...', 'rcp-content-filter' ); ?></h3>
                        <p><strong><?php _e( 'Please do not close this page.', 'rcp-content-filter' ); ?></strong></p>

                        <!-- Progress Bar -->
                        <div style="background: #f0f0f1; height: 40px; border-radius: 4px; overflow: hidden; margin: 15px 0; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                            <div id="rcf-progress-bar" style="background: linear-gradient(90deg, #0073aa 0%, #005a87 100%); height: 100%; width: <?php echo esc_attr( $batch_progress['progress_percent'] ); ?>%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center;">
                                <span id="rcf-progress-percent" style="color: white; font-weight: bold; font-size: 16px; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                                    <?php echo esc_html( $batch_progress['progress_percent'] ); ?>%
                                </span>
                            </div>
                        </div>

                        <!-- Progress Details -->
                        <div style="font-size: 14px; margin-top: 10px;">
                            <p>
                                <strong><?php _e( 'Progress:', 'rcp-content-filter' ); ?></strong>
                                <span id="rcf-progress-text">
                                    <?php printf( __( '%d of %d users created with Customer role', 'rcp-content-filter' ),
                                        esc_html( $batch_progress['processed'] ),
                                        esc_html( $batch_progress['total'] )
                                    ); ?>
                                </span>
                            </p>
                            <p>
                                <strong><?php _e( 'Current Batch:', 'rcp-content-filter' ); ?></strong>
                                <span id="rcf-batch-number">
                                    <?php echo esc_html( $batch_progress['current_batch'] + 1 ); ?>
                                    <?php _e( 'of', 'rcp-content-filter' ); ?>
                                    <?php echo esc_html( ceil( $batch_progress['total'] / 10 ) ); ?>
                                </span>
                            </p>
                            <p id="rcf-failed-count" style="<?php echo $batch_progress['failed'] > 0 ? 'color: #d63638;' : 'display: none;'; ?>">
                                <strong><?php _e( 'Failed:', 'rcp-content-filter' ); ?></strong>
                                <span><?php echo esc_html( $batch_progress['failed'] ); ?></span>
                            </p>
                            <p style="color: #46b450; font-weight: 500;">
                                <span id="rcf-status-message">⏳ <?php _e( 'Processing...', 'rcp-content-filter' ); ?></span>
                            </p>
                        </div>

                        <p style="margin-top: 15px; font-size: 12px; color: #666;">
                            <?php _e( 'Processing 10 users per batch to avoid server timeouts...', 'rcp-content-filter' ); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <!-- Info Box -->
                    <div class="rcf-info-box">
                        <h3><?php _e( 'How It Works', 'rcp-content-filter' ); ?></h3>
                        <ol>
                            <li><?php _e( 'Upload a CSV file with Email, First Name, and Last Name columns', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'The system checks each email against WordPress users database', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Missing users are automatically created with Customer role in batches of 10', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Results display in a sortable table with 100 records per page', 'rcp-content-filter' ); ?></li>
                            <li><?php _e( 'Users found in WordPress appear first with their roles', 'rcp-content-filter' ); ?></li>
                        </ol>
                    </div>

                    <!-- Upload Form -->
                    <h3><?php _e( 'Upload CSV File', 'rcp-content-filter' ); ?></h3>
                    <form method="post" enctype="multipart/form-data" action="">
                        <?php wp_nonce_field( 'rcf_user_import', 'rcf_user_import_nonce' ); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="user_import_csv"><?php _e( 'CSV File', 'rcp-content-filter' ); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="user_import_csv" id="user_import_csv" accept=".csv" required>
                                    <p class="description">
                                        <?php _e( 'Upload a CSV file with columns:', 'rcp-content-filter' ); ?>
                                        <code>Email</code>, <code>First Name</code>, <code>Last Name</code>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit_user_import" class="button button-primary"
                                   value="<?php esc_attr_e( 'Upload and Check Users', 'rcp-content-filter' ); ?>">
                            <?php if ( ! empty( $import_results ) ) : ?>
                                <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=rcp-content-filter&tab=user-import&clear_import=1' ), 'rcf_clear_import' ); ?>"
                                   class="button"
                                   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the current results?', 'rcp-content-filter' ); ?>');">
                                    <?php _e( 'Clear Results', 'rcp-content-filter' ); ?>
                                </a>
                            <?php endif; ?>
                        </p>
                    </form>
                <?php endif; ?>

                <?php
                // Display results if available
                if ( ! empty( $import_results ) && is_array( $import_results ) ) {
                    // Calculate statistics
                    $total_rows = count( $import_results );
                    $wp_users_count = count( array_filter( $import_results, function( $row ) {
                        return $row['wp_user_exists'];
                    } ) );
                    $non_wp_users_count = $total_rows - $wp_users_count;
                    ?>

                    <!-- Summary Statistics -->
                    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                        <h3><?php _e( 'Import Summary', 'rcp-content-filter' ); ?></h3>
                        <div style="display: flex; gap: 20px; margin-top: 15px;">
                            <div style="flex: 1; text-align: center;">
                                <div style="font-size: 32px; font-weight: 600; margin-bottom: 5px;">
                                    <?php echo esc_html( $total_rows ); ?>
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    <?php _e( 'Total Records', 'rcp-content-filter' ); ?>
                                </div>
                            </div>
                            <div style="flex: 1; text-align: center;">
                                <div style="font-size: 32px; font-weight: 600; color: #00a32a; margin-bottom: 5px;">
                                    <?php echo esc_html( $wp_users_count ); ?>
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    <?php _e( 'WordPress Users Found', 'rcp-content-filter' ); ?>
                                </div>
                            </div>
                            <div style="flex: 1; text-align: center;">
                                <div style="font-size: 32px; font-weight: 600; color: #d63638; margin-bottom: 5px;">
                                    <?php echo esc_html( $non_wp_users_count ); ?>
                                </div>
                                <div style="font-size: 13px; color: #666;">
                                    <?php _e( 'Not in WordPress', 'rcp-content-filter' ); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <h3><?php _e( 'Results', 'rcp-content-filter' ); ?></h3>
                    <?php
                    $list_table = new RCF_User_Import_List_Table( $import_results );
                    $list_table->prepare_items();
                    $list_table->display();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Process Stripe migration CSV
     */
    private function process_stripe_migration() {
        global $wpdb;

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'rcp-content-filter' ) );
        }

        // Check if file was uploaded
        if ( ! isset( $_FILES['stripe_csv'] ) || $_FILES['stripe_csv']['error'] !== UPLOAD_ERR_OK ) {
            add_settings_error(
                'rcf_messages',
                'rcf_csv_upload_error',
                __( 'Error uploading CSV file. Please try again.', 'rcp-content-filter' ),
                'error'
            );
            return;
        }

        $dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === '1';
        $file_path = $_FILES['stripe_csv']['tmp_name'];

        // Parse CSV
        $csv_data = $this->parse_stripe_csv( $file_path );

        if ( empty( $csv_data ) ) {
            add_settings_error(
                'rcf_messages',
                'rcf_csv_parse_error',
                __( 'Error parsing CSV file or file is empty.', 'rcp-content-filter' ),
                'error'
            );
            return;
        }

        // Process updates
        $results = array(
            'customer_id_updates' => 0,
            'customer_id_unchanged' => 0,
            'source_id_updates' => 0,
            'source_id_unchanged' => 0,
            'skipped' => 0,
            'errors' => array(),
            'details' => array()
        );

        foreach ( $csv_data as $row_num => $row ) {
            $customer_id_old = trim( $row['customer_id_old'] ?? '' );
            $source_id_old = trim( $row['source_id_old'] ?? '' );
            $customer_id_new = trim( $row['customer_id_new'] ?? '' );
            $source_id_new = trim( $row['source_id_new'] ?? '' );

            // Update customer ID if both old and new are present
            if ( ! empty( $customer_id_old ) && ! empty( $customer_id_new ) ) {
                if ( $customer_id_old !== $customer_id_new ) {
                    // Customer ID is changing - update it
                    $affected = $this->update_stripe_meta(
                        '_stripe_customer_id',
                        $customer_id_old,
                        $customer_id_new,
                        $dry_run
                    );

                    if ( $affected !== false ) {
                        $results['customer_id_updates'] += $affected;
                        if ( $affected > 0 ) {
                            $results['details'][] = sprintf(
                                '%s: Updated %d record(s) for customer ID: %s → %s',
                                $dry_run ? 'DRY RUN' : 'UPDATED',
                                $affected,
                                $customer_id_old,
                                $customer_id_new
                            );
                        }
                    } else {
                        $results['errors'][] = sprintf(
                            'Row %d: Error updating customer ID %s',
                            $row_num + 2,
                            $customer_id_old
                        );
                    }
                } else {
                    // Customer ID unchanged - just count it
                    $results['customer_id_unchanged']++;
                }
            }

            // Update source ID if both old and new are present
            if ( ! empty( $source_id_old ) && ! empty( $source_id_new ) ) {
                if ( $source_id_old !== $source_id_new ) {
                    // Source ID is changing - update it
                    $affected = $this->update_stripe_meta(
                        '_stripe_source_id',
                        $source_id_old,
                        $source_id_new,
                        $dry_run
                    );

                    if ( $affected !== false ) {
                        $results['source_id_updates'] += $affected;
                        if ( $affected > 0 ) {
                            $results['details'][] = sprintf(
                                '%s: Updated %d record(s) for source ID: %s → %s',
                                $dry_run ? 'DRY RUN' : 'UPDATED',
                                $affected,
                                $source_id_old,
                                $source_id_new
                            );
                        }
                    } else {
                        $results['errors'][] = sprintf(
                            'Row %d: Error updating source ID %s',
                            $row_num + 2,
                            $source_id_old
                        );
                    }
                } else {
                    // Source ID unchanged - just count it
                    $results['source_id_unchanged']++;
                }
            } else if ( empty( $source_id_old ) || empty( $source_id_new ) ) {
                $results['skipped']++;
            }
        }

        // Display results
        $this->display_migration_results( $results, $dry_run, count( $csv_data ) );
    }

    /**
     * Parse CSV file
     */
    private function parse_stripe_csv( $file_path ) {
        $data = array();
        $handle = fopen( $file_path, 'r' );

        if ( $handle === false ) {
            return $data;
        }

        // Read header row
        $headers = fgetcsv( $handle );
        if ( $headers === false ) {
            fclose( $handle );
            return $data;
        }

        // Normalize headers
        $headers = array_map( 'trim', $headers );

        // Read data rows
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( count( $row ) === count( $headers ) ) {
                $data[] = array_combine( $headers, $row );
            }
        }

        fclose( $handle );
        return $data;
    }

    /**
     * Parse user import CSV file
     *
     * @since 1.x.x
     * @param string $file_path Path to uploaded CSV file
     * @return array|false Array of parsed data or false on error
     */
    private function parse_user_import_csv( $file_path ) {
        $data = array();
        $handle = fopen( $file_path, 'r' );

        if ( $handle === false ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_open_error',
                __( 'Unable to open CSV file.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        // Read and validate header
        $headers = fgetcsv( $handle );
        if ( $headers === false ) {
            fclose( $handle );
            add_settings_error( 'rcf_messages', 'rcf_csv_empty',
                __( 'CSV file is empty or invalid.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        // Map headers (case-insensitive)
        $header_map = array();
        foreach ( $headers as $index => $header ) {
            $normalized = strtolower( trim( $header ) );
            $header_map[ $normalized ] = $index;
        }

        // Validate required columns
        $required = array( 'email', 'first name', 'last name' );
        $missing = array();
        foreach ( $required as $req ) {
            if ( ! isset( $header_map[ $req ] ) ) {
                $missing[] = $req;
            }
        }

        if ( ! empty( $missing ) ) {
            fclose( $handle );
            add_settings_error( 'rcf_messages', 'rcf_csv_missing_columns',
                sprintf( __( 'CSV is missing required columns: %s', 'rcp-content-filter' ),
                    implode( ', ', $missing ) ), 'error' );
            return false;
        }

        // Parse data rows
        $seen_emails = array();
        $duplicates = 0;
        $invalid_rows = 0;

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $email = trim( $row[ $header_map['email'] ] );

            // Skip invalid emails
            if ( empty( $email ) || ! is_email( $email ) ) {
                $invalid_rows++;
                continue;
            }

            $email_lower = strtolower( $email );

            // Skip duplicates (keep first occurrence)
            if ( isset( $seen_emails[ $email_lower ] ) ) {
                $duplicates++;
                continue;
            }

            $seen_emails[ $email_lower ] = true;

            $data[] = array(
                'email'      => $email,
                'first_name' => trim( $row[ $header_map['first name'] ] ),
                'last_name'  => trim( $row[ $header_map['last name'] ] ),
            );
        }

        fclose( $handle );

        // Report issues
        if ( $duplicates > 0 ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_duplicates',
                sprintf( __( 'Note: %d duplicate email(s) skipped.', 'rcp-content-filter' ),
                    $duplicates ), 'warning' );
        }

        if ( $invalid_rows > 0 ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_invalid',
                sprintf( __( 'Warning: %d row(s) skipped due to invalid emails.', 'rcp-content-filter' ),
                    $invalid_rows ), 'warning' );
        }

        if ( empty( $data ) ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_no_data',
                __( 'No valid data rows found in CSV.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        return $data;
    }

    /**
     * Efficiently lookup multiple users by email in single query
     *
     * @since 1.x.x
     * @param array $emails Array of email addresses
     * @return array Associative array mapping email => user data
     */
    private function batch_lookup_users( $emails ) {
        global $wpdb;

        $normalized_emails = array_unique( array_map( 'trim', $emails ) );
        $normalized_emails = array_values( $normalized_emails );

        if ( empty( $normalized_emails ) ) {
            return array();
        }

        // Chunk emails to avoid query size limits on large IN clauses
        $email_chunks = array_chunk( $normalized_emails, 50 );
        $all_users    = array();

        foreach ( $email_chunks as $email_chunk ) {
            $escaped_emails = array_map( function( $email ) use ( $wpdb ) {
                return "'" . $wpdb->_real_escape( strtolower( $email ) ) . "'";
            }, $email_chunk );

            $in_clause = implode( ',', $escaped_emails );

            $query = "SELECT ID, user_email, user_login, display_name
                      FROM {$wpdb->users}
                      WHERE LOWER(user_email) IN ($in_clause)";

            $chunk_users = $wpdb->get_results( $query );

            if ( ! empty( $chunk_users ) ) {
                $all_users = array_merge( $all_users, $chunk_users );
            }
        }

        // Build lookup map: email => user data
        $user_map = array();
        foreach ( $all_users as $user ) {
            $email_key = strtolower( $user->user_email );
            $wp_user   = new WP_User( $user->ID );

            $user_map[ $email_key ] = array(
                'ID'           => $user->ID,
                'email'        => $user->user_email,
                'login'        => $user->user_login,
                'display_name' => $user->display_name,
                'roles'        => $wp_user->roles,
            );
        }

        return $user_map;
    }

    /**
     * Initialize batch user creation process
     *
     * @since 1.x.x
     * @param array $csv_data Full CSV data including all users
     * @param array $user_map Existing users from lookup
     * @return array Updated user map
     */
    private function initialize_batch_user_creation( $csv_data, $user_map ) {
        // Build list of users that need to be created
        $users_to_create = array();
        foreach ( $csv_data as $row ) {
            $email_key = strtolower( $row['email'] );

            // Skip if user already exists
            if ( isset( $user_map[ $email_key ] ) ) {
                continue;
            }

            $users_to_create[] = array(
                'email'      => $row['email'],
                'email_key'  => $email_key,
                'first_name' => $row['first_name'],
                'last_name'  => $row['last_name'],
            );
        }

        if ( empty( $users_to_create ) ) {
            // No users to create, just return the map
            return $user_map;
        }

        // Store batch data in options (more reliable than transients on WP Engine with object caching)
        // Include full CSV data to build final results with all users
        update_option( 'rcf_user_batch_data_' . get_current_user_id(), array(
            'users_to_create' => $users_to_create,
            'user_map'        => $user_map,
            'csv_data'        => $csv_data, // Store full CSV for final results
            'expires'         => time() + HOUR_IN_SECONDS,
        ), false ); // autoload = false

        // Initialize progress tracking
        update_option( 'rcf_user_batch_progress_' . get_current_user_id(), array(
            'status'           => 'processing',
            'total'            => count( $users_to_create ),
            'processed'        => 0,
            'failed'           => 0,
            'current_batch'    => 0,
            'progress_percent' => 0,
            'expires'          => time() + HOUR_IN_SECONDS,
        ), false ); // autoload = false

        return $user_map;
    }

    /**
     * Continue batch user creation (called via GET or POST)
     *
     * @since 1.x.x
     */
    private function continue_batch_user_creation() {
        // Get batch data from options (more reliable than transients on WP Engine)
        $batch_data = get_option( 'rcf_user_batch_data_' . get_current_user_id() );
        $progress = get_option( 'rcf_user_batch_progress_' . get_current_user_id() );

        // Check if data exists and hasn't expired
        if ( empty( $batch_data ) || empty( $progress ) ) {
            add_settings_error( 'rcf_messages', 'rcf_batch_error',
                __( 'Batch processing session expired. Please upload the CSV again.', 'rcp-content-filter' ),
                'error' );
            return array( 'error' => true, 'message' => 'Session expired' );
        }

        // Check expiration
        if ( isset( $batch_data['expires'] ) && $batch_data['expires'] < time() ) {
            $this->cleanup_batch_options();
            return array( 'error' => true, 'message' => 'Session expired' );
        }

        $users_to_create = $batch_data['users_to_create'];
        $user_map = $batch_data['user_map'];

        // Extend PHP limits for batch processing
        @set_time_limit( 120 ); // 2 minutes per batch
        @ini_set( 'memory_limit', '256M' );
        ignore_user_abort( true ); // Continue even if browser disconnects

        // Suspend cache additions to reduce memory usage
        wp_suspend_cache_addition( true );

        // Process batch of 10 users (reduced from 25 to avoid timeouts)
        $batch_size = 10;
        $start_index = $progress['current_batch'] * $batch_size;
        $batch_users = array_slice( $users_to_create, $start_index, $batch_size );

        if ( empty( $batch_users ) ) {
            // All done!
            return $this->finalize_batch_user_creation( $user_map, $progress );
        }

        // Pre-generate usernames for this batch
        $username_map = array();
        $potential_usernames = array();

        foreach ( $batch_users as $index => $user_data ) {
            $username_base = sanitize_user( substr( $user_data['email'], 0, strpos( $user_data['email'], '@' ) ) );
            $username_map[ $index ] = $username_base;
            $potential_usernames[] = $username_base;
        }

        // Batch check which usernames exist
        $existing_usernames = $this->batch_check_usernames( $potential_usernames );

        // Create users in this batch
        foreach ( $batch_users as $index => $user_data ) {
            $username_base = $username_map[ $index ];
            $username = $username_base;

            // Find unique username
            $counter = 1;
            while ( isset( $existing_usernames[ $username ] ) ) {
                $username = $username_base . $counter;
                $counter++;
            }

            // Mark username as taken
            $existing_usernames[ $username ] = true;

            // Create WordPress user
            $user_id = wp_insert_user( array(
                'user_login' => $username,
                'user_email' => $user_data['email'],
                'first_name' => $user_data['first_name'],
                'last_name'  => $user_data['last_name'],
                'user_pass'  => wp_generate_password( 16, true, true ),
                'role'       => 'customer',
            ) );

            if ( is_wp_error( $user_id ) ) {
                error_log( '[RCF] Failed to create user for ' . $user_data['email'] . ': ' . $user_id->get_error_message() );
                $progress['failed']++;
            } else {
                // Add to user map
                $user_map[ $user_data['email_key'] ] = array(
                    'ID'           => $user_id,
                    'email'        => $user_data['email'],
                    'login'        => $username,
                    'display_name' => $user_data['first_name'] . ' ' . $user_data['last_name'],
                    'roles'        => array( 'customer' ),
                );
                $progress['processed']++;
            }
        }

        // Update progress
        $progress['current_batch']++;
        $progress['progress_percent'] = round( ( $progress['processed'] / $progress['total'] ) * 100 );

        // Re-enable cache additions before saving
        wp_suspend_cache_addition( false );

        // Save updated progress and user map to options
        update_option( 'rcf_user_batch_progress_' . get_current_user_id(), $progress, false );
        $batch_data['user_map'] = $user_map;
        update_option( 'rcf_user_batch_data_' . get_current_user_id(), $batch_data, false );

        // Clear local object cache to free memory (but NOT wp_cache_flush which affects transients)
        global $wp_object_cache;
        if ( is_object( $wp_object_cache ) && method_exists( $wp_object_cache, 'flush_runtime' ) ) {
            $wp_object_cache->flush_runtime();
        }

        // Return progress data for AJAX response
        return $progress;
    }

    /**
     * Finalize batch user creation and build final results
     *
     * @since 1.x.x
     * @param array $user_map Final user map with all created users
     * @param array $progress Progress tracking data
     */
    private function finalize_batch_user_creation( $user_map, $progress ) {
        // Get original CSV data from option to build final results
        $batch_data = get_option( 'rcf_user_batch_data_' . get_current_user_id() );

        if ( empty( $batch_data ) || empty( $batch_data['csv_data'] ) ) {
            add_settings_error( 'rcf_messages', 'rcf_batch_error',
                __( 'Unable to finalize import. Session data missing.', 'rcp-content-filter' ),
                'error' );
            return array( 'error' => true, 'message' => 'Session data missing during finalization' );
        }

        // Build results from FULL CSV data (includes existing + newly created users)
        $results = array();
        foreach ( $batch_data['csv_data'] as $row ) {
            $email_key = strtolower( $row['email'] );
            $user_found = isset( $user_map[ $email_key ] );

            $results[] = array(
                'email'          => $row['email'],
                'first_name'     => $row['first_name'],
                'last_name'      => $row['last_name'],
                'wp_user_exists' => $user_found,
                'wp_user_id'     => $user_found ? $user_map[ $email_key ]['ID'] : null,
                'user_roles'     => $user_found ? $user_map[ $email_key ]['roles'] : array(),
                'display_name'   => $user_found ? $user_map[ $email_key ]['display_name'] : '',
            );
        }

        // Store final results
        set_transient( 'rcf_user_import_' . get_current_user_id(), $results, HOUR_IN_SECONDS );

        // Success messages
        $created_count = $progress['processed'];
        $failed_count = $progress['failed'];

        if ( $created_count > 0 ) {
            add_settings_error( 'rcf_messages', 'rcf_users_created',
                sprintf( __( 'Successfully created %d new WordPress user(s) with Customer role.', 'rcp-content-filter' ),
                    $created_count ), 'success' );
        }

        if ( $failed_count > 0 ) {
            add_settings_error( 'rcf_messages', 'rcf_users_failed',
                sprintf( __( 'Failed to create %d user(s). Check error log for details.', 'rcp-content-filter' ),
                    $failed_count ), 'warning' );
        }

        // Clean up batch options
        $this->cleanup_batch_options();

        // Return completion status for AJAX
        return array(
            'complete'      => true,
            'created_count' => $created_count,
            'failed_count'  => $failed_count,
        );
    }

    /**
     * Clean up batch processing options
     *
     * @since 1.x.x
     */
    private function cleanup_batch_options() {
        delete_option( 'rcf_user_batch_progress_' . get_current_user_id() );
        delete_option( 'rcf_user_batch_data_' . get_current_user_id() );
    }

    /**
     * Batch check which usernames already exist in database
     *
     * @since 1.x.x
     * @param array $usernames Array of usernames to check
     * @return array Associative array of existing usernames (username => true)
     */
    private function batch_check_usernames( $usernames ) {
        global $wpdb;

        if ( empty( $usernames ) ) {
            return array();
        }

        $existing = array();
        $unique_usernames = array_unique( $usernames );

        // Chunk usernames to avoid query size limits
        $username_chunks = array_chunk( $unique_usernames, 100 );

        foreach ( $username_chunks as $chunk ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
            $query = $wpdb->prepare(
                "SELECT user_login FROM {$wpdb->users} WHERE user_login IN ($placeholders)",
                $chunk
            );

            $results = $wpdb->get_col( $query );

            foreach ( $results as $username ) {
                $existing[ $username ] = true;
            }
        }

        return $existing;
    }

    /**
     * Process uploaded CSV file and check users
     *
     * @since 1.x.x
     * @return array|false Processed results or false on error
     */
    private function process_user_import_csv() {
        // Security checks
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'rcp-content-filter' ) );
        }

        // Validate file upload
        if ( ! isset( $_FILES['user_import_csv'] ) ||
             $_FILES['user_import_csv']['error'] !== UPLOAD_ERR_OK ) {

            $error_msg = __( 'Error uploading CSV file.', 'rcp-content-filter' );

            if ( isset( $_FILES['user_import_csv']['error'] ) ) {
                switch ( $_FILES['user_import_csv']['error'] ) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg .= ' ' . __( 'File is too large.', 'rcp-content-filter' );
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_msg .= ' ' . __( 'No file was uploaded.', 'rcp-content-filter' );
                        break;
                }
            }

            add_settings_error( 'rcf_messages', 'rcf_upload_error', $error_msg, 'error' );
            return false;
        }

        // Validate file type
        $file_name = $_FILES['user_import_csv']['name'];
        $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

        if ( $file_ext !== 'csv' ) {
            add_settings_error( 'rcf_messages', 'rcf_invalid_file_type',
                __( 'Please upload a CSV file.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        // Parse CSV
        $file_path = $_FILES['user_import_csv']['tmp_name'];
        $csv_data = $this->parse_user_import_csv( $file_path );

        if ( $csv_data === false ) {
            return false; // Errors already set by parse method
        }

        // Extract emails for batch lookup
        $emails = array_column( $csv_data, 'email' );

        // Batch lookup users
        $user_map = $this->batch_lookup_users( $emails );

        // Initialize batch creation for missing users (don't create them all now)
        $user_map = $this->initialize_batch_user_creation( $csv_data, $user_map );

        // Check if batch processing was started (using options for reliability on WP Engine)
        $batch_progress = get_option( 'rcf_user_batch_progress_' . get_current_user_id() );

        if ( ! empty( $batch_progress ) && $batch_progress['status'] === 'processing' ) {
            // Batch processing started - JavaScript will handle the AJAX calls
            return false; // Don't show results yet, batch is processing
        }

        // All users existed - build results immediately
        $results = array();
        foreach ( $csv_data as $row ) {
            $email_key = strtolower( $row['email'] );
            $user_found = isset( $user_map[ $email_key ] );

            $results[] = array(
                'email'          => $row['email'],
                'first_name'     => $row['first_name'],
                'last_name'      => $row['last_name'],
                'wp_user_exists' => $user_found,
                'wp_user_id'     => $user_found ? $user_map[ $email_key ]['ID'] : null,
                'user_roles'     => $user_found ? $user_map[ $email_key ]['roles'] : array(),
                'display_name'   => $user_found ? $user_map[ $email_key ]['display_name'] : '',
            );
        }

        // Success message
        add_settings_error( 'rcf_messages', 'rcf_import_success',
            sprintf( __( 'Successfully processed %d records. All users already exist.', 'rcp-content-filter' ),
                count( $results ) ), 'success' );

        // Store results in transient for pagination (expires in 1 hour)
        set_transient( 'rcf_user_import_' . get_current_user_id(), $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Update Stripe metadata in database
     */
    private function update_stripe_meta( $meta_key, $old_value, $new_value, $dry_run = false ) {
        global $wpdb;

        if ( $dry_run ) {
            // Just count the records that would be updated
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                $meta_key,
                $old_value
            ) );
            return (int) $count;
        } else {
            // Actually update the records
            $updated = $wpdb->update(
                $wpdb->postmeta,
                array( 'meta_value' => $new_value ),
                array(
                    'meta_key' => $meta_key,
                    'meta_value' => $old_value
                ),
                array( '%s' ),
                array( '%s', '%s' )
            );
            return $updated;
        }
    }

    /**
     * Display migration results
     */
    private function display_migration_results( $results, $dry_run, $total_rows ) {
        $message_type = empty( $results['errors'] ) ? 'success' : 'warning';

        ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible" style="margin-top: 20px;">
            <h3><?php echo $dry_run ? __( '🔍 Dry Run Results (No Changes Made)', 'rcp-content-filter' ) : __( '✅ Migration Complete', 'rcp-content-filter' ); ?></h3>
            <p><strong><?php _e( 'Summary:', 'rcp-content-filter' ); ?></strong></p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php printf( __( 'Total CSV rows processed: %d', 'rcp-content-filter' ), $total_rows ); ?></li>
                <li><strong><?php _e( 'Customer IDs:', 'rcp-content-filter' ); ?></strong>
                    <ul style="list-style-type: circle; margin-left: 20px;">
                        <li><?php printf( __( '%s: %d', 'rcp-content-filter' ), $dry_run ? 'Will be updated' : 'Updated', $results['customer_id_updates'] ); ?></li>
                        <li><?php printf( __( 'Already correct (no change needed): %d', 'rcp-content-filter' ), $results['customer_id_unchanged'] ); ?></li>
                    </ul>
                </li>
                <li><strong><?php _e( 'Source IDs:', 'rcp-content-filter' ); ?></strong>
                    <ul style="list-style-type: circle; margin-left: 20px;">
                        <li><?php printf( __( '%s: %d', 'rcp-content-filter' ), $dry_run ? 'Will be updated' : 'Updated', $results['source_id_updates'] ); ?></li>
                        <li><?php printf( __( 'Already correct (no change needed): %d', 'rcp-content-filter' ), $results['source_id_unchanged'] ); ?></li>
                    </ul>
                </li>
                <li><?php printf( __( 'Rows skipped (empty/missing IDs): %d', 'rcp-content-filter' ), $results['skipped'] ); ?></li>
                <?php if ( ! empty( $results['errors'] ) ) : ?>
                <li style="color: #d63638;"><?php printf( __( 'Errors: %d', 'rcp-content-filter' ), count( $results['errors'] ) ); ?></li>
                <?php endif; ?>
            </ul>

            <?php if ( ! empty( $results['details'] ) && count( $results['details'] ) <= 50 ) : ?>
            <details style="margin-top: 15px;">
                <summary style="cursor: pointer; font-weight: bold;"><?php _e( 'View Details', 'rcp-content-filter' ); ?></summary>
                <div style="max-height: 400px; overflow-y: auto; margin-top: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">
                    <pre style="margin: 0; white-space: pre-wrap;"><?php echo esc_html( implode( "\n", $results['details'] ) ); ?></pre>
                </div>
            </details>
            <?php elseif ( count( $results['details'] ) > 50 ) : ?>
            <p><em><?php printf( __( '%d updates processed (too many to display)', 'rcp-content-filter' ), count( $results['details'] ) ); ?></em></p>
            <?php endif; ?>

            <?php if ( ! empty( $results['errors'] ) ) : ?>
            <details style="margin-top: 15px;">
                <summary style="cursor: pointer; font-weight: bold; color: #d63638;"><?php _e( 'View Errors', 'rcp-content-filter' ); ?></summary>
                <div style="max-height: 300px; overflow-y: auto; margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107;">
                    <pre style="margin: 0; color: #856404;"><?php echo esc_html( implode( "\n", $results['errors'] ) ); ?></pre>
                </div>
            </details>
            <?php endif; ?>

            <?php if ( $dry_run ) : ?>
            <p style="margin-top: 15px; padding: 10px; background: #d1ecf1; border-left: 4px solid #0c5460; color: #0c5460;">
                <strong><?php _e( 'This was a dry run.', 'rcp-content-filter' ); ?></strong>
                <?php _e( 'No changes were made to the database. Uncheck "Dry Run" to apply the changes.', 'rcp-content-filter' ); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    private function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle cache clear
        if ( ! empty( $_POST['rcf_clear_cache'] ) ) {
            $this->plugin->clear_all_caches();
            add_settings_error(
                'rcf_messages',
                'rcf_cache_cleared',
                __( 'Cache cleared successfully!', 'rcp-content-filter' ),
                'updated'
            );
        }

        // Save settings
        $settings = array(
            'enabled_post_types' => isset( $_POST['rcf_settings']['enabled_post_types'] ) ? array_map( 'sanitize_key', $_POST['rcf_settings']['enabled_post_types'] ) : array(),
            'filter_priority' => isset( $_POST['rcf_settings']['filter_priority'] ) ? intval( $_POST['rcf_settings']['filter_priority'] ) : 10,
            'hide_method' => 'remove', // Always use simple remove method
            'enable_learnpress_fix' => isset( $_POST['rcf_settings']['enable_learnpress_fix'] ) ? true : false,
            'uncheck_ship_to_different_address' => isset( $_POST['rcf_settings']['uncheck_ship_to_different_address'] ) ? true : false
        );

        // Handle custom post types field
        if ( isset( $_POST['rcf_settings']['custom_post_types'] ) ) {
            $custom_types = explode( ',', $_POST['rcf_settings']['custom_post_types'] );
            $custom_types = array_map( 'trim', $custom_types );
            $custom_types = array_map( 'sanitize_key', $custom_types );
            $custom_types = array_filter( $custom_types ); // Remove empty values
            $settings['custom_post_types'] = array_values( $custom_types );
        } else {
            $settings['custom_post_types'] = array();
        }

        $this->plugin->update_settings( $settings );
    }

    /**
     * Render Loqate Integration tab
     */
    private function render_loqate_tab() {
        // Handle Loqate settings form submission
        if ( isset( $_POST['rcf_loqate_nonce'] ) && wp_verify_nonce( $_POST['rcf_loqate_nonce'], 'rcf_save_loqate_settings' ) ) {
            $this->save_loqate_settings();
            wp_redirect( admin_url( 'admin.php?page=rcp-content-filter&tab=loqate&settings-updated=true' ) );
            exit;
        }

        // Get current Loqate settings
        $api_key = get_option( 'rcf_loqate_api_key', '' );
        $allowed_countries = get_option( 'rcf_loqate_allowed_countries', '' );
        $geolocation_enabled = get_option( 'rcf_loqate_geolocation_enabled', 0 );
        $allow_manual_entry = get_option( 'rcf_loqate_allow_manual_entry', 1 );
        $validate_email = get_option( 'rcf_loqate_validate_email', 1 );
        $validate_phone = get_option( 'rcf_loqate_validate_phone', 0 );
        $loqate_status = RCF_Loqate_Address_Capture::get_status();
        ?>
        <div class="rcf-settings-wrap" style="margin-top: 20px;">
            <div class="rcf-main-settings">
                <h2><?php _e( 'Loqate Address Capture Integration', 'rcp-content-filter' ); ?></h2>
                <p><?php _e( 'Configure Loqate Address Capture SDK for real-time address validation on WooCommerce checkout.', 'rcp-content-filter' ); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field( 'rcf_save_loqate_settings', 'rcf_loqate_nonce' ); ?>

                    <table class="form-table">
                        <!-- API Key Configuration -->
                        <tr>
                            <th scope="row">
                                <label for="loqate_api_key"><?php _e( 'Loqate API Key', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="password"
                                    id="loqate_api_key"
                                    name="rcf_loqate_api_key"
                                    value="<?php echo esc_attr( $api_key ); ?>"
                                    class="regular-text"
                                    placeholder="<?php esc_attr_e( 'Enter your Loqate API key', 'rcp-content-filter' ); ?>">
                                <p class="description">
                                    <?php printf(
                                        __( 'Get your API key from your <a href="%s" target="_blank">Loqate dashboard</a>. API keys can also be defined using the %s constant.', 'rcp-content-filter' ),
                                        'https://dashboard.loqate.com/',
                                        '<code>LOQATE_API_KEY</code>'
                                    ); ?>
                                </p>
                                <?php if ( ! empty( $api_key ) ) : ?>
                                    <p style="color: #28a745;">
                                        <strong>✓</strong> <?php _e( 'API key configured', 'rcp-content-filter' ); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- Geolocation Settings -->
                        <tr>
                            <th scope="row">
                                <label><?php _e( 'Geolocation Options', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox" name="rcf_loqate_geolocation_enabled" value="1" <?php checked( $geolocation_enabled ); ?>>
                                        <span><?php _e( 'Enable geolocation-based address suggestions', 'rcp-content-filter' ); ?></span>
                                    </label>
                                    <p class="description">
                                        <?php _e( 'When disabled (default), searches return global results (like "2707 W Avenue" returns USA addresses). When enabled, results are biased toward the user\'s current location.', 'rcp-content-filter' ); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- Address Field Options -->
                        <tr>
                            <th scope="row">
                                <label><?php _e( 'Address Field Options', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox" name="rcf_loqate_allow_manual_entry" value="1" <?php checked( $allow_manual_entry ); ?>>
                                        <span><?php _e( 'Allow Manual Address Entry', 'rcp-content-filter' ); ?></span>
                                    </label>
                                    <p class="description">
                                        <?php _e( 'When enabled, users can manually enter their address if they can\'t find it in the autocomplete dropdown.', 'rcp-content-filter' ); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- Validation Options -->
                        <tr>
                            <th scope="row">
                                <label><?php _e( 'Validation Services', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox" name="rcf_loqate_validate_email" value="1" <?php checked( $validate_email ); ?>>
                                        <span><?php _e( 'Validate Email Addresses', 'rcp-content-filter' ); ?></span>
                                    </label>

                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox" name="rcf_loqate_validate_phone" value="1" <?php checked( $validate_phone ); ?>>
                                        <span><?php _e( 'Validate Phone Numbers', 'rcp-content-filter' ); ?></span>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- Country Restriction -->
                        <tr>
                            <th scope="row">
                                <label for="loqate_allowed_countries"><?php _e( 'Allowed Countries', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="loqate_allowed_countries"
                                    name="rcf_loqate_allowed_countries"
                                    value="<?php echo esc_attr( $allowed_countries ); ?>"
                                    class="regular-text"
                                    placeholder="<?php esc_attr_e( 'e.g., USA,GBR,CAN,AUS', 'rcp-content-filter' ); ?>">
                                <p class="description">
                                    <?php _e( 'Restrict address capture to specific countries. Leave blank to allow all countries. Use ISO 3166-1 alpha-3 country codes separated by commas.', 'rcp-content-filter' ); ?><br>
                                    <strong><?php _e( 'Examples:', 'rcp-content-filter' ); ?></strong> USA, GBR (United Kingdom), CAN (Canada), AUS (Australia), DEU (Germany), FRA (France)
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit_loqate_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Loqate Settings', 'rcp-content-filter' ); ?>">
                    </p>
                </form>

                <!-- Status Information -->
                <div class="rcf-info-box" style="margin-top: 30px;">
                    <h3><?php _e( 'Integration Status', 'rcp-content-filter' ); ?></h3>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>
                            <span style="color: <?php echo $loqate_status['api_key_set'] ? '#00a32a' : '#d63638'; ?>;">●</span>
                            <?php _e( 'API Key:', 'rcp-content-filter' ); ?>
                            <strong><?php echo $loqate_status['api_key_set'] ? __( 'Configured', 'rcp-content-filter' ) : __( 'Not Set', 'rcp-content-filter' ); ?></strong>
                            <?php if ( $loqate_status['api_key_set'] ) : ?>
                                <code style="margin-left: 8px;"><?php echo esc_html( $loqate_status['masked_key'] ); ?></code>
                            <?php endif; ?>
                        </li>
                        <li>
                            <span style="color: <?php echo $loqate_status['enabled'] ? '#00a32a' : '#d63638'; ?>;">●</span>
                            <?php _e( 'Integration:', 'rcp-content-filter' ); ?>
                            <strong><?php echo $loqate_status['enabled'] ? __( 'Enabled', 'rcp-content-filter' ) : __( 'Disabled', 'rcp-content-filter' ); ?></strong>
                        </li>
                        <li>
                            <span style="color: <?php echo $loqate_status['woocommerce'] ? '#00a32a' : '#d63638'; ?>;">●</span>
                            <?php _e( 'WooCommerce:', 'rcp-content-filter' ); ?>
                            <strong><?php echo $loqate_status['woocommerce'] ? __( 'Active', 'rcp-content-filter' ) : __( 'Not Installed', 'rcp-content-filter' ); ?></strong>
                        </li>
                    </ul>
                </div>

                <!-- Documentation -->
                <div class="rcf-info-box" style="margin-top: 20px;">
                    <h3><?php _e( 'Documentation & Resources', 'rcp-content-filter' ); ?></h3>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>
                            <a href="https://docs.loqate.com/introduction" target="_blank">
                                <?php _e( 'Loqate Introduction', 'rcp-content-filter' ); ?>
                            </a>
                        </li>
                        <li>
                            <a href="https://docs.loqate.com/our-services/address-capture/overview" target="_blank">
                                <?php _e( 'Address Capture Overview', 'rcp-content-filter' ); ?>
                            </a>
                        </li>
                        <li>
                            <a href="https://dashboard.loqate.com/" target="_blank">
                                <?php _e( 'Loqate Dashboard', 'rcp-content-filter' ); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for processing user batches
     *
     * @since 1.x.x
     */
    public function ajax_process_user_batch() {
        // Clean any output buffers to ensure clean JSON response
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        // Start fresh output buffer
        ob_start();

        // Verify nonce
        check_ajax_referer( 'rcf_batch_processing', 'nonce' );

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rcp-content-filter' ) ) );
        }

        // Process the next batch
        $result = $this->continue_batch_user_creation();

        // Clean any output that may have occurred during processing
        ob_end_clean();

        // Check for error
        if ( isset( $result['error'] ) && $result['error'] === true ) {
            wp_send_json_error( array( 'message' => $result['message'] ?? __( 'Batch processing failed.', 'rcp-content-filter' ) ) );
            return;
        }

        // Check for completion
        if ( isset( $result['complete'] ) && $result['complete'] === true ) {
            // All done!
            wp_send_json_success( array(
                'complete'      => true,
                'created_count' => $result['created_count'],
                'failed_count'  => $result['failed_count'],
                'message'       => sprintf(
                    __( 'Successfully created %d users with Customer role!', 'rcp-content-filter' ),
                    $result['created_count']
                ),
            ) );
            return;
        }

        // Check for progress update
        if ( is_array( $result ) && isset( $result['processed'] ) ) {
            // Batch completed, more to process
            wp_send_json_success( array(
                'complete'         => false,
                'progress'         => $result['processed'],
                'total'            => $result['total'],
                'failed'           => $result['failed'],
                'progress_percent' => $result['progress_percent'],
                'current_batch'    => $result['current_batch'],
                'total_batches'    => ceil( $result['total'] / 10 ),
            ) );
            return;
        }

        // Unknown error
        wp_send_json_error( array( 'message' => __( 'Batch processing failed unexpectedly.', 'rcp-content-filter' ) ) );
    }

    /**
     * Save Loqate settings
     */
    private function save_loqate_settings() {
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save API Key
        if ( isset( $_POST['rcf_loqate_api_key'] ) ) {
            $api_key = sanitize_text_field( $_POST['rcf_loqate_api_key'] );
            if ( ! empty( $api_key ) ) {
                update_option( 'rcf_loqate_api_key', $api_key );
            } else {
                delete_option( 'rcf_loqate_api_key' );
            }
        }

        // Save Allowed Countries
        if ( isset( $_POST['rcf_loqate_allowed_countries'] ) ) {
            $countries = sanitize_text_field( $_POST['rcf_loqate_allowed_countries'] );
            if ( ! empty( $countries ) ) {
                update_option( 'rcf_loqate_allowed_countries', $countries );
            } else {
                delete_option( 'rcf_loqate_allowed_countries' );
            }
        }

        // Save Geolocation Enable/Disable
        if ( isset( $_POST['rcf_loqate_geolocation_enabled'] ) ) {
            update_option( 'rcf_loqate_geolocation_enabled', 1 );
        } else {
            update_option( 'rcf_loqate_geolocation_enabled', 0 );
        }

        // Save Geolocation Radius
        if ( isset( $_POST['rcf_loqate_geolocation_radius'] ) ) {
            $radius = (int) $_POST['rcf_loqate_geolocation_radius'];
            if ( $radius > 0 ) {
                update_option( 'rcf_loqate_geolocation_radius', $radius );
            }
        }

        // Save Geolocation Max Items
        if ( isset( $_POST['rcf_loqate_geolocation_max_items'] ) ) {
            $max_items = (int) $_POST['rcf_loqate_geolocation_max_items'];
            if ( $max_items > 0 ) {
                update_option( 'rcf_loqate_geolocation_max_items', $max_items );
            }
        }

        // Save Allow Manual Entry
        if ( isset( $_POST['rcf_loqate_allow_manual_entry'] ) ) {
            update_option( 'rcf_loqate_allow_manual_entry', 1 );
        } else {
            update_option( 'rcf_loqate_allow_manual_entry', 0 );
        }

        // Save Email Validation
        if ( isset( $_POST['rcf_loqate_validate_email'] ) ) {
            update_option( 'rcf_loqate_validate_email', 1 );
        } else {
            update_option( 'rcf_loqate_validate_email', 0 );
        }

        // Save Phone Validation
        if ( isset( $_POST['rcf_loqate_validate_phone'] ) ) {
            update_option( 'rcf_loqate_validate_phone', 1 );
        } else {
            update_option( 'rcf_loqate_validate_phone', 0 );
        }
    }

    // ============================================================
    // TEMPORARY FEATURE: AffiliateWP Import
    // Added: 2026-01-03
    // Purpose: One-time fix for lifetime commission customer links
    // Safe to delete: All code between these markers
    // ============================================================

    /**
     * Render AffiliateWP Import tab
     *
     * @since 1.x.x (Temporary Feature)
     */
    private function render_affiliatewp_import_tab() {
        // Handle clear results request
        if ( isset( $_GET['clear_affwp_import'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'rcf_clear_affwp_import' ) ) {
            delete_transient( 'rcf_affiliatewp_import_results_' . get_current_user_id() );
            wp_redirect( admin_url( 'admin.php?page=rcp-content-filter&tab=affiliatewp-import' ) );
            exit;
        }

        // Get import results from transient
        $import_results = get_transient( 'rcf_affiliatewp_import_results_' . get_current_user_id() );

        ?>
        <div class="rcf-settings-wrap" style="margin-top: 20px;">
            <div class="rcf-main-settings">
                <h2><?php _e( 'AffiliateWP Customer-Affiliate Link Import', 'rcp-content-filter' ); ?></h2>

                <!-- Warning Box -->
                <div class="notice notice-warning inline" style="margin: 20px 0; padding: 15px;">
                    <h3 style="margin-top: 0;"><?php _e( '⚠️ Important: Database Modification Tool', 'rcp-content-filter' ); ?></h3>
                    <p><?php _e( 'This tool directly modifies AffiliateWP database tables. Please backup your database before proceeding.', 'rcp-content-filter' ); ?></p>
                    <p><strong><?php _e( 'Dry run mode (enabled by default) is strongly recommended for first use.', 'rcp-content-filter' ); ?></strong></p>
                </div>

                <!-- Info Box -->
                <div class="rcf-info-box">
                    <h3><?php _e( 'How It Works', 'rcp-content-filter' ); ?></h3>
                    <ol>
                        <li><?php _e( 'Upload a CSV file with columns: <code>customer_user_id</code>, <code>affiliate_id</code>, and optionally <code>affiliate_user_id</code>', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'The script fetches WordPress user data (email, first name, last name) for each customer_user_id', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Creates or updates customer records in <code>affiliate_wp_customers</code> table', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Links each customer to their affiliate via <code>affiliate_wp_customermeta</code> table', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Creates lifetime customer records in <code>affiliate_wp_lifetime_customers</code> table (for lifetime commissions)', 'rcp-content-filter' ); ?></li>
                        <li><?php _e( 'Displays detailed results with statistics and error reporting', 'rcp-content-filter' ); ?></li>
                    </ol>
                    <p><strong><?php _e( 'CSV Format Example:', 'rcp-content-filter' ); ?></strong></p>
                    <pre style="background: #f0f0f1; padding: 10px; border-radius: 4px;">customer_user_id,affiliate_id,affiliate_user_id
123,456,789
234,456,789
345,567,890</pre>
                    <p class="description" style="margin-top: 10px;">
                        <?php _e( '<strong>customer_user_id</strong>: WordPress user ID of the customer<br>
                        <strong>affiliate_id</strong>: AffiliateWP affiliate ID to link the customer to<br>
                        <strong>affiliate_user_id</strong>: (Optional) WordPress user ID of the affiliate', 'rcp-content-filter' ); ?>
                    </p>
                </div>

                <!-- Upload Form -->
                <h3><?php _e( 'Upload CSV File', 'rcp-content-filter' ); ?></h3>
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field( 'rcf_affiliatewp_import', 'rcf_affiliatewp_import_nonce' ); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="affiliatewp_csv"><?php _e( 'CSV File', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <input type="file" name="affiliatewp_csv" id="affiliatewp_csv" accept=".csv" required>
                                <p class="description">
                                    <?php _e( 'Upload a CSV file with columns:', 'rcp-content-filter' ); ?>
                                    <code>customer_user_id</code>, <code>affiliate_id</code>, <code>affiliate_user_id</code> (optional)
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="affiliatewp_dry_run"><?php _e( 'Dry Run', 'rcp-content-filter' ); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="affiliatewp_dry_run" id="affiliatewp_dry_run" value="1" checked>
                                    <?php _e( 'Preview changes without modifying database (recommended)', 'rcp-content-filter' ); ?>
                                </label>
                                <p class="description">
                                    <?php _e( 'When checked, shows what WOULD be changed without actually making modifications.', 'rcp-content-filter' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="submit_affiliatewp_import" class="button button-primary"
                               value="<?php esc_attr_e( 'Upload and Process', 'rcp-content-filter' ); ?>">
                        <?php if ( ! empty( $import_results ) ) : ?>
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=rcp-content-filter&tab=affiliatewp-import&clear_affwp_import=1' ), 'rcf_clear_affwp_import' ); ?>"
                               class="button"
                               onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the current results?', 'rcp-content-filter' ); ?>');">
                                <?php _e( 'Clear Results', 'rcp-content-filter' ); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </form>

                <?php
                // Display results if available
                if ( ! empty( $import_results ) && is_array( $import_results ) ) {
                    $this->display_affiliatewp_import_results( $import_results );
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Process AffiliateWP import CSV
     *
     * @since 1.x.x (Temporary Feature)
     */
    private function process_affiliatewp_import() {
        // Verify capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error( 'rcf_messages', 'rcf_permission_error',
                __( 'You do not have permission to perform this action.', 'rcp-content-filter' ), 'error' );
            return;
        }

        // Check for file upload
        if ( ! isset( $_FILES['affiliatewp_csv'] ) || $_FILES['affiliatewp_csv']['error'] !== UPLOAD_ERR_OK ) {
            add_settings_error( 'rcf_messages', 'rcf_upload_error',
                __( 'File upload failed. Please try again.', 'rcp-content-filter' ), 'error' );
            return;
        }

        // Parse CSV
        $csv_data = $this->parse_affiliatewp_csv( $_FILES['affiliatewp_csv']['tmp_name'] );

        if ( empty( $csv_data ) ) {
            // Error messages already added by parser
            return;
        }

        // Check dry run mode
        $dry_run = isset( $_POST['affiliatewp_dry_run'] ) && $_POST['affiliatewp_dry_run'] === '1';

        // Initialize results tracking
        $results = array(
            'total_rows'              => count( $csv_data ),
            'customers_created'       => 0,
            'customers_updated'       => 0,
            'links_created'           => 0,
            'links_updated'           => 0,
            'lifetime_created'        => 0,
            'lifetime_already_exists' => 0,
            'skipped'                 => 0,
            'errors'                  => array(),
            'details'                 => array(),
            'dry_run'                 => $dry_run,
        );

        // Process each row
        $row_number = 1; // Start from 1 (header is row 0)
        foreach ( $csv_data as $row ) {
            $row_number++;

            // Get WordPress user
            $user = get_user_by( 'id', $row['user_id'] );

            if ( ! $user ) {
                $results['skipped']++;
                $results['errors'][] = sprintf(
                    __( 'Row %d: WordPress user ID %d does not exist', 'rcp-content-filter' ),
                    $row_number,
                    $row['user_id']
                );
                continue;
            }

            // Update/create customer record
            $customer_result = $this->update_customer_record(
                $row['user_id'],
                $user->user_email,
                $user->first_name,
                $user->last_name,
                $dry_run
            );

            if ( is_wp_error( $customer_result ) ) {
                $results['skipped']++;
                $results['errors'][] = sprintf(
                    __( 'Row %d (User ID %d): %s', 'rcp-content-filter' ),
                    $row_number,
                    $row['user_id'],
                    $customer_result->get_error_message()
                );
                continue;
            }

            // Track customer operation (use strpos to handle dry run suffix)
            if ( strpos( $customer_result['action'], 'created' ) === 0 ) {
                $results['customers_created']++;
            } else {
                $results['customers_updated']++;
            }

            // Update customer-affiliate link
            $link_result = $this->update_customer_affiliate_link(
                $customer_result['customer_id'],
                $row['affiliate_id'],
                $dry_run
            );

            if ( is_wp_error( $link_result ) ) {
                $results['errors'][] = sprintf(
                    __( 'Row %d (User ID %d): Failed to link affiliate - %s', 'rcp-content-filter' ),
                    $row_number,
                    $row['user_id'],
                    $link_result->get_error_message()
                );
                continue;
            }

            // Track link operation (use strpos to handle dry run suffix)
            if ( strpos( $link_result['action'], 'created' ) === 0 ) {
                $results['links_created']++;
            } else {
                $results['links_updated']++;
            }

            // Step 3: Create lifetime customer record
            $lifetime_result = $this->update_lifetime_customer_record(
                $customer_result['customer_id'],
                $row['affiliate_id'],
                $dry_run
            );

            if ( is_wp_error( $lifetime_result ) ) {
                $results['errors'][] = sprintf(
                    __( 'Row %d (User ID %d): Failed to create lifetime customer - %s', 'rcp-content-filter' ),
                    $row_number,
                    $row['user_id'],
                    $lifetime_result->get_error_message()
                );
                // Continue anyway - customer and link were created successfully
            } else {
                // Track lifetime operation
                if ( strpos( $lifetime_result['action'], 'created' ) === 0 ) {
                    $results['lifetime_created']++;
                } else {
                    $results['lifetime_already_exists']++;
                }
            }

            // Add to details (limit to 50 entries to avoid memory issues)
            if ( count( $results['details'] ) < 50 ) {
                $lifetime_action = is_wp_error( $lifetime_result ) ? 'error' : $lifetime_result['action'];
                $results['details'][] = sprintf(
                    __( 'User ID %d (%s): Customer %s, Link %s, Lifetime %s', 'rcp-content-filter' ),
                    $row['user_id'],
                    $user->user_email,
                    $customer_result['action'],
                    $link_result['action'],
                    $lifetime_action
                );
            }
        }

        // Store results in transient for display
        set_transient( 'rcf_affiliatewp_import_results_' . get_current_user_id(), $results, HOUR_IN_SECONDS );

        // Add success message
        $message = $dry_run ? __( 'Dry run complete. No database changes were made.', 'rcp-content-filter' )
                            : __( 'Import completed successfully!', 'rcp-content-filter' );
        add_settings_error( 'rcf_messages', 'rcf_import_success', $message, 'success' );
    }

    /**
     * Parse AffiliateWP import CSV
     *
     * @since 1.x.x (Temporary Feature)
     * @param string $file_path Path to uploaded CSV file
     * @return array|false Array of parsed data or false on error
     */
    private function parse_affiliatewp_csv( $file_path ) {
        $data = array();
        $handle = fopen( $file_path, 'r' );

        if ( $handle === false ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_open_error',
                __( 'Unable to open CSV file.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        // Read and validate header
        $headers = fgetcsv( $handle );
        if ( $headers === false ) {
            fclose( $handle );
            add_settings_error( 'rcf_messages', 'rcf_csv_empty',
                __( 'CSV file is empty or invalid.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        // Normalize headers (trim and lowercase)
        $headers = array_map( function( $header ) {
            return strtolower( trim( $header ) );
        }, $headers );

        // Validate required columns (support both 'customer_user_id' and 'user_id' for flexibility)
        $has_customer_user_id = in_array( 'customer_user_id', $headers );
        $has_user_id = in_array( 'user_id', $headers );
        $has_affiliate_id = in_array( 'affiliate_id', $headers );

        if ( ( ! $has_customer_user_id && ! $has_user_id ) || ! $has_affiliate_id ) {
            fclose( $handle );
            add_settings_error( 'rcf_messages', 'rcf_csv_missing_columns',
                __( 'CSV must contain "customer_user_id" (or "user_id") and "affiliate_id" columns.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        // Get column indices (prefer customer_user_id over user_id)
        $user_id_index = $has_customer_user_id
            ? array_search( 'customer_user_id', $headers )
            : array_search( 'user_id', $headers );
        $affiliate_id_index = array_search( 'affiliate_id', $headers );

        // Optional: affiliate_user_id column (for reference, not required)
        $affiliate_user_id_index = in_array( 'affiliate_user_id', $headers )
            ? array_search( 'affiliate_user_id', $headers )
            : false;

        // Parse data rows
        $invalid_rows = 0;
        $row_number = 1; // Header is row 1

        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_number++;

            // Skip empty rows
            if ( empty( array_filter( $row ) ) ) {
                continue;
            }

            // Validate row has enough columns
            if ( ! isset( $row[ $user_id_index ] ) || ! isset( $row[ $affiliate_id_index ] ) ) {
                $invalid_rows++;
                continue;
            }

            $user_id = trim( $row[ $user_id_index ] );
            $affiliate_id = trim( $row[ $affiliate_id_index ] );

            // Validate numeric values
            if ( ! is_numeric( $user_id ) || ! is_numeric( $affiliate_id ) ) {
                $invalid_rows++;
                add_settings_error( 'rcf_messages', 'rcf_csv_invalid_row_' . $row_number,
                    sprintf( __( 'Row %d: customer_user_id and affiliate_id must be numeric values.', 'rcp-content-filter' ),
                        $row_number ), 'warning' );
                continue;
            }

            // Validate positive values
            if ( (int) $user_id <= 0 || (int) $affiliate_id <= 0 ) {
                $invalid_rows++;
                continue;
            }

            $data[] = array(
                'user_id'      => (int) $user_id,
                'affiliate_id' => (int) $affiliate_id,
            );
        }

        fclose( $handle );

        // Report issues
        if ( $invalid_rows > 0 ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_invalid',
                sprintf( __( 'Warning: %d invalid row(s) skipped.', 'rcp-content-filter' ),
                    $invalid_rows ), 'warning' );
        }

        if ( empty( $data ) ) {
            add_settings_error( 'rcf_messages', 'rcf_csv_no_data',
                __( 'No valid data rows found in CSV.', 'rcp-content-filter' ), 'error' );
            return false;
        }

        return $data;
    }

    /**
     * Update or create customer record in affiliate_wp_customers
     *
     * @since 1.x.x (Temporary Feature)
     * @param int    $user_id    WordPress user ID
     * @param string $email      User email
     * @param string $first_name User first name
     * @param string $last_name  User last name
     * @param bool   $dry_run    Preview mode
     * @return array|WP_Error Array with customer_id and action, or WP_Error on failure
     */
    private function update_customer_record( $user_id, $email, $first_name, $last_name, $dry_run = false ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'affiliate_wp_customers';

        // Check if customer already exists
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT customer_id, date_created FROM {$table_name} WHERE user_id = %d",
            $user_id
        ) );

        if ( $dry_run ) {
            // Dry run mode: just return what would happen
            if ( $existing ) {
                return array(
                    'customer_id' => $existing->customer_id,
                    'action'      => 'updated (dry run)',
                );
            } else {
                return array(
                    'customer_id' => 0, // Placeholder for dry run
                    'action'      => 'created (dry run)',
                );
            }
        }

        if ( $existing ) {
            // Update existing customer
            $updated = $wpdb->update(
                $table_name,
                array(
                    'email'      => $email,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                ),
                array( 'user_id' => $user_id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );

            if ( $updated === false ) {
                return new WP_Error( 'db_update_failed', __( 'Failed to update customer record', 'rcp-content-filter' ) );
            }

            return array(
                'customer_id' => $existing->customer_id,
                'action'      => 'updated',
            );
        } else {
            // Create new customer
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'user_id'      => $user_id,
                    'email'        => $email,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'ip'           => '',
                    'date_created' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( $inserted === false ) {
                return new WP_Error( 'db_insert_failed', __( 'Failed to create customer record', 'rcp-content-filter' ) );
            }

            return array(
                'customer_id' => $wpdb->insert_id,
                'action'      => 'created',
            );
        }
    }

    /**
     * Update or create customer-affiliate link in affiliate_wp_customermeta
     *
     * @since 1.x.x (Temporary Feature)
     * @param int  $customer_id  AffiliateWP customer ID
     * @param int  $affiliate_id AffiliateWP affiliate ID
     * @param bool $dry_run      Preview mode
     * @return array|WP_Error Array with action, or WP_Error on failure
     */
    private function update_customer_affiliate_link( $customer_id, $affiliate_id, $dry_run = false ) {
        global $wpdb;

        // Early return for new customers in dry run (no valid customer_id yet)
        if ( $dry_run && $customer_id === 0 ) {
            return array( 'action' => 'created (dry run)' );
        }

        // Validate customer_id for non-dry-run mode
        if ( $customer_id === 0 ) {
            return new WP_Error( 'invalid_customer', __( 'Invalid customer ID', 'rcp-content-filter' ) );
        }

        $table_name = $wpdb->prefix . 'affiliate_wp_customermeta';

        // Check if link already exists (only runs with valid customer_id)
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_id FROM {$table_name} WHERE affwp_customer_id = %d AND meta_key = 'affiliate_id'",
            $customer_id
        ) );

        if ( $dry_run ) {
            // Dry run mode with existing customer - can check if link exists
            if ( $existing ) {
                return array( 'action' => 'updated (dry run)' );
            } else {
                return array( 'action' => 'created (dry run)' );
            }
        }

        if ( $existing ) {
            // Update existing meta
            $updated = $wpdb->update(
                $table_name,
                array( 'meta_value' => $affiliate_id ),
                array(
                    'affwp_customer_id' => $customer_id,
                    'meta_key'          => 'affiliate_id',
                ),
                array( '%s' ),
                array( '%d', '%s' )
            );

            if ( $updated === false ) {
                return new WP_Error( 'db_update_failed', __( 'Failed to update affiliate link', 'rcp-content-filter' ) );
            }

            return array( 'action' => 'updated' );
        } else {
            // Create new meta
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'affwp_customer_id' => $customer_id,
                    'meta_key'          => 'affiliate_id',
                    'meta_value'        => $affiliate_id,
                ),
                array( '%d', '%s', '%s' )
            );

            if ( $inserted === false ) {
                return new WP_Error( 'db_insert_failed', __( 'Failed to create affiliate link', 'rcp-content-filter' ) );
            }

            return array( 'action' => 'created' );
        }
    }

    /**
     * Create lifetime customer record in affiliate_wp_lifetime_customers
     *
     * @since 1.x.x (Temporary Feature)
     * @param int  $customer_id  AffiliateWP customer ID
     * @param int  $affiliate_id AffiliateWP affiliate ID
     * @param bool $dry_run      Preview mode
     * @return array|WP_Error Array with action, or WP_Error on failure
     */
    private function update_lifetime_customer_record( $customer_id, $affiliate_id, $dry_run = false ) {
        global $wpdb;

        // Early return for new customers in dry run (no valid customer_id yet)
        if ( $dry_run && $customer_id === 0 ) {
            return array( 'action' => 'created (dry run)' );
        }

        // Validate customer_id for non-dry-run mode
        if ( $customer_id === 0 ) {
            return new WP_Error( 'invalid_customer', __( 'Invalid customer ID', 'rcp-content-filter' ) );
        }

        $table_name = $wpdb->prefix . 'affiliate_wp_lifetime_customers';

        // Check if lifetime customer record already exists (only runs with valid customer_id)
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT lifetime_customer_id FROM {$table_name} WHERE affwp_customer_id = %d AND affiliate_id = %d",
            $customer_id,
            $affiliate_id
        ) );

        if ( $dry_run ) {
            // Dry run mode with existing customer - can check if lifetime record exists
            if ( $existing ) {
                return array( 'action' => 'already exists (dry run)' );
            } else {
                return array( 'action' => 'created (dry run)' );
            }
        }

        if ( $existing ) {
            // Record already exists - no need to create again
            return array( 'action' => 'already exists' );
        } else {
            // Create new lifetime customer record
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'affwp_customer_id' => $customer_id,
                    'affiliate_id'      => $affiliate_id,
                    'date_created'      => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s' )
            );

            if ( $inserted === false ) {
                return new WP_Error( 'db_insert_failed', __( 'Failed to create lifetime customer record', 'rcp-content-filter' ) );
            }

            return array( 'action' => 'created' );
        }
    }

    /**
     * Display AffiliateWP import results
     *
     * @since 1.x.x (Temporary Feature)
     * @param array $results Import results data
     */
    private function display_affiliatewp_import_results( $results ) {
        ?>
        <!-- Summary Statistics -->
        <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
            <h3><?php _e( 'Import Summary', 'rcp-content-filter' ); ?></h3>

            <?php if ( $results['dry_run'] ) : ?>
                <div class="notice notice-info inline" style="margin: 10px 0;">
                    <p><strong><?php _e( '🔍 DRY RUN MODE - No database changes were made', 'rcp-content-filter' ); ?></strong></p>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 15px;">
                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: 600; margin-bottom: 5px;">
                        <?php echo esc_html( $results['total_rows'] ); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php _e( 'Total Rows', 'rcp-content-filter' ); ?>
                    </div>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: 600; color: #00a32a; margin-bottom: 5px;">
                        <?php echo esc_html( $results['customers_created'] ); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php _e( 'Customers Created', 'rcp-content-filter' ); ?>
                    </div>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: 600; color: #0073aa; margin-bottom: 5px;">
                        <?php echo esc_html( $results['customers_updated'] ); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php _e( 'Customers Updated', 'rcp-content-filter' ); ?>
                    </div>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: 600; color: #00a32a; margin-bottom: 5px;">
                        <?php echo esc_html( $results['links_created'] ); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php _e( 'Links Created', 'rcp-content-filter' ); ?>
                    </div>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: 600; color: #0073aa; margin-bottom: 5px;">
                        <?php echo esc_html( $results['links_updated'] ); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php _e( 'Links Updated', 'rcp-content-filter' ); ?>
                    </div>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: 600; color: #00a32a; margin-bottom: 5px;">
                        <?php echo esc_html( $results['lifetime_created'] ); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        <?php _e( 'Lifetime Records Created', 'rcp-content-filter' ); ?>
                    </div>
                </div>

                <?php if ( $results['lifetime_already_exists'] > 0 ) : ?>
                    <div style="text-align: center;">
                        <div style="font-size: 32px; font-weight: 600; color: #0073aa; margin-bottom: 5px;">
                            <?php echo esc_html( $results['lifetime_already_exists'] ); ?>
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            <?php _e( 'Lifetime Already Existed', 'rcp-content-filter' ); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( $results['skipped'] > 0 ) : ?>
                    <div style="text-align: center;">
                        <div style="font-size: 32px; font-weight: 600; color: #d63638; margin-bottom: 5px;">
                            <?php echo esc_html( $results['skipped'] ); ?>
                        </div>
                        <div style="font-size: 13px; color: #666;">
                            <?php _e( 'Rows Skipped', 'rcp-content-filter' ); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Details Section -->
        <?php if ( ! empty( $results['details'] ) ) : ?>
            <details style="margin: 20px 0;">
                <summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                    <?php printf( __( 'View Processing Details (showing first %d entries)', 'rcp-content-filter' ),
                        min( count( $results['details'] ), 50 ) ); ?>
                </summary>
                <div style="margin-top: 10px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ( $results['details'] as $detail ) : ?>
                            <li><?php echo esc_html( $detail ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ( $results['total_rows'] > 50 ) : ?>
                        <p style="margin-top: 10px; font-style: italic; color: #666;">
                            <?php printf( __( '...and %d more entries', 'rcp-content-filter' ),
                                $results['total_rows'] - 50 ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </details>
        <?php endif; ?>

        <!-- Errors Section -->
        <?php if ( ! empty( $results['errors'] ) ) : ?>
            <details open style="margin: 20px 0;">
                <summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px; color: #d63638;">
                    <?php printf( __( '⚠️ Errors (%d)', 'rcp-content-filter' ), count( $results['errors'] ) ); ?>
                </summary>
                <div style="margin-top: 10px; padding: 15px; background: #fff; border: 1px solid #d63638; border-radius: 4px;">
                    <ul style="margin: 0; padding-left: 20px; color: #d63638;">
                        <?php foreach ( $results['errors'] as $error ) : ?>
                            <li><?php echo esc_html( $error ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </details>
        <?php endif; ?>
        <?php
    }

    // ============================================================
    // END TEMPORARY FEATURE: AffiliateWP Import
    // ============================================================
}

/**
 * Custom List Table for User Import Results
 *
 * @since 1.x.x
 */
class RCF_User_Import_List_Table extends WP_List_Table {

    private $import_data;

    /**
     * Constructor
     *
     * @param array $import_data Processed import data
     */
    public function __construct( $import_data ) {
        parent::__construct( array(
            'singular' => 'user_import',
            'plural'   => 'user_imports',
            'ajax'     => false,
        ) );

        $this->import_data = $import_data;
    }

    /**
     * Get table columns
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'email'      => __( 'Email', 'rcp-content-filter' ),
            'first_name' => __( 'First Name', 'rcp-content-filter' ),
            'last_name'  => __( 'Last Name', 'rcp-content-filter' ),
            'wp_user'    => __( 'WordPress User?', 'rcp-content-filter' ),
            'user_roles' => __( 'User Roles', 'rcp-content-filter' ),
        );
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'email'      => array( 'email', false ),
            'first_name' => array( 'first_name', false ),
            'last_name'  => array( 'last_name', false ),
            'wp_user'    => array( 'wp_user', false ),
        );
    }

    /**
     * Prepare items for display
     */
    public function prepare_items() {
        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            array(), // Hidden columns
            $this->get_sortable_columns(),
        );

        // Separate WP users from non-WP users
        $wp_users = array();
        $non_wp_users = array();

        foreach ( $this->import_data as $row ) {
            if ( $row['wp_user_exists'] ) {
                $wp_users[] = $row;
            } else {
                $non_wp_users[] = $row;
            }
        }

        // Get sorting parameters
        $orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'email';
        $order = isset( $_GET['order'] ) && $_GET['order'] === 'desc' ? 'desc' : 'asc';

        // Sort WP users based on selected column
        usort( $wp_users, function( $a, $b ) use ( $orderby, $order ) {
            $result = 0;

            switch ( $orderby ) {
                case 'first_name':
                    $result = strcasecmp( $a['first_name'], $b['first_name'] );
                    break;
                case 'last_name':
                    $result = strcasecmp( $a['last_name'], $b['last_name'] );
                    break;
                case 'wp_user':
                    $result = 0; // Both are WP users
                    break;
                case 'email':
                default:
                    $result = strcasecmp( $a['email'], $b['email'] );
                    break;
            }

            return ( $order === 'desc' ) ? -$result : $result;
        } );

        // Sort non-WP users alphabetically by email
        usort( $non_wp_users, function( $a, $b ) {
            return strcasecmp( $a['email'], $b['email'] );
        } );

        // CRITICAL: Merge with WP users first, non-WP users at bottom
        $all_items = array_merge( $wp_users, $non_wp_users );

        // Pagination setup
        $per_page = 100;
        $current_page = $this->get_pagenum();
        $total_items = count( $all_items );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );

        // Slice items for current page
        $offset = ( $current_page - 1 ) * $per_page;
        $this->items = array_slice( $all_items, $offset, $per_page );
    }

    /**
     * Default column rendering
     *
     * @param array  $item        Row data
     * @param string $column_name Column name
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] ?? '' );
    }

    /**
     * Email column rendering
     *
     * @param array $item Row data
     * @return string
     */
    public function column_email( $item ) {
        return '<strong>' . esc_html( $item['email'] ) . '</strong>';
    }

    /**
     * WordPress User column rendering
     *
     * @param array $item Row data
     * @return string
     */
    public function column_wp_user( $item ) {
        if ( $item['wp_user_exists'] ) {
            return '<span style="color: #00a32a;" aria-label="' .
                   esc_attr__( 'User exists in WordPress', 'rcp-content-filter' ) .
                   '">✓ ' . __( 'Yes', 'rcp-content-filter' ) . '</span>';
        } else {
            return '<span style="color: #d63638;" aria-label="' .
                   esc_attr__( 'User does not exist in WordPress', 'rcp-content-filter' ) .
                   '">✗ ' . __( 'No', 'rcp-content-filter' ) . '</span>';
        }
    }

    /**
     * User Roles column rendering
     *
     * @param array $item Row data
     * @return string
     */
    public function column_user_roles( $item ) {
        if ( empty( $item['user_roles'] ) ) {
            return '—';
        }

        // Convert role slugs to readable names
        $role_names = array_map( function( $role_slug ) {
            return ucfirst( str_replace( array( '_', '-' ), ' ', $role_slug ) );
        }, $item['user_roles'] );

        return esc_html( implode( ', ', $role_names ) );
    }

    /**
     * Override single row to add red background for non-WP users
     *
     * @param array $item Row data
     */
    public function single_row( $item ) {
        $row_class = $item['wp_user_exists'] ? '' : 'rcf-non-wp-user';
        echo '<tr class="' . esc_attr( $row_class ) . '">';
        $this->single_row_columns( $item );
        echo '</tr>';
    }
}