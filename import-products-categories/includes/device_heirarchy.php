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
