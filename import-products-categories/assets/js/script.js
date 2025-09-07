jQuery(document).ready(function ($) {
    // On change of any category dropdown
    $(document).on('change', 'select', function () {
        let slug = $(this).val();
        let container = $('#child-category-container');

        // Remove all next dropdowns after this one
        $(this).nextAll('select').remove();

        if (slug) {
            $.post(mpi_ajax.url, {
                action: 'mpi_get_child_categories',
                slug: slug,
                _ajax_nonce: mpi_ajax.nonce
            }, function (response) {
                console.log(response);
                // container.empty(); // Clear previous content

                if (response.success) {
                    container.append(response.data);
                }
            });
        }
    });
});
