<style>
    select {
        width: 100%;
    }

    div#child-category-container:empty {
        margin-bottom: 0 !important;
    }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo __(TVC_PLUGIN_NAME_PREFIX . ' Categories Importer') ?></h1>
    <hr class="wp-header-end">

    <?php
    if (isset($_POST['sync_type']) && $_POST['sync_type'] == 'products') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ A product pull request has been scheduled.</p></div>';
    }

    if (isset($_POST['sync_type']) && $_POST['sync_type'] == 'product_cat') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ A category pull request has been scheduled.</p></div>';
    }
    ?>

    <div class="card" style="max-width:700px; padding:20px; margin-top:20px;">
        <p class="description" style="margin-bottom:20px;">
            <?php echo __('Choose a category, then click fetch. It will fetch all sub categories of current selected category via background automation.') ?>
        </p>

        <form method="post" id="mpi-tvc-category-form">
            <?php wp_nonce_field('mpi_import_nonce'); ?>
            <table class="form-table">
                <tbody>
                <input type="hidden" value="product_cat" name="sync_type"/>
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
                    </td>
                </tr>

                <tr>
                    <th scope="row">Child Categories</th>
                    <td>
                        <div id="child-category-container"
                             style="margin-bottom:10px;grid-gap: 10px;display: flex;flex-wrap: wrap"></div>
                        <p style="margin-top: 0;" class="description">Child categories will appear here dynamically.</p>
                    </td>
                </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero">🚀 Fetch Category</button>
            </p>
        </form>
    </div>
</div>

<?php

require 'pre-loader.php';

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


// Manage Post request
if (isset($_POST['parent_category_code'])) {
    $importer = new MPI_Importer();
    $api = new MPI_API();
    $parent_code = sanitize_text_field($_POST['parent_category_code']);
    $sync_type = $_POST['sync_type'] ?? '';


    // Get existing parent term
    $existing_parent = ww_tvc_get_term_data_by_tvc_code($parent_code);
    if (empty($existing_parent)) {
        ww_tvs_import_allowed_channel_product_cats();
        $existing_parent = ww_tvc_get_term_data_by_tvc_code($parent_code);
    }

    // prepare formate of childs
    $all_child_cats = array();
    if (isset($_POST['category_code']) && !empty($_POST['category_code']) && is_array($_POST['category_code']) && count($_POST['category_code']) > 0) {
        foreach ($_POST['category_code'] as $cat_code) {
            if (str_starts_with($cat_code, '{')) {
                $all_child_cats[] = json_decode(wp_unslash($cat_code), true);
            }
        }
    }

    // do save and update into db
    if (!empty($all_child_cats)) {
        $last_created_term = $existing_parent; // keep parent first always
        foreach ($all_child_cats as $index => $cat) {
            ww_tvc_print_r($last_created_term);
            $parent_id = $last_created_term->term_id;

            // Validate status
            if ($cat['Status'] != 'Valid') {
                if (isset($_POST['category_code'])) {
                    $categoryCode = $_POST['category_code'];
                } else {
                    $categoryCode = $_POST['parent_category_code'];
                }

                if ($term_id = ww_tvc_get_term_data_by_tvc_code(json_decode(wp_unslash($categoryCode[0]), true)['Code'])) {
                    $term_id = $term_id->term_id;
                    
                    $product_ids = get_posts([
                        'post_type'   => 'product',
                        'numberposts' => -1,
                        'fields'      => 'ids',
                        'tax_query'   => [
                            [
                                'taxonomy' => 'product_cat',
                                'field'    => 'term_id',
                                'terms'    => $term_id,
                            ],
                        ],
                    ]);
    
                    if (!empty($product_ids)) {
                        foreach ($product_ids as $product_id) {
                            wp_update_post([
                                'ID'          => $product_id,
                                'post_status' => 'draft', // disable product
                            ]);
                        }
                    }
    
                    // 2️⃣ Delete the category itself
                    wp_delete_term($term_id, 'product_cat');
                }
                
                // TODO fire action based on status
                tvc_sync_log("Skipped invalid category record  {$cat['Name']}");
                continue;
            }

            // Validate name and code
            $name = sanitize_text_field($cat['Name']);
            $code = sanitize_text_field($cat['Code']);
            $slug = sanitize_title($name);
            $existing = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'meta_query' => [
                    ['key' => '_tvc_product_cat_code', 'value' => $code],
                ],
                'number' => 1,
            ]);

            if (!empty($existing) && !is_wp_error($existing)) {
                $term_id = $existing[0]->term_id;
                wp_update_term($term_id, 'product_cat', [
                    'name' => $name,
                    'slug' => $slug,
                    'parent' => $parent_id,
                ]);
                tvc_sync_log("Updated category: {$name} (code {$code})");
            } else {
                $new_term = wp_insert_term($name, 'product_cat', [
                    'slug' => $slug,
                    'parent' => $parent_id,
                ]);
                if (is_wp_error($new_term)) {
                    tvc_sync_log("Failed to insert category: {$name} – " . $new_term->get_error_message());
                    continue;
                }
                $term_id = $new_term['term_id'];
                tvc_sync_log("Created category: {$name} (code {$code})");
            }

            // update latest data for last created term
            $last_created_term = get_term_by('id', $term_id, 'product_cat');
            update_term_meta($term_id, '_tvc_product_cat_code', $code);
        }
    }

//        ww_start_category_sync_now($parent_code, $existing_parent->term_id);;

}

