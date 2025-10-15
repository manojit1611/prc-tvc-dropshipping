<?php

/**
 * Plugin Name: Import Products & Categories Old
 * Plugin URI: https://example.com/import-products-categories
 * Description: A simple WordPress plugin that imports products and categories.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

// Prevent direct access to file
if (!defined('ABSPATH')) {
    exit;
}


//if (defined('WP_CLI') && WP_CLI) {
//    WP_CLI::add_command('ww import-all', function ($args) {
//        // $args[0] = category code
//        $category = $args[0] ?? '';
//
//
//        // Delete all scheduled batches
////        ww_clear_all_product_batches();
////        WP_CLI::success( "All ww_import_product_batch jobs deleted.");
////        return;
//
//
//        if (empty($category)) {
//            WP_CLI::error('Please provide a category code. Example: wp ww import-all shoes');
//        }
//
//        // Kick off first batch exactly like the admin page does
//        ww_start_product_import($category);
////        as_enqueue_async_action(
////            'ww_import_product_batch',
////            [ uniqid( 'batch_', true ), [ 'category_code' => $category, 'page_index' => 1 ] ]
////        );
//
//        WP_CLI::success("Import started for category: $category");
//    });
//}


// === API Configuration ===
define('TVC_BASE_URL', 'https://openapi.tvc-mall.com');
define('TVC_EMAIL', 'bharat@labxrepair.com.au');
define('TVC_PASSWORD', 'Eik2Pea9@;??');
define('TVC_MPI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TVC_MPI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TVC_PLUGIN_NAME_PREFIX', 'Tvc');
define('TVC_IMAGE_BASE_URL', 'https://img.tvc-mall.com');
define('TVC_CURRENCY', 'AUD');


/**
 * @param $message
 * @param $type
 * @return void
 * tvc_sync_log
 * Manage Log Punch
 */
function tvc_sync_log($message, $type = 'general')
{
    if (!function_exists('wc_get_logger')) {
        error_log("[TVC Sync] " . $message);
        return;
    }

    $logger = wc_get_logger();
    $context = array('source' => 'tvc-sync-' . $type);

    $logger->info($message, $context);
}


/**
 * @return string
 * ww_tvc_product_api_log_type
 * Log type for tvc product api
 */
function ww_tvc_product_api_log_type()
{
    return "product-api";
}

/**
 * @return string
 * ww_tvc_product_data_log_type
 * Log type data related to apis when data is retried
 */
function ww_tvc_product_data_log_type()
{
    return "product-api-data";
}


/**
 * @return string[]
 * ww_tvs_get_allowed_channel_product_cat_ids
 * Return Allowed channel product ids
 */
function ww_tvs_get_allowed_channel_product_cat_ids()
{
    return array_column(ww_tvs_get_allowed_channel_product_cats(), 'code');
}

function ww_tvs_get_allowed_channel_product_cats()
{
    $cats = array();
    $cats[] = array('code' => "C0067", 'name' => "Cell Phone Accessories");
    $cats[] = array('code' => "C0037", 'name' => "Cell Phone Cases");
    $cats[] = array('code' => "C0060", 'name' => "Cell Phone Parts");
    $cats[] = array('code' => "C0005", 'name' => "Consumer Electronics");
    $cats[] = array('code' => "C0006", 'name' => "Computer & Networking");
    $cats[] = array('code' => "C0010", 'name' => "Sports & Outdoors");
    $cats[] = array('code' => "C0009", 'name' => "Home & Garden");
    $cats[] = array('code' => "C0004", 'name' => "Car Accessories");
    $cats[] = array('code' => "C0078", 'name' => "Smartwatch Accessories");
    $cats[] = array('code' => "C0077", 'name' => "Jewelry");
    $cats[] = array('code' => "C0092", 'name' => "Smart Publishing Level 1");
    return $cats;
}

/**
 * @param $code
 * @return int|mixed|string|WP_Term|null
 * get_product_cat_by_code
 */
function ww_tvc_get_term_data_by_tvc_code($code, $taxonomy = 'product_cat')
{
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'meta_query' => [
            [
                'key' => ww_tvs_get_meta_key_tvc_product_cat_code(),
                'value' => $code,
            ],
        ],
        'number' => 1, // fetch only one
    ]);
    if (!empty($terms) && !is_wp_error($terms)) {
        return $terms[0]; // return single WP_Term
    }
    return null; // nothing found
}

function ww_tvs_get_meta_key_tvc_product_cat_code()
{
    return '_tvc_product_cat_code';
}

/**
 * @return string
 * ww_tvs_get_product_model_taxonomy_type
 */
function ww_tvs_get_product_model_taxonomy_type()
{
    return 'product_model';
}

function ww_tvs_get_product_manufacturer_taxonomy_type()
{
    return 'product_manufacturer';
}

function ww_tvs_get_product_brand_taxonomy_type()
{
    return 'product_brand';
}

/**
 * @param $cat
 * @return int|mixed|null
 * ww_tvc_save_formatted_tvc_cat_term
 * This will save or update the term in WordPress based on tvc data
 */
function ww_tvc_save_formatted_tvc_cat_term($cat)
{
    $return_term = null;
    $taxonomy = $cat['taxonomy'] ?? '';
    if (!$taxonomy) {
        return null;
    }
    $existing = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'meta_query' => [
            [
                'key' => ww_tvs_get_meta_key_tvc_product_cat_code(),
                'value' => $cat['code'],
            ],
        ],
        'number' => 1,
    ]);
    $name = sanitize_text_field($cat['name']);
    $code = sanitize_text_field($cat['code']);
    $slug = sanitize_title($name); // slug comes from a name;
    if (!empty($existing) && !is_wp_error($existing)) {
        // Update the existing category
        $term_id = $existing[0]->term_id;
        wp_update_term($term_id, $taxonomy, [
            'name' => $name,
            'slug' => $slug,
            'parent' => $cat['parent'] ?? 0,
        ]);
    } else {
        //  Create a new category
        $new_term = wp_insert_term(
            $name,
            $taxonomy,
            [
                'slug' => $slug,
                'parent' => $cat['parent'] ?? 0,
            ]
        );
        if (is_wp_error($new_term)) {
            return null;
        }
        $term_id = $new_term['term_id'];
    }
    update_term_meta($term_id, ww_tvs_get_meta_key_tvc_product_cat_code(), $code);
    return $term_id;
}

function ww_tvc_print_r($data = array(), $die = false)
{
    echo '<pre>';
    echo print_r($data);
    echo '</pre>';
    if ($die) {
        die();
    }
}

// Add a new column to the Products admin list
add_filter('manage_edit-product_columns', 'ww_custom_product_list_column');
function ww_custom_product_list_column($columns)
{
    $columns['update_button'] = 'Action';
    $columns['product_log'] = 'Product Log';
    return $columns;
}

// Add content to the custom column
add_action('manage_product_posts_custom_column', 'ww_update_product_list_column_content', 10, 2);
function ww_update_product_list_column_content($column, $post_id)
{
    if ($column === 'update_button') {
        $sku = get_post_meta($post_id, '_sku', true);
        $url = admin_url('admin-ajax.php?redirect=true&action=product_fetch&sku=' . $sku);
        echo '<a href="' . esc_url($url) . '" class="button button-primary">Tvc Update</a>';
    }
}

add_action('admin_notices', function () {
    if (isset($_GET['ww_updated']) && $_GET['ww_updated'] == 1) {
        echo '<div class="notice notice-success is-dismissible">
                <p>' . $_GET['msg'] . '</p>
              </div>';
    }
});

// 2. Add content inside column (button + hidden popup)
add_action('manage_product_posts_custom_column', function ($column, $post_id) {
    if ($column === 'product_log') {
        $raw_value = get_post_meta($post_id, 'tvc_sync_log', true);
        if ($raw_value) {
            $data = json_decode($raw_value, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Unique ID for popup
                $popup_id = 'popup_' . $post_id;

                echo '<button class="button show-log-popup" data-target="#' . esc_attr($popup_id) . '">View Log</button>';

                echo '<div id="' . esc_attr($popup_id) . '" class="log-popup" style="display:none;">';
                echo '<div style="display:flex;align-items: center;justify-content: space-between;"><h3>Sync Log Details</h3><button class="button close-log-popup">Close</button></div>';
                echo '<ul style="margin-left:16px;">';
                if (isset($data['base_details']['succ'])) echo '<li>Basic Details: Inserted</li>';
                if (isset($data['attributes']['succ'])) echo '<li>Attributes: Inserted</li>';
                if (isset($data['also_available']['succ'])) echo '<li>Also Available: Inserted</li>';
                if (isset($data['manufacturer']['succ'])) echo '<li>Manufacturer: Inserted</li>';
                if (isset($data['model']['succ'])) echo '<li>Model: Inserted</li>';
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<span style="color:red;">Invalid JSON</span>';
            }
        } else {
            echo '<span style="color:#aaa;">—</span>';
        }
    }
}, 10, 2);

// 3. Add JavaScript to handle popup toggle
add_action('admin_footer', function () {
?>
    <script>
        jQuery(document).ready(function($) {
            $('.show-log-popup').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $(target).fadeIn();
            });

            $(document).on('click', '.close-log-popup', function(e) {
                e.preventDefault();
                $(this).closest('.log-popup').fadeOut();
            });
        });
    </script>

    <style>
        .log-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px;
            border: 2px solid #0073aa;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            min-width: 300px;
        }
    </style>
<?php
});

// === Includes ===
require __DIR__ . '/ww-tvc-logs.php';
require __DIR__ . '/plugins/ww-category-pricing.php';
require __DIR__ . '/plugins/also-available-products.php';
require __DIR__ . '/includes/views/automate-product-cat.php';
require __DIR__ . '/includes/views/automate-products.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-admin.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-api.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-importer.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/helpers.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/tvc-flush-wooocommerce.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/tvc-order-manipulation.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/api/get-products-by-date.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/api/get-manufacturer-by-post.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/api/get-model-by-post.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/slider-shortcodes/shortcodes.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/stock-qty-manipulation.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/table/create_table.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/device_heirarchy.php';

// === Init Plugin ===
add_action('plugins_loaded', function () {
    new MPI_Admin();
    new MPI_API();
    new MPI_Importer();
});


function ww_tvc_enqueue_select2_assets()
{
    // Select2 CSS
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');

    // Select2 JS
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);

    // Init script
    wp_add_inline_script('select2-js', "
        jQuery(document).ready(function($) {
            $('.tvc_importer .select2').select2();
        });
    ");
}

add_action('admin_enqueue_scripts', 'ww_tvc_enqueue_select2_assets');


// Custom Actions
add_action('init', function () {
    // Release Lock if current automation is running
    $tvc_auto_product_pull_running_clear = $_GET['tvc_auto_product_pull_running_clear'] ?? '';
    if ($tvc_auto_product_pull_running_clear) {
        update_option('tvc_auto_product_pull_running', false, false);
    }
    // Update Last Sync Time of automation
    //    $set_tvc_last_sync_time = $_GET['set_tvc_last_sync_time'] ?? '';
    //    if($set_tvc_last_sync_time){
    //        $endDate = date('Y-m-d\TH:i:s', $current_time);
    //        update_option('tvc_last_sync_time', $endDate, false);
    //    }

});

register_activation_hook(__FILE__, 'tvc_plugin_create_tables');

add_action('woocommerce_after_shop_loop_item_title', 'custom_section_above_add_to_cart', 15);
function custom_section_above_add_to_cart()
{
    aap_display_links_on_product_page();
}

add_action('wp_ajax_ww_get_product_data', 'ww_get_product_data');
add_action('wp_ajax_nopriv_ww_get_product_data', 'ww_get_product_data');
function ww_get_product_data()
{
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(['message' => 'Missing product ID']);
    }

    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'Invalid product']);
    }

    ob_start();
    echo $product->get_image('woocommerce_thumbnail');
    $image_html = ob_get_clean();

    // Get add to cart URL & classes
    $add_to_cart_url = esc_url($product->add_to_cart_url());
    $add_to_cart_classes = 'button add_to_cart_button ajax_add_to_cart';
    if (!$product->is_purchasable() || !$product->is_in_stock()) {
        $add_to_cart_classes .= ' disabled';
    }

    wp_send_json_success([
        'id' => $product_id,
        'title' => $product->get_name(),
        'price' => $product->get_price_html(),
        'image' => $image_html,
        'add_to_cart_url' => $add_to_cart_url,
        'add_to_cart_classes' => $add_to_cart_classes
    ]);
}


add_action('wp_footer', 'ww_product_card_ajax_script');
function ww_product_card_ajax_script()
{
?>
    <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.ww-change-product', function(e) {
                e.preventDefault();

                var productId = $(this).data('product-id');
                var card = $(this).closest('.product'); // WooCommerce product card

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ww_get_product_data',
                        product_id: productId
                    },
                    beforeSend: function() {
                        card.css('opacity', '0.5'); // loading effect
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;

                            // Update product image
                            card.find('img')[0].srcset = $(data.image).attr('src');

                            // console.log(card.find('img')[0].src);

                            // Update product title
                            card.find('.woocommerce-loop-product__title').text(data.title);

                            // Update product price
                            card.find('.price').html(data.price);

                            // Update Add to Cart button
                            var atcButton = card.find('.add_to_cart_button');
                            atcButton.attr('href', data.add_to_cart_url);
                            atcButton.attr('data-product_id', data.id);
                            atcButton.attr('class', data.add_to_cart_classes);
                        } else {
                            alert(response.data.message);
                        }
                    },
                    complete: function() {
                        card.css('opacity', '1');
                    }
                });
            });
        });
    </script>
<?php
}


// add_action('woocommerce_new_order', 'ww_save_markup_amount_to_order_meta', 20, 2);
function ww_save_markup_amount_to_order_meta($order_id, $order)
{
    $rules = get_option('ww_rules', []);
    if (empty($rules)) return;

    $total_markup = 0;
    $order_total_before_markup = 0;

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $price = $product->get_price();
        $order_total_before_markup += $price * $item->get_quantity();

        $product_cats = wc_get_product_term_ids($product->get_id(), 'product_cat');

        foreach ($rules as $r) {
            if (isset($r['cat']) && in_array((int)$r['cat'], $product_cats, true)) {
                $val  = floatval($r['value']);
                $ship = floatval($r['ship']);

                // Calculate markup for this item
                if ($r['type'] === 'percent') {
                    $markup = ($price * ($val / 100)) * $item->get_quantity();
                } else {
                    $markup = $val * $item->get_quantity();
                }

                $markup += $ship * $item->get_quantity();
                $total_markup += $markup;
                break; // Only first matching rule per product
            }
        }
    }

    if ($total_markup > 0) {
        $order->update_meta_data('_ww_markup_amount', $total_markup);
    }

    // Optional: also save original subtotal (for clarity)
    $order->update_meta_data('_ww_original_subtotal', $order_total_before_markup);
    $order->save();
}


add_action('woocommerce_checkout_create_order_line_item', 'ww_save_item_specific_markup', 20, 4);
function ww_save_item_specific_markup($item, $cart_item_key, $values, $order)
{
    $rules = get_option('ww_rules', []);
    if (empty($rules)) return;

    $product = $item->get_product();
    if (!$product) return;

    $price = $product->get_price();
    $product_cats = wc_get_product_term_ids($product->get_id(), 'product_cat');

    $item_markup = 0;

    foreach ($rules as $r) {
        if (isset($r['cat']) && in_array((int)$r['cat'], $product_cats, true)) {
            $val = floatval($r['value']);
            $ship = floatval($r['ship']);

            if ($r['type'] === 'percent') {
                $markup = ($price * ($val / 100)) * $item->get_quantity();
            } else {
                $markup = $val * $item->get_quantity();
            }

            $markup += $ship * $item->get_quantity();
            $item_markup += $markup;
            break;
        }
    }

    if ($item_markup > 0) {
        // ✅ Save markup as item meta (specific to this product in the order)
        $item->add_meta_data('_ww_item_markup', $item_markup, true);
    }
}

