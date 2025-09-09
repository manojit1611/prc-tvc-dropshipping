<?php

if (!defined('ABSPATH')) exit;

class MPI_Importer
{
    public function __construct()
    {
        add_action('init', [$this, 'register_custom_taxonomies']);
    }

    public function register_custom_taxonomies()
    {
        register_taxonomy(
            ww_tvs_get_product_model_taxonomy_type(), // Taxonomy slug
            'product',       // Attach to WooCommerce products
            array(
                'labels' => array(
                    'name' => 'Product Models',
                    'singular_name' => 'Product Model',
                    'search_items' => 'Search Models',
                    'all_items' => 'All Models',
                    'edit_item' => 'Edit Model',
                    'update_item' => 'Update Model',
                    'add_new_item' => 'Add New Model',
                    'new_item_name' => 'New Model Name',
                    'menu_name' => 'Product Models',
                ),
                'hierarchical' => true, // true = like categories, false = like tags
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'product-model'),
            )
        );

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
                'slug' => $category['Code'],
                'parent' => 0,
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
            $sku = $p['SKU'] ?? '';
            $name = $p['name'] ?? 'Untitled';
            $description = $p['description'] ?? '';
            $price = $p['unit_price'] ?? 0;
            $length = $p['length'] ?? '';
            $width = $p['width'] ?? '';
            $height = $p['height'] ?? '';
            $weight = $p['weight'] ?? '';
            $status = ($p['status'] == 1) ? 'publish' : 'draft';
            $category = $p['category'] ?? '';
            $images = !empty($p['images_urls']) ? explode(',', $p['images_urls']) : [];

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

            $api_url = TVC_BASE_URL . "/openapi/Product/Detail?ItemNo=" . urlencode($sku);
            // $api_url = TVC_BASE_URL . "/OpenApi/Product/Detail_NewVersion?ItemNo=" . urlencode($sku);

            $args = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'TVC ' . $token,
                ],
                'timeout' => 30,
            ];

            $response = wp_remote_get($api_url, $args);

            if (is_wp_error($response)) {
                return ['error' => $response->get_error_message()];
            }

            $body = wp_remote_retrieve_body($response);
            $product_data = json_decode($body, true)['Detail'];
            $model_list = json_decode($body, true)['ModelList'];

            // api replace
            if (empty($product_data)) {
                continue;
            }

            // Save base details
            $product_id = $this->save_update_products($product_data, $sku, $p);

            // Save update tvc product mapping
            $this->update_tvc_products($p, $product_id, $product_data);

            // ww_tvc_print_r($p);
            // ww_tvc_print_r($product_data);

            // Save Bulk Pricing Slab
            $this->update_tvc_bulk_pricing_table($product_id, $product_data);

            // Save Mapping of Compatible brand
            $this->add_update_brand($product_data, $product_id);

            // Save Mapping of Compatible Model
            $this->add_update_models($product_data, $product_id);

            $this->add_update_attributes($product_data, $product_id);

            $itemNo = [];
            $postId = [];
            // Manage also available/variations:colors
            if (isset($model_list) && !empty($model_list)) {
                foreach ($model_list as $index => $data) {
                    $modelSku = $data['ItemNo'];

                    if ($modelSku != $sku) {
                        $itemNo[] = $modelSku;
                    }

                    // first vala visible
                    if ($index == 0) continue;

                    $product_id_by_sku = wc_get_product_id_by_sku($modelSku);
                    // $postId[] = $product_id;
                    if ($product_id_by_sku) {
                        $product = wc_get_product($product_id_by_sku);
                        $product->set_catalog_visibility('hidden');
                        $product->save();
                    }

                    aap_save_product_links($product_id, [$product_id_by_sku]);
                }
                
                update_post_meta( $product_id, '_related_models', implode(',', $itemNo));
            }
        }

        return true;
    }

    function save_update_products($product_data, $sku, $apiShortProduct = array())
    {
        // 🔹 Example structure from API (adjust keys as needed)
        $name = $product_data['Name'] ?? 'Untitled';
        $description = $product_data['Description'] ?? '';
        $short_description = $product_data['ShortDescription'] ?? '';
        $price = $product_data['Price'] ?? 0;
        $length = $product_data['Length'] ?? '';
        $width = $product_data['Width'] ?? '';
        $height = $product_data['Height'] ?? '';
        $weight = $product_data['Weight'] ?? '';
        $status = ($product_data['ProductStatus'] == 1) ? 'publish' : 'draft';
        $category_slug = strtolower($product_data['CategoryCode'] ?? '');
        $product_id = wc_get_product_id_by_sku($sku);
        if ($product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                $product = new WC_Product_Simple();
                $product->set_sku($sku);
            }
        } else {
            // Product not found → create a new one
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

        $images = $this->tvc_get_extra_images($sku);

        $image_urls = [];
        if (!empty($images)) {
            foreach ($images as $img) {
                if (!empty($img['Url'])) {
                    // Add your domain in front if needed
                    $image_urls[] = 'https://img.tvc-mall.com' . $img['Url'];
                }
            }
        }

       $urls = implode(',', $image_urls); 

        // Save media CDN
        $product_image_url = $apiShortProduct['ImageUrl'] ?? '';
        $product->update_meta_data('_tvc_image_url', $product_image_url);
        $product->update_meta_data('_tvc_extra_image_urls', $urls);

        // 🔹 Assign a category if slug exists
        if (!empty($category_slug)) {
            // Get Product Category by tvc category code
            $term = ww_tvc_get_term_data_by_tvc_code($category_slug);
            if ($term && !is_wp_error($term)) {
                $product->set_category_ids([$term->term_id]);
            }
        }

        // 🔹 Save product
        $product_id = $product->save();
        return $product_id;
    }

    function update_tvc_products($data, $postId, $productData)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tvc_products_data';

        // delete old record
        $wpdb->delete($table_name, ['post_id' => (int)$postId], ['%d']);

        // prepare row
        $row = [
            'tvc_product_id' => isset($data['ProductId']) ? (int)$data['ProductId'] : 0,
            'post_id' => (int)$postId,
            'tvc_product' => json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        // insert new record
        $result = $wpdb->insert(
            $table_name,
            $row,
            ['%d', '%d', '%s'] // formats: int, int, string
        );

        if ($result === false) {
            error_log("❌ Insert failed for post_id {$postId} | DB error: " . $wpdb->last_error);
            error_log("❌ Query: " . $wpdb->last_query);
            error_log("❌ Data: " . print_r($row, true));
        } else {
            error_log("✅ Insert successful for post_id {$postId}");
        }

        return $result;
    }

    function tvc_get_extra_images($sku)
    {
        $api = new MPI_API();

        $token = $api->mpi_get_auth_token();
        if (!$token) {
            return ['error' => 'Failed to retrieve authentication token'];
        }

        $api_url = TVC_BASE_URL . "/OpenApi/Product/Detail_NewVersion?ItemNo=" . urlencode($sku);

        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'TVC ' . $token,
            ],
            'timeout' => 30,
        ];

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $product_data = json_decode($body, true);

        return $product_data['Images']['ProductImages'];
    }


    /**
     * @param $postId
     * @param $productData
     * @return void
     * update_tvc_bulk_pricing_table
     * Update TVC pricing slab
     */
    function update_tvc_bulk_pricing_table($postId, $productData)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . ww_tvc_get_bulk_pricing_db_table_name();
        if (empty($productData['PriceList'])) {
            return;
        }
        // Delete old entries for this post_id
        $wpdb->delete(
            $table_name,
            ['post_id' => $postId],
            ['%d']
        );
        // Insert fresh entries
        foreach ($productData['PriceList'] as $data) {
            $row = [
                'post_id' => $postId,
                'min_qty' => $data['MinimumQuantity'],
                'unit_price' => $data['UnitPrice'],
            ];
            $wpdb->insert(
                $table_name,
                $row,
                ['%d', '%d', '%f']
            );
        }
    }


    /**
     * @param $data
     * @param $product_id
     * @return void
     * add_update_brand
     * Save brand / manufacturer
     */
    public function add_update_brand($data, $product_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . ww_tvc_get_manufacturer_product_relation_table_name();
        $new_term = [];
        if (empty($data['CompatibleList'])) {
            return;
        }
        $brand_ids = array();
        $brand_taxonomy = 'product_brand';
        // ww_tvc_print_r($data['CompatibleList']);
        foreach ($data['CompatibleList'] as $list) {
            $brand_name = sanitize_text_field($list['Brand']);
            $brand_slug = sanitize_title($brand_name);
            //  Check if a brand exists
            $existing_term = get_term_by('slug', $brand_slug, $brand_taxonomy);
            if ($existing_term) {
                // Update the existing brand (name, slug or description)
                wp_update_term($existing_term->term_id, $brand_taxonomy, [
                    'name' => $brand_name,
                    'slug' => $brand_slug,
                ]);
                $brand_ids[] = (int)$existing_term->term_id;
            } else {
                // Insert a new brand
                wp_insert_term(
                    $brand_name,
                    $brand_taxonomy,
                    ['slug' => $brand_slug]
                );
                if (!is_wp_error($new_term) && isset($new_term['term_id'])) {
                    $brand_ids[] = (int)$new_term['term_id'];
                }
            }
        }

        // Manage hybrid brand sync
        $brand_type_flag = 1;
        // Delete old entries for this post_id
        $wpdb->delete($table_name, array(
            'post_id' => $product_id,
            'type' => $brand_type_flag,
        ));
        if (!empty($brand_ids)) {
            $brand_ids = array_unique($brand_ids);
            foreach ($brand_ids as $brandId) {
                $row = [
                    'post_id' => $product_id,
                    'type' => $brand_type_flag,
                    'term_id' => $brandId,
                ];
                $wpdb->insert(
                    $table_name,
                    $row,
                    ['%d', '%s', '%d']
                );
            }
        }
        // Sync wooCommerce
        wp_set_object_terms($product_id, $brand_ids, $brand_taxonomy);
    }

    /**
     * @param $data
     * @param $product_id
     * @return void
     * add_update_models
     * Manage models for products
     */
    public function add_update_models($data, $product_id)
    {
        if (empty($data['CompatibleList'])) {
            return;
        }
        global $wpdb;
        $table_name = $wpdb->prefix . ww_tvc_get_manufacturer_product_relation_table_name();
        $model_taxonomy = ww_tvs_get_product_model_taxonomy_type();
        $compatibleModelList = array();
        foreach ($data['CompatibleList'] as $list) {
            // skip conflict for the same name of brand and model
            //            if ($list['Brand'] == $list['Model']) {
            //                continue;
            //            }
            // Save update further models
            $model_name = sanitize_text_field($list['Model']);
            $model_slug = sanitize_title($model_name);
            $existing_term = ww_tvc_get_term_data_by_tvc_code($model_slug, $model_taxonomy);
            if (empty($existing_term)) {
                // create a new one
                $model_id = ww_tvc_save_formatted_tvc_cat_term(
                    [
                        "taxonomy" => $model_taxonomy,
                        'name' => $list['DisplayName'] ?? $model_name,
                        'slug' => $model_slug,
                        "code" => $model_slug
                    ]
                );
                // Might be an error thrown
                if (empty($model_id)) {
                    // Punch Log here
                    echo "Error while creating a new model: " . $model_name . " " . $model_slug . "";
                    continue;
                }

                // get again updated model
                $existing_term = get_term_by('id', $model_id, $model_taxonomy);
            }

            // append model id into a process list
            $compatibleModelList[] = $existing_term->term_id;
        }
        // Save Hybrid table for compatible models
        $model_type_flag = 2;

        // Delete old entries for this post_id
        $wpdb->delete($table_name, array(
            'post_id' => $product_id,
            'type' => $model_type_flag,
        ));

        if (!empty($compatibleModelList)) {
            foreach ($compatibleModelList as $modelId) {
                $row = [
                    'post_id' => $product_id,
                    'type' => $model_type_flag,
                    'term_id' => $modelId,
                ];
                $wpdb->insert(
                    $table_name,
                    $row,
                    ['%d', '%s', '%d']
                );
            }
        }

        // Make relation of product * product models
        // ww_tvc_print_r($compatibleModelList);
        wp_set_object_terms($product_id, $compatibleModelList, $model_taxonomy);
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

        foreach ($data['SpecificationList'] as $attr) {
            $attr_name = sanitize_title($attr['Name']); // e.g. "Color"
            $attr_value = sanitize_text_field($attr['Value']); // e.g. "Red"
            
            if (!in_array($attr_name, ['color', 'material', 'packaging-type', 'colorstyle', 'quick-charge'])) {
                continue;
            }

            // WooCommerce attribute taxonomy key must start with pa_
            $taxonomy = 'pa_' . $attr_name;

            // ✅ Create attribute taxonomy if it doesn’t exist
            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy($taxonomy, ['product'], [
                    'label' => __($attr_name, 'textdomain'),
                    'hierarchical' => true,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'rewrite' => ['slug' => $attr_name],
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
                wp_set_object_terms($product_id, [(int)$term_id], $taxonomy, true);
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
