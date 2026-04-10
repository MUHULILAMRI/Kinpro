<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kemenpu2');

// Base path untuk upload dan asset
if (!defined('BASE_PATH')) {
    define('BASE_PATH', rtrim(dirname(__DIR__), '/\\') . '/');
}
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', BASE_PATH . 'uploads/');
}

$db = null;

function getDB() {
    global $db;
    if ($db === null) {
        $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            die("Koneksi database gagal: " . $db->connect_error . "<br>Pastikan database 'kemenpu2' sudah ada.");
        }
        $db->set_charset("utf8mb4");
    }
    return $db;
}

// Helper function untuk mendapatkan predikat penilaian
function getPredikatFromNilai($nilai) {
    if ($nilai >= 90) return ['label' => 'Sangat Baik', 'class' => 'badge-excellent'];
    if ($nilai >= 80) return ['label' => 'Baik', 'class' => 'badge-good'];
    if ($nilai >= 70) return ['label' => 'Cukup', 'class' => 'badge-average'];
    return ['label' => 'Kurang', 'class' => 'badge-poor'];
}
?>
