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
    $current_time = current_time('timestamp');

    // Date window (last 15 minutes)
    $beginDate = date('Y-m-d\TH:i:s', $current_time - (15 * 60));
    $endDate   = date('Y-m-d\TH:i:s', $current_time);

    $batch_id = wp_generate_uuid4() . "_latest_products";
    $params = [
        'category_code' => null,
        'page_index' => 1,
        'last_product_id' => null,
        'beginDate' => $beginDate,
        'endDate' => $endDate
    ];

    // Kick off the first background job
    as_schedule_single_action(
        time(),
        'ww_import_product_batch',
        [$batch_id, $params]
    );

    return rest_ensure_response([
        'status' => 'success',
        'beginDate' => $beginDate,
        'endDate' => $endDate,
    ]);
}
