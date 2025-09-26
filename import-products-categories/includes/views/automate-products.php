<?php
/**
 * Plugin Name: WW Product Batch Import
 * Description: Fetches all products from the external API in background batches with unique locks—no WP-Cron setup required.
 * Version:     1.1
 * Author:      Your Name
 */

if (!defined('ABSPATH')) exit;

/**
 * Import one batch of products.
 *
 * @param string $batch_id Unique ID for this entire import chain.
 * @param string $category_code Category code (empty = all products).
 * @param int $page_index Current page number.
 * @param string $last_product_id For APIs that require a lastProductId.
 */
function ww_import_product_batch($batch_id, $filters = [])
{
    $lock_key = 'ww_product_sync_' . sanitize_key($batch_id);
    if (get_transient($lock_key)) {
        tvc_sync_log("Batch $batch_id already running—skipped page " . ($filters['page_index'] ?? 1) . ".", 'product');
        return;
    }

    set_transient($lock_key, 1, 60); // lock for 2 minutes

    try {
        $per_page = 5;
        $importer = new MPI_Importer();
        $api = new MPI_API();

        // ✅ Existing functions must be loaded elsewhere
        $products = $api->get_products_by_category_code(
            $filters['category_code'] ?? '',
            $filters['last_product_id'] ?? null,
            $per_page,
            $filters['page_index'] ?? 1,
            $filters['beginDate'] ?? null,
            $filters['endDate'] ?? null
        );

        if (empty($products) || !empty($products['error'])) {
            tvc_sync_log(
                "Batch $batch_id page " . ($filters['page_index'] ?? 1) . " error: " . print_r($products, true),
                'product'
            );

            delete_transient($lock_key);
            return;
        } else {
            tvc_sync_log("Batch $batch_id products found to proceed." . count($products['ProductItemNoList']), 'product');
        }

        sleep(1);
        // Update WooCommerce products (your updater)
        $importer->ww_update_detail_of_products($products, $batch_id, $filters);

        if (!empty($products['lastProductId'])) {
            $filters['last_product_id'] = $products['lastProductId'];

            wp_schedule_single_event(
                time() + 10,
                'ww_import_product_batch',
                [$batch_id, $filters]
            );

            tvc_sync_log("Batch $batch_id scheduled next page " . ($filters['page_index'] + 1), 'product');
        } else {
            tvc_sync_log("Batch $batch_id completed.", 'product');
        }
    } catch (Exception $e) {
        tvc_sync_log("Batch $batch_id exception: " . $e->getMessage(), 'product');
    }


    delete_transient($lock_key);
}

add_action('ww_import_product_batch', 'ww_import_product_batch', 10, 4);

/**
 * Kick off a new import.
 *
 * @param string $category_code Leave empty to fetch all products.
 */
function ww_start_product_import($category_code = '', $beginDate = null, $endDate = null)
{
    $batch_id = wp_generate_uuid4(); // globally unique

    // wp_schedule_single_event(
    //  time()+10,
    //  'ww_import_product_batch',
    //  [$batch_id, $category_code, 1, null, $beginDate, $endDate]
    // );

    $params = [
        'category_code' => $category_code,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate
    ];
    
    ww_import_product_batch($batch_id, $params);
    tvc_sync_log("Started new product batch $batch_id (category: $category_code)", 'product');
}

// Start automatically on activation (all products)
//register_activation_hook( __FILE__, function() {
//    ww_start_product_import( '' );
//} );

/**
 * Optional: clear all scheduled product imports.
 * Use with WP-CLI: wp eval 'ww_clear_all_product_batches();' --allow-root
 */
function ww_clear_all_product_batches()
{
    wp_clear_scheduled_hook('ww_import_product_batch');
    tvc_sync_log("Cleared all scheduled product import jobs.", 'product');
}


//
//add_action('init',function (){
//    ww_clear_all_product_batches();
//});