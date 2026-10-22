<?php
require_once __DIR__ . '/../config/database.php';

// Jika sudah login, langsung arahkan ke halaman sesuai role
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../absensi/index.php");
    }
    exit;
}

$error = '';

// Hitung total user untuk info panduan awal
$countUsers = 0;
try {
    $countUsers = (int)$pdo->query("SELECT COUNT(*) FROM karyawan")->fetchColumn();
} catch (Exception $e) {
    $countUsers = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email dan password wajib diisi!";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM karyawan WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['nik']     = $user['nik'];
            $_SESSION['role']    = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../absensi/index.php");
            }
            exit;
        } else {
            $error = "Email atau Password tidak cocok!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Presensi & WA Gateway</title>
    
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
<body class="bg-mesh d-flex align-items-center justify-content-center min-vh-100 p-3">

<div class="container" style="max-width: 960px;">
    <div class="row g-0 rounded-4 overflow-hidden shadow-xl modern-card border-0">
        <!-- Left Side: Hero Banner -->
        <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-between p-5 text-white" 
             style="background: linear-gradient(135deg, #0f172a 0%, #134e4a 50%, #064e3b 100%); position: relative;">
            
            <div class="position-relative z-1">
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-1 rounded-pill mb-4 border border-white border-opacity-10">
                    <span class="status-dot pulse-success"></span>
                    <span class="small fw-semibold text-light">WhatsApp Gateway Active</span>
                </div>
                
                <h2 class="display-6 fw-extrabold mb-3 text-white">Presensi Modern,<br><span class="text-success">Cepat & Otomatis.</span></h2>
                <p class="text-white-50 leading-relaxed">
                    Sistem presensi kehadiran karyawan terintegrasi API WhatsApp Gateway Fonnte. Catat jam masuk & pulang dengan notifikasi instan langsung ke HP Anda.
                </p>
            </div>

            <div class="position-relative z-1 pt-4 border-top border-white border-opacity-10">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-icon">
                        <i class="bi bi-chat-dots-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-white small">Notifikasi Real-time</div>
                        <div class="text-white-50" style="font-size: 0.75rem;">Setiap absen tercatat & terkirim ke WhatsApp</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="col-lg-6 bg-white p-4 p-md-5 d-flex flex-column justify-content-center">
            <div class="mb-4 text-center text-lg-start">
                <div class="d-inline-flex d-lg-none align-items-center gap-2 mb-3">
                    <div class="brand-icon">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <span class="fw-bold fs-4 text-dark">Presensi<span class="text-success">WA</span></span>
                </div>
                <h3 class="fw-bold text-dark mb-1">Selamat Datang 👋</h3>
                <p class="text-muted small">Silakan masukkan email dan password untuk masuk ke akun Anda.</p>
            </div>

            <?php if ($countUsers === 0): ?>
                <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis d-flex align-items-center gap-2 p-3 rounded-3 mb-3 small">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div>
                        <strong>Database masih baru!</strong> Silakan klik 
                        <a href="register.php" class="alert-link text-decoration-underline fw-bold">Daftar Akun Baru</a> untuk mendaftar sebagai karyawan/admin pertama.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger border-0 bg-danger-subtle text-danger-emphasis d-flex align-items-center gap-2 p-3 rounded-3 mb-3 small">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <!-- Email -->
                <div class="mb-3">
                    <label class="form-label fw-semibold small text-secondary">Alamat Email</label>
                    <div class="input-icon-group">
                        <i class="bi bi-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="nama@perusahaan.com" required autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label fw-semibold small text-secondary">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" id="loginPassword" name="password" class="form-control border-start-0 border-end-0" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                </button>
            </form>

            <div class="text-center pt-3 border-top mt-2">
                <span class="text-muted small">Belum memiliki akun?</span>
                <a href="register.php" class="text-success fw-bold text-decoration-none small ms-1">
                    Daftar di sini <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
    setupPasswordToggle('togglePassword', 'loginPassword');
</script>
</body>
</html>

</html>