<div class="wrap">
    <h1 class="wp-heading-inline">📦 Tvc Product Importer</h1>
    <hr class="wp-header-end">

    <?php
        if (isset($_POST['sync_type']) && $_POST['sync_type'] == 'products') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Products data has been updated successfully.</p></div>';
        }

        if (isset($_POST['sync_type']) && $_POST['sync_type'] == 'product_cat') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Category data has been updated successfully.</p></div>';
        }
    ?>

    <div class="importer card" style="max-width:700px; padding:20px; margin-top:20px;">
        <p class="description" style="margin-bottom:20px;">
            Select what you want to sync from the API. Choose a sync type and category, then click fetch.
        </p>

        <form method="post" id="mpi-category-form">
            <?php wp_nonce_field('mpi_import_nonce'); ?>

            <table class="form-table">
                <tbody>
                <!-- <tr>
                    <th scope="row"><label for="sync_type">Sync Type</label></th>
                    <td>
                        <select required name="sync_type" id="sync_type" style="min-width: 250px;">
                            <option value="products">Products</option>
                            <option value="product_cat">Product Categories</option>
                        </select>
                        <p class="description">Choose whether to import products or just categories.</p>
                    </td>
                </tr> -->
                <input type="hidden" value="products" name="sync_type" />

                <tr>
                    <th scope="row"><label for="parent_category">Parent Category</label></th>
                    <td>
                        <select required name="tvc_parent_category_code" id="parent_category" style="min-width: 250px;">
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
                        <div id="child-category-container" style="margin-top:10px;grid-gap: 10px;display: flex;"></div>
                        <p class="description">Child categories will appear here dynamically.</p>
                    </td>
                </tr>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero">🚀 Fetch Products</button>
            </p>
        </form>
    </div>
</div>

<?php

require 'pre-loader.php';

if (isset($_POST['tvc_parent_category_code'])) {
    if (isset($_POST['category_code']) && !empty($_POST['category_code'])) {
        $categoryCode = sanitize_text_field($_POST['category_code']);
    } else {
        $categoryCode = sanitize_text_field($_POST['tvc_parent_category_code']);
    }

    $importer = new MPI_Importer();
    $api = new MPI_API();

    // import Defaults
    $parent_code = $categoryCode;
    $sync_type = $_POST['sync_type'] ?? '';
    if ($sync_type == 'products') {
        ww_start_product_import($categoryCode);
    } else {
        echo "Product Sync ype is not set";
    }
}

