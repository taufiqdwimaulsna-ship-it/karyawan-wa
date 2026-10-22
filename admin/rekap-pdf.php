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
$sql = "SELECT a.*, k.nama, k.nik, k.jabatan 
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekap Presensi - <?= date('d/m/Y', strtotime($tglMulai)) ?> s/d <?= date('d/m/Y', strtotime($tglAkhir)) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .pdf-container {
            max-width: 900px;
            margin: 20px auto;
            background: #ffffff;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .doc-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .doc-table {
            font-size: 0.85rem;
            width: 100%;
            border-collapse: collapse;
        }

        .doc-table th {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 7px 10px;
            text-align: left;
            font-weight: bold;
        }

        .doc-table td {
            border: 1px solid #cbd5e1;
            padding: 7px 10px;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .status-hadir { background: #dcfce7; color: #166534; }
        .status-telat { background: #fef3c7; color: #92400e; }
        .status-izin  { background: #e0f2fe; color: #075985; }
        .status-sakit { background: #fee2e2; color: #991b1b; }

        .signature-box {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        /* Print Settings */
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .pdf-container {
                max-width: 100% !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 15px !important;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar (Hidden when printing) -->
<div class="no-print bg-dark text-white p-3 sticky-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center" style="max-width: 900px;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
            <span class="fw-bold">Preview Cetak / Export PDF</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-light btn-sm px-3">
                Tutup
            </button>
        </div>
    </div>
</div>

<div class="pdf-container">
    <!-- Header Dokumen -->
    <div class="doc-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold text-dark mb-0">LAPORAN REKAPITULASI PRESENSI KARYAWAN</h4>
            <div class="text-muted small">Sistem Informasi Absensi & Notifikasi WhatsApp Gateway</div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-6">PresensiWA</div>
            <div class="text-muted" style="font-size: 0.75rem;">Dicetak: <?= date('d/m/Y H:i') ?> WIB</div>
        </div>
    </div>

    <!-- Info Periode & Ringkasan -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <div>
            <strong>Periode:</strong> <?= date('d/m/Y', strtotime($tglMulai)) ?> s/d <?= date('d/m/Y', strtotime($tglAkhir)) ?>
        </div>
        <div>
            <strong>Total Rekap:</strong> <?= count($rows) ?> Catatan
        </div>
    </div>

    <!-- Kotak Ringkasan -->
    <div class="row g-2 mb-4 text-center">
        <div class="col-3">
            <div class="p-2 border rounded status-hadir">
                <div style="font-size: 0.75rem;">HADIR (TEPAT WAKTU)</div>
                <div class="fs-5 fw-bold"><?= $totHadir ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded status-telat">
                <div style="font-size: 0.75rem;">TERLAMBAT</div>
                <div class="fs-5 fw-bold"><?= $totTelat ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded status-izin">
                <div style="font-size: 0.75rem;">IZIN</div>
                <div class="fs-5 fw-bold"><?= $totIzin ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="p-2 border rounded status-sakit">
                <div style="font-size: 0.75rem;">SAKIT</div>
                <div class="fs-5 fw-bold"><?= $totSakit ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Rincian -->
    <table class="doc-table mb-4">
        <thead>
            <tr>
                <th style="width: 35px; text-align: center;">No</th>
                <th style="width: 85px;">Tanggal</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th style="width: 70px; text-align: center;">Masuk</th>
                <th style="width: 70px; text-align: center;">Pulang</th>
                <th style="width: 85px; text-align: center;">Durasi</th>
                <th style="width: 90px; text-align: center;">Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr>
                <td colspan="9" class="text-center py-3 text-muted">Tidak ada data kehadiran pada periode ini.</td>
            </tr>
            <?php endif; ?>

            <?php foreach ($rows as $i => $row): 
                $durasi = '-';
                if (!empty($row['jam_masuk']) && !empty($row['jam_pulang'])) {
                    $tM = strtotime($row['jam_masuk']);
                    $tP = strtotime($row['jam_pulang']);
                    $diffSec = max(0, $tP - $tM);
                    $jam   = floor($diffSec / 3600);
                    $menit = floor(($diffSec % 3600) / 60);
                    $durasi = "{$jam}j {$menit}m";
                }

                $st = strtoupper($row['status']);
                $badgeCls = 'status-hadir';
                if ($st === 'TERLAMBAT') $badgeCls = 'status-telat';
                elseif ($st === 'IZIN') $badgeCls = 'status-izin';
                elseif ($st === 'SAKIT') $badgeCls = 'status-sakit';
            ?>
            <tr>
                <td class="text-center"><?= $i + 1 ?></td>
                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td>
                    <div class="fw-bold"><?= htmlspecialchars($row['nama']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($row['nik']) ?></small>
                </td>
                <td><?= htmlspecialchars($row['jabatan']) ?></td>
                <td class="text-center"><?= !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?></td>
                <td class="text-center"><?= !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-' ?></td>
                <td class="text-center"><?= $durasi ?></td>
                <td class="text-center">
                    <span class="status-badge <?= $badgeCls ?>"><?= $st ?></span>
                </td>
                <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tanda Tangan / Pengesahan -->
    <div class="signature-box">
        <div>
            <div class="small text-muted mb-1">Dibuat oleh:</div>
            <div class="fw-bold"><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?></div>
            <div class="small text-muted">Petugas Presensi</div>
        </div>
        <div class="text-end">
            <div class="small text-muted mb-1"><?= date('d F Y') ?></div>
            <div class="small text-muted mb-4">Mengetahui,</div>
            <div class="fw-bold text-decoration-underline" style="margin-top: 50px;">( Pimpinan / HRD )</div>
            <div class="small text-muted">General Manager</div>
        </div>
    </div>
</div>

</body>
</html>
