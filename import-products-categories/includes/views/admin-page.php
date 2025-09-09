<div class="wrap">
    <h1>Product Importer</h1>
    <p>Select a category to fetch products from API:</p>

    <form method="post" id="mpi-category-form">
        <?php wp_nonce_field('mpi_import_nonce'); ?>

        <div>
            <label for="sync_type">Select Sync Type</label><select required name="sync_type" id="sync_type" style="margin-bottom:10px;">
                <option value="products">Products</option>
                <option value="product_cat">Product Categories</option>
            </select>
        </div>

        <!-- Parent Dropdown -->
        <label for="parent_category">Parent Category</label><select required name="parent_category_code" id="parent_category" style="margin-bottom:10px;">
            <option value="">-- Select Parent Category --</option>
            <?php
            $parent_cats = ww_tvs_get_allowed_channel_product_cats();
            if (!empty($parent_cats)) {
                foreach ($parent_cats as $cat) {
                    echo '<option value="' . esc_attr($cat['code']) . '">' . esc_html($cat['name']) . '</option>';
                }
            }
            //            $parent_cats = get_terms([
            //                'taxonomy' => 'product_cat',
            //                'hide_empty' => false,
            //                'parent' => 0,
            //            ]);
            //            if (!empty($parent_cats) && !is_wp_error($parent_cats)) {
            //                foreach ($parent_cats as $cat) {
            //                    if ($cat->slug === 'uncategorized') continue;
            //                    echo '<option value="' . esc_attr($cat->slug) . '">' . esc_html($cat->name) . '</option>';
            //                }
            //            }
            ?>
        </select>

        <!-- Container where child dropdowns will be added -->
        <div id="child-category-container"></div>

        <br><br>
        <button type="submit" class="button button-primary">Fetch Category & Products</button>
    </form>
</div>


<?php

function ww_tvc_rec_cats($categoryCode = null)
{
    $api = new MPI_API();

    // Fetch categories
    $response = $api->get_categories_from_api($categoryCode);
    $categories = $response['CateoryList'] ?? [];

    $result = [];

    foreach ($categories as $category) {
        if (empty($category['Code']) || empty($category['Name'])) {
            continue;
        }

        // Recursive call for children
        $children = ww_tvc_rec_cats($category['Code']);

        $result[] = [
            'code' => $category['Code'],
            'name' => $category['Name'],
            "ParentCode" => $category['ParentCode'] ?? null,
            'children' => $children, // nested array
        ];
    }

    return $result;
}

/**
 * @return void
 * ww_tvs_import_allowed_channel_product_cats
 * Import First Parent Terms
 */
function ww_tvs_import_allowed_channel_product_cats()
{
    $allowed_parentCategories = ww_tvs_get_allowed_channel_product_cats();
    if (!empty($allowed_parentCategories) && is_array($allowed_parentCategories)) {
        foreach ($allowed_parentCategories as $key => $cat) {
            $existing = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'meta_query' => [
                    [
                        'key' => '_tvc_product_cat_code',
                        'value' => $cat['code'],
                    ],
                ],
                'number' => 1,
            ]);
            $name = sanitize_text_field($cat['name']);
            $code = sanitize_text_field($cat['code']);
            $slug = sanitize_title($name); // slug comes from a name;
            if (!empty($existing) && !is_wp_error($existing)) {
                // ✅ Update the existing category
                $term_id = $existing[0]->term_id;
                wp_update_term($term_id, 'product_cat', [
                    'name' => $name,
                    'slug' => $slug,
                    'parent' => 0,
                ]);
            } else {
                // ✅ Create a new category
                $new_term = wp_insert_term(
                    $name,
                    'product_cat',
                    [
                        'slug' => $slug,
                        'parent' => 0,
                    ]
                );
                if (is_wp_error($new_term)) {
                    continue; // skip if failed
                }
                $term_id = $new_term['term_id'];
            }
            update_term_meta($term_id, '_tvc_product_cat_code', $code);
        }
    }
}

/**
 * @param $categories
 * @param $parent_id
 * @return void
 * ww_import_categories_to_wc
 */
function ww_import_categories_to_wc($categories, $parent_id = 0)
{
    foreach ($categories as $cat) {
        if (empty($cat['name']) || empty($cat['code'])) {
            continue;
        }

        $name = sanitize_text_field($cat['name']);
        $code = sanitize_text_field($cat['code']);
        $slug = sanitize_title($name); // slug comes from name

        // 🔎 Check if a category already exists by meta (_tvc_product_cat_code)
        $existing = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => '_tvc_product_cat_code',
                    'value' => $code,
                ],
            ],
            'number' => 1,
        ]);

        if (!empty($existing) && !is_wp_error($existing)) {
            // ✅ Update the existing category
            $term_id = $existing[0]->term_id;

            wp_update_term($term_id, 'product_cat', [
                'name' => $name,
                'slug' => $slug,
                'parent' => $parent_id,
            ]);
        } else {
            // ✅ Create a new category
            $new_term = wp_insert_term(
                $name,
                'product_cat',
                [
                    'slug' => $slug,
                    'parent' => $parent_id,
                ]
            );

            if (is_wp_error($new_term)) {
                continue; // skip if failed
            }

            $term_id = $new_term['term_id'];
        }

        // ✅ Always save/update the category code in meta
        update_term_meta($term_id, '_tvc_product_cat_code', $code);

        // 🔁 Recurse into children
        if (!empty($cat['children'])) {
            ww_import_categories_to_wc($cat['children'], $term_id);
        }
    }
}

if (isset($_POST['parent_category_code'])) {
    if (isset($_POST['category_code']) && !empty($_POST['category_code'])) {
        $categoryCode = sanitize_text_field($_POST['category_code']);
    } else {
        $categoryCode = sanitize_text_field($_POST['parent_category_code']);
    }


    $importer = new MPI_Importer();
    $api = new MPI_API();

    // import Defaults
    $parent_code = $categoryCode;
    $sync_type = $_POST['sync_type'] ?? '';
    if ($sync_type == 'products') {
        $lastProductId = null;
        $maxPages = 10;
        $pageIndex = 1;
        $perPage = 1;
        // $beginDate = '2020-01-11T00:16:34';
        // $endDate = '2020-01-15T00:16:34';
        $beginDate = null;
        $endDate = null;

        do {
            $products = $api->get_products_by_category_code($categoryCode, $lastProductId, $perPage, $pageIndex, $beginDate, $endDate);
            $importer->get_detail_of_products($products);
            $lastProductId = $products['lastProductId'];

            $pageIndex++;
        } while ($pageIndex <= $maxPages);
    } elseif ($sync_type == 'product_cat') {
        // Fetch product cats
        $existing_parent = ww_tvc_get_term_data_by_tvc_code($parent_code);
        if (empty($existing_parent)) {
            ww_tvs_import_allowed_channel_product_cats();
            $existing_parent = ww_tvc_get_term_data_by_tvc_code($parent_code);
        }
        $categoriesTree = ww_tvc_rec_cats($parent_code);
        if (!empty($categoriesTree)) {
            ww_import_categories_to_wc($categoriesTree, $existing_parent->term_id);;
        }
        echo '<pre>';
        echo "Product Categories Imported Successfully";
        print_r($categoriesTree);
        echo '</pre>';
    } else {
        echo "Product Sync ype is not set";
    }


//    $categories = $api->get_categories_from_api($categoryCode);
//    if (!empty($categories['CateoryList'])) {
//        if (empty($categories['CateoryList'][0]['ParentCode'])) {
//            $importer->import_categories($categories);
//        } else if (isset($_POST['parent_category_code']) && (isset($_POST['category_code']) || $_POST['category_code'] == "")) {
//            $importer->import_categories($categories);
//        } else if (isset($_POST['category_code'])) {
//            $importer->import_categories($categories);
//        }
//        // foreach ($categories['CateoryList'] as $categoryList) {
//        //     if (!empty($categoryList['ParentCode'])) {
//        //         // $categories = get_categories_from_api($categoryList['Code']);
//        //         $imoportCategories = import_categories($categories);
//        //         print_r($categoryList);
//        //     }
//        // }
//
//    } else {
//        $products = $api->get_products_by_category_code($categoryCode);
//        $importer->get_detail_of_products($products);
//    }
}
