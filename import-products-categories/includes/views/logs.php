<?php
global $wpdb;
$batches_table = $wpdb->prefix . 'tvc_import_batches';
$logs_table = $wpdb->prefix . 'tvc_import_logs';

$batches = $wpdb->get_results("SELECT batch_id, created_at FROM $batches_table ORDER BY created_at DESC");

$current_batch = isset($_GET['batch_id']) ? sanitize_text_field($_GET['batch_id']) : '';

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
                <th>View Logs</th>
            </tr>
        </thead>
        <tbody>
        <?php if(!empty($batches)) :
            foreach($batches as $batch) :

                // get all logs for this batch
                $logs = $wpdb->get_results($wpdb->prepare(
                    "SELECT status FROM $logs_table WHERE batch_id = %s",
                    $batch->batch_id
                ));

                $total_success = 0;
                $total_failed  = 0;

                if(!empty($logs)) {
                    foreach($logs as $log) {
                        $status = json_decode($log->status, true);
                        if(is_array($status)) {
                            $total_success += isset($status['success']) ? intval($status['success']) : 0;
                            $total_failed  += isset($status['failed']) ? intval($status['failed']) : 0;
                        }
                    }
                }

                ?>
                <tr>
                    <td><?php echo esc_html(date('Y-m-d H:i', strtotime($batch->created_at))); ?></td>
                    <td><?php echo esc_html($batch->batch_id); ?></td>
                    <td><?php echo esc_html($total_success); ?></td>
                    <td><?php echo esc_html($total_failed); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tvc-logs&batch_id=' . $batch->batch_id)); ?>" class="button button-small">View Logs</a>
                    </td>
                </tr>
            <?php endforeach; 
        else : ?>
            <tr><td colspan="5">No batches found.</td></tr>
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
    display:inline-block;
    padding:3px 8px;
    margin:2px;
    border-radius:3px;
    font-size:12px;
    color:#fff;
}
.tvc-status-success { background:#4CAF50; }
.tvc-status-failed { background:#F44336; }
.tvc-status-created { background:#2196F3; }
.tvc-status-updated { background:#FF9800; }
</style>

<?php

global $wpdb;

$current_batch = isset($_GET['batch_id']) ? sanitize_text_field($_GET['batch_id']) : '';
$logs_table = $wpdb->prefix . 'tvc_import_logs';

if ($current_batch) {

    // Pagination setup for logs
    $per_page = 10;
    $current_page = isset($_GET['paged_logs']) ? max(1, intval($_GET['paged_logs'])) : 1;
    $offset = ($current_page - 1) * $per_page;

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

    echo "<h4>Logs for Batch ID: " . esc_html($current_batch) . "</h4>";

    if (!empty($logs)) {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Type</th>';
        echo '<th>Status</th>';
        echo '<th>Success SKUs</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $log) {
            echo '<tr>';

            // Type
            echo '<td>' . esc_html(ucfirst($log->type)) . '</td>';

            // Status badges
            $status = json_decode($log->status, true);
            echo '<td>';
            if (is_array($status)) {
                foreach ($status as $key => $val) {
                    if (is_array($val)) continue; // skip invalid_records
                    $class = '';
                    switch ($key) {
                        case 'success': $class = 'tvc-status-success'; break;
                        case 'failed': $class = 'tvc-status-failed'; break;
                        case 'created': $class = 'tvc-status-created'; break;
                        case 'updated': $class = 'tvc-status-updated'; break;
                        default: $class = 'tvc-status-success';
                    }
                    echo '<span class="tvc-status-badge ' . esc_attr($class) . '">' . esc_html(ucfirst($key)) . ': ' . esc_html($val) . '</span> ';
                }
            } else {
                echo esc_html($log->status);
            }
            echo '</td>';

            // Success SKUs
            $skus = json_decode($log->success_skus, true);
            echo '<td>';
            if (!empty($skus) && is_array($skus)) {
                echo implode(', ', array_map('esc_html', $skus));
            } else {
                echo '<em>No SKUs</em>';
            }
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';

        // Logs pagination
        $total_pages = ceil($total_logs / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
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
