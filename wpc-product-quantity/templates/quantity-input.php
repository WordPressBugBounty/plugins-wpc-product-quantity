<?php
/**
 * Product quantity inputs
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 * @var $product_id
 * @var $step
 * @var $input_id
 * @var $input_name
 * @var $classes
 * @var $placeholder
 * @var $inputmode
 * @var $readonly
 * @var $type
 */

defined( 'ABSPATH' ) || exit;

$qty_sources = [
	'woosb_qty'     => 'WPC Product Bundles',
	'woobt_qty'     => 'WPC Frequently Bought Together',
	'woosg_qty'     => 'WPC Grouped Product',
	'overwrite_qty' => 'Overwrite'
];

foreach ( $qty_sources as $source => $comment ) {
	if ( isset( $$source ) ) {
		$min_value   = ${$source}['min_value'];
		$max_value   = ${$source}['max_value'];
		$input_value = ${$source}['input_value'];
		break;
	}
}

$plus_minus    = WPCleverWoopq()::get_setting( 'plus_minus', 'hide' ) === 'show';
$default_value = $input_value;

do_action( 'woopq_before_wrap' );

if ( $max_value && $min_value == $max_value ) {
	$woopq_hidden_class = apply_filters( 'woopq_quantity_hidden_class', 'quantity hidden woopq-quantity woopq-quantity-hidden woopq-input-type-' . $type . ( $plus_minus ? ' woopq-quantity-plus-minus buttons_added' : '' ), $product_id );
	$woopq_hidden_attrs = apply_filters( 'woopq_quantity_hidden_attrs', [
		'min'     => $min_value,
		'max'     => $max_value,
		'step'    => $step,
		'value'   => $input_value,
		'default' => $default_value,
	], $product_id );

	echo '<div class="' . esc_attr( $woopq_hidden_class ) . '" ' . WPCleverWoopq()->data_attributes( $woopq_hidden_attrs ) . '>';
	do_action( 'woopq_before_hidden_field' );
	echo '<input type="number" id="' . esc_attr( $input_id ) . '" class="qty" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $min_value ) . '" readonly/>';
	do_action( 'woopq_after_hidden_field' );
	echo '</div><!-- /woopq-quantity-hidden -->';
} else {
	$type           = 'number';
	$input_value    = max( $min_value ?: $input_value, min( $max_value ?: $input_value, $input_value ) );
	$label          = ! empty( $args['product_name'] ) ? sprintf( /* translators: product name */ esc_html__( '%s quantity', 'wpc-product-quantity' ), wp_strip_all_tags( $args['product_name'] ) ) : esc_html__( 'Quantity', 'wpc-product-quantity' );
	$woopq_quantity = WPCleverWoopq()->get_quantity( $product_id );
	$woopq_type     = WPCleverWoopq()->get_type( $product_id );

	if ( isset( $woosb_qty ) || isset( $woobt_qty ) || isset( $overwrite_qty ) ) {
		// overwrite by WPC Product Bundles/ WPC Frequently Bought Together
		$woopq_quantity = 'overwrite';
		$woopq_type     = 'default';
	}

	// filter
	$woopq_quantity = apply_filters( 'woopq_product_quantity', $woopq_quantity, $product_id );
	$woopq_type     = apply_filters( 'woopq_product_type', $woopq_type, $product_id );
	$woopq_class    = apply_filters( 'woopq_quantity_class', 'quantity woopq-quantity woopq-quantity-' . $woopq_quantity . ' woopq-input-type-' . $type . ' woopq-type-' . $woopq_type . ( $plus_minus ? ' woopq-quantity-plus-minus buttons_added' : '' ) );
	$woopq_attrs    = apply_filters( 'woopq_quantity_attrs', [
		'min'     => $min_value,
		'max'     => $max_value,
		'step'    => $step,
		'value'   => $input_value,
		'default' => $default_value,
	], $product_id );

	echo '<div class="' . esc_attr( $woopq_class ) . '" ' . WPCleverWoopq()->data_attributes( $woopq_attrs ) . '>';

	do_action( 'woopq_before_quantity_input' );
	do_action( 'woocommerce_before_quantity_input_field' );
	?>
    <label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
		<?php echo esc_attr( $label ); ?>
    </label>
	<?php if ( $woopq_type === 'select' ) {
		do_action( 'woopq_before_select_field' );
		?>
        <select id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" class="qty"
                title="<?php echo esc_attr_x( 'Qty', 'Product quantity input tooltip', 'wpc-product-quantity' ); ?>">
			<?php
			$woopq_values = WPCleverWoopq()->get_values( $product_id );
			$s            = 1;

			foreach ( $woopq_values as $woopq_value ) {
				echo '<option value="' . esc_attr( $woopq_value['value'] ) . '" ' . ( $input_value == $woopq_value['value'] ? 'selected' : '' ) . ' ' . ( ( $s > 1 ) && ( $max_value > 0 && (float) $woopq_value['value'] > $max_value ) ? 'disabled' : '' ) . '>' . $woopq_value['name'] . '</option>';
				$s ++;
			}
			?>
        </select>
		<?php
		do_action( 'woopq_after_select_field' );
	} elseif ( $woopq_type === 'radio' ) {
		$woopq_values = WPCleverWoopq()->get_values( $product_id );
		$s            = 1;

		do_action( 'woopq_before_radio_field' );

		foreach ( $woopq_values as $woopq_value ) {
			echo '<label><input type="radio" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $woopq_value['value'] ) . '" ' . ( $input_value == $woopq_value['value'] ? 'checked' : '' ) . ' ' . ( ( $s > 1 ) && ( $max_value > 0 && (float) $woopq_value['value'] > $max_value ) ? 'disabled' : '' ) . '/> ' . $woopq_value['name'] . '</label>';
			$s ++;
		}

		do_action( 'woopq_after_radio_field' );
	} else {
		// default
		do_action( 'woopq_before_input_field' );

		if ( $plus_minus ) {
			echo '<div class="woopq-quantity-input">';
			echo '<div class="woopq-quantity-input-minus">-</div>';
		}
		?>
        <input type="<?php echo esc_attr( $type ); ?>" <?php echo $readonly ? 'readonly="readonly"' : ''; ?> size="4"
               id="<?php echo esc_attr( $input_id ); ?>"
               class="<?php echo esc_attr( join( ' ', (array) $classes ) ); ?>" step="<?php echo esc_attr( $step ); ?>"
               min="<?php echo esc_attr( $min_value ); ?>"
               max="<?php echo esc_attr( 0 < $max_value ? $max_value : '' ); ?>"
               name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $input_value ); ?>"
               title="<?php echo esc_attr_x( 'Qty', 'Product quantity input tooltip', 'wpc-product-quantity' ); ?>"
               placeholder="<?php echo esc_attr( $placeholder ); ?>" inputmode="<?php echo esc_attr( $inputmode ); ?>"/>
		<?php
		if ( $plus_minus ) {
			echo '<div class="woopq-quantity-input-plus">+</div>';
			echo '</div><!-- /woopq-quantity-input -->';
		}

		do_action( 'woopq_after_input_field' );
	} ?><?php
	do_action( 'woocommerce_after_quantity_input_field' );
	do_action( 'woopq_after_quantity_input' );

	echo '</div><!-- /woopq-quantity -->';
}

do_action( 'woopq_after_wrap' );
