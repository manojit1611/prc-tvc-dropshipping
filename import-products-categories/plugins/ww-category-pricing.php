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

    // 💱 Convert USD → AUD
    $usd_to_aud_rate = 1.53; // 🔹 Set your live conversion rate here
    $price = $price * $usd_to_aud_rate;

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
        $id  = isset($_POST['ww_rule_id']) ? intval($_POST['ww_rule_id']) : null;
        $cat = intval($_POST['ww_category']); // final selected category

        $new_rule = [
            'cat'   => $cat,
            'type'  => sanitize_key($_POST['ww_markup_type'] ?? 'percent'),
            'value' => floatval($_POST['ww_value'] ?? 0),
            'ship'  => floatval($_POST['ww_ship'] ?? 0)
        ];

        // Get existing rules (default empty array if none)
        $rules = get_option('ww_rules', []);

        // ✅ Check if this category already exists
        $existing_index = null;
        foreach ($rules as $index => $rule) {
            if (isset($rule['cat']) && $rule['cat'] === $cat) {
                $existing_index = $index;
                break;
            }
        }

        // ✅ If found, update; else add new
        if (!is_null($existing_index)) {
            $rules[$existing_index] = $new_rule;
        } else {
            $rules[] = $new_rule;
        }

        update_option('ww_rules', $rules);

        echo '<div class="updated"><p>Rule saved successfully.</p></div>';
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
    <style>
        .ww-form {
            max-width: 480px;
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 10px;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            font-family: "Segoe UI", Roboto, Arial, sans-serif;
        }

        .ww-form .ww-field {
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
        }

        .ww-form .ww-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .ww-form .ww-input,
        .ww-form select.ww-category-select,
        .ww-form #ww_markup_type {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            max-width: 100%;
        }

        .ww-form .ww-input:focus,
        .ww-form select:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
            outline: none;
        }

        .ww-submit-wrap {
            text-align: center;
            margin-top: 20px;
        }

        .ww-form .ww-button {
            background-color: #2271b1 !important;
            border: none !important;
            border-radius: 6px;
            padding: 10px 25px;
            font-size: 15px;
            transition: background-color 0.2s, transform 0.1s;
        }

        .ww-form .ww-button:hover {
            background-color: #135e96 !important;
            transform: translateY(-1px);
        }

        .select2 {
            /* width: 100% !important; */
            margin-bottom: 10px;
        }
    </style>

    <div class="wrap">
        <h1>WW Category Pricing Rules</h1>
        <h2>Add / Update Rule</h2>
        <form method="post" id="ww_form" class="ww-form">
            <?php wp_nonce_field('ww_save_action', 'ww_nonce'); ?>
            <input type="hidden" name="ww_rule_id" id="ww_rule_id" value="">

            <div id="ww_category_selects" class="ww-field">
                <label for="ww_category" class="ww-label">Category</label>
                <select name="ww_category[]" class="ww-category-select" id='parent_category' data-level="0">
                    <option value="">— Select Parent —</option>
                    <?php foreach ($parents as $p) {
                        if ($p->name == 'Uncategorized') continue;
                        echo "<option value='{$p->term_id}'>{$p->name}</option>";
                    }
                    ?>
                </select>
            </div>

            <div id='child_categories'></div>

            <input type="hidden" name="ww_category" id="ww_category">

            <div class="ww-field">
                <label for="ww_markup_type" class="ww-label">Markup Type</label>
                <select name="ww_markup_type" id="ww_markup_type" class="ww-input">
                    <option value="percent">Percent</option>
                    <option value="fixed">Fixed</option>
                </select>
            </div>

            <div class="ww-field">
                <label for="ww_value" class="ww-label">Value</label>
                <input type="number" step="0.01" name="ww_value" id="ww_value" class="ww-input" placeholder="Enter value">
            </div>

            <div class="ww-submit-wrap">
                <input type="submit" name="ww_save" class="button button-primary ww-button" value="Save Rule">
            </div>
        </form>

        <?php
        // --- Pagination setup ---
        $per_page = 50; // Number of rules per page
        $total_rules = count($rules);
        $total_pages = ceil($total_rules / $per_page);

        // Get current page (default = 1)
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        // Slice the rules array to show only current page items
        $offset = ($current_page - 1) * $per_page;
        $paged_rules = array_slice($rules, $offset, $per_page);
        ?>

        <h2>All Rules</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paged_rules)): ?>
                    <tr>
                        <td colspan="5">No rules</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paged_rules as $i => $r): ?>
                        <?php
                        $term = get_term($r['cat'], 'product_cat');
                        $cat_name = $term ? esc_html($term->name) : '—';
                        ?>
                        <tr>
                            <td><?= $cat_name; ?></td>
                            <td><?= esc_html($r['type']); ?></td>
                            <td><?= esc_html($r['value']); ?></td>
                            <td>
                                <a href="<?= esc_url(wp_nonce_url(admin_url('admin.php?page=ww-category-pricing&delete=' . ($offset + $i)), 'ww_delete_rule', 'ww_nonce')); ?>" class="ww-delete">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'prev_text' => __('« Previous'),
                        'next_text' => __('Next »'),
                        'total'     => $total_pages,
                        'current'   => $current_page,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        jQuery(function($) {
            // Initialize first select
            $(".ww-category-select").select2();

            $(document).on("change", ".ww-category-select", function() {
                let $current = $(this); // reference to current select
                let val = $current.val();
                $("#ww_category").val(val);

                if ($(this).data('level') == 0) {
                    $("#child_categories").empty();
                }

                if (!val && $(this).data('level') == 1) {
                    $("#child_categories").find(".select2").last().remove();
                }

                if (val) {
                    // Fetch children dynamically
                    $.get(ajaxurl, {
                        action: 'ww_get_children',
                        parent: val
                    }, function(data) {
                        if (data.length) {
                            let sel = $("<select>").addClass("ww-category-select").attr("data-level", parseInt($current.data("level")) + 1);
                            sel.append($("<option>").text("— Select —").val(""));
                            $.each(data, function(i, t) {
                                sel.append($("<option>").val(t.term_id).text(t.name));
                            });

                            let container = $("#child_categories");

                            let existingSelects = container.find(".select2").length;
                            if (existingSelects < 2) {
                                container.append(sel);
                            } else if (existingSelects === 2) {
                                container.find(".select2").last().remove();
                                container.append(sel);
                            }

                            sel.select2();
                        }
                    }, "json");
                }
            });

            // Delete rule
            $(document).on("click", ".ww-delete", function(e) {
                e.preventDefault();
                if (confirm("Delete this rule?")) location.href = $(this).attr("href");
            });
            // Expose rules to JS
            var ww_rules = <?php echo json_encode(array_values($rules)); ?>;
        });
    </script>
<?php
}
