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

/**
 * Write messages to a dedicated log file: wp-content/tvc-sync.log
 * Writes to wp-content/tvc-sync.log by default,
 * or wp-content/tvc-sync-<type>.log if $type is provided.
 */
// function tvc_sync_log($message, $type = "")
// {
//     $file = WP_CONTENT_DIR . '/tvc-sync.log';
//     if ($type) {
//         $file = WP_CONTENT_DIR . '/tvc-sync-' . $type . '.log';
//     }
//     $time = date('Y-m-d H:i:s');
//     error_log("[{$time}] {$message}\n", 3, $file);
// }

function tvc_sync_log($message, $type = "")
{
    $date = date('Y-m-d');
    $file = WP_CONTENT_DIR . '/logs/tvc-sync-' . $date . '.log';
    if ($type) {
        $file = WP_CONTENT_DIR . '/logs/tvc-sync-' . $type . '-' . $date . '.log';
    }
    $time = date('Y-m-d H:i:s');
    error_log("[{$time}] {$message}\n", 3, $file);
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

// Custom error logger
if (!function_exists('my_log_error')) {
    function my_log_error($message)
    {
        $upload_dir = wp_upload_dir();

        $date = date('Y-m-d');
        $log_file = trailingslashit($upload_dir['basedir']) . "custom-errors-{$date}.log";

        // Format message with timestamp
        $timestamp = date("Y-m-d H:i:s");
        $formatted_message = "[{$timestamp}] " . print_r($message, true) . "\n";

        // Write to file (append mode)
        error_log($formatted_message, 3, $log_file);
    }
}

// Add admin menu page
add_action('admin_menu', function () {
    add_menu_page(
        'Error Logs',         // Page title
        'Error Logs',         // Menu title
        'manage_options',     // Capability
        'custom-error-logs',  // Menu slug
        'render_error_logs',  // Callback function
        'dashicons-warning',  // Icon
        100                   // Position
    );
});

// Render the error log contents
function render_error_logs()
{
    // Path to wp-content/logs folder
    $log_path = trailingslashit(WP_CONTENT_DIR) . 'logs/';

    // Get all log files starting with tvc-sync-product-
    $files = glob($log_path . 'tvc-sync-product-*.log');

    echo '<div class="wrap"><h1>Error Logs</h1>';

    if (!empty($files)) {
        echo '<h2>Select a log file:</h2>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="custom-error-logs">';
        echo '<select name="log_date">';
        foreach ($files as $file) {
            $filename = basename($file);
            // Extract just the date part
            $date = str_replace(array('tvc-sync-product-', '.log'), '', $filename);
            $selected = (isset($_GET['log_date']) && $_GET['log_date'] === $date) ? 'selected' : '';
            echo '<option value="' . esc_attr($date) . '" ' . $selected . '>' . esc_html($date) . '</option>';
        }
        echo '</select> ';
        submit_button('View Log', 'primary', '', false);
        echo '</form>';

        // Display the selected log
        if (isset($_GET['log_date']) && !empty($_GET['log_date'])) {
            $selected_date = sanitize_text_field($_GET['log_date']);
            $selected_file = $log_path . "tvc-sync-product-{$selected_date}.log";

            if (file_exists($selected_file)) {
                $logs = file_get_contents($selected_file);
                echo '<h2>Logs for ' . esc_html($selected_date) . '</h2>';
                echo '<pre style="background:#111;color:#0f0;padding:15px;max-height:600px;overflow:auto;border-radius:8px;">';
                echo esc_html($logs);
                echo '</pre>';

                // Clear log button
                echo '<form method="post" style="margin-top:20px;" onsubmit="return confirm(\'Are you sure you want to clear this log?\');">';
                echo '<input type="hidden" name="log_date" value="' . esc_attr($selected_date) . '">';
                submit_button('Clear Logs', 'delete', 'clear_logs');
                echo '</form>';

                // Handle clearing logs
                if (isset($_POST['clear_logs']) && current_user_can('manage_options')) {
                    $clear_date = sanitize_text_field($_POST['log_date']);
                    $clear_file = $log_path . "tvc-sync-product-{$clear_date}.log";
                    file_put_contents($clear_file, "");
                    echo '<div class="updated notice"><p>Logs cleared successfully.</p></div>';
                    echo '<script>window.location.reload();</script>';
                }
            } else {
                echo '<p>No logs found for this date.</p>';
            }
        }

    } else {
        echo '<p>No log files found.</p>';
    }

    echo '</div>';
}

// Add a new column to the Products admin list
add_filter('manage_edit-product_columns', 'ww_custom_product_list_column');
function ww_custom_product_list_column($columns)
{
    $columns['update_button'] = 'Action'; // Column header
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


// === Includes ===
require_once TVC_MPI_PLUGIN_PATH . 'includes/table/create_table.php';
//add_action('init', function () {
//    tvc_plugin_create_tables();
//});
require __DIR__ . '/includes/views/automate-product-cat.php';
require __DIR__ . '/includes/views/automate-products.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-admin.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-api.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-importer.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/helpers.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/tvc-flush-wooocommerce.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/tvc-product-manipulation.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/api/get-products-by-date.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/api/get-manufacturer-by-post.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/api/get-model-by-post.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/slider-shortcodes/shortcodes.php';

require_once TVC_MPI_PLUGIN_PATH . 'includes/stock-qty-manipulation.php';

// === Init Plugin ===
add_action('plugins_loaded', function () {
    new MPI_Admin();
    new MPI_API();
    new MPI_Importer();
});


// Logging import batches and errors
function start_import_batch($batch_id)
{
    global $wpdb;
    $batches_table = $wpdb->prefix . 'tvc_import_batches';

    // Sanitize batch_id first
    $batch_id = sanitize_text_field($batch_id);

    // Check if batch_id already exists
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $batches_table WHERE batch_id = %s",
            $batch_id
        )
    );

    if ($exists > 0) {
        // Batch already exists — ignore or maybe return something
        return false; // or return 'exists'
    }

    // Insert new batch_id
    $wpdb->insert(
        $batches_table,
        [
            'batch_id' => $batch_id,
            'created_at' => current_time('mysql') // optional: timestamp
        ],
        [
            '%s',
            '%s'
        ]
    );

    return true; // inserted
}

function add_import_error_log($batch_id, $state, $sku, $type)
{
    global $wpdb;
    $logs_table = $wpdb->prefix . 'tvc_import_logs';
    start_import_batch($batch_id);

    $wpdb->insert(
        $logs_table,
        [
            'batch_id' => $batch_id,
            'status' => maybe_serialize($state),
            'success_skus' => maybe_serialize($sku),
            'failed_sku' => maybe_serialize($state['failed_records'] ?? []),
            'type' => $type,
        ],
        [
            '%s',
            '%s',
            '%s',
            '%s'
        ]
    );
}

function enqueue_select2_assets()
{
    // Select2 CSS
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');

    // Select2 JS
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), null, true);

    // Init script
    wp_add_inline_script('select2-js', "
        jQuery(document).ready(function($) {
            $('select.select2').select2();
        });
    ");
}

add_action('admin_enqueue_scripts', 'enqueue_select2_assets');


