<?php
/*
Plugin Name: WPC Product Quantity for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Product Quantity provides powerful controls for product quantity.
Version: 5.0.4
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-product-quantity
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 3.0
WC tested up to: 9.3
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOPQ_VERSION' ) && define( 'WOOPQ_VERSION', '5.0.4' );
! defined( 'WOOPQ_LITE' ) && define( 'WOOPQ_LITE', __FILE__ );
! defined( 'WOOPQ_FILE' ) && define( 'WOOPQ_FILE', __FILE__ );
! defined( 'WOOPQ_URI' ) && define( 'WOOPQ_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOPQ_DIR' ) && define( 'WOOPQ_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOPQ_SUPPORT' ) && define( 'WOOPQ_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woopq&utm_campaign=wporg' );
! defined( 'WOOPQ_REVIEWS' ) && define( 'WOOPQ_REVIEWS', 'https://wordpress.org/support/plugin/wpc-product-quantity/reviews/?filter=5' );
! defined( 'WOOPQ_CHANGELOG' ) && define( 'WOOPQ_CHANGELOG', 'https://wordpress.org/plugins/wpc-product-quantity/#developers' );
! defined( 'WOOPQ_DISCUSSION' ) && define( 'WOOPQ_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-product-quantity' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOPQ_URI );

include 'includes/dashboard/wpc-dashboard.php';
include 'includes/kit/wpc-kit.php';
include 'includes/hpos.php';

if ( ! function_exists( 'woopq_init' ) ) {
	add_action( 'plugins_loaded', 'woopq_init', 11 );

	function woopq_init() {
		// load text-domain
		load_plugin_textdomain( 'wpc-product-quantity', false, basename( __DIR__ ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
			add_action( 'admin_notices', 'woopq_notice_wc' );

			return null;
		}

		if ( ! class_exists( 'WPCleverWoopq' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWoopq {
				protected static $settings = [];
				protected static $instance = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'woopq_settings', [] );

					// enqueue backend
					add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 99 );

					// enqueue frontend
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );

					// settings page
					add_action( 'admin_init', [ $this, 'register_settings' ] );
					add_action( 'admin_menu', [ $this, 'admin_menu' ] );

					// settings link
					add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
					add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

					// args
					add_filter( 'woocommerce_quantity_input_args', [ $this, 'quantity_input_args' ], 99, 2 );
					add_filter( 'woocommerce_loop_add_to_cart_args', [ $this, 'loop_add_to_cart_args' ], 99, 2 );

					// default input
					add_filter( 'woocommerce_quantity_input_min', [ $this, 'quantity_input_min' ], 99, 2 );
					add_filter( 'woocommerce_quantity_input_max', [ $this, 'quantity_input_max' ], 99, 2 );
					add_filter( 'woocommerce_quantity_input_step', [ $this, 'quantity_input_step' ], 99, 2 );

					// admin input
					add_filter( 'woocommerce_quantity_input_min_admin', [ $this, 'quantity_input_min_admin' ], 99, 2 );
					add_filter( 'woocommerce_quantity_input_step_admin', [
						$this,
						'quantity_input_step_admin'
					], 99, 2 );

					// decimal
					if ( self::get_setting( 'decimal', 'no' ) === 'yes' ) {
						remove_filter( 'woocommerce_stock_amount', 'intval' );
						add_filter( 'woocommerce_stock_amount', 'floatval' );
					}

					// fix stock status
					add_filter( 'woocommerce_product_get_stock_status', [ $this, 'get_stock_status' ], 99, 2 );

					// template
					add_filter( 'wc_get_template', [ $this, 'quantity_input_template' ], 99, 2 );

					// add to cart
					add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'add_to_cart_validation' ], 99, 4 );

					// add to cart message
					if ( self::get_setting( 'decimal', 'no' ) === 'yes' ) {
						add_filter( 'wc_add_to_cart_message_html', [ $this, 'add_to_cart_message_html' ], 99, 3 );
					}

					// product settings
					add_filter( 'woocommerce_product_data_tabs', [ $this, 'product_data_tabs' ] );
					add_action( 'woocommerce_product_data_panels', [ $this, 'product_data_panels' ] );
					add_action( 'woocommerce_process_product_meta', [ $this, 'process_product_meta' ] );

					// variation settings
					add_action( 'woocommerce_product_after_variable_attributes', [
						$this,
						'variation_settings'
					], 99, 3 );
					add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_settings' ], 99, 2 );
					add_action( 'woocommerce_before_variations_form', [ $this, 'before_variations_form' ] );

					// AJAX
					add_action( 'wp_ajax_woopq_search_term', [ $this, 'ajax_search_term' ] );
					add_action( 'wp_ajax_woopq_add_rule', [ $this, 'ajax_add_rule' ] );

					// WPC Smart Messages
					add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

					// WPC Variation Duplicator
					add_action( 'wpcvd_duplicated', [ $this, 'duplicate_variation' ], 99, 2 );

					// WPC Variation Bulk Editor
					add_action( 'wpcvb_bulk_update_variation', [ $this, 'bulk_update_variation' ], 99, 2 );
				}

				public static function get_settings() {
					return apply_filters( 'woopq_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
						$setting = self::$settings[ $name ];
					} else {
						$setting = get_option( '_woopq_' . $name, $default );
					}

					return apply_filters( 'woopq_get_setting', $setting, $name, $default );
				}

				function admin_enqueue_scripts( $hook ) {
					if ( apply_filters( 'woopq_ignore_backend_scripts', false, $hook ) ) {
						return null;
					}

					wp_enqueue_style( 'woopq-backend', WOOPQ_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WOOPQ_VERSION );
					wp_enqueue_script( 'woopq-backend', WOOPQ_URI . 'assets/js/backend.js', [
						'jquery',
						'jquery-ui-sortable',
						'wc-enhanced-select',
						'selectWoo'
					], WOOPQ_VERSION, true );
				}

				function enqueue_scripts() {
					wp_enqueue_style( 'woopq-frontend', WOOPQ_URI . 'assets/css/frontend.css', [], WOOPQ_VERSION );
					wp_enqueue_script( 'woopq-frontend', WOOPQ_URI . 'assets/js/frontend.js', [ 'jquery' ], WOOPQ_VERSION, true );
					wp_localize_script( 'woopq-frontend', 'woopq_vars', [
							'rounding'     => self::get_setting( 'rounding', 'down' ),
							'auto_correct' => self::get_setting( 'auto_correct', 'entering' ),
							'timeout'      => apply_filters( 'woopq_auto_correct_timeout', 1000 ),
						]
					);
				}

				function register_settings() {
					// settings
					register_setting( 'woopq_settings', 'woopq_settings' );
				}

				function admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'WPC Product Quantity', 'wpc-product-quantity' ), esc_html__( 'Product Quantity', 'wpc-product-quantity' ), 'manage_options', 'wpclever-woopq', [
						$this,
						'admin_menu_content'
					] );
				}

				function admin_menu_content() {
					$active_tab = sanitize_key( $_GET['tab'] ?? 'settings' );
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Product Quantity', 'wpc-product-quantity' ) . ' ' . esc_html( WOOPQ_VERSION ) . ' ' . ( defined( 'WOOPQ_PREMIUM' ) ? '<span class="premium" style="display: none">' . esc_html__( 'Premium', 'wpc-product-quantity' ) . '</span>' : '' ); ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( /* translators: stars */ esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-product-quantity' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOPQ_REVIEWS ); ?>" target="_blank"><?php esc_html_e( 'Reviews', 'wpc-product-quantity' ); ?></a> |
                                <a href="<?php echo esc_url( WOOPQ_CHANGELOG ); ?>" target="_blank"><?php esc_html_e( 'Changelog', 'wpc-product-quantity' ); ?></a> |
                                <a href="<?php echo esc_url( WOOPQ_DISCUSSION ); ?>" target="_blank"><?php esc_html_e( 'Discussion', 'wpc-product-quantity' ); ?></a>
                            </p>
                        </div>
						<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-product-quantity' ); ?></p>
                            </div>
						<?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
									<?php esc_html_e( 'Settings', 'wpc-product-quantity' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=premium' ) ); ?>" class="<?php echo esc_attr( $active_tab === 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>" style="color: #c9356e">
									<?php esc_html_e( 'Premium Version', 'wpc-product-quantity' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>" class="nav-tab">
									<?php esc_html_e( 'Essential Kit', 'wpc-product-quantity' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab === 'settings' ) {
								// update old settings
								if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
									if ( ! empty( self::$settings ) ) {
										foreach ( self::$settings as $k => $s ) {
											update_option( '_woopq_' . $k, $s );
										}
									}
								}

								$step         = self::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
								$decimal      = self::get_setting( 'decimal', 'no' );
								$plus_minus   = self::get_setting( 'plus_minus', 'hide' );
								$auto_correct = self::get_setting( 'auto_correct', 'entering' );
								$rounding     = self::get_setting( 'rounding', 'down' );
								$backend      = self::get_setting( 'backend', 'yes' );
								$type         = self::get_setting( 'type', 'default' );
								$rules        = self::get_setting( 'rules', [] );
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
                                                        <input type="button" class="button woopq-add-rule-btn" data-product_id="0" data-is_variation="0" value="<?php esc_attr_e( '+ Add rule', 'wpc-product-quantity' ); ?>">
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
                                                        <input type="hidden" name="woopq_settings[rules][placeholder][type]" value="none"/>
                                                    </div>
                                                    <div class="woopq-items-wrapper">
                                                        <div class="woopq-items">
                                                            <div class="woopq-item woopq-item-default woopq_settings_form active">
                                                                <div class="woopq-item-header">
                                                                    <span class="woopq-item-move ui-sortable-handle"><?php esc_html_e( 'move', 'wpc-product-quantity' ); ?></span>
                                                                    <span class="woopq-item-name"><span class="woopq-item-name-key">default</span></span>
                                                                </div>
                                                                <div class="woopq-item-content">
                                                                    <div class="woopq-item-line">
                                                                        <div class="woopq-item-label"><?php esc_html_e( 'Type', 'wpc-product-quantity' ); ?></div>
                                                                        <div class="woopq-item-input">
                                                                            <label>
                                                                                <select name="woopq_settings[type]" class="woopq_type">
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
                                                                                <textarea name="woopq_settings[values]" rows="10" cols="50"><?php echo esc_textarea( self::get_setting( 'values' ) ); ?></textarea>
                                                                            </label>
                                                                            <p class="description"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                                                        <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
                                                                        <div class="woopq-item-input">
                                                                            <label>
                                                                                <input type="number" name="woopq_settings[min]" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( self::get_setting( 'min' ) ); ?>"/>
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                                                        <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
                                                                        <div class="woopq-item-input">
                                                                            <label>
                                                                                <input type="number" name="woopq_settings[step]" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( self::get_setting( 'step' ) ); ?>"/>
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                                                        <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
                                                                        <div class="woopq-item-input">
                                                                            <label>
                                                                                <input type="number" name="woopq_settings[max]" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( self::get_setting( 'max' ) ); ?>"/>
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="woopq-item-line">
                                                                        <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
                                                                        <div class="woopq-item-input">
                                                                            <label>
                                                                                <input type="number" name="woopq_settings[value]" min="0" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( self::get_setting( 'value', 1 ) ); ?>"/>
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
												<?php settings_fields( 'woopq_settings' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab === 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
                                        Get the Premium Version just $29!
                                        <a href="https://wpclever.net/downloads/product-quantity?utm_source=pro&utm_medium=woopq&utm_campaign=wporg" target="_blank">https://wpclever.net/downloads/product-quantity</a>
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
                                    To display custom engaging real-time messages on any wished positions, please install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function ajax_search_term() {
					$return = [];

					$args = [
						'taxonomy'   => sanitize_text_field( $_REQUEST['taxonomy'] ),
						'orderby'    => 'id',
						'order'      => 'ASC',
						'hide_empty' => false,
						'fields'     => 'all',
						'name__like' => sanitize_text_field( $_REQUEST['q'] ),
					];

					$terms = get_terms( $args );

					if ( count( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = [ $term->slug, $term->name ];
						}
					}

					wp_send_json( $return );
				}

				function ajax_add_rule() {
					$rule         = [];
					$rule_data    = $_POST['rule_data'] ?? '';
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

				function rule( $key = '', $rule = [], $product_id = 0, $is_variation = false ) {
					if ( empty( $key ) ) {
						$key = self::generate_key();
					}

					$step = self::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
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
                            <span class="woopq-item-name"><span class="woopq-item-name-key"><?php echo esc_html( $key ); ?></span><span class="woopq-item-name-apply"><?php echo esc_html( $rule['apply'] === 'all' ? 'all' : $rule['apply'] . ': ' . implode( ',', (array) $rule['apply_val'] ) ); ?></span></span>
                            <span class="woopq-item-duplicate" data-product_id="<?php echo esc_attr( $product_id ); ?>" data-is_variation="<?php echo esc_attr( $is_variation ? '1' : '0' ); ?>"><?php esc_html_e( 'duplicate', 'wpc-product-quantity' ); ?></span>
                            <span class="woopq-item-remove"><?php esc_html_e( 'remove', 'wpc-product-quantity' ); ?></span>
                        </div>
                        <div class="woopq-item-content">
							<?php if ( ! $product_id ) { ?>
                                <div class="woopq-item-line">
                                    <div class="woopq-item-input">
                                        <span style="color: #c9356e;">* Global rules only available on the Premium Version.<a href="https://wpclever.net/downloads/product-quantity?utm_source=pro&utm_medium=woopq&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!</span>
                                    </div>
                                </div>
                                <div class="woopq-item-line woopq-item-apply">
                                    <div class="woopq-item-label">
										<?php esc_html_e( 'Apply for', 'wpc-product-quantity' ); ?>
                                    </div>
                                    <div class="woopq-item-input">
                                        <label>
                                            <select class="woopq_apply" name="<?php echo esc_attr( $name . '[' . $key . '][apply]' ); ?>">
                                                <option value="woopq_all" <?php selected( $rule['apply'], 'woopq_all' ); ?>><?php esc_attr_e( 'All products', 'wpc-product-quantity' ); ?></option>
												<?php
												$taxonomies = get_object_taxonomies( 'product', 'objects' ); //$taxonomies = get_taxonomies( [ 'object_type' => [ 'product' ] ], 'objects' );

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
                                                <select class="woopq_terms woopq_apply_val" multiple="multiple" name="<?php echo esc_attr( $name . '[' . $key . '][apply_val][]' ); ?>" data-<?php echo esc_attr( $rule['apply'] ); ?>="<?php echo esc_attr( implode( ',', (array) $rule['apply_val'] ) ); ?>">
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
                                        <select name="<?php echo esc_attr( $name . '[' . $key . '][roles][]' ); ?>" multiple class="woopq_roles_select">
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
                                        <select name="<?php echo esc_attr( $name . '[' . $key . '][type]' ); ?>" class="woopq_type">
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
                                        <textarea name="<?php echo esc_attr( $name . '[' . $key . '][values]' ); ?>" rows="10" cols="50" style="float: none; width: 100%; height: 200px"><?php echo $rule['values']; ?></textarea>
                                    </label>
                                    <p class="description" style="margin-left: 0"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
                                </div>
                            </div>
                            <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
                                <div class="woopq-item-input">
                                    <label>
                                        <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][min]' ); ?>" min="0" step="<?php echo esc_attr( $step ); ?>" style="width: 120px" value="<?php echo esc_attr( $rule['min'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
                                <div class="woopq-item-input">
                                    <label>
                                        <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][step]' ); ?>" min="0" step="<?php echo esc_attr( $step ); ?>" style="width: 120px" value="<?php echo esc_attr( $rule['step'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
                                <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
                                <div class="woopq-item-input">
                                    <label>
                                        <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][max]' ); ?>" min="0" step="<?php echo esc_attr( $step ); ?>" style="width: 120px" value="<?php echo esc_attr( $rule['max'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                            <div class="woopq-item-line">
                                <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
                                <div class="woopq-item-input">
                                    <label>
                                        <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][value]' ); ?>" min="0" step="<?php echo esc_attr( $step ); ?>" style="width: 120px" value="<?php echo esc_attr( $rule['value'] ); ?>"/>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function action_links( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$settings             = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-product-quantity' ) . '</a>';
						$links['wpc-premium'] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=premium' ) ) . '">' . esc_html__( 'Premium Version', 'wpc-product-quantity' ) . '</a>';
						array_unshift( $links, $settings );
					}

					return (array) $links;
				}

				function row_meta( $links, $file ) {
					static $plugin;

					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}

					if ( $plugin === $file ) {
						$row_meta = [
							'support' => '<a href="' . esc_url( WOOPQ_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-product-quantity' ) . '</a>',
						];

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function loop_add_to_cart_args( $args, $product ) {
					if ( $product ) {
						$woopq_value = self::get_value( $product );
						$woopq_min   = self::get_min( $product );

						if ( ! empty( $woopq_min ) && ( $woopq_value < $woopq_min ) ) {
							$args['quantity'] = $woopq_min;
						} else {
							$args['quantity'] = $woopq_value;
						}
					}

					return $args;
				}

				function quantity_input_args( $args, $product ) {
					if ( $product ) {
						$args['product_id'] = $product->get_id();
						$args['min_value']  = self::get_min( $product, $args['min_value'] );
						$args['max_value']  = self::get_max( $product, $args['max_value'] );
						$args['step']       = self::get_step( $product, $args['step'] );

						if ( ! empty( $args['input_name'] ) && ( str_starts_with( $args['input_name'], 'quantity' ) ) ) {
							// check if isn't in the cart
							$args['input_value'] = self::get_value( $product, $args['input_value'] );
						}
					}

					return $args;
				}

				function quantity_input_min( $min, $product ) {
					if ( $product ) {
						return self::get_min( $product, $min );
					}

					return $min;
				}

				function quantity_input_max( $max, $product ) {
					if ( $product ) {
						return self::get_max( $product, $max );
					}

					return $max;
				}

				function quantity_input_step( $step, $product ) {
					if ( $product ) {
						return self::get_step( $product, $step );
					}

					return $step;
				}

				function quantity_input_min_admin( $min, $product ) {
					$backend = self::get_setting( 'backend', 'yes' ) === 'yes';

					if ( ! $backend || apply_filters( 'woopq_ignore_admin_input', false, 'min' ) ) {
						return '0';
					} elseif ( $product ) {
						return self::get_min( $product, $min );
					}

					return $min;
				}

				function quantity_input_step_admin( $step, $product ) {
					$backend = self::get_setting( 'backend', 'yes' ) === 'yes';

					if ( ! $backend || apply_filters( 'woopq_ignore_admin_input', false, 'step' ) ) {
						return 'any';
					} elseif ( $product ) {
						return self::get_step( $product, $step );
					}

					return $step;
				}

				function get_quantity( $product, $is_variation = false ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					if ( $is_variation || $product->is_type( 'variation' ) ) {
						return apply_filters( 'woopq_quantity', get_post_meta( $product_id, '_woopq_quantity', true ) ?: 'parent', $product_id );
					}

					return apply_filters( 'woopq_quantity', get_post_meta( $product_id, '_woopq_quantity', true ) ?: 'default', $product_id );
				}

				function get_type( $product ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$woopq_type = 'default';
					$quantity   = self::get_quantity( $product_id );

					switch ( $quantity ) {
						case 'disable':
							$woopq_type = 'hidden';

							break;
						case 'global':
						case 'default':
							$woopq_type = self::get_global_setting( 'type', $product );

							break;
						case 'parent':
							if ( $product->is_type( 'variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								return self::get_type( $parent_id );
							}

							break;
						default:
							$woopq_type = self::get_product_setting( 'type', $product );

							break;
					}

					return apply_filters( 'woopq_type', $woopq_type, $product_id );
				}

				function get_min( $product, $min = 0 ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$woopq_min = $min;
					$quantity  = self::get_quantity( $product );

					switch ( $quantity ) {
						case 'disable':
							break;
						case 'global':
						case 'default':
							if ( self::get_type( $product_id ) !== 'default' ) {
								$woopq_values = self::get_values( $product );

								if ( ! empty( $woopq_values ) ) {
									$woopq_min = min( array_column( $woopq_values, 'value' ) );
								}
							} else {
								$woopq_min = self::get_global_setting( 'min', $product );
							}

							break;
						case 'parent':
							if ( $product->is_type( 'variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								return self::get_min( wc_get_product( $parent_id ) );
							}

							break;
						default:
							if ( self::get_type( $product_id ) !== 'default' ) {
								$woopq_values = self::get_values( $product );

								if ( ! empty( $woopq_values ) ) {
									$woopq_min = min( array_column( $woopq_values, 'value' ) );
								}
							} else {
								$woopq_min = self::get_product_setting( 'min', $product );
							}

							break;
					}

					if ( ! is_numeric( $woopq_min ) ) {
						// leave blank to disable
						$woopq_min = $min;
					}

					$woopq_min = (float) $woopq_min;

					if ( self::get_setting( 'decimal', 'no' ) !== 'yes' ) {
						$woopq_min = ceil( $woopq_min );
					}

					return apply_filters( 'woopq_min', $woopq_min, $product_id, $product );
				}

				function get_max( $product, $max = 100000, $max_value = null ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$woopq_max = $max;
					$quantity  = self::get_quantity( $product );

					if ( ! $max_value ) {
						$max_value = $product->get_max_purchase_quantity();
					}

					switch ( $quantity ) {
						case 'disable':
							break;
						case 'global':
						case 'default':
							if ( self::get_type( $product_id ) !== 'default' ) {
								$woopq_values = self::get_values( $product );

								if ( ! empty( $woopq_values ) ) {
									$woopq_max = max( array_column( $woopq_values, 'value' ) );
								}
							} else {
								$woopq_max = self::get_global_setting( 'max', $product );
							}

							break;
						case 'parent':
							if ( $product->is_type( 'variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								return self::get_max( wc_get_product( $parent_id ), $max, $max_value );
							}

							break;
						default:
							if ( self::get_type( $product_id ) !== 'default' ) {
								$woopq_values = self::get_values( $product );

								if ( ! empty( $woopq_values ) ) {
									$woopq_max = max( array_column( $woopq_values, 'value' ) );
								}
							} else {
								$woopq_max = self::get_product_setting( 'max', $product );
							}

							break;
					}

					if ( ! is_numeric( $woopq_max ) ) {
						// leave blank to disable
						$woopq_max = $max;
					}

					$woopq_max = (float) $woopq_max;

					if ( ( $max_value > 0 ) && ( $woopq_max > $max_value ) ) {
						$woopq_max = $max_value;
					}

					if ( self::get_setting( 'decimal', 'no' ) !== 'yes' ) {
						$woopq_max = ceil( $woopq_max );
					}

					return apply_filters( 'woopq_max', $woopq_max, $product_id, $product );
				}

				function get_step( $product, $step = 1 ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$woopq_step = $step;
					$quantity   = self::get_quantity( $product );

					switch ( $quantity ) {
						case 'disable':
							break;
						case 'global':
						case 'default':
							$woopq_step = self::get_global_setting( 'step', $product );

							break;
						case 'parent':
							if ( $product->is_type( 'variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								return self::get_step( wc_get_product( $parent_id ) );
							}

							break;
						default:
							$woopq_step = self::get_product_setting( 'step', $product );

							break;
					}

					if ( ! is_numeric( $woopq_step ) ) {
						// leave blank to disable
						$woopq_step = $step;
					}

					$woopq_step = (float) $woopq_step;

					if ( self::get_setting( 'decimal', 'no' ) !== 'yes' ) {
						$woopq_step = ceil( $woopq_step );
					}

					return apply_filters( 'woopq_step', $woopq_step, $product_id, $product );
				}

				function get_value( $product, $value = 1 ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$woopq_value = $value;
					$quantity    = self::get_quantity( $product );

					switch ( $quantity ) {
						case 'disable':
							break;
						case 'global':
						case 'default':
							$woopq_value = self::get_global_setting( 'value', $product );

							break;
						case 'parent':
							if ( $product->is_type( 'variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								return self::get_value( wc_get_product( $parent_id ) );
							}

							break;
						default:
							$woopq_value = self::get_product_setting( 'value', $product );

							break;
					}

					if ( ! is_numeric( $woopq_value ) ) {
						// leave blank to disable
						$woopq_value = $value;
					}

					$woopq_value = (float) $woopq_value;

					if ( self::get_setting( 'decimal', 'no' ) !== 'yes' ) {
						$woopq_value = ceil( $woopq_value );
					}

					return apply_filters( 'woopq_value', $woopq_value, $product_id, $product );
				}

				function get_values( $product, $values = '' ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
						$product    = wc_get_product( $product_id );
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$quantity = self::get_quantity( $product );

					switch ( $quantity ) {
						case 'disable':
							break;
						case 'global':
						case 'default':
							$values = self::get_global_setting( 'values', $product );

							break;
						case 'parent':
							if ( $product->is_type( 'variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								return self::get_values( wc_get_product( $parent_id ) );
							}

							break;
						default:
							$values = self::get_product_setting( 'values', $product );

							break;
					}

					$woopq_values  = [];
					$woopq_decimal = self::get_setting( 'decimal', 'no' );
					$values_arr    = explode( "\n", $values );

					if ( count( $values_arr ) > 0 ) {
						foreach ( $values_arr as $item ) {
							$item_value = self::clean_value( $item );

							if ( str_contains( $item_value, '-' ) ) {
								// quantity range e.g 1-10
								$item_value_arr = explode( '-', $item_value );

								for ( $i = (int) $item_value_arr[0]; $i <= (int) $item_value_arr[1]; $i ++ ) {
									$woopq_values[] = [ 'name' => $i, 'value' => $i ];
								}
							} elseif ( is_numeric( $item_value ) ) {
								if ( $woopq_decimal !== 'yes' ) {
									$woopq_values[] = [
										'name'  => esc_html( trim( $item ) ),
										'value' => (int) $item_value
									];
								} else {
									$woopq_values[] = [
										'name'  => esc_html( trim( $item ) ),
										'value' => (float) $item_value
									];
								}
							}
						}
					}

					if ( empty( $woopq_values ) ) {
						// default values
						$woopq_values = apply_filters( 'woopq_default_values', [
							[ 'name' => '1', 'value' => 1 ],
							[ 'name' => '2', 'value' => 2 ],
							[ 'name' => '3', 'value' => 3 ],
							[ 'name' => '4', 'value' => 4 ],
							[ 'name' => '5', 'value' => 5 ],
							[ 'name' => '6', 'value' => 6 ],
							[ 'name' => '7', 'value' => 7 ],
							[ 'name' => '8', 'value' => 8 ],
							[ 'name' => '9', 'value' => 9 ],
							[ 'name' => '10', 'value' => 10 ]
						] );
					} else {
						$woopq_values = array_intersect_key( $woopq_values, array_unique( array_map( 'serialize', $woopq_values ) ) );
					}

					return apply_filters( 'woopq_values', $woopq_values, $product_id, $product );
				}

				function get_global_setting( $name = 'type', $product = null ) {
					// default setting
					$setting = self::get_setting( $name );

					return apply_filters( 'woopq_get_global_setting', $setting, $name, $product );
				}

				function get_product_setting( $name = 'type', $product = null ) {
					if ( is_numeric( $product ) ) {
						$product_id = $product;
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					$setting = get_post_meta( $product_id, '_woopq_' . $name, true );
					$setting = $setting !== '' ? $setting : 'default';

					return apply_filters( 'get_product_setting', $setting, $name, $product );
				}

				function check_apply( $product, $rule ) {
					$apply     = $rule['apply'] ?? 'woopq_all';
					$apply_val = $rule['apply_val'] ?? [];
					$apply_inc = $rule['apply_inc'] ?? 'either';

					if ( is_numeric( $product ) ) {
						$product_id = $product;
					} elseif ( is_a( $product, 'WC_Product' ) ) {
						$product_id = $product->get_id();
					} else {
						$product_id = 0;
					}

					if ( ! $product_id ) {
						return false;
					}

					if ( empty( $apply ) || ( $apply === 'woopq_all' ) || empty( $apply_val ) ) {
						return true;
					}

					if ( $apply_inc === 'all' ) {
						foreach ( $apply_val as $term ) {
							if ( ! has_term( $term, $apply, $product_id ) ) {
								return false;
							}
						}

						return true;
					} else {
						// either
						if ( has_term( $apply_val, $apply, $product_id ) ) {
							return true;
						}
					}

					return false;
				}

				function check_roles( $rule ) {
					$roles     = $rule['roles'] ?? [];
					$roles_inc = $rule['roles_inc'] ?? 'either';

					if ( is_string( $roles ) ) {
						$roles = explode( ',', $roles );
					}

					if ( empty( $roles ) || in_array( 'woopq_all', (array) $roles ) ) {
						return true;
					}

					if ( is_user_logged_in() ) {
						if ( in_array( 'woopq_user', (array) $roles ) ) {
							return true;
						}

						$current_user = wp_get_current_user();

						if ( $roles_inc === 'all' ) {
							foreach ( $roles as $role ) {
								if ( ! in_array( $role, $current_user->roles ) ) {
									return false;
								}
							}

							return true;
						} else {
							// either
							foreach ( $current_user->roles as $role ) {
								if ( in_array( $role, (array) $roles ) ) {
									return true;
								}
							}
						}
					} else {
						if ( in_array( 'woopq_guest', (array) $roles ) ) {
							return true;
						}
					}

					return false;
				}

				function quantity_input_template( $located, $template_name ) {
					if ( $template_name === 'global/quantity-input.php' ) {
						return WOOPQ_DIR . 'templates/quantity-input.php';
					}

					return $located;
				}

				function get_stock_status( $stock_status, $product ) {
					if ( ! $product->get_manage_stock() ) {
						return $stock_status;
					}

					$stock_quantity                        = self::get_setting( 'decimal', 'no' ) === 'yes' ? (float) $product->get_stock_quantity() : (int) $product->get_stock_quantity();
					$stock_is_above_notification_threshold = ( $stock_quantity > absint( get_option( 'woocommerce_notify_no_stock_amount', 0 ) ) );
					$backorders_are_allowed                = ( 'no' !== $product->get_backorders() );

					if ( $stock_is_above_notification_threshold ) {
						$stock_status = 'instock';
					} elseif ( $backorders_are_allowed ) {
						$stock_status = 'onbackorder';
					} else {
						$stock_status = 'outofstock';
					}

					return apply_filters( 'woopq_product_get_stock_status', $stock_status, $product );
				}

				function product_data_tabs( $tabs ) {
					$tabs['woopq'] = [
						'label'  => esc_html__( 'Quantity', 'wpc-product-quantity' ),
						'target' => 'woopq_settings',
					];

					return $tabs;
				}

				function product_data_panels() {
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
                        <div id='woopq_settings' class='woopq_table panel woocommerce_options_panel woopq_settings_form'>
                            <p style="padding: 0 12px; color: #c9356e"><?php esc_html_e( 'Product wasn\'t returned.', 'wpc-product-quantity' ); ?></p>
                        </div>
						<?php
						return;
					}

					self::product_settings( $product_id );
				}

				function product_settings( $product_id, $is_variation = false ) {
					$step     = self::get_setting( 'decimal', 'no' ) === 'yes' ? '0.000001' : '1';
					$quantity = self::get_quantity( $product_id, $is_variation );
					$type     = self::get_type( $product_id );
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
                                        <select name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>" class="woopq_active_select">
                                            <option value="default" <?php selected( $quantity, 'default' ); ?>><?php esc_html_e( 'Default', 'wpc-product-quantity' ); ?></option>
                                            <option value="parent" <?php selected( $quantity, 'parent' ); ?>><?php esc_html_e( 'Parent', 'wpc-product-quantity' ); ?></option>
                                            <option value="disable" <?php selected( $quantity, 'disable' ); ?>><?php esc_html_e( 'Disable', 'wpc-product-quantity' ); ?></option>
                                            <option value="overwrite" <?php selected( $quantity, 'overwrite' ); ?> disabled><?php esc_html_e( 'Overwrite', 'wpc-product-quantity' ); ?></option>
                                        </select> </label>
								<?php } else { ?>
                                    <div class="woopq_active_wrapper">
                                        <div class="woopq_active">
                                            <label>
                                                <input name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>" type="radio" class="woopq_active_input" value="default" <?php checked( $quantity, 'default' ); ?>/>
												<?php esc_html_e( 'Default', 'wpc-product-quantity' ); ?>
                                            </label> (<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>" target="_blank"><?php esc_html_e( 'settings', 'wpc-product-quantity' ); ?></a>)
                                        </div>
                                        <div class="woopq_active">
                                            <label>
                                                <input name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>" type="radio" class="woopq_active_input" value="disable" <?php checked( $quantity, 'disable' ); ?>/>
												<?php esc_html_e( 'Disable', 'wpc-product-quantity' ); ?>
                                            </label>
                                        </div>
                                        <div class="woopq_active">
                                            <label>
                                                <input name="<?php echo esc_attr( '_woopq_quantity' . $name ); ?>" type="radio" class="woopq_active_input" value="overwrite" <?php checked( $quantity, 'overwrite' ); ?> disabled/>
												<?php esc_html_e( 'Overwrite', 'wpc-product-quantity' ); ?>
                                            </label>
                                        </div>
                                    </div>
								<?php } ?>
                                <div style="color: #c9356e; padding-left: 0; padding-right: 0; margin-top: 10px">You only can use the
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-woopq&tab=settings' ) ); ?>" target="_blank">default settings</a> for all products and variations.<br/>Quantity settings at a product or variation basis only available on the Premium Version.
                                    <a href="https://wpclever.net/downloads/product-quantity?utm_source=pro&utm_medium=woopq&utm_campaign=wporg" target="_blank">Click here</a> to buy, just $29!
                                </div>
                            </div>
                        </div>
                    </div>
					<?php
				}

				function process_product_meta( $post_id ) {
					if ( isset( $_POST['_woopq_quantity'] ) ) {
						update_post_meta( $post_id, '_woopq_quantity', sanitize_text_field( $_POST['_woopq_quantity'] ) );
					} else {
						delete_post_meta( $post_id, '_woopq_quantity' );
					}
				}

				function add_to_cart_validation( $passed, $product_id, $qty, $variation_id = 0 ) {
					if ( $variation_id ) {
						$product_id = $variation_id;
					}

					if ( ( self::get_quantity( $product_id ) !== 'disable' ) && apply_filters( 'woopq_add_to_cart_validation', true, $product_id, $qty ) ) {
						// only validate when active quantity settings
						$product = wc_get_product( $product_id );
						$added   = self::qty_in_cart( $product_id );

						if ( self::get_type( $product_id ) === 'default' ) {
							// input
							$min  = self::get_min( $product );
							$step = self::get_step( $product );
							$max  = self::get_max( $product );

							if ( ( $min > 0 ) && ( $qty < $min ) && apply_filters( 'woopq_add_to_cart_validation_min', true, $product_id, $qty, $min ) ) {
								wc_add_notice( sprintf( /* translators: min */ esc_html__( 'You can\'t add less than %1$s &times; "%2$s" to the cart.', 'wpc-product-quantity' ), $min, esc_html( get_the_title( $product_id ) ) ), 'error' );

								return false;
							}

							if ( ( $max > 0 ) && ( $qty + $added ) > $max && apply_filters( 'woopq_add_to_cart_validation_max', true, $product_id, $qty, $max, $added ) ) {
								wc_add_notice( sprintf( /* translators: max */ esc_html__( 'You can\'t add more than %1$s &times; "%2$s" to the cart.', 'wpc-product-quantity' ), $max, esc_html( get_the_title( $product_id ) ) ), 'error' );

								return false;
							}

							if ( $step > 0 ) {
								$num = ( $qty - $min ) / $step;

								if ( ( filter_var( $num, FILTER_VALIDATE_INT ) === false ) && apply_filters( 'woopq_add_to_cart_validation_step', true, $product_id, $qty, $step, $min ) ) {
									wc_add_notice( sprintf( /* translators: invalid */ esc_html__( 'You can\'t add %1$s &times; "%2$s" to the cart.', 'wpc-product-quantity' ), $qty, esc_html( get_the_title( $product_id ) ) ), 'error' );

									return false;
								}
							}
						} else {
							// select or radio
							$values = self::get_values( $product );

							if ( ! empty( $values ) ) {
								if ( ( ! in_array( $qty, array_column( $values, 'value' ) ) || ! in_array( $qty + $added, array_column( $values, 'value' ) ) ) && apply_filters( 'woopq_add_to_cart_validation_values', true, $product_id, $qty, $added, $values ) ) {
									wc_add_notice( sprintf( /* translators: invalid */ esc_html__( 'You can\'t add %1$s &times; "%2$s" to the cart.', 'wpc-product-quantity' ), $qty, esc_html( get_the_title( $product_id ) ) ), 'error' );

									return false;
								}
							}
						}
					}

					return $passed;
				}

				function qty_in_cart( $product_id ) {
					$qty = 0;

					foreach ( WC()->cart->get_cart() as $cart_item ) {
						if ( ( $cart_item['product_id'] === $product_id ) || ( $cart_item['variation_id'] === $product_id ) ) {
							$qty += $cart_item['quantity'];
						}
					}

					return apply_filters( 'woopq_qty_in_cart', $qty, $product_id );
				}

				function add_to_cart_message_html( $message, $products, $show_qty ) {
					$titles = [];
					$count  = 0;

					if ( ! is_array( $products ) ) {
						$products = [ $products => 1 ];
						$show_qty = false;
					}

					if ( ! $show_qty ) {
						$products = array_fill_keys( array_keys( $products ), 1 );
					}

					foreach ( $products as $product_id => $qty ) {
						/* translators: %s: product name */
						$titles[] = apply_filters( 'woocommerce_add_to_cart_qty_html', ( $qty > 1 ? (float) $qty . ' &times; ' : '' ), $product_id ) . apply_filters( 'woocommerce_add_to_cart_item_name_in_quotes', sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'wpc-product-quantity' ), wp_strip_all_tags( get_the_title( $product_id ) ) ), $product_id );
						$count    += $qty;
					}

					$titles = array_filter( $titles );
					/* translators: %s: product name */
					$added_text = sprintf( _n( '%s has been added to your cart.', '%s have been added to your cart.', $count, 'wpc-product-quantity' ), wc_format_list_of_items( $titles ) );

					if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
						$return_to = apply_filters( 'woocommerce_continue_shopping_redirect', wc_get_raw_referer() ? wp_validate_redirect( wc_get_raw_referer(), false ) : wc_get_page_permalink( 'shop' ) );
						$message   = sprintf( '<a href="%s" tabindex="1" class="button wc-forward">%s</a> %s', esc_url( $return_to ), esc_html__( 'Continue shopping', 'wpc-product-quantity' ), esc_html( $added_text ) );
					} else {
						$message = sprintf( '<a href="%s" tabindex="1" class="button wc-forward">%s</a> %s', esc_url( wc_get_cart_url() ), esc_html__( 'View cart', 'wpc-product-quantity' ), esc_html( $added_text ) );
					}

					return $message;
				}

				function variation_settings( $loop, $variation_data, $variation ) {
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

				function save_variation_settings( $post_id ) {
					if ( isset( $_POST['_woopq_quantity_v'][ $post_id ] ) ) {
						update_post_meta( $post_id, '_woopq_quantity', sanitize_text_field( $_POST['_woopq_quantity_v'][ $post_id ] ) );
					} else {
						delete_post_meta( $post_id, '_woopq_quantity' );
					}
				}

				function before_variations_form() {
					global $product;
					ob_start();
					woocommerce_quantity_input( [], $product );
					$woopq_qty = htmlentities( ob_get_clean() );

					echo '<span class="woopq-quantity-variable" data-qty="' . $woopq_qty . '" style="display: none"></span>';
				}

				function clean_value( $str ) {
					return preg_replace( '/[^.\-0-9]/', '', $str );
				}

				function generate_key() {
					$key         = '';
					$key_str     = apply_filters( 'woopq_key_characters', 'abcdefghijklmnopqrstuvwxyz0123456789' );
					$key_str_len = strlen( $key_str );

					for ( $i = 0; $i < apply_filters( 'woopq_key_length', 4 ); $i ++ ) {
						$key .= $key_str[ random_int( 0, $key_str_len - 1 ) ];
					}

					if ( is_numeric( $key ) ) {
						$key = self::generate_key();
					}

					return apply_filters( 'woopq_generate_key', $key );
				}

				function sanitize_array( $arr ) {
					foreach ( (array) $arr as $k => $v ) {
						if ( is_array( $v ) ) {
							$arr[ $k ] = self::sanitize_array( $v );
						} else {
							$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
						}
					}

					return $arr;
				}

				function wpcsm_locations( $locations ) {
					$locations['WPC Product Quantity'] = [
						'woopq_before_wrap'           => esc_html__( 'Before wrapper', 'wpc-product-quantity' ),
						'woopq_after_wrap'            => esc_html__( 'After wrapper', 'wpc-product-quantity' ),
						'woopq_before_quantity_input' => esc_html__( 'Before quantity input', 'wpc-product-quantity' ),
						'woopq_after_quantity_input'  => esc_html__( 'After quantity input', 'wpc-product-quantity' ),
						'woopq_before_hidden_field'   => esc_html__( 'Before hidden field', 'wpc-product-quantity' ),
						'woopq_after_hidden_field'    => esc_html__( 'After hidden field', 'wpc-product-quantity' ),
						'woopq_before_select_field'   => esc_html__( 'Before select field', 'wpc-product-quantity' ),
						'woopq_after_select_field'    => esc_html__( 'After select field', 'wpc-product-quantity' ),
						'woopq_before_radio_field'    => esc_html__( 'Before radio field', 'wpc-product-quantity' ),
						'woopq_after_radio_field'     => esc_html__( 'After radio field', 'wpc-product-quantity' ),
						'woopq_before_input_field'    => esc_html__( 'Before input field', 'wpc-product-quantity' ),
						'woopq_after_input_field'     => esc_html__( 'After input field', 'wpc-product-quantity' ),
					];

					return $locations;
				}

				function duplicate_variation( $old_variation_id, $new_variation_id ) {
					if ( $quantity = get_post_meta( $old_variation_id, '_woopq_quantity', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_quantity', $quantity );
					}

					if ( $rules = get_post_meta( $old_variation_id, '_woopq_rules', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_rules', $rules );
					}

					if ( $type = get_post_meta( $old_variation_id, '_woopq_type', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_type', $type );
					}

					if ( $min = get_post_meta( $old_variation_id, '_woopq_min', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_min', $min );
					}

					if ( $step = get_post_meta( $old_variation_id, '_woopq_step', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_step', $step );
					}

					if ( $max = get_post_meta( $old_variation_id, '_woopq_max', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_max', $max );
					}

					if ( $value = get_post_meta( $old_variation_id, '_woopq_value', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_value', $value );
					}

					if ( $values = get_post_meta( $old_variation_id, '_woopq_values', true ) ) {
						update_post_meta( $new_variation_id, '_woopq_values', $values );
					}
				}

				function bulk_update_variation( $variation_id, $fields ) {
					if ( ! empty( $fields['_woopq_quantity_v'] ) && ( $fields['_woopq_quantity_v'] !== 'wpcvb_no_change' ) ) {
						update_post_meta( $variation_id, '_woopq_quantity', sanitize_text_field( $fields['_woopq_quantity_v'] ) );
					}

					if ( ! empty( $fields['_woopq_quantity_v'] ) && ( $fields['_woopq_quantity_v'] === 'overwrite' ) && ! empty( $fields['_woopq_rules_v'] ) ) {
						update_post_meta( $variation_id, '_woopq_rules', self::sanitize_array( $fields['_woopq_rules_v'] ) );
					}

					if ( ! empty( $fields['_woopq_type_v'] ) && ( $fields['_woopq_type_v'] !== 'wpcvb_no_change' ) ) {
						update_post_meta( $variation_id, '_woopq_type', sanitize_text_field( $fields['_woopq_type_v'] ) );
					}

					if ( ! empty( $fields['_woopq_min_v'] ) ) {
						update_post_meta( $variation_id, '_woopq_min', sanitize_text_field( $fields['_woopq_min_v'] ) );
					}

					if ( ! empty( $fields['_woopq_step_v'] ) ) {
						update_post_meta( $variation_id, '_woopq_step', sanitize_text_field( $fields['_woopq_step_v'] ) );
					}

					if ( ! empty( $fields['_woopq_max_v'] ) ) {
						update_post_meta( $variation_id, '_woopq_max', sanitize_text_field( $fields['_woopq_max_v'] ) );
					}

					if ( ! empty( $fields['_woopq_value_v'] ) ) {
						update_post_meta( $variation_id, '_woopq_value', sanitize_text_field( $fields['_woopq_value_v'] ) );
					}

					if ( ! empty( $fields['_woopq_values_v'] ) ) {
						update_post_meta( $variation_id, '_woopq_values', sanitize_textarea_field( $fields['_woopq_values_v'] ) );
					}
				}

				function data_attributes( $attrs ) {
					$attrs_arr = [];

					foreach ( $attrs as $key => $attr ) {
						$attrs_arr[] = 'data-' . sanitize_title( str_replace( 'data-', '', $key ) ) . '="' . esc_attr( $attr ) . '"';
					}

					return implode( ' ', $attrs_arr );
				}
			}

			function WPCleverWoopq() {
				return WPCleverWoopq::instance();
			}

			WPCleverWoopq();
		}

		return null;
	}
}

if ( ! function_exists( 'woopq_notice_wc' ) ) {
	function woopq_notice_wc() {
		?>
        <div class="error">
            <p><strong>WPC Product Quantity</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
		<?php
	}
}
