<?php


/**
 * @return int
 * ww_tvc_auto_pull_sync_type_flag
 */
function ww_tvc_auto_pull_sync_type_flag()
{
    return 1;
}

function ww_tvc_category_products_sync_type_flag()
{
    return 0;
}

function ww_tvc_log_insert_batch_log_entry($batch_id, $formData = array())
{
    global $wpdb;

    // Use the actual table name
    $table_name = $wpdb->prefix . 'tvc_import_batches';

    // Get current_args if provided, default to empty array
    $current_args = $formData['current_args'] ?? array();

    // Prepare data for insertion
    $data = array(
        'batch_id' => $batch_id,
        'current_args' => json_encode($current_args),
        'total_success' => $formData['total_success'] ?? 0,
        'total_failed' => $formData['total_failed'] ?? 0,
        'sync_type' => $formData['sync_type'] ?? ww_tvc_category_products_sync_type_flag(), // keep default as product_cat
    );

    // Correct format: integer, string, integer, integer
    $format = array('%s', '%s', '%d', '%d');

    // Insert data
    $inserted = $wpdb->insert($table_name, $data, $format);

    if ($inserted) {
        return $wpdb->insert_id; // Return the inserted ID
    } else {
        return false; // Insert failed
    }
}


function ww_tvc_log_update_batch_status($import_batch_id, $status)
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_import_batches';
    // Ensure current_args is JSON-encoded
    $data = array(
        'status' => $status
    );
    // Format: string (for JSON)
    $format = array('%d');

    // WHERE clause to target the specific id
    $where = array('id' => $import_batch_id);

    $where_format = array('%d');
    // Perform the update
    $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);

    if ($updated !== false) {
        tvc_sync_log("Batch {$import_batch_id} status updated to {$status}", 'batch');;
        return $updated; // Returns the number of rows updated (0 if nothing changed)
    } else {
        return false; // Update failed
    }
}

function ww_tvc_log_update_current_args($batch_id, $current_args = array())
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_import_batches';

    // Ensure current_args is JSON-encoded
    $data = array(
        'current_args' => json_encode($current_args)
    );

    // Format: string (for JSON)
    $format = array('%s');

    // WHERE clause to target the specific id
    $where = array('id' => $batch_id);
    $where_format = array('%d');

    // Perform the update
    $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);

    if ($updated !== false) {
        return $updated; // Returns the number of rows updated (0 if nothing changed)
    } else {
        return false; // Update failed
    }
}

/**
 * @param $batch_id
 * @param $tvc_sku
 * @param $status
 * @param $meta_data
 * @param $tvc_product_data
 * @return false|int
 * ww_tvc_log_insert_product_sync
 */
function ww_tvc_log_insert_product_sync($batch_id, $tvc_sku, $status = 0, $meta_data = array(), $tvc_product_data = array())
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_product_sync_logs';

    if (empty($meta_data)) {
        $meta_data = array();
    }

    // Prepare data
    $data = array(
        'batch_id' => $batch_id,
        'tvc_sku' => $tvc_sku,
        'status' => $status,
        'meta_data' => json_encode($meta_data),
        'tvc_product_data' => json_encode($tvc_product_data),
    );

    // Correct format: integer, string, integer, string, string
    // $format = array('%d', '%s', '%d', '%s', '%s');

    // Insert data
    $inserted = $wpdb->insert($table_name, $data);

    if ($inserted) {
        return $wpdb->insert_id; // Return inserted ID
    } else {
        return false; // Insert failed
    }
}

/**
 * @param $batch_id
 * @param $tvc_sku
 * @param $status
 * @param $meta_data
 * @param $tvc_product_data
 * @return bool|int|mysqli_result|null
 * ww_tvc_log_update_product_sync
 */
function ww_tvc_log_update_product_sync($batch_id, $tvc_sku, $status = null, $meta_data = null, $tvc_product_data = null, $msg = null)
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_product_sync_logs';


    // Prepare data array only with values that are not null
    $data = array();
    $format = array();

    if ($status !== null) {
        $data['status'] = $status;
        $format[] = '%d';
    }

    if ($meta_data !== null) {
        $data['meta_data'] = json_encode($meta_data);
        $format[] = '%s';
    }

    if ($tvc_product_data !== null) {
        $data['tvc_product_data'] = json_encode($tvc_product_data);
        $format[] = '%s';
    }

    if ($msg !== null) {
        $data['failed_log'] = $msg;
        $format[] = '%s';
    }

    if (empty($data)) {
        return false; // Nothing to update
    }

    // WHERE clause
    $where = array(
        'batch_id' => $batch_id,
        'tvc_sku' => $tvc_sku
    );
    $where_format = array('%d', '%s');

    // Perform update
    $updated = $wpdb->update($table_name, $data, $where);

    if ($updated !== false) {
        return $updated; // Number of rows updated (0 if no change)
    } else {
        return false; // Update failed
    }
}

function ww_tvc_log_get_batch_data($batch_id)
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_import_batches';

    // Prepare the query safely
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
        $batch_id
    );

    // Get the row
    $batch_data = $wpdb->get_row($query, ARRAY_A); // ARRAY_A returns associative array

    return $batch_data ?: false; // Return false if not found
}

function ww_tvc_log_increment_total_success($batch_id)
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_import_batches';

    // Prepare the query to increment total_success safely
    $query = $wpdb->prepare(
        "UPDATE {$table_name} SET total_success = total_success + 1 WHERE id = %d",
        $batch_id
    );

    // Execute the query
    $updated = $wpdb->query($query);

    if ($updated !== false) {
        return $updated; // Number of rows affected
    } else {
        return false; // Update failed
    }
}

function ww_tvc_log_increment_total_failed($batch_id)
{
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'tvc_import_batches';

    // Prepare the query to increment total_success safely
    $query = $wpdb->prepare(
        "UPDATE {$table_name} SET total_failed = total_failed + 1 WHERE id = %d",
        $batch_id
    );

    // Execute the query
    $updated = $wpdb->query($query);

    if ($updated !== false) {
        return $updated; // Number of rows affected
    } else {
        return false; // Update failed
    }
}


/**
 * @return int
 * ww_tvc_batch_cancel_status_flag
 */
function ww_tvc_batch_cancel_status_flag()
{
    return 3;
}

/**
 * @return int
 * ww_tvc_batch_complete_status_flag
 * Batch Status Complete
 */
function ww_tvc_batch_complete_status_flag()
{
    return 2;
}

/**
 * @return int
 * ww_tvc_batch_running_status_flag
 */
function ww_tvc_batch_running_status_flag()
{
    return 1;
}

/**
 * @return int
 * ww_tvc_batch_pending_status_flag
 * In-queue | Pending
 */
function ww_tvc_batch_pending_status_flag()
{
    return 0;
}


/**
 * @param $import_batch_id
 * @return void
 * ww_tvc_release_auto_pull_lock
 * This will release auto pull lock flag from option table
 */
function ww_tvc_release_auto_pull_lock($import_batch_id)
{
    $batch_data = ww_tvc_log_get_batch_data($import_batch_id);
    $sync_type = $batch_data['sync_type'] ?? '';
    if ($sync_type == ww_tvc_auto_pull_sync_type_flag() && get_option('tvc_auto_product_pull_running', false) == true) {
        update_option('tvc_auto_product_pull_running', false, false);
    }
}