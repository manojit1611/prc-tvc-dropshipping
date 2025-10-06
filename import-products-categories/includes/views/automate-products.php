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
function ww_import_product_batch($import_batch_id, $filters = [])
{

    // Update State for batch
    ww_tvc_log_update_current_args($import_batch_id, $filters);

    // Make Batch Running state
    ww_tvc_log_update_batch_status($import_batch_id, ww_tvc_batch_running_status_flag());

    // Punch php log
    tvc_sync_log("Batch::$import_batch_id Product Pull Started with params " . json_encode($filters), 'batch');

    // Prepare Batch transient
    $lock_key = sanitize_key($import_batch_id);
    if (get_transient($lock_key)) {
        return;
    }

    // lock for 2 min
    set_transient($lock_key, 1, 120);

    $per_page = 10; // adjust as needed
    $importer = new MPI_Importer();
    $api = new MPI_API();

    try {
        // Pull products from TVC API
        $products = $api->get_products_by_category_code(
            $filters['category_code'] ?? '',
            $filters['last_product_id'] ?? null,
            $per_page,
            $filters['page_index'] ?? 1,
            $filters['beginDate'] ?? null,
            $filters['endDate'] ?? null,
        );

        // Check if products are coming or not to process
        if (empty($products['ProductItemNoList'])) {
            tvc_sync_log("No products found to import. Batch $import_batch_id completed.", 'product');
            delete_transient($lock_key);

            // Make Batch Complete state
            ww_tvc_log_update_batch_status($import_batch_id, ww_tvc_batch_complete_status_flag());

            // Release automate product pull in case of sync type auto pull
            if (function_exists('ww_tvc_release_auto_pull_lock')) {
                ww_tvc_release_auto_pull_lock($import_batch_id);
            }


            return;
        }

        // Send Product for a process
        tvc_sync_log("Product Send to Process:: $import_batch_id And products was " . json_encode($products), ww_tvc_product_data_log_type());

        // Process for update into a system
        $importer->ww_update_detail_of_products($products, $import_batch_id, $filters);

        if (!empty($products['lastProductId'])) {
            // Prepare filters for the next batch
            $filters['last_product_id'] = $products['lastProductId'];
            $filters['page_index'] = ($filters['page_index'] ?? 1) + 1;

            // Schedule the next batch via Action Scheduler
            $delay_seconds = 100; // adjust delay as needed
            if (function_exists('as_schedule_single_action')) {
                as_schedule_single_action(time() + $delay_seconds, 'ww_import_product_batch', [$import_batch_id, $filters]);
            }
        }
    } catch (Exception $e) {
        tvc_sync_log("Batch $import_batch_id exception: " . $e->getMessage(), 'product');
    }

    // delete it which are completed successfully
    ww_delete_all_product_batched_which_are_completed();

    // delete batch transit
    delete_transient($lock_key);
}

add_action('ww_import_product_batch', 'ww_import_product_batch', 10, 2);

/**
 * Kick off a new import.
 *
 * @param string $category_code Leave empty to fetch all products.
 */
function ww_start_product_import($category_code = '', $beginDate = null, $endDate = null, $additionalData = array())
{
    $generated_batch_id = $additionalData['import_batch_id'] ?? wp_generate_uuid4() . "__pull_tvc_products";
    $params = [
        'category_code' => $category_code,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate,
        'import_batch_id' => null,
        'batch_name' => $generated_batch_id,
    ];

    // Punch Batch
    $import_batch_id = $generated_batch_id; // keep default
    if (function_exists('ww_tvc_log_insert_batch_log_entry')) {
        $import_batch_id = ww_tvc_log_insert_batch_log_entry($generated_batch_id, array(
            "current_args" => $params,
        ));
        $params['import_batch_id'] = $import_batch_id;
    }

    // Punch log in php
    tvc_sync_log("Batch Scheduled:: $import_batch_id: " . json_encode($params), 'batch');

    // Kick off the first background job
    as_schedule_single_action(
        time(), // run now
        'ww_import_product_batch',
        [$import_batch_id, $params]
    );
}
