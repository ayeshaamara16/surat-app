<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin', 'admin_arsip'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';

$title = 'Arsip Dokumen - Sistem Manajemen Surat';
$page = 'arsip';
include 'layout/header.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // =====================================================
    // HANDLE POST (UPLOAD & DELETE)
    // =====================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken('arsip.php');
        $action = $_POST['action'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        // ---------------- UPLOAD ----------------
        if ($action === 'upload') {
            $judul = $_POST['judul'] ?? '';
            $nomor_peraturan = $_POST['nomor_peraturan'] ?? '';
            $jenis = $_POST['jenis'] ?? '';
            $tahun = $_POST['tahun'] ?? date('Y');
            $keterangan = $_POST['keterangan'] ?? '';
            
            // Handle File Upload
            if (isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/arsip/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Konfigurasi
                $allowed_ext = ['pdf', 'doc', 'docx'];
                $allowed_mime = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];
                $max_size = 10 * 1024 * 1024; // 10MB
                
                $file_info = pathinfo($_FILES['dokumen']['name']);
                $ext = strtolower($file_info['extension']);
                
                // Cek ekstensi
                if (!in_array($ext, $allowed_ext)) {
                    $_SESSION['flash'] = ['type' => 'error', 'title' => 'Format Salah', 'message' => 'Hanya file PDF, DOC, DOCX yang diizinkan.'];
                    header('Location: arsip.php');
                    exit();
                }
                
                // Cek MIME asli
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['dokumen']['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mime, $allowed_mime)) {
                    $_SESSION['flash'] = ['type' => 'error', 'title' => 'Tipe File Invalid', 'message' => 'Tipe file tidak valid.'];
                    header('Location: arsip.php');
                    exit();
                }
                
                // Cek ukuran
                if ($_FILES['dokumen']['size'] > $max_size) {
                    $_SESSION['flash'] = ['type' => 'error', 'title' => 'File Terlalu Besar', 'message' => 'Ukuran file melebihi 10MB.'];
                    header('Location: arsip.php');
                    exit();
                }
                
                // Rename file biar unik
                $new_filename = uniqid('arsip_') . '.' . $ext;
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['dokumen']['tmp_name'], $file_path)) {
                    $stmt = $db->prepare("INSERT INTO arsip_dokumen (judul, nomor_peraturan, jenis, tahun, file_name, file_path, file_size, mime_type, keterangan, uploaded_by) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $judul, $nomor_peraturan, $jenis, $tahun, 
                        $_FILES['dokumen']['name'], $file_path, 
                        $_FILES['dokumen']['size'], $_FILES['dokumen']['type'], 
                        $keterangan, $user_id
                    ]);
                    
                    // LOG AKTIVITAS
                    $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'upload_arsip', ?)");
                    $log->execute([$user_id, "Upload arsip: $judul ($jenis)"]);
                    
                    $_SESSION['flash'] = ['type' => 'created', 'title' => 'Berhasil Diarsipkan', 'message' => 'Dokumen berhasil diarsipkan.'];
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'title' => 'Upload Gagal', 'message' => 'Gagal mengupload file.'];
                }
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'File Kosong', 'message' => 'File wajib diupload.'];
            }
            header('Location: arsip.php');
            exit();
        
        // ---------------- DELETE ----------------
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Ambil data dulu untuk log
            $stmt = $db->prepare("SELECT judul, file_path FROM arsip_dokumen WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            
            if ($data) {
                // Hapus file fisik
                if (file_exists($data['file_path'])) {
                    unlink($data['file_path']);
                }
                
                // Hapus dari database
                $stmt = $db->prepare("DELETE FROM arsip_dokumen WHERE id = ?");
                $stmt->execute([$id]);
                
                // LOG AKTIVITAS
                $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'delete_arsip', ?)");
                $log->execute([$user_id, "Menghapus arsip: " . $data['judul']]);
                
                $_SESSION['flash'] = ['type' => 'deleted', 'title' => 'Berhasil Dihapus', 'message' => 'Arsip telah dihapus permanen.'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'title' => 'Data Tidak Ditemukan', 'message' => 'Arsip tidak ditemukan.'];
            }
            header('Location: arsip.php');
            exit();
        }
    }

    // =====================================================
    // PAGINATION & SEARCH (SQL LIMIT)
    // =====================================================
    $years_stmt = $db->query("SELECT DISTINCT tahun FROM arsip_dokumen ORDER BY tahun DESC");
    $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

    $search = $_GET['search'] ?? '';
    $jenis_filter = $_GET['jenis'] ?? '';
    $tahun_filter = $_GET['tahun'] ?? '';

    $page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $valid_limits = [10, 20, 50, 100];
    if (!in_array($limit, $valid_limits)) {
        $limit = 10;
    }
    
    $offset = ($page_num - 1) * $limit;
    
    $where = "WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (judul LIKE ? OR nomor_peraturan LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if (!empty($jenis_filter)) {
        $where .= " AND jenis = ?";
        $params[] = $jenis_filter;
    }
    if (!empty($tahun_filter)) {
        $where .= " AND tahun = ?";
        $params[] = $tahun_filter;
    }

    // Count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM arsip_dokumen $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);
    
    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset = ($page_num - 1) * $limit;
    }
    
    // Get data dengan SQL LIMIT
    $sql = "SELECT a.*, u.name as uploader_name 
            FROM arsip_dokumen a 
            LEFT JOIN users u ON a.uploaded_by = u.id 
            $where 
            ORDER BY a.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $arsips = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Gagal memuat data: ' . $e->getMessage();
    $arsips = [];
    $total = 0;
    $total_pages = 0;
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
                <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-400 flex items-center justify-center shadow-lg shadow-amber-500/30 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-amber-500 to-rose-500 bg-clip-text text-transparent">Arsip Dokumen</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-12">Kelola arsip Perbup, Perdes, SK, Perda, dan lainnya</p>
        </div>
        <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" 
                class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 bg-gradient-to-r from-amber-500 to-orange-400 hover:from-amber-600 hover:to-orange-500 text-white rounded-2xl text-xs sm:text-sm font-medium transition-all duration-300 shadow-lg shadow-amber-500/30 hover:shadow-xl hover:-translate-y-0.5 w-full sm:w-auto justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Upload Arsip
        </button>
    </div>
    
    <!-- Filter & Search -->
    <div class="search-card bg-white rounded-2xl shadow-lg border border-amber-100 p-4 sm:p-5 mb-6 hover:shadow-xl transition-all duration-500">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Cari judul atau nomor..." class="w-full py-2.5 pl-11 pr-4 text-sm bg-amber-50/50 border border-amber-200/50 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none placeholder:text-gray-400 text-gray-700">
            </div>
            <div class="flex items-center gap-2">
                <div>
                    <select name="jenis" onchange="this.form.submit()" 
                            class="px-3 py-2.5 bg-amber-50/50 border border-amber-200/50 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer">
                        <option value="">Semua Jenis</option>
                        <option value="Perbup" <?php echo $jenis_filter === 'Perbup' ? 'selected' : ''; ?>>Perbup</option>
                        <option value="Perdes" <?php echo $jenis_filter === 'Perdes' ? 'selected' : ''; ?>>Perdes</option>
                        <option value="SK" <?php echo $jenis_filter === 'SK' ? 'selected' : ''; ?>>SK</option>
                        <option value="Perda" <?php echo $jenis_filter === 'Perda' ? 'selected' : ''; ?>>Perda</option>
                        <option value="Lainnya" <?php echo $jenis_filter === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                    </select>
                </div>
                <div>
                    <select name="tahun" onchange="this.form.submit()" 
                            class="px-3 py-2.5 bg-amber-50/50 border border-amber-200/50 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer">
                        <option value="">Semua Tahun</option>
                        <?php foreach ($available_years as $yr): ?>
                            <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo $tahun_filter == $yr ? 'selected' : ''; ?>><?php echo htmlspecialchars($yr); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500 font-medium">Tampilkan</label>
                <select name="limit" onchange="this.form.submit()" 
                        class="px-3 py-2.5 bg-amber-50/50 border border-amber-200/50 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer">
                    <?php foreach ([10, 20, 50, 100] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $limit == $opt ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-sm text-gray-400">data</span>
            </div>

            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-400 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl text-sm font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Filter
            </button>
        </form>
    </div>
    
    <!-- Tabel Arsip -->
    <div class="table-card bg-white rounded-2xl shadow-lg border border-amber-100 overflow-hidden hover:shadow-xl transition-all duration-500">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="bg-gradient-to-r from-amber-50 via-white to-orange-50 border-b border-amber-100">
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-amber-600/70 uppercase tracking-wider">No</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-amber-600/70 uppercase tracking-wider">Judul</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-amber-600/70 uppercase tracking-wider">Nomor</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-amber-600/70 uppercase tracking-wider">Jenis</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-amber-600/70 uppercase tracking-wider">Tahun</th>
                        <th class="px-4 sm:px-6 py-3.5 text-center text-xs font-semibold text-amber-600/70 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-100/40">
                    <?php if (!empty($arsips)): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach ($arsips as $arsip): ?>
                            <tr class="hover:bg-amber-50/50 transition-all duration-200 group">
                                <td class="px-4 sm:px-6 py-3.5 text-sm font-semibold text-gray-800"><?php echo $no++; ?></td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-gray-600 max-w-[200px] truncate" title="<?php echo htmlspecialchars($arsip['judul']); ?>">
                                    <?php echo htmlspecialchars($arsip['judul']); ?>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-gray-600 font-medium"><?php echo htmlspecialchars($arsip['nomor_peraturan']); ?></td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm">
                                    <?php 
                                    $jenis_colors = [
                                        'Perbup' => 'bg-indigo-100 text-indigo-700',
                                        'Perdes' => 'bg-emerald-100 text-emerald-700',
                                        'SK' => 'bg-purple-100 text-purple-700',
                                        'Perda' => 'bg-blue-100 text-blue-700',
                                        'Lainnya' => 'bg-amber-100 text-amber-700'
                                    ];
                                    $color_class = $jenis_colors[$arsip['jenis']] ?? 'bg-gray-100 text-gray-700';
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-xl <?php echo $color_class; ?>">
                                        <?php echo $arsip['jenis']; ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-gray-600">
                                    <span class="bg-amber-50 px-2.5 py-1 rounded-lg text-amber-600 text-xs font-medium">
                                        <?php echo $arsip['tahun']; ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5">
                                    <div class="flex items-center justify-center gap-1 sm:gap-1.5">
                                        <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" target="_blank" 
                                           class="p-1.5 rounded-lg text-amber-500 hover:text-amber-700 hover:bg-amber-50 transition-all duration-200" 
                                           title="Lihat/Download">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                        </a>
                                        <button onclick="if(confirm('Yakin ingin menghapus arsip ini?')) { document.getElementById('deleteArsip_<?php echo $arsip['id']; ?>').submit(); }" 
                                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-all duration-200" 
                                                title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <form id="deleteArsip_<?php echo $arsip['id']; ?>" method="POST" style="display:none;">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $arsip['id']; ?>">
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 text-amber-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                    </svg>
                                    <span class="text-gray-400 font-medium">Belum ada arsip</span>
                                    <span class="text-sm text-gray-300 mt-1">Klik tombol Upload Arsip untuk menambahkan</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="px-4 sm:px-6 py-4 border-t border-amber-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gradient-to-r from-amber-50/40 to-orange-50/40">
                <div class="text-sm text-gray-500">
                    Menampilkan <span class="font-medium text-gray-700"><?php echo $total > 0 ? $offset + 1 : 0; ?></span> - 
                    <span class="font-medium text-gray-700"><?php echo min($offset + $limit, $total); ?></span> 
                    dari <span class="font-medium text-gray-700"><?php echo $total; ?></span> data
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($page_num > 1): ?>
                        <a href="?page=<?php echo $page_num - 1; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($jenis_filter) ? '&jenis=' . urlencode($jenis_filter) : ''; ?><?php echo !empty($tahun_filter) ? '&tahun=' . urlencode($tahun_filter) : ''; ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-amber-100 text-amber-600 hover:bg-amber-200 hover:text-amber-800 transition-all duration-200">
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
                            <a href="?page=1&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($jenis_filter) ? '&jenis=' . urlencode($jenis_filter) : ''; ?><?php echo !empty($tahun_filter) ? '&tahun=' . urlencode($tahun_filter) : ''; ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl bg-amber-100 text-amber-600 hover:bg-amber-200 hover:text-amber-800 transition-all duration-200">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($jenis_filter) ? '&jenis=' . urlencode($jenis_filter) : ''; ?><?php echo !empty($tahun_filter) ? '&tahun=' . urlencode($tahun_filter) : ''; ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl transition-all duration-200 
                                   <?php echo $i == $page_num ? 'bg-gradient-to-r from-amber-500 to-orange-400 text-white shadow-md' : 'bg-amber-100 text-amber-600 hover:bg-amber-200 hover:text-amber-800'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($jenis_filter) ? '&jenis=' . urlencode($jenis_filter) : ''; ?><?php echo !empty($tahun_filter) ? '&tahun=' . urlencode($tahun_filter) : ''; ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl bg-amber-100 text-amber-600 hover:bg-amber-200 hover:text-amber-800 transition-all duration-200">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?page=<?php echo $page_num + 1; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($jenis_filter) ? '&jenis=' . urlencode($jenis_filter) : ''; ?><?php echo !empty($tahun_filter) ? '&tahun=' . urlencode($tahun_filter) : ''; ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-amber-100 text-amber-600 hover:bg-amber-200 hover:text-amber-800 transition-all duration-200">
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
    background: linear-gradient(180deg, #FDE68A 0%, #FFFBEB 70%, #FFFFFF 100%) !important;
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

#uploadModal > div {
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

<!-- Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 hidden transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-5 sm:p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-400 flex items-center justify-center shadow-lg shadow-amber-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Upload Arsip</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Tambahkan dokumen arsip baru</p>
                </div>
            </div>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" 
                    class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <input type="hidden" name="action" value="upload">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Judul Dokumen <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="judul" required 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400"
                               placeholder="Judul dokumen">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nomor Peraturan <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16"/>
                            </svg>
                        </div>
                        <input type="text" name="nomor_peraturan" required 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400"
                               placeholder="Nomor peraturan">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Jenis <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <select name="jenis" required 
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer appearance-none">
                            <option value="Perbup">Perbup</option>
                            <option value="Perdes">Perdes</option>
                            <option value="SK">SK</option>
                            <option value="Perda">Perda</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tahun <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <select name="tahun" required 
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer appearance-none">
                            <?php for ($y = date('Y'); $y >= 2015; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">File Dokumen <span class="text-rose-500">*</span></label>
                    <div class="relative group">
                        <input type="file" name="dokumen" accept=".pdf,.doc,.docx" required 
                               class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                               id="fileInput_upload">
                        <div class="w-full px-4 py-4 bg-amber-50/60 border-2 border-dashed border-amber-200 rounded-xl transition-all duration-200 group-hover:border-amber-400 group-hover:bg-amber-100/30 flex items-center gap-3 text-sm text-amber-800/70">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0 group-hover:bg-amber-200 transition-colors">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p id="fileNameDisplay_upload" class="font-medium truncate text-gray-700">Klik untuk pilih file</p>
                                <p class="text-[11px] text-gray-400">Format: PDF, DOC, DOCX (Maks. 10MB)</p>
                            </div>
                            <span class="text-xs font-medium text-amber-600 bg-amber-100 px-3 py-1 rounded-full shrink-0">Browse</span>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Keterangan <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <textarea name="keterangan" rows="2" 
                                  class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400 resize-none"
                                  placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" 
                        class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200">
                    Batal
                </button>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-400 hover:from-amber-600 hover:to-orange-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-amber-500/30 hover:shadow-amber-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Simpan Arsip
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('fileInput_upload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const display = document.getElementById('fileNameDisplay_upload');
    if (file) {
        display.textContent = file.name;
        display.classList.add('text-amber-700', 'font-semibold');
    } else {
        display.textContent = 'Klik untuk pilih file';
        display.classList.remove('text-amber-700', 'font-semibold');
    }
});
</script>

<style>
#uploadModal > div::-webkit-scrollbar { 
    display: none;
}
#uploadModal > div {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>