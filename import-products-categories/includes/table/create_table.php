<?php


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


    // Table 6: Import Batches
    $sql6 = "CREATE TABLE " . $wpdb->prefix . "tvc_import_batches" . " (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        batch_id LONGTEXT NULL,
        total_success INT NULL,
        total_failed INT NULL,
        status VARCHAR(12) DEFAULT '0',
        sync_type TINYINT DEFAULT 0,
        current_args JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    );";

    // Individual product sync log
    $tvc_product_sync_logs_table_name = $wpdb->prefix . 'tvc_product_sync_logs';
    $sql_product_sync_log = "CREATE TABLE IF NOT EXISTS `$tvc_product_sync_logs_table_name` (
        `id` bigint NOT NULL AUTO_INCREMENT,
        `batch_id` longtext,
        `tvc_sku` varchar(200) DEFAULT NULL,
        `status` tinyint NOT NULL DEFAULT '0',
        `meta_data` longtext,
        `tvc_product_data` longtext,
        `failed_log` LONGTEXT DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    )";


    $also_available_table = $wpdb->prefix . 'woo_also_available_links';
    $also_available_table_sql = "CREATE TABLE $also_available_table (
        product_id BIGINT UNSIGNED NOT NULL,
        related_product_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (product_id, related_product_id),
        KEY related_product_id (related_product_id)
    )";


    // Run dbDelta on each table SQL
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    // dbDelta($sql5);
    dbDelta($sql6);
    dbDelta($sql_product_sync_log);
    dbDelta($also_available_table_sql);

    // Save the db version so you can do incremental migrations later
    update_option('tvc_plugin_db_version', '1.0');
}
