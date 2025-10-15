<?php

// Add fields on Add New form
add_action('device_heirarchy_add_form_fields', 'dh_add_term_fields', 10, 2);
function dh_add_term_fields($taxonomy)
{
?>
    <div class="form-field term-image-wrap">
        <label for="term_image">Image</label>
        <input type="hidden" id="term_image" name="term_image" value="">
        <div id="term-image-preview" style="margin-top:10px;"></div>
        <button type="button" class="button upload-term-image">Upload Image</button>
    </div>

    <div class="form-field term-redirect-wrap">
        <label for="term_redirect_url">Redirect URL</label>
        <input type="url" name="term_redirect_url" id="term_redirect_url" value="" placeholder="https://example.com">
    </div>
<?php
}

// Add fields on Edit form
add_action('device_heirarchy_edit_form_fields', 'dh_edit_term_fields', 10, 2);
function dh_edit_term_fields($term, $taxonomy)
{
    $image_id = get_term_meta($term->term_id, 'term_image', true);
    $redirect_url = get_term_meta($term->term_id, 'term_redirect_url', true);
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
?>
    <tr class="form-field term-image-wrap">
        <th scope="row"><label for="term_image">Image</label></th>
        <td>
            <input type="hidden" id="term_image" name="term_image" value="<?php echo esc_attr($image_id); ?>">
            <div id="term-image-preview" style="margin-top:10px;">
                <?php if ($image_url): ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width:100px; height:auto;">
                <?php endif; ?>
            </div>
            <button type="button" class="button upload-term-image">Upload Image</button>
            <button type="button" class="button remove-term-image">Remove</button>
        </td>
    </tr>

    <tr class="form-field term-redirect-wrap">
        <th scope="row"><label for="term_redirect_url">Redirect URL</label></th>
        <td>
            <input type="url" name="term_redirect_url" id="term_redirect_url" value="<?php echo esc_attr($redirect_url); ?>" placeholder="https://example.com">
        </td>
    </tr>
<?php
}

add_action('admin_enqueue_scripts', 'dh_enqueue_media_uploader');
function dh_enqueue_media_uploader($hook)
{
    // Only load on taxonomy edit/add pages for device_heirarchy
    if (isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'device_heirarchy') {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }
}


// Save term meta
add_action('created_device_heirarchy', 'dh_save_term_meta', 10, 2);
add_action('edited_device_heirarchy', 'dh_save_term_meta', 10, 2);

function dh_save_term_meta($term_id)
{
    if (isset($_POST['term_image'])) {
        update_term_meta($term_id, 'term_image', intval($_POST['term_image']));
    }
    if (isset($_POST['term_redirect_url'])) {
        update_term_meta($term_id, 'term_redirect_url', esc_url_raw($_POST['term_redirect_url']));
    }
}


add_action('admin_footer', 'dh_term_image_upload_script');
function dh_term_image_upload_script()
{
    if (!isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'device_heirarchy') {
        return;
    }
?>
    <script>
        jQuery(document).ready(function($) {
            let frame;
            $(document).on('click', '.upload-term-image', function(e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: 'Select or Upload Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false
                });
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $('#term_image').val(attachment.id);
                    $('#term-image-preview').html('<img src="' + attachment.url + '" style="max-width:100px; height:auto;">');
                });
                frame.open();
            });

            $(document).on('click', '.remove-term-image', function(e) {
                e.preventDefault();
                $('#term_image').val('');
                $('#term-image-preview').html('');
            });
        });
    </script>
<?php
}


add_filter('manage_edit-device_heirarchy_columns', function ($columns) {
    $columns['image'] = 'Image';
    return $columns;
});

add_filter('manage_device_heirarchy_custom_column', function ($content, $column_name, $term_id) {
    if ($column_name === 'image') {
        $image_id = get_term_meta($term_id, 'term_image', true);
        $image_url = $image_id ? wp_get_attachment_thumb_url($image_id) : '';
        if ($image_url) {
            $content = '<img src="' . esc_url($image_url) . '" style="width:40px;height:auto;">';
        }
    }
    return $content;
}, 10, 3);

add_action('wp_ajax_get_device_children', 'dh_get_device_children');
add_action('wp_ajax_nopriv_get_device_children', 'dh_get_device_children');

add_shortcode('device_hierarchy_browser', function () {
    ob_start();
?>

    <style>
        .device-level {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .device-card {
            border: 1px solid #ccc;
            border-radius: 10px;
            text-align: center;
            width: 150px;
            padding: 10px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .device-card:hover {
            background: #f8f8f8;
        }

        .device-back-btn {
            background: #1e293b;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 15px;
        }
    </style>

    <div id="device-browser">
        <div id="breadcrumb" class="text-sm text-gray-600 mb-4 flex flex-wrap justify-center gap-1"></div>

        <div id="device-level-container"></div>
    </div>

    <script>
        jQuery(function($) {
            let parentStack = [];
            let currentLevel = 1;


            function loadChildren(parent_id = 0, level = 1) {
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'get_device_children',
                    parent_id: parent_id,
                    level: level
                }, function(response) {
                    if (response.success) {
                        const container = $('#device-level-container');
                        container.html(response.data);

                        // Back button
                        $('#device-back-btn').on('click', function() {
                            const prev = parentStack.pop() || {
                                id: 0,
                                level: 1
                            };
                            loadChildren(prev.id, prev.level);
                        });

                        // Device card click
                        $('.device-card').on('click', function() {
                            const id = $(this).data('id');
                            const url = $(this).data('url');
                            const hasChildren = $(this).data('has-children') == 1;

                            if (url) {
                                window.location.href = url;
                            } else if (hasChildren) {
                                parentStack.push({
                                    id: parent_id,
                                    level: level
                                });
                                loadChildren(id, level + 1);
                            }
                        });
                    }
                });
            }

            loadChildren(0, 1);
        });
    </script>

<?php
    return ob_get_clean();
});

add_action('wp_ajax_get_device_children', 'dh_get_device_children');
add_action('wp_ajax_nopriv_get_device_children', 'dh_get_device_children');

function dh_get_device_children()
{
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $level = intval($_POST['level'] ?? 1);

    // Determine the header title based on hierarchy level
    $headings = get_device_heirarchy_types();

    $heading = $headings[$level] ?? 'Select Option';

    $children = get_terms([
        'taxonomy' => 'device_heirarchy',
        'parent'   => $parent_id,
        'hide_empty' => false,
    ]);

    ob_start(); ?>

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-gray-800"><?php echo esc_html($heading); ?></h2>
    </div>

    <?php if ($parent_id !== 0): ?>
        <div class="mb-4">
            <button id="device-back-btn" class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                ← Back
            </button>
        </div>
<?php endif;

    if (empty($children)) {
        echo '<p>No more devices.</p>';
    } else {
        echo '<div class="device-level">';
        foreach ($children as $child) {
            $image_id  = get_term_meta($child->term_id, 'term_image', true);
            $custom_url = get_term_meta($child->term_id, 'term_redirect_url', true);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

            $has_children = get_terms([
                'taxonomy' => 'device_heirarchy',
                'parent'   => $child->term_id,
                'hide_empty' => false,
                'fields'   => 'ids'
            ]) ? true : false;

            echo '<div class="device-card" 
                data-id="' . esc_attr($child->term_id) . '" 
                data-has-children="' . ($has_children ? '1' : '0') . '" 
                data-url="' . esc_url($custom_url) . '">';

            if ($image_url) {
                echo '<img src="' . esc_url($image_url) . '" style="width:100px;height:100px;object-fit:contain;">';
            }

            echo '<p>' . esc_html($child->name) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    $html = ob_get_clean();
    wp_send_json_success($html);
}

?>