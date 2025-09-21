<div class="wrap">
    <h1 class="wp-heading-inline">📦 Product Importer By SKU</h1>
    <hr class="wp-header-end">

    <div class="card" style="max-width:700px; padding:20px; margin-top:20px;">
        <p class="description" style="margin-bottom:20px;">
            Enter SKU of the product you want to fetch and update. Click the "Fetch" button to retrieve the product details.
        </p>

        <form method="post" id="mpi-category-form">
            <?php wp_nonce_field('mpi_import_nonce'); ?>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="sku">Product Fetch by SKU</label></th>
                        <td>
                            <input type="text" id="sku" name="sku" style="min-width: 250px;"
                                placeholder="Enter SKU to fetch">
                            <button type="button" id="fetch_sku_btn" class="button">Fetch</button>
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
                    if (response.success) {
                        $('#ajax_result').html('✅ Product Updated');
                    } else {
                        $('#ajax_result').html('❌ ' + response.data);
                    }
                },
                error: function () {
                    $('#ajax_result').html('❌ Something went wrong.');
                }
            });
        });
    });
</script>

