<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

$error   = '';
$success = '';

// Cek apakah ini akun pertama (jika iya, jadikan admin)
$countUsers = 0;
try {
    $countUsers = (int)$pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn();
} catch (Exception $e) {
    $countUsers = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik             = trim($_POST['nik'] ?? '');
    $nama            = trim($_POST['nama'] ?? '');
    $jabatan         = trim($_POST['jabatan'] ?? '');
    $no_hp           = trim($_POST['no_hp'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $konfirmasi_pass = $_POST['konfirmasi_password'] ?? '';
    $role            = ($countUsers === 0) ? 'admin' : ($_POST['role'] ?? 'karyawan');

    // Normalisasi nomor HP (misal ubah 08xx jadi format standar)
    $cleanHp = preg_replace('/[^0-9]/', '', $no_hp);
    if (substr($cleanHp, 0, 1) === '0') {
        $cleanHp = '62' . substr($cleanHp, 1);
    }

    if (empty($nik) || empty($nama) || empty($jabatan) || empty($no_hp) || empty($email) || empty($password)) {
        $error = "Semua kolom formulir wajib diisi!";
    } elseif ($password !== $konfirmasi_pass) {
        $error = "Password dan Konfirmasi Password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal terdiri dari 6 karakter!";
    } else {
        // Cek duplikasi NIK & Email
        $stmtCheck = $pdo->prepare("SELECT id FROM karyawan WHERE nik = ? OR email = ?");
        $stmtCheck->execute([$nik, $email]);
        if ($stmtCheck->rowCount() > 0) {
            $error = "NIK atau Email tersebut sudah terdaftar di sistem!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmtInsert = $pdo->prepare("INSERT INTO karyawan (nik, nama, jabatan, no_hp, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmtInsert->execute([$nik, $nama, $jabatan, $no_hp, $email, $hashedPassword, $role])) {
                // Notifikasi WA Karyawan (Sesuai format template presensi)
                $roleUpper = strtoupper($role);
                $pesanKaryawan = "REGISTRASI BERHASIL 🚀\n\n" .
                                 "Halo *{$nama}*,\n" .
                                 "Data Anda telah berhasil terdaftar di Sistem Presensi.\n\n" .
                                 "  • NIK: {$nik}\n" .
                                 "  • Jabatan: {$jabatan}\n" .
                                 "  • Role: {$roleUpper}\n\n" .
                                 "Silakan login menggunakan email dan password yang telah didaftarkan.\n" .
                                 "Terima kasih!";
                sendWhatsAppNotification($cleanHp, $pesanKaryawan);

                // Notifikasi WA Admin (hanya jika nomor admin berbeda)
                $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
                if (substr($cleanAdminHp, 0, 1) === '0') {
                    $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
                }
                if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
                    usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
                    $pesanAdmin = "NOTIFIKASI REGISTRASI KARYAWAN 👤\n\n" .
                                  "Halo Admin,\n" .
                                  "Karyawan baru telah berhasil terdaftar di Sistem Presensi.\n\n" .
                                  "  • NIK: {$nik}\n" .
                                  "  • Nama: {$nama}\n" .
                                  "  • Jabatan: {$jabatan}\n" .
                                  "  • No WA: {$no_hp}\n" .
                                  "  • Role: {$roleUpper}\n\n" .
                                  "Data telah berhasil disimpan ke database.\n" .
                                  "Terima kasih!";
                    sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
                }

                $success = "Pendaftaran berhasil! Akun Anda siap digunakan. Silakan login.";
            } else {
                $error = "Terjadi kesalahan saat memproses pendaftaran!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Karyawan - Sistem Presensi</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-mesh d-flex align-items-center justify-content-center min-vh-100 p-3 py-5">

<div class="container" style="max-width: 650px;">
    <div class="card border-0 shadow-xl modern-card overflow-hidden">
        
        <!-- Header -->
        <div class="p-4 p-md-5 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #0d9488 100%);">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="brand-icon">
                        <i class="bi bi-person-plus-fill fs-5"></i>
                    </div>
                    <span class="fw-bold fs-5 text-white">Presensi<span class="text-success">WA</span></span>
                </div>
                <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-2 small">
                    <i class="bi bi-shield-lock me-1"></i> Registrasi Akun
                </span>
            </div>
            <h3 class="fw-bold text-white mb-1">Daftar Akun Karyawan</h3>
            <p class="text-white-50 small mb-0">Lengkapi formulir di bawah ini untuk membuat akun presensi Anda.</p>

            <?php if ($countUsers === 0): ?>
                <div class="alert bg-white bg-opacity-15 border border-white border-opacity-25 text-white p-2 px-3 rounded-3 mt-3 mb-0 small d-flex align-items-center gap-2">
                    <i class="bi bi-star-fill text-warning"></i>
                    <span>Akun pertama yang didaftarkan akan otomatis menjadi <strong>Administrator Sistem</strong>.</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="card-body p-4 p-md-5 bg-white">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger-subtle text-danger-emphasis d-flex align-items-center gap-2 p-3 rounded-3 mb-4 small">
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success border-0 bg-success-subtle text-success-emphasis p-4 rounded-3 mb-4 text-center">
                    <div class="mb-2"><i class="bi bi-check-circle-fill text-success fs-1"></i></div>
                    <h5 class="fw-bold mb-1">Registrasi Berhasil!</h5>
                    <p class="small mb-3"><?= htmlspecialchars($success) ?></p>
                    <a href="login.php" class="btn btn-success px-4 py-2">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Menuju Halaman Login
                    </a>
                </div>
            <?php else: ?>

            <form method="POST" action="register.php">
                <div class="row g-3">
                    <!-- NIK -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nomor Induk Karyawan (NIK)</label>
                        <div class="input-icon-group">
                            <i class="bi bi-card-text"></i>
                            <input type="text" name="nik" class="form-control" placeholder="Contoh: KRY-001" required value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nama Lengkap</label>
                        <div class="input-icon-group">
                            <i class="bi bi-person"></i>
                            <input type="text" name="nama" class="form-control" placeholder="Nama Anda" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Jabatan -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Jabatan / Posisi</label>
                        <div class="input-icon-group">
                            <i class="bi bi-briefcase"></i>
                            <input type="text" name="jabatan" class="form-control" placeholder="Staff IT / Marketing" required value="<?= htmlspecialchars($_POST['jabatan'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- No WhatsApp -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nomor WhatsApp Aktif</label>
                        <div class="input-icon-group">
                            <i class="bi bi-whatsapp text-success"></i>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
                        </div>
                        <small class="text-muted" style="font-size: 0.72rem;">*Notifikasi presensi akan dikirimkan ke nomor ini.</small>
                    </div>

                    <!-- Email -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary">Alamat Email (Untuk Login)</label>
                        <div class="input-icon-group">
                            <i class="bi bi-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="nama@perusahaan.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Role Option if users > 0 -->
                    <?php if ($countUsers > 0): ?>
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary">Tipe Akun (Role)</label>
                        <select name="role" class="form-select">
                            <option value="karyawan" selected>Karyawan (Akses Portal Presensi)</option>
                            <option value="admin">Administrator (Akses Penuh & Kelola Data)</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Password -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Password</label>
                        <div class="input-icon-group">
                            <i class="bi bi-lock"></i>
                            <input type="password" name="password" id="regPassword" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>

                    <!-- Konfirmasi Password -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Ulangi Password</label>
                        <div class="input-icon-group">
                            <i class="bi bi-shield-check"></i>
                            <input type="password" name="konfirmasi_password" id="regConfirmPassword" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary w-100 py-2 fs-6">
                        <i class="bi bi-check2-circle me-1"></i> Selesaikan Registrasi
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <div class="text-center pt-4 border-top mt-4">
                <span class="text-muted small">Sudah memiliki akun?</span>
                <a href="login.php" class="text-success fw-bold text-decoration-none small ms-1">
                    Masuk di sini <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

</html>