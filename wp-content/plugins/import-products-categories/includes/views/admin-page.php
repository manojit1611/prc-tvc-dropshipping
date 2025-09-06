<div class="wrap">
    <h1>Product Importer</h1>
    <p>Select category to fetch products from API:</p>

    <form method="post" id="mpi-category-form">
        <?php wp_nonce_field('mpi_import_nonce'); ?>

        <!-- Parent Dropdown -->
        <select name="parent_category_code" id="parent_category" style="margin-bottom:10px;">
            <option value="">-- Select Parent Category --</option>
            <?php
            $parent_cats = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => 0,
            ]);
            if (!empty($parent_cats) && !is_wp_error($parent_cats)) {
                foreach ($parent_cats as $cat) {
                    if ($cat->slug === 'uncategorized') continue;
                    echo '<option value="' . esc_attr($cat->slug) . '">' . esc_html($cat->name) . '</option>';
                }
            }
            ?>
        </select>

        <!-- Container where child dropdowns will be added -->
        <div id="child-category-container"></div>

        <br><br>
        <button type="submit" class="button button-primary">Fetch Category & Products</button>
    </form>
</div>


<?php
if (isset($_POST['parent_category_code'])) {
    if (!empty($_POST['category_code'])) {
        $categoryCode = sanitize_text_field($_POST['category_code']);
    } else {
        $categoryCode = sanitize_text_field($_POST['parent_category_code']);
    }

    $importer = new MPI_Importer();
    $api = new MPI_API();

    $categories = $api->get_categories_from_api($categoryCode);

    if (!empty($categories['CateoryList'])) {
        if (empty($categories['CateoryList'][0]['ParentCode'])) {
            $importer->import_categories($categories);
        } else if (isset($_POST['parent_category_code']) && $_POST['category_code'] == '') {
            $importer->import_categories($categories);
        } else if (isset($_POST['category_code'])) {
            // echo "<pre>";
            // print_r($categories);
            // die;
            $importer->import_categories($categories);
        }

        // foreach ($categories['CateoryList'] as $categoryList) {
        //     if (!empty($categoryList['ParentCode'])) {
        //         // $categories = get_categories_from_api($categoryList['Code']);
        //         $imoportCategories = import_categories($categories);
        //         print_r($categoryList);
        //     }
        // }
        
    } else {
        $products = $api->get_products_by_category_code($categoryCode);
        $importer->get_detail_of_products($products);
    } 
}
