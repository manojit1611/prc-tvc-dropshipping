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
// ---------- BACKGROUND WORKER ----------
function ww_import_product_batch($batch_id, $filters = [])
{
    tvc_sync_log("SCHEDULED WORKER STARTED: Batch $batch_id And filter was " . print_r($filters, true));

    $lock_key = 'ww_product_sync_' . sanitize_key($batch_id);
    if (get_transient($lock_key)) {
        return; // another batch already running
    }
    set_transient($lock_key, 1, 120); // lock for 2 min

    $per_page = 5; // adjust as needed
    $importer = new MPI_Importer();
    $api = new MPI_API();

    try {
        $products = $api->get_products_by_category_code(
            $filters['category_code'] ?? '',
            $filters['last_product_id'] ?? null,
            $per_page,
            $filters['page_index'] ?? 1
        );

        if (empty($products['ProductItemNoList'])) {
            tvc_sync_log("No more products to import. Batch $batch_id completed.", 'product');
            delete_transient($lock_key);
            return;
        }

        $importer->ww_update_detail_of_products($products, $batch_id, $filters);

        if (!empty($products['lastProductId'])) {
            // Prepare filters for the next batch
            $filters['last_product_id'] = $products['lastProductId'];
            $filters['page_index'] = ($filters['page_index'] ?? 1) + 1;

            // Schedule the next batch via Action Scheduler
            $delay_seconds = 100; // adjust delay as needed
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + $delay_seconds, 'ww_import_product_batch', [$batch_id, $filters]);
            }
        }
    } catch (Exception $e) {
        error_log("Batch $batch_id exception: " . $e->getMessage());
    }

    delete_transient($lock_key);
}

add_action('ww_import_product_batch', 'ww_import_product_batch', 10, 2);

/**
 * Kick off a new import.
 *
 * @param string $category_code Leave empty to fetch all products.
 */
function ww_start_product_import($category_code = '', $beginDate = null, $endDate = null)
{
    $batch_id = wp_generate_uuid4(); // globally unique
    $params = [
        'category_code' => $category_code,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate
    ];

    // Kick off the first background job
    as_enqueue_async_action(
        'ww_import_product_batch',
        [$batch_id, $params]
    );

//    ww_import_product_batch($batch_id, $params);
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