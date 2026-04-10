<?php
// Auth Helper - Session Management

if (session_status() === PHP_SESSION_NONE) {
    // Pastikan session bisa persist di server
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current logged in user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $id = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM pegawai WHERE id_pegawai = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Attempt login with username/email/NIP and password
 */
function attemptLogin($username, $password) {
    $db = getDB();
    
    // Use prepared statement to prevent SQL injection
    // Check username, email, or NIP
    $stmt = $db->prepare("SELECT * FROM pegawai WHERE username = ? OR nip = ? OR email = ?");
    $stmt->bind_param('sss', $username, $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session
            $_SESSION['user_id'] = $user['id_pegawai'];
            $_SESSION['user_name'] = $user['nama_lengkap'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            $_SESSION['user_nip'] = $user['nip'];
            $_SESSION['user_jabatan'] = $user['jabatan'];
            $_SESSION['user_foto'] = $user['foto_profil'];
            $_SESSION['login_time'] = time();
            $_SESSION['user_ip'] = getClientIP();
            
            return ['success' => true, 'user' => $user];
        }
    }
    $stmt->close();
    
    return ['success' => false, 'message' => 'Username/Email atau password salah!'];
}

/**
 * Logout user
 */
function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

/**
 * Require login - redirect if not logged in
 */
/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
    // Session timeout: 2 hours
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) {
        session_unset();
        session_destroy();
        session_start();
        header("Location: login.php?expired=1");
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: index.php?page=dashboard&error=unauthorized");
        exit;
    }
}

/**
 * Get user initials for avatar
 */
function getUserInitials($nama = null) {
    if ($nama === null) {
        $nama = $_SESSION['user_name'] ?? 'U';
    }
    $words = explode(' ', $nama);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    return $initials ?: 'U';
}

/**
 * Get login duration
 */
function getLoginDuration() {
    if (!isset($_SESSION['login_time'])) return 'Baru saja';
    
    $duration = time() - $_SESSION['login_time'];
    
    if ($duration < 60) return 'Baru saja';
    if ($duration < 3600) return floor($duration / 60) . ' menit';
    if ($duration < 86400) return floor($duration / 3600) . ' jam';
    return floor($duration / 86400) . ' hari';
}
?>
