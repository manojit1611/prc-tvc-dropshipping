<?php

if (!defined('ABSPATH')) exit;

class MPI_Importer {
    public function __construct()
    {
        add_action('init', [$this, 'register_custom_taxonomies']); 
    }

    public function register_custom_taxonomies()
    {
        register_taxonomy('product_model', ['product'], [
            'label'        => __('Models', 'textdomain'),
            'hierarchical' => true,
            'show_ui'      => true,
            'show_admin_column' => true,
            'rewrite'      => ['slug' => 'models'],
        ]);
    }
    
    public function import_categories($categories)
    {
        if (isset($categories['error'])) {
            return;
        }
    
        foreach ($categories['CateoryList'] as $category) {
            $term_name = $category['Name'];
            $taxonomy = 'product_cat';
    
            $args = array(
                'description' => $category['Name'],
                'slug'        => $category['Code'],
                'parent'      => 0,
            );
            
            // add_action('init', function() use ($term_name, $taxonomy, $args, $category) {
            if (taxonomy_exists($taxonomy)) {
                if (!term_exists($term_name, $taxonomy)) {
                    $result = wp_insert_term($term_name, $taxonomy, $args);
                }
            }

            if (!empty($category['ParentCode'])) {
                $parent_cat = get_term_by('slug', $category['ParentCode'], 'product_cat');

                if ($parent_cat) {
                    wp_update_term($result['term_id'], 'product_cat', [
                        'parent' => $parent_cat->term_id,
                    ]);
                }
            }
            // });
        }
    }
    
    public function mpi_import_products($products)
    {
        foreach ($products['ProductItemNoList'] as $p) {
            $sku = $p['ItemNo'];
            $product_id = wc_get_product_id_by_sku($sku);
    
            if ($product_id) {
                // Update existing product
                $product = wc_get_product($product_id);
            } else {
                // Create new simple product
                $product = new WC_Product_Simple();
                $product->set_sku($sku);
            }
    
            // Set product name (fallback to SKU if no name field in API)
            $product->set_name("Product " . $p['ItemNo']);
    
            // Map StockStatus (assuming 2 = in stock, else out of stock)
            if ($p['StockStatus'] == 2) {
                $product->set_stock_status('instock');
            } else {
                $product->set_stock_status('outofstock');
            }
    
            // Map ProductStatus (assuming 1 = publish, else draft)
            if ($p['ProductStatus'] == 1) {
                $product->set_status('publish');
            } else {
                $product->set_status('draft');
            }
    
            // Save product
            $new_product_id = $product->save();
    
            // Store extra data as meta
            update_post_meta($new_product_id, '_api_product_id', $p['ProductId']);
            update_post_meta($new_product_id, '_catalog_code', $p['CatalogCode']);
            update_post_meta($new_product_id, '_publish_date', $p['PublishDate']);
            update_post_meta($new_product_id, '_modified_date', $p['Modified']);
        }
    }

    public function insert_products_from_xml($products)
    {
        foreach ($products as $p) {
            $sku         = $p['SKU'] ?? '';
            $name        = $p['name'] ?? 'Untitled';
            $description = $p['description'] ?? '';
            $price       = $p['unit_price'] ?? 0;
            $length      = $p['length'] ?? '';
            $width       = $p['width'] ?? '';
            $height      = $p['height'] ?? '';
            $weight      = $p['weight'] ?? '';
            $status      = ($p['status'] == 1) ? 'publish' : 'draft';
            $category    = $p['category'] ?? '';
            $images      = !empty($p['images_urls']) ? explode(',', $p['images_urls']) : [];

            // ✅ Check if product exists by SKU
            $product_id = wc_get_product_id_by_sku($sku);
            if ($product_id) {
                $product = wc_get_product($product_id);
            } else {
                $product = new WC_Product_Simple();
                $product->set_sku($sku);
            }

            // ✅ Basic fields
            $product->set_name($name);
            $product->set_description($description);
            $product->set_regular_price($price);
            $product->set_status($status);
            $product->set_length($length);
            $product->set_width($width);
            $product->set_height($height);
            $product->set_weight($weight);

            // ✅ Assign category by name (create if not exists)
            if (!empty($category)) {
                $term = get_term_by('name', $category, 'product_cat');
                if (!$term) {
                    $new_term = wp_insert_term($category, 'product_cat');
                    if (!is_wp_error($new_term)) {
                        $term_id = $new_term['term_id'];
                    }
                } else {
                    $term_id = $term->term_id;
                }
                if (!empty($term_id)) {
                    $product->set_category_ids([$term_id]);
                }
            }

            // ✅ Save product first to get product_id
            $product_id = $product->save();

            // ✅ Handle images
            if (!empty($images)) {
                $image_ids = [];
                foreach ($images as $i => $img_url) {
                    $image_id = mpi_download_image_to_media($img_url, $sku . '-' . $i);
                    if ($image_id) {
                        $image_ids[] = $image_id;
                    }
                }

                if (!empty($image_ids)) {
                    $product->set_image_id($image_ids[0]); // main image
                    $product->set_gallery_image_ids(array_slice($image_ids, 1)); // gallery
                    $product->save();
                }
            }
        }
    }

    function get_detail_of_products($products)
    {
        $api = new MPI_API();

        $token = $api->mpi_get_auth_token();
        if (!$token) {
            return ['error' => 'Failed to retrieve authentication token'];
        }

        foreach ($products['ProductItemNoList'] as $p) {
            $sku = $p['ItemNo'];

            $api_url = BASE_URL . "/openapi/Product/Detail?ItemNo=" . urlencode($sku);

            $args = [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'TVC '.$token,
                ],
                'timeout' => 30,
            ];

            $response = wp_remote_get($api_url, $args);

            if (is_wp_error($response)) {
                return ['error' => $response->get_error_message()];
            }

            $body = wp_remote_retrieve_body($response);
            $product_data = json_decode($body, true)['Detail'];

            if (empty($product_data)) {
                continue;
            }
            
            $product_id = $this->save_update_products($product_data, $sku);

            $this->update_json_table($p, $product_id, $product_data);

            $this->update_pricing_table($product_id, $product_data);

            $this->add_update_brand($product_data, $product_id);

            $this->add_update_models($product_data, $product_id);

            $this->add_update_attributes($product_data, $product_id);
        }

        return true;
    }

    function save_update_products($product_data, $sku)
    {
        // 🔹 Example structure from API (adjust keys as needed)
        $name        = $product_data['Name'] ?? 'Untitled';
        $description = $product_data['Description'] ?? '';
        $short_description = $product_data['ShortDescription'] ?? '';
        $price       = $product_data['Price'] ?? 0;
        $length      = $product_data['Length'] ?? '';
        $width       = $product_data['Width'] ?? '';
        $height      = $product_data['Height'] ?? '';
        $weight      = $product_data['Weight'] ?? '';
        $status      = ($product_data['ProductStatus'] == 1) ? 'publish' : 'draft';
        $category_slug = strtolower($product_data['CategoryCode'] ?? '');

        $product_id = wc_get_product_id_by_sku($sku);

        if ($product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                $product = new WC_Product_Simple();
                $product->set_sku($sku);
            }
        } else {
            // Product not found → create new one
            $product = new WC_Product_Simple();
            $product->set_sku($sku);
        }

        // 🔹 Set WooCommerce fields
        $product->set_name($name);
        $product->set_description($description);
        $product->set_regular_price($price);
        $product->set_status($status);
        $product->set_short_description($short_description);

        // 🔹 Dimensions & Weight
        $product->set_length($length);
        $product->set_width($width);
        $product->set_height($height);
        $product->set_weight($weight);

        // 🔹 Assign category if slug exists
        if (!empty($category_slug)) {
            $term = get_term_by('slug', $category_slug, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $product->set_category_ids([$term->term_id]);
            }
        }

        // 🔹 Save product
        $product_id = $product->save();

        return $product_id;
    }

    function update_json_table($data, $postId, $productData)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'products_data';

        // Check if record exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE tvc_id = %s AND post_id = %d",
                $data['ProductId'],
                $postId
            )
        );

        $row = [
            'tvc_id'       => $data['ProductId'],
            'post_id'      => $postId,
            'product_json' => json_encode($productData),
        ];

        if ($exists) {
            // ✅ Update
            $wpdb->update(
                $table_name,
                $row,
                [ 'tvc_id' => $data['ProductId'], 'post_id' => $postId ],
                [ '%s', '%d', '%s' ],
                [ '%s', '%d' ]
            );
        } else {
            // ✅ Insert
            $wpdb->insert(
                $table_name,
                $row,
                [ '%s', '%d', '%s' ]
            );
        }
    }

    function update_pricing_table($postId, $productData)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'product_pricing';

        if (empty($productData['PriceList'])) {
            return;
        }

        foreach ($productData['PriceList'] as $data) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND min_qty = %d",
                    $postId,
                    $data['MinimumQuantity']
                )
            );

            $row = [
                'post_id'    => $postId,
                'min_qty'    => $data['MinimumQuantity'],
                'unit_price' => $data['UnitPrice'],
            ];

            if ($exists) {
                // ✅ Update
                $wpdb->update(
                    $table_name,
                    $row,
                    [ 'post_id' => $postId, 'min_qty' => $data['MinimumQuantity'] ],
                    [ '%d', '%d', '%f' ],
                    [ '%d', '%d' ]
                );
            } else {
                // ✅ Insert
                $wpdb->insert(
                    $table_name,
                    $row,
                    [ '%d', '%d', '%f' ]
                );
            }
        }
    }

    public function add_update_brand($data, $product_id)
    {
        global $wpdb;
        $table_name = 'wp_manufacturer_hierarchy';
        $new_term = [];

        if (empty($data['CompatibleList'])) {
            return;
        }

        foreach ($data['CompatibleList'] as $list) {
            $brand_name = sanitize_text_field($list['Brand']);
            $brand_slug = sanitize_title($brand_name);

            // ✅ Check if brand exists
            $existing_term = get_term_by('slug', $brand_slug, 'product_brand');

            if ($existing_term) {
                // ✅ Update existing brand (name, slug or description)
                wp_update_term($existing_term->term_id, 'product_brand', [
                    'name' => $brand_name,
                    'slug' => $brand_slug,
                ]);
                $brand_ids[] = (int) $existing_term->term_id;
            } else {
                // ✅ Insert new brand
                wp_insert_term(
                    $brand_name,
                    'product_brand',
                    ['slug' => $brand_slug]
                );

                if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                    $brand_ids[] = (int) $new_term['term_id'];
                }
            }
        }

        if (!empty($brand_ids)) {
            wp_set_object_terms($product_id, $brand_ids, 'product_brand');
        }

        foreach ($brand_ids as $brandId) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND type = %s AND term_id = %d",
                    $product_id,
                    get_type(0),
                    $brandId
                )
            );

            $row = [
                'post_id' => $product_id,
                'type'    => get_type(0),
                'term_id' => $brandId,
            ];

            if ($exists) {
                // ✅ Update (only needed if you have extra columns like updated_at, meta etc.)
                $wpdb->update(
                    $table_name,
                    $row,
                    [ 'post_id' => $product_id, 'type' => 'brand', 'term_id' => $brandId ],
                    [ '%d', '%s', '%d' ],
                    [ '%d', '%s', '%d' ]
                );
            } else {
                // ✅ Insert
                $wpdb->insert(
                    $table_name,
                    $row,
                    [ '%d', '%s', '%d' ]
                );
            }
        }

    }

    public function add_update_models($data, $product_id)
    {
        if (empty($data['CompatibleList'])) {
            return;
        }

        global $wpdb;
        $table_name = 'wp_manufacturer_hierarchy';

        foreach ($data['CompatibleList'] as $list) {
            $model_name = sanitize_text_field($list['Model']);
            $model_slug = sanitize_title($model_name);
            $new_term = [];

            // ✅ Check if brand exists
            $existing_term = get_term_by('slug', $model_slug, 'product_model');

            if ($existing_term) {
                // ✅ Update existing brand (name, slug or description)
                wp_update_term($existing_term->term_id, 'product_model', [
                    'name' => $model_name,
                    'slug' => $model_slug,
                ]);
                $model_ids[] = (int) $existing_term->term_id;
            } else {
                // ✅ Insert new brand
                wp_insert_term(
                    $model_name,
                    'product_model',
                    ['slug' => $model_slug]
                );

                if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                    $model_ids[] = (int) $new_term['term_id'];
                }
            }

            foreach ($model_ids as $modelId) {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND type = %s AND term_id = %d",
                        $product_id,
                        get_type(1),
                        $modelId
                    )
                );

                $row = [
                    'post_id' => $product_id,
                    'type'    => get_type(1),
                    'term_id' => $modelId,
                ];

                if ($exists) {
                    // ✅ Update (only needed if you have extra columns like updated_at, meta etc.)
                    $wpdb->update(
                        $table_name,
                        $row,
                        [ 'post_id' => $product_id, 'type' => 'brand', 'term_id' => $modelId ],
                        [ '%d', '%s', '%d' ],
                        [ '%d', '%s', '%d' ]
                    );
                } else {
                    // ✅ Insert
                    $wpdb->insert(
                        $table_name,
                        $row,
                        [ '%d', '%s', '%d' ]
                    );
                }
            }
        }

        if (!empty($model_ids)) {
            wp_set_object_terms($product_id, $model_ids, 'product_model');
        }
    }

    public function add_update_attributes($data, $product_id)
    {
        if (empty($data['Attributes'])) {
            return;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        $attributes_data = [];
        
        foreach ($data['Attributes'] as $key => $attr) {
            $attr_name  = sanitize_title($key); // e.g. "Color"
            $attr_value = sanitize_text_field($attr); // e.g. "Red"

            if (empty($attr_name) || empty($attr_value)) {
                continue;
            }

            // WooCommerce attribute taxonomy key must start with pa_
            $taxonomy = 'pa_' . $attr_name;

            // ✅ Create attribute taxonomy if it doesn’t exist
            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy($taxonomy, ['product'], [
                    'label'        => __($attr_name, 'textdomain'),
                    'hierarchical' => true,
                    'show_ui'      => true,
                    'show_admin_column' => true,
                    'rewrite'      => ['slug' => $attr_name],
                ]);
            }

            // ✅ Insert term (attribute value) if not exists
            $term = get_term_by('name', $attr_value, $taxonomy);
            if (!$term) {
                $new_term = wp_insert_term($attr_value, $taxonomy);
                if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                    $term_id = $new_term['term_id'];
                }
            } else {
                $term_id = $term->term_id;
            }

            // ✅ Assign the term to the product
            if (!empty($term_id)) {
                wp_set_object_terms($product_id, [(int) $term_id], $taxonomy, true);
            }

            // ✅ Prepare attribute object
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
            $attribute->set_name($taxonomy);
            $attribute->set_options([$attr_value]);
            $attribute->set_visible(true);
            $attribute->set_variation(false);

            $attributes_data[$taxonomy] = $attribute;
        }

        // ✅ Save attributes to product
        if (!empty($attributes_data)) {
            $product->set_attributes($attributes_data);
            $product->save();
        }
    }
}
