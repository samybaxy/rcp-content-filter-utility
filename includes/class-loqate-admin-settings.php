<?php
/**
 * Loqate Admin Settings
 *
 * Provides admin interface for configuring Loqate Address Capture integration
 * in the RCP Content Filter settings page.
 *
 * @package RCP_Content_Filter
 * @since 1.0.15
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loqate Admin Settings Handler
 *
 * Manages admin UI and settings for Loqate integration
 */
class RCF_Loqate_Admin_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add Loqate tab to settings page
		add_filter( 'rcf_settings_tabs', array( $this, 'add_loqate_tab' ) );
		add_action( 'rcf_render_loqate_tab', array( $this, 'render_loqate_tab' ) );
		add_action( 'rcf_save_loqate_settings', array( $this, 'save_loqate_settings' ) );
	}

	/**
	 * Add Loqate tab to settings tabs
	 *
	 * @param array $tabs Existing tabs
	 * @return array Updated tabs
	 */
	public function add_loqate_tab( $tabs ) {
		$tabs['loqate'] = __( 'Loqate Integration', 'rcp-content-filter' );
		return $tabs;
	}

	/**
	 * Render Loqate settings tab
	 *
	 * @return void
	 */
	public function render_loqate_tab(): void {
		$api_key = get_option( 'rcf_loqate_api_key', '' );
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
										<input type="checkbox" name="rcf_loqate_geolocation_enabled" value="1" checked>
										<span><?php _e( 'Enable geolocation-based address suggestions', 'rcp-content-filter' ); ?></span>
									</label>
									<p class="description">
										<?php _e( 'When enabled, Loqate will prioritize address suggestions based on the user\'s current location.', 'rcp-content-filter' ); ?>
									</p>

									<label style="display: block; margin-bottom: 8px;">
										<?php _e( 'Search Radius (km):', 'rcp-content-filter' ); ?><br>
										<input type="number" name="rcf_loqate_geolocation_radius" value="100" min="1" max="500" style="width: 80px;">
										<span class="description"><?php _e( 'Default: 100 km', 'rcp-content-filter' ); ?></span>
									</label>

									<label style="display: block; margin-bottom: 8px;">
										<?php _e( 'Max Results:', 'rcp-content-filter' ); ?><br>
										<input type="number" name="rcf_loqate_geolocation_max_items" value="5" min="1" max="20" style="width: 80px;">
										<span class="description"><?php _e( 'Default: 5 results', 'rcp-content-filter' ); ?></span>
									</label>
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
										<input type="checkbox" name="rcf_loqate_allow_manual_entry" value="1" checked>
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
										<input type="checkbox" name="rcf_loqate_validate_email" value="1" checked>
										<span><?php _e( 'Validate Email Addresses', 'rcp-content-filter' ); ?></span>
									</label>
									<p class="description">
										<?php _e( 'Uses Loqate Email Validation to verify email addresses during checkout.', 'rcp-content-filter' ); ?>
									</p>

									<label style="display: block; margin-bottom: 10px;">
										<input type="checkbox" name="rcf_loqate_validate_phone" value="1">
										<span><?php _e( 'Validate Phone Numbers', 'rcp-content-filter' ); ?></span>
									</label>
									<p class="description">
										<?php _e( 'Uses Loqate Phone Validation to verify phone numbers during checkout.', 'rcp-content-filter' ); ?>
									</p>
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
									value=""
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
					<?php $this->render_status_info( $loqate_status ); ?>
				</div>

				<!-- Documentation -->
				<div class="rcf-info-box" style="margin-top: 20px;">
					<h3><?php _e( 'Documentation', 'rcp-content-filter' ); ?></h3>
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
							<a href="https://docs.loqate.com/our-services/address-verify/overview" target="_blank">
								<?php _e( 'Address Verification', 'rcp-content-filter' ); ?>
							</a>
						</li>
						<li>
							<a href="https://docs.loqate.com/our-services/email-validation/overview" target="_blank">
								<?php _e( 'Email Validation', 'rcp-content-filter' ); ?>
							</a>
						</li>
					</ul>
				</div>

				<!-- Implementation Notes -->
				<div class="rcf-info-box" style="margin-top: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
					<h3 style="color: #856404;"><?php _e( '⚠️ Implementation Notes', 'rcp-content-filter' ); ?></h3>
					<ul style="margin: 10px 0; padding-left: 20px;">
						<li><?php _e( 'Loqate SDK is only loaded on the WooCommerce checkout page', 'rcp-content-filter' ); ?></li>
						<li><?php _e( 'API key can be configured here or via the LOQATE_API_KEY constant', 'rcp-content-filter' ); ?></li>
						<li><?php _e( 'Browser autocomplete is disabled on address fields to prevent conflicts', 'rcp-content-filter' ); ?></li>
						<li><?php _e( 'All configuration is filterable via WordPress hooks for customization', 'rcp-content-filter' ); ?></li>
						<li><?php _e( 'The integration respects all WooCommerce checkout field settings', 'rcp-content-filter' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render status information
	 *
	 * @param array $status Status information
	 * @return void
	 */
	private function render_status_info( $status ): void {
		?>
		<ul style="margin: 0; padding-left: 20px;">
			<li>
				<span style="color: <?php echo $status['api_key_set'] ? '#00a32a' : '#d63638'; ?>;">●</span>
				<?php _e( 'API Key:', 'rcp-content-filter' ); ?>
				<strong><?php echo $status['api_key_set'] ? __( 'Configured', 'rcp-content-filter' ) : __( 'Not Set', 'rcp-content-filter' ); ?></strong>
				<?php if ( $status['api_key_set'] ) : ?>
					<code style="margin-left: 8px;"><?php echo esc_html( $status['masked_key'] ); ?></code>
				<?php endif; ?>
			</li>
			<li>
				<span style="color: <?php echo $status['enabled'] ? '#00a32a' : '#d63638'; ?>;">●</span>
				<?php _e( 'Integration:', 'rcp-content-filter' ); ?>
				<strong><?php echo $status['enabled'] ? __( 'Enabled', 'rcp-content-filter' ) : __( 'Disabled', 'rcp-content-filter' ); ?></strong>
			</li>
			<li>
				<span style="color: <?php echo $status['woocommerce'] ? '#00a32a' : '#d63638'; ?>;">●</span>
				<?php _e( 'WooCommerce:', 'rcp-content-filter' ); ?>
				<strong><?php echo $status['woocommerce'] ? __( 'Active', 'rcp-content-filter' ) : __( 'Not Installed', 'rcp-content-filter' ); ?></strong>
			</li>
		</ul>
		<?php
	}

	/**
	 * Save Loqate settings
	 *
	 * @return void
	 */
	public function save_loqate_settings(): void {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check nonce
		if ( ! isset( $_POST['rcf_loqate_nonce'] ) || ! wp_verify_nonce( $_POST['rcf_loqate_nonce'], 'rcf_save_loqate_settings' ) ) {
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
	}
}
