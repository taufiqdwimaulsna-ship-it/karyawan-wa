<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jamMasuk = trim($_POST['jam_masuk_maksimal'] ?? '08:00');
    $adminHp  = trim($_POST['admin_phone'] ?? '');

    // Format jam masuk ke HH:mm:ss jika perlu
    if (strlen($jamMasuk) === 5) {
        $jamMasuk .= ':00';
    }

    try {
        // Update jam_masuk_maksimal
        $stmt1 = $pdo->prepare("INSERT INTO pengaturan (nama_pengaturan, nilai, keterangan) VALUES ('jam_masuk_maksimal', ?, 'Batas jam masuk') ON DUPLICATE KEY UPDATE nilai = ?");
        $stmt1->execute([$jamMasuk, $jamMasuk]);

        // Update admin_phone
        if (!empty($adminHp)) {
            $stmt2 = $pdo->prepare("INSERT INTO pengaturan (nama_pengaturan, nilai, keterangan) VALUES ('admin_phone', ?, 'No WA Admin') ON DUPLICATE KEY UPDATE nilai = ?");
            $stmt2->execute([$adminHp, $adminHp]);
        }

        $_SESSION['flash'] = [
            'type'    => 'success',
            'title'   => 'Pengaturan Disimpan',
            'message' => "Batas jam masuk berhasil diatur ke {$jamMasuk} WIB."
        ];
    } catch (Exception $e) {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'title'   => 'Gagal Menyimpan',
            'message' => 'Terjadi kesalahan: ' . $e->getMessage()
        ];
    }
}

header("Location: index.php");
exit;

