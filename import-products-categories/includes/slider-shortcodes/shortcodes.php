<?php
/**
 * WooCommerce Owl Carousel Slider & Tabs
 * Single slider version using ob_start() / ob_get_clean()
 */

/* ---------------------------------------------
 * Enqueue Owl Carousel Scripts & Styles
 * --------------------------------------------- */
function enqueue_owl_carousel()
{
    wp_enqueue_style('owl-carousel', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css');
    wp_enqueue_style('owl-theme-default', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css');
    wp_enqueue_script('owl-carousel', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js', ['jquery'], null, true);
}

add_action('wp_enqueue_scripts', 'enqueue_owl_carousel');

/* ---------------------------------------------
 * Shared JS for Woo Sliders
 * --------------------------------------------- */
function custom_wc_slider_init_js()
{
    ob_start(); ?>
    <script>
        jQuery(document).ready(function ($) {

            $('.woo-owl-slider ul.products').addClass('owl-carousel owl-theme');


            //  Woo Product Categories Slider
            $('.woo-owl-slider-categories ul.products').addClass('owl-carousel owl-theme').owlCarousel({
                loop: true,
                margin: 10,
                nav: false,
                dots: true,
                responsive: {
                    0: {
                        items: 2
                    },
                    600: {
                        items: 4
                    },
                    768: {
                        items: 6
                    },
                    1000: {
                        items: 8
                    }
                }
            });

            // Woo Brand Slider
            $('.woo-owl-brand-slider').owlCarousel({
                loop: true,
                margin: 10,
                nav: false,
                dots: false,
                items: 1,
            });


            $('.woo-owl-slider ul.products').owlCarousel({
                loop: true,
                margin: 20,
                nav: true,
                dots: false,
                responsive: {
                    0: {items: 2},
                    600: {items: 3},
                    992: {items: 4},
                    1200: {items: 6}
                }
            });


            $('.fullwidth-banner-slider .owl-carousel').owlCarousel({
                items: 1,
                loop: true,
                margin: 0,
                nav: true,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
            });
        });
    </script>
    <?php
    echo ob_get_clean();
}

add_action('wp_footer', 'custom_wc_slider_init_js', 99);

/* ---------------------------------------------
 * Shared Styles
 * --------------------------------------------- */
function custom_wc_slider_styles()
{
    ob_start(); ?>
    <style>


        .owl-carousel .owl-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .owl-carousel .owl-nav button {
            pointer-events: all;
            background: #333;
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            transition: background-color .3s;
        }

        .owl-carousel .owl-nav button:hover {
            background: #555;
        }

        .custom-tabs-wrapper {
            max-width: 100%;
            margin-top: 30px;
            font-family: inherit;
        }

        .custom-tabs-nav {
            list-style: none;
            padding: 0;
            display: flex;
            margin-bottom: 0;
            border-bottom: 2px solid #eee;
            flex-wrap: wrap;
        }

        .custom-tabs-nav li {
            padding: 12px 25px;
            cursor: pointer;
            background: #f9f9f9;
            margin-right: 5px;
            border-radius: 6px 6px 0 0;
            font-weight: 600;
            color: #444;
            transition: all .3s;
        }

        .custom-tabs-nav li:hover, .custom-tabs-nav li.active {
            background: #0073aa;
            color: #fff;
        }

        .custom-tabs-content {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 0 6px 6px 6px;
            background: #fff;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            animation: fadeIn .3s ease;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    <?php
    echo ob_get_clean();
}

add_action('wp_head', 'custom_wc_slider_styles');

/* ---------------------------------------------
 * Shortcodes with ob_start / ob_get_clean
 * --------------------------------------------- */

// Recent products
function custom_wc_product_slider_shortcode($atts)
{
    ob_start(); ?>
    <div class="woo-owl-slider woo-products ww-product-section">
        <div class="ww-product-section__entry-heading">
            <h4><?php echo __('Recent Products') ?></h4>
        </div>
        <div class="ww-product-section__items-area">
            <?php echo do_shortcode('[recent_products per_page="8" columns="4"]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('recent_product_slider', 'custom_wc_product_slider_shortcode');

// Best selling products
function custom_wc_best_seller_slider_shortcode($atts)
{
    ob_start(); ?>
    <div class="woo-owl-slider woo-products ww-product-section">
        <div class="ww-product-section__entry-heading">
            <h4>Best Selling Products</h4>
        </div>
        <div class="ww-product-section__items-area">
            <?php echo do_shortcode('[best_selling_products per_page="8" columns="4"]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('best_seller_product_slider', 'custom_wc_best_seller_slider_shortcode');

// New arrivals
function custom_new_arrival_slider_shortcode($atts)
{
    $loop = new WP_Query(['post_type' => 'product', 'posts_per_page' => 8, 'post_status' => 'publish']);
    ob_start(); ?>
    <div class="woo-owl-slider woo-products ww-product-section">
        <div class="ww-product-section__entry-heading">
            <h4>New Arrivals</h4>
        </div>
        <div class="ww-product-section__items-area">
            <?php
            echo do_shortcode('[products limit="8" columns="4" orderby="id" order="DESC" visibility="visible"]');
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('new_arrival', 'custom_new_arrival_slider_shortcode');

// Featured categories
function custom_wc_category_slider_shortcode($atts)
{
    ob_start(); ?>
    <div class="woo-owl-slider-categories ww-product-section">
        <div class="ww-product-section__entry-heading">
            <h4>Featured Categories</h4>
        </div>
        <div class="ww-product-section__items-area">
            <?php echo do_shortcode('[product_categories limit=8]'); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('featured_category_slider', 'custom_wc_category_slider_shortcode');

// Specific category
function custom_wc_specific_category_slider_shortcode($atts)
{
    $atts = shortcode_atts(['category' => '', 'title' => 'Featured Categories'], $atts, 'specific_category_slider');
    ob_start();
    if (empty($atts['category'])) {
        echo '<p>Please provide a category.</p>';
    } else { ?>
        <div class="woo-owl-slider woo-products">
            <h4><?php echo esc_html($atts['title']); ?></h4>
            <?php echo do_shortcode('[product_category category="' . esc_attr($atts['category']) . '"]'); ?>
        </div>
    <?php }
    return ob_get_clean();
}

add_shortcode('specific_category_slider', 'custom_wc_specific_category_slider_shortcode');


// Fullwidth banner slider
// Shortcode to display all WooCommerce brands
function custom_wc_all_brands_shortcode($atts)
{
    $atts = shortcode_atts([
        'taxonomy' => 'product_brand', // change if your brand taxonomy is different
        'title' => 'All Brands',
        'columns' => 6, // number of items per row
        'rows' => 2     // number of rows per slide
    ], $atts, 'all_brands');

    $brands = get_terms([
        'taxonomy' => $atts['taxonomy'],
        'hide_empty' => false
    ]);

    ob_start();

    if (!empty($brands) && !is_wp_error($brands)) { ?>
        <div class="ww-brands-section">
            <h4><?php echo esc_html($atts['title']); ?></h4>
            <ul class="woo-owl-brand-slider owl-carousel owl-theme ww-brands-row">
                <?php
                // split brands into slides (columns * rows per slide)
                foreach (array_chunk($brands, $atts['columns'] * $atts['rows']) as $slide): ?>
                    <li class="item">
                        <div class="brand-slide">
                            <?php
                            // split each slide into rows
                            foreach (array_chunk($slide, $atts['columns']) as $row): ?>
                                <div class="brand-row">
                                    <?php foreach ($row as $brand):
                                        $thumb_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                                        $img_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : wc_placeholder_img_src();
                                        $link = get_term_link($brand); ?>
                                        <div class="brand-item">
                                            <a href="<?php echo esc_url($link); ?>">
                                                <img src="<?php echo esc_url($img_url); ?>"
                                                     alt="<?php echo esc_attr($brand->name); ?>"
                                                     style="max-width:100%;">
                                                <h5><?php echo esc_html($brand->name); ?></h5>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php } else {
        echo '<p>No brands found.</p>';
    }

    return ob_get_clean();
}

add_shortcode('all_brands', 'custom_wc_all_brands_shortcode');


// Custom tabs
function custom_tabs_shortcode($atts, $content = null)
{
    $tabs_id = 'custom-tabs-' . rand(1000, 9999);
    ob_start(); ?>
    <div id="<?php echo esc_attr($tabs_id); ?>" class="custom-tabs-wrapper">
        <ul class="custom-tabs-nav">
            <li class="active" data-tab="tab1">Best Selling Products</li>
            <li data-tab="tab2">New Arrivals</li>
        </ul>
        <div class="custom-tabs-content">
            <div class="tab-content active"
                 id="tab1"><?php echo do_shortcode('[best_seller_product_slider per_page="4" columns="4"]'); ?></div>
            <div class="tab-content"
                 id="tab2"><?php echo do_shortcode('[new_arrival per_page="4" columns="4"]'); ?></div>
        </div>
    </div>
    <script>
        jQuery(document).ready(function ($) {
            $('#<?php echo esc_js($tabs_id); ?> .custom-tabs-nav li').click(function () {
                var tab = $(this).data('tab');
                var wrapper = $(this).closest('.custom-tabs-wrapper');
                wrapper.find('.custom-tabs-nav li').removeClass('active');
                $(this).addClass('active');
                wrapper.find('.tab-content').removeClass('active');
                wrapper.find('#' + tab).addClass('active');
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('custom_tabs', 'custom_tabs_shortcode');

/* ---------------------------------------------
 * Available Shortcodes
 * ---------------------------------------------
 * [recent_product_slider]
 * [best_seller_product_slider]
 * [new_arrival]
 * [featured_category_slider]
 * [specific_category_slider category="category-slug" title="Custom Title"]
 * [brand_slider taxonomy="product_brand" title="Our Brands" limit="24" per_row="6" rows="2"]
 * [banner_slider ids="1,2,3"]
 * [custom_tabs]
 */
