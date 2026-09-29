<?php
require_once __DIR__ . '/middleware/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';

$title = 'Profil - Sistem Manajemen Surat';
$page = 'profile';
include 'layout/header.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success'; // success | error

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: auth/logout.php');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken('profile.php');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $updates = [];
        $params = [];
        $has_error = false;
        
        // Validasi nama
        if (empty($name)) {
            $message = 'Nama lengkap wajib diisi!';
            $message_type = 'error';
            $has_error = true;
        } else {
            $updates[] = "name = ?";
            $params[] = $name;
        }
        
        // Validasi email (format)
        if (!empty($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Format email tidak valid!';
                $message_type = 'error';
                $has_error = true;
            } else {
                $updates[] = "email = ?";
                $params[] = $email;
            }
        }
        
        // Validasi password (kalau diisi)
        if (!$has_error && (!empty($current_password) || !empty($new_password) || !empty($confirm_password))) {
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = 'Semua field password harus diisi!';
                $message_type = 'error';
                $has_error = true;
            } elseif (!password_verify($current_password, $user['password'])) {
                $message = 'Password saat ini salah!';
                $message_type = 'error';
                $has_error = true;
            } elseif ($new_password !== $confirm_password) {
                $message = 'Password baru dan konfirmasi tidak sesuai!';
                $message_type = 'error';
                $has_error = true;
            } elseif (strlen($new_password) < 6) {
                $message = 'Password minimal 6 karakter!';
                $message_type = 'error';
                $has_error = true;
            } else {
                $updates[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
        }
        
        // Eksekusi update kalau tidak ada error
        if (!$has_error && !empty($updates)) {
            $params[] = $user_id;
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            if (!empty($name)) {
                $_SESSION['name'] = $name;
            }
            
            $message = 'Profil berhasil diperbarui!';
            $message_type = 'success';
            
            // LOG AKTIVITAS
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'update_profile', ?)");
            $log->execute([$user_id, "Update profil: " . $name]);
            
            // Refresh data user
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }
} catch (PDOException $e) {
    $message = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    $message_type = 'error';
}
?>

<?php include 'layout/navbar.php'; ?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
    
    <!-- Flash Message -->
    <?php if ($message): ?>
        <div class="flash-message <?php echo $message_type === 'success' ? 'bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700' : 'bg-rose-50 border-l-4 border-rose-500 text-rose-700'; ?> px-4 py-3 rounded-xl mb-4 flex items-center gap-3 text-xs shadow-sm max-w-4xl mx-auto">
            <svg class="w-4 h-4 <?php echo $message_type === 'success' ? 'text-emerald-500' : 'text-rose-500'; ?> shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $message_type === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; ?>"/>
            </svg>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="max-w-4xl mx-auto mb-6">
        <h1 class="text-lg sm:text-2xl font-bold text-gray-800 flex items-center gap-2">
            <span class="w-7 h-7 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shadow-lg shadow-slate-700/30 shrink-0">
                <svg class="w-3.5 h-3.5 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </span>
            <span class="bg-gradient-to-r from-slate-700 to-slate-500 bg-clip-text text-transparent">Profil Saya</span>
        </h1>
        <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-9 sm:ml-12">Kelola data dan password akun Anda</p>
    </div>
    
    <!-- Profile Card -->
    <div class="profile-card bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden max-w-4xl mx-auto">
        
        <div class="flex flex-col sm:flex-row">
            
            <!-- KOLOM KIRI -->
            <div class="sm:w-1/3 p-6 bg-gradient-to-br from-slate-700 to-slate-900 text-white flex flex-col items-center justify-center text-center">
                
                <div class="w-20 h-20 rounded-full bg-white/15 backdrop-blur-sm flex items-center justify-center text-white text-3xl font-bold shadow-lg border-2 border-white/20 mb-4">
                    <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                </div>
                
                <h2 class="text-base font-bold leading-tight">
                    <?php echo htmlspecialchars($user['name'] ?? ''); ?>
                </h2>
                <p class="text-xs text-white/60 mt-1">@<?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
                
                <span class="mt-3 px-3 py-1 text-[10px] font-semibold rounded-full bg-white/15 text-white/90 border border-white/20">
                    <?php echo ucfirst($user['role'] ?? 'staff'); ?>
                </span>
                
                <div class="mt-3 inline-flex items-center gap-1.5 text-[10px] text-emerald-300">
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                    Online
                </div>
                
                <div class="w-full h-px bg-white/15 my-5"></div>
                
                <div class="w-full text-left">
                    <p class="text-[9px] uppercase tracking-wider font-semibold text-white/40 mb-1">Terakhir Login</p>
                    <p class="text-xs font-medium text-white/80">
                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Belum pernah'; ?>
                    </p>
                </div>
            </div>
            
            <!-- KOLOM KANAN -->
            <div class="sm:w-2/3 p-6 sm:p-7">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    
                    <div class="flex items-center gap-2 mb-5">
                        <span class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </span>
                        <h3 class="text-sm font-bold text-gray-800">Informasi Akun</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Nama Lengkap</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/40 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-xs text-gray-700">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/40 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-xs text-gray-700">
                        </div>
                        
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">
                                Username <span class="text-gray-400 font-normal">(tidak dapat diubah)</span>
                            </label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled 
                                   class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-lg cursor-not-allowed text-gray-400 text-xs">
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 my-5">
                        <div class="flex-1 h-px bg-slate-100"></div>
                        <span class="text-[10px] uppercase tracking-wider font-semibold text-gray-400">Ganti Password</span>
                        <div class="flex-1 h-px bg-slate-100"></div>
                    </div>
                    
                    <p class="text-[10px] text-gray-400 mb-4 -mt-3">Kosongkan jika tidak ingin mengubah password</p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Password Saat Ini</label>
                            <input type="password" name="current_password" placeholder="••••••"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/40 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-xs text-gray-700 placeholder:text-gray-300">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Password Baru</label>
                            <input type="password" name="new_password" placeholder="Min. 6 karakter"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/40 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-xs text-gray-700 placeholder:text-gray-300">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Konfirmasi</label>
                            <input type="password" name="confirm_password" placeholder="Ulangi"
                                   class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400/40 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-xs text-gray-700 placeholder:text-gray-300">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-slate-700 to-slate-500 hover:from-slate-800 hover:to-slate-600 text-white rounded-lg text-xs font-semibold transition-all duration-300 shadow-md shadow-slate-500/30 hover:shadow-lg flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
html {
    background: linear-gradient(180deg, #CBD5E1 0%, #F1F5F9 70%, #FFFFFF 100%) !important;
    background-attachment: fixed !important;
    background-repeat: no-repeat !important;
    min-height: 100vh;
}
body, #mainContent, main, main > div, main > div > div {
    background: transparent !important;
    background-image: none !important;
}

.profile-card {
    background: #FFFFFF !important;
    background-image: none !important;
}
</style>

<?php include 'layout/footer.php'; ?>