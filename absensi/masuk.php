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

// Cek apakah sudah ada catatan presensi/izin/sakit hari ini
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
$stmt->execute([$karyawan_id, $today]);
$existing = $stmt->fetch();

if ($existing) {
    if (in_array($existing['status'], ['IZIN', 'SAKIT'])) {
        $_SESSION['flash'] = [
            'type'    => 'warning',
            'title'   => 'Pengajuan Tercatat',
            'message' => "Anda sudah tercatat {$existing['status']} untuk hari ini."
        ];
    } else {
        $_SESSION['flash'] = [
            'type'    => 'warning',
            'title'   => 'Sudah Absen',
            'message' => 'Anda sudah melakukan presensi masuk hari ini!'
        ];
    }
    header("Location: index.php");
    exit;
}

// Tentukan batas waktu masuk dan status keterlambatan
$batasWaktu = defined('JAM_MASUK_MAKSIMAL') ? JAM_MASUK_MAKSIMAL : '08:00:00';
$isLate     = (strtotime($now) > strtotime($batasWaktu));

if ($isLate) {
    $status      = 'TERLAMBAT';
    $diffSec     = strtotime($now) - strtotime($batasWaktu);
    $menitTelat  = ceil($diffSec / 60);
    $keterangan  = "Terlambat {$menitTelat} menit";
} else {
    $status      = 'HADIR';
    $menitTelat  = 0;
    $keterangan  = "Tepat Waktu";
}

// Simpan absensi masuk ke database
$stmtInsert = $pdo->prepare("INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, status, keterangan) VALUES (?, ?, ?, ?, ?)");
if ($stmtInsert->execute([$karyawan_id, $today, $now, $status, $keterangan])) {
    // Ambil detail data karyawan
    $stmtUser = $pdo->prepare("SELECT nama, no_hp, nik, jabatan FROM karyawan WHERE id = ?");
    $stmtUser->execute([$karyawan_id]);
    $karyawan = $stmtUser->fetch();

    $tglFormat   = date('d F Y');
    $jamFormat   = date('H:i');
    $batasFormat = date('H:i', strtotime($batasWaktu));

    // Normalisasi No HP Karyawan
    $cleanHp = preg_replace('/[^0-9]/', '', $karyawan['no_hp'] ?? '');
    if (substr($cleanHp, 0, 1) === '0') {
        $cleanHp = '62' . substr($cleanHp, 1);
    }

    // 1. Notifikasi WhatsApp ke Karyawan (Sesuai format template presensi)
    if ($status === 'HADIR') {
        $pesanKaryawan = "PRESENSI MASUK BERHASIL 🟢\n\n" .
                         "Halo *{$karyawan['nama']}*,\n" .
                         "Presensi Masuk Anda telah berhasil dicatat di Sistem Presensi.\n\n" .
                         "  • NIK: {$karyawan['nik']}\n" .
                         "  • Tanggal: {$tglFormat}\n" .
                         "  • Jam Masuk: {$jamFormat} WIB\n" .
                         "  • Status: HADIR (Tepat Waktu)\n\n" .
                         "Selamat bekerja dan tetap semangat!\n" .
                         "Terima kasih!";
    } else {
        $pesanKaryawan = "PRESENSI MASUK BERHASIL ⚠️\n\n" .
                         "Halo *{$karyawan['nama']}*,\n" .
                         "Presensi Masuk Anda telah berhasil dicatat di Sistem Presensi.\n\n" .
                         "  • NIK: {$karyawan['nik']}\n" .
                         "  • Tanggal: {$tglFormat}\n" .
                         "  • Jam Masuk: {$jamFormat} WIB (Batas: {$batasFormat} WIB)\n" .
                         "  • Status: TERLAMBAT ({$menitTelat} menit)\n\n" .
                         "Mohon untuk memperhatikan kedisiplinan jam kerja.\n" .
                         "Terima kasih!";
    }
    sendWhatsAppNotification($cleanHp, $pesanKaryawan);

    // 2. Notifikasi WhatsApp ke Admin (Requirement 5)
    $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
    if (substr($cleanAdminHp, 0, 1) === '0') {
        $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
    }

    if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
        usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
        if ($status === 'HADIR') {
            $pesanAdmin = "NOTIFIKASI PRESENSI MASUK 🟢\n\n" .
                          "Halo Admin,\n" .
                          "Karyawan telah melakukan absen masuk tepat waktu.\n\n" .
                          "  • NIK: {$karyawan['nik']}\n" .
                          "  • Nama: {$karyawan['nama']}\n" .
                          "  • Jabatan: {$karyawan['jabatan']}\n" .
                          "  • Jam Masuk: {$jamFormat} WIB\n" .
                          "  • Status: HADIR (Tepat Waktu)\n" .
                          "  • Tanggal: {$tglFormat}\n\n" .
                          "Data presensi telah tersimpan di sistem.\n" .
                          "Terima kasih!";
        } else {
            $pesanAdmin = "NOTIFIKASI KARYAWAN TERLAMBAT ⚠️\n\n" .
                          "Halo Admin,\n" .
                          "Karyawan melakukan absen melebihi batas waktu kedatangan.\n\n" .
                          "  • NIK: {$karyawan['nik']}\n" .
                          "  • Nama: {$karyawan['nama']}\n" .
                          "  • Jabatan: {$karyawan['jabatan']}\n" .
                          "  • Jam Masuk: {$jamFormat} WIB (Batas: {$batasFormat} WIB)\n" .
                          "  • Keterangan: Terlambat {$menitTelat} menit\n" .
                          "  • Status: TERLAMBAT\n" .
                          "  • Tanggal: {$tglFormat}\n\n" .
                          "Data keterlambatan telah dicatat di sistem.\n" .
                          "Terima kasih!";
        }
        sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
    }

    $_SESSION['flash'] = [
        'type'    => ($status === 'HADIR' ? 'success' : 'warning'),
        'title'   => ($status === 'HADIR' ? 'Absen Masuk Berhasil!' : 'Absen Tercatat (Terlambat)'),
        'message' => "Kehadiran dicatat pada pukul {$jamFormat} WIB [Status: {$status}]. Notifikasi telah dikirim via WhatsApp."
    ];
} else {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'title'   => 'Gagal',
        'message' => 'Terjadi kesalahan sistem saat menyimpan presensi!'
    ];
}

header("Location: index.php");
exit;
