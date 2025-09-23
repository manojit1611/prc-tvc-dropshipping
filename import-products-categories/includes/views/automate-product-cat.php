<?php
/**
 * Plugin Name: Tvc Category Sync
 * Description: Import hierarchical product categories from MPI API into WooCommerce without recursion. Improved: locking, spread scheduling, duplicate checks and logging.
 * Version: 1.2
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Fetch one level of categories from the external API.
 * (Assumes MPI_API class is available.)
 */
function ww_get_categories_level($parent_code = null)
{
    $api = new MPI_API();
    $response = $api->get_categories_from_api($parent_code);
    return $response['CateoryList'] ?? [];
}

/**
 * Import a single level and schedule its children, with:
 * - global transient lock,
 * - spread scheduling (incremental delay),
 * - duplicate schedule checks,
 * - limit of scheduled children per run.
 *
 * Hook: ww_import_child_level
 */
function ww_import_category_level($batch_id, $parent_code = null, $parent_id = 0)
{
    $parent_desc = $parent_code ? $parent_code : 'TOP-LEVEL';
    tvc_sync_log("Starting run for parent: {$parent_desc}");
    $stage = 'Scheduled'; 

    // Simple global lock to avoid concurrent runs
    // $lock_key = 'tvc_sync_running';

    $lock_key = 'tvc_sync_running_' . ($parent_code ?: 'root');
    if (get_transient($lock_key)) {
        tvc_sync_log("Another sync is running — skipping parent: {$parent_desc}");
        return;
    }

    // Lock for a short period (2 min example)
    set_transient($lock_key, 1, 180);
    $successfully_processed = [];

    try {
        $categories = ww_get_categories_level($parent_code);

        if (empty($categories)) {
            tvc_sync_log("No categories found for parent: {$parent_desc}");
            delete_transient($lock_key);
            return;
        }

        $max_schedule_per_run = 500; // adjust if needed
        $i = 0;
        $state = [];
        $success_count = 0;
        $failure_count = 0;
        $updated_count = 0;
        $created_count = 0;
        $invalid_records = [];
        foreach ($categories as $cat) {
            $stage = 'Processing'; 

            if (empty($cat['Name']) || empty($cat['Code'])) {
                $invalid_records[] = $cat;
                tvc_sync_log("Skipped invalid cat record under parent {$parent_desc}");
                continue;
            }

            tvc_sync_log("Category data: " . print_r($cat, true));

            // echo "<pre>";
            // print_r($cat); // Debug output
            // die;

            $name = sanitize_text_field($cat['Name']);
            $code = sanitize_text_field($cat['Code']);
            $slug = sanitize_title($name);

            // Find or create the category
            $existing = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'meta_query' => [
                    [
                        'key' => '_tvc_product_cat_code',
                        'value' => $code,
                    ],
                ],
                'number' => 1,
            ]);

            if (!empty($existing) && !is_wp_error($existing)) {
                $term_id = $existing[0]->term_id;
                wp_update_term($term_id, 'product_cat', [
                    'name' => $name,
                    'slug' => $slug,
                    'parent' => $parent_id,
                ]);
                $updated_count++;
                tvc_sync_log("Updated category: {$name} (code {$code})");
            } else {
                $new_term = wp_insert_term($name, 'product_cat', [
                    'slug' => $slug,
                    'parent' => $parent_id,
                ]);
                if (is_wp_error($new_term)) {
                    tvc_sync_log("Failed to insert category: {$name} – " . $new_term->get_error_message());
                    continue;
                }
                $term_id = $new_term['term_id'];
                $created_count++;
                tvc_sync_log("Created category: {$name} (code {$code})");
            }
            $successfully_processed[] = $code;

            update_term_meta($term_id, '_tvc_product_cat_code', $code);

            // Safety cap per run
            if ($i >= $max_schedule_per_run) {
                tvc_sync_log("Reached max_schedule_per_run ({$max_schedule_per_run}) for parent {$parent_desc}");
                break;
            }

            $success_count++;
            // ✅ NEW: Only schedule if this category actually has sub-categories
            $child_cats = ww_get_categories_level($code);
            if (!empty($child_cats)) {
                $delay = 3 + ($i * 2);
                if (!wp_next_scheduled('ww_import_child_level', [$batch_id, $code, $term_id])) {
                    wp_schedule_single_event(time() + $delay, 'ww_import_child_level', [$batch_id, $code, $term_id]);
                    tvc_sync_log("Scheduled child import for code {$code} (term {$term_id}) with +{$delay}s delay");
                } else {
                    tvc_sync_log("Child import already scheduled for code {$code} (term {$term_id}) — skipping");
                }
            } else {
                tvc_sync_log("No sub-categories for code {$code} — no child job scheduled.");
            }

            $i++;
        }
        $stage = 'Completed';
    } catch (Exception $e) {
        $failure_count++;
        $stage = 'Failed'; 

        tvc_sync_log("Exception during import for parent {$parent_desc}: " . $e->getMessage());
    }

    $state = [
        'success' => $success_count,
        'failed' => $failure_count,
        'created' => $created_count,
        'updated' => $updated_count,
        'total_processed' => $success_count + $failure_count,
        'stage' => $stage,
        'invalid_records' => $invalid_records
    ];

    add_import_error_log($batch_id, json_encode($state), json_encode($successfully_processed), 'category');

    // Release lock
    delete_transient($lock_key);
    tvc_sync_log("Completed run for parent: {$parent_desc}");
}


// Hook for scheduled child jobs
add_action('ww_import_child_level', 'ww_import_category_level', 10, 3);

/**
 * Start the full category sync (schedules the top-level run).
 * Call this on activation or via WP-CLI / eval to start manually.
 */
function ww_start_category_sync_now($parent_code = null, $parent_id = 0)
{
    tvc_sync_log("Request to start full category sync.");
    $batch_id = wp_generate_uuid4(); // globally unique

    // Avoid scheduling duplicate top-level job
    if (!wp_next_scheduled('ww_import_child_level', [$batch_id, $parent_code, $parent_id])) {

        wp_schedule_single_event(time(), 'ww_import_child_level', [$batch_id, $parent_code, $parent_id]);
        tvc_sync_log("Scheduled top-level job (now).");
    } else {
        tvc_sync_log("Top-level job already scheduled. Not scheduling again.");
    }
}

register_activation_hook(__FILE__, 'ww_start_category_sync_now');

/**
 * Utility: clear all scheduled child events.
 * Use via WP-CLI:
 * wp eval 'wp_clear_scheduled_hook("ww_import_child_level");' --allow-root
 */
function ww_clear_all_child_schedules()
{
    wp_clear_scheduled_hook('ww_import_child_level');
    tvc_sync_log("Cleared all scheduled ww_import_child_level events.");
}
