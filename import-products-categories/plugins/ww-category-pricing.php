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

    $price = floatval($price);

    // Add extra shipment price from product meta
    $shipment_price = floatval($product->get_meta('tvc_shipping_cost') ?? 0);
    $price += $shipment_price;

    $product_cats = wc_get_product_term_ids($product->get_id(), 'product_cat');
    if (empty($product_cats)) return $price;

    $matched_rule = null;
    $matched_depth = -1;

    foreach ($rules as $r) {
        if (!isset($r['cat'])) continue;

        $rule_cat = (int) $r['cat'];

        // Get all children (to ensure parent applies to subcategories)
        $child_cats = get_term_children($rule_cat, 'product_cat');
        $all_cats = array_merge([$rule_cat], $child_cats);

        // If the product belongs to any of these categories
        if (array_intersect($product_cats, $all_cats)) {
            // Determine how deep the matching category is (for priority)
            $depth = ww_get_category_depth($rule_cat);

            // Keep the deepest (most specific) match only
            if ($depth > $matched_depth) {
                $matched_rule = $r;
                $matched_depth = $depth;
            }
        }
    }

    // print_r($matched_rule);
    // die;

    if ($matched_rule) {
        $val  = floatval($matched_rule['value']);
        $ship = floatval($matched_rule['ship']);

        if ($matched_rule['type'] === 'percent') {
            $price += $price * ($val / 100);
        } else {
            $price += $val;
        }

        $price += $ship;
    } else {
        $price += 5.00; // Default shipping if no rule matched
    }

    return $price;
}

/**
 * Helper: Get category depth (how deep in hierarchy it is)
 */
function ww_get_category_depth($cat_id)
{
    $depth = 0;
    while ($cat_id) {
        $term = get_term($cat_id, 'product_cat');
        if (!$term || !$term->parent) break;
        $cat_id = $term->parent;
        $depth++;
    }
    return $depth;
}

add_action('wp_ajax_ww_update_rule', 'ww_update_rule_callback');
function ww_update_rule_callback()
{
    // check_ajax_referer('ww_update_rule_nonce');

    $index = intval($_POST['ww_rule_index']);
    $value = floatval($_POST['ww_value']);

    $rules = get_option('ww_rules', []);
    if (!isset($rules[$index])) {
        wp_send_json_error('Rule not found.');
    }

    // Update only the value (or you can update type, ship, etc.)
    $rules[$index]['value'] = $value;

    update_option('ww_rules', $rules);

    // Rebuild HTML for that cell
    $html = '<span>' . esc_html($value) . '</span>';

    wp_send_json_success(['html' => $html]);
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

        if (!empty($_POST['ww_rule_index'])) {
            $edit_index = intval($_POST['ww_rule_index']);
            if (isset($rules[$edit_index])) {
                $rules[$edit_index] = $new_rule;
            }
        } else {
            // Otherwise, add/update based on category duplication
            $existing_index = null;
            foreach ($rules as $index => $rule) {
                if (isset($rule['cat']) && $rule['cat'] === $cat) {
                    $existing_index = $index;
                    break;
                }
            }

            if (!is_null($existing_index)) {
                $rules[$existing_index] = $new_rule;
            } else {
                $rules[] = $new_rule;
            }
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
                    <option value="fixed">Fixed</option>
                    <option value="percent">Percent</option>
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
                            <td id='ww_value_<?= esc_attr($offset + $i); ?>'><?= esc_html($r['value']); ?></td>
                            <td>
                                <a href="#" class="ww-edit"
                                    data-index="<?= esc_attr($offset + $i); ?>"
                                    data-cat="<?= esc_attr($r['cat']); ?>"
                                    data-type="<?= esc_attr($r['type']); ?>"
                                    data-value="<?= esc_attr($r['value']); ?>"
                                    data-ship="<?= esc_attr($r['ship'] ?? 0); ?>">Edit</a> |
                                <a href="<?= esc_url(wp_nonce_url(admin_url('admin.php?page=ww-category-pricing&delete=' . ($offset + $i)), 'ww_delete_rule', 'ww_nonce')); ?>" class="ww-delete">Delete</a>
                            </td>

                            <!-- <td>
                                <a href="<?= esc_url(wp_nonce_url(admin_url('admin.php?page=ww-category-pricing&delete=' . ($offset + $i)), 'ww_delete_rule', 'ww_nonce')); ?>" class="ww-delete">Delete</a>
                            </td> -->
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

            $(document).on('click', '#save_btn', function(e) {
                e.preventDefault();

                const $row = $(this).closest('div');
                const ruleIndex = $(this).closest('td').attr('id').replace('ww_value_', '');
                const value = $row.find('input[name="ww_rule_index"]').val();

                $.ajax({
                    url: ajaxurl, // WordPress admin AJAX endpoint
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ww_update_rule',
                        ww_rule_index: ruleIndex,
                        ww_value: value,
                        // _ajax_nonce: ww_ajax_object.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Rule updated successfully!');
                            // Replace input with updated value
                            $('#ww_value_' + ruleIndex).html(response.data.html);
                        } else {
                            alert('❌ Update failed: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('⚠️ Error connecting to server.');
                    }
                });
            });

            $(document).on('click', '#cancel_btn', function(e) {
                e.preventDefault();
                var index = $(this).data('index');
                var value = $(this).data('value');

                $('#ww_value_' + index).html(value);
            });

            var ww_rules = <?php echo json_encode(array_values($rules)); ?>;
            $(document).on("click", ".ww-edit", function(e) {
                e.preventDefault();

                var index = $(this).data('index');
                var value = $(this).data('value');

                var field = `
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <input 
                            type="text" 
                            name="ww_rule_index" 
                            value="${value}" 
                            style="
                                padding: 5px 8px; 
                                border: 1px solid #ccc; 
                                border-radius: 4px; 
                                font-size: 13px;
                                width: 80px;
                            "
                        />
                        <button 
                            id="save_btn" 
                            style="
                                background: #0073aa; 
                                color: #fff; 
                                border: none; 
                                border-radius: 4px; 
                                padding: 5px 10px; 
                                font-size: 13px;
                                cursor: pointer;
                            "
                        >
                            Update
                        </button>
                        <button 
                            id="cancel_btn"
                            data-index="${index}"
                            data-value="${value}"
                            style="
                                background: #ccc; 
                                color: #000; 
                                border: none; 
                                border-radius: 4px; 
                                padding: 5px 10px; 
                                font-size: 13px;
                                cursor: pointer;
                            "
                        >
                            Cancel
                        </button>
                    </div>
                `;

                $('#ww_value_' + index).html(field);
            });
        });
    </script>
<?php
}
