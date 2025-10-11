<?php

// Order Commission Logic
// add_filter('woocommerce_product_get_price', 'add_commission_to_price', 99, 2);
// add_filter('woocommerce_product_get_sale_price', 'add_commission_to_price', 99, 2);
// function add_commission_to_price($price, $product) {
//     $rules = get_option('yaydp_cart_discount_rules');

//     if (empty($rules) || !is_array($rules)) {
//         return $price; // No rules saved
//     }

//     $product_id = $product->get_id();

//     // Get all category IDs for this product
//     $product_category_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

//     // Flag to see if product matches any rule condition
//     $match = false;

//     // Loop through all rules
//     foreach ($rules as $rule) {
//         if (!isset($rule['conditions']['logics'])) continue;

//         foreach ($rule['conditions']['logics'] as $logic) {
//             if ($logic['type'] === 'cart_item_category' && !empty($logic['value'])) {
//                 // Extract category IDs from logic values
//                 $rule_cat_ids = array_column($logic['value'], 'value');

//                 // Check if product category matches any rule category
//                 if (array_intersect($product_category_ids, $rule_cat_ids)) {
//                     $match = true;
//                     $pricing = $rule['pricing'] ?? [];
//                     break 2; // Found a match, no need to check further
//                 }
//             }
//         }
//     }

//     // Only add commission if product matches the rule categories
//     if ($match && !empty($pricing)) {
//         if ($pricing['type'] == 'fixed_discount') {
//             $commission_amount = $pricing['value']; // flat commission
//             $price = (float)$price + $commission_amount;
//         } elseif ($pricing['type'] == 'percentage_discount') {
//             // or you can use $pricing['value'] directly
//             $commission_percentage = 10; 
//             $price = $price + ($price * ($commission_percentage / 100));
//         }
//     }

//     return $price;
// }

// add_action('woocommerce_cart_calculate_fees', 'add_fixed_product_shipping_cost', 20, 1);
// function add_fixed_product_shipping_cost($cart) {
//     if (is_admin() && !defined('DOING_AJAX')) return;

//     $rules = get_option('yaydp_cart_discount_rules');
//     if (empty($rules) || !is_array($rules)) return;

//     $extra_shipping_cost = 0;

//     foreach ($cart->get_cart() as $cart_item) {
//         $product_id = $cart_item['product_id'];
//         $product_category_ids = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

//         foreach ($rules as $rule) {
//             if (!isset($rule['conditions']['logics'])) continue;

//             foreach ($rule['conditions']['logics'] as $logic) {
//                 if ($logic['type'] === 'cart_item_category' && !empty($logic['value'])) {
//                     $rule_cat_ids = array_column($logic['value'], 'value');

//                     if (array_intersect($product_category_ids, $rule_cat_ids)) {
//                         $pricing = $rule['pricing'] ?? [];
//                         if (!empty($pricing) && $pricing['type'] == 'fixed_product') {
//                             // Add shipping cost = value * quantity
//                             $extra_shipping_cost += (float)$pricing['value'] * $cart_item['quantity'];
//                         }
//                     }
//                 }
//             }
//         }
//     }

//     if ($extra_shipping_cost > 0) {
//         $cart->add_fee(__('Shipping Cost', 'textdomain'), $extra_shipping_cost, true);
//     }
// }

// Show Commission on Admin Order Page
add_action('woocommerce_after_order_itemmeta', 'show_commission_on_admin_order_page', 10, 3);
function show_commission_on_admin_order_page($item_id, $item, $product)
{
    // Get commission (markup) amount per unit from order item meta
    $commission_amount = $item->get_meta('_ww_item_markup');

    if ($item->is_type('line_item')) {
        // Get product ID
        $product_id = $item->get_product_id();
    
        // Get saved shipping cost from product meta
        $shipping_cost = get_post_meta($product_id, 'tvc_shipping_cost', true);
        $shipping_cost = floatval($shipping_cost);
    
        if ($commission_amount || $shipping_cost) {
            $qty = $item->get_quantity();
    
            echo '<style>.display_meta { display: none; }</style>';
    
            echo '<div style="margin-top:6px; padding:6px 10px; background:#f9f9f9; border-radius:6px;">';
    
            if ($commission_amount) {
                // Commission
                $total_commission = $commission_amount * $qty;
                echo '<p><strong>Commission:</strong> ' . wc_price($commission_amount) . ' × ' . $qty . ' = <strong>' . wc_price($total_commission) . '</strong></p>';
            }
    
            if ($shipping_cost) {
                // Shipping cost
                $total_shipping = $shipping_cost * $qty;
                echo '<p><strong>Shipping Cost:</strong> ' . wc_price($shipping_cost) . ' × ' . $qty . ' = <strong>' . wc_price($total_shipping) . '</strong></p>';
            }
    
            echo '</div>';
        }
    }
}


// 1️⃣ Add column to Orders List
add_filter('woocommerce_shop_order_list_table_columns', 'my_add_commission_column', 20);
function my_add_commission_column($columns)
{
    $columns['order_commission'] = __('Commission', 'woocommerce');
    return $columns;
}

// 2️⃣ Show content in the Commission column
add_action('woocommerce_shop_order_list_table_custom_column', 'my_show_commission_column', 20, 2);
function my_show_commission_column($column, $order)
{
    if ('order_commission' === $column) {
        if (! is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }

        $total_commission = 0;

        foreach ($order->get_items() as $item) {
            $commission_amount = $item->get_meta('_ww_item_markup'); // per unit
            if ($commission_amount !== '' && $commission_amount !== null) {
                $qty = $item->get_quantity();
                $total_commission += (float) $commission_amount * (int) $qty;
            }
        }

        echo $total_commission > 0 ? wc_price($total_commission) : '-';
    }
}

// Show Total Commission in Admin Order Totals
add_action('woocommerce_admin_order_totals_after_tax', 'show_total_commission_in_admin_order_totals');
function show_total_commission_in_admin_order_totals($order_id)
{
    $order = wc_get_order($order_id);
    $total_commission = 0;

    // Sum up commissions for all line items
    foreach ($order->get_items() as $item) {
        $commission_amount = $item->get_meta('_ww_item_markup');
        if ($commission_amount) {
            $qty = $item->get_quantity();
            $total_commission += $commission_amount * $qty;
        }
    }

    if ($total_commission > 0) {
?>
        <tr>
            <td class="label"><?php _e('Total Commission:', 'woocommerce'); ?></td>
            <td width="1%"></td>
            <td class="total">
                <strong><?php echo wc_price($total_commission); ?></strong>
            </td>
        </tr>
    <?php
    }
}

// Hook into admin_head to inject the button
add_action('admin_head', 'add_download_csv_button');
function add_download_csv_button()
{
    // Only run on the WC orders page
    if (isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Find the search box container
                let searchBox = document.querySelector('.search-box');
                if (searchBox) {
                    let btn = document.createElement('a');
                    btn.href = '<?php echo admin_url('admin-ajax.php?action=download_orders_csv'); ?>';
                    btn.className = 'button button-primary';
                    btn.style.marginLeft = '10px';
                    btn.textContent = 'Download CSV';
                    searchBox.appendChild(btn);
                }
            });
        </script>
<?php
    }
}

add_action('wp_ajax_download_orders_csv', 'handle_download_orders_csv');
function handle_download_orders_csv()
{
    // Security check
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Access Denied');
    }

    // CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders.csv"');

    $output = fopen('php://output', 'w');

    // Custom CSV column headers
    fputcsv($output, [
        'Your Order No. (Option)',
        'Shipping Name*',
        'Shipping Street*',
        'Shipping City*',
        'Shipping Zip*',
        'Shipping Province*',
        'Shipping Country*',
        'Shipping Phone*',
        'Fax(Option)',
        'MULT Item No.*'
    ]);

    // Get all orders
    $orders = wc_get_orders([
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    foreach ($orders as $order) {
        /** @var WC_Order $order */
        $order_id = $order->get_id();

        // Shipping info
        $shipping_name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        $shipping_street = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2();
        $shipping_city = $order->get_shipping_city();
        $shipping_zip = $order->get_shipping_postcode();
        $shipping_province = $order->get_shipping_state();
        $shipping_country = $order->get_shipping_country();
        $shipping_phone = $order->get_billing_phone(); // WooCommerce uses billing phone by default
        $fax = $order->get_meta('fax'); // If you store fax in meta

        // MULT Item No. = SKU*Qty list
        $sku_qty_array = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $sku = $product->get_sku();
            $qty = $item->get_quantity();
            $sku_qty_array[] = $sku . '*' . $qty;
        }
        $mult_item_no = implode(', ', $sku_qty_array);

        // Write row
        fputcsv($output, [
            $order_id,
            $shipping_name,
            $shipping_street,
            $shipping_city,
            $shipping_zip,
            $shipping_province,
            $shipping_country,
            $shipping_phone,
            $fax,
            $mult_item_no
        ]);
    }

    fclose($output);
    exit;
}