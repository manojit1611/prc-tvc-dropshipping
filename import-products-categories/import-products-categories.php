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
if (!defined('ABSPATH')) {
    exit;
}


// === API Configuration ===
define('TVC_BASE_URL', 'https://openapi.tvc-mall.com');
define('TVC_EMAIL', 'bharat@labxrepair.com.au');
define('TVC_PASSWORD', 'Eik2Pea9@;??');
define('TVC_MPI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TVC_MPI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * @return string[]
 * ww_tvs_get_allowed_channel_product_cat_ids
 * Return Allowed channel product ids
 */
function ww_tvs_get_allowed_channel_product_cat_ids()
{
//    C0067 = Cell Phone Accessories
    return array_column(ww_tvs_get_allowed_channel_product_cats(), 'code');
}

function ww_tvs_get_allowed_channel_product_cats()
{
    $cats = array();
    $cats[] = array(
        'code' => "C0067",
        "name" => "Cell Phone Accessories",
    );
//    $cats[] = array(
//        'code' => "C0037",
//        "name" => "Cell Phone Cases",
//    );
    return $cats;
}

/**
 * @param $code
 * @return int|mixed|string|WP_Term|null
 * get_product_cat_by_code
 */
function ww_tvc_get_term_data_by_tvc_code($code, $taxonomy = 'product_cat')
{
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'meta_query' => [
            [
                'key' => ww_tvs_get_meta_key_tvc_product_cat_code(),
                'value' => $code,
            ],
        ],
        'number' => 1, // fetch only one
    ]);
    if (!empty($terms) && !is_wp_error($terms)) {
        return $terms[0]; // return single WP_Term
    }
    return null; // nothing found
}

function ww_tvs_get_meta_key_tvc_product_cat_code()
{
    return '_tvc_product_cat_code';
}

/**
 * @return string
 * ww_tvs_get_product_model_taxonomy_type
 */
function ww_tvs_get_product_model_taxonomy_type()
{
    return 'product_model';
}

/**
 * @param $cat
 * @return int|mixed|null
 * ww_tvc_save_formatted_tvc_cat_term
 * This will save or update the term in WordPress based on tvc data
 */
function ww_tvc_save_formatted_tvc_cat_term($cat)
{
    $return_term = null;
    $taxonomy = $cat['taxonomy'] ?? '';
    if (!$taxonomy) {
        return null;
    }
    $existing = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'meta_query' => [
            [
                'key' => ww_tvs_get_meta_key_tvc_product_cat_code(),
                'value' => $cat['code'],
            ],
        ],
        'number' => 1,
    ]);
    $name = sanitize_text_field($cat['name']);
    $code = sanitize_text_field($cat['code']);
    $slug = sanitize_title($name); // slug comes from a name;
    if (!empty($existing) && !is_wp_error($existing)) {
        // Update the existing category
        $term_id = $existing[0]->term_id;
        wp_update_term($term_id, $taxonomy, [
            'name' => $name,
            'slug' => $slug,
            'parent' => $cat['parent'] ?? 0,
        ]);
    } else {
        //  Create a new category
        $new_term = wp_insert_term(
            $name,
            $taxonomy,
            [
                'slug' => $slug,
                'parent' => $cat['parent'] ?? 0,
            ]
        );
        if (is_wp_error($new_term)) {
            return null;
        }
        $term_id = $new_term['term_id'];
    }
    update_term_meta($term_id, ww_tvs_get_meta_key_tvc_product_cat_code(), $code);
    return $term_id;
}

function ww_tvc_print_r($data = array(), $die = false)
{
    echo '<pre>';
    echo print_r($data);
    echo '</pre>';
    if ($die) {
        die();
    }
}

// Add field to category edit form
add_action('product_cat_add_form_fields', 'add_category_commission_field');
add_action('product_cat_edit_form_fields', 'edit_category_commission_field');

function add_category_commission_field($taxonomy) {
    ?>
    <div class="form-field">
        <label for="cat_commission"><?php _e('Commission (%)', 'textdomain'); ?></label>
        <input type="number" name="cat_commission" id="cat_commission" value="" step="0.01" min="0">
        <p class="description"><?php _e('Enter commission percentage for this category.', 'textdomain'); ?></p>
    </div>
    <?php
}

function edit_category_commission_field($term) {
    $commission = get_term_meta($term->term_id, 'cat_commission', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="cat_commission"><?php _e('Commission (%)', 'textdomain'); ?></label></th>
        <td>
            <input type="number" name="cat_commission" id="cat_commission" value="<?php echo esc_attr($commission); ?>" step="0.01" min="0">
            <p class="description"><?php _e('Enter commission percentage for this category.', 'textdomain'); ?></p>
        </td>
    </tr>
    <?php
}

add_action('created_product_cat', 'save_category_commission_field');
add_action('edited_product_cat', 'save_category_commission_field');

function save_category_commission_field($term_id) {
    if (isset($_POST['cat_commission'])) {
        update_term_meta($term_id, 'cat_commission', sanitize_text_field($_POST['cat_commission']));
    }
}


add_action('woocommerce_cart_calculate_fees', 'add_dynamic_category_commission');

function add_dynamic_category_commission($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $total_commission = 0;

    foreach ($cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $line_total = $cart_item['line_total'];

        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);

        foreach ($categories as $cat_id) {
            $commission_rate = get_term_meta($cat_id, 'cat_commission', true);

            if (!empty($commission_rate)) {
                $total_commission += ($line_total * ($commission_rate / 100));
                break; // only apply first found commission category per product
            }
        }
    }

    if ($total_commission > 0) {
        $cart->add_fee(__('Category Commission', 'textdomain'), $total_commission, true);
    }
}

// foreach ( [1, 2] as $index => $product ) {
//     wp_schedule_single_event( time() + ( $index * 10 ), 'insert_single_product', [ $product ] );
// }

// add_action( 'insert_single_product', function( $product ) {
    
// });




// === Includes ===
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-admin.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-api.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/class-mpi-importer.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/helpers.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/tvc-flush-wooocommerce.php';
require_once TVC_MPI_PLUGIN_PATH . 'includes/tvc-product-manipulation.php';


// === Init Plugin ===
add_action('plugins_loaded', function () {
    new MPI_Admin();
    new MPI_API();
    new MPI_Importer();
});



