<?php
/**
 * CSRF Token Helper
 * Generate & verify CSRF token untuk semua form POST
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token (sekali per session)
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token (untuk dipakai di form)
 */
function getCsrfToken() {
    return generateCsrfToken();
}

/**
 * Verify CSRF token saat POST
 * Return true jika valid, false jika tidak
 */
function verifyCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // bukan POST, skip
    }
    
    $token = $_POST['csrf_token'] ?? '';
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF atau redirect dengan error
 */
function requireCsrfToken($redirect_url = null) {
    if (!verifyCsrfToken()) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'title' => 'Token Invalid',
            'message' => 'Sesi tidak valid. Silakan coba lagi.'
        ];
        header('Location: ' . ($redirect_url ?? $_SERVER['PHP_SELF']));
        exit();
    }
}