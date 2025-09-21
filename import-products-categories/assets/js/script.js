jQuery(document).ready(function ($) {
  // On change of any category dropdown
  $(document).on("change", ".importer select", function () {
    let slug = $(this).val();
    let container = $("#child-category-container");
    // Remove all next dropdowns after this one
    $(this).nextAll("select").remove();
    // container.find('select').not(this).remove();

    if ($(this).attr("id") === "parent_category") {
      container.empty(); // Clear previous dropdowns
    }

    if (slug) {
      $.post(
        mpi_ajax.url,
        {
          action: "mpi_get_child_categories",
          slug: slug,
          _ajax_nonce: mpi_ajax.nonce,
        },
        function (response) {
          if (response.success) {
            container.append(response.data);
          } else {
            console.log(response);
            // alert(response.error_msg);
          }
        }
      );
    }
  });
});
