<?php

add_action('rest_api_init', function () {
    register_rest_route('mpi/v1', '/auto-pull-last-updated-products', [
        'methods' => 'GET', // or 'GET' if you prefer
        'callback' => 'ww_tvc_last_updated_products_pull_callback',
        'permission_callback' => '__return_true', // restrict if needed
    ]);
});


function ww_tvc_last_updated_products_pull_callback(WP_REST_Request $request)
{
    $current_time = current_time('timestamp');
    $generated_batch_id = "AUTO_PRODUCT_PULL_BATCH_" . time();

    /**
     * 1️⃣ Get the last successful sync time from wp_options
     */
    $last_sync_time = get_option('tvc_last_sync_time');

    if ($last_sync_time) {
        $beginDate = date('Y-m-d\TH:i:s', strtotime($last_sync_time));
    } else {
        // Defaulted to 15 minutes ago if no sync record yet
        $beginDate = date('Y-m-d\TH:i:s', $current_time - (15 * 60));
    }

    /**
     * 2️⃣ Current end time
     */
    $endDate = date('Y-m-d\TH:i:s', $current_time);

    /**
     * 3️⃣ Prepare parameters
     */
    $params = [
        'category_code' => null,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate,
        'import_batch_id' => null,
        'batch_name' => $generated_batch_id,
    ];

    /**
     * 4️⃣ Generate batch and log entry (if the function exists)
     */
    $import_batch_id = $generated_batch_id; // default fallback
    if (function_exists('ww_tvc_log_insert_batch_log_entry')) {
        $import_batch_id = ww_tvc_log_insert_batch_log_entry($generated_batch_id, [
            'current_args' => $params,
            "sync_type" => ww_tvc_auto_pull_sync_type_flag()
        ]);
        $params['import_batch_id'] = $import_batch_id;
    }

    /**
     * 5️⃣ Check running flag (option)
     */
    $is_running = get_option('tvc_auto_product_pull_running', false);

    if (!$is_running) {
        // Mark as running before scheduling
        update_option('tvc_auto_product_pull_running', true, false);

        // 🔹 Schedule background job
        as_schedule_single_action(
            time(),
            'ww_import_product_batch',
            [$import_batch_id, $params]
        );

        // Update the last sync start time
        update_option('tvc_last_sync_time', $endDate, false);

        $message = 'New import batch started successfully.';
    } else {
        $message = 'An auto product pull import batch is already running.';
    }

    /**
     * 6️⃣ Return sync info
     */
    return rest_ensure_response([
        'status' => 'success',
        'beginDate' => $beginDate,
        'endDate' => $endDate,
        'running' => (bool)$is_running,
        'last_sync' => $last_sync_time ?: 'No previous sync found',
        'batch_id' => $import_batch_id,
        'message' => $message,
    ]);
}

//function mpi_fetch_by_date_time_callback(WP_REST_Request $request)
//{
//    global $wpdb;
//    $current_time = current_time('timestamp');
//
//    $beginDate = date('Y-m-d\TH:i:s', $current_time - (15 * 60));
//    $endDate = date('Y-m-d\TH:i:s', $current_time);
//
//    $batch_name = "latest_products";
//    $import_batch_id = start_import_batch($batch_name, NULL);
//
//    $params = [
//        'category_code' => null,
//        'page_index' => 1,
//        'last_product_id' => null,
//        'beginDate' => $beginDate,
//        'endDate' => $endDate,
//        'import_batch_id' => $import_batch_id
//    ];
//
//    $status = $wpdb->get_var($wpdb->prepare(
//        "SELECT status FROM {$wpdb->prefix}actionscheduler_actions
//        WHERE hook = 'ww_import_product_batch'
//        AND status = 'pending'
//        AND args LIKE %s
//        ORDER BY scheduled_date_gmt DESC LIMIT 1",
//        '%' . $wpdb->esc_like($batch_name) . '%'
//    ));
//
//    if (!$status) {
//        // Kick off the first background job
//        as_schedule_single_action(
//            time(),
//            'ww_import_product_batch',
//            [$batch_name, $params]
//        );
//    } else {
//        $data['filters'] = $params;
//    }
//
//    return rest_ensure_response([
//        'status' => 'success',
//        'beginDate' => $beginDate,
//        'endDate' => $endDate,
//    ]);
//}



