<?php

/**
 * Plugin Name: WW Product Batch Import
 * Description: Fetches all products from the external API in background batches with unique locks—no WP-Cron setup required.
 * Version:     1.1
 * Author:      Your Name
 */

if (!defined('ABSPATH')) exit;

function ww_delete_all_product_batched_which_are_completed()
{

// Run this once (via functions.php, custom plugin, or wp eval-file)

    global $wpdb;

// 1. Get IDs of all completed/failed actions for the hook
    $action_ids = $wpdb->get_col($wpdb->prepare("
    SELECT action_id 
    FROM {$wpdb->prefix}actionscheduler_actions
    WHERE hook = %s
    AND status IN ('complete')
", 'ww_import_product_batch'));

    if (!empty($action_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($action_ids), '%d'));

        // 2. Delete related logs
        $wpdb->query($wpdb->prepare("
        DELETE FROM {$wpdb->prefix}actionscheduler_logs
        WHERE action_id IN ($ids_placeholder)
    ", ...$action_ids));

        // 3. Delete related claims
        $wpdb->query($wpdb->prepare("
        DELETE FROM {$wpdb->prefix}actionscheduler_claims
        WHERE action_id IN ($ids_placeholder)
    ", ...$action_ids));

        // 4. Delete the actions themselves
        $wpdb->query($wpdb->prepare("
        DELETE FROM {$wpdb->prefix}actionscheduler_actions
        WHERE action_id IN ($ids_placeholder)
    ", ...$action_ids));
    }

}

//add_action("admin_init", "ww_delete_all_product_batched_which_are_completed", 10, 1);


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
    tvc_sync_log("Product Pull Started:: $batch_id And filter was " . json_encode($filters), 'product');

    // Prepare Batch transient
    $lock_key = sanitize_key($batch_id);
    if (get_transient($lock_key)) {
        // another batch already running
        return;
    }

    // lock for 2 min
    set_transient($lock_key, 1, 120);

    $per_page = 20; // adjust as needed
    $importer = new MPI_Importer();
    $api = new MPI_API();

    try {

        // Pull products from TVC API
        $products = $api->get_products_by_category_code(
            $filters['category_code'] ?? '',
            $filters['last_product_id'] ?? null,
            $per_page,
            $filters['page_index'] ?? 1
        );

        // Check if products are coming or not to process
        if (empty($products['ProductItemNoList'])) {
            tvc_sync_log("No products found to import. Batch $batch_id completed.", 'product');
            delete_transient($lock_key);
            return;
        }

        // Send Product for a process
        tvc_sync_log("Product Send to Process:: $batch_id And products was " . json_encode($products), ww_tvc_product_data_log_type());

        // Process for update into a system
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

            // delete it which are completed successfully
            ww_delete_all_product_batched_which_are_completed();
        }
    } catch (Exception $e) {
        tvc_sync_log("Batch $batch_id exception: " . $e->getMessage(), 'product');
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
    $term = ww_tvc_get_term_data_by_tvc_code($category_code);
    $name = $term->name ?? wp_generate_uuid4() . "_product_automation";
    $batch_id = sanitize_title($name);
    $params = [
        'category_code' => $category_code,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate
    ];

    tvc_sync_log("Batch Scheduled:: $batch_id: " . json_encode($params), 'product');

    // Kick off the first background job
    as_schedule_single_action(
        time(), // run now
        'ww_import_product_batch',
        [$batch_id, $params]
    );
}

// Start automatically on activation (all products)
//register_activation_hook( __FILE__, function() {
//    ww_start_product_import( '' );
//} );

/**
 * Optional: clear all scheduled product imports.
 * Use with WP-CLI: wp eval 'ww_clear_all_product_batches();' --allow-root
 */
// function ww_clear_all_product_batches()
// {
//     wp_clear_scheduled_hook('ww_import_product_batch');
//     tvc_sync_log("Cleared all scheduled product import jobs.", 'product');
// }


//
//add_action('init',function (){
//    ww_clear_all_product_batches();
//});