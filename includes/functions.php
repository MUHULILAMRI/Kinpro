<?php
/**
 * Get base URL dynamically (works on localhost and production)
 */
function getBaseUrl() {
    static $baseUrl = null;
    if ($baseUrl === null) {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $baseUrl = rtrim($scriptDir, '/') . '/';
    }
    return $baseUrl;
}

/**
 * Get URL for uploaded files
 */
function uploadUrl($filename) {
    if (empty($filename)) return '';
    return getBaseUrl() . 'uploads/' . $filename;
}

function getPredikat($nilai) {
    if ($nilai >= 90) return ['label' => 'Sangat Baik', 'class' => 'badge-excellent'];
    if ($nilai >= 80) return ['label' => 'Baik', 'class' => 'badge-good'];
    if ($nilai >= 70) return ['label' => 'Cukup', 'class' => 'badge-average'];
    return ['label' => 'Kurang', 'class' => 'badge-poor'];
}

function getStatusClass($status) {
    switch($status) {
        case 'Aktif': return 'status-active';
        case 'Tidak Aktif': return 'status-inactive';
        case 'Baru': return 'status-new';
        case 'Diproses': return 'status-process';
        case 'Selesai': return 'status-done';
        case 'Ditolak': return 'status-rejected';
        default: return '';
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function formatTanggal($date) {
    if (!$date) return '-';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $d = explode('-', $date);
    return $d[2] . ' ' . $bulan[(int)$d[1]] . ' ' . $d[0];
}

function getInitials($nama) {
    $words = explode(' ', $nama);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper($word[0]);
    }
    return $initials;
}

function getAvatarColor($id) {
    $colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6'];
    return $colors[$id % count($colors)];
}

// Flash message functions
function setFlashMessage($type, $title, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'title' => $title,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function flashSuccess($message, $title = 'Berhasil') {
    setFlashMessage('success', $title, $message);
}

function flashError($message, $title = 'Error') {
    setFlashMessage('error', $title, $message);
}

function flashWarning($message, $title = 'Perhatian') {
    setFlashMessage('warning', $title, $message);
}

function flashInfo($message, $title = 'Info') {
    setFlashMessage('info', $title, $message);
}
?>
