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

    /**
     * Constructor
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
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
        $geolocation_radius = get_option( 'rcf_loqate_geolocation_radius', 100 );
        $geolocation_max_items = get_option( 'rcf_loqate_geolocation_max_items', 5 );
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
}