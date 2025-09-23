<?php

add_action('rest_api_init', function () {
    register_rest_route('mpi/v1', '/fetch-by-date-time', [
        'methods' => 'GET', // or 'GET' if you prefer
        'callback' => 'mpi_fetch_by_date_time_callback',
        'permission_callback' => '__return_true', // restrict if needed
    ]);
});

function mpi_fetch_by_date_time_callback(WP_REST_Request $request) {
    // Optional: accept params (perPage, batch_id, etc.)
    $perPage = $request->get_param('per_page') ?: 10;
    $maxPages = $request->get_param('max_pages') ?: 1;

    // Your existing importer + API
    $importer = new MPI_Importer();
    $api = new MPI_API();

    $lastProductId = null;
    $pageIndex = 1;

    // Current date/time in WP timezone
    $current_time = current_time('timestamp');

    // Date window (last 15 minutes)
    $beginDate = date('Y-m-d\TH:i:s', $current_time - (15 * 60));
    $endDate   = date('Y-m-d\TH:i:s', $current_time);

    $allProducts = [];

    do {
        $products = $api->get_products_by_category_code(
            null,
            $lastProductId,
            $perPage,
            $pageIndex,
            $beginDate,
            $endDate
        );

        $importer->ww_update_detail_of_products($products);

        $allProducts[] = $products; // store for response

        $lastProductId = $products['lastProductId'] ?? null;
        $pageIndex++;
    } while ($pageIndex <= $maxPages);

    return rest_ensure_response([
        'status' => 'success',
        'beginDate' => $beginDate,
        'endDate' => $endDate,
        'data' => $allProducts,
    ]);
}