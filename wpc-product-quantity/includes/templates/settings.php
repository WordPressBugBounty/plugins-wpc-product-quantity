<?php
/**
 * WPC Product Quantity - Settings Page Template (Premium)
 *
 * Renders the admin settings page using the modern card-based layout.
 * Variables available: $active_tab (string).
 *
 * @package WPC Product Quantity
 */

defined( 'ABSPATH' ) || exit;

$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'settings' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$step       = WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
$rules      = WPCleverWoopq::get_setting( 'rules', [] );
unset( $rules['placeholder'] );
?>
<div class="wrap woopq-settings-wrap">
    <div class="woopq-settings-header">
        <div class="woopq-settings-header-inner">
            <div class="woopq-header-left">
                <div class="woopq-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                </div>
                <div>
                    <h1>
                        <?php echo esc_html__( 'WPC Product Quantity', 'wpc-product-quantity' ) . ' ' . esc_html( WOOPQ_VERSION ); ?>
                        <?php if ( defined( 'WOOPQ_PREMIUM' ) ) : ?>
                            <span class="premium"><?php esc_html_e( 'Premium', 'wpc-product-quantity' ); ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="woopq-tagline">
                        <?php esc_html_e( 'Configure quantity rules, input types, and restrictions for your WooCommerce products.', 'wpc-product-quantity' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Settings updated.', 'wpc-product-quantity' ); ?></p>
        </div>
    <?php } ?>

    <div class="woopq-admin-nav">
        <div class="woopq-nav-container">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>"
               class="woopq-nav-item <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                <?php esc_html_e( 'Settings', 'wpc-product-quantity' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=simulator' ) ); ?>"
               class="woopq-nav-item <?php echo $active_tab === 'simulator' ? 'active' : ''; ?>">
                <?php esc_html_e( 'Simulator', 'wpc-product-quantity' ); ?>
            </a>
            <?php if ( ! defined( 'WOOPQ_PREMIUM' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=premium' ) ); ?>"
                   class="woopq-nav-item wpc-premium <?php echo $active_tab === 'premium' ? 'active' : ''; ?>">
                    <?php esc_html_e( 'Premium Version', 'wpc-product-quantity' ); ?>
                </a>
            <?php endif; ?>
            <?php if ( defined( 'WOOPQ_PREMIUM' ) ) : ?>
                <a href="<?php echo esc_url( WOOPQ_SUPPORT ); ?>" class="woopq-nav-item" target="_blank">
                    <?php esc_html_e( 'Support', 'wpc-product-quantity' ); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
               class="woopq-nav-item">
                <?php esc_html_e( 'Essential Kit', 'wpc-product-quantity' ); ?>
            </a>
        </div>
    </div>

    <div class="woopq-tab-content active">
        <?php if ( $active_tab === 'settings' ) { ?>
            <form method="post" action="options.php">
                <div class="woopq-card">
                    <div class="woopq-card-header">
                        <div>
                            <h2 class="woopq-card-title"><?php esc_html_e( 'General Settings', 'wpc-product-quantity' ); ?></h2>
                            <p class="woopq-card-desc"><?php esc_html_e( 'Configure global quantity behaviour for your store.', 'wpc-product-quantity' ); ?></p>
                        </div>
                    </div>

                    <div class="woopq-settings-row">
                        <div class="woopq-settings-label">
                            <strong><?php esc_html_e( 'Decimal quantities', 'wpc-product-quantity' ); ?></strong>
                        </div>
                        <div class="woopq-settings-field">
                            <select name="woopq_settings[decimal]">
                                <option value="no" <?php selected( WPCleverWoopq::get_setting( 'decimal', 'no' ), 'no' ); ?>><?php esc_html_e( 'No', 'wpc-product-quantity' ); ?></option>
                                <option value="yes" <?php selected( WPCleverWoopq::get_setting( 'decimal', 'no' ), 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-product-quantity' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'Press "Update Options" after enabling this option, then you can enter decimal quantities in min, max, step quantity options.', 'wpc-product-quantity' ); ?></span>
                        </div>
                    </div>

                    <div class="woopq-settings-row">
                        <div class="woopq-settings-label">
                            <strong><?php esc_html_e( 'Plus/minus button', 'wpc-product-quantity' ); ?></strong>
                        </div>
                        <div class="woopq-settings-field">
                            <select name="woopq_settings[plus_minus]">
                                <option value="show" <?php selected( WPCleverWoopq::get_setting( 'plus_minus', 'hide' ), 'show' ); ?>><?php esc_html_e( 'Show', 'wpc-product-quantity' ); ?></option>
                                <option value="hide" <?php selected( WPCleverWoopq::get_setting( 'plus_minus', 'hide' ), 'hide' ); ?>><?php esc_html_e( 'Hide', 'wpc-product-quantity' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'Show the plus/minus button for the input type to increase/decrease the quantity.', 'wpc-product-quantity' ); ?></span>
                        </div>
                    </div>

                    <div class="woopq-settings-row">
                        <div class="woopq-settings-label">
                            <strong><?php esc_html_e( 'Auto-correct', 'wpc-product-quantity' ); ?></strong>
                        </div>
                        <div class="woopq-settings-field">
                            <select name="woopq_settings[auto_correct]">
                                <option value="entering" <?php selected( WPCleverWoopq::get_setting( 'auto_correct', 'entering' ), 'entering' ); ?>><?php esc_html_e( 'While entering', 'wpc-product-quantity' ); ?></option>
                                <option value="out_of_focus" <?php selected( WPCleverWoopq::get_setting( 'auto_correct', 'entering' ), 'out_of_focus' ); ?>><?php esc_html_e( 'Out of focus', 'wpc-product-quantity' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'When the auto-correct functionality will be triggered: while entering the number or out of focus on the input (click outside).', 'wpc-product-quantity' ); ?></span>
                        </div>
                    </div>

                    <div class="woopq-settings-row">
                        <div class="woopq-settings-label">
                            <strong><?php esc_html_e( 'Rounding values', 'wpc-product-quantity' ); ?></strong>
                        </div>
                        <div class="woopq-settings-field">
                            <select name="woopq_settings[rounding]">
                                <option value="down" <?php selected( WPCleverWoopq::get_setting( 'rounding', 'down' ), 'down' ); ?>><?php esc_html_e( 'Down', 'wpc-product-quantity' ); ?></option>
                                <option value="up" <?php selected( WPCleverWoopq::get_setting( 'rounding', 'down' ), 'up' ); ?>><?php esc_html_e( 'Up', 'wpc-product-quantity' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'Round the quantity to the nearest bigger (up) or smaller (down) value when an invalid number is inputted.', 'wpc-product-quantity' ); ?></span>
                        </div>
                    </div>

                    <div class="woopq-settings-row">
                        <div class="woopq-settings-label">
                            <strong><?php esc_html_e( 'Works in backend', 'wpc-product-quantity' ); ?></strong>
                        </div>
                        <div class="woopq-settings-field">
                            <select name="woopq_settings[backend]">
                                <option value="yes" <?php selected( WPCleverWoopq::get_setting( 'backend', 'yes' ), 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-product-quantity' ); ?></option>
                                <option value="no" <?php selected( WPCleverWoopq::get_setting( 'backend', 'yes' ), 'no' ); ?>><?php esc_html_e( 'No', 'wpc-product-quantity' ); ?></option>
                            </select>
                            <span class="description"><?php esc_html_e( 'Quantity rules will be applied for product in the backend or not. E.g, editing products on the order.', 'wpc-product-quantity' ); ?></span>
                        </div>
                    </div>
                </div>

                <div class="woopq-card">
                    <div class="woopq-card-header">
                        <div>
                            <h2 class="woopq-card-title"><?php esc_html_e( 'Default Quantity Settings', 'wpc-product-quantity' ); ?></h2>
                            <p class="woopq-card-desc"><?php esc_html_e( 'These settings apply to all products that have no individual or global rule matched.', 'wpc-product-quantity' ); ?></p>
                        </div>
                    </div>

                    <div class="woopq-settings">
                        <div class="woopq-items-wrapper">
                            <div class="woopq-items">
                                <div class="woopq-item woopq-item-default woopq_settings_form active">
                                    <div class="woopq-item-header">
                                        <span class="woopq-item-name">
                                            <span class="woopq-item-name-key">#default</span>
                                        </span>
                                    </div>
                                    <div class="woopq-item-content">
                                        <div class="woopq-item-line">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Type', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                    <select name="woopq_settings[type]" class="woopq_type">
                                                        <option value="default" <?php selected( WPCleverWoopq::get_setting( 'type', 'default' ), 'default' ); ?>><?php esc_html_e( 'Input (Default)', 'wpc-product-quantity' ); ?></option>
                                                        <option value="select" <?php selected( WPCleverWoopq::get_setting( 'type', 'default' ), 'select' ); ?>><?php esc_html_e( 'Select', 'wpc-product-quantity' ); ?></option>
                                                        <option value="radio" <?php selected( WPCleverWoopq::get_setting( 'type', 'default' ), 'radio' ); ?>><?php esc_html_e( 'Radio', 'wpc-product-quantity' ); ?></option>
                                                    </select>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_select woopq_show_if_type_radio">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Values', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                    <textarea name="woopq_settings[values]" rows="10" cols="50"><?php echo esc_textarea( WPCleverWoopq::get_setting( 'values' ) ); ?></textarea>
                                                <p class="description"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                    <input type="number" name="woopq_settings[min]" min="0"
                                                           step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'min' ) ); ?>"/>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                    <input type="number" name="woopq_settings[step]" min="0"
                                                           step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'step' ) ); ?>"/>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                    <input type="number" name="woopq_settings[max]" min="0"
                                                           step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'max' ) ); ?>"/>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                    <input type="number" name="woopq_settings[value]" min="0"
                                                           step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'value', 1 ) ); ?>"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="woopq-card">
                    <div class="woopq-card-header">
                        <div>
                            <h2 class="woopq-card-title"><?php esc_html_e( 'Global Rules', 'wpc-product-quantity' ); ?></h2>
                            <p class="woopq-card-desc">
                                <?php esc_html_e( 'Conditions will be checked from the top of the list down to the end. Products with no applicable conditions matched will follow the default settings. Order of priority: Individual rules >> Global rules >> Default settings.', 'wpc-product-quantity' ); ?>
                            </p>
                        </div>
                        <div class="woopq-card-header-actions">
                            <a href="#" class="woopq-action-tool-btn woopq_expand_all">
                                <span class="dashicons dashicons-arrow-down-alt2"></span> <?php esc_html_e( 'Expand All', 'wpc-product-quantity' ); ?>
                            </a>
                            <a href="#" class="woopq-action-tool-btn woopq_collapse_all">
                                <span class="dashicons dashicons-arrow-up-alt2"></span> <?php esc_html_e( 'Collapse All', 'wpc-product-quantity' ); ?>
                            </a>
                            <button type="button" class="woopq-import-export-btn wpclever_export"
                                    data-key="woopq_settings"
                                    data-name="settings">
                                <span class="dashicons dashicons-database-export"></span>
                                <?php esc_html_e( 'Import / Export', 'wpc-product-quantity' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="woopq-settings woopq-rules-wrapper">
                        <div class="woopq-items-wrapper">
                            <div class="woopq-items woopq-rules">
                                <?php
                                if ( ! empty( $rules ) ) {
                                    foreach ( $rules as $key => $rule ) {
                                        WPCleverWoopq_Backend::rule( $key, $rule );
                                    }
                                }
                                ?>
                            </div>
                            <!-- Placeholder rule to ensure the rules array key is always present when all rules are removed -->
                            <input type="hidden" name="woopq_settings[rules][placeholder][type]" value="none"/>
                        </div>
                        <div class="woopq-add-rule">
                            <div class="woopq-add-rule-btn" data-product_id="0" data-is_variation="0">
                                <span>+</span> <?php esc_html_e( 'Add rule', 'wpc-product-quantity' ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="woopq-submit-row">
                    <?php
                    settings_fields( 'woopq_settings' );
                    submit_button( '', 'primary', 'submit', false );

                    if ( function_exists( 'wpc_last_saved' ) ) {
                        wpc_last_saved( WPCleverWoopq::get_settings() );
                    }
                    ?>
                </div>
            </form>
        <?php } elseif ( $active_tab === 'simulator' ) { ?>
            <?php include WOOPQ_DIR . 'includes/templates/simulator.php'; ?>
        <?php } elseif ( $active_tab === 'premium' ) { ?>
            <div class="woopq-card">
                <div class="woopq-card-header">
                    <div>
                        <h2 class="woopq-card-title"><?php esc_html_e( 'Premium Version', 'wpc-product-quantity' ); ?></h2>
                        <p class="woopq-card-desc"><?php esc_html_e( 'Unlock powerful features with WPC Product Quantity Premium.', 'wpc-product-quantity' ); ?></p>
                    </div>
                </div>
                <div class="woopq-settings-page-content-text">
                    <p><?php esc_html_e( 'Get the Premium Version just $29!', 'wpc-product-quantity' ); ?>
                        <a href="https://wpclever.net/downloads/product-quantity/?utm_source=pro&utm_medium=woopq&utm_campaign=wporg"
                           target="_blank">https://wpclever.net/downloads/product-quantity/</a>
                    </p>
                    <p><strong><?php esc_html_e( 'Extra features for Premium Version:', 'wpc-product-quantity' ); ?></strong></p>
                    <ul>
                        <li>- <?php esc_html_e( 'Allow adding global rules.', 'wpc-product-quantity' ); ?></li>
                        <li>- <?php esc_html_e( 'Allow individual settings for every single product and variation.', 'wpc-product-quantity' ); ?></li>
                        <li>- <?php esc_html_e( 'Get the lifetime update & premium support.', 'wpc-product-quantity' ); ?></li>
                    </ul>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
