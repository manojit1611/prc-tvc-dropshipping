<?php

function make_products_hierarchical( $args, $post_type ) {
    if ( 'product' === $post_type ) {
        $args['hierarchical'] = true;
    }
    return $args;
}
add_filter( 'register_post_type_args', 'make_products_hierarchical', 10, 2 );


/**
 * WooCommerce: Use custom product meta (_tvc_image_url) as the main product image
 */

// Override WooCommerce product image ID (force external URL)
add_filter('woocommerce_product_get_image_id', 'ww_tvc_replace_product_image_id', 10, 2);
add_filter('woocommerce_product_get_gallery_image_ids', '__return_empty_array'); // remove galleries if needed

function ww_tvc_replace_product_image_id($image_id, $product)
{
    // Force WooCommerce to think there is no image, so it falls back
    return 0;
}

// Replace WooCommerce placeholder with custom external URL from meta
add_filter('woocommerce_placeholder_img_src', 'ww_tvc_global_placeholder', 10, 1);

function ww_tvc_global_placeholder($src)
{
    global $product;

    if ($product && is_a($product, 'WC_Product')) {
        $custom_url = $product->get_meta('_tvc_image_url');
        if (!empty($custom_url)) {
            return esc_url($custom_url);
        }
    }

    return $src;
}


/**
 * WooCommerce: Replace product image with external URL from _tvc_image_url
 * Only replace if meta exists, otherwise keep default behavior.
 */

// 1. Replace product image HTML (loops, single product, cart, etc.)
add_filter('woocommerce_product_get_image', 'ww_tvc_replace_product_get_image', 10, 5);
function ww_tvc_replace_product_get_image($html, $product, $size, $attr, $placeholder)
{
    $custom_url = $product->get_meta('_tvc_image_url');

    if (!empty($custom_url)) {
        $alt = esc_attr($product->get_name());

        return sprintf(
            '<img src="%s" alt="%s" class="wp-post-image" />',
            esc_url($custom_url),
            $alt
        );
    }

    return $html;
}

// add_filter('woocommerce_product_get_gallery_image_ids', 'ww_tvc_replace_product_get_extra_images', 10, 2);
// function ww_tvc_replace_product_get_extra_images($ids, $product) {
//     $external_urls = get_post_meta($product->get_id(), '_tvc_extra_image_urls', true);
//     if ($external_urls) {
//         return explode(',', $external_urls); // store as CSV in meta
//     }
//     return $ids;
// };

// 2. Replace the image src for functions like get_the_post_thumbnail_url() on single product
add_filter('wp_get_attachment_url', 'ww_tvc_replace_attachment_url', 10, 2);
function ww_tvc_replace_attachment_url($url, $post_id)
{
    if (get_post_type($post_id) === 'product') {
        $product = wc_get_product($post_id);
        if ($product) {
            $custom_url = $product->get_meta('_tvc_image_url');
            if (!empty($custom_url)) {
                return esc_url($custom_url);
            }
        }
    }
    return $url;
}

add_filter('woocommerce_single_product_image_thumbnail_html', function($html, $attachment_id) {
    $product_id = get_the_ID();
    $gallery_urls = get_post_meta($product_id, '_tvc_extra_image_urls', true);
    $main_url = get_post_meta($product_id, '_tvc_image_url', true);

    if ($gallery_urls) {
        $urls = explode(',', $gallery_urls);
        $html = '';
        ?>
            <style>
                .gallery {
                    display:flex;
                    grid-gap:10px;
                    /* justify-content:space-between; */
                    flex-wrap:wrap;
                }
                
                .custom-woocommerce-gallery {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                /* Main Image */
                .custom-woocommerce-gallery .main-image img {
                    width: 100%;
                    max-width: 500px;
                    border-radius: 5px;
                    border: 1px solid #ddd;
                }

                /* Thumbnails */
                .custom-woocommerce-gallery .gallery-thumbnails {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .custom-woocommerce-gallery .gallery-thumbnails .thumb-box {
                    width: 60px;
                    height: 60px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    overflow: hidden;
                    cursor: pointer;
                    transition: 0.2s;
                }

                .custom-woocommerce-gallery .gallery-thumbnails .thumb-box img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                }

                .custom-woocommerce-gallery .gallery-thumbnails .thumb-box:hover {
                    border-color: #000;
                }

            </style>

            <script>
                jQuery(document).ready(function ($) {
                    $('.gallery-thumbnails .thumb-box img').on('click', function(e){
                        e.preventDefault();
                        
                        // Get clicked image URL
                        var newSrc = $(this).attr('src');
    
                        // Update main image
                        $('#main-gallery-image').attr('src', newSrc);
                    });
                });
            </script>
            
            <div class="main-image">
                <div class="thumb-box">
                    <a href="<?php echo esc_url($main_url); ?>" class="gallery-lightbox">
                        <img src="<?php echo esc_url($main_url); ?>" alt="Gallery Image" id='main-gallery-image'>
                    </a>
                </div>
            </div>
            <div class='gallery'>
        <?php

        foreach ($urls as $key => $url) {
            if ($key == 0) continue;
            ?>
                <div class="custom-woocommerce-gallery">
                    <div class="gallery-thumbnails">
                        <div class="thumb-box">
                            <a href="<?php echo esc_url($url); ?>" class="gallery-lightbox">
                                <img src="<?php echo esc_url($url); ?>" alt="Gallery Image" class='thumb' style='width:120px;'>
                            </a>
                        </div>
                    </div>
                </div>
            <?php
        }
        ?>
            </div>
        <?php
    }

    return $html;
}, 10, 2);



