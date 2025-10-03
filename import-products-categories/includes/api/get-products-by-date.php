<?php

add_action('rest_api_init', function () {
    register_rest_route('mpi/v1', '/fetch-by-date-time', [
        'methods' => 'GET', // or 'GET' if you prefer
        'callback' => 'mpi_fetch_by_date_time_callback',
        'permission_callback' => '__return_true', // restrict if needed
    ]);
});

function mpi_fetch_by_date_time_callback(WP_REST_Request $request)
{
    global $wpdb;
    $current_time = current_time('timestamp');

    $beginDate = date('Y-m-d\TH:i:s', $current_time - (15 * 60));
    $endDate   = date('Y-m-d\TH:i:s', $current_time);

    $batch_name = "latest_products";
    $import_batch_id = start_import_batch($batch_name, NULL);

    $params = [
        'category_code' => null,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate,
        'import_batch_id' => $import_batch_id
    ];

    $status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}actionscheduler_actions 
        WHERE hook = 'ww_import_product_batch'
        AND status = 'pending' 
        AND args LIKE %s 
        ORDER BY scheduled_date_gmt DESC LIMIT 1",
        '%' . $wpdb->esc_like($batch_name) . '%'
    ));

    if (!$status) {
        // Kick off the first background job
        as_schedule_single_action(
            time(),
            'ww_import_product_batch',
            [$batch_name, $params]
        );
    } else {
        $data['filters'] = $params;
        add_import_error_log($batch_name, $import_batch_id, json_encode($data), '', 'product');
        // start_import_batch($batch_id, $params);
    }

    return rest_ensure_response([
        'status' => 'success',
        'beginDate' => $beginDate,
        'endDate' => $endDate,
    ]);
}
