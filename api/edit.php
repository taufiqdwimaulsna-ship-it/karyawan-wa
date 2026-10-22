<?php
$id = $_GET['id'] ?? '';
header("Location: ../karyawan/edit.php?id=" . urlencode($id));
exit;
