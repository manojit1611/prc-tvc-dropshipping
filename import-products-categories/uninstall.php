<?php

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// List of plugin tables
$tables = [
    $wpdb->prefix . 'tvc_product_bulk_pricing',
    $wpdb->prefix . 'tvc_products_data',
    $wpdb->prefix . 'tvc_manufacturer_relation',
    $wpdb->prefix . 'tvc_manufacturer_product_relation',
    $wpdb->prefix . 'tvc_import_batches',
    $wpdb->prefix . 'tvc_product_sync_logs',
    $wpdb->prefix . 'woo_also_available_links',
];

// Loop and drop each table
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `$table`");
}

// Optionally, delete plugin options
delete_option('tvc_plugin_db_version');
