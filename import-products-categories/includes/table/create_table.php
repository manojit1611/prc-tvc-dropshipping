<?php

register_activation_hook(__FILE__, 'tvc_plugin_create_tables');

function tvc_plugin_create_tables()
{
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // Table 1: Bulk Pricing
    $table1 = $wpdb->prefix . 'tvc_product_bulk_pricing';
    $sql1 = "CREATE TABLE $table1 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        min_qty INT(11) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id)
    ) $charset_collate;";

    // Table 2: Products Data
    $table2 = $wpdb->prefix . 'tvc_products_data';
    $sql2 = "CREATE TABLE $table2 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tvc_product_id BIGINT(20) UNSIGNED NOT NULL,
        tvc_product LONGTEXT NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        KEY tvc_product_id (tvc_product_id),
        KEY post_id (post_id)
    ) $charset_collate;";

    // Table 3: Manufacturer Relation
    $table3 = $wpdb->prefix . 'tvc_manufacturer_relation';
    $sql3 = "CREATE TABLE $table3 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        term_id BIGINT(20) UNSIGNED NOT NULL,
        parent_id BIGINT(20) UNSIGNED DEFAULT NULL,
        type TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY term_id (term_id)
    ) $charset_collate;";

    // Table 4: Manufacturer Product Relation
    $table4 = $wpdb->prefix . 'tvc_manufacturer_product_relation';
    $sql4 = "CREATE TABLE $table4 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        term_id BIGINT(20) UNSIGNED NOT NULL,
        type TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY term_id (term_id)
    ) $charset_collate;";

    // Table 5: Import Logs
    $table5 = $wpdb->prefix . 'tvc_import_logs';
    $sql5 = "CREATE TABLE $table5 (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        import_batch_id INT Null,
        status LONGTEXT NULL,
        success_skus LONGTEXT NULL,
        type VARCHAR(100) NULL,
        failed_sku LONGTEXT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";


    // Table 6: Import Batches
    $table6 = $wpdb->prefix . 'tvc_import_batches';
    $sql6 = "CREATE TABLE wp_tvc_import_batches (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        batch_id LONGTEXT NULL,
        total_success INT NULL,
        total_failed INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    );";


    // Run dbDelta on each table SQL
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);
    dbDelta($sql6);

    // Save the db version so you can do incremental migrations later
    update_option('tvc_plugin_db_version', '1.0');
}
