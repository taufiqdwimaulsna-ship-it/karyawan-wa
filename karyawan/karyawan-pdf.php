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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Data Karyawan - <?= date('d/m/Y') ?></title>
    
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
            padding: 35px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
        }

        .doc-header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .doc-title {
            font-size: 18pt;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-sub {
            font-size: 9.5pt;
            color: #64748b;
            margin: 4px 0 0 0;
        }

        .meta-box {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }

        .meta-item {
            font-size: 9pt;
            color: #475569;
        }
        .meta-item strong {
            color: #0f172a;
        }

        .kpi-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        .kpi-card {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            background: #fafafa;
        }
        .kpi-label {
            font-size: 8pt;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .kpi-val {
            font-size: 16pt;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        table.pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 30px;
        }

        table.pdf-table th {
            background-color: #0f766e;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #0f766e;
        }

        table.pdf-table td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }

        table.pdf-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .badge-role-admin {
            background-color: #f3e8ff;
            color: #6b21a8;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-block;
            font-size: 7.5pt;
        }

        .badge-role-karyawan {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-block;
            font-size: 7.5pt;
        }

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
            <span class="fw-bold">Preview Cetak / Export PDF Data Karyawan</span>
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
            <h1 class="doc-title">LAPORAN DATA KARYAWAN</h1>
            <p class="doc-sub">Sistem Manajemen Presensi & Notifikasi WhatsApp Terintegrasi</p>
        </div>
        <div class="text-end text-muted small">
            <div><strong>PT. KARYAWAN INDONESIA</strong></div>
            <div>Dokumen Resmi Internal</div>
        </div>
    </div>

    <!-- Metadata Informasi -->
    <div class="meta-box">
        <div class="row g-2">
            <div class="col-6 meta-item">
                Tanggal Cetak: <strong><?= date('d/m/Y H:i') ?> WIB</strong>
            </div>
            <div class="col-6 meta-item text-end">
                Dicetak Oleh: <strong><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?></strong>
            </div>
        </div>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Total Karyawan</div>
            <div class="kpi-val text-primary"><?= $totalKaryawan ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Administrator</div>
            <div class="kpi-val text-purple" style="color: #6b21a8;"><?= $totAdmin ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Staf / Karyawan</div>
            <div class="kpi-val text-info" style="color: #0284c7;"><?= $totStaff ?></div>
        </div>
    </div>

    <!-- Tabel Data Karyawan -->
    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 35px; text-align: center;">No</th>
                <th style="width: 100px;">NIK</th>
                <th>Nama Lengkap</th>
                <th>Jabatan</th>
                <th>Kontak WhatsApp</th>
                <th>Email Akun</th>
                <th style="width: 80px; text-align: center;">Role</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($karyawanList)): ?>
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">Belum ada data karyawan terdaftar.</td>
            </tr>
            <?php endif; ?>

            <?php foreach ($karyawanList as $i => $row): 
                $isAdmin = (strtolower($row['role']) === 'admin');
            ?>
            <tr>
                <td style="text-align: center;"><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($row['nik']) ?></strong></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['jabatan']) ?></td>
                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td style="text-align: center;">
                    <span class="<?= $isAdmin ? 'badge-role-admin' : 'badge-role-karyawan' ?>">
                        <?= strtoupper(htmlspecialchars($row['role'])) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Signature Box -->
    <div class="signature-box">
        <div class="text-center" style="width: 200px;">
            <div class="small text-muted mb-5">Petugas Verifikasi,</div>
            <div class="fw-bold border-top border-dark pt-1 small"><?= htmlspecialchars($_SESSION['nama'] ?? 'Administrator') ?></div>
            <div class="text-muted" style="font-size: 8pt;">Staff Administrasi</div>
        </div>
        <div class="text-center" style="width: 200px;">
            <div class="small text-muted mb-1"><?= date('d F Y') ?></div>
            <div class="small text-muted mb-5">Mengetahui, Pimpinan / HRD</div>
            <div class="fw-bold border-top border-dark pt-1 small">( ......................................... )</div>
            <div class="text-muted" style="font-size: 8pt;">Kepala Bagian SDM</div>
        </div>
    </div>
</div>

<script>
    // Auto-trigger print jika ada query ?print=1
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('print') === '1') {
        window.addEventListener('load', () => {
            window.print();
        });
    }
</script>

</body>
</html>

