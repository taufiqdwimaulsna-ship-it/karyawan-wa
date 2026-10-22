<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Akses ditolak.");
}

$tglMulai       = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglAkhir       = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filterKaryawan = $_GET['id_karyawan'] ?? '';
$filterStatus   = $_GET['status'] ?? '';

// Bangun query
$sql = "SELECT a.*, k.nama, k.nik, k.jabatan, k.no_hp 
        FROM absensi a 
        JOIN karyawan k ON a.id_karyawan = k.id 
        WHERE a.tanggal BETWEEN ? AND ?";
$params = [$tglMulai, $tglAkhir];

if (!empty($filterKaryawan)) {
    $sql .= " AND a.id_karyawan = ?";
    $params[] = $filterKaryawan;
}

if (!empty($filterStatus)) {
    $sql .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY a.tanggal ASC, a.jam_masuk ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Hitung rekap
$totHadir = 0; $totTelat = 0; $totIzin = 0; $totSakit = 0;
foreach ($rows as $r) {
    $st = strtoupper($r['status']);
    if ($st === 'HADIR' || $st === 'PULANG') $totHadir++;
    elseif ($st === 'TERLAMBAT') $totTelat++;
    elseif ($st === 'IZIN') $totIzin++;
    elseif ($st === 'SAKIT') $totSakit++;
}

$filename = "Rekap-Presensi-" . date('d-m-Y', strtotime($tglMulai)) . "-sd-" . date('d-m-Y', strtotime($tglAkhir)) . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rekap Presensi Karyawan</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #999999; padding: 8px; text-align: left; }
        th { background-color: #0f766e; color: #ffffff; font-weight: bold; }
        .title { font-size: 16pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 11pt; text-align: center; color: #555555; }
        .summary-box { background-color: #f1f5f9; font-weight: bold; }
        .text-center { text-align: center; }
        .text-format { mso-number-format: "\@"; }
    </style>
</head>
<body>

<div class="title">LAPORAN REKAPITULASI PRESENSI KARYAWAN</div>
<div class="subtitle">Periode: <?= date('d/m/Y', strtotime($tglMulai)) ?> s/d <?= date('d/m/Y', strtotime($tglAkhir)) ?></div>
<br>

<!-- Ringkasan Singkat -->
<table>
    <tr class="summary-box">
        <td>Total Presensi: <?= count($rows) ?></td>
        <td style="color: #15803d;">Hadir (Tepat Waktu): <?= $totHadir ?></td>
        <td style="color: #b45309;">Terlambat: <?= $totTelat ?></td>
        <td style="color: #0369a1;">Izin: <?= $totIzin ?></td>
        <td style="color: #b91c1c;">Sakit: <?= $totSakit ?></td>
    </tr>
</table>

<!-- Rincian Data -->
<table>
    <thead>
        <tr>
            <th class="text-center">No</th>
            <th>Tanggal</th>
            <th>NIK</th>
            <th>Nama Karyawan</th>
            <th>Jabatan</th>
            <th class="text-center">Jam Masuk</th>
            <th class="text-center">Jam Pulang</th>
            <th class="text-center">Durasi Kerja</th>
            <th class="text-center">Status</th>
            <th>Keterangan / Alasan</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $i => $row): 
            $durasi = '-';
            if (!empty($row['jam_masuk']) && !empty($row['jam_pulang'])) {
                $tM = strtotime($row['jam_masuk']);
                $tP = strtotime($row['jam_pulang']);
                $diffSec = max(0, $tP - $tM);
                $jam   = floor($diffSec / 3600);
                $menit = floor(($diffSec % 3600) / 60);
                $durasi = "{$jam} jam {$menit} mnt";
            }
        ?>
        <tr>
            <td class="text-center"><?= $i + 1 ?></td>
            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
            <td class="text-format text-center"><?= htmlspecialchars($row['nik']) ?></td>
            <td><?= htmlspecialchars($row['nama']) ?></td>
            <td><?= htmlspecialchars($row['jabatan']) ?></td>
            <td class="text-center"><?= !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?></td>
            <td class="text-center"><?= !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-' ?></td>
            <td class="text-center"><?= $durasi ?></td>
            <td class="text-center"><strong><?= htmlspecialchars($row['status']) ?></strong></td>
            <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
