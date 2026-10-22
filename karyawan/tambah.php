<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/fonnte.php';

// Cek hak akses admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../absensi/index.php");
    exit;
}

$pageTitle = 'Tambah Karyawan Baru';
$activeMenu = 'karyawan';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik      = trim($_POST['nik'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $jabatan  = trim($_POST['jabatan'] ?? '');
    $no_hp    = trim($_POST['no_hp'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $rawPass  = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'karyawan';

    // Normalisasi nomor HP
    $cleanHp = preg_replace('/[^0-9]/', '', $no_hp);
    if (substr($cleanHp, 0, 1) === '0') {
        $cleanHp = '62' . substr($cleanHp, 1);
    }

    if (empty($nik) || empty($nama) || empty($jabatan) || empty($no_hp) || empty($email) || empty($rawPass)) {
        $error = "Semua kolom formulir wajib diisi!";
    } elseif (strlen($rawPass) < 6) {
        $error = "Password minimal terdiri dari 6 karakter!";
    } else {
        // Cek duplikasi NIK atau Email
        $stmtCheck = $pdo->prepare("SELECT id FROM karyawan WHERE nik = ? OR email = ?");
        $stmtCheck->execute([$nik, $email]);
        if ($stmtCheck->rowCount() > 0) {
            $error = "NIK atau Email tersebut sudah digunakan oleh akun lain!";
        } else {
            $passwordHashed = password_hash($rawPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO karyawan (nik, nama, jabatan, no_hp, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$nik, $nama, $jabatan, $no_hp, $email, $passwordHashed, $role])) {
                // Notifikasi WhatsApp ke Karyawan (Sesuai format template presensi)
                $roleUpper = strtoupper($role);
                $pesan = "REGISTRASI BERHASIL 🚀\n\n" .
                         "Halo *{$nama}*,\n" .
                         "Data Anda telah berhasil terdaftar di Sistem Presensi.\n\n" .
                         "  • NIK: {$nik}\n" .
                         "  • Jabatan: {$jabatan}\n" .
                         "  • Email: {$email}\n" .
                         "  • Password: {$rawPass}\n" .
                         "  • Role: {$roleUpper}\n\n" .
                         "Silakan login menggunakan email dan password yang telah didaftarkan.\n" .
                         "Terima kasih!";

                sendWhatsAppNotification($cleanHp, $pesan);

                // Notifikasi WhatsApp ke Admin (hanya jika nomor admin berbeda)
                $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
                if (substr($cleanAdminHp, 0, 1) === '0') {
                    $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
                }
                if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
                    usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
                    $pesanAdmin = "NOTIFIKASI REGISTRASI KARYAWAN 👤\n\n" .
                                  "Halo Admin,\n" .
                                  "Karyawan baru telah berhasil ditambahkan ke Sistem Presensi.\n\n" .
                                  "  • NIK: {$nik}\n" .
                                  "  • Nama: {$nama}\n" .
                                  "  • Jabatan: {$jabatan}\n" .
                                  "  • No WA: {$no_hp}\n" .
                                  "  • Role: {$roleUpper}\n\n" .
                                  "Data telah berhasil disimpan ke database.\n" .
                                  "Terima kasih!";
                    sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
                }

                $_SESSION['flash'] = [
                    'type'    => 'success',
                    'title'   => 'Berhasil!',
                    'message' => "Karyawan {$nama} berhasil ditambahkan & notifikasi WA telah terkirim."
                ];

                header("Location: index.php");
                exit;
            } else {
                $error = "Gagal menyimpan data karyawan!";
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4" style="max-width: 700px;">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2 small">
            <li class="breadcrumb-item"><a href="../admin/index.php" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Data Karyawan</a></li>
            <li class="breadcrumb-item active" aria-current="page">Tambah Karyawan</li>
        </ol>
    </nav>

    <!-- Card Form -->
    <div class="modern-card overflow-hidden shadow-sm">
        <div class="p-4 border-bottom bg-white d-flex align-items-center gap-3">
            <div class="brand-icon">
                <i class="bi bi-person-plus fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold text-dark mb-0">Tambah Karyawan Baru</h5>
                <span class="text-muted small">Lengkapi data profil dan akun login karyawan</span>
            </div>
        </div>

        <div class="p-4 p-md-5 bg-white">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger-subtle text-danger-emphasis d-flex align-items-center gap-2 p-3 rounded-3 mb-4 small">
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-3">
                    <!-- NIK -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nomor Induk Karyawan (NIK)</label>
                        <div class="input-icon-group">
                            <i class="bi bi-card-text"></i>
                            <input type="text" name="nik" class="form-control" placeholder="Contoh: KRY-002" required value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nama Lengkap</label>
                        <div class="input-icon-group">
                            <i class="bi bi-person"></i>
                            <input type="text" name="nama" class="form-control" placeholder="Nama lengkap karyawan" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Jabatan -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Jabatan / Posisi</label>
                        <div class="input-icon-group">
                            <i class="bi bi-briefcase"></i>
                            <input type="text" name="jabatan" class="form-control" placeholder="Staff Operasional" required value="<?= htmlspecialchars($_POST['jabatan'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- WhatsApp -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nomor WhatsApp</label>
                        <div class="input-icon-group">
                            <i class="bi bi-whatsapp text-success"></i>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Alamat Email</label>
                        <div class="input-icon-group">
                            <i class="bi bi-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="karyawan@perusahaan.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Hak Akses (Role)</label>
                        <select name="role" class="form-select">
                            <option value="karyawan" selected>Karyawan (Portal Presensi)</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                    </div>

                    <!-- Password -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary">Password Sementara</label>
                        <div class="input-icon-group">
                            <i class="bi bi-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">*Password ini akan dikirimkan otomatis ke WhatsApp karyawan.</small>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="index.php" class="btn btn-light border px-4">Batal</a>
                    <button type="submit" class="btn btn-wa text-white px-4">
                        <i class="bi bi-check2-circle"></i> Simpan Data Karyawan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

