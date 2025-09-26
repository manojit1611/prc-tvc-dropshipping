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

function get_type($index)
{
    $types = [
        'brand',
        'model',
        'series',
    ];

    return $types[$index];
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
 * @param string $code  The value of _tvc_product_cat_code to search for.
 * @return int|false    Term ID if found, false if not found.
 */
function category_exists_by_code( $code ) {
    $term = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key'   => '_tvc_product_cat_code',
                'value' => $code,
            )
        ),
        'number' => 1, // limit to 1 for performance
        'fields' => 'ids'
    ) );

    if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
        return $term[0]; // return term_id
    }

    return false;
}