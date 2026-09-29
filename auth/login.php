<?php
date_default_timezone_set('Asia/Jakarta');
session_start();

// Cek apakah sudah login
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    header('Location: ../index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        require_once __DIR__ . '/../config/database.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['login_success'] = true;
                
                $update = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([$user['id']]);
                
                header('Location: ../index.php');
                exit();
            } else {
                $error = 'Username atau password salah!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Manajemen Surat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
            background: #F1F5F9;
        }
        
        /* ========================================
           BACKGROUND - NETRAL SLATE
           ======================================== */
        .bg-gradient {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg,
                #F8FAFC 0%,
                #F1F5F9 50%,
                #E2E8F0 100%);
            z-index: -4;
        }
        
        /* Grid pattern halus */
        .bg-grid {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(15, 23, 42, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: -3;
            pointer-events: none;
        }
        
        /* Blob abu netral - kanan atas */
        .bg-blob-1 {
            position: fixed;
            top: -180px;
            right: -120px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle,
                rgba(71, 85, 105, 0.12) 0%,
                rgba(71, 85, 105, 0.04) 40%,
                transparent 70%);
            border-radius: 50%;
            filter: blur(50px);
            z-index: -2;
            pointer-events: none;
        }
        
        /* Blob abu netral - kiri bawah */
        .bg-blob-2 {
            position: fixed;
            bottom: -180px;
            left: -120px;
            width: 550px;
            height: 550px;
            background: radial-gradient(circle,
                rgba(30, 41, 59, 0.15) 0%,
                rgba(30, 41, 59, 0.05) 40%,
                transparent 70%);
            border-radius: 50%;
            filter: blur(50px);
            z-index: -2;
            pointer-events: none;
        }
        
        /* ==============================
           LOGIN CARD
           ============================== */
        .login-card {
            background: #FFFFFF;
            border-radius: 18px;
            padding: 38px 36px 32px;
            width: 100%;
            max-width: 380px;
            box-shadow: 
                0 30px 90px rgba(15, 23, 42, 0.12),
                0 15px 50px rgba(15, 23, 42, 0.08),
                0 0 0 1px rgba(255, 255, 255, 0.6);
            position: relative;
            z-index: 10;
            animation: cardIn 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes cardIn {
            from { 
                opacity: 0; 
                transform: scale(0.95) translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            }
        }
        
        /* ==============================
           LOGO - Hitam/Slate
           ============================== */
        .logo-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #334155 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .logo-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                transparent 40%, 
                rgba(255, 255, 255, 0.15) 50%, 
                transparent 60%);
            animation: shine 3s ease-in-out infinite;
        }
        
        @keyframes shine {
            0%, 100% { transform: translateX(-100%) translateY(-100%); }
            50% { transform: translateX(100%) translateY(100%); }
        }
        
        .logo-icon svg {
            width: 22px;
            height: 22px;
            color: #FFFFFF;
            position: relative;
            z-index: 1;
        }
        
        .logo-text {
            font-size: 26px;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: -0.5px;
        }
        
        .logo-text span {
            color: #94A3B8;
            font-weight: 800;
        }
        
        /* ==============================
           SUBTITLE
           ============================== */
        .subtitle {
            text-align: center;
            font-size: 12px;
            color: #1E293B;
            line-height: 1.6;
            margin-bottom: 28px;
            font-weight: 600;
            letter-spacing: 0.1px;
        }
        
        .subtitle span {
            display: block;
            font-size: 11px;
            color: #94A3B8;
            margin-top: 3px;
            font-weight: 400;
        }
        
        /* ==============================
           FORM
           ============================== */
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #0F172A;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 16px;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.25s ease;
        }
        
        .input-field {
            width: 100%;
            padding: 11px 14px 11px 42px;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: #0F172A;
            background: #F8FAFC;
            transition: all 0.25s ease;
            outline: none;
            font-weight: 500;
        }
        
        .input-field::placeholder {
            color: #94A3B8;
            font-size: 12px;
            font-weight: 400;
        }
        
        .input-field:focus {
            border-color: #0F172A;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.08);
        }
        
        .input-field:focus + .input-icon,
        .input-field:focus ~ .input-icon {
            color: #0F172A;
        }
        
        .input-field:hover {
            background: #FFFFFF;
            border-color: #CBD5E1;
        }
        
        /* ==============================
           BUTTON - Hitam/Slate
           ============================== */
        .btn-signin {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #334155 100%);
            background-size: 200% 200%;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.3);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .btn-signin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.2), 
                transparent);
            transition: left 0.5s ease;
        }
        
        .btn-signin:hover::before {
            left: 100%;
        }
        
        .btn-signin:hover {
            transform: translateY(-2px);
            background-position: 100% 100%;
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.4);
        }
        
        .btn-signin:active {
            transform: translateY(0);
        }
        
        .btn-signin svg {
            width: 16px;
            height: 16px;
            position: relative;
            z-index: 1;
        }
        
        .btn-signin span {
            position: relative;
            z-index: 1;
            font-size: 12px;
        }
        
        /* ==============================
           FOOTER
           ============================== */
        .footer-divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        
        .footer-divider::before,
        .footer-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #E2E8F0, transparent);
        }
        
        .footer-divider span {
            font-size: 10px;
            color: #94A3B8;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .footer-text {
            text-align: center;
            font-size: 10px;
            color: #94A3B8;
            line-height: 1.6;
        }
        
        .footer-text a {
            color: #0F172A;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        /* ==============================
           ERROR ALERT
           ============================== */
        .error-alert {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-left: 4px solid #EF4444;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.3s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .error-alert svg {
            width: 16px;
            height: 16px;
            color: #EF4444;
            flex-shrink: 0;
        }
        
        .error-alert span {
            font-size: 12px;
            color: #991B1B;
            font-weight: 500;
        }
        
        /* ==============================
           RESPONSIVE
           ============================== */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 24px 26px;
                border-radius: 16px;
            }
            
            .logo-text {
                font-size: 22px;
            }
            
            .logo-icon {
                width: 38px;
                height: 38px;
            }
            
            .logo-icon svg {
                width: 20px;
                height: 20px;
            }
        }
        
        @media (max-width: 380px) {
            .login-card {
                padding: 24px 18px 20px;
            }
            
            .logo-text {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Background Netral Slate -->
    <div class="bg-gradient"></div>
    <div class="bg-grid"></div>
    <div class="bg-blob-1"></div>
    <div class="bg-blob-2"></div>

    <!-- Login Card -->
    <div class="login-card">
        <!-- Logo -->
        <div class="logo-wrapper">
            <div class="logo-icon">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M50 10L15 30V70L50 90L85 70V30L50 10Z" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M35 45L50 55L65 45" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M50 55V75" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <span class="logo-text">Surat<span>App</span></span>
        </div>

        <!-- Subtitle -->
        <div class="subtitle">
            Sistem Manajemen Surat
            <span>Bagian Hukum Setda Kabupaten Banyumas</span>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="error-alert">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="">
            <!-- Username -->
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-wrapper">
                    <input type="text" name="username" class="input-field" placeholder="Masukkan username" required autocomplete="username">
                    <span class="input-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" class="input-field" placeholder="Masukkan password" required autocomplete="current-password">
                    <span class="input-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Sign In Button -->
            <button type="submit" class="btn-signin">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                </svg>
                <span>Masuk ke Sistem</span>
            </button>
        </form>

        <!-- Footer -->
        <div class="footer-divider">
            <span>Manajemen Surat Digital</span>
        </div>
        <div class="footer-text">
            &copy; 2026 Pemerintah Kabupaten Banyumas. <a href="#">Kebijakan &amp; Privasi</a>
        </div>
    </div>
</body>
</html>