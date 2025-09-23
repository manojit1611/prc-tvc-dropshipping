<?php

if (!defined('ABSPATH')) exit;

class MPI_Importer
{
    public function __construct()
    {
        add_action('init', [$this, 'register_custom_taxonomies']);

        add_action('wp_ajax_product_fetch', [$this, 'ww_ajax_product_save_update']);
        // For non-logged-in users
        add_action('wp_ajax_nopriv_product_fetch', [$this, 'ww_ajax_product_save_update']);

        add_action('wp_ajax_product_fetch_by_date', [$this, 'ww_ajax_product_fetch_date']);
        // For non-logged-in users
        add_action('wp_ajax_nopriv_product_fetch_by_date', [$this, 'ww_ajax_product_fetch_date']);
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

        register_taxonomy(
            ww_tvs_get_product_manufacturer_taxonomy_type(), // Taxonomy slug
            'product',
            array(
                'labels' => array(
                    'name' => 'Manufacturers',
                    'singular_name' => 'Manufacturer',
                    'search_items' => 'Search Manufacturers',
                    'all_items' => 'All Manufacturers',
                    'edit_item' => 'Edit Manufacturer',
                    'update_item' => 'Update Manufacturer',
                    'add_new_item' => 'Add New Manufacturer',
                    'new_item_name' => 'New Manufacturer Name',
                    'menu_name' => 'Manufacturers',
                ),
                'hierarchical' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
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

    /**
     * @param $products
     * @return true
     * Update Product Details
     */
    function ww_update_detail_of_products($products, $batch_id = null, $filters = [])
    {
        global $wpdb;
        $success_count = 0;
        $failure_count = 0;
        $total_processed = 0;
        $stage = 'Scheduled';
        $invalid_records = [];

        foreach ($products['ProductItemNoList'] as $p) {
            try {
                $stage = 'Processing';
                $brandIds = [];
                $modelIds = [];
    
                $sku = $p['ItemNo'];
                $body = $this->ww_tvc_get_products_by_sku($sku);
                $response = json_decode($body, true);

                // Safely get 'Detail'
                $tvc_product_data = isset($response['Detail']) && is_array($response['Detail']) 
                    ? $response['Detail'] 
                    : [];

                // Safely get 'ModelList'
                if (isset($response['ModelList']) && is_array($response['ModelList'])) {
                    $model_list = $response['ModelList'];
                } else {
                    // add_import_error_log($batch_id, $tvc_product_data, 'Empty Model List', 'product');
                    $model_list = [];
                }

                if (empty($tvc_product_data)) {
                    $invalid_records[] = ['Empty Product' => $sku];
                    // add_import_error_log($batch_id, $tvc_product_data, 'Empty Product', 'product');
                    // my_log_error('Empty Product' . $tvc_product_data);
                    continue;
                }
    
                if (function_exists('category_exists_by_code')) {
                    $checkCategory = category_exists_by_code($tvc_product_data['CategoryCode']);
                    if (!$checkCategory) {
                        $invalid_records[] = ['Category Code does not exist ' . $tvc_product_data['CategoryCode'] => $sku];
                        // my_log_error('Category Code does not exist' . $tvc_product_data['CategoryCode']);
                        continue;
                    }
                }
    
                // Save base details
                $product_id = $this->save_update_products($tvc_product_data, $sku);
    
                $product = wc_get_product($product_id);
    
                $this->update_tvc_product($p, $product_id, $tvc_product_data);
    
                $this->update_additional_info($product_id, $tvc_product_data, $product, $model_list, $sku);

                $successfully_processed[] = $sku;
                $success_count++;
                $stage = 'Completed';
            } catch (Exception $e) {
                $failure_count++;
                $stage = 'Failed';
                $failedSku[] = $sku;
            }
        }

        $failedSku = implode(',', $failedSku ?? []);

        $state = [
            'success' => $success_count,
            'failed' => $failure_count,
            'total_processed' => $success_count + $failure_count,
            'stage' => $stage,
            'invalid_records' => $invalid_records,
            'lastProductId' => $p['ProductId'] ?? null,
            'failed_records' => $failedSku,
            'filters' => $filters,
        ];

        add_import_error_log($batch_id, json_encode($state), json_encode($successfully_processed), 'product');

        // my_log_error('Product Inserted ' . $count);

        return true;
    }

    /**
     * @param $product_id
     * @param $tvc_product_data
     * @param $product
     * @param $model_list
     * @return void
     * Update Additional Details of Product
     */
    function update_additional_info($product_id, $tvc_product_data, $product, $model_list, $sku)
    {
        $this->update_tvc_bulk_pricing_table($product_id, $tvc_product_data);

        $brandIds = $this->add_update_manufacturer($tvc_product_data, $product_id);

        $modelIds = $this->add_update_models($tvc_product_data, $product_id);

        $this->ww_update_brand_model_relation($brandIds, $modelIds);

        $this->add_update_attributes($tvc_product_data, $product_id, $product);

        if (function_exists('aap_save_product_links')) {
            $itemNo = [];
            // Manage also available/variations:colors
            if (isset($model_list) && !empty($model_list)) {
                foreach ($model_list as $index => $data) {
                    $modelSku = $data['ItemNo'];
                    if ($modelSku != $sku) {
                        $itemNo[] = $modelSku;
                    }

                    if ($index == 0) continue;
                    $product_id_by_sku = wc_get_product_id_by_sku($modelSku);
                    if ($product_id_by_sku) {
                        $product = wc_get_product($product_id_by_sku);
                        $product->set_catalog_visibility('search');
                        $product->save();
                    }

                    aap_save_product_links($product_id, [$product_id_by_sku]);
                }

                update_post_meta($product_id, '_related_models', implode(',', $itemNo));
            }
        }
    }

    /**
     * @return array
     * Fetch Product save and update
     */
    function ww_ajax_product_save_update()
    {
        $sku = isset($_GET['sku']) ? sanitize_text_field($_GET['sku']) : '';
        $redirect = isset($_GET['redirect']) ? filter_var($_GET['redirect'], FILTER_VALIDATE_BOOLEAN) : false;

        $args = [
            'post_type' => 'product',
            'ww_updated' => 1,
        ];

        $body = $this->ww_tvc_get_products_by_sku($sku);
        $product_data = json_decode($body, true)['Detail'] ?? [];
        $model_list = json_decode($body, true)['ModelList'] ?? [];

        // 🔹 Helper: handle error response/redirect
        $handle_error = function ($msg) use ($redirect, $args) {
            my_log_error($msg);

            if ($redirect) {
                $args['msg'] = $msg;
                wp_safe_redirect(add_query_arg($args, admin_url('edit.php')));
                exit;
            }

            wp_send_json_success([
                'success' => false,
                'data'    => $msg,
            ]);
            exit;
        };

        // If empty product
        if (empty($product_data)) {
            $handle_error('Empty Product: ' . $sku);
        }

        // If category check fails
        if (function_exists('category_exists_by_code')) {
            if (!category_exists_by_code($product_data['CategoryCode'])) {
                $handle_error('Category Code does not exist: ' . $product_data['CategoryCode']);
            }
        }

        // Save or update product
        if ($product_id = $this->save_update_products($product_data, $sku)) {
            $product = wc_get_product($product_id);

            $this->update_additional_info($product_id, $product_data, $product, $model_list, $sku);

            $msg = 'Product updated successfully';
            my_log_error('Product updated: ' . $sku);

            if ($redirect) {
                $args['msg'] = $msg;
                wp_safe_redirect(add_query_arg($args, admin_url('edit.php')));
                exit;
            }

            wp_send_json_success([
                'success' => true,
                'message' => $msg,
            ]);
            exit;
        }

        // If not updated
        $handle_error('Product not updated: ' . $sku);
    }

    /**
     * @param $sku
     * @return array
     * Get Products by SKU
     */
    function ww_tvc_get_products_by_sku($sku)
    {
        $api = new MPI_API();

        $token = $api->mpi_get_auth_token();
        if (!$token) {
            return ['error' => 'Failed to retrieve authentication token'];
        }

        $api_url = TVC_BASE_URL . "/openapi/Product/Detail?ItemNo=" . urlencode($sku);

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

        return $body;
    }

    function ww_ajax_product_fetch_date()
    {
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        $importer = new MPI_Importer();
        $api = new MPI_API();

        $lastProductId = null;
        $maxPages = 1; // 
        $pageIndex = 1; // Db offset
        $perPage = 10;

        // Current date/time in WP timezone
        $current_time = current_time('timestamp');

        // Begin date = 15 minutes before now
        $beginDate = $start_date;
        $endDate = $end_date;

        ww_start_product_import(null, $beginDate, $endDate);

        wp_send_json_success([
            'success' => true,
            'message' => 'Product fetch initiated. It may take a few minutes to complete.',
        ]);
        exit;
    }

    /**
     * @param $product_data
     * @param $sku
     * @return int
     * Save and update Products
     */
    function save_update_products($product_data, $sku)
    {
        $name = $product_data['Name'] ?? 'Untitled';
        $description = $product_data['Description'] ?? '';
        $short_description = $product_data['ShortDescription'] ?? '';

        // if (isset($product_data['PackageList']) && !empty($product_data['PackageList'])) {
        //     $short_description .= "<ul>";
        //     foreach ($product_data['PackageList'] as $package) {
        //         $short_description .= "<li>{$package}</li>";
        //     }
        //     $short_description .= "</ul>";
        // }

        $price = $product_data['Price'] ?? 0;
        $length = $product_data['Length'] ?? '';
        $width = $product_data['Width'] ?? '';
        $height = $product_data['Height'] ?? '';
        $weight = $product_data['Weight'] ?? '';
        $moq = $product_data['MOQ'] ?? '';
        $status = ($product_data['ProductStatus'] == 1) ? 'publish' : 'draft';
        $stock_status_code = $product_data['StockStatus'] ?? 0;

        switch ($stock_status_code) {
            case 1:
                $wc_stock_status = 'instock';
                break;
            case 2:
                $wc_stock_status = 'on_sale'; // or custom 'on_sale' if you added
                break;
            case 3:
                $wc_stock_status = 'in_shortage';
                break;
            case 4:
                $wc_stock_status = 'outofstock';
                break;
            case 5:
                $wc_stock_status = '5_7_days';
                break;
            case 7:
                $wc_stock_status = '7_10_days';
                break;
            default:
                $wc_stock_status = 'instock';
                break;
        }

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
        $product->set_stock_status($wc_stock_status);

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
        $product_image_url = $image_urls[0] ?? '';
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

        // 🔹 Save MOQ
        if (!empty($moq)) {
            $product->update_meta_data('_min_order_qty', $moq);
        }

        $product_id = $product->save();
        return $product_id;
    }

    /**
     * @param $data
     * @param $postId
     * @param $productData
     * @return array
     * Update Products
     */
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
            my_log_error("❌ Insert failed for post_id {$postId} in tvc_products | DB error: " . $wpdb->last_error);
        }

        return $result;
    }

    /**
     * @param $brandIds
     * @param $modelIds
     * @return void
     * Update Brand and Model relation
     */
    function ww_update_brand_model_relation($brandIds, $modelIds)
    {
        global $wpdb;

        $brand_type_flag = 1;
        $model_type_flag = 2;

        if (!empty($brandIds)) {
            foreach ($brandIds as $key => $id) {
                $brand_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM wp_tvc_manufacturer_relation WHERE term_id = %d AND type = %d",
                        $id,
                        $brand_type_flag
                    )
                );

                if (!$brand_exists) {
                    $brandRow = [
                        'term_id'   => (int) $id,
                        'parent_id' => 0,
                        'type'      => $brand_type_flag,
                    ];
    
                    $wpdb->insert(
                        'wp_tvc_manufacturer_relation',
                        $brandRow,
                        ['%d', '%d', '%d']
                    );

                    $lastId = $wpdb->insert_id;
                } else {
                    $lastId = $id;
                }

                $model_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM wp_tvc_manufacturer_relation WHERE term_id = %d AND type = %d",
                        $modelIds[$key],
                        $model_type_flag
                    )
                );

                if (!$model_exists) {
                    $modelRow = [
                        'term_id'   => (int) $modelIds[$key],
                        'parent_id' => $lastId,
                        'type'      => $model_type_flag,
                    ];

                    $wpdb->insert(
                        'wp_tvc_manufacturer_relation',
                        $modelRow,
                        ['%d', '%d', '%d']
                    );
                }
            }
        }
    }

    /**
     * @param $sku
     * @return mixed
     * Get Tvc
     */
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
     * add_update_manufacturer
     * Save brand / manufacturer
     */
    public function add_update_manufacturer($data, $product_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . ww_tvc_get_manufacturer_product_relation_table_name();
        $brand_relation_table_name = $wpdb->prefix . ww_tvc_get_manufacturer_product_relation_table_name();
        $new_term = [];
        if (empty($data['CompatibleList'])) {
            my_log_error('Empty CompatibleList ' . $product_id);
            return;
        }

        $brand_ids = array();
        $brand_taxonomy = ww_tvs_get_product_manufacturer_taxonomy_type();

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

        $brand_type_flag = 1;

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

        return $brand_ids;
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
            my_log_error('Empty CompatibleList ' . $product_id);
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

        return $compatibleModelList;
    }

    /**
     * @param $data
     * @param $product_id
     * @param $product
     * @return void
     * Update attributes
     */
    public function add_update_attributes($data, $product_id, $product)
    {
        if (empty($data['Attributes'])) return;

        if (!$product) return;

        $attributes_data = [];

        foreach ($data['SpecificationList'] as $attr) {
            $attr_name = sanitize_title($attr['Name']); // e.g. "Color"
            $attr_value = sanitize_text_field($attr['Value']); // e.g. "Red"

            if ($attr_name == 'brand') {
                $brand_taxonomy = 'product_brand';
                $brand_name = $attr_value;
                $brand_slug = sanitize_title($attr_value);
                
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
                }

                if (!empty($brand_ids)) {
                    wp_set_object_terms($product_id, $brand_ids, $brand_taxonomy);
                }
            }

            if (!in_array($attr_name, ['color', 'material', 'packaging-type', 'colorstyle', 'quick-charge'])) continue;

            // WooCommerce attribute taxonomy key must start with pa_
            $taxonomy = 'pa_' . $attr_name;

            // ✅ Create attribute taxonomy if it doesn’t exist
            //            if (!taxonomy_exists($taxonomy)) {
            //                register_taxonomy($taxonomy, ['product'], [
            //                    'label' => __($attr_name, 'textdomain'),
            //                    'hierarchical' => true,
            //                    'show_ui' => true,
            //                    'show_admin_column' => true,
            //                    'rewrite' => ['slug' => $attr_name],
            //                ]);
            //            }

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
