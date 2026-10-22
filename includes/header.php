<?php
require_once __DIR__ . '/../config/database.php';

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'id'   => $_SESSION['user_id'],
        'nama' => $_SESSION['nama'] ?? 'User',
        'nik'  => $_SESSION['nik'] ?? '',
        'role' => $_SESSION['role'] ?? 'karyawan',
    ];
}

$pageTitle = $pageTitle ?? 'Sistem Presensi Karyawan & WhatsApp Gateway';
$activeMenu = $activeMenu ?? '';

// Initial huruf nama untuk avatar
$userInitials = 'U';
if ($currentUser) {
    $parts = explode(' ', trim($currentUser['nama']));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - KaryawanWA</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Custom Modern CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">

<?php if ($currentUser): ?>
<!-- Modern Header / Navbar -->
<nav class="navbar navbar-expand-lg modern-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $currentUser['role'] === 'admin' ? BASE_URL . '/admin/index.php' : BASE_URL . '/absensi/index.php' ?>">
            <div class="brand-icon">
                <i class="bi bi-whatsapp fs-5"></i>
            </div>
            <div>
                <span class="fw-bold fs-5 text-dark tracking-tight">Presensi<span class="text-success">WA</span></span>
                <span class="d-block text-muted" style="font-size: 0.68rem; font-weight: 600; letter-spacing: 0.5px;">WHATSAPP GATEWAY</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <!-- Nav Links -->
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-1">
                <?php if ($currentUser['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'dashboard' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/admin/index.php">
                        <i class="bi bi-grid-fill me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'rekap' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/admin/rekap.php">
                        <i class="bi bi-clipboard-data-fill me-1"></i> Rekap Presensi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'karyawan' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/karyawan/index.php">
                        <i class="bi bi-people-fill me-1"></i> Data Karyawan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'absensi' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/absensi/index.php">
                        <i class="bi bi-fingerprint me-1"></i> Portal Absen
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'izin' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/absensi/izin.php">
                        <i class="bi bi-file-earmark-medical me-1"></i> Izin & Sakit
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'absensi' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/absensi/index.php">
                        <i class="bi bi-fingerprint me-1"></i> Presensi Harian
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-3 fw-medium <?= $activeMenu === 'izin' ? 'active bg-light text-primary fw-bold' : 'text-secondary' ?>" 
                       href="<?= BASE_URL ?>/absensi/izin.php">
                        <i class="bi bi-file-earmark-medical me-1"></i> Izin & Sakit
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right Profile Info -->
            <div class="d-flex align-items-center gap-3">
                <!-- Status WA Gateway Pill -->
                <div class="d-none d-md-flex align-items-center gap-2 bg-light px-3 py-1 rounded-pill border">
                    <span class="status-dot pulse-success"></span>
                    <span class="text-muted fw-semibold" style="font-size: 0.75rem;">Fonnte Gateway</span>
                </div>

                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border bg-white px-3 py-1 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle sm bg-gradient">
                            <?= htmlspecialchars($userInitials) ?>
                        </div>
                        <div class="text-start d-none d-sm-block">
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($currentUser['nama']) ?></div>
                            <div class="badge <?= $currentUser['role'] === 'admin' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' ?>" style="font-size: 0.65rem;">
                                <?= strtoupper($currentUser['role']) ?>
                            </div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2" style="min-width: 200px;">
                        <li class="px-3 py-2 border-bottom mb-1">
                            <div class="small text-muted">Login sebagai</div>
                            <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($currentUser['nama']) ?></div>
                            <small class="text-muted">NIK: <?= htmlspecialchars($currentUser['nik'] ?: '-') ?></small>
                        </li>
                        <li>
                            <a class="dropdown-item rounded-3 text-danger d-flex align-items-center gap-2 py-2" href="<?= BASE_URL ?>/auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Keluar (Logout)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="flex-grow-1">
