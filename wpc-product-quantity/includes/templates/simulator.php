<?php
/**
 * WPC Product Quantity - Simulator Page Template
 *
 * @package WPC Product Quantity
 */

defined( 'ABSPATH' ) || exit;
global $wp_roles;
$roles = $wp_roles->roles;
?>
<div class="woopq-card">
    <div class="woopq-card-header">
        <div>
            <h2 class="woopq-card-title">
                <span class="dashicons dashicons-calculator"></span>
                <?php esc_html_e( 'Quantity Simulator', 'wpc-product-quantity' ); ?>
            </h2>
            <p class="woopq-card-desc">
                <?php esc_html_e( 'Select a product and user role to test and evaluate which quantity rule is matched and applied.', 'wpc-product-quantity' ); ?>
            </p>
        </div>
    </div>

    <div class="woopq-sim-grid" style="grid-template-columns: 1fr;" id="woopq-simulator-form">
        <div class="woopq-sim-section">
            <h3 class="woopq-sim-section-title">
                <span class="dashicons dashicons-products"></span>
                <?php esc_html_e( 'Simulation Parameters', 'wpc-product-quantity' ); ?>
            </h3>
            <div class="woopq-sim-row woopq-sim-row-product">
                <label for="woopq-sim-product"><?php esc_html_e( 'Product', 'wpc-product-quantity' ); ?></label>
                <select id="woopq-sim-product" class="wc-product-search"
                        data-placeholder="<?php esc_attr_e( 'Search for a product or variation...', 'wpc-product-quantity' ); ?>"
                        data-action="woocommerce_json_search_products_and_variations">
                </select>
            </div>
            <div class="woopq-sim-row">
                <label for="woopq-sim-role"><?php esc_html_e( 'User Role', 'wpc-product-quantity' ); ?></label>
                <select id="woopq-sim-role">
                    <option value="woopq_guest"><?php esc_html_e( 'Guest (Not logged in)', 'wpc-product-quantity' ); ?></option>
                    <option value="woopq_user"><?php esc_html_e( 'Any Logged-in User', 'wpc-product-quantity' ); ?></option>
                    <?php
                    foreach ( $roles as $role_key => $role ) {
                        echo '<option value="' . esc_attr( $role_key ) . '">' . esc_html( $role['name'] ) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <div class="woopq-sim-actions" style="margin-top: 20px; padding: 20px 0; border-top: 1px solid var(--woopq-border); display: flex; gap: 10px; align-items: center;">
        <button type="button" id="woopq-sim-run" class="button button-primary">
            <span class="dashicons dashicons-controls-play" style="margin-top: 3px;"></span>
            <?php esc_html_e( 'Run Simulation', 'wpc-product-quantity' ); ?>
        </button>
        <button type="button" id="woopq-sim-reset" class="button">
            <?php esc_html_e( 'Reset', 'wpc-product-quantity' ); ?>
        </button>
        <span id="woopq-sim-spinner" class="spinner"></span>
    </div>

    <div id="woopq-sim-results" class="woopq-sim-results" style="display: none; padding: 20px; border-top: 1px solid var(--woopq-border); background: #f8fafc;"></div>
</div>
