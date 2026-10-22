<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$pageTitle = 'Portal Presensi Karyawan';
$activeMenu = 'absensi';

$karyawan_id = $_SESSION['user_id'];
$today       = date('Y-m-d');

// Ambil data detail karyawan
$stmtUser = $pdo->prepare("SELECT * FROM karyawan WHERE id = ?");
$stmtUser->execute([$karyawan_id]);
$karyawan = $stmtUser->fetch();

// Cek status absensi hari ini
$stmtToday = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
$stmtToday->execute([$karyawan_id, $today]);
$absenHariIni = $stmtToday->fetch();

// Riwayat Absensi Pribadi
$stmtLog = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? ORDER BY tanggal DESC, id DESC LIMIT 15");
$stmtLog->execute([$karyawan_id]);
$logs = $stmtLog->fetchAll();

// Pesan query param jika ada
$msg = $_GET['msg'] ?? '';
$msgType = $_GET['type'] ?? 'info';

// Batas jam masuk
$batasMasuk = defined('JAM_MASUK_MAKSIMAL') ? JAM_MASUK_MAKSIMAL : '08:00:00';
$batasMasukFormatted = date('H:i', strtotime($batasMasuk));

// Format Hari & Tanggal
$hariList = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$bulanList = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$hariIni = $hariList[date('l')] ?? date('l');
$tanggalFormat = $hariIni . ', ' . date('d') . ' ' . ($bulanList[date('m')] ?? date('F')) . ' ' . date('Y');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width: 960px;">
    
    <!-- Alert Message jika ada -->
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType === 'success' ? 'success' : ($msgType === 'warning' ? 'warning' : 'info') ?> border-0 shadow-sm rounded-4 p-3 mb-4 d-flex align-items-center gap-3">
            <i class="bi <?= $msgType === 'success' ? 'bi-check-circle-fill text-success' : 'bi-info-circle-fill text-info' ?> fs-4"></i>
            <div class="fw-medium"><?= htmlspecialchars($msg) ?></div>
        </div>
    <?php endif; ?>

    <!-- Header Grid: Live Clock + Status -->
    <div class="row g-4 mb-4">
        <!-- Live Clock Widget -->
        <div class="col-md-6">
            <div class="clock-widget h-100 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-white bg-opacity-10 text-success rounded-pill px-3 py-1 small border border-white border-opacity-10">
                        <i class="bi bi-clock-fill me-1"></i> Waktu Server (WIB)
                    </span>
                    <span class="text-white-50 small" id="digitalClockDate"><?= $tanggalFormat ?></span>
                </div>
                
                <div class="my-2">
                    <div class="digital-time" id="digitalClockTime">--:--:--</div>
                    <div class="text-white-50 small mt-1 d-flex align-items-center gap-2">
                        <i class="bi bi-bell-fill text-warning"></i>
                        <span>Batas Waktu Tepat Waktu: <strong><?= $batasMasukFormatted ?> WIB</strong></span>
                    </div>
                </div>

                <div class="pt-3 border-top border-white border-opacity-10 d-flex justify-content-between align-items-center text-white-50 small">
                    <span><i class="bi bi-geo-alt-fill text-success"></i> Kantor Pusat</span>
                    <span>Presensi otomatis dicatat</span>
                </div>
            </div>
        </div>

        <!-- Status Card -->
        <div class="col-md-6">
            <div class="modern-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small fw-semibold text-uppercase">Status Kehadiran Hari Ini</span>
                        <?php if (!$absenHariIni): ?>
                            <span class="badge-soft-danger"><span class="status-dot bg-danger"></span> Belum Absen</span>
                        <?php elseif ($absenHariIni['status'] === 'HADIR'): ?>
                            <span class="badge-soft-success"><span class="status-dot bg-success"></span> Hadir Tepat Waktu</span>
                        <?php elseif ($absenHariIni['status'] === 'TERLAMBAT'): ?>
                            <span class="badge-soft-warning"><span class="status-dot bg-warning"></span> Terlambat</span>
                        <?php elseif ($absenHariIni['status'] === 'IZIN'): ?>
                            <span class="badge-soft-primary"><span class="status-dot bg-primary"></span> Izin</span>
                        <?php elseif ($absenHariIni['status'] === 'SAKIT'): ?>
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 fw-bold"><i class="bi bi-heart-pulse-fill me-1"></i> Sakit</span>
                        <?php endif; ?>
                    </div>

                    <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($karyawan['nama']) ?></h4>
                    <p class="text-muted small mb-3">
                        NIK: <?= htmlspecialchars($karyawan['nik']) ?> &bull; Jabatan: <?= htmlspecialchars($karyawan['jabatan']) ?>
                    </p>

                    <div class="p-3 bg-light rounded-3">
                        <?php if ($absenHariIni && in_array($absenHariIni['status'], ['IZIN', 'SAKIT'])): ?>
                            <div class="text-center py-1">
                                <div class="badge bg-secondary-subtle text-secondary fs-6 mb-1">
                                    Status: <?= $absenHariIni['status'] ?>
                                </div>
                                <div class="small text-muted">
                                    Alasan: "<?= htmlspecialchars($absenHariIni['keterangan'] ?? '-') ?>"
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row g-2 text-center">
                                <div class="col-6 border-end">
                                    <div class="text-muted small">Jam Masuk</div>
                                    <div class="fw-bold text-success fs-5">
                                        <?= !empty($absenHariIni['jam_masuk']) ? date('H:i', strtotime($absenHariIni['jam_masuk'])) . ' WIB' : '--:--' ?>
                                    </div>
                                    <?php if (!empty($absenHariIni['keterangan']) && $absenHariIni['status'] === 'TERLAMBAT'): ?>
                                        <small class="text-danger fw-semibold" style="font-size: 0.72rem;"><?= htmlspecialchars($absenHariIni['keterangan']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small">Jam Pulang</div>
                                    <div class="fw-bold text-primary fs-5">
                                        <?= !empty($absenHariIni['jam_pulang']) ? date('H:i', strtotime($absenHariIni['jam_pulang'])) . ' WIB' : '--:--' ?>
                                    </div>
                                    <?php if (!empty($absenHariIni['jam_pulang'])): ?>
                                        <small class="text-success fw-semibold" style="font-size: 0.72rem;">Sudah Checkout</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 text-muted small d-flex justify-content-between align-items-center">
                    <span class="d-flex align-items-center gap-1">
                        <i class="bi bi-whatsapp text-success"></i> WA: <strong><?= htmlspecialchars($karyawan['no_hp'] ?: '-') ?></strong>
                    </span>
                    <?php if (!$absenHariIni): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-1" data-bs-toggle="modal" data-bs-target="#modalIzinSakit" style="font-size: 0.78rem;">
                            <i class="bi bi-file-earmark-medical me-1"></i> Ajukan Izin / Sakit
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Action Buttons -->
    <div class="modern-card p-4 mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-fingerprint text-success fs-4"></i> Tombol Presensi
                </h5>
                <p class="text-muted small mb-0">Klik tombol di bawah ini sesuai jadwal. Melebihi <strong><?= $batasMasukFormatted ?> WIB</strong> otomatis berstatus <strong>Terlambat</strong>.</p>
            </div>
            <?php if (!$absenHariIni): ?>
            <button type="button" class="btn btn-outline-primary btn-sm align-self-start align-self-sm-auto" data-bs-toggle="modal" data-bs-target="#modalIzinSakit">
                <i class="bi bi-envelope-paper-fill me-1"></i> Form Izin / Sakit
            </button>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <!-- Tombol Absen Masuk -->
            <div class="col-sm-6">
                <?php 
                $isAdaPengajuan = ($absenHariIni && in_array($absenHariIni['status'], ['IZIN', 'SAKIT']));
                $sudahMasuk = ($absenHariIni && !empty($absenHariIni['jam_masuk'])); 
                $disabledMasuk = $sudahMasuk || $isAdaPengajuan;
                ?>
                <a href="masuk.php" class="btn btn-success btn-lg-action w-100 shadow-sm <?= $disabledMasuk ? 'disabled opacity-50 pe-none' : '' ?>">
                    <i class="bi bi-box-arrow-in-right fs-4"></i>
                    <div class="text-start">
                        <div class="fw-bold">
                            <?php if ($isAdaPengajuan): ?>
                                STATUS <?= $absenHariIni['status'] ?>
                            <?php elseif ($sudahMasuk): ?>
                                SUDAH MASUK (<?= $absenHariIni['status'] ?>)
                            <?php else: ?>
                                ABSEN MASUK SEKARANG
                            <?php endif; ?>
                        </div>
                        <small class="opacity-75" style="font-size: 0.75rem;">
                            <?= $sudahMasuk ? 'Pukul ' . date('H:i', strtotime($absenHariIni['jam_masuk'])) . ' WIB' : 'Catat waktu kedatangan Anda' ?>
                        </small>
                    </div>
                </a>
            </div>

            <!-- Tombol Absen Pulang -->
            <div class="col-sm-6">
                <?php 
                $belumBolehPulang = (!$absenHariIni || empty($absenHariIni['jam_masuk']) || $isAdaPengajuan);
                $sudahPulang = ($absenHariIni && !empty($absenHariIni['jam_pulang']));
                $disabledPulang = $belumBolehPulang || $sudahPulang;
                ?>
                <a href="pulang.php" class="btn btn-wa btn-lg-action w-100 shadow-sm <?= $disabledPulang ? 'disabled opacity-50 pe-none' : '' ?>">
                    <i class="bi bi-box-arrow-right fs-4"></i>
                    <div class="text-start">
                        <div class="fw-bold"><?= $sudahPulang ? 'SUDAH ABSEN PULANG' : 'ABSEN PULANG SEKARANG' ?></div>
                        <small class="opacity-75" style="font-size: 0.75rem;">
                            <?= $sudahPulang ? 'Pukul ' . date('H:i', strtotime($absenHariIni['jam_pulang'])) . ' WIB' : ($belumBolehPulang ? 'Harus absen masuk terlebih dahulu' : 'Catat waktu kepulangan Anda') ?>
                        </small>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Bagian Izin & Sakit -->
    <div class="modern-card p-4 mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-medical text-primary fs-4"></i> Bagian Izin & Sakit
                </h5>
                <p class="text-muted small mb-0">Berhalangan hadir bekerja? Pilih pengajuan di bawah untuk pemberitahuan resmi.</p>
            </div>
            <a href="izin.php" class="btn btn-outline-primary btn-sm align-self-start align-self-sm-auto">
                <i class="bi bi-journal-check me-1"></i> Buka Halaman Izin & Sakit
            </a>
        </div>

        <div class="row g-3">
            <!-- Kartu Pengajuan Izin -->
            <div class="col-sm-6">
                <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="stat-icon info" style="width: 44px; height: 44px; font-size: 1.3rem;">
                                <i class="bi bi-calendar2-check-fill"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">Pengajuan Izin</div>
                                <small class="text-muted">Keperluan mendesak / dinas / keluarga</small>
                            </div>
                        </div>
                        <p class="small text-muted mb-3">Ajukan izin tidak masuk kerja dengan menyertakan keterangan lengkap. Notifikasi akan diteruskan ke WhatsApp Admin.</p>
                    </div>
                    <a href="izin.php?jenis=IZIN" class="btn btn-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-pencil-square me-1"></i> Form Pengajuan Izin
                    </a>
                </div>
            </div>

            <!-- Kartu Pengajuan Sakit -->
            <div class="col-sm-6">
                <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="stat-icon danger" style="width: 44px; height: 44px; font-size: 1.3rem;">
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">Pengajuan Sakit</div>
                                <small class="text-muted">Kondisi kesehatan atau istirahat medis</small>
                            </div>
                        </div>
                        <p class="small text-muted mb-3">Laporkan kondisi sakit agar manajemen mencatat dispensasi kehadiran resmi tanpa mengurangi hak Anda.</p>
                    </div>
                    <a href="izin.php?jenis=SAKIT" class="btn btn-danger btn-sm w-100 fw-semibold text-white">
                        <i class="bi bi-heart-pulse me-1"></i> Form Pengajuan Sakit
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Kehadiran Pribadi -->
    <div class="modern-card overflow-hidden">
        <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    Riwayat Kehadiran Pribadi
                </h5>
                <span class="text-muted small">Catatan 15 aktivitas presensi terakhir Anda</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table modern-table mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Durasi Kerja</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-calendar-x"></i>
                                </div>
                                <h6 class="fw-bold text-dark">Belum Ada Riwayat Presensi</h6>
                                <p class="text-muted small mb-0">Lakukan absen masuk hari ini untuk memulai pencatatan kehadiran.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $row): 
                        $durasi = '-';
                        if (!empty($row['jam_masuk']) && !empty($row['jam_pulang'])) {
                            $tMasuk  = strtotime($row['jam_masuk']);
                            $tPulang = strtotime($row['jam_pulang']);
                            $diffSec = max(0, $tPulang - $tMasuk);
                            $jam     = floor($diffSec / 3600);
                            $menit   = floor(($diffSec % 3600) / 60);
                            $durasi  = "{$jam} jam {$menit} mnt";
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">
                                <i class="bi bi-calendar-event me-1 text-muted"></i>
                                <?= date('d M Y', strtotime($row['tanggal'])) ?>
                            </div>
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

<!-- Modal Form Pengajuan Izin / Sakit -->
<div class="modal fade" id="modalIzinSakit" tabindex="-1" aria-labelledby="modalIzinSakitLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="brand-icon" style="width: 36px; height: 36px; font-size: 1rem;">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalIzinSakitLabel">Ajukan Izin atau Sakit</h5>
                        <small class="text-muted">Pemberitahuan otomatis terkirim ke WhatsApp Admin</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="izin.php" method="POST">
                <input type="hidden" name="tanggal" value="<?= $today ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Jenis Pengajuan</label>
                        <div class="d-flex gap-3">
                            <div class="form-check form-check-inline p-3 border rounded-3 flex-fill">
                                <input class="form-check-input" type="radio" name="status" id="optIzin" value="IZIN" checked>
                                <label class="form-check-label fw-bold text-dark ms-1" for="optIzin">
                                    <i class="bi bi-info-circle text-primary me-1"></i> Izin
                                </label>
                                <div class="text-muted" style="font-size: 0.72rem;">Keperluan mendesak/pribadi</div>
                            </div>
                            <div class="form-check form-check-inline p-3 border rounded-3 flex-fill">
                                <input class="form-check-input" type="radio" name="status" id="optSakit" value="SAKIT">
                                <label class="form-check-label fw-bold text-dark ms-1" for="optSakit">
                                    <i class="bi bi-heart-pulse text-danger me-1"></i> Sakit
                                </label>
                                <div class="text-muted" style="font-size: 0.72rem;">Kondisi kesehatan/rawat</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Alasan / Keterangan Lengkap</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Mengalami demam dan perlu istirahat periksa dokter / Ada keperluan keluarga mendesak" required></textarea>
                    </div>

                    <div class="p-3 bg-light rounded-3 small text-muted d-flex align-items-center gap-2">
                        <i class="bi bi-whatsapp text-success fs-5"></i>
                        <span>Admin akan menerima notifikasi WhatsApp segera setelah Anda menekan tombol kirim.</span>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send-fill me-1"></i> Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
