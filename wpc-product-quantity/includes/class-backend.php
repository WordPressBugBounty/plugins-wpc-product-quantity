<?php
/**
 * WPC Product Quantity - Backend Class
 *
 * Handles all admin/backend functionality including settings pages,
 * product meta panels, AJAX handlers, and admin enqueue scripts.
 *
 * @package WPC Product Quantity
 * @since 5.1.7
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPCleverWoopq_Backend' ) ) {
    class WPCleverWoopq_Backend {
        protected static $instance = null;

        /**
         * Meta keys used for product quantity settings.
         *
         * @var array
         */
        private static $meta_keys = [
                '_woopq_quantity',
                '_woopq_rules',
                '_woopq_type',
                '_woopq_min',
                '_woopq_step',
                '_woopq_max',
                '_woopq_value',
                '_woopq_values',
        ];

        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function __construct() {
            // enqueue backend scripts
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 99 );

            // settings page
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
            add_action( 'admin_menu', [ $this, 'admin_menu' ] );

            // settings link
            add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
            add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

            // product settings
            add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
            add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
            add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

            // variation settings
            add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'variation_settings' ], 99, 3 );
            add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 99, 2 );

            // AJAX
            add_action( 'wp_ajax_woopq_search_term', [ $this, 'ajax_search_term' ] );
            add_action( 'wp_ajax_woopq_add_rule', [ $this, 'ajax_add_rule' ] );

            // WPC Variation Duplicator
            add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

            // WPC Variation Bulk Editor
            add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );
        }

        /**
         * Enqueue backend scripts and styles.
         *
         * @param string $hook Current admin page hook.
         */
        public function admin_enqueue_scripts( $hook ) {
            if ( apply_filters( 'woopq_ignore_backend_scripts', false, $hook ) ) {
                return;
            }

            wp_enqueue_style( 'woopq-backend', WOOPQ_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WOOPQ_VERSION );
            wp_enqueue_script( 'woopq-backend', WOOPQ_URI . 'assets/js/backend.js', [
                    'jquery',
                    'jquery-ui-sortable',
                    'wc-enhanced-select',
                    'selectWoo',
            ], WOOPQ_VERSION, true );
            wp_localize_script( 'woopq-backend', 'woopq_admin_vars', [
                    'nonce' => wp_create_nonce( 'woopq_backend' ),
            ] );
        }

        /**
         * Register plugin settings.
         */
        public function register_settings() {
            register_setting( 'woopq_settings', 'woopq_settings', [
                    'type'              => 'array',
                    'sanitize_callback' => [ WPCleverWoopq::instance(), 'sanitize_array' ],
            ] );
        }

        /**
         * Track last saved timestamp.
         *
         * @param mixed $value Option value.
         * @param string $option Option name.
         *
         * @return mixed
         */
        public function last_saved( $value, $option ) {
            if ( $option === 'woopq_settings' ) {
                $value['_last_saved']    = current_time( 'timestamp' );
                $value['_last_saved_by'] = get_current_user_id();
            }

            return $value;
        }

        /**
         * Add admin menu page.
         */
        public function admin_menu() {
            add_submenu_page( 'wpclever', esc_html__( 'WPC Product Quantity', 'wpc-product-quantity' ), esc_html__( 'Product Quantity', 'wpc-product-quantity' ), 'manage_options', 'wpclever-woopq', [
                    $this,
                    'admin_menu_content',
            ] );
        }

        /**
         * Render the settings page content.
         */
        public function admin_menu_content() {
            $active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
            ?>
            <div class="wpclever_settings_page wrap">
                <div class="wpclever_settings_page_header">
                    <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
                       target="_blank" title="Visit wpclever.net"></a>
                    <div class="wpclever_settings_page_header_text">
                        <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Product Quantity', 'wpc-product-quantity' ) . ' ' . esc_html( WOOPQ_VERSION ) . ' ' . ( defined( 'WOOPQ_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-product-quantity' ) . '</span>' : '' ); ?></div>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
                                <?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-product-quantity' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOPQ_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'wpc-product-quantity' ); ?></a>
                                |
                                <a href="<?php echo esc_url( WOOPQ_CHANGELOG ); ?>"
                                   target="_blank"><?php esc_html_e( 'Changelog', 'wpc-product-quantity' ); ?></a>
                                |
                                <a href="<?php echo esc_url( WOOPQ_DISCUSSION ); ?>"
                                   target="_blank"><?php esc_html_e( 'Discussion', 'wpc-product-quantity' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
                <h2></h2>
                <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Settings updated.', 'wpc-product-quantity' ); ?></p>
                    </div>
                <?php } ?>
                <div class="wpclever_settings_page_nav">
                    <h2 class="nav-tab-wrapper">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                            <?php esc_html_e( 'Settings', 'wpc-product-quantity' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=premium' ) ); ?>"
                           class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>"
                           style="color: #c9356e">
                            <?php esc_html_e( 'Premium Version', 'wpc-product-quantity' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                           class="nav-tab">
                            <?php esc_html_e( 'Essential Kit', 'wpc-product-quantity' ); ?>
                        </a>
                    </h2>
                </div>
                <div class="wpclever_settings_page_content">
                    <?php if ( $active_tab === 'settings' ) {
                        $this->render_settings_tab();
                    } elseif ( $active_tab === 'premium' ) { ?>
                        <div class="wpclever_settings_page_content_text">
                            <p>
                                Get the Premium Version just $29!
                                <a href="https://wpclever.net/downloads/product-quantity?utm_source=pro&utm_medium=woopq&utm_campaign=wporg"
                                   target="_blank">https://wpclever.net/downloads/product-quantity</a>
                            </p>
                            <p><strong>Extra features for Premium Version:</strong></p>
                            <ul style="margin-bottom: 0">
                                <li>- Allow adding global rules.</li>
                                <li>- Allow individual settings for every single product and variation.</li>
                                <li>- Get the lifetime update & premium support.</li>
                            </ul>
                        </div>
                    <?php } ?>
                </div><!-- /.wpclever_settings_page_content -->
                <div class="wpclever_settings_page_suggestion">
                    <div class="wpclever_settings_page_suggestion_label">
                        <span class="dashicons dashicons-yes-alt"></span> Suggestion
                    </div>
                    <div class="wpclever_settings_page_suggestion_content">
                        <div>
                            To display custom engaging real-time messages on any wished positions, please
                            install
                            <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC
                                Smart Messages</a> plugin. It's free!
                        </div>
                        <div>
                            Wanna save your precious time working on variations? Try our brand-new free plugin
                            <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                Variation Bulk Editor</a> and
                            <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                Variation Duplicator</a>.
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Render the settings tab content.
         */
        private function render_settings_tab() {
            $step         = WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
            $decimal      = WPCleverWoopq::get_setting( 'decimal', 'no' );
            $plus_minus   = WPCleverWoopq::get_setting( 'plus_minus', 'hide' );
            $auto_correct = WPCleverWoopq::get_setting( 'auto_correct', 'entering' );
            $rounding     = WPCleverWoopq::get_setting( 'rounding', 'down' );
            $backend      = WPCleverWoopq::get_setting( 'backend', 'yes' );
            $type         = WPCleverWoopq::get_setting( 'type', 'default' );
            $rules        = WPCleverWoopq::get_setting( 'rules', [] );
            unset( $rules['placeholder'] );
            ?>
            <form method="post" action="options.php">
                <table class="form-table">
                    <tr class="heading">
                        <th colspan="2">
                            <?php esc_html_e( 'General', 'wpc-product-quantity' ); ?>
                        </th>
                    </tr>
                    <tr>
                        <th>
                            <?php esc_html_e( 'Decimal quantities', 'wpc-product-quantity' ); ?>
                        </th>
                        <td>
                            <label> <select name="woopq_settings[decimal]">
                                    <option value="no" <?php selected( $decimal, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-product-quantity' ); ?></option>
                                    <option value="yes" <?php selected( $decimal, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'Press "Update Options" after enabling this option, then you can enter decimal quantities in min, max, step quantity options.', 'wpc-product-quantity' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Plus/minus button', 'wpc-product-quantity' ); ?></th>
                        <td>
                            <label> <select name="woopq_settings[plus_minus]">
                                    <option value="show" <?php selected( $plus_minus, 'show' ); ?>><?php esc_html_e( 'Show', 'wpc-product-quantity' ); ?></option>
                                    <option value="hide" <?php selected( $plus_minus, 'hide' ); ?>><?php esc_html_e( 'Hide', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'Show the plus/minus button for the input type to increase/decrease the quantity.', 'wpc-product-quantity' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Auto-correct', 'wpc-product-quantity' ); ?></th>
                        <td>
                            <label> <select name="woopq_settings[auto_correct]">
                                    <option value="entering" <?php selected( $auto_correct, 'entering' ); ?>><?php esc_html_e( 'While entering', 'wpc-product-quantity' ); ?></option>
                                    <option value="out_of_focus" <?php selected( $auto_correct, 'out_of_focus' ); ?>><?php esc_html_e( 'Out of focus', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'When the auto-correct functionality will be triggered: while entering the number or out of focus on the input (click outside).', 'wpc-product-quantity' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Rounding values', 'wpc-product-quantity' ); ?></th>
                        <td>
                            <label> <select name="woopq_settings[rounding]">
                                    <option value="down" <?php selected( $rounding, 'down' ); ?>><?php esc_html_e( 'Down', 'wpc-product-quantity' ); ?></option>
                                    <option value="up" <?php selected( $rounding, 'up' ); ?>><?php esc_html_e( 'Up', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'Round the quantity to the nearest bigger (up) or smaller (down) value when an invalid number is inputted.', 'wpc-product-quantity' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Works in backend', 'wpc-product-quantity' ); ?></th>
                        <td>
                            <label> <select name="woopq_settings[backend]">
                                    <option value="yes" <?php selected( $backend, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-product-quantity' ); ?></option>
                                    <option value="no" <?php selected( $backend, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                            <span class="description"><?php esc_html_e( 'Quantity rules will be applied for product in the backend or not. E.g, editing products on the order.', 'wpc-product-quantity' ); ?></span>
                        </td>
                    </tr>
                    <tr class="heading">
                        <th>
                            <?php esc_html_e( 'Global rules', 'wpc-product-quantity' ); ?>
                        </th>
                        <td>
                            <?php esc_html_e( 'Conditions will be checked from the top of the list down to the end. Products with no applicable conditions matched will follow the default settings in #default. Quantity rules for individual products can be configured on their single product pages and will be the most prioritized. Order of priority: Individual rules >> Global rules >> Default settings.', 'wpc-product-quantity' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td>
                            <div class="woopq-rules-wrapper">
                                <div class="woopq-add-rule">
                                    <input type="button" class="button woopq-add-rule-btn"
                                           data-product_id="0" data-is_variation="0"
                                           value="<?php esc_attr_e( '+ Add rule', 'wpc-product-quantity' ); ?>">
                                </div>
                                <div class="woopq-items-wrapper">
                                    <div class="woopq-items woopq-rules">
                                        <?php
                                        if ( ! empty( $rules ) ) {
                                            foreach ( $rules as $key => $rule ) {
                                                self::rule( $key, $rule );
                                            }
                                        }
                                        ?>
                                    </div>
                                    <!-- Add a placeholder rule, so you can remove all other rules -->
                                    <input type="hidden"
                                           name="woopq_settings[rules][placeholder][type]"
                                           value="none"/>
                                </div>
                                <div class="woopq-items-wrapper">
                                    <div class="woopq-items">
                                        <div class="woopq-item woopq-item-default woopq_settings_form active">
                                            <div class="woopq-item-header">
                                                <span class="woopq-item-move ui-sortable-handle"><?php esc_html_e( 'move', 'wpc-product-quantity' ); ?></span>
                                                <span class="woopq-item-name"><span
                                                            class="woopq-item-name-key">default</span></span>
                                            </div>
                                            <div class="woopq-item-content">
                                                <div class="woopq-item-line">
                                                    <div class="woopq-item-label"><?php esc_html_e( 'Type', 'wpc-product-quantity' ); ?></div>
                                                    <div class="woopq-item-input">
                                                        <label>
                                                            <select name="woopq_settings[type]"
                                                                    class="woopq_type">
                                                                <option value="default" <?php selected( $type, 'default' ); ?>><?php esc_html_e( 'Input (Default)', 'wpc-product-quantity' ); ?></option>
                                                                <option value="select" <?php selected( $type, 'select' ); ?>><?php esc_html_e( 'Select', 'wpc-product-quantity' ); ?></option>
                                                                <option value="radio" <?php selected( $type, 'radio' ); ?>><?php esc_html_e( 'Radio', 'wpc-product-quantity' ); ?></option>
                                                            </select> </label>
                                                    </div>
                                                </div>
                                                <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_select woopq_show_if_type_radio">
                                                    <div class="woopq-item-label"><?php esc_html_e( 'Values', 'wpc-product-quantity' ); ?></div>
                                                    <div class="woopq-item-input">
                                                        <label>
															<textarea name="woopq_settings[values]"
                                                                      rows="10"
                                                                      cols="50"><?php echo esc_textarea( WPCleverWoopq::get_setting( 'values' ) ); ?></textarea>
                                                        </label>
                                                        <p class="description"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
                                                    </div>
                                                </div>
                                                <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                                    <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
                                                    <div class="woopq-item-input">
                                                        <label>
                                                            <input type="number"
                                                                   name="woopq_settings[min]"
                                                                   min="0"
                                                                   step="<?php echo esc_attr( $step ); ?>"
                                                                   value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'min' ) ); ?>"/>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                                    <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
                                                    <div class="woopq-item-input">
                                                        <label>
                                                            <input type="number"
                                                                   name="woopq_settings[step]"
                                                                   min="0"
                                                                   step="<?php echo esc_attr( $step ); ?>"
                                                                   value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'step' ) ); ?>"/>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                                    <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
                                                    <div class="woopq-item-input">
                                                        <label>
                                                            <input type="number"
                                                                   name="woopq_settings[max]"
                                                                   min="0"
                                                                   step="<?php echo esc_attr( $step ); ?>"
                                                                   value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'max' ) ); ?>"/>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="woopq-item-line">
                                                    <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
                                                    <div class="woopq-item-input">
                                                        <label>
                                                            <input type="number"
                                                                   name="woopq_settings[value]"
                                                                   min="0"
                                                                   step="<?php echo esc_attr( $step ); ?>"
                                                                   value="<?php echo esc_attr( WPCleverWoopq::get_setting( 'value', 1 ) ); ?>"/>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="submit">
                        <th colspan="2">
                            <div class="wpclever_submit">
                                <?php
                                settings_fields( 'woopq_settings' );
                                submit_button( '', 'primary', 'submit', false );

                                if ( function_exists( 'wpc_last_saved' ) ) {
                                    wpc_last_saved( WPCleverWoopq::get_settings() );
                                }
                                ?>
                            </div>
                            <a style="display: none;" class="wpclever_export"
                               data-key="woopq_settings"
                               data-name="settings"
                               href="#"><?php esc_html_e( 'import / export', 'wpc-product-quantity' ); ?></a>
                        </th>
                    </tr>
                </table>
            </form>
            <?php
        }

        /**
         * AJAX handler: search taxonomy terms.
         */
        public function ajax_search_term() {
            if ( ! check_ajax_referer( 'woopq_backend', 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_send_json_error( 'Unauthorized', 403 );
            }

            $return = [];

            $args = [
                    'taxonomy'   => sanitize_text_field( wp_unslash( $_REQUEST['taxonomy'] ?? '' ) ),
                    'orderby'    => 'id',
                    'order'      => 'ASC',
                    'hide_empty' => false,
                    'fields'     => 'all',
                    'name__like' => sanitize_text_field( wp_unslash( $_REQUEST['q'] ?? '' ) ),
            ];

            $terms = get_terms( $args );

            if ( count( $terms ) ) {
                foreach ( $terms as $term ) {
                    $return[] = [ $term->slug, $term->name ];
                }
            }

            wp_send_json( $return );
        }

        /**
         * AJAX handler: add a new rule row.
         */
        public function ajax_add_rule() {
            if ( ! check_ajax_referer( 'woopq_backend', 'nonce', false ) || ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( - 1, 403 );
            }

            $rule         = [];
            $rule_data    = isset( $_POST['rule_data'] ) ? wp_unslash( $_POST['rule_data'] ) : '';
            $product_id   = absint( $_POST['product_id'] ?? 0 );
            $is_variation = wc_string_to_bool( $_POST['is_variation'] ?? false );

            if ( ! empty( $rule_data ) ) {
                $form_rule = [];
                parse_str( $rule_data, $form_rule );

                if ( isset( $form_rule['woopq_settings']['rules'] ) && is_array( $form_rule['woopq_settings']['rules'] ) ) {
                    $rule = reset( $form_rule['woopq_settings']['rules'] );
                }

                if ( isset( $form_rule['_woopq_rules'] ) && is_array( $form_rule['_woopq_rules'] ) ) {
                    $rule = reset( $form_rule['_woopq_rules'] );
                }

                if ( isset( $form_rule['_woopq_rules_v'][ $product_id ] ) && is_array( $form_rule['_woopq_rules_v'][ $product_id ] ) ) {
                    $rule = reset( $form_rule['_woopq_rules_v'][ $product_id ] );
                }
            }

            self::rule( '', $rule, $product_id, $is_variation );
            wp_die();
        }

        /**
         * Render a single rule row.
         *
         * @param string $key Rule key.
         * @param array $rule Rule data.
         * @param int $product_id Product ID (0 for global).
         * @param bool $is_variation Whether this is for a variation.
         */
        public static function rule( $key = '', $rule = [], $product_id = 0, $is_variation = false ) {
            if ( empty( $key ) ) {
                $key = WPCleverWoopq::generate_key();
            }

            $step = WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
            $name = 'woopq_settings[rules]';

            if ( $product_id ) {
                $name = '_woopq_rules';

                if ( $is_variation ) {
                    $name = '_woopq_rules_v[' . $product_id . ']';
                }
            }

            $rule = array_merge( [
                    'apply'     => 'woopq_all',
                    'apply_val' => [],
                    'apply_inc' => 'either',
                    'roles'     => [ 'woopq_all' ],
                    'roles_inc' => 'either',
                    'type'      => 'default',
                    'min'       => '',
                    'step'      => '',
                    'max'       => '',
                    'value'     => '',
                    'values'    => '',
            ], $rule );
            ?>
            <div class="<?php echo esc_attr( 'woopq-rule woopq-item woopq_settings_form woopq-item-' . $key ); ?>">
                <div class="woopq-item-header">
                    <span class="woopq-item-move ui-sortable-handle"><?php esc_html_e( 'move', 'wpc-product-quantity' ); ?></span>
                    <span class="woopq-item-name"><span
                                class="woopq-item-name-key"><?php echo esc_html( $key ); ?></span><span
                                class="woopq-item-name-apply"><?php echo esc_html( $rule['apply'] === 'all' ? 'all' : $rule['apply'] . ': ' . implode( ',', (array) $rule['apply_val'] ) ); ?></span></span>
                    <span class="woopq-item-duplicate" data-product_id="<?php echo esc_attr( $product_id ); ?>"
                          data-is_variation="<?php echo esc_attr( $is_variation ? '1' : '0' ); ?>"><?php esc_html_e( 'duplicate', 'wpc-product-quantity' ); ?></span>
                    <span class="woopq-item-remove"><?php esc_html_e( 'remove', 'wpc-product-quantity' ); ?></span>
                </div>
                <div class="woopq-item-content">
                    <?php if ( ! $product_id ) { ?>
                        <div class="woopq-item-line">
                            <div class="woopq-item-input">
                                <span style="color: #c9356e;font-size: 13px;font-style: italic;">* Global rules only available on the Premium Version.<a
                                            href="https://wpclever.net/downloads/product-quantity?utm_source=pro&utm_medium=woopq&utm_campaign=wporg"
                                            target="_blank">Click here</a> to buy, just $29!</span>
                            </div>
                        </div>
                        <div class="woopq-item-line woopq-item-apply">
                            <div class="woopq-item-label">
                                <?php esc_html_e( 'Apply for', 'wpc-product-quantity' ); ?>
                            </div>
                            <div class="woopq-item-input">
                                <label>
                                    <select class="woopq_apply"
                                            name="<?php echo esc_attr( $name . '[' . $key . '][apply]' ); ?>">
                                        <option value="woopq_all" <?php selected( $rule['apply'], 'woopq_all' ); ?>><?php esc_attr_e( 'All products', 'wpc-product-quantity' ); ?></option>
                                        <?php
                                        $taxonomies = get_object_taxonomies( 'product', 'objects' );

                                        foreach ( $taxonomies as $taxonomy ) {
                                            echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $rule['apply'], $taxonomy->name, false ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                                        }
                                        ?>
                                    </select> </label> <span class="hide_if_apply_all"><label>
<select name="<?php echo esc_attr( $name . '[' . $key . '][apply_inc]' ); ?>">
        <option value="either" <?php selected( $rule['apply_inc'], 'either' ); ?>><?php esc_attr_e( 'Include either', 'wpc-product-quantity' ); ?></option>
        <option value="all" <?php selected( $rule['apply_inc'], 'all' ); ?>><?php esc_attr_e( 'Include all', 'wpc-product-quantity' ); ?></option>
    </select>
</label></span>
                                <div class="hide_if_apply_all">
                                    <label>
                                        <select class="woopq_terms woopq_apply_val" multiple="multiple"
                                                name="<?php echo esc_attr( $name . '[' . $key . '][apply_val][]' ); ?>"
                                                data-<?php echo esc_attr( $rule['apply'] ); ?>="<?php echo esc_attr( implode( ',', (array) $rule['apply_val'] ) ); ?>">
                                            <?php if ( is_array( $rule['apply_val'] ) && ! empty( $rule['apply_val'] ) ) {
                                                foreach ( $rule['apply_val'] as $t ) {
                                                    if ( $term = get_term_by( 'slug', $t, $rule['apply'] ) ) {
                                                        echo '<option value="' . esc_attr( $t ) . '" selected>' . esc_html( $term->name ) . '</option>';
                                                    }
                                                }
                                            } ?>
                                        </select> </label>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="woopq-item-line">
                        <div class="woopq-item-label">
                            <?php esc_html_e( 'User roles', 'wpc-product-quantity' ); ?>
                        </div>
                        <div class="woopq-item-input">
                            <label>
                                <select name="<?php echo esc_attr( $name . '[' . $key . '][roles_inc]' ); ?>">
                                    <option value="either" <?php selected( $rule['roles_inc'], 'either' ); ?>><?php esc_attr_e( 'Include either', 'wpc-product-quantity' ); ?></option>
                                    <option value="all" <?php selected( $rule['roles_inc'], 'all' ); ?>><?php esc_attr_e( 'Include all', 'wpc-product-quantity' ); ?></option>
                                </select> </label> <label>
                                <select name="<?php echo esc_attr( $name . '[' . $key . '][roles][]' ); ?>"
                                        multiple class="woopq_roles_select">
                                    <?php
                                    global $wp_roles;
                                    $roles = ( ! empty( $rule['roles'] ) ) ? (array) $rule['roles'] : [ 'woopq_all' ];

                                    echo '<option value="woopq_all" ' . ( in_array( 'woopq_all', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-product-quantity' ) . '</option>';
                                    echo '<option value="woopq_user" ' . ( in_array( 'woopq_user', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-product-quantity' ) . '</option>';
                                    echo '<option value="woopq_guest" ' . ( in_array( 'woopq_guest', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-product-quantity' ) . '</option>';

                                    foreach ( $wp_roles->roles as $role => $details ) {
                                        echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
                                    }
                                    ?>
                                </select> </label>
                        </div>
                    </div>
                    <div class="woopq-item-line">
                        <div class="woopq-item-label"><?php esc_html_e( 'Type', 'wpc-product-quantity' ); ?></div>
                        <div class="woopq-item-input">
                            <label>
                                <select name="<?php echo esc_attr( $name . '[' . $key . '][type]' ); ?>"
                                        class="woopq_type">
                                    <option value="default" <?php echo esc_attr( $rule['type'] === 'default' ? 'selected' : '' ); ?>><?php esc_html_e( 'Input (Default)', 'wpc-product-quantity' ); ?></option>
                                    <option value="select" <?php echo esc_attr( $rule['type'] === 'select' ? 'selected' : '' ); ?>><?php esc_html_e( 'Select', 'wpc-product-quantity' ); ?></option>
                                    <option value="radio" <?php echo esc_attr( $rule['type'] === 'radio' ? 'selected' : '' ); ?>><?php esc_html_e( 'Radio', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                        </div>
                    </div>
                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_select woopq_show_if_type_radio">
                        <div class="woopq-item-label"><?php esc_html_e( 'Values', 'wpc-product-quantity' ); ?></div>
                        <div class="woopq-item-input">
                            <label>
								<textarea name="<?php echo esc_attr( $name . '[' . $key . '][values]' ); ?>"
                                          rows="10" cols="50"
                                          style="float: none; width: 100%; height: 200px"><?php echo esc_textarea( $rule['values'] ); ?></textarea>
                            </label>
                            <p class="description"
                               style="margin-left: 0"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
                        </div>
                    </div>
                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                        <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
                        <div class="woopq-item-input">
                            <label>
                                <input type="number"
                                       name="<?php echo esc_attr( $name . '[' . $key . '][min]' ); ?>" min="0"
                                       step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                                       value="<?php echo esc_attr( $rule['min'] ); ?>"/>
                            </label>
                        </div>
                    </div>
                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                        <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
                        <div class="woopq-item-input">
                            <label>
                                <input type="number"
                                       name="<?php echo esc_attr( $name . '[' . $key . '][step]' ); ?>" min="0"
                                       step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                                       value="<?php echo esc_attr( $rule['step'] ); ?>"/>
                            </label>
                        </div>
                    </div>
                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                        <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
                        <div class="woopq-item-input">
                            <label>
                                <input type="number"
                                       name="<?php echo esc_attr( $name . '[' . $key . '][max]' ); ?>" min="0"
                                       step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                                       value="<?php echo esc_attr( $rule['max'] ); ?>"/>
                            </label>
                        </div>
                    </div>
                    <div class="woopq-item-line">
                        <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
                        <div class="woopq-item-input">
                            <label>
                                <input type="number"
                                       name="<?php echo esc_attr( $name . '[' . $key . '][value]' ); ?>" min="0"
                                       step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                                       value="<?php echo esc_attr( $rule['value'] ); ?>"/>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Add plugin action links.
         *
         * @param array $links Plugin links.
         * @param string $file Plugin file.
         *
         * @return array
         */
        public function action_links( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOPQ_FILE );
            }

            if ( $plugin === $file ) {
                $settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-product-quantity' ) . '</a>';
                $links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-product-quantity' ) . '</a>';
                array_unshift( $links, $settings );
            }

            return (array) $links;
        }

        /**
         * Add plugin row meta links.
         *
         * @param array $links Plugin links.
         * @param string $file Plugin file.
         *
         * @return array
         */
        public function row_meta( $links, $file ) {
            static $plugin;

            if ( ! isset( $plugin ) ) {
                $plugin = plugin_basename( WOOPQ_FILE );
            }

            if ( $plugin === $file ) {
                $row_meta = [
                        'support' => '<a href="' . esc_url( WOOPQ_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-product-quantity' ) . '</a>',
                ];

                return array_merge( $links, $row_meta );
            }

            return (array) $links;
        }

        /**
         * Add product data tab.
         *
         * @param array $tabs Existing tabs.
         *
         * @return array
         */
        public function product_data_tabs( $tabs ) {
            $tabs['woopq'] = [
                    'label'  => esc_html__( 'Quantity', 'wpc-product-quantity' ),
                    'target' => 'woopq_settings',
            ];

            return $tabs;
        }

        /**
         * Render product data panel.
         */
        public function product_data_panels() {
            global $post, $thepostid, $product_object;

            if ( $product_object instanceof WC_Product ) {
                $product_id = $product_object->get_id();
            } elseif ( is_numeric( $thepostid ) ) {
                $product_id = $thepostid;
            } elseif ( $post instanceof WP_Post ) {
                $product_id = $post->ID;
            } else {
                $product_id = 0;
            }

            if ( ! $product_id ) {
                ?>
                <div id='woopq_settings'
                     class='woopq_table panel woocommerce_options_panel woopq_settings_form'>
                    <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-product-quantity' ); ?></p>
                </div>
                <?php
                return;
            }

            self::product_settings( $product_id );
        }

        /**
         * Render product/variation quantity settings panel.
         *
         * @param int $product_id Product or variation ID.
         * @param bool $is_variation Whether this is a variation.
         */
        public static function product_settings( $product_id, $is_variation = false ) {
            $step     = WPCleverWoopq::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
            $quantity = WPCleverWoopq::get_quantity( $product_id );
            $type     = WPCleverWoopq::get_type( $product_id );
            $rules    = (array) ( get_post_meta( $product_id, '_woopq_rules', true ) ?: [] );
            unset( $rules['placeholder'] );

            $name  = '';
            $id    = 'woopq_settings';
            $class = 'woopq_table panel woocommerce_options_panel woopq_product_settings woopq_settings_form';

            if ( $is_variation ) {
                $name  = '_v[' . $product_id . ']';
                $id    = 'woopq_settings_' . $product_id;
                $class = 'woopq_table woopq_product_settings woopq_settings_form';
            }
            ?>
            <div id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>">
                <div class="woopq_tr">
                    <div class="woopq_td"><?php esc_html_e( 'Quantity', 'wpc-product-quantity' ); ?></div>
                    <div class="woopq_td">
                        <?php if ( $is_variation ) { ?>
                            <label>
                                <select name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>"
                                        class="woopq_active_select">
                                    <option value="default" <?php selected( $quantity, 'default' ); ?>><?php esc_html_e( 'Global', 'wpc-product-quantity' ); ?></option>
                                    <option value="parent" <?php selected( $quantity, 'parent' ); ?>><?php esc_html_e( 'Parent', 'wpc-product-quantity' ); ?></option>
                                    <option value="disable" <?php selected( $quantity, 'disable' ); ?>><?php esc_html_e( 'Disable', 'wpc-product-quantity' ); ?></option>
                                    <option value="overwrite" <?php selected( $quantity, 'overwrite' ); ?>
                                            disabled><?php esc_html_e( 'Override', 'wpc-product-quantity' ); ?></option>
                                </select> </label>
                        <?php } else { ?>
                            <div class="woopq_active_wrapper">
                                <div class="woopq_active">
                                    <label>
                                        <input name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>"
                                               type="radio" class="woopq_active_input"
                                               value="default" <?php checked( $quantity, 'default' ); ?>/>
                                        <?php esc_html_e( 'Global', 'wpc-product-quantity' ); ?>
                                    </label> (<a
                                            href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>"
                                            target="_blank"><?php esc_html_e( 'settings', 'wpc-product-quantity' ); ?></a>)
                                </div>
                                <div class="woopq_active">
                                    <label>
                                        <input name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>"
                                               type="radio" class="woopq_active_input"
                                               value="disable" <?php checked( $quantity, 'disable' ); ?>/>
                                        <?php esc_html_e( 'Disable', 'wpc-product-quantity' ); ?>
                                    </label>
                                </div>
                                <div class="woopq_active">
                                    <label>
                                        <input name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>"
                                               type="radio" class="woopq_active_input" disabled
                                               value="overwrite" <?php checked( $quantity, 'overwrite' ); ?>/>
                                        <?php esc_html_e( 'Override', 'wpc-product-quantity' ); ?>
                                    </label>
                                </div>
                            </div>
                        <?php } ?>
                        <div style="color: #c9356e; padding-left: 0; padding-right: 0; margin-top: 10px; font-size: 13px; font-style: italic">You only can
                            use the
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>"
                               target="_blank">default settings</a> for all products and variations.<br/>Quantity
                            settings at a product or variation basis only available on the Premium Version.
                            <a href="https://wpclever.net/downloads/product-quantity?utm_source=pro&utm_medium=woopq&utm_campaign=wporg"
                               target="_blank">Click here</a> to buy, just $29!
                        </div>
                    </div>
                </div>
                <div class="woopq_show_if_overwrite">
                    <div class="woopq-rules-wrapper">
                        <div class="woopq-add-rule">
                            <input type="button" class="button woopq-add-rule-btn"
                                   data-product_id="<?php echo esc_attr( $product_id ); ?>"
                                   data-is_variation="<?php echo esc_attr( $is_variation ? '1' : '0' ); ?>"
                                   value="<?php esc_attr_e( '+ Add rule', 'wpc-product-quantity' ); ?>">
                        </div>
                        <div class="woopq-items-wrapper">
                            <div class="woopq-items woopq-rules">
                                <?php
                                if ( ! empty( $rules ) ) {
                                    foreach ( $rules as $key => $rule ) {
                                        self::rule( $key, $rule, $product_id, $is_variation );
                                    }
                                }
                                ?>
                            </div>
                            <?php
                            // Add a placeholder rule, so you can remove all other rules
                            if ( $is_variation ) {
                                echo '<input type="hidden" name="_woopq_rules_v[' . $product_id . '][placeholder][type]" value="none"/>';
                            } else {
                                echo '<input type="hidden" name="_woopq_rules[placeholder][type]" value="none"/>';
                            }
                            ?>
                        </div>
                        <div class="woopq-items-wrapper">
                            <div class="woopq-items">
                                <div class="woopq-item woopq-item-default woopq_settings_form active">
                                    <div class="woopq-item-header">
                                        <span class="woopq-item-move ui-sortable-handle"><?php esc_html_e( 'move', 'wpc-product-quantity' ); ?></span>
                                        <span class="woopq-item-name"><span
                                                    class="woopq-item-name-key">default</span></span>
                                    </div>
                                    <div class="woopq-item-content">
                                        <div class="woopq-item-line">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Type', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                <label>
                                                    <select name="<?php echo esc_attr( '_woopq_type' . $name ); ?>"
                                                            class="woopq_type">
                                                        <option value="default" <?php selected( $type, 'default' ); ?>><?php esc_html_e( 'Input (Default)', 'wpc-product-quantity' ); ?></option>
                                                        <option value="select" <?php selected( $type, 'select' ); ?>><?php esc_html_e( 'Select', 'wpc-product-quantity' ); ?></option>
                                                        <option value="radio" <?php selected( $type, 'radio' ); ?>><?php esc_html_e( 'Radio', 'wpc-product-quantity' ); ?></option>
                                                    </select> </label>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_select woopq_show_if_type_radio">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Values', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                <label>
													<textarea
                                                            name="<?php echo esc_attr( '_woopq_values' . $name ); ?>"
                                                            rows="10"
                                                            cols="50"><?php echo esc_textarea( get_post_meta( $product_id, '_woopq_values', true ) ); ?></textarea>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                <label>
                                                    <input type="number"
                                                           name="<?php echo esc_attr( '_woopq_min' . $name ); ?>"
                                                           min="0" step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( get_post_meta( $product_id, '_woopq_min', true ) ); ?>"/>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                <label>
                                                    <input type="number"
                                                           name="<?php echo esc_attr( '_woopq_step' . $name ); ?>"
                                                           min="0" step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( get_post_meta( $product_id, '_woopq_step', true ) ); ?>"/>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                <label>
                                                    <input type="number"
                                                           name="<?php echo esc_attr( '_woopq_max' . $name ); ?>"
                                                           min="0" step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( get_post_meta( $product_id, '_woopq_max', true ) ); ?>"/>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="woopq-item-line">
                                            <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
                                            <div class="woopq-item-input">
                                                <label>
                                                    <input type="number"
                                                           name="<?php echo esc_attr( '_woopq_value' . $name ); ?>"
                                                           min="0" step="<?php echo esc_attr( $step ); ?>"
                                                           value="<?php echo esc_attr( get_post_meta( $product_id, '_woopq_value', true ) ); ?>"/>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Save product meta on product save.
         *
         * @param int $post_id Product ID.
         */
        public function process_product_meta( $post_id ) {
            $text_fields = [
                    '_woopq_quantity',
                    '_woopq_type',
                    '_woopq_min',
                    '_woopq_step',
                    '_woopq_max',
                    '_woopq_value'
            ];

            foreach ( $text_fields as $field ) {
                if ( isset( $_POST[ $field ] ) ) {
                    update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
                } else {
                    delete_post_meta( $post_id, $field );
                }
            }
        }

        /**
         * Render variation-level quantity settings.
         *
         * @param int $loop Variation loop index.
         * @param array $variation_data Variation data.
         * @param object $variation Variation post object.
         */
        public function variation_settings( $loop, $variation_data, $variation ) {
            $variation_id = absint( $variation->ID );
            ?>
            <div class="form-row form-row-full woopq-variation-settings">
                <label><?php esc_html_e( 'WPC Product Quantity', 'wpc-product-quantity' ); ?></label>
                <div class="woopq-variation-wrap woopq-variation-wrap-<?php echo esc_attr( $variation_id ); ?>">
                    <?php self::product_settings( $variation_id, true ); ?>
                </div>
            </div>
            <?php
        }

        /**
         * Save variation-level quantity settings.
         *
         * @param int $post_id Variation ID.
         */
        public function save_variation_settings( $post_id ) {
            $text_fields = [
                    '_woopq_quantity_v' => '_woopq_quantity',
                    '_woopq_type_v'     => '_woopq_type',
                    '_woopq_min_v'      => '_woopq_min',
                    '_woopq_step_v'     => '_woopq_step',
                    '_woopq_max_v'      => '_woopq_max',
                    '_woopq_value_v'    => '_woopq_value',
            ];

            foreach ( $text_fields as $post_key => $meta_key ) {
                if ( isset( $_POST[ $post_key ][ $post_id ] ) ) {
                    update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ][ $post_id ] ) ) );
                } else {
                    delete_post_meta( $post_id, $meta_key );
                }
            }

            // array field
            if ( isset( $_POST['_woopq_rules_v'][ $post_id ] ) ) {
                update_post_meta( $post_id, '_woopq_rules', WPCleverWoopq::sanitize_array( wp_unslash( $_POST['_woopq_rules_v'][ $post_id ] ) ) );
            } else {
                delete_post_meta( $post_id, '_woopq_rules' );
            }

            // textarea field
            if ( isset( $_POST['_woopq_values_v'][ $post_id ] ) ) {
                update_post_meta( $post_id, '_woopq_values', sanitize_textarea_field( wp_unslash( $_POST['_woopq_values_v'][ $post_id ] ) ) );
            } else {
                delete_post_meta( $post_id, '_woopq_values' );
            }
        }

        /**
         * Duplicate variation meta when using WPC Variation Duplicator.
         *
         * @param int $old_variation_id Original variation ID.
         * @param int $new_variation_id New variation ID.
         */
        public function duplicate_variation( $old_variation_id, $new_variation_id ) {
            foreach ( self::$meta_keys as $meta_key ) {
                $value = get_post_meta( $old_variation_id, $meta_key, true );

                if ( $value ) {
                    update_post_meta( $new_variation_id, $meta_key, $value );
                }
            }
        }

        /**
         * Bulk update variation meta when using WPC Variation Bulk Editor.
         *
         * @param int $variation_id Variation ID.
         * @param array $fields Bulk editor fields.
         */
        public function bulk_update_variation( $variation_id, $fields ) {
            if ( ! empty( $fields['_woopq_quantity_v'] ) && ( $fields['_woopq_quantity_v'] !== 'wpcvb_no_change' ) ) {
                update_post_meta( $variation_id, '_woopq_quantity', sanitize_text_field( $fields['_woopq_quantity_v'] ) );
            }

            if ( ! empty( $fields['_woopq_quantity_v'] ) && ( $fields['_woopq_quantity_v'] === 'overwrite' ) && ! empty( $fields['_woopq_rules_v'] ) ) {
                update_post_meta( $variation_id, '_woopq_rules', WPCleverWoopq::sanitize_array( $fields['_woopq_rules_v'] ) );
            }

            if ( ! empty( $fields['_woopq_type_v'] ) && ( $fields['_woopq_type_v'] !== 'wpcvb_no_change' ) ) {
                update_post_meta( $variation_id, '_woopq_type', sanitize_text_field( $fields['_woopq_type_v'] ) );
            }

            $simple_fields = [
                    '_woopq_min_v'   => '_woopq_min',
                    '_woopq_step_v'  => '_woopq_step',
                    '_woopq_max_v'   => '_woopq_max',
                    '_woopq_value_v' => '_woopq_value',
            ];

            foreach ( $simple_fields as $field_key => $meta_key ) {
                if ( ! empty( $fields[ $field_key ] ) ) {
                    update_post_meta( $variation_id, $meta_key, sanitize_text_field( $fields[ $field_key ] ) );
                }
            }

            if ( ! empty( $fields['_woopq_values_v'] ) ) {
                update_post_meta( $variation_id, '_woopq_values', sanitize_textarea_field( $fields['_woopq_values_v'] ) );
            }
        }
    }
}
