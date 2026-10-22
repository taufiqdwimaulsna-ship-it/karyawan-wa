<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../absensi/index.php");
    exit;
}

$pageTitle = 'Rekapitulasi Kehadiran Karyawan';
$activeMenu = 'rekap';

// Ambil parameter filter
$tglMulai    = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tglAkhir    = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filterKaryawan = $_GET['id_karyawan'] ?? '';
$filterStatus   = $_GET['status'] ?? '';

// Ambil list semua karyawan untuk dropdown
$karyawanList = $pdo->query("SELECT id, nik, nama, jabatan FROM karyawan ORDER BY nama ASC")->fetchAll();

// Bangun query dinamis dengan prepared statement
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

$sql .= " ORDER BY a.tanggal DESC, a.jam_masuk DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rekapData = $stmt->fetchAll();

// Hitung metrik ringkasan
$totalData      = count($rekapData);
$totalHadir     = 0;
$totalTerlambat = 0;
$totalIzin      = 0;
$totalSakit     = 0;

// Matriks ringkasan per karyawan
$summaryPerKaryawan = [];

foreach ($rekapData as $r) {
    $st = strtoupper($r['status']);
    if ($st === 'HADIR' || $st === 'PULANG') $totalHadir++;
    elseif ($st === 'TERLAMBAT') $totalTerlambat++;
    elseif ($st === 'IZIN') $totalIzin++;
    elseif ($st === 'SAKIT') $totalSakit++;

    $kId = $r['id_karyawan'];
    if (!isset($summaryPerKaryawan[$kId])) {
        $summaryPerKaryawan[$kId] = [
            'nama'      => $r['nama'],
            'nik'       => $r['nik'],
            'jabatan'   => $r['jabatan'],
            'hadir'     => 0,
            'terlambat' => 0,
            'izin'      => 0,
            'sakit'     => 0,
            'total'     => 0
        ];
    }
    if ($st === 'HADIR' || $st === 'PULANG') $summaryPerKaryawan[$kId]['hadir']++;
    elseif ($st === 'TERLAMBAT') $summaryPerKaryawan[$kId]['terlambat']++;
    elseif ($st === 'IZIN') $summaryPerKaryawan[$kId]['izin']++;
    elseif ($st === 'SAKIT') $summaryPerKaryawan[$kId]['sakit']++;
    $summaryPerKaryawan[$kId]['total']++;
}

// Build query string untuk URL export
$exportQuery = http_build_query([
    'tgl_mulai'   => $tglMulai,
    'tgl_akhir'   => $tglAkhir,
    'id_karyawan' => $filterKaryawan,
    'status'      => $filterStatus
]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rekap Presensi</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-1">Rekapitulasi Kehadiran</h3>
            <p class="text-muted small mb-0">Laporan presensi karyawan, analisis keterlambatan, izin, sakit, dan cetak dokumen.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="rekap-excel.php?<?= $exportQuery ?>" class="btn btn-success" title="Download Format Excel">
                <i class="bi bi-file-earmark-spreadsheet-fill"></i> Export Excel
            </a>
            <a href="rekap-pdf.php?<?= $exportQuery ?>" target="_blank" class="btn btn-danger" title="Cetak atau Simpan PDF">
                <i class="bi bi-file-earmark-pdf-fill"></i> Cetak / PDF
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="modern-card p-4 mb-4 bg-white">
        <form method="GET" action="rekap.php">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold small text-secondary">Tanggal Mulai</label>
                    <div class="input-icon-group">
                        <i class="bi bi-calendar"></i>
                        <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tglMulai) ?>" required>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold small text-secondary">Tanggal Akhir</label>
                    <div class="input-icon-group">
                        <i class="bi bi-calendar-check"></i>
                        <input type="date" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tglAkhir) ?>" required>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label class="form-label fw-semibold small text-secondary">Karyawan</label>
                    <select name="id_karyawan" class="form-select">
                        <option value="">-- Semua Karyawan --</option>
                        <?php foreach ($karyawanList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $filterKaryawan == $k['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama']) ?> (<?= htmlspecialchars($k['nik']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label class="form-label fw-semibold small text-secondary">Status</label>
                    <select name="status" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="HADIR" <?= $filterStatus === 'HADIR' ? 'selected' : '' ?>>Hadir Tepat Waktu</option>
                        <option value="TERLAMBAT" <?= $filterStatus === 'TERLAMBAT' ? 'selected' : '' ?>>Terlambat</option>
                        <option value="IZIN" <?= $filterStatus === 'IZIN' ? 'selected' : '' ?>>Izin</option>
                        <option value="SAKIT" <?= $filterStatus === 'SAKIT' ? 'selected' : '' ?>>Sakit</option>
                    </select>
                </div>

                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100" title="Terapkan Filter">
                        <i class="bi bi-funnel-fill"></i>
                    </button>
                    <a href="rekap.php" class="btn btn-light border" title="Reset Filter">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Ringkasan Statistik Periode -->
    <div class="row g-3 mb-4">
        <!-- Total Data -->
        <div class="col-6 col-lg">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Rekap</div>
                        <h3 class="fw-bold text-dark mt-1 mb-0"><?= $totalData ?></h3>
                    </div>
                    <div class="stat-icon primary" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="bi bi-clipboard-data-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hadir -->
        <div class="col-6 col-lg">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Tepat Waktu</div>
                        <h3 class="fw-bold text-success mt-1 mb-0"><?= $totalHadir ?></h3>
                    </div>
                    <div class="stat-icon success" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terlambat -->
        <div class="col-6 col-lg">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Terlambat</div>
                        <h3 class="fw-bold text-warning mt-1 mb-0"><?= $totalTerlambat ?></h3>
                    </div>
                    <div class="stat-icon warning" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Izin -->
        <div class="col-6 col-lg">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Izin</div>
                        <h3 class="fw-bold text-info mt-1 mb-0"><?= $totalIzin ?></h3>
                    </div>
                    <div class="stat-icon info" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="bi bi-info-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sakit -->
        <div class="col-12 col-lg">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Sakit</div>
                        <h3 class="fw-bold text-danger mt-1 mb-0"><?= $totalSakit ?></h3>
                    </div>
                    <div class="stat-icon danger" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nav Tabs: Rincian Harian vs Ringkasan Per Karyawan -->
    <ul class="nav nav-tabs border-bottom mb-3" id="rekapTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold text-dark" id="detail-tab" data-bs-toggle="tab" data-bs-target="#tabDetail" type="button" role="tab">
                <i class="bi bi-list-check me-1"></i> Rincian Presensi Harian (<?= $totalData ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-secondary" id="summary-tab" data-bs-toggle="tab" data-bs-target="#tabSummary" type="button" role="tab">
                <i class="bi bi-pie-chart me-1"></i> Matriks Rekap Per Karyawan (<?= count($summaryPerKaryawan) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="rekapTabContent">
        <!-- Tab 1: Detail Harian -->
        <div class="tab-pane fade show active" id="tabDetail" role="tabpanel">
            <div class="modern-card overflow-hidden">
                <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Periode: <strong><?= date('d M Y', strtotime($tglMulai)) ?></strong> s/d <strong><?= date('d M Y', strtotime($tglAkhir)) ?></strong></span>
                    <div class="input-icon-group" style="max-width: 260px;">
                        <i class="bi bi-search"></i>
                        <input type="text" id="filterDetailTable" class="form-control form-control-sm" placeholder="Cari dalam tabel...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table modern-table" id="tableDetailRekap">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Tanggal</th>
                                <th>Karyawan</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Durasi Kerja</th>
                                <th>Status</th>
                                <th>Keterangan / Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rekapData)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-inbox"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark">Tidak Ada Data Presensi</h6>
                                        <p class="text-muted small mb-0">Tidak ditemukan catatan kehadiran pada rentang tanggal atau filter yang dipilih.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($rekapData as $i => $row): 
                                $durasi = '-';
                                if (!empty($row['jam_masuk']) && !empty($row['jam_pulang'])) {
                                    $tM = strtotime($row['jam_masuk']);
                                    $tP = strtotime($row['jam_pulang']);
                                    $diffSec = max(0, $tP - $tM);
                                    $jam   = floor($diffSec / 3600);
                                    $menit = floor(($diffSec % 3600) / 60);
                                    $durasi = "{$jam} jam {$menit} mnt";
                                }

                                $parts = explode(' ', trim($row['nama']));
                                $initial = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                            ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?= $i + 1 ?></td>
                                <td>
                                    <div class="fw-semibold text-dark">
                                        <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                                    </div>
                                    <small class="text-muted"><?= date('l', strtotime($row['tanggal'])) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle sm" style="width: 30px; height: 30px; font-size: 0.75rem;">
                                            <?= htmlspecialchars($initial) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 0.88rem;"><?= htmlspecialchars($row['nama']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;">NIK: <?= htmlspecialchars($row['nik']) ?> &bull; <?= htmlspecialchars($row['jabatan']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['jam_masuk'])): ?>
                                        <span class="badge-soft-success">
                                            <i class="bi bi-box-arrow-in-right"></i> <?= date('H:i', strtotime($row['jam_masuk'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['jam_pulang'])): ?>
                                        <span class="badge-soft-primary">
                                            <i class="bi bi-box-arrow-right"></i> <?= date('H:i', strtotime($row['jam_pulang'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><?= in_array($row['status'], ['IZIN', 'SAKIT']) ? '-' : 'Belum Pulang' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-secondary"><?= $durasi ?></span>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'HADIR'): ?>
                                        <span class="badge-soft-success"><i class="bi bi-check-circle-fill me-1"></i> HADIR</span>
                                    <?php elseif ($row['status'] === 'TERLAMBAT'): ?>
                                        <span class="badge-soft-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i> TERLAMBAT</span>
                                    <?php elseif ($row['status'] === 'IZIN'): ?>
                                        <span class="badge-soft-primary"><i class="bi bi-info-circle-fill me-1"></i> IZIN</span>
                                    <?php elseif ($row['status'] === 'SAKIT'): ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 fw-bold"><i class="bi bi-heart-pulse-fill me-1"></i> SAKIT</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Matriks Ringkasan Per Karyawan -->
        <div class="tab-pane fade" id="tabSummary" role="tabpanel">
            <div class="modern-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table modern-table" id="tableSummaryKaryawan">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Karyawan</th>
                                <th>Jabatan</th>
                                <th class="text-center text-success">Hadir</th>
                                <th class="text-center text-warning">Terlambat</th>
                                <th class="text-center text-info">Izin</th>
                                <th class="text-center text-danger">Sakit</th>
                                <th class="text-center">Total Tercatat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($summaryPerKaryawan)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada data untuk periode ini.</td>
                            </tr>
                            <?php endif; ?>

                            <?php $idx = 1; foreach ($summaryPerKaryawan as $kId => $sum): ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?= $idx++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($sum['nama']) ?></div>
                                    <small class="text-muted">NIK: <?= htmlspecialchars($sum['nik']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border"><?= htmlspecialchars($sum['jabatan']) ?></span>
                                </td>
                                <td class="text-center fw-bold text-success"><?= $sum['hadir'] ?></td>
                                <td class="text-center fw-bold text-warning"><?= $sum['terlambat'] ?></td>
                                <td class="text-center fw-bold text-info"><?= $sum['izin'] ?></td>
                                <td class="text-center fw-bold text-danger"><?= $sum['sakit'] ?></td>
                                <td class="text-center fw-extrabold text-dark"><?= $sum['total'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        setupTableSearch('filterDetailTable', 'tableDetailRekap');
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
