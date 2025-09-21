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
 * Write messages to a dedicated log file: wp-content/tvc-sync.log
 * Writes to wp-content/tvc-sync.log by default,
 * or wp-content/tvc-sync-<type>.log if $type is provided.
 */
function tvc_sync_log($message, $type = "")
{
    $file = WP_CONTENT_DIR . '/tvc-sync.log';
    if ($type) {
        $file = WP_CONTENT_DIR . '/tvc-sync-' . $type . '.log';
    }
    $time = date('Y-m-d H:i:s');
    error_log("[{$time}] {$message}\n", 3, $file);
}

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
    $cats[] = array('code' => "C0067", 'name' => "Cell Phone Accessories");
    $cats[] = array('code' => "C0037", 'name' => "Cell Phone Cases");
    $cats[] = array('code' => "C0060", 'name' => "Cell Phone Parts");
    $cats[] = array('code' => "C0005", 'name' => "Consumer Electronics");
    $cats[] = array('code' => "C0006", 'name' => "Computer & Networking");
    $cats[] = array('code' => "C0010", 'name' => "Sports & Outdoors");
    $cats[] = array('code' => "C0009", 'name' => "Home & Garden");
    $cats[] = array('code' => "C0004", 'name' => "Car Accessories");
    $cats[] = array('code' => "C0078", 'name' => "Smartwatch Accessories");
    $cats[] = array('code' => "C0077", 'name' => "Jewelry");
    $cats[] = array('code' => "C0092", 'name' => "Smart Publishing Level 1");
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

function ww_tvs_get_product_manufacturer_taxonomy_type()
{
    return 'product_manufacturer';
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

// Custom error logger
if (!function_exists('my_log_error')) {
    function my_log_error($message)
    {
        // Path to wp-content/uploads directory
        $upload_dir = wp_upload_dir();

        // Create a log file with date, e.g., custom-errors-2025-09-12.log
        $date = date('Y-m-d');
        $log_file = trailingslashit($upload_dir['basedir']) . "custom-errors-{$date}.log";

        // Format message with timestamp
        $timestamp = date("Y-m-d H:i:s");
        $formatted_message = "[{$timestamp}] " . print_r($message, true) . "\n";

        // Write to file (append mode)
        error_log($formatted_message, 3, $log_file);
    }
}

// Add admin menu page
add_action('admin_menu', function () {
    add_menu_page(
        'Error Logs',         // Page title
        'Error Logs',         // Menu title
        'manage_options',     // Capability
        'custom-error-logs',  // Menu slug
        'render_error_logs',  // Callback function
        'dashicons-warning',  // Icon
        100                   // Position
    );
});

// Render the error log contents
function render_error_logs()
{
    $upload_dir = wp_upload_dir();
    $log_path = trailingslashit($upload_dir['basedir']);

    // Get all log files starting with custom-errors-
    $files = glob($log_path . 'custom-errors-*.log');

    echo '<div class="wrap"><h1>Error Logs</h1>';

    if (!empty($files)) {
        echo '<h2>Select a log file:</h2>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="custom-error-logs">';
        echo '<select name="log_date">';
        foreach ($files as $file) {
            $filename = basename($file);
            $date = str_replace(array('custom-errors-', '.log'), '', $filename);
            $selected = (isset($_GET['log_date']) && $_GET['log_date'] === $date) ? 'selected' : '';
            echo '<option value="' . esc_attr($date) . '" ' . $selected . '>' . esc_html($date) . '</option>';
        }
        echo '</select> ';
        submit_button('View Log', 'primary', '', false);
        echo '</form>';

        // Display the selected log
        if (isset($_GET['log_date']) && !empty($_GET['log_date'])) {
            $selected_date = sanitize_text_field($_GET['log_date']);
            $selected_file = $log_path . "custom-errors-{$selected_date}.log";

            if (file_exists($selected_file)) {
                $logs = file_get_contents($selected_file);
                echo '<h2>Logs for ' . esc_html($selected_date) . '</h2>';
                echo '<pre style="background:#111;color:#0f0;padding:15px;max-height:600px;overflow:auto;border-radius:8px;">';
                echo esc_html($logs);
                echo '</pre>';

                // Clear log button
                echo '<form method="post" style="margin-top:20px;">';
                echo '<input type="hidden" name="log_date" value="' . esc_attr($selected_date) . '">';
                submit_button('Clear Logs', 'delete', 'clear_logs');
                echo '</form>';

                // Handle clearing logs
                if (isset($_POST['clear_logs']) && current_user_can('manage_options')) {
                    $clear_date = sanitize_text_field($_POST['log_date']);
                    $clear_file = $log_path . "custom-errors-{$clear_date}.log";
                    file_put_contents($clear_file, "");
                    echo '<div class="updated notice"><p>Logs cleared successfully.</p></div>';
                    echo '<script>window.location.reload();</script>';
                }
            } else {
                echo '<p>No logs found for this date.</p>';
            }
        }

    } else {
        echo '<p>No log files found.</p>';
    }

    echo '</div>';
}

// Add a new column to the Products admin list
add_filter('manage_edit-product_columns', 'ww_custom_product_list_column');
function ww_custom_product_list_column($columns)
{
    $columns['update_button'] = 'Action'; // Column header
    return $columns;
}

// Add content to the custom column
add_action('manage_product_posts_custom_column', 'ww_update_product_list_column_content', 10, 2);
function ww_update_product_list_column_content($column, $post_id)
{
    if ($column === 'update_button') {
        $sku = get_post_meta($post_id, '_sku', true);
        $url = admin_url('admin-ajax.php?redirect=true&action=product_fetch&sku=' . $sku);
        echo '<a href="' . esc_url($url) . '" class="button button-primary">Tvc Update</a>';
    }
}

add_action('admin_notices', function () {
    if (isset($_GET['ww_updated']) && $_GET['ww_updated'] == 1) {
        echo '<div class="notice notice-success is-dismissible">
                <p>' . $_GET['msg'] . '</p>
              </div>';
    }
});

// === Includes ===
require __DIR__ . '/includes/views/automate-product-cat.php';
require __DIR__ . '/includes/views/automate-products.php';
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


/**
 * Plugin Name: Assign Product to All Categories
 * Description: Assigns a specific product to every WooCommerce product category (including empty ones).
 */

// Run this once (or hook to an admin action) and then remove/disable the plugin.
add_action('init', function () {
    return;

    // 🔧 Replace with the actual product ID you want to update
    $product_id = 123;  // e.g., 123 is the product's post ID

    if (!$product_id) {
        return;
    }

    // ✅ Get all product categories, including empty ones
    $all_cats = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'fields' => 'ids',     // only need IDs
    ));

    if (empty($all_cats) || is_wp_error($all_cats)) {
        error_log('No product categories found.');
        return;
    }

    // ✅ Assign product to all categories
    wp_set_post_terms($product_id, $all_cats, 'product_cat');

    // Optional: log confirmation
    error_log('Product ' . $product_id . ' assigned to categories: ' . implode(',', $all_cats));
});

function ww_delete_All_prodcuct_cat()
{
    add_action('init', function () {
        if (!current_user_can('manage_woocommerce')) {
            return; // Safety check
        }

        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'fields' => 'ids',
        ));

        if (empty($terms) || is_wp_error($terms)) {
            error_log('No product_cat terms found.');
            return;
        }

        foreach ($terms as $term_id) {
            wp_delete_term($term_id, 'product_cat');
        }

        error_log('All product categories deleted.');
    });
}

// ✅ Enqueue Swiper once globally
function custom_wc_enqueue_swiper() {
    if (!wp_style_is('swiper', 'enqueued')) {
        wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], null);
    }
    if (!wp_script_is('swiper', 'enqueued')) {
        wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', ['jquery'], null, true);
    }
}
add_action('wp_enqueue_scripts', 'custom_wc_enqueue_swiper');

// ✅ Shared JS init
function custom_wc_slider_init_js() { ?>
    <script>
    jQuery(window).on('load', function(){
        jQuery('.swiper.custom-slider').each(function(){
            let el = jQuery(this);
            let next = el.find('.swiper-button-next')[0];
            let prev = el.find('.swiper-button-prev')[0];
            new Swiper(this, {
                loop: false,
                spaceBetween: 20,
                navigation: { nextEl: next, prevEl: prev },
                breakpoints: {
                    320:  { slidesPerView: 2 },
                    768:  { slidesPerView: 3 },
                    1024: { slidesPerView: 4 },
                    1400: { slidesPerView: 5 }
                }
            });
        });
    });
    </script>
<?php }
add_action('wp_footer', 'custom_wc_slider_init_js', 99);

// ✅ Shared styles
function custom_wc_slider_styles() { ?>
    <style>
        /* Slider container */
        .custom-slider { 
            width: 1170px; 
            max-width: 100%; 
            padding: 30px 0; 
            margin-top: 40px; 
            position: relative;
        }

        /* Each card/slide */
        .custom-slide {
            background: #fff; 
            border-radius: 16px; 
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            text-align: center; 
            padding: 20px; 
            transition: transform .3s ease, box-shadow .3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
			text-align: left;
		    max-height: 370px;
        }

        /* Hover effect */
        .custom-slide:hover { 
            transform: translateY(-6px) scale(1.02); 
            box-shadow: 0 12px 24px rgba(0,0,0,0.08); 
        }

        /* Product image */
        .custom-slide img { 
            max-width: 120px; 
            max-height: 120px; 
            margin: 0 auto 15px auto; 
            border-radius: 12px; 
            object-fit: contain;
            transition: transform .3s ease;
        }

        .custom-slide:hover img { 
            transform: scale(1.05); 
        }

        /* Titles */
        .custom-slide h3, 
        .custom-slide h4 { 
            font-size: 16px; 
            color: #222; 
            font-weight: 600; 
            margin: 15px 0 10px;
            line-height: 1.4; 
        }

        /* Price / secondary info */
        .custom-slide p { 
            font-size: 14px; 
            color: #666; 
            margin: 0; 
        }

        /* Swiper buttons */
        .custom-slider .swiper-button-next, 
        .custom-slider .swiper-button-prev {
            width: 44px; 
            height: 44px; 
            background: #fff; 
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            color: #333;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            transition: all .3s ease;
        }

        .custom-slider .swiper-button-next:hover, 
        .custom-slider .swiper-button-prev:hover {
            background: #0073aa; 
            color: #fff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        .custom-slider .swiper-button-next::after, 
        .custom-slider .swiper-button-prev::after { 
            font-size: 20px; 
            font-weight: bold; 
        }

        @media(max-width:768px){
            .custom-slider {padding: 15px 0;}
            .custom-slide {padding: 15px;}
            .custom-slider .swiper-button-prev,
            .custom-slider .swiper-button-next {display:none;}
        }
		
		/* Add to Cart button styling */
        .custom-slide .add-to-cart-btn {
            display: inline-block;
            background: #0073aa; 
            color: #fff; 
            padding: 10px 18px; 
            border-radius: 8px; 
            font-size: 14px; 
            font-weight: 600;
            text-decoration: none; 
            transition: all .3s ease;
        }

        .custom-slide .add-to-cart-btn:hover {
            background: #005f8d; 
            color: #fff; 
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }
		
		.brand-slider .custom-slide {
			text-align: center;
		}
		
		.brand-slider h4 {
			margin: 0;
		}
    </style>
<?php }
add_action('wp_head', 'custom_wc_slider_styles');

// ✅ Products slider
function custom_wc_product_slider_shortcode($atts) {
    $atts = shortcode_atts(['type'=>'recent','limit'=>8], $atts, 'product_slider');

    $args = ['post_type'=>'product','posts_per_page'=>intval($atts['limit']),'post_status'=>'publish'];

    if ($atts['type'] === 'featured') {
        $args['tax_query'][] = ['taxonomy'=>'product_visibility','field'=>'name','terms'=>'featured'];
    } elseif ($atts['type']==='recent') {
        $args['orderby'] = 'date'; $args['order'] = 'DESC';
    }

    $products = new WP_Query($args);
    if(!$products->have_posts()) return '<p>No products found.</p>';

    ob_start(); ?>
    <div class="swiper custom-slider product-slider">
        <h4 style='margin-left:15px;'>Recent Products</h4>
        <div class="swiper-wrapper">
            <?php while($products->have_posts()):$products->the_post();global $product; ?>
                <div class="swiper-slide">
                    <a class="custom-slide" href="<?php the_permalink(); ?>">
                        <?php echo $product->get_image(); ?>
                        <h3 >
							<?php
								$title = get_the_title(); 
								echo wp_trim_words( $title, 20, '...' ); 
							?>
						</h3>
                        <span class="price"><?php echo $product->get_price_html(); ?></span>
						<button href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" 
						   class="add-to-cart-btn" 
						   data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" 
						   data-quantity="1">
						   <?php echo esc_html( $product->add_to_cart_text() ); ?>
						</button>
						
                    </a>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('product_slider','custom_wc_product_slider_shortcode');

// ✅ Brand slider
function custom_wc_brand_slider_shortcode($atts) {
    $atts = shortcode_atts(['taxonomy'=>'product_brand','limit'=>12], $atts,'brand_slider');
    $brands = get_terms(['taxonomy'=>$atts['taxonomy'],'hide_empty'=>false,'number'=>intval($atts['limit'])]);
    if(is_wp_error($brands)||empty($brands)) return '<p>No brands found.</p>';

    ob_start(); ?>
    <div class="swiper custom-slider brand-slider">
        <h4 style='margin-left:15px;'>Shop by Brand</h4>
        <div class="swiper-wrapper">
            <?php foreach($brands as $brand):
                $thumb = get_term_meta($brand->term_id,'thumbnail_id',true);
                $img = $thumb?wp_get_attachment_image_src($thumb,'medium')[0]:wc_placeholder_img_src();
                $link = get_term_link($brand); ?>
                <div class="swiper-slide">
                    <a class="custom-slide" href="<?php echo esc_url($link); ?>">
                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($brand->name); ?>">
                        <h4><?php echo esc_html($brand->name); ?></h4>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('brand_slider','custom_wc_brand_slider_shortcode');

// ✅ Category slider
function custom_wc_category_slider_shortcode($atts){
    $atts = shortcode_atts(['taxonomy'=>'product_cat','parent'=>0,'limit'=>12],$atts,'category_slider');
    $categories = get_terms(['taxonomy'=>$atts['taxonomy'],'hide_empty'=>false,'number'=>intval($atts['limit']),'parent'=>intval($atts['parent'])]);
    if(is_wp_error($categories)||empty($categories)) return '<p>No categories found.</p>';

    ob_start(); ?>
    <div class="swiper custom-slider category-slider">
        <h4 style='margin-left:15px;'>Shop by Category</h4>
        <div class="swiper-wrapper">
            <?php foreach($categories as $cat):
                if($cat->term_id==947) continue; //skip
                $thumb=get_term_meta($cat->term_id,'thumbnail_id',true);
                $img=$thumb?wp_get_attachment_image_src($thumb,'medium')[0]:wc_placeholder_img_src();
                $link=get_term_link($cat); ?>
                <div class="swiper-slide">
                    <a class="custom-slide" href="<?php echo esc_url($link); ?>">
                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($cat->name); ?>">
                        <h4><?php echo esc_html($cat->name); ?></h4>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('category_slider','custom_wc_category_slider_shortcode');


// Logging import batches and errors
function start_import_batch($batch_id) {
    global $wpdb;
    $batches_table = $wpdb->prefix . 'tvc_import_batches';

    // Sanitize batch_id first
    $batch_id = sanitize_text_field($batch_id);

    // Check if batch_id already exists
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM $batches_table WHERE batch_id = %s",
            $batch_id
        )
    );

    if ($exists > 0) {
        // Batch already exists — ignore or maybe return something
        return false; // or return 'exists'
    }

    // Insert new batch_id
    $wpdb->insert(
        $batches_table,
        [
            'batch_id' => $batch_id,
            'created_at' => current_time('mysql') // optional: timestamp
        ],
        [
            '%s',
            '%s'
        ]
    );

    return true; // inserted
}

function add_import_error_log($batch_id, $state, $error_log, $type) {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'tvc_import_logs';
    start_import_batch($batch_id);

    $wpdb->insert(
        $logs_table,
        [
            'batch_id' => $batch_id,
            'status' => maybe_serialize($state),
            'success_skus' => maybe_serialize($error_log),
            'type' => $type,
        ],
        [
            '%s',
            '%s',
            '%s',
            '%s'
        ]
    );
}

// Add custom field for Minimum Order Qty in product backend
add_action('woocommerce_product_options_inventory_product_data', 'add_min_order_qty_field');
function add_min_order_qty_field() {
    woocommerce_wp_text_input( array(
        'id' => '_min_order_qty',
        'label' => __('Minimum Order Quantity', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array('min' => '1', 'step' => '1'),
        'desc_tip' => true,
        'description' => __('Enter the minimum quantity allowed for this product.', 'woocommerce')
    ));
}

// Save the field value
add_action('woocommerce_process_product_meta', 'save_min_order_qty_field');
function save_min_order_qty_field($post_id) {
    $min_qty = isset($_POST['_min_order_qty']) ? absint($_POST['_min_order_qty']) : '';
    update_post_meta($post_id, '_min_order_qty', $min_qty);
}

// Enforce minimum quantity
add_filter('woocommerce_add_to_cart_quantity', 'set_minimum_order_quantity', 10, 2);
function set_minimum_order_quantity($quantity, $product_id) {
    $min_qty = get_post_meta($product_id, '_min_order_qty', true);
    if (!empty($min_qty) && $quantity < $min_qty) {
        wc_add_notice(sprintf(__('The minimum quantity for %s is %d.', 'woocommerce'), get_the_title($product_id), $min_qty), 'error');
        return $min_qty; // adjust automatically
    }
    return $quantity;
}

add_filter('woocommerce_product_stock_status_options', function($status_options) {
    $status_options['on_sale'] = __('On Sale', 'woocommerce');
    $status_options['in_shortage'] = __('In Shortage', 'woocommerce');
    $status_options['5_7_days'] = __('5-7 Days', 'woocommerce');
    $status_options['7_10_days'] = __('7-10 Days', 'woocommerce');
    return $status_options;
});

// Display custom stock status on product page
add_filter('woocommerce_get_stock_html', function($html, $product) {
    $status = $product->get_stock_status();
    if ($status == 'on_sale') return '<p class="stock in-shortage">On Sale</p>';
    if ($status == 'in_shortage') return '<p class="stock in-shortage">In Shortage</p>';
    if ($status == '5_7_days') return '<p class="stock on-order">5-7 Days</p>';
    if ($status == '7_10_days') return '<p class="stock on-order">7-10 Days</p>';
    return $html;
}, 10, 2);

add_action('rest_api_init', function () {
    register_rest_route('mpi/v1', '/fetch-by-date-time', [
        'methods' => 'GET', // or 'GET' if you prefer
        'callback' => 'mpi_fetch_by_date_time_callback',
        'permission_callback' => '__return_true', // restrict if needed
    ]);
});


function mpi_fetch_by_date_time_callback(WP_REST_Request $request) {
    // Optional: accept params (perPage, batch_id, etc.)
    $perPage = $request->get_param('per_page') ?: 10;
    $maxPages = $request->get_param('max_pages') ?: 1;

    // Your existing importer + API
    $importer = new MPI_Importer();
    $api = new MPI_API();

    $lastProductId = null;
    $pageIndex = 1;

    // Current date/time in WP timezone
    $current_time = current_time('timestamp');

    // Date window (last 15 minutes)
    $beginDate = date('Y-m-d\TH:i:s', $current_time - (15 * 60));
    $endDate   = date('Y-m-d\TH:i:s', $current_time);

    $allProducts = [];

    do {
        $products = $api->get_products_by_category_code(
            null,
            $lastProductId,
            $perPage,
            $pageIndex,
            $beginDate,
            $endDate
        );

        $importer->ww_update_detail_of_products($products);

        $allProducts[] = $products; // store for response

        $lastProductId = $products['lastProductId'] ?? null;
        $pageIndex++;
    } while ($pageIndex <= $maxPages);

    return rest_ensure_response([
        'status' => 'success',
        'beginDate' => $beginDate,
        'endDate' => $endDate,
        'data' => $allProducts,
    ]);
}


//ww_delete_All_prodcuct_cat();
//add_action( 'woocommerce_before_main_content', function() {
//    if ( is_shop() ) {
//        wp_list_categories( array(
//            'taxonomy'   => 'product_cat',
//            'hide_empty' => false,  // 🔑 show empty categories
//            'title_li'   => '',
//        ) );
//    }
//}, 5 );

