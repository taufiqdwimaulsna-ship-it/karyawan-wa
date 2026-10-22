<?php
require_once __DIR__ . '/../config/database.php';

// Cek hak akses admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Akses ditolak. Halaman ini hanya untuk Administrator.");
}

// Ambil data karyawan
$stmt = $pdo->query("SELECT * FROM karyawan ORDER BY role ASC, nama ASC");
$karyawanList = $stmt->fetchAll();

$totalKaryawan = count($karyawanList);
$totAdmin = 0;
$totStaff = 0;
foreach ($karyawanList as $k) {
    if (strtolower($k['role']) === 'admin') {
        $totAdmin++;
    } else {
        $totStaff++;
    }
}

$filename = "Data-Karyawan-" . date('Y-m-d') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Karyawan</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 10pt;
            text-align: left;
        }
        th {
            background-color: #0f766e;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .title {
            font-size: 15pt;
            font-weight: bold;
            text-align: center;
            color: #0f172a;
        }
        .subtitle {
            font-size: 10pt;
            text-align: center;
            color: #64748b;
            margin-bottom: 15px;
        }
        .summary-box td {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 10pt;
        }
        .text-center {
            text-align: center;
        }
        .text-format {
            mso-number-format: "\@";
        }
        .role-admin {
            color: #6d28d9;
            font-weight: bold;
        }
        .role-karyawan {
            color: #0369a1;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="title">LAPORAN DATA KARYAWAN</div>
<div class="subtitle">Sistem Presensi Karyawan & Notifikasi WhatsApp &bull; Tanggal Ekspor: <?= date('d/m/Y H:i') ?> WIB</div>

<!-- Ringkasan Singkat -->
<table>
    <tr class="summary-box">
        <td class="text-center">Total Karyawan: <?= $totalKaryawan ?></td>
        <td class="text-center" style="color: #6d28d9;">Administrator: <?= $totAdmin ?></td>
        <td class="text-center" style="color: #0369a1;">Karyawan / Staf: <?= $totStaff ?></td>
    </tr>
</table>

<!-- Tabel Data -->
<table>
    <thead>
        <tr>
            <th style="width: 40px;">No</th>
            <th style="width: 140px;">NIK</th>
            <th style="width: 220px;">Nama Lengkap</th>
            <th style="width: 160px;">Jabatan</th>
            <th style="width: 150px;">Nomor WhatsApp</th>
            <th style="width: 220px;">Email Akun</th>
            <th style="width: 100px;">Role Akses</th>
            <th style="width: 140px;">Tanggal Dibuat</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($karyawanList)): ?>
        <tr>
            <td colspan="8" class="text-center" style="padding: 20px; color: #64748b;">Belum ada data karyawan.</td>
        </tr>
        <?php endif; ?>

        <?php foreach ($karyawanList as $i => $row): 
            $roleClass = (strtolower($row['role']) === 'admin') ? 'role-admin' : 'role-karyawan';
            $tglDibuat = !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-';
        ?>
        <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td class="text-format text-center"><?= htmlspecialchars($row['nik']) ?></td>
            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
            <td><?= htmlspecialchars($row['jabatan']) ?></td>
            <td class="text-format"><?= htmlspecialchars($row['no_hp']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td class="text-center <?= $roleClass ?>"><?= strtoupper(htmlspecialchars($row['role'])) ?></td>
            <td class="text-center"><?= $tglDibuat ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

