<div class="wrap">
    <h1>Upload XML File</h1>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('mpi_xml_importer_nonce'); ?>
        <input type="file" name="xml_file" accept=".xml" required>
        <br><br>
        <button type="submit" class="button button-primary">Upload & Parse</button>
    </form>
</div>

<?php
// Handle file upload right in this view (or better in handler class)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'mpi_xml_importer_nonce')) {
        wp_die('Security check failed.');
    }

    $file = $_FILES['xml_file']['tmp_name'];

    if (file_exists($file)) {
        $xml_content = file_get_contents($file);
        $xml_object  = simplexml_load_string($xml_content, "SimpleXMLElement", LIBXML_NOCDATA);
        $array       = json_decode(json_encode($xml_object), true);

        if (!empty($array['product'])) {
            $importer = new MPI_Importer();
            $importer->insert_products_from_xml($array['product']);
        }
    }
}
?>
