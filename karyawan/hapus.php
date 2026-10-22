<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

// Cek hak akses admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../absensi/index.php");
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    if ($id == $_SESSION['user_id']) {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'title'   => 'Ditolak',
            'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!'
        ];
        header("Location: index.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM karyawan WHERE id = ?");
    $stmt->execute([$id]);
    $karyawan = $stmt->fetch();

    if ($karyawan) {
        $cleanHp = preg_replace('/[^0-9]/', '', $karyawan['no_hp'] ?? '');
        if (substr($cleanHp, 0, 1) === '0') {
            $cleanHp = '62' . substr($cleanHp, 1);
        }

        // Kirim notifikasi WA pemberitahuan penghapusan ke karyawan (Sesuai format template presensi)
        $pesan = "PENONAKTIFAN AKUN BERHASIL 🗑️\n\n" .
                 "Halo *{$karyawan['nama']}*,\n" .
                 "Data akun Anda telah berhasil dinonaktifkan/dihapus dari Sistem Presensi.\n\n" .
                 "  • NIK: {$karyawan['nik']}\n" .
                 "  • Jabatan: {$karyawan['jabatan']}\n" .
                 "  • Status: NONAKTIF / DIHAPUS\n\n" .
                 "Terima kasih atas dedikasi dan kerja sama Anda selama ini.\n" .
                 "Terima kasih!";
        sendWhatsAppNotification($cleanHp, $pesan);

        // Notifikasi WA ke Admin (hanya jika nomor admin berbeda agar tidak bentrok rate-limit)
        $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
        if (substr($cleanAdminHp, 0, 1) === '0') {
            $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
        }
        if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
            usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
            $waktu = date('d/m/Y H:i');
            $pesanAdmin = "NOTIFIKASI PENGHAPUSAN KARYAWAN 🗑️\n\n" .
                          "Halo Admin,\n" .
                          "Akun karyawan telah berhasil dihapus dari Sistem Presensi.\n\n" .
                          "  • NIK: {$karyawan['nik']}\n" .
                          "  • Nama: {$karyawan['nama']}\n" .
                          "  • Jabatan: {$karyawan['jabatan']}\n" .
                          "  • No WA: {$karyawan['no_hp']}\n" .
                          "  • Waktu: {$waktu} WIB\n\n" .
                          "Data akun dan riwayat presensi terkait telah dihapus.\n" .
                          "Terima kasih!";
            sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
        }

        // Hapus juga riwayat absensi karyawan terkait
        $stmtDelAbsen = $pdo->prepare("DELETE FROM absensi WHERE id_karyawan = ?");
        $stmtDelAbsen->execute([$id]);

        // Hapus karyawan
        $stmtDelete = $pdo->prepare("DELETE FROM karyawan WHERE id = ?");
        $stmtDelete->execute([$id]);

        $_SESSION['flash'] = [
            'type'    => 'success',
            'title'   => 'Karyawan Dihapus',
            'message' => "Akun {$karyawan['nama']} telah berhasil dihapus dari sistem."
        ];
    }
}

header("Location: index.php");
exit;

