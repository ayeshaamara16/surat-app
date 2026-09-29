<?php
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    header('Location: auth/login.php');
    exit();
}

$timeout_duration = 1800;
if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    if ($elapsed_time > $timeout_duration) {
        session_unset();
        session_destroy();
        header('Location: auth/login.php?timeout=1');
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Role Check Function
if (!function_exists('checkRole')) {
    function checkRole($allowedRoles) {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        $userRole = strtolower($_SESSION['role']);
        
        // Superadmin always has access
        if ($userRole === 'superadmin') {
            return true;
        }
        
        if (is_array($allowedRoles)) {
            return in_array($userRole, array_map('strtolower', $allowedRoles));
        }
        
        return $userRole === strtolower($allowedRoles);
    }
}
?>