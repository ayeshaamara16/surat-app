<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin', 'admin_surat'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';

// =====================================================
// CSRF TOKEN — generate sekali per session
// =====================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// KONFIGURASI UPLOAD
// =====================================================
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
$ALLOWED_EXT  = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx'];
$ALLOWED_MIME = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$title = 'Surat Masuk - Sistem Manajemen Surat';
$page = 'surat';
include 'layout/header.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // =====================================================
    // HELPER: validasi & proses upload file
    // =====================================================
    function processUpload($file, $upload_dir, $allowed_ext, $allowed_mime) {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Gagal upload file (kode: ' . $file['error'] . ')');
        }
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            throw new Exception('Ukuran file melebihi 10MB.');
        }
        
        $file_original = $file['name'];
        $file_tmp      = $file['tmp_name'];
        $file_ext      = strtolower(pathinfo($file_original, PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception('Ekstensi file tidak diizinkan.');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mime)) {
            throw new Exception('Tipe file tidak valid.');
        }
        
        $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_original);
        $safe_name = preg_replace('/_+/', '_', $safe_name);
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $full_path = $upload_dir . $safe_name;
        $file_name = $safe_name;
        
        if (file_exists($full_path)) {
            $name_without_ext = pathinfo($safe_name, PATHINFO_FILENAME);
            $file_name = $name_without_ext . '_' . date('Ymd_His') . '.' . $file_ext;
            $full_path = $upload_dir . $file_name;
        }
        
        if (!move_uploaded_file($file_tmp, $full_path)) {
            throw new Exception('Gagal menyimpan file.');
        }
        
        return [
            'file_name' => $file_name,
            'file_path' => 'uploads/surat_masuk/' . $file_name,
        ];
    }
    
    // =====================================================
    // HANDLE POST
    // =====================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $_SESSION['flash'] = ['type' => 'error', 'title' => 'Token Invalid', 'message' => 'Sesi tidak valid. Silakan coba lagi.'];
            header('Location: surat.php');
            exit();
        }
        
        $action = $_POST['action'] ?? '';
        
        // ---------------- CREATE ----------------
        if ($action === 'create') {
            // >>> REVISI: hapus $jenis_rancangan
            $nomor_surat     = $_POST['nomor_surat'] ?? '';
            $tanggal_surat   = $_POST['tanggal_surat'] ?? '';
            $tgl_masuk       = $_POST['tgl_masuk'] ?? '';
            $pengirim        = $_POST['pengirim'] ?? '';
            $perihal         = $_POST['perihal'] ?? '';
            $ket             = $_POST['ket'] ?? '';
            $dispo           = $_POST['dispo'] ?? '';
            $file_name       = null;
            $file_path       = null;
            
            try {
                $upload = processUpload($_FILES['dokumen'] ?? null, __DIR__ . '/uploads/surat_masuk/', $ALLOWED_EXT, $ALLOWED_MIME);
                if ($upload) {
                    $file_name = $upload['file_name'];
                    $file_path = $upload['file_path'];
                }
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Upload Gagal', 'message' => $e->getMessage()];
                header('Location: surat.php');
                exit();
            }
            
            // >>> REVISI: hapus jenis_rancangan dari query INSERT
            $stmt = $db->prepare("INSERT INTO surat_masuk 
                (nomor_surat, tanggal_surat, tgl_masuk, pengirim, perihal, ket, dispo, file_name, file_path, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nomor_surat, $tanggal_surat, $tgl_masuk, $pengirim, $perihal, $ket, $dispo, $file_name, $file_path, $_SESSION['user_id']]);
            
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'create_surat', ?)");
            $log->execute([$_SESSION['user_id'], "Menambah surat: $pengirim - $perihal"]);
            
            $_SESSION['flash'] = ['type' => 'created', 'title' => 'Berhasil Ditambahkan', 'message' => 'Surat masuk baru berhasil dicatat.'];
            header('Location: surat.php');
            exit();
        
        // ---------------- UPDATE STATUS ----------------
        } elseif ($action === 'update_status') {
            $id = $_POST['id'] ?? 0;
            
            $tgl_koreksi = !empty($_POST['tgl_koreksi']) ? $_POST['tgl_koreksi'] . ' 00:00:00' : null;
            $tgl_asmin   = !empty($_POST['tgl_asmin'])   ? $_POST['tgl_asmin']   . ' 00:00:00' : null;
            $tgl_sekda   = !empty($_POST['tgl_sekda'])   ? $_POST['tgl_sekda']   . ' 00:00:00' : null;
            $tgl_bupati  = !empty($_POST['tgl_bupati'])  ? $_POST['tgl_bupati']  . ' 00:00:00' : null;
            
            $status_koreksi = !empty($tgl_koreksi) ? 'selesai' : 'pending';
            $status_asmin   = !empty($tgl_asmin)   ? 'selesai' : 'pending';
            $status_sekda   = !empty($tgl_sekda)   ? 'selesai' : 'pending';
            $status_bupati  = !empty($tgl_bupati)  ? 'selesai' : 'pending';
            
            $stmt = $db->prepare("UPDATE surat_masuk SET 
                status_koreksi=?, status_asmin=?, status_sekda=?, status_bupati=?,
                tgl_koreksi=?, tgl_asmin=?, tgl_sekda=?, tgl_bupati=?
                WHERE id=?");
            $stmt->execute([
                $status_koreksi, $status_asmin, $status_sekda, $status_bupati,
                $tgl_koreksi, $tgl_asmin, $tgl_sekda, $tgl_bupati,
                $id
            ]);
            
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'update_status', ?)");
            $log->execute([$_SESSION['user_id'], "Update status surat ID: $id"]);
            
            $_SESSION['flash'] = ['type' => 'updated', 'title' => 'Status Diperbarui', 'message' => 'Status proses surat berhasil diupdate.'];
            header('Location: surat.php');
            exit();
        
        // ---------------- UPDATE ----------------
        } elseif ($action === 'update') {
            $id              = $_POST['id'] ?? 0;
            // >>> REVISI: hapus $jenis_rancangan
            $nomor_surat     = $_POST['nomor_surat'] ?? '';
            $tanggal_surat   = $_POST['tanggal_surat'] ?? '';
            $tgl_masuk       = $_POST['tgl_masuk'] ?? '';
            $pengirim        = $_POST['pengirim'] ?? '';
            $perihal         = $_POST['perihal'] ?? '';
            $ket             = $_POST['ket'] ?? '';
            $dispo           = $_POST['dispo'] ?? '';
            
            $old_stmt = $db->prepare("SELECT file_name, file_path FROM surat_masuk WHERE id = ?");
            $old_stmt->execute([$id]);
            $old_data = $old_stmt->fetch();
            
            if (!$old_data) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Data Tidak Ditemukan', 'message' => 'Surat yang mau diupdate tidak ada.'];
                header('Location: surat.php');
                exit();
            }
            
            $file_name = $old_data['file_name'];
            $file_path = $old_data['file_path'];
            $old_file_to_delete = null;
            
            try {
                $upload = processUpload($_FILES['dokumen'] ?? null, __DIR__ . '/uploads/surat_masuk/', $ALLOWED_EXT, $ALLOWED_MIME);
                if ($upload) {
                    if ($old_data['file_path'] && file_exists(__DIR__ . '/' . $old_data['file_path'])) {
                        $old_file_to_delete = __DIR__ . '/' . $old_data['file_path'];
                    }
                    $file_name = $upload['file_name'];
                    $file_path = $upload['file_path'];
                }
            } catch (Exception $e) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Upload Gagal', 'message' => $e->getMessage()];
                header('Location: surat.php?edit=' . $id);
                exit();
            }
            
            // >>> REVISI: hapus jenis_rancangan dari query UPDATE
            $stmt = $db->prepare("UPDATE surat_masuk SET 
                nomor_surat=?, tanggal_surat=?,
                tgl_masuk=?, pengirim=?, perihal=?, ket=?, dispo=?, file_name=?, file_path=? 
                WHERE id=?");
            $stmt->execute([$nomor_surat, $tanggal_surat, $tgl_masuk, $pengirim, $perihal, $ket, $dispo, $file_name, $file_path, $id]);
            
            if ($old_file_to_delete && file_exists($old_file_to_delete)) {
                unlink($old_file_to_delete);
            }
            
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'update_surat', ?)");
            $log->execute([$_SESSION['user_id'], "Mengupdate surat ID: $id ($pengirim - $perihal)"]);
            
            $_SESSION['flash'] = ['type' => 'updated', 'title' => 'Berhasil Diupdate', 'message' => 'Data surat berhasil diperbarui.'];
            header('Location: surat.php');
            exit();
        
        // ---------------- DELETE ----------------
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $file_stmt = $db->prepare("SELECT file_path FROM surat_masuk WHERE id = ?");
            $file_stmt->execute([$id]);
            $file_data = $file_stmt->fetch();
            
            if (!$file_data) {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Data Tidak Ditemukan', 'message' => 'Surat yang mau dihapus tidak ada.'];
                header('Location: surat.php');
                exit();
            }
            
            $stmt = $db->prepare("DELETE FROM surat_masuk WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($file_data['file_path'] && file_exists(__DIR__ . '/' . $file_data['file_path'])) {
                unlink(__DIR__ . '/' . $file_data['file_path']);
            }
            
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'delete_surat', ?)");
            $log->execute([$_SESSION['user_id'], "Menghapus surat ID: $id"]);
            
            $_SESSION['flash'] = ['type' => 'deleted', 'title' => 'Berhasil Dihapus', 'message' => 'Data surat telah dihapus permanen.'];
            header('Location: surat.php');
            exit();
        }
    }
    
    // =====================================================
    // GET DATA — search, filter, pagination
    // =====================================================
    $search        = $_GET['search'] ?? '';
    $filter_tahun  = $_GET['tahun'] ?? '';
    // >>> REVISI: hapus filter_jenis
    $page_num      = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit         = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $valid_limits = [10, 20, 50, 100];
    if (!in_array($limit, $valid_limits)) {
        $limit = 10;
    }
    
    $offset = ($page_num - 1) * $limit;
    
    $years_stmt = $db->query("SELECT DISTINCT YEAR(tgl_masuk) as tahun FROM surat_masuk ORDER BY tahun DESC");
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $where = "WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (pengirim LIKE ? OR perihal LIKE ? OR nomor_surat LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($filter_tahun) && $filter_tahun !== 'Semua Tahun') {
        $where .= " AND YEAR(tgl_masuk) = ?";
        $params[] = $filter_tahun;
    }
    
    // >>> REVISI: hapus filter jenis dari query WHERE
    
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM surat_masuk $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);
    
    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset = ($page_num - 1) * $limit;
    }
    
    $sql = "SELECT s.*, u.name as created_by_name 
            FROM surat_masuk s 
            LEFT JOIN users u ON s.created_by = u.id 
            $where 
            ORDER BY s.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    
    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $surats = $stmt->fetchAll();
    
    $edit_data = null;
    if (isset($_GET['edit'])) {
        $edit_stmt = $db->prepare("SELECT * FROM surat_masuk WHERE id = ?");
        $edit_stmt->execute([$_GET['edit']]);
        $edit_data = $edit_stmt->fetch();
    }
    
    $status_data = null;
    if (isset($_GET['status'])) {
        $status_stmt = $db->prepare("SELECT * FROM surat_masuk WHERE id = ?");
        $status_stmt->execute([$_GET['status']]);
        $status_data = $status_stmt->fetch();
    }
    
    // =====================================================
    // HELPER: bangun query string untuk pagination & link
    // =====================================================
    function buildQuery($overrides = []) {
        $base = [
            'search' => $_GET['search'] ?? '',
            'tahun'  => $_GET['tahun'] ?? '',
            // >>> REVISI: hapus 'jenis'
            'limit'  => $_GET['limit'] ?? 10,
            'page'   => $_GET['page'] ?? 1,
        ];
        $merged = array_merge($base, $overrides);
        $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
        return http_build_query($merged);
    }
    
} catch (PDOException $e) {
    $error = 'Gagal memuat data: ' . $e->getMessage();
    $surats = [];
    $total = 0;
    $total_pages = 0;
}
?>

<!-- ==================== HTML ==================== -->
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
                <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">Surat Masuk</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-12">Kelola surat masuk rancangan keputusan/peraturan bupati</p>
        </div>
        <div class="flex flex-wrap gap-2 w-full sm:w-auto">
            <button onclick="document.getElementById('createModal').classList.remove('hidden')" 
                    class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 bg-gradient-to-r from-blue-500 to-cyan-400 hover:from-blue-600 hover:to-cyan-500 text-white rounded-2xl text-xs sm:text-sm font-medium transition-all duration-300 shadow-lg shadow-blue-500/30 hover:shadow-xl hover:-translate-y-0.5 flex-1 sm:flex-none justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Surat
            </button>
            <button onclick="document.getElementById('exportModal').classList.remove('hidden')" 
                    class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 bg-gradient-to-r from-rose-500 to-pink-400 hover:from-rose-600 hover:to-pink-500 text-white rounded-2xl text-xs sm:text-sm font-medium transition-all duration-300 shadow-lg shadow-rose-500/30 hover:shadow-xl hover:-translate-y-0.5 flex-1 sm:flex-none justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export
            </button>
        </div>
    </div>
    
    <!-- Search & Filter -->
    <div class="search-card bg-white rounded-2xl shadow-lg border border-blue-100 p-4 sm:p-5 mb-6 hover:shadow-xl transition-all duration-500">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Cari surat..." class="w-full py-2.5 pl-11 pr-4 text-sm bg-blue-50/50 border border-blue-200/50 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none placeholder:text-gray-400 text-gray-700">
            </div>
            
            <!-- >>> REVISI: Hapus dropdown Jenis -->
            
            <div class="flex items-center gap-2">
                <select name="tahun" onchange="this.form.submit()" 
                        class="px-3 py-2.5 bg-blue-50/50 border border-blue-200/50 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $filter_tahun == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500 font-medium">Tampilkan</label>
                <select name="limit" onchange="this.form.submit()" 
                        class="px-3 py-2.5 bg-blue-50/50 border border-blue-200/50 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer">
                    <?php foreach ([10, 20, 50, 100] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $limit == $opt ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-sm text-gray-400">data</span>
            </div>
            
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-cyan-400 hover:from-blue-600 hover:to-cyan-500 text-white rounded-xl text-sm font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Cari
            </button>
        </form>
    </div>
    
    <!-- Table -->
    <div class="table-card bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden hover:shadow-xl transition-all duration-500">
        <div class="w-full overflow-x-auto">
            <table class="w-full min-w-[1100px] table-fixed">
                <thead>
                    <tr class="bg-gradient-to-r from-blue-50 via-white to-cyan-50 border-b border-blue-100">
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[50px]">NO</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[110px] hidden sm:table-cell">Tgl Masuk</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[110px] hidden sm:table-cell">Tgl Surat</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[150px]">No. Surat</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[130px]">Pengirim</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold text-blue-600/70 uppercase tracking-wider">Perihal</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[110px]">Proses</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[100px]">Dokumen</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold text-blue-600/70 uppercase tracking-wider w-[120px]">Aksi</th> 
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-100/40">
                    <?php if (!empty($surats) && is_array($surats)): ?>
                        <?php $nomor = $offset + 1; ?>
                        <?php foreach ($surats as $surat): ?>
                            <tr class="hover:bg-blue-50/50 transition-all duration-200 group">
                                
                                <!-- NO -->
                                <td class="px-3 py-3 text-sm font-semibold text-gray-800 text-center">
                                    <?php echo $nomor++; ?>
                                </td>
                                
                                <!-- TANGGAL MASUK -->
                                <td class="px-3 py-3 text-sm text-gray-600 hidden sm:table-cell">
                                    <span class="inline-block bg-blue-50 px-2 py-1 rounded-lg text-blue-600 text-xs font-medium whitespace-nowrap">
                                        <?php echo date('d/m/Y', strtotime($surat['tgl_masuk'])); ?>
                                    </span>
                                </td>
                                
                                <!-- TANGGAL SURAT -->
                                <td class="px-3 py-3 text-sm text-gray-600 hidden sm:table-cell">
                                    <?php if (!empty($surat['tanggal_surat'])): ?>
                                        <span class="inline-block bg-emerald-50 px-2 py-1 rounded-lg text-emerald-600 text-xs font-medium whitespace-nowrap">
                                            <?php echo date('d/m/Y', strtotime($surat['tanggal_surat'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- NO SURAT -->
                                <td class="px-3 py-3 text-sm text-gray-700">
                                    <div class="truncate max-w-[150px]" title="<?php echo htmlspecialchars($surat['nomor_surat'] ?? '-'); ?>">
                                        <?php echo htmlspecialchars($surat['nomor_surat'] ?? '-'); ?>
                                    </div>
                                </td>
                                
                                <!-- PENGIRIM -->
                                <td class="px-3 py-3 text-sm text-gray-700">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-400 to-cyan-400 flex items-center justify-center text-white text-[8px] font-bold shrink-0">
                                            <?php echo strtoupper(substr($surat['pengirim'], 0, 2)); ?>
                                        </span>
                                        <span class="truncate" title="<?php echo htmlspecialchars($surat['pengirim']); ?>">
                                            <?php echo htmlspecialchars($surat['pengirim']); ?>
                                        </span>
                                    </div>
                                </td>
                                
                                <!-- PERIHAL -->
                                <td class="px-3 py-3 text-sm text-gray-600">
                                    <div class="truncate max-w-[300px]" title="<?php echo htmlspecialchars($surat['perihal']); ?>">
                                        <?php echo htmlspecialchars($surat['perihal']); ?>
                                    </div>
                                </td>
                                
                                <!-- PROSES -->
                                <td class="px-3 py-3">
                                    <?php 
                                    $tgl_koreksi = !empty($surat['tgl_koreksi']) ? date('d/m/Y', strtotime($surat['tgl_koreksi'])) : '-';
                                    $tgl_asmin   = !empty($surat['tgl_asmin'])   ? date('d/m/Y', strtotime($surat['tgl_asmin']))   : '-';
                                    $tgl_sekda   = !empty($surat['tgl_sekda'])   ? date('d/m/Y', strtotime($surat['tgl_sekda']))   : '-';
                                    $tgl_bupati  = !empty($surat['tgl_bupati'])  ? date('d/m/Y', strtotime($surat['tgl_bupati']))  : '-';
                                    
                                    $status_label = '';
                                    $badge_color = 'bg-gray-100 border-gray-300 text-gray-400';
                                    if ($tgl_koreksi != '-'):
                                        $badge_color = 'bg-blue-100 border-blue-300 text-blue-700';
                                        $status_label = 'KOREKSI';
                                    endif;
                                    if ($tgl_asmin != '-'):
                                        $badge_color = 'bg-yellow-100 border-yellow-300 text-yellow-700';
                                        $status_label = 'ASMIN';
                                    endif;
                                    if ($tgl_sekda != '-'):
                                        $badge_color = 'bg-purple-100 border-purple-300 text-purple-700';
                                        $status_label = 'SEKDA';
                                    endif;
                                    if ($tgl_bupati != '-'):
                                        $badge_color = 'bg-red-100 border-red-300 text-red-700';
                                        $status_label = 'BUPATI';
                                    endif;
                                    ?>
                                    <div class="flex justify-center">
                                        <?php if ($status_label != ''): ?>
                                            <span class="<?php echo $badge_color; ?> border rounded-md px-2 py-1 text-[10px] font-semibold inline-block text-center w-[80px] whitespace-nowrap">
                                                <?php echo $status_label; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 border border-gray-200 rounded-md px-2 py-1 text-[10px] text-gray-400 inline-block text-center w-[80px] whitespace-nowrap">
                                                BELUM
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- DOKUMEN -->
                                <td class="px-4 py-3 text-center">
                                    <?php if (!empty($surat['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($surat['file_path']); ?>" target="_blank" 
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg text-xs font-medium transition-all duration-200 hover:shadow-md whitespace-nowrap">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Lihat
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- AKSI -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="?<?php echo buildQuery(['status' => $surat['id'], 'edit' => '', 'page' => '']); ?>" 
                                           class="p-2 rounded-lg text-emerald-500 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-200" 
                                           title="Update Status">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </a>
                                        <a href="?<?php echo buildQuery(['edit' => $surat['id'], 'status' => '', 'page' => '']); ?>" 
                                           class="p-1.5 rounded-lg text-blue-500 hover:text-blue-700 hover:bg-blue-50 transition-all duration-200" 
                                           title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button onclick="if(confirm('Yakin ingin menghapus surat ini?')) { document.getElementById('deleteForm_<?php echo $surat['id']; ?>').submit(); }" 
                                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-all duration-200"
                                                title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <form id="deleteForm_<?php echo $surat['id']; ?>" method="POST" style="display:none;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $surat['id']; ?>">
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 text-blue-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-gray-400 font-medium">Belum ada data surat</span>
                                    <span class="text-sm text-gray-300 mt-1">Klik tombol Tambah Surat untuk menambahkan</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="px-4 sm:px-6 py-4 border-t border-blue-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gradient-to-r from-blue-50/40 to-cyan-50/40">
                <div class="text-sm text-gray-500">
                    Menampilkan <span class="font-medium text-gray-700"><?php echo $total > 0 ? $offset + 1 : 0; ?></span> - 
                    <span class="font-medium text-gray-700"><?php echo min($offset + $limit, $total); ?></span> 
                    dari <span class="font-medium text-gray-700"><?php echo $total; ?></span> data
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($page_num > 1): ?>
                        <a href="?<?php echo buildQuery(['page' => $page_num - 1]); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 text-sm font-medium rounded-xl bg-gray-100 text-gray-300 cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                    
                    <div class="flex gap-1.5">
                        <?php 
                        $start_page = max(1, $page_num - 2);
                        $end_page = min($total_pages, $page_num + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?<?php echo buildQuery(['page' => 1]); ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 transition-all duration-200">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?<?php echo buildQuery(['page' => $i]); ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl transition-all duration-200 
                                   <?php echo $i == $page_num ? 'bg-gradient-to-r from-blue-500 to-cyan-400 text-white shadow-md' : 'bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
                            <?php endif; ?>
                            <a href="?<?php echo buildQuery(['page' => $total_pages]); ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 transition-all duration-200">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?<?php echo buildQuery(['page' => $page_num + 1]); ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 text-sm font-medium rounded-xl bg-gray-100 text-gray-300 cursor-not-allowed">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
html {
    background: linear-gradient(180deg, #BFDBFE 0%, #EFF6FF 70%, #FFFFFF 100%) !important;
    background-attachment: fixed !important;
    background-repeat: no-repeat !important;
    min-height: 100vh;
}
body, #mainContent, main, main > div, main > div > div {
    background: transparent !important;
    background-image: none !important;
}

.search-card,
.table-card {
    background: #FFFFFF !important;
    background-image: none !important;
}

#createModal > div,
#editModal > div,
#exportModal > div,
#statusModal > div {
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

<!-- Create Modal -->
<div id="createModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-5 sm:p-6 w-full max-w-lg sm:max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Tambah Surat Masuk</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Catat surat masuk baru</p>
                </div>
            </div>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" 
                    class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="create">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tanggal Masuk <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="date" name="tgl_masuk" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Pengirim <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="pengirim" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Nama instansi/pengirim">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nomor Surat <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                        </div>
                        <input type="text" name="nomor_surat" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Contoh: 500.2.7/1307/XII/2025">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tanggal Surat <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="date" name="tanggal_surat" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <!-- >>> REVISI: HAPUS FIELD JENIS RANCANGAN DI SINI -->
                
                <!-- Disposisi — 1 kolom -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Disposisi</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <input type="text" name="dispo" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Disposisi">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Perihal <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="perihal" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Perihal surat">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Upload Dokumen <span class="text-gray-400 font-normal normal-case">(opsional, max 10MB)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <input type="file" name="dokumen" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">Format: PDF, DOC, DOCX, JPG, JPEG, PNG, XLS, XLSX</p>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Keterangan <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <textarea name="ket" rows="2" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400 resize-none" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200">Batal</button>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-blue-500 to-cyan-400 hover:from-blue-600 hover:to-cyan-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Surat
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Status Modal -->
<?php if ($status_data): ?>
<div id="statusModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-2xl p-4 sm:p-5 w-full max-w-xl max-h-[85vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-3 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30 shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Update Status Surat</h3>
                    <p class="text-[10px] text-gray-400 font-medium">Perbarui tanggal proses surat</p>
                </div>
            </div>
            <a href="?<?php echo buildQuery(['status' => '', 'edit' => '']); ?>" 
               class="p-1.5 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?php echo $status_data['id']; ?>">
            
            <div class="bg-blue-50 rounded-xl p-3 mb-3 border border-blue-100">
                <div class="grid grid-cols-2 gap-1 text-xs">
                    <div><span class="text-gray-500">Pengirim:</span> <span class="font-medium text-gray-700"><?php echo htmlspecialchars(substr($status_data['pengirim'], 0, 25)) . (strlen($status_data['pengirim']) > 25 ? '...' : ''); ?></span></div>
                    <div><span class="text-gray-500">Perihal:</span> <span class="font-medium text-gray-700"><?php echo htmlspecialchars(substr($status_data['perihal'], 0, 20)) . (strlen($status_data['perihal']) > 20 ? '...' : ''); ?></span></div>
                    <div><span class="text-gray-500">Tgl Masuk:</span> <span class="font-medium text-gray-700"><?php echo date('d/m/Y', strtotime($status_data['tgl_masuk'])); ?></span></div>
                    <div><span class="text-gray-500">File:</span> <span class="font-medium text-gray-700"><?php echo htmlspecialchars($status_data['file_name'] ?? '-'); ?></span></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3">
                    <label class="text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> KOREKSI
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="tgl_koreksi" 
                               value="<?php echo !empty($status_data['tgl_koreksi']) ? date('Y-m-d', strtotime($status_data['tgl_koreksi'])) : ''; ?>" 
                               class="flex-1 px-3 py-1.5 text-sm bg-white border border-blue-200 rounded-lg focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 outline-none">
                        <?php if (!empty($status_data['tgl_koreksi'])): ?>
                            <span class="text-[10px] text-blue-600 whitespace-nowrap">✓ <?php echo date('d/m/Y', strtotime($status_data['tgl_koreksi'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3">
                    <label class="text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span> ASMIN
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="tgl_asmin" 
                               value="<?php echo !empty($status_data['tgl_asmin']) ? date('Y-m-d', strtotime($status_data['tgl_asmin'])) : ''; ?>" 
                               class="flex-1 px-3 py-1.5 text-sm bg-white border border-yellow-200 rounded-lg focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 outline-none">
                        <?php if (!empty($status_data['tgl_asmin'])): ?>
                            <span class="text-[10px] text-blue-600 whitespace-nowrap">✓ <?php echo date('d/m/Y', strtotime($status_data['tgl_asmin'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-purple-50 border border-purple-200 rounded-xl p-3">
                    <label class="text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> SEKDA
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="tgl_sekda" 
                               value="<?php echo !empty($status_data['tgl_sekda']) ? date('Y-m-d', strtotime($status_data['tgl_sekda'])) : ''; ?>" 
                               class="flex-1 px-3 py-1.5 text-sm bg-white border border-purple-200 rounded-lg focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 outline-none">
                        <?php if (!empty($status_data['tgl_sekda'])): ?>
                            <span class="text-[10px] text-blue-600 whitespace-nowrap">✓ <?php echo date('d/m/Y', strtotime($status_data['tgl_sekda'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                    <label class="text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> BUPATI
                    </label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="tgl_bupati" 
                               value="<?php echo !empty($status_data['tgl_bupati']) ? date('Y-m-d', strtotime($status_data['tgl_bupati'])) : ''; ?>" 
                               class="flex-1 px-3 py-1.5 text-sm bg-white border border-red-200 rounded-lg focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 outline-none">
                        <?php if (!empty($status_data['tgl_bupati'])): ?>
                            <span class="text-[10px] text-blue-600 whitespace-nowrap">✓ <?php echo date('d/m/Y', strtotime($status_data['tgl_bupati'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 flex justify-end gap-2 flex-col-reverse sm:flex-row">
                <a href="?<?php echo buildQuery(['status' => '', 'edit' => '']); ?>" 
                   class="w-full sm:w-auto px-4 py-2 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200 text-center">
                    Batal
                </a>
                <button type="submit" class="w-full sm:w-auto px-5 py-2 bg-gradient-to-r from-blue-500 to-cyan-400 hover:from-blue-600 hover:to-cyan-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<?php if ($edit_data): ?>
<div id="editModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-5 sm:p-6 w-full max-w-lg sm:max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-blue-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Edit Surat Masuk</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Perbarui data surat yang sudah dicatat</p>
                </div>
            </div>
            <a href="?<?php echo buildQuery(['edit' => '', 'status' => '']); ?>" class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </a>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tanggal Masuk <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="date" name="tgl_masuk" value="<?php echo $edit_data['tgl_masuk']; ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Pengirim <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="pengirim" value="<?php echo htmlspecialchars($edit_data['pengirim']); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nomor Surat <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                        </div>
                        <input type="text" name="nomor_surat" value="<?php echo htmlspecialchars($edit_data['nomor_surat'] ?? ''); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tanggal Surat <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="date" name="tanggal_surat" value="<?php echo $edit_data['tanggal_surat'] ?? ''; ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <!-- >>> REVISI: HAPUS FIELD JENIS RANCANGAN DI SINI -->
                
                <!-- Disposisi — 1 kolom -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Disposisi</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <input type="text" name="dispo" value="<?php echo htmlspecialchars($edit_data['dispo']); ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Perihal <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="perihal" value="<?php echo htmlspecialchars($edit_data['perihal']); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Upload Dokumen <span class="text-gray-400 font-normal normal-case">(opsional, max 10MB)</span></label>
                    <?php if (!empty($edit_data['file_path'])): ?>
                        <div class="mb-2 p-2.5 bg-blue-50 rounded-xl flex items-center gap-2 border border-blue-200">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span class="text-xs text-gray-600">Dokumen saat ini: </span>
                            <a href="<?php echo htmlspecialchars($edit_data['file_path']); ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 font-semibold truncate">
                                <?php echo htmlspecialchars($edit_data['file_name']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <input type="file" name="dokumen" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1.5">Kosongkan jika tidak ingin mengganti dokumen.</p>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Keterangan <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <textarea name="ket" rows="2" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400 resize-none"><?php echo htmlspecialchars($edit_data['ket'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <a href="?<?php echo buildQuery(['edit' => '', 'status' => '']); ?>" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200 text-center">Batal</a>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-blue-500 to-cyan-400 hover:from-blue-600 hover:to-cyan-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update Surat
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-4 sm:p-6 w-full max-w-md shadow-2xl border border-gray-100 animate-modal">
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-rose-500 to-pink-400 flex items-center justify-center shadow-lg shadow-rose-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <div>
                    <!-- >>> REVISI: Ganti Judul Modal Export -->
                    <h3 class="text-lg font-bold text-gray-800 leading-tight">Export Draft Produk Hukum</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Pilih data dan format</p>
                </div>
            </div>
            <button onclick="document.getElementById('exportModal').classList.add('hidden')" class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form action="export_pdf.php" method="GET" target="_blank">
            <!-- >>> REVISI: HAPUS FILTER JENIS RANCANGAN DI SINI -->
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Pilih Data yang Diexport</label>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_periode" value="semua" class="text-rose-600" checked onchange="document.getElementById('filter_tanggal').classList.add('hidden'); document.getElementById('filter_bulan').classList.add('hidden'); document.getElementById('filter_tahun_select').classList.add('hidden');">
                        <span class="ml-2 text-sm text-gray-700">Semua Data</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_periode" value="tanggal" class="text-rose-600" onchange="document.getElementById('filter_tanggal').classList.remove('hidden'); document.getElementById('filter_bulan').classList.add('hidden'); document.getElementById('filter_tahun_select').classList.add('hidden');">
                        <span class="ml-2 text-sm text-gray-700">Tanggal</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_periode" value="bulan" class="text-rose-600" onchange="document.getElementById('filter_tanggal').classList.add('hidden'); document.getElementById('filter_bulan').classList.remove('hidden'); document.getElementById('filter_tahun_select').classList.add('hidden');">
                        <span class="ml-2 text-sm text-gray-700">Bulan</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="jenis_periode" value="tahun" class="text-rose-600" onchange="document.getElementById('filter_tanggal').classList.add('hidden'); document.getElementById('filter_bulan').classList.add('hidden'); document.getElementById('filter_tahun_select').classList.remove('hidden');">
                        <span class="ml-2 text-sm text-gray-700">Tahun</span>
                    </label>
                </div>
            </div>
            <div id="filter_tanggal" class="grid grid-cols-2 gap-4 mb-4 hidden">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="w-full py-2 px-3 border border-gray-200 rounded-xl outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-400 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="w-full py-2 px-3 border border-gray-200 rounded-xl outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-400 text-sm">
                </div>
            </div>
            <div id="filter_bulan" class="grid grid-cols-2 gap-4 mb-4 hidden">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Bulan Mulai</label>
                    <input type="month" name="start_month" class="w-full py-2 px-3 border border-gray-200 rounded-xl outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-400 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Bulan Akhir</label>
                    <input type="month" name="end_month" class="w-full py-2 px-3 border border-gray-200 rounded-xl outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-400 text-sm">
                </div>
            </div>
            <div id="filter_tahun_select" class="mb-4 hidden">
                <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">Pilih Tahun</label>
                <select name="tahun" class="w-full py-2 px-3 border border-gray-200 rounded-xl outline-none focus:border-rose-400 focus:ring-1 focus:ring-rose-400 text-sm">
                    <option value="">-- Pilih Tahun --</option>
                    <?php if (!empty($available_years)): ?>
                        <?php foreach ($available_years as $y): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Pilih Format</label>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center">
                        <input type="radio" name="format" value="pdf" class="text-rose-600" checked>
                        <span class="ml-2 text-sm text-gray-700">PDF</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="format" value="excel" class="text-rose-600">
                        <span class="ml-2 text-sm text-gray-700">Excel</span>
                    </label>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <button type="button" onclick="document.getElementById('exportModal').classList.add('hidden')" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200">Batal</button>
                <button type="submit" onclick="document.getElementById('exportModal').classList.add('hidden')" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-rose-500 to-pink-400 hover:from-rose-600 hover:to-pink-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-rose-500/30 hover:shadow-rose-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.95) translateY(-10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.animate-modal { animation: modalIn 0.25s cubic-bezier(0.4, 0, 0.2, 1); }

@media (min-width: 480px) {
    .xs\:inline { display: inline; }
    .xs\:table-cell { display: table-cell; }
}
@media (max-width: 479px) {
    .xs\:inline { display: none; }
    .xs\:table-cell { display: none; }
}

#createModal > div::-webkit-scrollbar, 
#editModal > div::-webkit-scrollbar,
#exportModal > div::-webkit-scrollbar,
#statusModal > div::-webkit-scrollbar {
    display: none;
}

#createModal > div, 
#editModal > div,
#exportModal > div,
#statusModal > div {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>