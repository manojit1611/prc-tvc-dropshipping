<?php
/*
 * Also Available Products for WooCommerce
 */

if (!defined('ABSPATH')) exit;

global $wpdb;
define('AAP_TABLE', $wpdb->prefix . 'woo_also_available_links');

// Create custom table on plugin activation
register_activation_hook(__FILE__, 'aap_create_custom_table');
function aap_create_custom_table()
{
    global $wpdb;

    $table = AAP_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        product_id BIGINT UNSIGNED NOT NULL,
        related_product_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (product_id, related_product_id),
        KEY related_product_id (related_product_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Add field to product admin
add_action('woocommerce_product_options_related', 'aap_admin_product_field');
function aap_admin_product_field()
{
    global $post, $wpdb;

    $product_id = $post->ID;
    $table = AAP_TABLE;
    $related_ids = $wpdb->get_col($wpdb->prepare("SELECT related_product_id FROM $table WHERE product_id = %d", $product_id));

    echo '<div class="options_group">';
?>
    <p class="form-field">
        <label for="also_available_products"><?php _e('Also Available (Products)', 'woocommerce'); ?></label>
        <select class="wc-product-search"
            multiple="multiple"
            style="width: 100%;"
            id="also_available_products"
            name="also_available_products[]"
            data-placeholder="<?php esc_attr_e('Search for a product…', 'woocommerce'); ?>"
            data-action="woocommerce_json_search_products_and_variations">
            <?php
            foreach ($related_ids as $related_id) {
                $product = wc_get_product($related_id);
                if ($product) {
                    echo '<option value="' . esc_attr($related_id) . '" selected>' . esc_html($product->get_formatted_name()) . '</option>';
                }
            }
            ?>
        </select>
    </p>
<?php
    echo '</div>';
}

// Save bidirectional relations in the table
add_action('woocommerce_process_product_meta', 'aap_save_product_links');
function aap_save_product_links($post_id, $related_id = array())
{
    global $wpdb;

    $table = AAP_TABLE;
    $selected = isset($_POST['also_available_products']) ? array_map('absint', (array)$_POST['also_available_products']) : $related_id;

    // Delete existing links for this product
    $wpdb->delete($table, ['product_id' => $post_id]);
    $wpdb->delete($table, ['related_product_id' => $post_id]);

    foreach ($selected as $related_id) {
        // Insert both directions
        $wpdb->replace($table, ['product_id' => $post_id, 'related_product_id' => $related_id]);
        $wpdb->replace($table, ['product_id' => $related_id, 'related_product_id' => $post_id]);
    }
}


add_action('template_redirect', function () {
    if (!is_product()) {
        return;
    }

    global $product;

    if (!$product || !is_a($product, 'WC_Product')) {
        $product = wc_get_product(get_the_ID());
    }

    // Double-check that it's a valid product object
    if ($product instanceof WC_Product) {
        if ($product->is_type('variable')) {
            add_action('woocommerce_before_variations_form', 'aap_display_links_on_product_page', 15);
        } else {
            add_action('woocommerce_before_add_to_cart_form', 'aap_display_links_on_product_page', 15);
        }
    }
});


// Display links on product page

function aap_display_links_on_product_page()
{
    global $product, $wpdb;

    $id = $product->get_id();
    $table = AAP_TABLE;

    $related_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT related_product_id FROM $table WHERE product_id = %d", $id));
    $related_ids = array_diff(array_unique($related_ids), [$id]); // Avoid self-reference

    $related_ids = $product->get_meta('_related_models') ?? "";
    if (!empty($related_ids)) {
        $related_ids = explode(',', $related_ids);
    }


    if (empty($related_ids)) return;


    $finalAvailableProducts = array();
    foreach ($related_ids as $sku) {
        $rid = wc_get_product_id_by_sku($sku);
        if (empty($rid)) {
            continue;
        }
        $wooProduct = wc_get_product($rid);
        if ($wooProduct && $wooProduct->get_status() == 'publish') {
            $finalAvailableProducts[] = $wooProduct;
        }
    }

    if (empty($finalAvailableProducts)) {
        return;
    }

?>
    <style>
        .ww-also-available-products h3 {
            color: #222;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 0;
        }

        .ww-also-available-products ul {
            list-style: none;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            margin: 0;
            padding: 0;
            gap: 8px;
            margin-bottom: var(--woo-sp-content-space, 1rem);
            margin-top: calc(var(--woo-sp-content-space, 1rem) / 2);
        }

        .ww-also-available-products ul li a {
            display: flex;
            height: 100%;
            width: 100%;
            align-items: center;
        }

        .ww-also-available-products ul li {
            flex: 0 0 auto;
            height: auto;
            border: 1px solid var(--wpc);
            border-radius: var(--wsbr, 0);
            overflow: hidden;
            padding: 2px;
        }

        .ww-also-available-products img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: inherit;
        }

        .ww-also-available-products ul li:hover,
        .ww-also-available-products ul li.active {
            border-color: var(--wsc);
        }
    </style>
    <div class="ww-also-available-products">
        <h3><?php echo __('Same Model in Different Colors:'); ?></h3>
        <ul>
            <?php
            $is_single = is_product();

            foreach ($finalAvailableProducts as $p) {
                $rid = $p->get_id();
                if ($p && $p->get_status() == 'publish') {
                    if ($is_single) {
                        // Single product page → normal link
                        echo '<li><a href="' . get_permalink($rid) . '">' . $p->get_image('woocommerce_thumbnail') . '</a></li>';
                    } else {
                        // Shop/archive/product card → AJAX update
                        echo '<li><a href="#" class="ww-change-product" data-product-id="' . esc_attr($rid) . '">'
                            . $p->get_image('woocommerce_thumbnail') . '</a></li>';
                    }
                }
            }
            ?>
        </ul>
    </div>
<?php
}

// Cleanup when product is deleted
add_action('before_delete_post', 'aap_cleanup_deleted_product_links');
function aap_cleanup_deleted_product_links($post_id)
{
    if (get_post_type($post_id) !== 'product') return;

    global $wpdb;
    $table = AAP_TABLE;
    $wpdb->delete($table, ['product_id' => $post_id]);
    $wpdb->delete($table, ['related_product_id' => $post_id]);
}
