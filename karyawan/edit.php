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

$pageTitle = 'Edit Data Karyawan';
$activeMenu = 'karyawan';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM karyawan WHERE id = ?");
$stmt->execute([$id]);
$karyawan = $stmt->fetch();

if (!$karyawan) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'title'   => 'Tidak Ditemukan',
        'message' => 'Data karyawan tidak ditemukan dalam database!'
    ];
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $no_hp   = trim($_POST['no_hp'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $role    = $_POST['role'] ?? $karyawan['role'];
    $newPass = $_POST['new_password'] ?? '';

    // Normalisasi no hp
    $cleanHp = preg_replace('/[^0-9]/', '', $no_hp);
    if (substr($cleanHp, 0, 1) === '0') {
        $cleanHp = '62' . substr($cleanHp, 1);
    }

    if (empty($nama) || empty($jabatan) || empty($no_hp) || empty($email)) {
        $error = "Nama, Jabatan, No WhatsApp, dan Email wajib diisi!";
    } else {
        // Cek duplikasi email ke user lain
        $stmtCheck = $pdo->prepare("SELECT id FROM karyawan WHERE email = ? AND id != ?");
        $stmtCheck->execute([$email, $id]);
        if ($stmtCheck->rowCount() > 0) {
            $error = "Email tersebut sudah digunakan oleh karyawan lain!";
        } else {
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) {
                    $error = "Password baru minimal 6 karakter!";
                } else {
                    $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmtUpdate = $pdo->prepare("UPDATE karyawan SET nama = ?, jabatan = ?, no_hp = ?, email = ?, role = ?, password = ? WHERE id = ?");
                    $stmtUpdate->execute([$nama, $jabatan, $no_hp, $email, $role, $hashed, $id]);
                }
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE karyawan SET nama = ?, jabatan = ?, no_hp = ?, email = ?, role = ? WHERE id = ?");
                $stmtUpdate->execute([$nama, $jabatan, $no_hp, $email, $role, $id]);
            }

            if (empty($error)) {
                // Notifikasi ke karyawan (Sesuai format template presensi)
                $roleUpper = strtoupper($role);
                $pesan = "PEMBARUAN DATA BERHASIL ✏️\n\n" .
                         "Halo *{$nama}*,\n" .
                         "Data profil Anda telah berhasil diperbarui di Sistem Presensi.\n\n" .
                         "  • NIK: {$karyawan['nik']}\n" .
                         "  • Jabatan: {$jabatan}\n" .
                         "  • No WA: {$no_hp}\n" .
                         "  • Role: {$roleUpper}\n\n" .
                         "Silakan login untuk memeriksa pembaruan data akun Anda.\n" .
                         "Terima kasih!";

                sendWhatsAppNotification($cleanHp, $pesan);

                // Notifikasi ke Admin (hanya jika nomor admin berbeda dengan nomor karyawan agar tidak bentrok rate-limit)
                $cleanAdminHp = preg_replace('/[^0-9]/', '', ADMIN_PHONE);
                if (substr($cleanAdminHp, 0, 1) === '0') {
                    $cleanAdminHp = '62' . substr($cleanAdminHp, 1);
                }
                if (!empty($cleanAdminHp) && $cleanAdminHp !== '6281234567890' && $cleanAdminHp !== $cleanHp) {
                    usleep(300000); // jeda 300ms untuk mencegah antrean bentrok Fonnte
                    $pesanAdmin = "NOTIFIKASI PERUBAHAN DATA ✏️\n\n" .
                                  "Halo Admin,\n" .
                                  "Data profil karyawan telah berhasil diperbarui di Sistem Presensi.\n\n" .
                                  "  • NIK: {$karyawan['nik']}\n" .
                                  "  • Nama: {$nama}\n" .
                                  "  • Jabatan: {$jabatan}\n" .
                                  "  • No WA: {$no_hp}\n" .
                                  "  • Role: {$roleUpper}\n\n" .
                                  "Pembaruan data telah berhasil disimpan ke database.\n" .
                                  "Terima kasih!";
                    sendWhatsAppNotification($cleanAdminHp, $pesanAdmin);
                }

                $_SESSION['flash'] = [
                    'type'    => 'success',
                    'title'   => 'Perubahan Disimpan',
                    'message' => "Data karyawan {$nama} berhasil diperbarui."
                ];

                header("Location: index.php");
                exit;
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
            <li class="breadcrumb-item active" aria-current="page">Edit Karyawan</li>
        </ol>
    </nav>

    <!-- Card Form -->
    <div class="modern-card overflow-hidden shadow-sm">
        <div class="p-4 border-bottom bg-white d-flex align-items-center gap-3">
            <div class="brand-icon">
                <i class="bi bi-pencil-square fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold text-dark mb-0">Edit Data Karyawan</h5>
                <span class="text-muted small">Perbarui data profil & hak akses karyawan</span>
            </div>
        </div>

        <div class="p-4 p-md-5 bg-white">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger-subtle text-danger-emphasis d-flex align-items-center gap-2 p-3 rounded-3 mb-4 small">
                    <i class="bi bi-exclamation-octagon-fill fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit.php?id=<?= urlencode($id) ?>">
                <div class="row g-3">
                    <!-- NIK (Readonly) -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">NIK (Tidak dapat diubah)</label>
                        <div class="input-icon-group">
                            <i class="bi bi-lock-fill"></i>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($karyawan['nik']) ?>" readonly>
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nama Lengkap</label>
                        <div class="input-icon-group">
                            <i class="bi bi-person"></i>
                            <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($karyawan['nama']) ?>">
                        </div>
                    </div>

                    <!-- Jabatan -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Jabatan / Posisi</label>
                        <div class="input-icon-group">
                            <i class="bi bi-briefcase"></i>
                            <input type="text" name="jabatan" class="form-control" required value="<?= htmlspecialchars($karyawan['jabatan']) ?>">
                        </div>
                    </div>

                    <!-- WhatsApp -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Nomor WhatsApp</label>
                        <div class="input-icon-group">
                            <i class="bi bi-whatsapp text-success"></i>
                            <input type="text" name="no_hp" class="form-control" required value="<?= htmlspecialchars($karyawan['no_hp']) ?>">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Alamat Email</label>
                        <div class="input-icon-group">
                            <i class="bi bi-envelope"></i>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($karyawan['email']) ?>">
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary">Hak Akses (Role)</label>
                        <select name="role" class="form-select">
                            <option value="karyawan" <?= $karyawan['role'] === 'karyawan' ? 'selected' : '' ?>>Karyawan</option>
                            <option value="admin" <?= $karyawan['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>

                    <!-- Ubah Password (Optional) -->
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-secondary">Ganti Password (Kosongkan jika tidak ingin mengubah)</label>
                        <div class="input-icon-group">
                            <i class="bi bi-key"></i>
                            <input type="password" name="new_password" class="form-control" placeholder="Masukkan password baru...">
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="index.php" class="btn btn-light border px-4">Batal</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2-circle"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

