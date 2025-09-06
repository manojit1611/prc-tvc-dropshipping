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
        'name'     => basename($url),
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