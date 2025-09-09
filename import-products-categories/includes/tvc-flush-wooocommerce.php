<?php

add_action('admin_menu', 'my_wc_reset_menu');
function my_wc_reset_menu() {
    add_submenu_page(
        'woocommerce',
        'Reset WooCommerce',
        'Reset WooCommerce',
        'manage_options',
        'reset-woocommerce',
        'my_wc_reset_page'
    );
}

function my_wc_reset_page() {
    if (isset($_POST['wc_reset_confirm'])) {
        my_wc_reset_all_data();
        echo '<div class="updated"><p>✅ WooCommerce data has been reset.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Reset WooCommerce</h1>
        <p><strong>Warning:</strong> This will delete ALL WooCommerce data (products, orders, customers, settings) and cannot be undone.</p>
        <form method="post">
            <?php submit_button('Reset WooCommerce', 'delete', 'wc_reset_confirm'); ?>
        </form>
    </div>
    <?php
}

function my_wc_reset_all_data() {
    global $wpdb;

    // Delete WooCommerce products & variations
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation')");

    // Delete WooCommerce orders & refunds
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type IN ('shop_order','shop_order_refund')");

    // Delete orphaned post meta
    $wpdb->query("
        DELETE pm FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE p.ID IS NULL
    ");

    // Delete WooCommerce terms (categories, tags, attributes, brands, product_model)
    $wpdb->query("
        DELETE t FROM {$wpdb->terms} t
        LEFT JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy IN ('product_cat','product_tag','product_brand','product_model','pa_color','pa_size')
    ");

    // Delete WooCommerce term taxonomy
    $wpdb->query("
        DELETE FROM {$wpdb->term_taxonomy}
        WHERE taxonomy IN ('product_cat','product_tag','product_brand','product_model','pa_color','pa_size')
    ");

    // Delete orphaned term relationships
    $wpdb->query("
        DELETE tr FROM {$wpdb->term_relationships} tr
        LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
        WHERE p.ID IS NULL
    ");

    // 🔥 Delete orphaned term meta (important!)
    $wpdb->query("
        DELETE tm FROM {$wpdb->termmeta} tm
        LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
        WHERE t.term_id IS NULL
    ");

    // Truncate WooCommerce custom tables
    $tables = [
        "{$wpdb->prefix}wc_orders",
        "{$wpdb->prefix}wc_order_stats",
        "{$wpdb->prefix}wc_order_product_lookup",
        "{$wpdb->prefix}wc_customer_lookup",
        "{$wpdb->prefix}wc_download_log",
        "{$wpdb->prefix}wc_tax_rate_classes",
        "{$wpdb->prefix}tvc_manufacturer_hierarchy",
        "{$wpdb->prefix}tvc_products_data",
        "{$wpdb->prefix}tvc_product_bulk_pricing"
    ];
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
    }

    // Delete WooCommerce options (settings)
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_%'");
}




