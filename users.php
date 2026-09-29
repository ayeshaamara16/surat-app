<?php
require_once __DIR__ . '/middleware/auth_check.php';

// Admin & Superadmin only
if (!checkRole(['superadmin'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';

$title = 'Manajemen Pengguna - Sistem Manajemen Surat';
$page = 'users';
include 'layout/header.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Handle CRUD
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken('users.php');
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'staff';
            
            if (empty($username) || empty($password) || empty($name)) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Data Kurang', 'message' => 'Semua field wajib diisi!'];
            } elseif (strlen($password) < 6) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Password Lemah', 'message' => 'Password minimal 6 karakter!'];
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed, $name, $email, $role]);
                
                $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'create_user', ?)");
                $log->execute([$_SESSION['user_id'], "Menambah pengguna: $username"]);
                
                $_SESSION['flash'] = ['type' => 'created', 'title' => 'Berhasil Ditambahkan', 'message' => 'Pengguna baru berhasil ditambahkan.'];
            }
            header('Location: users.php');
            exit();
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'staff';
            $new_password = $_POST['new_password'] ?? '';
            
            $updates = [];
            $params = [];
            
            if (!empty($name)) {
                $updates[] = "name = ?";
                $params[] = $name;
            }
            if (!empty($email)) {
                $updates[] = "email = ?";
                $params[] = $email;
            }
            if (!empty($role)) {
                $updates[] = "role = ?";
                $params[] = $role;
            }
            if (!empty($new_password) && strlen($new_password) >= 6) {
                $updates[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            if (!empty($updates) && $id != $_SESSION['user_id']) {
                $params[] = $id;
                $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'update_user', ?)");
                $log->execute([$_SESSION['user_id'], "Mengupdate pengguna ID: $id"]);
                
                $_SESSION['flash'] = ['type' => 'updated', 'title' => 'Berhasil Diupdate', 'message' => 'Data pengguna berhasil diperbarui.'];
            } elseif ($id == $_SESSION['user_id']) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Tidak Diizinkan', 'message' => 'Tidak dapat mengubah akun sendiri!'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Tidak Ada Perubahan', 'message' => 'Tidak ada data yang diubah.'];
            }
            header('Location: users.php');
            exit();
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            if ($id != $_SESSION['user_id']) {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                
                $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'delete_user', ?)");
                $log->execute([$_SESSION['user_id'], "Menghapus pengguna ID: $id"]);
                
                $_SESSION['flash'] = ['type' => 'deleted', 'title' => 'Berhasil Dihapus', 'message' => 'Pengguna telah dihapus permanen.'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Tidak Diizinkan', 'message' => 'Tidak dapat menghapus akun sendiri!'];
            }
            header('Location: users.php');
            exit();
        }
    }
    
    // Get all users
    $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
    
    // Get edit data
    $edit_data = null;
    if (isset($_GET['edit'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_data = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = 'Gagal memuat data: ' . $e->getMessage();
}
?>

<?php include 'layout/navbar.php'; ?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
    <?php if (isset($error)): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 px-4 py-3 rounded-2xl mb-6 flex items-center gap-3 text-sm shadow-lg shadow-rose-100/50">
            <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- NOTIFIKASI FLASH -->
    <?php if (isset($_SESSION['flash'])): ?>
    <?php 
    $flash = $_SESSION['flash'];
    $type = $flash['type'] ?? 'success';
    
    $text_colors = [
        'created' => 'text-emerald-600',
        'updated' => 'text-blue-600',
        'deleted' => 'text-rose-600',
        'success' => 'text-emerald-600',
        'error'   => 'text-rose-600',
    ];
    $text_color = $text_colors[$type] ?? 'text-gray-700';
    ?>
    <div class="flash-message bg-white border border-slate-200 rounded-xl px-4 py-3 mb-6 flex items-center gap-3 text-sm shadow-sm w-full">
        <span class="flex-1 font-medium <?php echo $text_color; ?>"><?php echo htmlspecialchars($flash['message'] ?? ''); ?></span>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center shadow-lg shadow-slate-500/30 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-slate-700 to-slate-500 bg-clip-text text-transparent">Kelola Pengguna</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-12">Kelola hak akses dan data pengguna sistem</p>
        </div>
        <button onclick="document.getElementById('createUserModal').classList.remove('hidden')" 
                class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 bg-gradient-to-r from-slate-700 to-slate-500 hover:from-slate-800 hover:to-slate-600 text-white rounded-2xl text-xs sm:text-sm font-medium transition-all duration-300 shadow-lg shadow-slate-500/30 hover:shadow-xl hover:-translate-y-0.5 w-full sm:w-auto justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Pengguna
        </button>
    </div>
    
    <!-- Table -->
    <div class="table-card bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden hover:shadow-xl transition-all duration-500">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-slate-50 via-white to-slate-100 border-b border-slate-100">
                        <th class="px-5 py-4 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs">Username</th>
                        <th class="px-5 py-4 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs">Nama</th>
                        <th class="px-5 py-4 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs hidden sm:table-cell">Email</th>
                        <th class="px-5 py-4 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs">Role</th>
                        <th class="px-5 py-4 text-left font-semibold text-slate-500 uppercase tracking-wider text-xs hidden lg:table-cell">Login Terakhir</th>
                        <th class="px-5 py-4 text-center font-semibold text-slate-500 uppercase tracking-wider text-xs w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50/70 transition-all duration-200 group">
                            <!-- Username -->
                            <td class="px-5 py-4 text-sm font-semibold text-gray-800">
                                <span class="inline-flex items-center gap-2.5">
                                    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center text-white text-[10px] font-bold shrink-0 shadow-md shadow-slate-500/20">
                                        <?php echo strtoupper(substr($u['username'], 0, 2)); ?>
                                    </span>
                                    <?php echo htmlspecialchars($u['username']); ?>
                                </span>
                            </td>
                            
                            <!-- Nama -->
                            <td class="px-5 py-4 text-sm text-gray-600 font-medium">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </td>
                            
                            <!-- Email -->
                            <td class="px-5 py-4 text-sm text-gray-500 hidden sm:table-cell">
                                <?php echo htmlspecialchars($u['email'] ?? '-'); ?>
                            </td>
                            
                            <!-- Role - dengan warna berbeda -->
                            <td class="px-5 py-4">
                                <?php 
                                $role_colors = [
                                    'superadmin'  => 'bg-rose-100 text-rose-700 border-rose-200',
                                    'admin_surat' => 'bg-blue-100 text-blue-700 border-blue-200',
                                    'admin_nota'  => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'admin_arsip' => 'bg-amber-100 text-amber-700 border-amber-200'
                                ];
                                $role_color = $role_colors[$u['role']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                $role_label = ucfirst(str_replace('_', ' ', $u['role']));
                                ?>
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-lg border <?php echo $role_color; ?>">
                                    <?php echo $role_label; ?>
                                </span>
                            </td>
                            
                            <!-- Last Login -->
                            <td class="px-5 py-4 text-sm text-gray-500 hidden lg:table-cell">
                                <?php if ($u['last_login']): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-slate-50 px-3 py-1 rounded-lg text-slate-600 text-xs font-medium border border-slate-100">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <?php echo date('d/m/Y H:i', strtotime($u['last_login'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Aksi -->
                            <td class="px-5 py-4 w-32">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <!-- Edit -->
                                        <a href="?edit=<?php echo $u['id']; ?>" 
                                           class="inline-flex items-center justify-center w-9 h-9 rounded-xl 
                                                  text-blue-500 bg-blue-50 hover:text-white hover:bg-blue-500 
                                                  border border-blue-100 hover:border-blue-500
                                                  transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/30 hover:scale-110" 
                                           title="Edit Pengguna">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        
                                        <!-- Hapus -->
                                        <button onclick="if(confirm('Yakin ingin menghapus pengguna ini?')) { document.getElementById('deleteUser_<?php echo $u['id']; ?>').submit(); }" 
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-xl 
                                                       text-rose-500 bg-rose-50 hover:text-white hover:bg-rose-500 
                                                       border border-rose-100 hover:border-rose-500
                                                       transition-all duration-200 hover:shadow-lg hover:shadow-rose-500/30 hover:scale-110" 
                                                title="Hapus Pengguna">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <form id="deleteUser_<?php echo $u['id']; ?>" method="POST" style="display:none;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                        </form>
                                    <?php else: ?>
                                        <!-- Badge "Anda" -->
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-500 bg-slate-100 border border-slate-200 rounded-xl">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Anda
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    <span class="text-gray-400 font-medium">Belum ada pengguna</span>
                                    <span class="text-sm text-gray-300 mt-1">Klik tombol Tambah Pengguna untuk membuat</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<!-- ============================================ -->
<!-- BACKGROUND SLATE GRADIENT VERTIKAL - USERS   -->
<!-- ============================================ -->
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

/* Paksa card tabel jadi putih solid */
.table-card {
    background: #FFFFFF !important;
    background-image: none !important;
}

/* Modal - putih solid juga */
#createUserModal > div,
#editUserModal > div {
    background: #FFFFFF !important;
    background-image: none !important;
}

@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal { animation: modalIn 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
</style>

<?php include 'layout/footer.php'; ?>

<!-- Create User Modal -->
<div id="createUserModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-5 sm:p-6 w-full max-w-lg sm:max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center shadow-lg shadow-slate-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Tambah Pengguna</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Buat akun pengguna baru</p>
                </div>
            </div>
            <button onclick="document.getElementById('createUserModal').classList.add('hidden')" 
                    class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="create">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Username <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" name="username" required 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700"
                               placeholder="Username">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Password <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" name="password" required 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700"
                               placeholder="Min. 6 karakter">
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" name="name" required 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700"
                               placeholder="Nama lengkap pengguna">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Email <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="email" name="email" 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700"
                               placeholder="email@contoh.com">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Role <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <select name="role" required 
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer appearance-none">
                            <option value="admin_surat">Admin Surat</option>
                            <option value="admin_nota">Admin Nota</option>
                            <option value="admin_arsip">Admin Arsip</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <button type="button" onclick="document.getElementById('createUserModal').classList.add('hidden')" 
                        class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200">
                    Batal
                </button>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-slate-700 to-slate-500 hover:from-slate-800 hover:to-slate-600 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-slate-500/30 hover:shadow-slate-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($edit_data): ?>
<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-5 sm:p-6 w-full max-w-lg sm:max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center shadow-lg shadow-slate-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Edit Pengguna</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Perbarui data pengguna</p>
                </div>
            </div>
            <a href="users.php" class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" value="<?php echo htmlspecialchars($edit_data['username']); ?>" disabled 
                               class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl cursor-not-allowed text-gray-500 text-sm">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">Username tidak dapat diubah</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" required 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Email <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>" 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Role <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <select name="role" required 
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer appearance-none">
                            <option value="superadmin" <?php echo $edit_data['role'] === 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                            <option value="admin_surat" <?php echo $edit_data['role'] === 'admin_surat' ? 'selected' : ''; ?>>Admin Surat</option>
                            <option value="admin_nota" <?php echo $edit_data['role'] === 'admin_nota' ? 'selected' : ''; ?>>Admin Nota</option>
                            <option value="admin_arsip" <?php echo $edit_data['role'] === 'admin_arsip' ? 'selected' : ''; ?>>Admin Arsip</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Password Baru <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" name="new_password" 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-slate-400/50 focus:border-slate-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700" 
                               placeholder="Kosongkan jika tidak diubah">
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <a href="users.php" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200 text-center">
                    Batal
                </a>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-slate-700 to-slate-500 hover:from-slate-800 hover:to-slate-600 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-slate-500/30 hover:shadow-slate-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<style>
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal { animation: modalIn 0.25s cubic-bezier(0.4, 0, 0.2, 1); }

/* Hilangkan scrollbar tapi tetap bisa scroll */
#createUserModal > div::-webkit-scrollbar, 
#editUserModal > div::-webkit-scrollbar {
    display: none;
}

#createUserModal > div, 
#editUserModal > div {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>