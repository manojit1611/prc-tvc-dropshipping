<?php
global $wpdb;
$batches_table = $wpdb->prefix . 'tvc_import_batches';
// $logs_table = $wpdb->prefix . 'tvc_import_logs';
$sync_logs_table = $wpdb->prefix . 'tvc_product_sync_logs';

$batches = $wpdb->get_results("SELECT batch_id, created_at, total_success, total_failed FROM $batches_table ORDER BY created_at DESC");

$current_batch = isset($_GET['import_id']) ? sanitize_text_field($_GET['import_id']) : '';

if (isset($_GET['delete_batch']) && !empty($_GET['delete_batch'])) {
    $id = sanitize_text_field($_GET['delete_batch']);
    $batch_name = sanitize_text_field($_GET['delete_batch_name']);

    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_batch_' . $id)) {
        // delete logs first
        $wpdb->delete($batches_table, ['id' => $id]);
        $wpdb->delete($sync_logs_table, ['batch_id' => $id]);
        // $wpdb->delete($batches_table, ['id' => $id]);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}actionscheduler_actions 
                WHERE args LIKE %s",
                '%' . $wpdb->esc_like($batch_name) . '%'
            )
        );

        // redirect to avoid resubmission
        wp_safe_redirect(remove_query_arg(['delete_batch', '_wpnonce']));
        exit;
    }
}

if (isset($_GET['batch_name']) && !empty($_GET['batch_name'])) {
    $batch_name = sanitize_text_field($_GET['batch_name']);
    $batch_id   = sanitize_text_field($_GET['batch_id'] ?? '');

    // ✅ Only verify nonce if it actually exists
    if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'batch_' . $batch_name)) {
        ww_cancel_product_batches($batch_id, $batch_name);

        wp_safe_redirect(remove_query_arg(['batch', '_wpnonce']));
        exit;
    }
}

if (isset($_GET['restart_batch']) && !empty($_GET['restart_batch'])) {
    $batch_id = sanitize_text_field($_GET['restart_batch']);
    $batch_name = sanitize_text_field($_GET['batch_name']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'restart_batch_' . $batch_id)) {
        ww_restart_product_batch($batch_id, $batch_name);

        wp_safe_redirect(remove_query_arg(['restart_batch', '_wpnonce']));
        exit;
    }
}

function ww_restart_product_batch($batch_id, $batch_name)
{
    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}actionscheduler_actions 
            SET status = %s 
            WHERE args LIKE %s",
            'pending',
            '%' . $wpdb->esc_like($batch_name) . '%'
        )
    );


    // $row = $wpdb->get_row(
    //     $wpdb->prepare(
    //         "SELECT current_args 
    //         FROM {$wpdb->prefix}tvc_import_batches 
    //         WHERE id = %d",
    //         $batch_id
    //     )
    // );

    // if ($row) {
    //     $args = json_decode($row->current_args, true);
    // }

    // as_schedule_single_action(
    //     time(),
    //     'ww_import_product_batch',
    //     [$batch_name, $args]
    // );

    ww_tvc_log_update_batch_status($batch_id, ww_tvc_batch_running_status_flag());

    tvc_sync_log("Restart product import jobs.", 'product');
}

function ww_cancel_product_batches($batch_id, $batch_name)
{
    global $wpdb;

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}actionscheduler_actions 
            SET status = %s 
            WHERE args LIKE %s",
            'canceled',
            '%' . $wpdb->esc_like($batch_name) . '%'
        )
    );

    if (str_contains($batch_name, "AUTO_PRODUCT_PULL")) {
        ww_tvc_release_auto_pull_lock($batch_id);
    }

    // Mark as cancel
    ww_tvc_log_update_batch_status($batch_id, ww_tvc_batch_cancel_status_flag());
}

if (!$current_batch) {
?>
    <?php
    global $wpdb;

    $logs_table = $wpdb->prefix . 'tvc_import_logs';
    $batches_table = $wpdb->prefix . 'tvc_import_batches';

    // Pagination setup
    $per_page = 10;
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
                    <th>Params</th>
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
                            <td>
                                <?php
                                switch ($batch->status) {
                                    case 0:
                                        echo "Pending";
                                        break;
                                    case 1:
                                        echo "Running";
                                        break;
                                    case 2:
                                        echo "Complete";
                                        break;
                                    case 3:
                                        echo "Canceled";
                                        break;
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($batch->current_args); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=tvc-logs&import_id=' . $batch->id)); ?>"
                                    class="button button-small">
                                    View Logs
                                </a>
                            </td>
                            <td style="display: flex;flex-wrap: wrap;grid-gap: 10px;">
                                <?php
                                $delete_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'tvc-logs',
                                        'delete_batch' => $batch->id,
                                        'delete_batch_name' => $batch->batch_id
                                    ]),
                                    'delete_batch_' . $batch->id
                                );

                                $cancel_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'tvc-logs',
                                        'batch_name' => $batch->batch_id,
                                        'batch_id' => $batch->id,
                                    ]),
                                    'batch_' . $batch->batch_id,
                                );

                                $restart_url = wp_nonce_url(
                                    add_query_arg([
                                        'page' => 'tvc-logs',
                                        'restart_batch' => $batch->id,
                                        'batch_name' => $batch->batch_id,
                                    ]),
                                    'restart_batch_' . $batch->id,
                                );

                                if ($batch->status != 2 && $batch->status != 3) {
                                ?>
                                    <a href="<?php echo esc_url($cancel_url); ?>"
                                        class="button button-small button-danger"
                                        onclick="return confirm('Are you sure you want to Stop this batch');">Cancel</a>
                                <?php
                                }
                                ?>

                                <?php
                                if (
                                    !str_contains($batch->batch_id, "AUTO_PRODUCT_PULL") &&
                                    ($batch->status == 3 || $batch->status == 0)
                                ) {
                                ?>
                                    <a href="<?php echo esc_url($restart_url); ?>"
                                        class="button button-small button-danger"
                                        onclick="return confirm('Are you sure you want to restart this batch?');">
                                        Start/Restart
                                    </a>
                                <?php
                                }
                                ?>
                                <a href="<?php echo esc_url($delete_url); ?>"
                                    class="button button-small button-danger"
                                    onclick="return confirm('Are you sure you want to delete this batch and all related logs?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach;
                else : ?>
                    <tr>
                        <td colspan="8">No batches found.</td>
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
$logs_table = $wpdb->prefix . 'tvc_product_sync_logs';

if ($current_batch) {
    // Pagination setup for logs
    $per_page = 10;
    $current_page = isset($_GET['paged_logs']) ? max(1, intval($_GET['paged_logs'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_logs = $wpdb->get_var($wpdb->prepare(
        "SELECT batch_id FROM $logs_table WHERE batch_id = %s",
        $current_batch
    ));

    // Total logs count
    $total_logs = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $logs_table WHERE batch_id = %s",
        $current_batch
    ));

    // Fetch logs with limit & offset
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table WHERE batch_id = %s ORDER BY id DESC LIMIT %d OFFSET %d",
        $current_batch,
        $per_page,
        $offset
    ));

    echo "<h4>Logs for Batch ID: " . esc_html($batches[0]->batch_id) . "</h4>";

    echo "<span><strong>Total Success</strong></span>: " . esc_html($batches[0]->total_success) . "</br>";
    echo "<span><strong>Total Failed</strong><span>: " . esc_html($batches[0]->total_failed) . "</br></br>";

    if (!empty($logs)) {
        echo '<table class="widefat fixed striped" style="width:98%;">';
        echo '<thead><tr>';
        echo '<th>SKU</th>';
        echo '<th>Status</th>';
        echo '<th>Update</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $log) {
            echo '<tr>';

            echo '<td>' . esc_html($log->tvc_sku) . '</td>';

            echo '<td>';
            switch ($log->status) {
                case 1:
                    echo "Success";
                    break;
                case 2:
                    echo "Failed";
                    break;
            }
            echo '</td>';

            echo "<td>";
            if ($log->status == 2) {
                echo "<button id='update_btn' data-sku=" . esc_html($log->tvc_sku) . " class='button button-primary'>Update</button>";
            } else {
                echo "N/A";
            }
            echo "</td>";

            echo '<td>';
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

<script>
    jQuery(document).ready(function($) {
        $('#update_btn').on('click', function() {
            var sku = $(this).data('sku');

            if (!sku) {
                alert('Please enter a SKU');
                return;
            }

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'GET',
                data: {
                    action: 'product_fetch',
                    sku: sku,
                    redirect: false
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.msg);
                    } else {
                        alert(response.data.msg);
                    }
                },
                error: function() {
                    alert('Something went wrong.');
                }
            });
        });
    });
</script>

</div>