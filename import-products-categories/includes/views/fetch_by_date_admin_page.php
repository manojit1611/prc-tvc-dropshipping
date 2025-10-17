<div class="wrap">
    <h1 class="wp-heading-inline">📦 Product Importer By Date</h1>
    <hr class="wp-header-end">

    <div class="card" style="max-width:700px; padding:20px; margin-top:20px;">
        <p class="description" style="margin-bottom:20px;">
            Enter the date range for the products you want to fetch and update. Click the "Fetch" button to retrieve the product details.
        </p>

        <form method="post" id="mpi-category-form">
            <?php wp_nonce_field('mpi_import_nonce'); ?>

            <table class="form-table">
                <tbody>
                    <tr>
                        <td style="display: flex;flex-direction: column;align-items: center;justify-content: start;">
                            <label>Start Date
                                <input type="datetime-local" id="start_date" name="start_date" style="min-width: 250px;"
                                    placeholder="Enter Start Date to fetch">
                            </label>

                            <label>End Date
                                <input type="datetime-local" id="end_date" name="end_date" style="min-width: 250px;margin-top: 10px;"
                                    placeholder="Enter End Date to fetch">
                            </label>
                            <button type="button" id="fetch_date_btn" class="button" style='margin-top: 10px;'>Fetch</button>
                            <div id="ajax_result" style="margin-top:10px; font-weight:bold; margin-top: 10px;"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
</div>

<?php require 'pre-loader.php'; ?>

<script>
    jQuery(document).ready(function($) {
        $('#fetch_date_btn').on('click', function() {
            var start_date = $('#start_date').val();
            var end_date = $('#end_date').val();

            if (!start_date || !end_date) {
                alert('Please enter both start and end dates');
                return;
            }

            $('#ajax_result').text('Fetching...');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'GET',
                data: {
                    action: 'product_fetch_by_date',
                    start_date: start_date,
                    end_date: end_date,
                    redirect: false
                },
                success: function(response) {
                    if (response.success) {
                        $('#ajax_result').html(response.data.message);
                    } else {
                        $('#ajax_result').html('❌ ' + response.data.message);
                    }
                },
                error: function() {
                    $('#ajax_result').html('❌ Something went wrong.');
                }
            });
        });
    });
</script>