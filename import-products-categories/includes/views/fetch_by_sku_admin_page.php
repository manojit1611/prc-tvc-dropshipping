<div class="wrap">
    <div class="card" style="max-width:700px; padding:20px; margin-top:20px;">
        <form method="post" id="mpi-category-form">
            <?php wp_nonce_field('mpi_import_nonce'); ?>

            <table class="form-table">
                <tbody>
                <tr>
                    <th colspan="2" style="padding-top: 0">
                        <h1 style="padding: 0" class="wp-heading-inline"><?php echo __('Pull ' . TVC_PLUGIN_NAME_PREFIX) . ' Product By SKU' ?></h1>
                    </th>
                </tr>
                <tr>
                    <th style="padding: 0" colspan="2">
                        <p class="description">
                            <?php echo __('Enter the SKU of the product you want to fetch and update. Click the ‘Fetch’ button to retrieve the product details'); ?>
                        </p>
                    </th>
                </tr>
                <tr>
                    <th scope="row"><label for="sku"><?php echo __('TVC SKU'); ?></label></th>
                    <td>
                        <input type="text" id="sku" name="sku" style="min-width: 250px;"
                               placeholder="Enter <?php echo TVC_PLUGIN_NAME_PREFIX ?> SKU to fetch">
                        <button type="button" id="fetch_sku_btn" class="button"><?php echo __('Fetch'); ?></button>
                        <div id="ajax_result" style="margin-top:10px; font-weight:bold;"></div>
                    </td>
                </tr>
                </tbody>
            </table>
        </form>
    </div>
</div>

<?php require 'pre-loader.php'; ?>

<script>
    jQuery(document).ready(function ($) {
        $('#fetch_sku_btn').on('click', function () {
            var sku = $('#sku').val();

            if (!sku) {
                alert('Please enter a SKU');
                return;
            }

            $('#ajax_result').text('Fetching...');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'GET',
                data: {
                    action: 'product_fetch',
                    sku: sku,
                    redirect: false
                },
                success: function (response) {
                    console.log(response);
                    if (response.success) {
                        $('#ajax_result').html(response.data.msg);
                    } else {
                        $('#ajax_result').html(response.data.msg);
                    }
                },
                error: function () {
                    $('#ajax_result').html('Something went wrong.');
                }
            });
        });
    });
</script>

