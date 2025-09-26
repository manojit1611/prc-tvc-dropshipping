<?php

add_action('rest_api_init', function () {
    register_rest_route('mpi/v1', '/fetch-by-post', [
        'methods' => 'GET',
        'callback' => 'mpi_fetch_by_post_callback',
        'permission_callback' => '__return_true', // restrict if needed
    ]);
});

function mpi_fetch_by_post_callback ()
{
    if (empty($_GET['post_id']) ) {
        wp_send_json_error([
            'message' => 'Missing required parameters. Please provide post_id.'
        ], 400);
    }

    global $wpdb;
    $current_post_id   = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0; // optional current product ID

    // STEP 3️⃣ — collect all attribute slugs of these products
    $attributes = [];
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT tt.taxonomy, t.slug, tt.term_taxonomy_id
        FROM {$wpdb->term_relationships} tr
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE tr.object_id = %d
    ", $current_post_id));

    if (!empty($rows)) {
        foreach ($rows as $row) {
            if (strpos($row->taxonomy, 'pa_') === 0) {
                $attributes[$row->taxonomy][] = $row->term_taxonomy_id;
            }
        }
    }

    if (empty($attributes)) {
        wp_send_json_error(['message' => 'No attributes found for manufacturer products']);
    }

    $where_parts = [];
    // Build dynamic WHERE parts
    $where_parts = [];
    $params = [];
    foreach ($attributes as $taxonomy => $term_id) {
        $where_parts[] = "(tt.taxonomy = %s AND tt.term_id = %d)";
        $params[] = $taxonomy;
        $params[] = $term_id[0];
    }

    $where_clause = implode(' OR ', $where_parts);

    // Count distinct taxonomies for HAVING
    $taxonomy_count = count($attributes);

    // Build query with placeholders
    $sql = "
        SELECT 
            mpr.term_id,
            GROUP_CONCAT(DISTINCT mpr.post_id ORDER BY mpr.post_id ASC) AS product_ids
        FROM {$wpdb->prefix}tvc_manufacturer_product_relation AS mpr
        INNER JOIN {$wpdb->prefix}term_relationships AS tr 
            ON tr.object_id = mpr.post_id
        INNER JOIN {$wpdb->prefix}term_taxonomy AS tt 
            ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE mpr.type = " . ww_tvc_get_manufacturer_type() . "
        AND ( $where_clause )
        GROUP BY mpr.term_id
        HAVING COUNT(DISTINCT tt.taxonomy) = %d
    ";

    // Add the taxonomy_count to params
    $params[] = $taxonomy_count;

    // Prepare + run
    $query = $wpdb->prepare($sql, $params);
    $results = $wpdb->get_results($query);

    return $results;
}