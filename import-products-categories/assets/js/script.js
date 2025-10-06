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
      ww_start_preloader();
      $.post(
        mpi_ajax.url,
        {
          action: "mpi_get_child_categories",
          slug: slug,
          _ajax_nonce: mpi_ajax.nonce,
        },
        function (response) {
          ww_stop_preloader();
          if (response.success) {
            container.append(response.data);
            $("select.select2").select2();
          } else {
            console.log(response);
            // alert(response.error_msg);
          }
        }
      );
    }
  });
});

/**
 * Fetch child categories via AJAX.
 * Returns a Promise that resolves with the response.
 */
function ww_mpi_get_child_categories(formData = {}) {
  const defaultVars = {
    action: "mpi_get_child_categories",
    slug: "",
    _ajax_nonce: mpi_ajax.nonce, // nonce passed via wp_localize_script
  };

  // Merge defaults with any supplied data
  const final_formData = Object.keys(formData).length
    ? { ...defaultVars, ...formData }
    : defaultVars;

  // jQuery.post returns a promise-like jqXHR
  return jQuery.post(mpi_ajax.url, final_formData);
}

/**
 * On change of the parent category dropdown,
 * fetch and render the next level of child categories.
 */
jQuery(document).on(
  "change",
  "#mpi-tvc-category-form select",
  async function () {
    let is_parent = false;
    if (jQuery(this).attr("name") === "parent_category_code") {
      is_parent = true;
    }
    let formEl = jQuery("#mpi-tvc-category-form");
    let slug = jQuery(this).val();
    let child_containerEl = formEl.find("#child-category-container");
    // Remove all selecting after this one and clear the child container
    jQuery(this).nextAll("select").remove();
    // if parent then empty all childs
    if (is_parent) {
      child_containerEl.empty();
    }

    if (!slug) {
      console.log("Parent Category is not selected");
      return;
    }

    let apiParams = {
      slug: slug,
      mode: "tvc_api",
    };

    ww_start_preloader();
    try {
      // Wait for AJAX response
      let response = await ww_mpi_get_child_categories(apiParams);
      ww_stop_preloader();

      if (response.success) {
        let existingSelects = child_containerEl.find(".select2").length;

        if (existingSelects < 3) {
          child_containerEl.append(response.data);
        } else if (existingSelects === 3) {
          child_containerEl.find(".select2").last().remove();
          child_containerEl.append(response.data);
        }

        jQuery("select.select2").select2();
      } else {
        formEl.append(
          '<div style="color: #FF0000FF" class="ww-error"><p>' +
            response.msg +
            "</p></div>"
        );
        setTimeout(function () {
          formEl.find(".ww-error").remove();
        }, 3000);
        console.log("Error or empty result:", response);
      }
    } catch (err) {
      console.error("AJAX request failed:", err);
    }
  }
);
