<div class="wrap">
    <h1 class="wp-heading-inline">📦 Product Importer</h1>
    <hr class="wp-header-end">

    <?php
    if (isset($_POST['sync_type']) && $_POST['sync_type'] == 'products') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ Products data has been updated successfully.</p></div>';
    }

    if (isset($_POST['sync_type']) && $_POST['sync_type'] == 'product_cat') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ Category data has been updated successfully.</p></div>';
    }
    ?>

    <div class="card" style="max-width:700px; padding:20px; margin-top:20px;">
        <p class="description" style="margin-bottom:20px;">
            Select what you want to sync from the API. Choose a sync type and category, then click fetch.
        </p>

        <form method="post" id="mpi-category-form">
            <?php wp_nonce_field('mpi_import_nonce'); ?>

            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label for="sync_type">Sync Type</label></th>
                    <td>
                        <select required name="sync_type" id="sync_type" style="min-width: 250px;">
                            <option value="products">Products</option>
                            <option value="product_cat">Product Categories</option>
                        </select>
                        <p class="description">Choose whether to import products or just categories.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="parent_category">Parent Category</label></th>
                    <td>
                        <select required name="parent_category_code" id="parent_category" style="min-width: 250px;">
                            <option value="">-- Select Parent Category --</option>
                            <?php
                            $parent_cats = ww_tvs_get_allowed_channel_product_cats();
                            if (!empty($parent_cats)) {
                                foreach ($parent_cats as $cat) {
                                    echo '<option value="' . esc_attr($cat['code']) . '">' . esc_html($cat['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <p class="description">This will fetch child categories and products under the selected
                            parent.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Child Categories</th>
                    <td>
                        <div id="child-category-container" style="margin-top:10px;"></div>
                        <p class="description">Child categories will appear here dynamically.</p>
                    </td>
                </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero">🚀 Fetch Category & Products</button>
            </p>
        </form>
    </div>
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

        $maxPages = 1; //
        $pageIndex = 1; // Db offset
        $perPage = 30;

        // $beginDate = '2020-01-11T00:16:34';
        // $endDate = '2020-01-15T00:16:34';

        $beginDate = null;
        $endDate = null;
        do {
            $products = $api->get_products_by_category_code($categoryCode, $lastProductId, $perPage, $pageIndex, $beginDate, $endDate);
            $importer->ww_update_detail_of_products($products);
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


//        $categoriesTree = ww_tvc_rec_cats($parent_code);
//        if (!empty($categoriesTree)) {
//            ww_import_categories_to_wc($categoriesTree, $existing_parent->term_id);;
//        }

        // Action Schedular
        ww_start_category_sync_now($parent_code, $existing_parent->term_id);;


        echo '<pre>';
        echo "Product Categories Imported Successfully";
        echo '</pre>';
    } else {
        echo "Product Sync ype is not set";
    }
}

