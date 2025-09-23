<?php

add_filter('woocommerce_product_stock_status_options', function($status_options) {
    $status_options['on_sale'] = __('On Sale', 'woocommerce');
    $status_options['in_shortage'] = __('In Shortage', 'woocommerce');
    $status_options['5_7_days'] = __('5-7 Days', 'woocommerce');
    $status_options['7_10_days'] = __('7-10 Days', 'woocommerce');
    return $status_options;
});

// Display custom stock status on product page
add_filter('woocommerce_get_stock_html', function($html, $product) {
    $status = $product->get_stock_status();
    if ($status == 'on_sale') return '<p class="stock in-shortage">On Sale</p>';
    if ($status == 'in_shortage') return '<p class="stock in-shortage">In Shortage</p>';
    if ($status == '5_7_days') return '<p class="stock on-order">5-7 Days</p>';
    if ($status == '7_10_days') return '<p class="stock on-order">7-10 Days</p>';
    return $html;
}, 10, 2);


// Add custom field for Minimum Order Qty in product backend
add_action('woocommerce_product_options_inventory_product_data', 'add_min_order_qty_field');
function add_min_order_qty_field() {
    woocommerce_wp_text_input( array(
        'id' => '_min_order_qty',
        'label' => __('Minimum Order Quantity', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array('min' => '1', 'step' => '1'),
        'desc_tip' => true,
        'description' => __('Enter the minimum quantity allowed for this product.', 'woocommerce')
    ));
}

add_action('woocommerce_process_product_meta', 'save_min_order_qty_field');
function save_min_order_qty_field($post_id) {
    $min_qty = isset($_POST['_min_order_qty']) ? absint($_POST['_min_order_qty']) : '';
    update_post_meta($post_id, '_min_order_qty', $min_qty);
}

// Validate & enforce minimum quantity before adding to cart
add_filter('woocommerce_add_to_cart_validation', 'enforce_minimum_order_quantity', 10, 3);

function enforce_minimum_order_quantity($passed, $product_id, $quantity) {
    $min_qty = get_post_meta($product_id, '_min_order_qty', true);

    if (!empty($min_qty) && $quantity < $min_qty) {
        // Show error message
        wc_add_notice(sprintf(__('The minimum quantity for %s is %d.', 'woocommerce'), get_the_title($product_id), $min_qty), 'error');

        // Block adding to cart
        return false;
    }

    return $passed;
}