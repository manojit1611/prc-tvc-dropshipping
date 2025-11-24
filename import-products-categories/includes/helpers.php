<?php

function mpi_download_image_to_media($url, $title = '')
{
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Sideload image
    $tmp = download_url($url);
    if (is_wp_error($tmp)) return false;

    $file_array = [
        'name' => basename($url),
        'tmp_name' => $tmp
    ];

    $id = media_handle_sideload($file_array, 0, $title);

    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return false;
    }

    return $id;
}

/**
 * @return string
 * ww_tvc_get_bulk_pricing_db_table_name
 */
function ww_tvc_get_bulk_pricing_db_table_name()
{
    return 'tvc_product_bulk_pricing';
}


/**
 * @return string
 * ww_tvc_get_manufacturer_product_relation_table_name
 * This table will container
 * brand*product relation
 * model*product relation
 */
function ww_tvc_get_manufacturer_product_relation_table_name()
{
    return 'tvc_manufacturer_product_relation';
}

function ww_tvc_get_manufacturer_relation_table_name()
{
    return 'wp_tvc_manufacturer_relation';
}

/**
 * @return string
 * Model type Id
 * This table will container
 */
function ww_tvc_get_model_type()
{
    return 2;
}

/**
 * @return string
 * Brand type Id
 * This table will container
 * brand*product relation
 * model*product relation
 */
// function ww_tvc_get_brand_type()
// {
//     return 1;
// }

/**
 * @return string
 * Manufacturer type Id
 * This table will container
 * brand*product relation
 * model*product relation
 */
function ww_tvc_get_manufacturer_type()
{
    return 1;
}

/**
 * Check if a WooCommerce product category exists by meta key
 *
 * @param string $code The value of _tvc_product_cat_code to search for.
 * @return int|false    Term ID if found, false if not found.
 */
function category_exists_by_code($code)
{
    $term = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key' => '_tvc_product_cat_code',
                'value' => $code,
            )
        ),
        'number' => 1, // limit to 1 for performance
        'fields' => 'ids'
    ));

    if (!empty($term) && !is_wp_error($term)) {
        return $term[0]; // return term_id
    }

    return false;
}


/**
 * @param $import_batch_id
 * @param $params
 * @return void
 * ww_tvc_schedule_auto_pull_based_on_time_frame
 * This will schedule auto pull based on time frame
 * Ex: Pull updated products every last 15 minutes
 *
 */
function ww_tvc_schedule_auto_pull_based_on_time_frame($import_batch_id, $params)
{
    // Mark as running before scheduling
    update_option('tvc_auto_product_pull_running', true, false);

    // 🔹 Schedule background job
    as_schedule_single_action(
        time() + 10,
        'ww_import_product_batch',
        [$import_batch_id, $params]
    );
    // Update the last sync start time
    update_option('tvc_last_sync_time', $params['endDate'], false);
}


/**
 * @param $batch_id
 * @return array|object|stdClass|null
 * ww_get_batch_details
 * Get Single batch details
 */
function ww_get_batch_details($batch_id)
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tvc_import_batches 
            where id = %d
         ORDER BY id DESC 
         LIMIT 1",
            $batch_id
        ),
        ARRAY_A
    );

    return $row;
}


/**
 * @param $currentBatchData
 * @return void
 * ww_action_schedule_already_in_queue_auto_pull_batch
 * this will check if any other auto pull batch is pending
 * if yes then reschedule the next batch based on the time frame
 * $currentBatchData == will be auto pull batch data which was running before
 */
function ww_action_schedule_already_in_queue_auto_pull_batch($currentBatchData)
{
    // get batch data if any other auto pull batch is pending
    $upcomingBatchData = ww_get_upcoming_auto_pull_batch_details($currentBatchData['created_at']);
    if (!empty($upcomingBatchData)) {
        $params = $upcomingBatchData ? json_decode($upcomingBatchData['current_args']) : [];
        // Reschedule the next batch based on the time frame
        ww_tvc_schedule_auto_pull_based_on_time_frame($upcomingBatchData['id'], $params);
    }
}
