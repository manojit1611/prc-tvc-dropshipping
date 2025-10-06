<?php
/*
 * WW Category Pricing Rules (Fixed)
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 *  Admin Menu & Assets
 * ---------------------------------------------------------------------- */

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'woocommerce_page_ww-category-pricing') return;
    wp_enqueue_style('ww_select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('ww_select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
});

/* -------------------------------------------------------------------------
 *  AJAX: Fetch Child Categories
 * ---------------------------------------------------------------------- */
add_action('wp_ajax_ww_get_children', function () {
    $parent = intval($_GET['parent']);
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $parent]);
    wp_send_json($terms);
});

/* -------------------------------------------------------------------------
 *  Frontend Price Adjustment
 * ---------------------------------------------------------------------- */
add_filter('woocommerce_product_get_price', 'ww_adjust_price', 20, 2);
add_filter('woocommerce_product_get_regular_price', 'ww_adjust_price', 20, 2);
function ww_adjust_price($price, $product)
{
    $rules = get_option('ww_rules', []);
    if (!is_array($rules)) return $price;

    // add Extra Shipment Price
    $shipment_price = $product->get_meta('tvc_shipping_cost') ?? 0;
    if ($shipment_price) {
        $price += $shipment_price;
    }

    $product_cats = wc_get_product_term_ids($product->get_id(), 'product_cat');
    foreach ($rules as $r) {
        if (isset($r['cat']) && in_array((int)$r['cat'], $product_cats, true)) {
            $val = floatval($r['value']);
            $ship = floatval($r['ship']);
            $price += ($r['type'] == 'percent') ? $price * ($val / 100) : $val;
            $price += $ship;
            break;
        }
    }
    return $price;
}

/* -------------------------------------------------------------------------
 *  Admin Page
 * ---------------------------------------------------------------------- */
function ww_admin_category_pricing_page()
{
    $rules = get_option('ww_rules', []);
    if (!is_array($rules)) $rules = [];

    // Save form
    if (isset($_POST['ww_save']) && check_admin_referer('ww_save_action', 'ww_nonce')) {
        $id = isset($_POST['ww_rule_id']) ? intval($_POST['ww_rule_id']) : null;
        $cat = intval($_POST['ww_category']); // final selected category

        $new_rule = [
            'cat' => $cat,
            'type' => sanitize_key($_POST['ww_markup_type'] ?? 'percent'),
            'value' => floatval($_POST['ww_value'] ?? 0),
            'ship' => floatval($_POST['ww_ship'] ?? 0)
        ];
        if (is_null($id)) $rules[] = $new_rule; else $rules[$id] = $new_rule;
        update_option('ww_rules', $rules);
        echo '<div class="updated"><p>Rule saved.</p></div>';
    }

    // Delete
    if (isset($_GET['delete']) && check_admin_referer('ww_delete_rule', 'ww_nonce')) {
        $id = intval($_GET['delete']);
        if (isset($rules[$id])) {
            unset($rules[$id]);
            update_option('ww_rules', array_values($rules));
        }
        echo '<div class="updated"><p>Rule deleted.</p></div>';
    }

    // Get top-level parents
    $parents = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0]);

    ?>
    <div class="wrap">
        <h1>WW Category Pricing Rules</h1>
        <h2>Add / Update Rule</h2>
        <form method="post" id="ww_form">
            <?php wp_nonce_field('ww_save_action', 'ww_nonce'); ?>
            <input type="hidden" name="ww_rule_id" id="ww_rule_id" value="">
            <div id="ww_category_selects">
                <select name="ww_category[]" class="ww-category-select" data-level="0">
                    <option value="">— Select Parent —</option>
                    <?php foreach ($parents as $p) echo "<option value='{$p->term_id}'>{$p->name}</option>"; ?>
                </select>
            </div>
            <input type="hidden" name="ww_category" id="ww_category">
            <select name="ww_markup_type" id="ww_markup_type">
                <option value="percent">Percent</option>
                <option value="fixed">Fixed</option>
            </select>
            <input type="number" step="0.01" name="ww_value" id="ww_value" placeholder="Value">
            <input type="number" step="0.01" name="ww_ship" id="ww_ship" placeholder="Shipment Cost">
            <input type="submit" name="ww_save" class="button button-primary" value="Save Rule">
        </form>

        <h2>All Rules</h2>
        <table class="widefat">
            <thead>
            <tr>
                <th>Category</th>
                <th>Type</th>
                <th>Value</th>
                <th>Shipment</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rules)) echo '<tr><td colspan="5">No rules</td></tr>';
            else foreach ($rules as $i => $r) {
                $term = get_term($r['cat'], 'product_cat');
                $cat_name = $term ? $term->name : '—';
                echo "<tr>
                    <td>{$cat_name}</td>
                    <td>{$r['type']}</td>
                    <td>{$r['value']}</td>
                    <td>{$r['ship']}</td>
                    <td>
                        <a href='" . wp_nonce_url(admin_url('admin.php?page=ww-category-pricing&delete=' . $i), 'ww_delete_rule', 'ww_nonce') . "' class='ww-delete'>Delete</a>
                    </td>
                </tr>";
            } ?>
            </tbody>
        </table>
    </div>

    <script>
        jQuery(function ($) {
            // Initialize first select
            $(".ww-category-select").select2({width: "200px"});

            $(document).on("change", ".ww-category-select", function () {
                let $current = $(this); // reference to current select
                let val = $current.val();
                $("#ww_category").val(val);

                // Remove all lower-level selects to prevent duplicates
                $current.nextAll(".ww-category-select").remove();

                if (val) {
                    // Fetch children dynamically
                    $.get(ajaxurl, {action: 'ww_get_children', parent: val}, function (data) {
                        if (data.length) {
                            let sel = $("<select>").addClass("ww-category-select").attr("data-level", parseInt($current.data("level")) + 1);
                            sel.append($("<option>").text("— Select —").val(""));
                            $.each(data, function (i, t) {
                                sel.append($("<option>").val(t.term_id).text(t.name));
                            });
                            $("#ww_category_selects").append(sel);
                            sel.select2({width: "200px"});
                        }
                    }, "json");
                }
            });

            // Delete rule
            $(document).on("click", ".ww-delete", function (e) {
                e.preventDefault();
                if (confirm("Delete this rule?")) location.href = $(this).attr("href");
            });


            // Expose rules to JS
            var ww_rules = <?php echo json_encode(array_values($rules)); ?>;
        });
    </script>
    <?php
}
