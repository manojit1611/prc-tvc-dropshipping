<?php
/**
 * Plugin Name: Import Products & Categories Old
 * Plugin URI: https://example.com/import-products-categories
 * Description: A simple WordPress plugin that imports products and categories.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

// Prevent direct access to file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// === API Configuration ===
define('BASE_URL', 'https://openapi.tvc-mall.com');
define('EMAIL', 'bharat@labxrepair.com.au');
define('PASSWORD', 'Eik2Pea9@;??');
define('MPI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MPI_PLUGIN_URL', plugin_dir_url(__FILE__));

// === Includes ===
require_once MPI_PLUGIN_PATH . 'includes/class-mpi-admin.php';
require_once MPI_PLUGIN_PATH . 'includes/class-mpi-api.php';
require_once MPI_PLUGIN_PATH . 'includes/class-mpi-importer.php';
require_once MPI_PLUGIN_PATH . 'includes/helpers.php';

// === Init Plugin ===
add_action('plugins_loaded', function () {
    new MPI_Admin();
    new MPI_API();
    new MPI_Importer();
});

