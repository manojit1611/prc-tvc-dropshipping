<?php
global $wpdb;
$batches_table = $wpdb->prefix . 'tvc_import_batches';
$logs_table = $wpdb->prefix . 'tvc_import_logs';

$batches = $wpdb->get_results("SELECT batch_id, created_at FROM $batches_table ORDER BY created_at DESC");

$current_batch = isset($_GET['import_id']) ? sanitize_text_field($_GET['import_id']) : '';

if (isset($_GET['delete_batch']) && !empty($_GET['delete_batch'])) {
    $id = sanitize_text_field($_GET['delete_batch']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_batch_' . $id)) {
        // delete logs first
        $wpdb->delete($logs_table, ['id' => $id]);
        // delete batch
        $wpdb->delete($batches_table, ['id' => $id]);
        // redirect to avoid resubmission
        wp_safe_redirect(remove_query_arg(['delete_batch', '_wpnonce']));
        exit;
    }
}

if (isset($_GET['stop_batch']) && !empty($_GET['stop_batch'])) {
    $batch_id = sanitize_text_field($_GET['stop_batch']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'stop_batch_' . $batch_id)) {
        ww_clear_all_product_batches($batch_id);

        wp_safe_redirect(remove_query_arg(['stop_batch', '_wpnonce']));
        exit;
    }
}

if (isset($_GET['restart_batch']) && !empty($_GET['restart_batch'])) {
    $batch_id = sanitize_text_field($_GET['restart_batch']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'restart_batch_' . $batch_id)) {
        ww_restart_product_batch($batch_id);

        wp_safe_redirect(remove_query_arg(['restart_batch', '_wpnonce']));
        exit;
    }
}

function ww_restart_product_batch($batch_id)
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT status 
            FROM {$wpdb->prefix}tvc_import_logs 
            WHERE import_batch_id = %d 
            ORDER BY id DESC 
            LIMIT 1",
            $batch_id
        )
    );

    if ($row) {
        $status = json_decode($row->status, true);

        if (isset($status['filters'])) {
            $filters = $status['filters'];
        }
    }

    start_import_batch($batch_id);

    $filters['batch_id'] = $batch_id;
    $batch_id = 'latest_products';

    as_schedule_single_action(
        time(),
        'ww_import_product_batch',
        [$batch_id, $filters]
    );

    tvc_sync_log("Restart product import jobs.", 'product');
}

function ww_clear_all_product_batches($batch_id)
{
    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}actionscheduler_actions 
            WHERE args LIKE %s",
            '%' . $wpdb->esc_like($batch_id) . '%'
        )
    );

    start_import_batch($batch_id, 'Stopped');

    // wp_clear_scheduled_hook('ww_import_product_batch');
    tvc_sync_log("Cleared all scheduled product import jobs.", 'product');
}


if (!$current_batch) {
?>
    <?php
    global $wpdb;

    $logs_table    = $wpdb->prefix . 'tvc_import_logs';
    $batches_table = $wpdb->prefix . 'tvc_import_batches';

    // Pagination setup
    $per_page  = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Get total number of batches
    $total_batches = $wpdb->get_var("SELECT COUNT(*) FROM $batches_table");

    // Fetch batches for current page
    $batches = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $batches_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));

    $total_pages = ceil($total_batches / $per_page);
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">📦 Import Logs Summary</h1>
        <hr class="wp-header-end">

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Batch ID</th>
                    <th>Total Success</th>
                    <th>Total Failed</th>
                    <th>Status</th>
                    <th>View Logs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($batches)) :
                    foreach ($batches as $batch) :
                ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->created_at))); ?></td>
                            <td><?php echo esc_html($batch->batch_id); ?></td>
                            <td><?php echo esc_html($batch->total_success); ?></td>
                            <td><?php echo esc_html($batch->total_failed); ?></td>
                            <td><?php echo esc_html($batch->status); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tvc-logs&import_id=' . $batch->id)); ?>" class="button button-small">
                                    View Logs
                                </a>
                            </td>
                            <td>
                                <?php
                                $delete_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'tvc-logs',
                                        'delete_batch' => $batch->id
                                    ]),
                                    'delete_batch_' . $batch->id
                                );

                                $stop_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'tvc-logs',
                                        'stop_batch' => $batch->id
                                    ]),
                                    'stop_batch_' . $batch->id
                                );

                                $restart_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'tvc-logs',
                                        'restart_batch' => $batch->id
                                    ]),
                                    'restart_batch_' . $batch->id
                                );
                                ?>
                                <a href="<?php echo esc_url($stop_url); ?>"
                                    class="button button-small button-danger"
                                    onclick="return confirm('Are you sure you want to Stop this batch');">Stop</a>

                                <a href="<?php echo esc_url($restart_url); ?>"
                                    class="button button-small button-danger"
                                    onclick="return confirm('Are you sure you want to restart this batch');">Start/Restart</a>

                                <a href="<?php echo esc_url($delete_url); ?>"
                                    class="button button-small button-danger"
                                    onclick="return confirm('Are you sure you want to delete this batch and all related logs?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach;
                else : ?>
                    <tr>
                        <td colspan="5">No batches found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base_url = remove_query_arg('paged');
                    if ($current_page > 1) :
                        $prev_page = add_query_arg('paged', $current_page - 1, $base_url); ?>
                        <a class="prev-page button" href="<?php echo esc_url($prev_page); ?>">&laquo; Previous</a>
                    <?php endif; ?>

                    <span class="paging-input">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </span>

                    <?php if ($current_page < $total_pages) :
                        $next_page = add_query_arg('paged', $current_page + 1, $base_url); ?>
                        <a class="next-page button" href="<?php echo esc_url($next_page); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php
}

?>

<style>
    .tvc-status-badge {
        display: inline-block;
        padding: 3px 8px;
        margin: 2px;
        border-radius: 3px;
        font-size: 12px;
        color: #fff;
    }

    .tvc-status-success {
        background: #4CAF50;
    }

    .tvc-status-failed {
        background: #F44336;
    }

    .tvc-status-created {
        background: #2196F3;
    }

    .tvc-status-updated {
        background: #FF9800;
    }
</style>

<?php

global $wpdb;

$current_batch = isset($_GET['import_id']) ? sanitize_text_field($_GET['import_id']) : '';
$logs_table = $wpdb->prefix . 'tvc_import_logs';

if ($current_batch) {
    // Pagination setup for logs
    $per_page = 10;
    $current_page = isset($_GET['paged_logs']) ? max(1, intval($_GET['paged_logs'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Total logs count
    $total_logs = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $logs_table WHERE import_batch_id = %s",
        $current_batch
    ));

    // Fetch logs with limit & offset
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table WHERE import_batch_id = %s ORDER BY id DESC LIMIT %d OFFSET %d",
        $current_batch,
        $per_page,
        $offset
    ));

    echo "<h4>Logs for Batch ID: " . esc_html($current_batch) . "</h4>";

    if (!empty($logs)) {
        echo '<table class="widefat fixed striped" style="width:98%;">';
        echo '<thead><tr>';
        echo '<th>Type</th>';
        echo '<th>Status</th>';
        echo '<th>Filters</th>';
        echo '<th>Success SKUs</th>';
        echo '<th>Invalid records</th>';
        echo '<th>Failed records</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $log) {
            echo '<tr>';

            // Type
            echo '<td>' . esc_html(ucfirst($log->type)) . '</td>';

            // Status badges
            $status = json_decode($log->status, true);
            echo '<td>';
            if (is_array($status)) {

                // Begin inner table
                echo '<table class="tvc-status-table" style="width:100%; border-collapse: collapse;">';
                echo '<thead><tr>';
                echo '<th style="border:1px solid #ccc;padding:4px;">Key</th>';
                echo '<th style="border:1px solid #ccc;padding:4px;">Value</th>';
                echo '</tr></thead><tbody>';

                // Badge map
                $badge_map = [
                    'success' => 'tvc-status-success',
                    'failed' => 'tvc-status-failed',
                    'created' => 'tvc-status-created',
                    'updated' => 'tvc-status-updated',
                    'total_processed' => 'tvc-status-success',
                ];

                foreach ($status as $key => $val) {
                    // skip arrays first
                    if (in_array($key, ['invalid_records', 'filters', 'failed_records'], true)) {
                        continue;
                    }

                    $class = isset($badge_map[$key]) ? $badge_map[$key] : 'tvc-status-success';
                    $value = is_scalar($val) ? $val : json_encode($val);

                    echo '<tr>';
                    echo '<td style="border:1px solid #ccc;padding:4px;"><b>' . esc_html(ucfirst(str_replace('_', ' ', $key))) . '</b></td>';
                    echo '<td style="border:1px solid #ccc;padding:4px;">' . esc_html($value) . '</td>';
                    echo '</tr>';
                }

                // Stage row
                if (!empty($status['stage'])) {
                    echo '<tr>';
                    echo '<td style="border:1px solid #ccc;padding:4px;"><strong>Stage</strong></td>';
                    echo '<td style="border:1px solid #ccc;padding:4px;">' . esc_html($status['stage']) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            } else {
                echo esc_html($log->status);
            }

            echo '</td>';

            echo '<td>';
            if (!empty($status['filters']) && is_array($status['filters'])) {
                foreach ($status['filters'] as $filterKey => $filterVal) {
                    $filterVal = $filterVal === null ? 'null' : $filterVal;
                    echo '<li>' . esc_html(ucfirst(str_replace('_', ' ', $filterKey))) . ': <code>' . esc_html($filterVal) . '</code></li>';
                }
                echo '</ul></div>';
            }
            echo '</td>';


            // Success SKUs column (like before)
            $skus = json_decode($log->success_skus, true);
            echo '<td>';
            if (!empty($skus) && is_array($skus)) {
                echo implode(', ', array_map(function($sku) {
                    $url = admin_url('edit.php?post_status=all&post_type=product&s=' . urlencode($sku));
                    return '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($sku) . '</a>';
                }, $skus));
            } else {
                echo '<em>No SKUs</em>';
            }
            echo '</td>';

            echo '<td>';
            // Invalid records list
            if (!empty($status['invalid_records']) && is_array($status['invalid_records'])) {
                echo '<div><strong>Invalid Records:</strong><ul style="margin-left:15px;">';
                foreach ($status['invalid_records'] as $invalid) {
                    foreach ($invalid as $msg => $sku) {
                        $url = admin_url('edit.php?post_status=all&post_type=product&s=' . urlencode($sku));
                        echo '<li>' . esc_html($msg) . ' → <code><a href="' . esc_url($url) . '" target="_blank">' . esc_html($sku) . '</a></code></li>';
                    }
                }
                echo '</ul></div>';
            }
            echo '</td>';

            $skus = $log->failed_sku;
            echo '<td>';
            echo $skus;
            echo '</td>';

            echo '</tr>';
        }
        echo '</tbody></table>';

        // Logs pagination
        $total_pages = ceil($total_logs / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav" style="width: 98%;"><div class="tablenav-pages">';
            $base_url = remove_query_arg('paged_logs');
            if ($current_page > 1) {
                $prev_page = add_query_arg('paged_logs', $current_page - 1, $base_url);
                echo '<a class="prev-page button" href="' . esc_url($prev_page) . '">&laquo; Previous</a> ';
            }

            echo '<span class="paging-input">Page ' . $current_page . ' of ' . $total_pages . '</span> ';

            if ($current_page < $total_pages) {
                $next_page = add_query_arg('paged_logs', $current_page + 1, $base_url);
                echo '<a class="next-page button" href="' . esc_url($next_page) . '">Next &raquo;</a>';
            }

            echo '</div></div>';
        }
    } else {
        echo '<p>No logs found for this batch.</p>';
    }
}

?>
</div>