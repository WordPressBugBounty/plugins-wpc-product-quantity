<?php
/*
Plugin Name: WPC Product Quantity for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Product Quantity provides powerful controls for product quantity.
Version: 6.0.1
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-product-quantity
Domain Path: /languages/
Requires Plugins: woocommerce
Requires at least: 4.0
Tested up to: 7.0
WC requires at least: 3.0
WC tested up to: 10.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOPQ_VERSION' ) && define( 'WOOPQ_VERSION', '6.0.1' );
! defined( 'WOOPQ_LITE' ) && define( 'WOOPQ_LITE', __FILE__ );
! defined( 'WOOPQ_FILE' ) && define( 'WOOPQ_FILE', __FILE__ );
! defined( 'WOOPQ_URI' ) && define( 'WOOPQ_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOPQ_DIR' ) && define( 'WOOPQ_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WOOPQ_SUPPORT' ) && define( 'WOOPQ_SUPPORT', 'https://wpclever.net/support?utm_source=support&utm_medium=woopq&utm_campaign=wporg' );
! defined( 'WOOPQ_REVIEWS' ) && define( 'WOOPQ_REVIEWS', 'https://wordpress.org/support/plugin/wpc-product-quantity/reviews/' );
! defined( 'WOOPQ_CHANGELOG' ) && define( 'WOOPQ_CHANGELOG', 'https://wordpress.org/plugins/wpc-product-quantity/#developers' );
! defined( 'WOOPQ_DISCUSSION' ) && define( 'WOOPQ_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-product-quantity' );

// WPC Core
require_once __DIR__ . '/includes/wpc-core/wpc-core.php';
wpc_core_register( [
	'file'    => __FILE__,
	'version' => WOOPQ_VERSION,
	'prefix'  => 'woopq',
] );

if ( ! function_exists( 'woopq_init' ) ) {
	add_action( 'plugins_loaded', 'woopq_init', 11 );

	function woopq_init() {
		if ( ! class_exists( 'WPCleverWoopq' ) && class_exists( 'WC_Product' ) ) {
			class WPCleverWoopq {
				protected static $settings = [];
				protected static $instance = null;

				/**
				 * Runtime cache for decimal setting check.
				 *
				 * @var bool|null
				 */
				private static $is_decimal = null;

				/**
				 * Per-request runtime caches to avoid redundant DB lookups.
				 */
				private static $product_cache = [];
				private static $quantity_cache = [];
				private static $type_cache = [];
				private static $parsed_rules = null;

				public static function instance() {
					if ( is_null( self::$instance ) ) {
						self::$instance = new self();
					}

					return self::$instance;
				}

				function __construct() {
					self::$settings = (array) get_option( 'woopq_settings', [] );

					add_action( 'init', [ $this, 'init' ] );

					// enqueue frontend
					add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );

					// args
					add_filter( 'woocommerce_quantity_input_args', [ $this, 'quantity_input_args' ], 99, 2 );
					add_filter( 'woocommerce_loop_add_to_cart_args', [ $this, 'loop_add_to_cart_args' ], 99, 2 );

					// admin input
					add_filter( 'woocommerce_quantity_input_min_admin', [ $this, 'quantity_input_min_admin' ], 99, 2 );
					add_filter( 'woocommerce_quantity_input_step_admin', [
						$this,
						'quantity_input_step_admin'
					], 99, 2 );

					// decimal
					if ( self::is_decimal() ) {
						remove_filter( 'woocommerce_stock_amount', 'intval' );
						add_filter( 'woocommerce_stock_amount', 'floatval' );

						// add to cart message
						add_filter( 'wc_add_to_cart_message_html', [ $this, 'add_to_cart_message_html' ], 999, 3 );

						// rest api
						add_filter( 'woocommerce_rest_shop_order_schema', [ $this, 'rest_shop_order_schema' ], 999 );
					}

					// fix stock status
					add_filter( 'woocommerce_product_get_stock_status', [ $this, 'get_stock_status' ], 99, 2 );

					// template
					add_filter( 'wc_get_template', [ $this, 'quantity_input_template' ], 99, 2 );

					// add to cart
					add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'add_to_cart_validation' ], 99, 4 );

					// variation data
					add_filter( 'woocommerce_available_variation', [ $this, 'available_variation' ], 99, 3 );
					add_action( 'woocommerce_before_variations_form', [ $this, 'before_variations_form' ] );

					// WPC Smart Messages
					add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );

					// Load backend class
					if ( is_admin() ) {
						include_once WOOPQ_DIR . 'includes/class-backend.php';
						WPCleverWoopq_Backend::instance();
					}
				}

				function init() {
					// load text-domain
					load_plugin_textdomain( 'wpc-product-quantity', false, basename( WOOPQ_DIR ) . '/languages/' );
				}

				/**
				 * Check if decimal quantities are enabled.
				 *
				 * @return bool
				 */
				public static function is_decimal() {
					if ( self::$is_decimal === null ) {
						self::$is_decimal = self::get_setting( 'decimal', 'no' ) === 'yes';
					}

					return self::$is_decimal;
				}

				public static function get_settings() {
					return apply_filters( 'woopq_get_settings', self::$settings );
				}

				public static function get_setting( $name, $default = false ) {
					$setting = self::$settings[ $name ] ?? get_option( 'woopq_' . $name, $default );

					return apply_filters( 'woopq_get_setting', $setting, $name, $default );
				}

				public static function enqueue_scripts() {
					wp_enqueue_style( 'woopq-frontend', WOOPQ_URI . 'assets/css/frontend.css', [], WOOPQ_VERSION );
					wp_enqueue_script( 'woopq-frontend', WOOPQ_URI . 'assets/js/frontend.js', [ 'jquery' ], WOOPQ_VERSION, true );
					wp_localize_script( 'woopq-frontend', 'woopq_vars', [
							'rounding'     => self::get_setting( 'rounding', 'down' ),
							'auto_correct' => self::get_setting( 'auto_correct', 'entering' ),
							'timeout'      => apply_filters( 'woopq_auto_correct_timeout', 1000 ),
						]
					);
				}

				public static function loop_add_to_cart_args( $args, $product ) {
					if ( empty( $product ) ) {
						return $args;
					}

					$woopq_value = self::get_value( $product );
					$woopq_min   = self::get_min( $product );

					$args['quantity'] = ( ! empty( $woopq_min ) && $woopq_value < $woopq_min )
						? $woopq_min
						: $woopq_value;

					return $args;
				}

				public static function quantity_input_args( $args, $product ) {
					if ( empty( $product ) ) {
						return $args;
					}

					// Extract values once to avoid multiple array access
					$input_name = $args['input_name'] ?? '';
					$min_value  = $args['min_value'] ?? null;
					$max_value  = $args['max_value'] ?? null;
					$step       = $args['step'] ?? null;

					// Batch assign values
					$args = array_merge( $args, [
						'product_id' => $product->get_id(),
						'min_value'  => self::get_min( $product, $min_value ),
						'max_value'  => self::get_max( $product, $max_value ),
						'step'       => self::get_step( $product, $step )
					] );

					// Use early return pattern for conditional logic
					if ( empty( $input_name ) || ! str_starts_with( $input_name, 'quantity' ) ) {
						return $args;
					}

					$args['input_value'] = self::get_value( $product, $args['input_value'] ?? null );

					return $args;
				}

				public static function quantity_input_min( $min, $product ) {
					if ( $product ) {
						return self::get_min( $product, $min );
					}

					return $min;
				}

				public static function quantity_input_max( $max, $product ) {
					if ( $product ) {
						return self::get_max( $product, $max );
					}

					return $max;
				}

				public static function quantity_input_step( $step, $product ) {
					if ( $product ) {
						return self::get_step( $product, $step );
					}

					return $step;
				}

				public static function quantity_input_min_admin( $min, $product ) {
					$backend = self::get_setting( 'backend', 'yes' ) === 'yes';

					if ( ! $backend || apply_filters( 'woopq_ignore_admin_input', false, 'min' ) ) {
						return '0';
					} elseif ( $product ) {
						return self::get_min( $product, $min );
					}

					return $min;
				}

				public static function quantity_input_step_admin( $step, $product ) {
					$backend = self::get_setting( 'backend', 'yes' ) === 'yes';

					if ( ! $backend || apply_filters( 'woopq_ignore_admin_input', false, 'step' ) ) {
						return 'any';
					} elseif ( $product ) {
						return self::get_step( $product, $step );
					}

					return $step;
				}

				/**
				 * Resolve a product parameter to a product ID and WC_Product object.
				 *
				 * @param mixed $product Numeric ID or WC_Product instance.
				 *
				 * @return array [ product_id, product_object ]
				 */
				private static function resolve_product( $product ) {
					if ( is_numeric( $product ) ) {
						$id = (int) $product;

						if ( ! isset( self::$product_cache[ $id ] ) ) {
							self::$product_cache[ $id ] = wc_get_product( $id );
						}

						return [ $id, self::$product_cache[ $id ] ];
					}

					if ( is_a( $product, 'WC_Product' ) ) {
						$id                         = $product->get_id();
						self::$product_cache[ $id ] = $product;

						return [ $id, $product ];
					}

					return [ 0, null ];
				}

				public static function get_quantity( $product ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( isset( self::$quantity_cache[ $product_id ] ) ) {
						return self::$quantity_cache[ $product_id ];
					}

					if ( is_a( $product, 'WC_Product_Variation' ) ) {
						$quantity = get_post_meta( $product_id, '_woopq_quantity', true ) ?: 'parent';

						if ( $quantity === 'parent' ) {
							$quantity = get_post_meta( $product->get_parent_id(), '_woopq_quantity', true ) ?: 'default';
						}
					} else {
						$quantity = get_post_meta( $product_id, '_woopq_quantity', true ) ?: 'default';
					}

					$result = apply_filters( 'woopq_quantity', $quantity, $product_id );

					self::$quantity_cache[ $product_id ] = $result;

					return $result;
				}

				public static function get_type( $product ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( isset( self::$type_cache[ $product_id ] ) ) {
						return self::$type_cache[ $product_id ];
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
							if ( is_a( $product, 'WC_Product_Variation' ) && ( $parent_id = $product->get_parent_id() ) ) {
								$result                          = self::get_type( $parent_id );
								self::$type_cache[ $product_id ] = $result;

								return $result;
							}

							// Fallback to global if not a variation
							$woopq_type = self::get_global_setting( 'type', $product );

							break;
						default:
							$woopq_type = self::get_product_setting( 'type', $product );

							break;
					}

					$result = apply_filters( 'woopq_type', $woopq_type, $product_id );

					self::$type_cache[ $product_id ] = $result;

					return $result;
				}

				/**
				 * Resolve a setting value based on the quantity mode.
				 *
				 * @param int $product_id Product ID.
				 * @param WC_Product $product Product object.
				 * @param string $name Setting name (min, max, step, value, values).
				 * @param mixed $default Default fallback value.
				 * @param callable|null $values_reducer Callback to derive value from values array (for min/max).
				 *
				 * @return mixed
				 */
				private static function resolve_setting( $product_id, $product, $name, $default, $values_reducer = null ) {
					$result   = $default;
					$quantity = self::get_quantity( $product );

					switch ( $quantity ) {
						case 'disable':
							break;
						case 'global':
						case 'default':
							if ( $values_reducer && self::get_type( $product_id ) !== 'default' ) {
								$woopq_values = self::get_values( $product );
								if ( ! empty( $woopq_values ) ) {
									$result = $values_reducer( array_column( $woopq_values, 'value' ) );
								}
							} else {
								$result = self::get_global_setting( $name, $product );
							}
							break;
						case 'parent':
							$result = self::get_global_setting( $name, $product );
							break;
						default:
							if ( $values_reducer && self::get_type( $product_id ) !== 'default' ) {
								$woopq_values = self::get_values( $product );
								if ( ! empty( $woopq_values ) ) {
									$result = $values_reducer( array_column( $woopq_values, 'value' ) );
								}
							} else {
								$result = self::get_product_setting( $name, $product );
							}
							break;
					}

					return $result;
				}

				/**
				 * Check if the product should recurse to parent for settings.
				 *
				 * @param WC_Product $product Product object.
				 *
				 * @return int|false Parent ID or false.
				 */
				private static function should_recurse_parent( $product ) {
					$quantity = self::get_quantity( $product );

					if ( $quantity === 'parent' && is_a( $product, 'WC_Product_Variation' ) ) {
						return $product->get_parent_id() ?: false;
					}

					return false;
				}

				public static function get_min( $product, $min = 0 ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( $parent_id = self::should_recurse_parent( $product ) ) {
						return self::get_min( $parent_id );
					}

					$woopq_min = self::resolve_setting( $product_id, $product, 'min', $min, 'min' );

					if ( ! is_numeric( $woopq_min ) ) {
						// leave blank to disable
						$woopq_min = $min;
					}

					$woopq_min = (float) $woopq_min;

					if ( ! self::is_decimal() ) {
						$woopq_min = ceil( $woopq_min );
					}

					return apply_filters( 'woopq_min', $woopq_min, $product_id, $product );
				}

				public static function get_max( $product, $max = 100000, $max_value = null ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( ! $max_value ) {
						$max_value = $product->get_max_purchase_quantity();
					}

					if ( $parent_id = self::should_recurse_parent( $product ) ) {
						return self::get_max( $parent_id, $max, $max_value );
					}

					$woopq_max = self::resolve_setting( $product_id, $product, 'max', $max, 'max' );

					if ( ! is_numeric( $woopq_max ) ) {
						// leave blank to disable
						$woopq_max = $max;
					}

					$woopq_max = (float) $woopq_max;

					if ( ( $max_value > 0 ) && ( $woopq_max > $max_value ) ) {
						$woopq_max = $max_value;
					}

					if ( ! self::is_decimal() ) {
						$woopq_max = ceil( $woopq_max );
					}

					return apply_filters( 'woopq_max', $woopq_max, $product_id, $product );
				}

				public static function get_step( $product, $step = 1 ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( $parent_id = self::should_recurse_parent( $product ) ) {
						return self::get_step( $parent_id );
					}

					$woopq_step = self::resolve_setting( $product_id, $product, 'step', $step );

					if ( ! is_numeric( $woopq_step ) ) {
						// leave blank to disable
						$woopq_step = $step;
					}

					$woopq_step = (float) $woopq_step;

					if ( ! self::is_decimal() ) {
						$woopq_step = ceil( $woopq_step );
					}

					return apply_filters( 'woopq_step', $woopq_step, $product_id, $product );
				}

				public static function get_value( $product, $value = 1 ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( $parent_id = self::should_recurse_parent( $product ) ) {
						return self::get_value( $parent_id );
					}

					$woopq_value = self::resolve_setting( $product_id, $product, 'value', $value );

					if ( ! is_numeric( $woopq_value ) ) {
						// leave blank to disable
						$woopq_value = $value;
					}

					$woopq_value = (float) $woopq_value;

					if ( ! self::is_decimal() ) {
						$woopq_value = ceil( $woopq_value );
					}

					return apply_filters( 'woopq_value', $woopq_value, $product_id, $product );
				}

				public static function get_values( $product, $values = '' ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					if ( $parent_id = self::should_recurse_parent( $product ) ) {
						return self::get_values( $parent_id );
					}

					$values = self::resolve_setting( $product_id, $product, 'values', $values );

					$woopq_values = [];
					$is_decimal   = self::is_decimal();
					$values_arr   = explode( "\n", $values );

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
								$woopq_values[] = [
									'name'  => esc_html( trim( $item ) ),
									'value' => $is_decimal ? (float) $item_value : (int) $item_value,
								];
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

				/**
				 * Default rule structure used for merging.
				 *
				 * @return array
				 */
				private static function get_default_rule() {
					return [
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
					];
				}

				public static function get_global_setting( $name = 'type', $product = null ) {
					// default setting
					$setting = self::get_setting( $name );

					// global rules skipped

					return apply_filters( 'woopq_get_global_setting', $setting, $name, $product );
				}

				public static function get_product_setting( $name = 'type', $product = null ) {
					[ $product_id, $product ] = self::resolve_product( $product );

					$setting = get_post_meta( $product_id, '_woopq_' . $name, true );
					$setting = $setting !== '' ? $setting : 'default';

					$rules = (array) ( get_post_meta( $product_id, '_woopq_rules', true ) ?: [] );
					unset( $rules['placeholder'] );

					if ( ! empty( $rules ) ) {
						$default_rule = self::get_default_rule();

						// check apply rule
						foreach ( $rules as $rule ) {
							$rule = array_merge( $default_rule, $rule );

							if ( self::check_roles( $rule ) && isset( $rule[ $name ] ) ) {
								$setting = $rule[ $name ];
								break;
							}
						}
					}

					return apply_filters( 'woopq_get_product_setting', $setting, $name, $product );
				}

				public static function check_apply( $product, $rule ) {
					$apply     = $rule['apply'] ?? 'woopq_all';
					$apply_val = $rule['apply_val'] ?? [];
					$apply_inc = $rule['apply_inc'] ?? 'either';

					[ $product_id, $product ] = self::resolve_product( $product );

					if ( ! $product_id ) {
						return false;
					}

					if ( empty( $apply ) || ( $apply === 'woopq_all' ) || empty( $apply_val ) ) {
						return true;
					}

					if ( $apply_inc === 'all' ) {
						if ( $product->is_type( 'variation' ) ) {
							// all attributes
							$all_attrs = [];
							$attrs     = $product->get_attributes();
							$parent_id = $product->get_parent_id();

							if ( $taxonomies = get_object_taxonomies( 'product', 'objects' ) ) {
								foreach ( $taxonomies as $taxonomy ) {
									if ( str_starts_with( $taxonomy->name, 'pa_' ) ) {
										$all_attrs[] = $taxonomy->name;
									}
								}
							}

							if ( in_array( $apply, $all_attrs ) ) {
								if ( empty( $attrs[ $apply ] ) || ! in_array( $attrs[ $apply ], $apply_val ) ) {
									return false;
								}
							} else {
								foreach ( $apply_val as $term ) {
									if ( ! has_term( $term, $apply, $product_id ) && ! has_term( $term, $apply, $parent_id ) ) {
										return false;
									}
								}
							}
						} else {
							foreach ( $apply_val as $term ) {
								if ( ! has_term( $term, $apply, $product_id ) ) {
									return false;
								}
							}
						}

						return true;
					} else {
						// either
						if ( $product->is_type( 'variation' ) ) {
							// all attributes
							$all_attrs = [];
							$attrs     = $product->get_attributes();
							$parent_id = $product->get_parent_id();

							if ( $taxonomies = get_object_taxonomies( 'product', 'objects' ) ) {
								foreach ( $taxonomies as $taxonomy ) {
									if ( str_starts_with( $taxonomy->name, 'pa_' ) ) {
										$all_attrs[] = $taxonomy->name;
									}
								}
							}

							if ( in_array( $apply, $all_attrs ) ) {
								if ( ! empty( $attrs[ $apply ] ) && in_array( $attrs[ $apply ], $apply_val ) ) {
									return true;
								}
							} else {
								if ( has_term( $apply_val, $apply, $product_id ) || has_term( $apply_val, $apply, $parent_id ) ) {
									return true;
								}
							}
						} else {
							if ( has_term( $apply_val, $apply, $product_id ) ) {
								return true;
							}
						}
					}

					return false;
				}

				public static function check_roles( $rule ) {
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

				public static function quantity_input_template( $located, $template_name ) {
					if ( $template_name === 'global/quantity-input.php' ) {
						return WOOPQ_DIR . 'templates/quantity-input.php';
					}

					return $located;
				}

				public static function get_stock_status( $stock_status, $product ) {
					if ( ! $product->get_manage_stock() ) {
						return $stock_status;
					}

					$stock_quantity                        = self::is_decimal() ? (float) $product->get_stock_quantity() : (int) $product->get_stock_quantity();
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

				public static function add_to_cart_validation( $passed, $product_id, $qty, $variation_id = 0 ) {
					$product_id = $variation_id ?: $product_id;

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

				public static function qty_in_cart( $product_id ) {
					$qty = 0;

					foreach ( WC()->cart->get_cart() as $cart_item ) {
						if ( ( $cart_item['product_id'] === $product_id ) || ( $cart_item['variation_id'] === $product_id ) ) {
							$qty += $cart_item['quantity'];
						}
					}

					return apply_filters( 'woopq_qty_in_cart', $qty, $product_id );
				}

				public static function add_to_cart_message_html( $message, $products, $show_qty ) {
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

				public static function rest_shop_order_schema( $properties ) {
					$properties['line_items']['items']['properties']['quantity']['type'] = 'number';

					return $properties;
				}

				public static function before_variations_form() {
					global $product;
					ob_start();
					woocommerce_quantity_input( [], $product );
					$woopq_qty = htmlentities( ob_get_clean() );

					echo '<span class="woopq-quantity-variable" data-qty="' . $woopq_qty . '" style="display: none"></span>';
				}

				public static function available_variation( $available, $variable, $variation ) {
					// Resolve once, reuse for both WC default and custom keys
					$available['min_qty'] = $available['woopq_min'] = self::get_min( $variation );
					$available['max_qty'] = $available['woopq_max'] = self::get_max( $variation );

					$available['woopq_step']  = self::get_step( $variation );
					$available['woopq_value'] = self::get_value( $variation );

					// qty
					ob_start();
					woocommerce_quantity_input( [], $variation );
					$available['woopq_qty'] = htmlentities( ob_get_clean() );

					return $available;
				}

				public static function clean_value( $str ) {
					return preg_replace( '/[^.\-0-9]/', '', $str );
				}

				public static function generate_key() {
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

				public static function sanitize_array( $arr ) {
					foreach ( (array) $arr as $k => $v ) {
						if ( is_array( $v ) ) {
							$arr[ $k ] = self::sanitize_array( $v );
						} else {
							$arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
						}
					}

					return $arr;
				}

				public static function wpcsm_locations( $locations ) {
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

				public static function data_attributes( $attrs ) {
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
