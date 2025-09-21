<div id="mpi-preloader" style="
    display:none;
    position:fixed;
    top:0; left:0; right:0; bottom:0;
    background:rgba(255,255,255,0.6);
    z-index:9999;
    text-align:center;
">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);">
        <span class="spinner is-active" style="float:none;margin:0;"></span>
        <p style="margin-top:10px;font-weight:bold;">Loading...</p>
    </div>
</div>

<script>
    jQuery(document).ready(function ($) {
        // When form is submitted
        $('#mpi-category-form').on('submit', function () {
            $('#mpi-preloader').fadeIn(); // show preloader
        });

        // If you’re using AJAX, show preloader on request start, hide on complete
        $(document).ajaxStart(function () {
            $('#mpi-preloader').fadeIn();
        }).ajaxStop(function () {
            $('#mpi-preloader').fadeOut();
        });
    });
</script>

