<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$karyawan_id = $_SESSION['user_id'];
$today       = date('Y-m-d');

// Ambil detail karyawan
$stmtUser = $pdo->prepare("SELECT * FROM karyawan WHERE id = ?");
$stmtUser->execute([$karyawan_id]);
$karyawan = $stmtUser->fetch();

if (!$karyawan) {
    header("Location: ../auth/login.php");
    exit;
}

// -------------------------------------------------------------
// PROSES POST: PENGAJUAN IZIN / SAKIT
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $status     = strtoupper(trim($_POST['status'] ?? 'IZIN'));
    $keterangan = trim($_POST['keterangan'] ?? '');
    $tglPilihan = trim($_POST['tanggal'] ?? $today);

    // Validasi format tanggal (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglPilihan)) {
        $tglPilihan = $today;
    }

    if (!in_array($status, ['IZIN', 'SAKIT'])) {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'title'   => 'Tidak Valid',
            'message' => 'Status pengajuan hanya boleh Izin atau Sakit!'
        ];
        header("Location: izin.php");
        exit;
    }

    if (empty($keterangan)) {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'title'   => 'Keterangan Kosong',
            'message' => 'Harap berikan alasan atau keterangan pengajuan Anda!'
        ];
        header("Location: izin.php");
        exit;
    }

    // Cek apakah sudah ada catatan absensi pada tanggal tersebut
    $stmtCek = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
    $stmtCek->execute([$karyawan_id, $tglPilihan]);
    $existing = $stmtCek->fetch();

    if ($existing) {
        $tglIndo = date('d/m/Y', strtotime($tglPilihan));
        $_SESSION['flash'] = [
            'type'    => 'warning',
            'title'   => 'Catatan Sudah Ada',
            'message' => "Anda sudah memiliki catatan presensi ({$existing['status']}) untuk tanggal {$tglIndo}!"
        ];
        header("Location: izin.php");
        exit;
    }

    // Simpan pengajuan ke database
    $stmtInsert = $pdo->prepare("INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, jam_pulang, status, keterangan) VALUES (?, ?, NULL, NULL, ?, ?)");
    if ($stmtInsert->execute([$karyawan_id, $tglPilihan, $status, $keterangan])) {
        
        $tglFormat = date('d F Y', strtotime($tglPilihan));
        $cleanHp   = preg_replace('/[^0-9]/', '', $karyawan['no_hp'] ?? '');
        if (substr($cleanHp, 0, 1) === '0') {
            $cleanHp = '62' . substr($cleanHp, 1);
        }

        // 1. Notifikasi WhatsApp ke Karyawan
        $pesanKaryawan = "PENGAJUAN " . strtoupper($status) . " BERHASIL 📋\n\n" .
                         "Halo *{$karyawan['nama']}*,\n" .
                         "Pengajuan " . strtoupper($status) . " Anda telah berhasil dicatat di Sistem Presensi.\n\n" .
                         "  • NIK: {$karyawan['nik']}\n" .
                         "  • Tanggal: {$tglFormat}\n" .
                         "  • Status: " . strtoupper($status) . "\n" .
                         "  • Keterangan: {$keterangan}\n\n" .
                         ($status === 'SAKIT' ? "Semoga lekas sembuh dan dapat beristirahat dengan baik!\n" : "Terima kasih telah memberikan pemberitahuan izin.\n") .
                         "Terima kasih!";

        sendWhatsAppNotification($cleanHp, $pesanKaryawan);

        // 2. Notifikasi WhatsApp ke Admin
        $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
        if (substr($cleanAdminHp, 0, 1) === '0') {
            $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
        }

        if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
            usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
            $pesanAdmin = "NOTIFIKASI PENGAJUAN " . strtoupper($status) . " 📋\n\n" .
                          "Halo Admin,\n" .
                          "Karyawan telah mengajukan pemberitahuan tidak hadir.\n\n" .
                          "  • NIK: {$karyawan['nik']}\n" .
                          "  • Nama: {$karyawan['nama']}\n" .
                          "  • Jabatan: {$karyawan['jabatan']}\n" .
                          "  • Status: " . strtoupper($status) . "\n" .
                          "  • Tanggal: {$tglFormat}\n" .
                          "  • Keterangan: {$keterangan}\n\n" .
                          "Data pengajuan telah dicatat di sistem.\n" .
                          "Terima kasih!";

            sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
        }

        $_SESSION['flash'] = [
            'type'    => 'success',
            'title'   => "Pengajuan {$status} Berhasil!",
            'message' => "Pengajuan Anda telah dicatat dan notifikasi WhatsApp berhasil dikirimkan ke Admin."
        ];
    } else {
        $_SESSION['flash'] = [
            'type'    => 'error',
            'title'   => 'Gagal',
            'message' => 'Terjadi kesalahan sistem saat menyimpan pengajuan!'
        ];
    }

    header("Location: izin.php");
    exit;
}

// -------------------------------------------------------------
// PROSES GET: TAMPILKAN BAGIAN IZIN & SAKIT
// -------------------------------------------------------------
$pageTitle  = 'Bagian Izin & Sakit';
$activeMenu = 'izin';

// Pre-selected jenis pengajuan dari URL (?jenis=SAKIT atau ?jenis=IZIN)
$defaultJenis = strtoupper($_GET['jenis'] ?? 'IZIN');
if (!in_array($defaultJenis, ['IZIN', 'SAKIT'])) {
    $defaultJenis = 'IZIN';
}

// Cek absensi hari ini
$stmtToday = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
$stmtToday->execute([$karyawan_id, $today]);
$absenHariIni = $stmtToday->fetch();

// Statistik bulan ini
$currMonth = date('m');
$currYear  = date('Y');

$stmtStatIzin = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE id_karyawan = ? AND status = 'IZIN' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtStatIzin->execute([$karyawan_id, $currMonth, $currYear]);
$totalIzinBulanIni = (int) $stmtStatIzin->fetchColumn();

$stmtStatSakit = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE id_karyawan = ? AND status = 'SAKIT' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtStatSakit->execute([$karyawan_id, $currMonth, $currYear]);
$totalSakitBulanIni = (int) $stmtStatSakit->fetchColumn();

$stmtStatHadir = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE id_karyawan = ? AND status IN ('HADIR', 'TERLAMBAT') AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtStatHadir->execute([$karyawan_id, $currMonth, $currYear]);
$totalHadirBulanIni = (int) $stmtStatHadir->fetchColumn();

// Riwayat khusus pengajuan Izin & Sakit karyawan ini
$stmtHistory = $pdo->prepare("SELECT * FROM absensi WHERE id_karyawan = ? AND status IN ('IZIN', 'SAKIT') ORDER BY tanggal DESC, id DESC LIMIT 50");
$stmtHistory->execute([$karyawan_id]);
$riwayatIzinSakit = $stmtHistory->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width: 1000px;">
    
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Portal Presensi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Izin & Sakit</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-file-earmark-medical text-primary me-2"></i>Bagian Izin & Sakit
            </h3>
            <p class="text-muted small mb-0">Formulir pengajuan tidak hadir resmi dan riwayat catatan izin serta sakit Anda.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Ke Presensi Harian
            </a>
        </div>
    </div>

    <!-- KPI Summary Bulan Ini -->
    <div class="row g-3 mb-4">
        <!-- Izin Bulan Ini -->
        <div class="col-sm-4">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Izin Bulan Ini</div>
                        <h3 class="fw-bold text-info mt-1 mb-0"><?= $totalIzinBulanIni ?> <span class="fs-6 fw-normal text-muted">Hari</span></h3>
                    </div>
                    <div class="stat-icon info" style="width: 46px; height: 46px; font-size: 1.3rem;">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sakit Bulan Ini -->
        <div class="col-sm-4">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Sakit Bulan Ini</div>
                        <h3 class="fw-bold text-danger mt-1 mb-0"><?= $totalSakitBulanIni ?> <span class="fs-6 fw-normal text-muted">Hari</span></h3>
                    </div>
                    <div class="stat-icon danger" style="width: 46px; height: 46px; font-size: 1.3rem;">
                        <i class="bi bi-heart-pulse-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kehadiran Bulan Ini -->
        <div class="col-sm-4">
            <div class="stat-card p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Kehadiran Kerja</div>
                        <h3 class="fw-bold text-success mt-1 mb-0"><?= $totalHadirBulanIni ?> <span class="fs-6 fw-normal text-muted">Hari</span></h3>
                    </div>
                    <div class="stat-icon success" style="width: 46px; height: 46px; font-size: 1.3rem;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Form Pengajuan (Kiri) & Info / Status Hari Ini (Kanan) -->
    <div class="row g-4 mb-4">
        <!-- Form Pengajuan -->
        <div class="col-lg-7">
            <div class="modern-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Formulir Pengajuan</h5>
                        <small class="text-muted">Isi data ketidakhadiran dengan jelas</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-semibold small">
                        <i class="bi bi-whatsapp me-1"></i> Auto Notif WA
                    </span>
                </div>

                <?php if ($absenHariIni): ?>
                    <div class="alert alert-info border-0 rounded-3 p-3 mb-3 d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle-fill text-info fs-5 mt-1"></i>
                        <div>
                            <div class="fw-bold">Status Presensi Hari Ini: <?= htmlspecialchars($absenHariIni['status']) ?></div>
                            <div class="small">
                                Anda sudah memiliki catatan presensi hari ini
                                <?= !empty($absenHariIni['jam_masuk']) ? '(Masuk: ' . date('H:i', strtotime($absenHariIni['jam_masuk'])) . ' WIB)' : '' ?>.
                                <?php if (in_array($absenHariIni['status'], ['IZIN', 'SAKIT'])): ?>
                                    Keterangan: "<?= htmlspecialchars($absenHariIni['keterangan'] ?? '-') ?>"
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="izin.php" method="POST" id="formPengajuanIzin">
                    <!-- Pilihan Jenis Pengajuan -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Pilih Jenis Pengajuan <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="w-100 p-3 border rounded-3 cursor-pointer text-center position-relative option-card" id="cardOptIzin" style="cursor: pointer;">
                                    <input type="radio" name="status" id="radioIzin" value="IZIN" class="form-check-input position-absolute top-0 end-0 m-2" <?= $defaultJenis === 'IZIN' ? 'checked' : '' ?> onchange="updateOptionCards()">
                                    <i class="bi bi-calendar2-check-fill text-primary fs-3 d-block mb-1"></i>
                                    <div class="fw-bold text-dark">IZIN</div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Urusan Pribadi / Keluarga</small>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="w-100 p-3 border rounded-3 cursor-pointer text-center position-relative option-card" id="cardOptSakit" style="cursor: pointer;">
                                    <input type="radio" name="status" id="radioSakit" value="SAKIT" class="form-check-input position-absolute top-0 end-0 m-2" <?= $defaultJenis === 'SAKIT' ? 'checked' : '' ?> onchange="updateOptionCards()">
                                    <i class="bi bi-heart-pulse-fill text-danger fs-3 d-block mb-1"></i>
                                    <div class="fw-bold text-dark">SAKIT</div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Istirahat Medis / Dokter</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tanggal Pengajuan -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Tanggal Pengajuan <span class="text-danger">*</span></label>
                        <div class="input-icon-group">
                            <i class="bi bi-calendar-event"></i>
                            <input type="date" name="tanggal" class="form-control" value="<?= $today ?>" required>
                        </div>
                        <div class="form-text small text-muted">Default tanggal hari ini. Anda juga dapat memilih tanggal izin/sakit yang ditentukan.</div>
                    </div>

                    <!-- Alasan / Keterangan -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Alasan / Keterangan Lengkap <span class="text-danger">*</span></label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Mengalami demam tinggi dan disarankan dokter istirahat / Ada keperluan keluarga yang mendesak di luar kota..." required></textarea>
                    </div>

                    <!-- Notifikasi Alert Info -->
                    <div class="p-3 bg-light rounded-3 small text-muted d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-whatsapp text-success fs-4"></i>
                        <div>
                            Setelah disimpan, pesan konfirmasi otomatis dikirimkan ke <strong>WhatsApp Admin</strong> dan nomor WhatsApp Anda.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-send-fill me-1"></i> Kirim Pengajuan Sekarang
                    </button>
                </form>
            </div>
        </div>

        <!-- Info & Petunjuk (Kanan) -->
        <div class="col-lg-5">
            <div class="modern-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill text-primary"></i> Panduan Ketidakhadiran
                    </h5>

                    <div class="d-flex align-items-start gap-3 mb-3 p-3 bg-light rounded-3">
                        <div class="badge bg-primary rounded-circle p-2 mt-1">
                            <i class="bi bi-chat-quote-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark small">Pengajuan Izin</div>
                            <div class="text-muted small">
                                Izin digunakan untuk keperluan mendesak, urusan keluarga, atau dinas luar yang telah disepakati bersama manajemen.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3 mb-3 p-3 bg-light rounded-3">
                        <div class="badge bg-danger rounded-circle p-2 mt-1">
                            <i class="bi bi-heart-pulse-fill"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark small">Pengajuan Sakit</div>
                            <div class="text-muted small">
                                Pengajuan sakit diajukan saat Anda dalam kondisi kurang sehat atau rawat inap medis. Pastikan beristirahat hingga pulih.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3">
                        <div class="badge bg-success rounded-circle p-2 mt-1">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark small">Tercatat Otomatis</div>
                            <div class="text-muted small">
                                Status Izin dan Sakit langsung terdata di laporan presensi admin tanpa perlu mengisi jam masuk dan pulang.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-top mt-3 text-muted small text-center">
                    Pertanyaan terkait jatah cuti? Hubungi tim HRD / Administrator kantor.
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Pengajuan Izin & Sakit -->
    <div class="modern-card overflow-hidden">
        <div class="p-4 border-bottom bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-secondary"></i>
                    Riwayat Izin & Sakit Anda
                </h5>
                <span class="text-muted small">Daftar rekapan permohonan izin dan sakit yang pernah Anda ajukan</span>
            </div>
            <div class="input-icon-group" style="min-width: 240px;">
                <i class="bi bi-search"></i>
                <input type="text" id="filterRiwayatIzin" class="form-control form-control-sm" placeholder="Cari keterangan / tanggal...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table modern-table mb-0" id="tableRiwayatIzin">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Tanggal</th>
                        <th>Jenis Pengajuan</th>
                        <th>Alasan / Keterangan</th>
                        <th class="text-center">Status Notifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayatIzinSakit)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-clipboard2-check"></i>
                                </div>
                                <h6 class="fw-bold text-dark">Belum Ada Riwayat Izin atau Sakit</h6>
                                <p class="text-muted small mb-0">Anda belum pernah mengajukan permohonan izin atau sakit di sistem.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($riwayatIzinSakit as $i => $row): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td>
                            <div class="fw-semibold text-dark">
                                <i class="bi bi-calendar-event me-1 text-muted"></i>
                                <?= date('d M Y', strtotime($row['tanggal'])) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'IZIN'): ?>
                                <span class="badge-soft-primary">
                                    <i class="bi bi-info-circle-fill me-1"></i> IZIN
                                </span>
                            <?php elseif ($row['status'] === 'SAKIT'): ?>
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 fw-bold">
                                    <i class="bi bi-heart-pulse-fill me-1"></i> SAKIT
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="fw-medium text-dark"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-semibold small">
                                <i class="bi bi-whatsapp me-1"></i> Terkirim ke WA
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function updateOptionCards() {
    const isIzin = document.getElementById('radioIzin').checked;
    const cardIzin = document.getElementById('cardOptIzin');
    const cardSakit = document.getElementById('cardOptSakit');

    if (isIzin) {
        cardIzin.classList.add('border-primary', 'bg-primary-subtle');
        cardSakit.classList.remove('border-danger', 'bg-danger-subtle');
    } else {
        cardSakit.classList.add('border-danger', 'bg-danger-subtle');
        cardIzin.classList.remove('border-primary', 'bg-primary-subtle');
    }
}
document.addEventListener('DOMContentLoaded', updateOptionCards);

// Filter pencarian tabel riwayat izin
document.getElementById('filterRiwayatIzin')?.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#tableRiwayatIzin tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
