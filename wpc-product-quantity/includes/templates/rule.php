<?php
/**
 * WPC Product Quantity - Rule Template
 *
 * Renders a single rule item.
 * Variables available: $key, $rule, $product_id, $is_variation, $step, $name
 *
 * @package WPC Product Quantity
 */

defined( 'ABSPATH' ) || exit;

$default = $key === 'default';

// Map 'apply' value string formatting for display.
$apply_label = 'all';
if ( $rule['apply'] !== 'woopq_all' && $rule['apply'] !== 'all' ) {
    $apply_label = $rule['apply'] . ': ' . implode( ',', (array) $rule['apply_val'] );
}
?>
<div class="<?php echo esc_attr( $default ? 'woopq-rule woopq-item woopq_settings_form woopq-item-' . $key . ' active' : 'woopq-rule woopq-item woopq_settings_form woopq-item-' . $key ); ?>">
    <div class="woopq-item-header">
        <?php if ( ! $default ) { ?>
            <span class="woopq-item-move ui-sortable-handle hint--top" aria-label="<?php esc_attr_e( 'Drag to reorder', 'wpc-product-quantity' ); ?>">
                <span class="dashicons dashicons-menu"></span>
            </span>
        <?php } ?>
        <span class="woopq-item-name">
            <span class="woopq-item-name-key">#<?php echo esc_html( $key ); ?></span>
            <span class="woopq-item-name-apply"><?php echo esc_html( $apply_label ); ?></span>
        </span>
        <?php if ( ! $default ) { ?>
            <span class="woopq-item-summary woopq_summary_btn hint--top"
                  data-product_id="<?php echo esc_attr( $product_id ); ?>"
                  data-is_variation="<?php echo esc_attr( $is_variation ? '1' : '0' ); ?>"
                  aria-label="<?php esc_attr_e( 'Summary', 'wpc-product-quantity' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </span>
            <span class="woopq-item-duplicate woopq_duplicate_btn hint--top" data-product_id="<?php echo esc_attr( $product_id ); ?>"
                  data-is_variation="<?php echo esc_attr( $is_variation ? '1' : '0' ); ?>"
                  aria-label="<?php esc_attr_e( 'Duplicate', 'wpc-product-quantity' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
            </span>
            <span class="woopq-item-remove woopq_remove_btn hint--top"
                  aria-label="<?php esc_attr_e( 'Remove', 'wpc-product-quantity' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </span>
        <?php } ?>
    </div>
    <div class="woopq-item-content">
        <?php if ( ! $product_id ) { ?>
            <?php if ( ! defined( 'WOOPQ_PREMIUM' ) ) { ?>
                <div class="woopq-item-line">
                    <div class="woopq-item-input">
                        <span style="color: #c9356e;font-size: 13px;font-style: italic;">* Global rules only available on the Premium Version.<a
                                    href="https://wpclever.net/downloads/product-quantity/?utm_source=pro&utm_medium=woopq&utm_campaign=wporg"
                                    target="_blank">Click here</a> to buy, just $29!</span>
                    </div>
                </div>
            <?php } ?>
            <div class="woopq-item-line woopq-item-apply">
                <div class="woopq-item-label">
                    <?php esc_html_e( 'Apply for', 'wpc-product-quantity' ); ?>
                </div>
                <div class="woopq-item-input">
                        <select class="woopq_apply" name="<?php echo esc_attr( $name . '[' . $key . '][apply]' ); ?>">
                            <option value="woopq_all" <?php selected( $rule['apply'], 'woopq_all' ); ?>><?php esc_attr_e( 'All products', 'wpc-product-quantity' ); ?></option>
                            <?php
                            $taxonomies = get_object_taxonomies( 'product', 'objects' );

                            foreach ( $taxonomies as $taxonomy ) {
                                echo '<option value="' . esc_attr( $taxonomy->name ) . '" ' . selected( $rule['apply'], $taxonomy->name, false ) . '>' . esc_html( $taxonomy->label ) . '</option>';
                            }
                            ?>
                        </select>
                    <span class="hide_if_apply_all">
                            <select name="<?php echo esc_attr( $name . '[' . $key . '][apply_inc]' ); ?>">
                                <option value="either" <?php selected( $rule['apply_inc'], 'either' ); ?>><?php esc_attr_e( 'Include either', 'wpc-product-quantity' ); ?></option>
                                <option value="all" <?php selected( $rule['apply_inc'], 'all' ); ?>><?php esc_attr_e( 'Include all', 'wpc-product-quantity' ); ?></option>
                            </select>
                    </span>
                    <div class="hide_if_apply_all">
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
                            </select>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div class="woopq-item-line">
            <div class="woopq-item-label">
                <?php esc_html_e( 'User roles', 'wpc-product-quantity' ); ?>
            </div>
            <div class="woopq-item-input">
                    <select name="<?php echo esc_attr( $name . '[' . $key . '][roles_inc]' ); ?>">
                        <option value="either" <?php selected( $rule['roles_inc'], 'either' ); ?>><?php esc_attr_e( 'Include either', 'wpc-product-quantity' ); ?></option>
                        <option value="all" <?php selected( $rule['roles_inc'], 'all' ); ?>><?php esc_attr_e( 'Include all', 'wpc-product-quantity' ); ?></option>
                    </select>
                    <select name="<?php echo esc_attr( $name . '[' . $key . '][roles][]' ); ?>"
                            multiple class="woopq_roles_select">
                        <?php
                        global $wp_roles;
                        $roles = ( ! empty( $rule['roles'] ) ) ? (array) $rule['roles'] : [ 'woopq_all' ];

                        echo '<option value="woopq_all" ' . ( in_array( 'woopq_all', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'All', 'wpc-product-quantity' ) . '</option>';
                        echo '<option value="woopq_user" ' . ( in_array( 'woopq_user', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-product-quantity' ) . '</option>';
                        echo '<option value="woopq_guest" ' . ( in_array( 'woopq_guest', $roles ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-product-quantity' ) . '</option>';

                        foreach ( $wp_roles->roles as $role_key => $details ) {
                            echo '<option value="' . esc_attr( $role_key ) . '" ' . ( in_array( $role_key, $roles ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
                        }
                        ?>
                    </select>
            </div>
        </div>
        <div class="woopq-item-line">
            <div class="woopq-item-label"><?php esc_html_e( 'Type', 'wpc-product-quantity' ); ?></div>
            <div class="woopq-item-input">
                    <select name="<?php echo esc_attr( $name . '[' . $key . '][type]' ); ?>" class="woopq_type">
                        <option value="default" <?php echo esc_attr( $rule['type'] === 'default' ? 'selected' : '' ); ?>><?php esc_html_e( 'Input (Default)', 'wpc-product-quantity' ); ?></option>
                        <option value="select" <?php echo esc_attr( $rule['type'] === 'select' ? 'selected' : '' ); ?>><?php esc_html_e( 'Select', 'wpc-product-quantity' ); ?></option>
                        <option value="radio" <?php echo esc_attr( $rule['type'] === 'radio' ? 'selected' : '' ); ?>><?php esc_html_e( 'Radio', 'wpc-product-quantity' ); ?></option>
                    </select>
            </div>
        </div>
        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_select woopq_show_if_type_radio">
            <div class="woopq-item-label"><?php esc_html_e( 'Values', 'wpc-product-quantity' ); ?></div>
            <div class="woopq-item-input">
                    <textarea name="<?php echo esc_attr( $name . '[' . $key . '][values]' ); ?>"
                              rows="10" cols="50"
                              style="float: none; width: 100%; height: 200px"><?php echo esc_textarea( $rule['values'] ); ?></textarea>
                <p class="description" style="margin-left: 0"><?php esc_html_e( 'These values will be used for select/radio type. Enter each value in one line and can use the range e.g "10-20".', 'wpc-product-quantity' ); ?></p>
            </div>
        </div>
        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
            <div class="woopq-item-label"><?php esc_html_e( 'Minimum', 'wpc-product-quantity' ); ?></div>
            <div class="woopq-item-input">
                    <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][min]' ); ?>" min="0"
                           step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                           value="<?php echo esc_attr( $rule['min'] ); ?>"/>
            </div>
        </div>
        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
            <div class="woopq-item-label"><?php esc_html_e( 'Step', 'wpc-product-quantity' ); ?></div>
            <div class="woopq-item-input">
                    <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][step]' ); ?>" min="0"
                           step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                           value="<?php echo esc_attr( $rule['step'] ); ?>"/>
            </div>
        </div>
        <div class="woopq-item-line woopq_show_if_type woopq_show_if_type_default">
            <div class="woopq-item-label"><?php esc_html_e( 'Maximum', 'wpc-product-quantity' ); ?></div>
            <div class="woopq-item-input">
                    <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][max]' ); ?>" min="0"
                           step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                           value="<?php echo esc_attr( $rule['max'] ); ?>"/>
            </div>
        </div>
        <div class="woopq-item-line">
            <div class="woopq-item-label"><?php esc_html_e( 'Default value', 'wpc-product-quantity' ); ?></div>
            <div class="woopq-item-input">
                    <input type="number" name="<?php echo esc_attr( $name . '[' . $key . '][value]' ); ?>" min="0"
                           step="<?php echo esc_attr( $step ); ?>" style="width: 120px"
                           value="<?php echo esc_attr( $rule['value'] ); ?>"/>
            </div>
        </div>
    </div>
</div>
