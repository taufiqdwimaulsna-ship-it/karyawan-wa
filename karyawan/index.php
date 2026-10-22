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

$pageTitle = 'Kelola Data Karyawan';
$activeMenu = 'karyawan';

$stmt = $pdo->query("SELECT * FROM karyawan ORDER BY role ASC, id DESC");
$karyawanList = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="../admin/index.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Data Karyawan</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-1">Manajemen Karyawan</h3>
            <p class="text-muted small mb-0">Kelola informasi karyawan, hak akses, dan sinkronisasi notifikasi WhatsApp.</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="tambah.php" class="btn btn-wa text-white">
                <i class="bi bi-person-plus-fill"></i> Tambah Karyawan Baru
            </a>
            <a href="karyawan-excel.php" class="btn btn-success text-white" title="Export Data Karyawan ke Excel">
                <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Excel
            </a>
            <a href="karyawan-pdf.php" target="_blank" class="btn btn-danger text-white" title="Cetak atau Export ke PDF">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
            </a>
            <button type="button" class="btn btn-outline-secondary" onclick="exportTableToCSV('tableKaryawan', 'data-karyawan.csv')" title="Export Data Karyawan ke CSV">
                <i class="bi bi-download"></i> CSV
            </button>
        </div>
    </div>

    <!-- Main Card -->
    <div class="modern-card overflow-hidden">
        <!-- Toolbar -->
        <div class="p-4 border-bottom bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill">
                    Total: <?= count($karyawanList) ?> Karyawan
                </span>
            </div>

            <div class="input-icon-group" style="min-width: 280px;">
                <i class="bi bi-search"></i>
                <input type="text" id="filterKaryawan" class="form-control form-control-sm" placeholder="Cari nama, NIK, jabatan, no HP...">
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table modern-table" id="tableKaryawan">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Karyawan</th>
                        <th>Jabatan</th>
                        <th>Kontak WhatsApp</th>
                        <th>Email Akun</th>
                        <th>Role Akses</th>
                        <th style="width: 140px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($karyawanList)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                                <h6 class="fw-bold text-dark">Belum Ada Karyawan</h6>
                                <p class="text-muted small mb-3">Tambahkan karyawan pertama Anda untuk memulai manajemen presensi.</p>
                                <a href="tambah.php" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-lg"></i> Tambah Karyawan
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($karyawanList as $i => $row): 
                        $parts = explode(' ', trim($row['nama']));
                        $initial = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                        
                        // Normalisasi nomor HP untuk link WA
                        $cleanHp = preg_replace('/[^0-9]/', '', $row['no_hp']);
                        if (substr($cleanHp, 0, 1) === '0') {
                            $cleanHp = '62' . substr($cleanHp, 1);
                        }
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
                            <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($row['jabatan'] ?? '-') ?></span>
                        </td>
                        <td>
                            <a href="https://wa.me/<?= $cleanHp ?>" target="_blank" class="d-inline-flex align-items-center gap-1 text-decoration-none badge-soft-success" title="Kirim Chat WhatsApp">
                                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($row['no_hp']) ?>
                                <i class="bi bi-box-arrow-up-right" style="font-size: 0.65rem;"></i>
                            </a>
                        </td>
                        <td>
                            <span class="text-muted small"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($row['email']) ?></span>
                        </td>
                        <td>
                            <?php if ($row['role'] === 'admin'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-bold">
                                    <i class="bi bi-shield-check me-1"></i> Admin
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1 fw-medium">
                                    Karyawan
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary" title="Edit Data">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                <button type="button" class="btn btn-outline-danger" title="Hapus Karyawan" 
                                        onclick="confirmDelete('hapus.php?id=<?= $row['id'] ?>', 'Hapus <?= htmlspecialchars(addslashes($row['nama'])) ?>?', 'Data karyawan beserta riwayat absensi akan terhapus!')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-outline-secondary opacity-50" disabled title="Akun Anda Sendiri">
                                    <i class="bi bi-lock-fill"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        setupTableSearch('filterKaryawan', 'tableKaryawan');
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

