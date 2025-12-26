
<?php
// Determine base path for assets based on current directory
// Use existing $assetBase if set in header.php, otherwise calculate it
if (!isset($assetBase)) {
    $currentScript = $_SERVER['PHP_SELF'];
    $isAdminDir = (strpos($currentScript, '/admin/') !== false);
    $isStaffDir = (strpos($currentScript, '/staff/') !== false);
    $assetBase = ($isAdminDir || $isStaffDir) ? '../' : '';
}
?>

<script src="<?php echo $assetBase; ?>js/jquery-1.11.1.min.js"></script>
<script src="<?php echo $assetBase; ?>js/bootstrap.min.js"></script>
<script src="<?php echo $assetBase; ?>js/jquery.dataTables.min.js"></script>
<script src="<?php echo $assetBase; ?>js/dataTables.bootstrap.min.js"></script>
<script src="<?php echo $assetBase; ?>js/foundation-datepicker.min.js"></script>
<script src="<?php echo $assetBase; ?>js/validator.min.js"></script>
<script src="<?php echo $assetBase; ?>js/custom.js"></script>
<script src="<?php echo $assetBase; ?>ajax.js"></script>


</body>
</html>