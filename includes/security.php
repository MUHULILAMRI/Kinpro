<?php
/**
 * Security Helper Functions
 * - CSRF Protection
 * - Rate Limiting
 * - File Upload Validation
 * - Input Sanitization
 */

// =====================================================
// CSRF PROTECTION
// =====================================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token input field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    
    // Check if token exists and matches
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Check token expiry (4 hours)
    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 14400) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or die
 */
function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCSRFToken()) {
            http_response_code(403);
            die('Sesi tidak valid. Silakan refresh halaman dan coba lagi.');
        }
    }
}

// =====================================================
// RATE LIMITING
// =====================================================

/**
 * Check rate limit for login attempts
 * @param string $identifier - IP or username
 * @param int $maxAttempts - Max attempts allowed
 * @param int $windowSeconds - Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function checkRateLimit($identifier, $maxAttempts = 5, $windowSeconds = 900) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    $data = $_SESSION[$key];
    $now = time();
    
    // Reset if window has passed
    if ($now - $data['first_attempt'] > $windowSeconds) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => $now
        ];
        $data = $_SESSION[$key];
    }
    
    $remaining = $maxAttempts - $data['attempts'];
    $resetTime = $data['first_attempt'] + $windowSeconds;
    
    return [
        'allowed' => $data['attempts'] < $maxAttempts,
        'remaining' => max(0, $remaining),
        'reset_time' => $resetTime,
        'wait_seconds' => max(0, $resetTime - $now)
    ];
}

/**
 * Increment rate limit counter
 */
function incrementRateLimit($identifier) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    $_SESSION[$key]['attempts']++;
}

/**
 * Reset rate limit after successful login
 */
function resetRateLimit($identifier) {
    $key = 'rate_limit_' . md5($identifier);
    unset($_SESSION[$key]);
}

// =====================================================
// FILE UPLOAD VALIDATION
// =====================================================

/**
 * Allowed file types configuration
 */
function getAllowedFileTypes() {
    return [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max_size' => 5 * 1024 * 1024 // 5MB
        ],
        'document' => [
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ],
            'max_size' => 10 * 1024 * 1024 // 10MB
        ],
        'all' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'],
            'mimes' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'max_size' => 10 * 1024 * 1024 // 10MB
        ]
    ];
}

/**
 * Validate uploaded file
 * @param array $file - $_FILES['field']
 * @param string $type - 'image', 'document', or 'all'
 * @return array ['valid' => bool, 'error' => string|null, 'extension' => string|null]
 */
function validateUploadedFile($file, $type = 'all') {
    // Check if file exists
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => true, 'error' => null, 'extension' => null]; // No file uploaded is OK
    }
    
    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi batas server)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi'
        ];
        return [
            'valid' => false,
            'error' => $errors[$file['error']] ?? 'Error upload tidak diketahui',
            'extension' => null
        ];
    }
    
    $config = getAllowedFileTypes()[$type] ?? getAllowedFileTypes()['all'];
    
    // Check file size
    if ($file['size'] > $config['max_size']) {
        $maxMB = $config['max_size'] / 1024 / 1024;
        return [
            'valid' => false,
            'error' => "Ukuran file maksimal {$maxMB}MB",
            'extension' => null
        ];
    }
    
    // Check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $config['extensions'])) {
        return [
            'valid' => false,
            'error' => 'Format file tidak didukung. Gunakan: ' . implode(', ', $config['extensions']),
            'extension' => null
        ];
    }
    
    // Check MIME type
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    } else {
        $mimeType = $file['type']; // fallback ke MIME dari browser
    }
    
    if (!in_array($mimeType, $config['mimes'])) {
        return [
            'valid' => false,
            'error' => 'Tipe file tidak valid atau file rusak',
            'extension' => null
        ];
    }
    
    // Additional check for images - verify it's a real image
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'error' => 'File bukan gambar yang valid',
                'extension' => null
            ];
        }
    }
    
    return [
        'valid' => true,
        'error' => null,
        'extension' => $extension
    ];
}

/**
 * Safely move uploaded file with secure naming
 * @param array $file - $_FILES['field']
 * @param string $uploadDir - Directory to save (relative to root)
 * @param string $prefix - Filename prefix
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function safeUploadFile($file, $uploadDir, $prefix = 'file') {
    // Validate file first
    $validation = validateUploadedFile($file);
    if (!$validation['valid']) {
        return [
            'success' => false,
            'filename' => null,
            'error' => $validation['error']
        ];
    }
    
    if ($validation['extension'] === null) {
        return ['success' => true, 'filename' => null, 'error' => null]; // No file
    }
    
    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return [
                'success' => false,
                'filename' => null,
                'error' => 'Gagal membuat direktori upload'
            ];
        }
    }
    
    // Add .htaccess to prevent script execution
    $htaccess = $uploadDir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -ExecCGI\nAddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .aspx .htm .html .shtml\nRemoveHandler .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .aspx .htm .html .shtml\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>");
    }
    
    // Generate secure filename
    $extension = $validation['extension'];
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $fullPath = rtrim($uploadDir, '/') . '/' . $filename;
    
    // Move file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [
            'success' => false,
            'filename' => null,
            'error' => 'Gagal menyimpan file'
        ];
    }
    
    return [
        'success' => true,
        'filename' => $filename,
        'error' => null
    ];
}

// =====================================================
// SECURE INPUT HELPERS
// =====================================================

/**
 * Get client IP address
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Check for proxy headers (use with caution)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Sanitize string input
 */
function sanitizeInput($str) {
    if (is_array($str)) {
        return array_map('sanitizeInput', $str);
    }
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indonesian format)
 */
function validatePhone($phone) {
    // Remove spaces and dashes
    $phone = preg_replace('/[\s\-]/', '', $phone);
    // Check Indonesian phone format
    return preg_match('/^(\+62|62|0)[0-9]{9,12}$/', $phone);
}

/**
 * Validate NIP format (18 digits)
 */
function validateNIP($nip) {
    $nip = preg_replace('/[\s\-]/', '', $nip);
    return preg_match('/^[0-9]{18}$/', $nip);
}
?>
