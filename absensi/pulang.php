<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$today       = date('Y-m-d');
$now         = date('H:i:s');

// Cek apakah sudah absen masuk
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
$stmt->execute([$karyawan_id, $today]);
$existing = $stmt->fetch();

if (!$existing || empty($existing['jam_masuk'])) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'title'   => 'Gagal Absen Pulang',
        'message' => 'Anda belum melakukan absen masuk hari ini!'
    ];
    header("Location: index.php");
    exit;
}

if (!empty($existing['jam_pulang'])) {
    $_SESSION['flash'] = [
        'type'    => 'warning',
        'title'   => 'Sudah Pulang',
        'message' => 'Anda sudah melakukan presensi pulang sebelumnya!'
    ];
    header("Location: index.php");
    exit;
}

// Simpan jam pulang (pertahankan status HADIR / TERLAMBAT untuk akurasi rekapitulasi)
$stmtUpdate = $pdo->prepare("UPDATE absensi SET jam_pulang = ? WHERE id = ?");
if ($stmtUpdate->execute([$now, $existing['id']])) {
    $stmtUser = $pdo->prepare("SELECT nama, no_hp, nik, jabatan FROM karyawan WHERE id = ?");
    $stmtUser->execute([$karyawan_id]);
    $karyawan = $stmtUser->fetch();

    $tglFormat = date('d F Y');
    $jamMasuk  = date('H:i', strtotime($existing['jam_masuk']));
    $jamPulang = date('H:i', strtotime($now));

    // Hitung durasi kerja
    $tM = strtotime($existing['jam_masuk']);
    $tP = strtotime($now);
    $diffSec = max(0, $tP - $tM);
    $jam   = floor($diffSec / 3600);
    $menit = floor(($diffSec % 3600) / 60);
    $durasi = "{$jam} jam {$menit} menit";

    // 1. Notifikasi WhatsApp ke Karyawan
    $cleanHp = preg_replace('/[^0-9]/', '', $karyawan['no_hp'] ?? '');
    if (substr($cleanHp, 0, 1) === '0') {
        $cleanHp = '62' . substr($cleanHp, 1);
    }

    // 1. Notifikasi WhatsApp ke Karyawan (Sesuai format template presensi)
    $pesanKaryawan = "PRESENSI PULANG BERHASIL 🏁\n\n" .
                     "Halo *{$karyawan['nama']}*,\n" .
                     "Presensi Pulang Anda telah berhasil dicatat di Sistem Presensi.\n\n" .
                     "  • NIK: {$karyawan['nik']}\n" .
                     "  • Tanggal: {$tglFormat}\n" .
                     "  • Jam Masuk: {$jamMasuk} WIB\n" .
                     "  • Jam Pulang: {$jamPulang} WIB\n" .
                     "  • Total Kerja: {$durasi}\n\n" .
                     "Terima kasih atas kerja keras Anda hari ini. Hati-hati di jalan!\n" .
                     "Terima kasih!";

    sendWhatsAppNotification($cleanHp, $pesanKaryawan);

    // 2. Notifikasi WhatsApp ke Admin (Requirement 5)
    $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
    if (substr($cleanAdminHp, 0, 1) === '0') {
        $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
    }

    if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
        usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
        $pesanAdmin = "NOTIFIKASI PRESENSI PULANG 🏁\n\n" .
                      "Halo Admin,\n" .
                      "Karyawan telah melakukan presensi pulang hari ini.\n\n" .
                      "  • NIK: {$karyawan['nik']}\n" .
                      "  • Nama: {$karyawan['nama']}\n" .
                      "  • Jabatan: {$karyawan['jabatan']}\n" .
                      "  • Jam Masuk: {$jamMasuk} WIB\n" .
                      "  • Jam Pulang: {$jamPulang} WIB\n" .
                      "  • Total Kerja: {$durasi}\n" .
                      "  • Tanggal: {$tglFormat}\n\n" .
                      "Data kepulangan telah tercatat di sistem.\n" .
                      "Terima kasih!";

        sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
    }

    $_SESSION['flash'] = [
        'type'    => 'success',
        'title'   => 'Absen Pulang Berhasil!',
        'message' => "Kepulangan Anda pada pukul {$jamPulang} WIB telah dicatat (Durasi kerja: {$durasi})."
    ];
} else {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'title'   => 'Gagal',
        'message' => 'Terjadi kesalahan sistem saat menyimpan presensi pulang!'
    ];
}

header("Location: index.php");
exit;
