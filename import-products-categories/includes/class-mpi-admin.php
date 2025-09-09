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
            __('Product Importer', 'import-products-categories'),
            __('Product Importer', 'import-products-categories'),
            'manage_options',
            'mpi-settings',
            [$this, 'render_admin_page'],
            'dashicons-products',
            26
        );

        add_menu_page(
            __('XML Importer', 'import-products-categories'),
            __('XML Importer', 'import-products-categories'),
            'manage_options',
            'xml-importer',
            [$this, 'render_xml_importer_page'],
            'dashicons-upload',
            27
        );
    }

    public function enqueue_scripts($hook)
    {
        wp_enqueue_script(
            'mpi-admin-js',
            TVC_MPI_PLUGIN_URL . 'assets/js/script.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('mpi-admin-js', 'mpi_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpi_import_nonce')
        ]);
    }

    public function render_admin_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/admin-page.php';
    }

    public function render_xml_importer_page()
    {
        include TVC_MPI_PLUGIN_PATH . 'includes/views/xml-importer-page.php';
    }


    public function get_child_categories()
    {
        check_ajax_referer('mpi_import_nonce', 'nonce');

        $json_response = array();

        $slug = sanitize_text_field($_POST['slug']);
//        $term = get_term_by('slug', $slug, 'product_cat');
        $term = ww_tvc_get_term_data_by_tvc_code($slug,'product_cat');


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
            echo '<select name="category_code" class="mpi-child-category">';
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
                "error_msg" => "No child categories found for this category. Please create a child category and try again.",
                "data" => "",
                "success" => 0
            ));
        }
    }
}
