<?php
require_once __DIR__ . '/../config/database.php';

// Cek hak akses admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../absensi/index.php");
    exit;
}

$pageTitle = 'Dashboard Admin';
$activeMenu = 'dashboard';

$today = date('Y-m-d');
$batasMasuk = defined('JAM_MASUK_MAKSIMAL') ? JAM_MASUK_MAKSIMAL : '08:00:00';
$batasMasukFormatted = date('H:i', strtotime($batasMasuk));

// Metrik Statistik Hari Ini
$totalKaryawan    = (int)$pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn();
$sudahAbsen       = (int)$pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal = '{$today}' AND jam_masuk IS NOT NULL")->fetchColumn();
$tepatWaktu       = (int)$pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal = '{$today}' AND status = 'HADIR'")->fetchColumn();
$terlambat        = (int)$pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal = '{$today}' AND status = 'TERLAMBAT'")->fetchColumn();
$izinSakit        = (int)$pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal = '{$today}' AND status IN ('IZIN', 'SAKIT')")->fetchColumn();
$sudahPulang      = (int)$pdo->query("SELECT COUNT(*) FROM absensi WHERE tanggal = '{$today}' AND jam_pulang IS NOT NULL")->fetchColumn();
$belumAbsen       = max(0, $totalKaryawan - ($sudahAbsen + $izinSakit));

// Query Laporan Kehadiran Hari Ini
$queryLaporan = "SELECT k.nama, k.nik, k.jabatan, k.no_hp, a.tanggal, a.jam_masuk, a.jam_pulang, a.status, a.keterangan 
                 FROM absensi a 
                 JOIN karyawan k ON a.id_karyawan = k.id 
                 WHERE a.tanggal = '{$today}' 
                 ORDER BY a.jam_masuk ASC, a.id DESC";
$laporan = $pdo->query($queryLaporan)->fetchAll();

// Format Tanggal Bahasa Indonesia
$hariList = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$bulanList = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];

$hariIni = $hariList[date('l')] ?? date('l');
$tanggalFormat = $hariIni . ', ' . date('d') . ' ' . ($bulanList[date('m')] ?? date('F')) . ' ' . date('Y');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Welcome Banner -->
    <div class="modern-card p-4 mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white;">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <span class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-1 rounded-pill border border-white border-opacity-10">
                        <span class="status-dot pulse-success"></span>
                        <span class="text-light small fw-medium"><?= $tanggalFormat ?></span>
                    </span>
                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25 rounded-pill px-3 py-1">
                        <i class="bi bi-clock-history me-1"></i> Batas Masuk: <?= $batasMasukFormatted ?> WIB
                    </span>
                </div>
                <h3 class="fw-bold mb-1 text-white">Selamat Datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?> 👋</h3>
                <p class="text-white-50 mb-0 small">Sistem mendeteksi keterlambatan otomatis dan mengirimkan notifikasi WhatsApp secara real-time.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modalPengaturan">
                    <i class="bi bi-gear-fill"></i> Atur Batas Waktu
                </button>
                <a href="rekap.php" class="btn btn-primary text-white">
                    <i class="bi bi-clipboard-data-fill"></i> Rekap Presensi
                </a>
                <a href="<?= BASE_URL ?>/karyawan/tambah.php" class="btn btn-wa text-white">
                    <i class="bi bi-person-plus-fill"></i> Tambah Karyawan
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards (5 Cards) -->
    <div class="row g-3 mb-4">
        <!-- Total Karyawan -->
        <div class="col-sm-6 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase">Total Karyawan</div>
                        <h2 class="fw-bold mt-2 mb-0 text-dark"><?= $totalKaryawan ?></h2>
                        <small class="text-muted">Karyawan aktif</small>
                    </div>
                    <div class="stat-icon primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tepat Waktu -->
        <div class="col-sm-6 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase">Tepat Waktu</div>
                        <h2 class="fw-bold mt-2 mb-0 text-success"><?= $tepatWaktu ?></h2>
                        <small class="text-success fw-medium"><i class="bi bi-check-circle-fill me-1"></i>Masuk &le; <?= $batasMasukFormatted ?></small>
                    </div>
                    <div class="stat-icon success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terlambat -->
        <div class="col-sm-6 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase">Terlambat</div>
                        <h2 class="fw-bold mt-2 mb-0 text-warning"><?= $terlambat ?></h2>
                        <small class="text-warning fw-medium"><i class="bi bi-exclamation-triangle-fill me-1"></i>Masuk &gt; <?= $batasMasukFormatted ?></small>
                    </div>
                    <div class="stat-icon warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Izin / Sakit -->
        <div class="col-sm-6 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase">Izin / Sakit</div>
                        <h2 class="fw-bold mt-2 mb-0 text-info"><?= $izinSakit ?></h2>
                        <small class="text-muted">Pemberitahuan</small>
                    </div>
                    <div class="stat-icon info">
                        <i class="bi bi-file-medical-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Belum Hadir -->
        <div class="col-sm-6 col-xl">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-semibold small text-uppercase">Belum Hadir</div>
                        <h2 class="fw-bold mt-2 mb-0 text-danger"><?= $belumAbsen ?></h2>
                        <small class="text-muted">Belum ada catatan</small>
                    </div>
                    <div class="stat-icon danger">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table Card -->
    <div class="modern-card overflow-hidden">
        <div class="p-4 border-bottom bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar2-check text-success"></i>
                    Laporan Presensi Hari Ini
                </h5>
                <span class="text-muted small"><?= $tanggalFormat ?> &bull; Batas Masuk: <strong><?= $batasMasukFormatted ?> WIB</strong></span>
            </div>

            <!-- Table Actions -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-icon-group" style="min-width: 200px;">
                    <i class="bi bi-search"></i>
                    <input type="text" id="filterAbsensi" class="form-control form-control-sm" placeholder="Cari nama / NIK...">
                </div>

                <a href="rekap.php" class="btn btn-outline-primary btn-sm" title="Buka Halaman Rekapitulasi Lengkap">
                    <i class="bi bi-calendar3"></i> Rekap
                </a>

                <a href="rekap-excel.php?tgl_mulai=<?= $today ?>&tgl_akhir=<?= $today ?>" class="btn btn-success btn-sm text-white" title="Export Presensi Hari Ini ke Excel">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Excel
                </a>

                <a href="rekap-pdf.php?tgl_mulai=<?= $today ?>&tgl_akhir=<?= $today ?>" target="_blank" class="btn btn-danger btn-sm text-white" title="Cetak atau Export Presensi Hari Ini ke PDF">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
                </a>

                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableToCSV('tableAbsensiHariIni', 'presensi-<?= $today ?>.csv')" title="Export ke CSV">
                    <i class="bi bi-download"></i> CSV
                </button>

                <a href="index.php" class="btn btn-light btn-sm border" title="Refresh">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table modern-table" id="tableAbsensiHariIni">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Karyawan</th>
                        <th>Jabatan</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Durasi Kerja</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($laporan)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-clipboard-x"></i>
                                </div>
                                <h6 class="fw-bold text-dark">Belum Ada Aktivitas Presensi Hari Ini</h6>
                                <p class="text-muted small mb-0">Belum ada karyawan yang melakukan absen masuk atau pengajuan izin/sakit hari ini.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($laporan as $i => $row): 
                        $durasi = '-';
                        if (!empty($row['jam_masuk']) && !empty($row['jam_pulang'])) {
                            $tMasuk  = strtotime($row['jam_masuk']);
                            $tPulang = strtotime($row['jam_pulang']);
                            $diffSec = max(0, $tPulang - $tMasuk);
                            $jam     = floor($diffSec / 3600);
                            $menit   = floor(($diffSec % 3600) / 60);
                            $durasi  = "{$jam} jam {$menit} mnt";
                        }

                        $parts = explode(' ', trim($row['nama']));
                        $initial = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                    ?>
                    <tr>
                        <td class="text-muted fw-semibold"><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar-circle sm">
                                    <?= htmlspecialchars($initial) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama']) ?></div>
                                    <div class="text-muted small" style="font-size: 0.78rem;">NIK: <?= htmlspecialchars($row['nik']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($row['jabatan'] ?? '-') ?></span>
                        </td>
                        <td>
                            <?php if (!empty($row['jam_masuk'])): ?>
                                <span class="badge-soft-success">
                                    <i class="bi bi-box-arrow-in-right"></i> <?= date('H:i', strtotime($row['jam_masuk'])) ?> WIB
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['jam_pulang'])): ?>
                                <span class="badge-soft-primary">
                                    <i class="bi bi-box-arrow-right"></i> <?= date('H:i', strtotime($row['jam_pulang'])) ?> WIB
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

<!-- Modal Pengaturan Batas Jam Masuk & WA Admin -->
<div class="modal fade" id="modalPengaturan" tabindex="-1" aria-labelledby="modalPengaturanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="brand-icon" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalPengaturanLabel">Pengaturan Presensi & Notifikasi</h5>
                        <small class="text-muted">Konfigurasi batas keterlambatan dan nomor admin</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="pengaturan.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <i class="bi bi-clock-fill text-warning me-1"></i> Batas Waktu Jam Masuk (Tepat Waktu)
                        </label>
                        <input type="time" name="jam_masuk_maksimal" class="form-control form-control-lg" value="<?= substr($batasMasuk, 0, 5) ?>" required step="60">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            *Karyawan yang melakukan absen masuk melebihi jam ini akan otomatis berstatus <strong>TERLAMBAT</strong>.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">
                            <i class="bi bi-whatsapp text-success me-1"></i> Nomor WhatsApp Admin Penerima Notifikasi
                        </label>
                        <div class="input-icon-group">
                            <i class="bi bi-telephone"></i>
                            <input type="text" name="admin_phone" class="form-control" value="<?= htmlspecialchars(ADMIN_PHONE) ?>" placeholder="Contoh: 081234567890" required>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            *Nomor ini akan menerima notifikasi otomatis saat karyawan masuk tepat waktu, terlambat, pulang, atau izin/sakit.
                        </small>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        setupTableSearch('filterAbsensi', 'tableAbsensiHariIni');
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
