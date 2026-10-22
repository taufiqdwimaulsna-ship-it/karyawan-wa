<?php
date_default_timezone_set('Asia/Jakarta');

$host     = "localhost";
$db_name  = "db_karyawan";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Helper untuk mengambil pengaturan dari tabel pengaturan
function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_pengaturan = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return ($row && !empty($row['nilai'])) ? $row['nilai'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Konfigurasi Admin, Batas Jam Masuk & Token Fonnte
define('FONNTE_TOKEN', '1ZTkt5LtsrjbJbG8NbkR');
define('ADMIN_PHONE', getSetting($pdo, 'admin_phone', '081234567890'));
define('JAM_MASUK_MAKSIMAL', getSetting($pdo, 'jam_masuk_maksimal', '08:00:00'));
define('JAM_PULANG_MULAI', getSetting($pdo, 'jam_pulang_mulai', '17:00:00'));

if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname(__DIR__));
    $docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs');
    $relative  = str_replace($docRoot, '', $scriptDir);
    define('BASE_URL', rtrim($relative, '/') ?: '/karyawan_wa');
}
