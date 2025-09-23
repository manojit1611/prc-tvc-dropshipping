<?php

add_action('rest_api_init', function () {
    register_rest_route('mpi/v1', '/fetch-by-manufacturer', [
        'methods' => 'GET', // or 'GET' if you prefer
        'callback' => 'mpi_fetch_by_manufacturer_callback',
        'permission_callback' => '__return_true', // restrict if needed
    ]);
});

function mpi_fetch_by_manufacturer_callback () {
    if ( empty($_GET['manufacturer']) || empty($_GET['post_id']) ) {
        wp_send_json_error([
            'message' => 'Missing required parameters. Please provide manufacturer and post_id.'
        ], 400);
    }

    global $wpdb;
    $manufacturer_slug = sanitize_text_field($_GET['manufacturer']);
    $current_post_id   = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0; // optional current product ID

    // STEP 1️⃣ — get term_id of manufacturer by slug
    $term = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT term_id FROM {$wpdb->terms} WHERE slug = %s",
            $manufacturer_slug
        )
    );

    if (!$term) {
        wp_send_json_error(['message' => 'Manufacturer not found']);
    }

    $manufacturer_term_id = intval($term->term_id);

    // STEP 2️⃣ — find product IDs attached to this term_id
    $product_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT tr.object_id 
                FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s AND tt.term_id = %d",
            'product_manufacturer',
            $manufacturer_term_id
        )
    );

    if (empty($product_ids)) {
        wp_send_json_error(['message' => 'No products found for this manufacturer']);
    }

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

    // build tax_query style WHERE for all attributes
    $product_sets = [];
    foreach ($attributes as $taxonomy => $term_ids) {
        $term_ids = array_unique($term_ids); // remove duplicates

        // Get product IDs for this taxonomy and term(s)
        $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));

        $query = $wpdb->prepare(
            "SELECT object_id FROM {$wpdb->prefix}term_relationships AS tr
            INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s AND tt.term_id IN ($placeholders)",
            array_merge([$taxonomy], $term_ids)
        );

        $products = $wpdb->get_col($query);

        if (!empty($products)) {
            $product_sets[] = $products;
        }
    }

    // Find intersection (products that have all attributes)
    if (!empty($product_sets)) {
        $common_products = call_user_func_array('array_intersect', $product_sets);
    } else {
        $common_products = [];
    }

    // Get URLs
    $urls = [];
    foreach ($common_products as $pid) {
        $urls[] = get_permalink($pid);
    }

    wp_send_json_success([
        'manufacturer' => $manufacturer_slug,
        'products_count' => count($urls),
        'urls' => $urls
    ]);
}