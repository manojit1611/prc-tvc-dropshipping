<?php

function enqueue_owl_carousel() {
    // Owl Carousel CSS + JS (CDN)
    wp_enqueue_style( 'owl-carousel', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css' );
    wp_enqueue_style( 'owl-theme-default', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css' );
    wp_enqueue_script( 'owl-carousel', 'https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js', array('jquery'), null, true );
}
add_action( 'wp_enqueue_scripts', 'enqueue_owl_carousel' );


// ✅ Shared JS init
function custom_wc_slider_init_js()
{ ?>
	<script>
		jQuery(document).ready(function($) {
			// Target the <ul> inside your wrapper
			$('.woo-owl-slider ul.products').addClass('owl-carousel');
			$('.woo-owl-slider ul.products').addClass('owl-loaded');
			

			// Convert each product <li> into Owl slide
			$('.woo-owl-slider ul.products li').addClass('item');

			// Init Owl
			$('.woo-owl-slider ul.products').owlCarousel({
				loop: true,
				margin: 20,
				nav: true,
				dots: false,
				responsive:{
					0:{ items:3 },
					600:{ items:2 },
					800: {items: 3},
					1200:{ items:5 }
				}
			});
		});
	</script>

<?php }

add_action('wp_footer', 'custom_wc_slider_init_js', 99);

// ✅ Shared styles
function custom_wc_slider_styles()
{ ?>
    <style>
        /* Slider container */
		.woo-owl-slider .products {
			grid-template-columns: inherit !important;
		}
		
		.woo-owl-slider ul li {
			background-color: white;
		    border-radius: 10px;
		}
		
		/* Position the arrows */
		.owl-carousel .owl-nav {
		  position: absolute;
		  top: 50%;
		  width: 100%;
		  display: flex;
		  justify-content: space-between;
		  transform: translateY(-50%);
		  pointer-events: none; /* so container doesn’t block clicks */
		}

		.owl-carousel .owl-nav button.owl-prev,
		.owl-carousel .owl-nav button.owl-next {
		  pointer-events: all; /* enable clicks */
		  background-color: #333; /* arrow background */
		  color: #fff; /* arrow color */
		  border: none;
		  width: 40px;
		  height: 40px;
		  border-radius: 50%;
		  font-size: 20px;
		  line-height: 40px;
		  display: flex;
		  align-items: center;
		  justify-content: center;
		  cursor: pointer;
		  transition: background-color 0.3s ease;
		  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
		}

		.owl-carousel .owl-nav button.owl-prev:hover,
		.owl-carousel .owl-nav button.owl-next:hover {
		  background-color: #555; /* hover color */
		}

		.owl-carousel .owl-nav button span {
		  font-size: 24px;
		  line-height: 1;
		}
		
		/* CSS – Add to your theme or customizer */
		.woo-owl-slider .products {
			opacity: 0;               /* Hide initially */
			transition: opacity 0.3s; /* Smooth fade in */
		}
		.woo-owl-slider .products.owl-loaded {
			opacity: 1; /* Show after Owl finishes */
		}
		
		.woo-products ul li {
			max-height: 400px;
			height: 400px;
			padding: 20px !important;
		}
		
		.categories ul li h2 {
			bottom: 0px !important;
		}
		
		@media only screen and (min-width: 500px) {
			.category_desktop {
				display:block !important;
			}
		}
		
		@media only screen and (max-width: 500px) {
			.woo-owl-slider {
				 width: 400px !important;
			}
			
			.woo-owl-slider .woocommerce ul li {
				height: 265px;
			    padding: 10px !important;
			}
			
			.woo-owl-slider .woocommerce ul li .button {
				padding: 10px;
			}
			
			.custom-tabs-wrapper {
				display: none;
			}
			
			.categories {
				width: 400px;
			}
			
			.categories .product-category {
				margin-bottom: 0px !important;
			}
			
			.brand-slider ul li {
				height: 350px !important;
			}
			
			.category_mobile {
				display:block !important;
			}
			
			.owl-prev, .owl-next {
				display: none !important;	
			}
			
			.brand-slider .owl-carousel {
				height: 350px;
			}
			
			.brand-slider .mobile {
				display: block !important;
			}
		}
		
		@media only screen and (min-width: 481px) and (max-width: 768px) {
			.woo-owl-slider {
				 width: 740px !important;
			}
			
			.woo-owl-slider .woocommerce ul li {
				height: 490px;
				max-height: 500px;
			}
			
			.custom-tabs-wrapper {
				width: 740px;
			}
			
			.tab-content ul li {
				width: 325px !important;
				max-height: 500px;
		        height: 455px;
			}
			
			.categories {
				width: 740px;
			}
			
			.brand-slider ul li {
				height: 435px !important;
			}
			
			.owl-prev, .owl-next {
				display: none !important;	
			}
		}
		
		@media only screen and (min-width: 769px) and (max-width: 1024px) {
			.woo-owl-slider {
				 width: 980px !important;
			}
			
			.custom-tabs-wrapper {
				width: 980px;
			}
			
			.woo-products ul li {
				height: 310px;
			}
			
			.brand-slider .mobile {
				display: block;
			}
		}
		
		@media only screen and (min-width: 1024px) {
			.brand-slider .desktop {
				display: block !important;
			}
		}
		
		@media only screen and (min-width: 768px) {
			.brand-slider .desktop {
				display: block !important;
			}
		}
    </style>
<?php }

add_action('wp_head', 'custom_wc_slider_styles');

// ✅ Products slider
function custom_wc_product_slider_shortcode($atts)
{
	?>
	<div class="woo-owl-slider woo-products">
		<h4>Recent Products</h4>
		<?php echo do_shortcode('[recent_products per_page="8" columns="4"]'); ?>
	</div>
<?php
}

add_shortcode('recent_product_slider', 'custom_wc_product_slider_shortcode');

// ✅ Products slider
function custom_wc_best_seller_slider_shortcode($atts)
{
	?>
	<div class="woo-owl-slider woo-products">
		<h4>Best Selling Products</h4>
		<?php echo do_shortcode('[best_selling_products per_page="8" columns="4"]'); ?>
	</div>
<?php
}

add_shortcode('best_seller_product_slider', 'custom_wc_best_seller_slider_shortcode');

// ✅ Brand slider
function custom_new_arrival_slider_shortcode($atts)
{
    $args = [
		'post_type' => 'product',
		'posts_per_page' => 8,
// 		'orderby' => 'date',
// 		'order' => 'DESC',
		'post_status' => 'publish'
	];

	$loop = new WP_Query($args);
	
	echo '<div class="woo-owl-slider woo-products">';
	echo "<h4>New Arrivals</h4>";
	if($loop->have_posts()) {
		echo '<ul class="products">';
		while($loop->have_posts()): $loop->the_post();
			wc_get_template_part('content', 'product');
		endwhile;
		echo '</ul>';
	} else {
		echo 'No new products found';
	}
	echo '</div>';
}

add_shortcode('new_arrival', 'custom_new_arrival_slider_shortcode');

// ✅ Category slider
function custom_wc_category_slider_shortcode($atts)
{
	?>
    <div class="woo-owl-slider categories category_desktop" style="margin-top: 15px;display:none;">
		<h4>Featured Categories</h4>
		<?php echo do_shortcode('[product_categories limit=6 ]'); ?>
	</div>

	<div class="categories category_mobile" style="margin-top: 15px;display:none;">
		<h4>Featured Categories</h4>
		<?php echo do_shortcode('[product_categories limit=6 ]'); ?>
	</div>
	<?php
}
add_shortcode('featured_category_slider', 'custom_wc_category_slider_shortcode');

// ✅ Category slider
function custom_wc_specific_category_slider_shortcode($atts) {
    $atts = shortcode_atts([
        'category' => '', // default empty
        'title'    => 'Featured Categories', // optional title
    ], $atts, 'specific_category_slider');

    if (empty($atts['category'])) return '<p>Please provide a category.</p>';
    ?>
    <div class="woo-owl-slider woo-products">
        <h4><?php echo esc_html($atts['title']); ?></h4>
        <?php 
            // Display WooCommerce category shortcode dynamically
            echo do_shortcode('[product_category category="' . esc_attr($atts['category']) . '"]'); 
        ?>
    </div>
    <?php
}
add_shortcode('specific_category_slider', 'custom_wc_specific_category_slider_shortcode');

function custom_wc_brand_slider_shortcode($atts) {
    $atts = shortcode_atts([
        'taxonomy' => 'product_brand', 
        'title'    => 'Our Brands',
        'limit'    => 24, // multiples of 8 recommended
        'per_row_desktop'  => 6,
        'per_row_mobile'  => 3,
        'rows'     => 2
    ], $atts, 'brand_slider');

    $brands = get_terms([
        'taxonomy'   => $atts['taxonomy'],
        'hide_empty' => false,
        'number'     => intval($atts['limit']),
    ]);

    if (empty($brands) || is_wp_error($brands)) return '<p>No brands found.</p>';

    $desktop_per_slide = $atts['per_row_desktop'] * $atts['rows'];
    $desktopChunks = array_chunk($brands, $desktop_per_slide);
	
	$mobile_per_slide = $atts['per_row_mobile'] * $atts['rows'];
    $mobile_chunks = array_chunk($brands, $mobile_per_slide);

    ob_start(); ?>
    <div class="woo-owl-slider brand-slider">
        <h4><?php echo esc_html($atts['title']); ?></h4>
        <div class="woocommerce columns-4">
            <ul class="owl-carousel products desktop" style='display:none;';>
                <?php foreach ($desktopChunks as $chunk): ?>
                    <li class="item">
                        <div class="brand-slide " style="padding: 20px;">
                            <?php 
                            $row_chunks = array_chunk($chunk, $atts['per_row_desktop']);
                            foreach ($row_chunks as $row): ?>
                                <div class="brand-row" style="display:flex; justify-content:center; gap:15px; margin-bottom:15px;">
                                    <?php foreach ($row as $brand):
                                        $thumb_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                                        $img_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : wc_placeholder_img_src();
                                        $link = get_term_link($brand);
                                    ?>
                                        <div class="brand-item" style="text-align:center; width:200px;">
                                            <a href="<?php echo esc_url($link); ?>">
                                                <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" style="max-width:100%;">
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
			
			<ul class="owl-carousel products mobile" style='display:none;';>
                <?php foreach ($mobile_chunks as $chunk): ?>
                    <li class="item">
                        <div class="brand-slide " style="padding: 20px;">
                            <?php 
                            $row_chunks = array_chunk($chunk, $atts['per_row_mobile']);
                            foreach ($row_chunks as $row): ?>
                                <div class="brand-row" style="display:flex; justify-content:center; gap:15px; margin-bottom:15px;">
                                    <?php foreach ($row as $brand):
                                        $thumb_id = get_term_meta($brand->term_id, 'thumbnail_id', true);
                                        $img_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : wc_placeholder_img_src();
                                        $link = get_term_link($brand);
                                    ?>
                                        <div class="brand-item" style="text-align:center; width:200px;">
                                            <a href="<?php echo esc_url($link); ?>">
                                                <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" style="max-width:100%;">
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
    </div>

    <script>
    jQuery(document).ready(function($){
        $('.woo-owl-slider .owl-carousel').owlCarousel({
            items:1, // because each slide contains 2 rows × 4 columns already
            loop:true,
            margin:15,
            nav:true,
            dots:false,
            autoplay:false,
            navText:['‹','›']
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('brand_slider', 'custom_wc_brand_slider_shortcode');





function custom_fullwidth_banner_slider_shortcode($atts) {
    $atts = shortcode_atts([
        'ids' => '', // comma separated attachment IDs
    ], $atts, 'banner_slider');

    if (empty($atts['ids'])) return '<p>No banners provided.</p>';

    $ids = array_map('intval', explode(',', $atts['ids']));
    if (empty($ids)) return '<p>No valid banner IDs.</p>';
    ?>

    <div class="fullwidth-banner-slider">
        <div class="owl-carousel" style="max-width: inherit;">
            <?php foreach ($ids as $id):
                $img_url = wp_get_attachment_image_url($id, 'full');
                if (!$img_url) continue;
            ?>
                <div class="item">
                    <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr(get_the_title($id)); ?>" style="width:100%; display:block;">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($){
            $('.fullwidth-banner-slider .owl-carousel').owlCarousel({
                items: 1,
                loop: true,
                margin: 0,
                nav: true,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
                navText: ['‹','›'],
            });
        });
    </script>

    <style>
        .fullwidth-banner-slider .owl-nav button.owl-prev,
        .fullwidth-banner-slider .owl-nav button.owl-next {
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 30px;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
        }
        .fullwidth-banner-slider .owl-nav button.owl-prev { left: 10px; }
        .fullwidth-banner-slider .owl-nav button.owl-next { right: 10px; }
    </style>

<?php
}
add_shortcode('banner_slider', 'custom_fullwidth_banner_slider_shortcode');

function custom_tabs_shortcode($atts, $content = null) {
    $tabs_id = 'custom-tabs-' . rand(1000,9999); // unique ID for multiple instances
    $content = do_shortcode($content); // allow shortcodes inside tabs

    ob_start(); ?>
    
    <div id="<?php echo esc_attr($tabs_id); ?>" class="custom-tabs-wrapper">
		<ul class="custom-tabs-nav">
			<li class="active" data-tab="tab1">Best Selling Products</li>
			<li data-tab="tab2">New Arrivals</li>
		</ul>
		<div class="custom-tabs-content">
			<div class="tab-content active" id="tab1">
				<?php echo do_shortcode('[best_seller_product_slider per_page="4" columns="4"]'); ?>
			</div>
			<div class="tab-content" id="tab2">
				<?php echo do_shortcode('[new_arrival per_page="4" columns="4"]'); ?>
			</div>
		</div>
	</div>

	<style>
	/* Wrapper */
	.custom-tabs-wrapper {
		max-width: 100%;
		margin-top: 30px;
		font-family: inherit;
	}

	/* Tabs navigation */
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
		position: relative;
		transition: all .3s ease;
	}

	.custom-tabs-nav li:hover {
		background: #0073aa;
		color: #fff;
	}

	.custom-tabs-nav li.active {
		background: #0073aa;
		color: #fff;
		box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
	}

	/* Tabs content */
	.custom-tabs-content {
		border: 1px solid #ddd;
		padding: 20px;
		border-radius: 0 6px 6px 6px;
		box-shadow: 0 3px 10px rgba(0,0,0,0.05);
		background: #fff;
		animation: fadeIn .3s ease;
	}
		
		.custom-tabs-content ul li {
			margin-bottom: 0px !important;
			padding: 10px !important;
		}

	.tab-content {
		display: none;
	}

	.tab-content.active {
		display: block;
	}

	/* Subtle fade-in animation for content */
	@keyframes fadeIn {
		from {opacity: 0; transform: translateY(5px);}
		to {opacity: 1; transform: translateY(0);}
	}
	</style>

	<script>
	jQuery(document).ready(function($){
		$('.custom-tabs-nav li').click(function(){
			var tab_id = $(this).data('tab');
			var wrapper = $(this).closest('.custom-tabs-wrapper');

			wrapper.find('.custom-tabs-nav li').removeClass('active');
			$(this).addClass('active');

			wrapper.find('.tab-content').removeClass('active');
			wrapper.find('#'+tab_id).addClass('active');
		});
	});
	</script>


    <script>
        jQuery(document).ready(function($){
            $('#<?php echo esc_js($tabs_id); ?> .custom-tabs-nav li').click(function(){
                var tab = $(this).data('tab');
                var wrapper = $(this).closest('.custom-tabs-wrapper');
                
                wrapper.find('.custom-tabs-nav li').removeClass('active');
                $(this).addClass('active');
                
                wrapper.find('.tab-content').removeClass('active');
                wrapper.find('#'+tab).addClass('active');
            });
        });
    </script>

    <?php
}
add_shortcode('custom_tabs', 'custom_tabs_shortcode');