<?php
$id = $_GET['id'] ?? '';
header("Location: ../../karyawan/hapus.php?id=" . urlencode($id));
exit;
