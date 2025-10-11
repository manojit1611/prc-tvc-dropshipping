<?php
if (!defined('ABSPATH')) exit;

class MPI_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_mpi_get_child_categories', [$this, 'get_child_categories']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('TVC Store', 'import-products-categories'), // Menu Title
            __('TVC Store', 'import-products-categories'), // Page Title
            'manage_options',
            'tvc-main', // Slug for the main page
            [$this, 'render_admin_page'], // Callback for the main page
            'dashicons-products', // Icon
            26 // Position
        );

        add_submenu_page(
            'tvc-main', // Parent slug
            __('Pull Categories', 'import-products-categories'), // Page title
            __('Pull Categories', 'import-products-categories'), // Menu title
            'manage_options',
            'category_page', // Slug
            [$this, 'render_category_page'] // Callback
        );

        // Submenu: Product Importer
        add_submenu_page(
            'tvc-main', // Parent slug
            __('Pull Products', 'import-products-categories'), // Page title
            __('Pull Products', 'import-products-categories'), // Menu title
            'manage_options',
            'product_page', // Slug
            [$this, 'render_product_page'] // Callback
        );

        // Submenu: Product Fetch By SKU
        add_submenu_page(
            'tvc-main',
            __('Fetch By SKU', 'import-products-categories'),
            __('Fetch By SKU', 'import-products-categories'),
            'manage_options',
            'product-fetch-by-sku',
            [$this, 'fetch_by_sku_admin_page']
        );

        // Submenu: Product Fetch By Date
        add_submenu_page(
            'tvc-main',
            __('Fetch By Date', 'import-products-categories'),
            __('Fetch By Date', 'import-products-categories'),
            'manage_options',
            'product-fetch-by-date',
            [$this, 'fetch_by_date_admin_page']
        );

        add_submenu_page(
            'tvc-main', // Parent slug
            'Category Pricing',
            'Category Pricing',
            'manage_woocommerce',
            'ww-category-pricing',
            'ww_admin_category_pricing_page'
        );

        add_submenu_page(
            'tvc-main',
            __('Logs', 'import-products-categories'),
            __('Logs', 'import-products-categories'),
            'manage_options',
            'tvc-logs',
            [$this, 'render_tvc_import_logs_page']
        );



        add_submenu_page(
            'tvc-main', // Parent slug
            'Woo Logs',    // Page title
            'Woo Logs',        // Menu title
            'manage_woocommerce', // Capability
            'wc-logs-link', // Menu slug (unique)
            function () {
                // Redirect to the WooCommerce Logs page
                wp_redirect(admin_url('admin.php?page=wc-status&tab=logs'));
                exit;
            },
            99 // Position
        );

        add_submenu_page(
            'tvc-main', // Parent slug
            'Schedulers',   // Page title
            'Schedulers',   // Menu title
            'manage_woocommerce', // Capability
            'ww_import_redirect', // Menu slug (unique)
            function () {
                // Redirect directly to your custom status tab
                wp_redirect(admin_url('admin.php?page=wc-status&status=pending&tab=action-scheduler&s=ww_import_product_batch&paged=1'));
                exit;
            },
            99 // Position in submenu
        );


        add_submenu_page(
            'tvc-main', // Parent slug
            'Reset Everything',
            'Reset Everything',
            'manage_options',
            'reset-woocommerce',
            'ww_tvc_wc_reset_page'
        );


        remove_submenu_page('tvc-main', 'tvc-main');
    }

    public function enqueue_scripts($hook)
    {
        wp_enqueue_script(
            'mpi-admin-js',
            TVC_MPI_PLUGIN_URL . 'assets/js/script.js',
            ['jquery'],
            '1.' + rand(1, 10),
            true
        );

        wp_localize_script('mpi-admin-js', 'mpi_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpi_import_nonce')
        ]);
    }


    public function render_product_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/product-pull-page.php';
    }

    public function render_category_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/category-pull-page.php';
    }

    public function fetch_by_sku_admin_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/fetch_by_sku_admin_page.php';
    }

    public function fetch_by_date_admin_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/fetch_by_date_admin_page.php';
    }


    /**
     * @param $slug
     * @return void
     * get_product_cat_select_based_on_tvc_api
     */
    public function get_product_cat_select_based_on_tvc_api($slug = null)
    {
        $tvc_cat = array();
        if (str_starts_with($slug, '{')) {
            $tvc_cat = json_decode(wp_unslash($slug), true);
            $slug = $tvc_cat['Code'] ?? '';
        }
        $apiInstance = new MPI_API();
        $childElements = $apiInstance->get_categories_from_api($slug);
        $childElements = $childElements['CateoryList'] ?? array();
        if (empty($childElements)) {
            wp_send_json(array(
                'msg' => 'No more records under this category.',
                "data" => "",
                "success" => 0
            ));
        }
        ob_start();
        echo '<select required name="category_code[]" class="mpi-child-category select2">';
        echo '<option value="">-- Select Child Category --</option>';
        foreach ($childElements as $child) {
            echo '<option 
             value="' . esc_attr(wp_json_encode($child)) . '" 
            ">' . esc_html($child['Name']) . '</option>';
        }
        echo '</select>';
        $data = ob_get_clean();
        wp_send_json(array(
            'msg' => '',
            "data" => $data,
            "success" => 1
        ));
    }

    public function get_child_categories()
    {
        check_ajax_referer('mpi_import_nonce', 'nonce');
        $json_response = array();
        $slug = sanitize_text_field($_POST['slug']);

        $formData = $_POST;
        $mode = $formData['mode'] ?? '';
        if ($mode == "tvc_api") {
            $this->get_product_cat_select_based_on_tvc_api($slug);
        }

        $term = ww_tvc_get_term_data_by_tvc_code($slug, 'product_cat');
        if (!$term) {
            wp_send_json_error('Invalid category slug');
        }
        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $term->term_id,
        ]);
        if (!empty($children)) {
            ob_start();
            echo '<select name="category_code" class="mpi-child-category select2">';
            echo '<option value="">-- Select Child Category --</option>';
            foreach ($children as $child) {
                $tvc_product_cat_code = get_term_meta($child->term_id, ww_tvs_get_meta_key_tvc_product_cat_code(), true);
                echo '<option value="' . esc_attr($tvc_product_cat_code) . '">' . esc_html($child->name) . '</option>';
            }
            echo '</select>';
            $data = ob_get_clean();
            wp_send_json(array(
                'msg' => '',
                "data" => $data,
                "success" => 1
            ));
        } else {
            wp_send_json(array(
                'msg' => '',
                "error_msg" => "No child categories found for this category.",
                "data" => "",
                "success" => 0
            ));
        }
    }

    function render_tvc_import_logs_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/logs.php';
    }
}
