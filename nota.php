<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin', 'admin_nota'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/csrf.php';
$title = 'Nota Pengajuan - Sistem Manajemen Surat';
$page = 'nota';
include 'layout/header.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Handle CRUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken('nota.php');
        $action = $_POST['action'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        if ($action === 'create') {
            $tempat = $_POST['tempat'] ?? 'Purwokerto';
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
            $tempat_tgl = $tempat . ', ' . date('d F Y', strtotime($tanggal));
            
            $kepada = $_POST['kepada'] ?? 'Yth. BUPATI BANYUMAS';
            $konsep_naskah = $_POST['konsep_naskah'] ?? 'NASKAH KEPUTUSAN BUPATI BANYUMAS';
            $tentang = $_POST['tentang'] ?? '';
            $catatan = $_POST['catatan'] ?? '';
            $jabatan_penandatangan = $_POST['jabatan_penandatangan'] ?? 'KEPALA BAGIAN HUKUM SETDA KABUPATEN BANYUMAS';
            $nama_penandatangan = $_POST['nama_penandatangan'] ?? 'ARIF ROHMAN, S.H.M.H.';
            $nip_penandatangan = $_POST['nip_penandatangan'] ?? '19750531 200903 1 002';
            $lampiran = $_POST['lampiran'] ?? '';
            
            $stmt = $db->prepare("INSERT INTO nota_pengajuan (tempat_tgl, kepada, konsep_naskah, tentang, catatan, jabatan_penandatangan, nama_penandatangan, nip_penandatangan, lampiran, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tempat_tgl, $kepada, $konsep_naskah, $tentang, $catatan, $jabatan_penandatangan, $nama_penandatangan, $nip_penandatangan, $lampiran, $user_id]);
            
            // LOG AKTIVITAS
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'create_nota', ?)");
            $log->execute([$user_id, "Menambah nota: $konsep_naskah - $tentang"]);
            
            $_SESSION['flash'] = ['type' => 'created', 'title' => 'Berhasil Ditambahkan', 'message' => 'Nota pengajuan baru berhasil dicatat.'];
            header('Location: nota.php');
            exit();
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $tempat = $_POST['tempat'] ?? 'Purwokerto';
            $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
            $tempat_tgl = $tempat . ', ' . date('d F Y', strtotime($tanggal));
            
            $kepada = $_POST['kepada'] ?? '';
            $konsep_naskah = $_POST['konsep_naskah'] ?? '';
            $tentang = $_POST['tentang'] ?? '';
            $catatan = $_POST['catatan'] ?? '';
            $jabatan_penandatangan = $_POST['jabatan_penandatangan'] ?? '';
            $nama_penandatangan = $_POST['nama_penandatangan'] ?? 'ARIF ROHMAN, S.H.M.H.';
            $nip_penandatangan = $_POST['nip_penandatangan'] ?? '19750531 200903 1 002';
            $lampiran = $_POST['lampiran'] ?? '';
            
            $stmt = $db->prepare("UPDATE nota_pengajuan SET tempat_tgl=?, kepada=?, konsep_naskah=?, tentang=?, catatan=?, jabatan_penandatangan=?, nama_penandatangan=?, nip_penandatangan=?, lampiran=? WHERE id=?");
            $stmt->execute([$tempat_tgl, $kepada, $konsep_naskah, $tentang, $catatan, $jabatan_penandatangan, $nama_penandatangan, $nip_penandatangan, $lampiran, $id]);
            
            // LOG AKTIVITAS
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'update_nota', ?)");
            $log->execute([$user_id, "Mengupdate nota ID: $id ($konsep_naskah - $tentang)"]);
            
            $_SESSION['flash'] = ['type' => 'updated', 'title' => 'Berhasil Diupdate', 'message' => 'Data nota berhasil diperbarui.'];
            header('Location: nota.php');
            exit();
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM nota_pengajuan WHERE id = ?");
            $stmt->execute([$id]);
            
            // LOG AKTIVITAS
            $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'delete_nota', ?)");
            $log->execute([$user_id, "Menghapus nota ID: $id"]);
            
            $_SESSION['flash'] = ['type' => 'deleted', 'title' => 'Berhasil Dihapus', 'message' => 'Nota telah dihapus permanen.'];
            header('Location: nota.php');
            exit();
        }
    }
    
    // Get all nota with pagination
    $search = $_GET['search'] ?? '';
    $page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $valid_limits = [10, 20, 50, 100];
    if (!in_array($limit, $valid_limits)) {
        $limit = 10;
    }
    
    $offset = ($page_num - 1) * $limit;
    
    $where = "";
    $params = [];
    if (!empty($search)) {
        $where = "WHERE tentang LIKE ? OR nama_penandatangan LIKE ? OR konsep_naskah LIKE ?";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param];
    }
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM nota_pengajuan $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);
    
    if ($page_num > $total_pages && $total_pages > 0) {
        $page_num = $total_pages;
        $offset = ($page_num - 1) * $limit;
    }
    
    // Get data
    $sql = "SELECT n.*, u.name as creator_name 
            FROM nota_pengajuan n 
            LEFT JOIN users u ON n.created_by = u.id 
            $where 
            ORDER BY n.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    
    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $notas = $stmt->fetchAll();
    
    // Get data for edit
    $edit_data = null;
    $tempat_edit = 'Purwokerto';
    $tanggal_edit = date('Y-m-d');
    if (isset($_GET['edit'])) {
        $stmt = $db->prepare("SELECT * FROM nota_pengajuan WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_data = $stmt->fetch();
        
        // Parse tempat_tgl jadi tempat & tanggal
        if ($edit_data && !empty($edit_data['tempat_tgl'])) {
            $parts = explode(', ', $edit_data['tempat_tgl'], 2);
            $tempat_edit = $parts[0] ?? 'Purwokerto';
            if (isset($parts[1])) {
                $ts = strtotime($parts[1]);
                if ($ts) $tanggal_edit = date('Y-m-d', $ts);
            }
        }
    }
    
} catch (PDOException $e) {
    $error = 'Gagal memuat data: ' . $e->getMessage();
    $notas = [];
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
                <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/30 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-emerald-600 to-cyan-500 bg-clip-text text-transparent">Nota Pengajuan</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-12">Buat dan kelola nota pengajuan konsep naskah dinas</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" 
                class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white rounded-2xl text-xs sm:text-sm font-medium transition-all duration-300 shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:-translate-y-0.5 w-full sm:w-auto justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Nota
        </button>
    </div>
    
    <!-- Search & Filter -->
    <div class="search-card bg-white rounded-2xl shadow-lg border border-emerald-100 p-4 sm:p-5 mb-6 hover:shadow-xl transition-all duration-500">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Cari nota..." class="w-full py-2.5 pl-11 pr-4 text-sm bg-emerald-50/50 border border-emerald-200/50 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none placeholder:text-gray-400 text-gray-700">
            </div>
            
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-500 font-medium">Tampilkan</label>
                <select name="limit" onchange="this.form.submit()" 
                        class="px-3 py-2.5 bg-emerald-50/50 border border-emerald-200/50 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 cursor-pointer">
                    <?php foreach ([10, 20, 50, 100] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $limit == $opt ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-sm text-gray-400">data</span>
            </div>
            
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white rounded-xl text-sm font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Cari
            </button>
        </form>
    </div>
    
    <!-- Table -->
    <div class="table-card bg-white rounded-2xl shadow-lg border border-emerald-100 overflow-hidden hover:shadow-xl transition-all duration-500">
        <div class="overflow-x-auto">
            <!-- min-w diperbesar lagi jadi 1050px -->
            <table class="w-full min-w-[1050px]">
                <thead>
                    <tr class="bg-gradient-to-r from-emerald-50 via-white to-teal-50 border-b border-emerald-100">
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">No</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">Konsep Naskah</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">Tentang</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider hidden sm:table-cell">Penandatangan</th>
                        <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider hidden md:table-cell">Dibuat</th>
                        <th class="px-4 sm:px-6 py-3.5 text-center text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-emerald-100/40">
                    <?php if (!empty($notas) && is_array($notas)): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach ($notas as $nota): ?>
                            <tr class="hover:bg-emerald-50/50 transition-all duration-200 group">
                                <td class="px-4 sm:px-6 py-3.5 text-sm font-semibold text-gray-800"><?php echo $no++; ?></td>
                                <!-- KOLOM KONSEP NASKAH: max-w 320px, substr 70 -->
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-emerald-700 max-w-[300px] truncate" title="<?php echo htmlspecialchars($nota['konsep_naskah']); ?>">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-5 h-5 rounded-full bg-gradient-to-br from-emerald-400 to-teal-400 flex items-center justify-center text-white text-[7px] font-bold shrink-0">KN</span>
                                        <?php echo htmlspecialchars(substr($nota['konsep_naskah'], 0, 70)) . (strlen($nota['konsep_naskah']) > 70 ? '...' : ''); ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-gray-600 max-w-[120px] sm:max-w-[200px] truncate" title="<?php echo htmlspecialchars($nota['tentang']); ?>">
                                    <?php echo htmlspecialchars(substr($nota['tentang'], 0, 35)) . (strlen($nota['tentang']) > 35 ? '...' : ''); ?>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-gray-600 hidden sm:table-cell">
                                    <?php echo htmlspecialchars($nota['nama_penandatangan']); ?>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5 text-sm text-gray-400 hidden md:table-cell whitespace-nowrap">
                                    <span class="inline-block bg-emerald-50 px-2.5 py-1 rounded-lg text-emerald-600 text-[10px] sm:text-xs whitespace-nowrap">
                                        <?php echo date('d/m/Y H:i', strtotime($nota['created_at'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-3.5">
                                    <div class="flex items-center justify-center gap-1 sm:gap-1.5">
                                        <a href="preview_nota.php?id=<?php echo $nota['id']; ?>" target="_blank" 
                                           class="p-1 sm:p-1.5 rounded-lg text-emerald-500 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-200" 
                                           title="Lihat / Generate Nota">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </a>
                                        <a href="?edit=<?php echo $nota['id']; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                           class="p-1 sm:p-1.5 rounded-lg text-blue-500 hover:text-blue-700 hover:bg-blue-50 transition-all duration-200" 
                                           title="Edit">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button onclick="if(confirm('Yakin ingin menghapus nota ini?')) { document.getElementById('deleteNota_<?php echo $nota['id']; ?>').submit(); }" 
                                                class="p-1 sm:p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-all duration-200" 
                                                title="Hapus">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                        <form id="deleteNota_<?php echo $nota['id']; ?>" method="POST" style="display:none;">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>"> 
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $nota['id']; ?>">
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 text-emerald-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span class="text-gray-400 font-medium">Belum ada nota</span>
                                    <span class="text-sm text-gray-300 mt-1">Klik tombol Tambah Nota untuk membuat</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="px-4 sm:px-6 py-4 border-t border-emerald-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gradient-to-r from-emerald-50/40 to-teal-50/40">
                <div class="text-sm text-gray-500">
                    Menampilkan <span class="font-medium text-gray-700"><?php echo $total > 0 ? $offset + 1 : 0; ?></span> - 
                    <span class="font-medium text-gray-700"><?php echo min($offset + $limit, $total); ?></span> 
                    dari <span class="font-medium text-gray-700"><?php echo $total; ?></span> data
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($page_num > 1): ?>
                        <a href="?page=<?php echo $page_num - 1; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 hover:text-emerald-800 transition-all duration-200">
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
                            <a href="?page=1&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 hover:text-emerald-800 transition-all duration-200">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl transition-all duration-200 
                                   <?php echo $i == $page_num ? 'bg-gradient-to-r from-emerald-500 to-teal-400 text-white shadow-md' : 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200 hover:text-emerald-800'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="px-2 py-1.5 text-sm text-gray-400">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                               class="px-3.5 py-1.5 text-sm font-medium rounded-xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 hover:text-emerald-800 transition-all duration-200">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?page=<?php echo $page_num + 1; ?>&limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-emerald-100 text-emerald-600 hover:bg-emerald-200 hover:text-emerald-800 transition-all duration-200">
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

<!-- ============================================ -->
<!-- BACKGROUND HIJAU GRADIENT VERTIKAL - NOTA    -->
<!-- ============================================ -->
<style>
html {
    background: linear-gradient(180deg, #A7F3D0 0%, #ECFDF5 70%, #FFFFFF 100%) !important;
    background-attachment: fixed !important;
    background-repeat: no-repeat !important;
    min-height: 100vh;
}
body, #mainContent, main, main > div, main > div > div {
    background: transparent !important;
    background-image: none !important;
}

/* Paksa card search & tabel jadi putih solid */
.search-card,
.table-card {
    background: #FFFFFF !important;
    background-image: none !important;
}

/* Modal - putih solid juga */
#createModal > div,
#editModal > div {
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
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Tambah Nota Pengajuan</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Buat nota konsep naskah dinas baru</p>
                </div>
            </div>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" 
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
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tempat <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <input type="text" name="tempat" value="Purwokerto" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Tempat">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tanggal <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Kepada <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="kepada" value="Yth. BUPATI BANYUMAS" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Tujuan nota">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Konsep Naskah <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="konsep_naskah" value="NASKAH KEPUTUSAN BUPATI BANYUMAS" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="Jenis konsep naskah">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tentang <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <textarea name="tentang" rows="3" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400 resize-none" placeholder="1. TAMBAHAN APRESIASI KERJA ...&#10;2. INSENTIF PEMUNGUTAN ..."></textarea>
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Catatan <span class="text-gray-400 font-normal normal-case">(opsional, satu poin per baris)</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <textarea name="catatan" rows="3" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400 resize-none" placeholder="- Substansi naskah telah dikoordinasikan...&#10;- Tata naskah telah dikoreksi..."></textarea>
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Jabatan Penandatangan <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <input type="text" name="jabatan_penandatangan" value="KEPALA BAGIAN HUKUM SETDA KABUPATEN BANYUMAS" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nama Penandatangan</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" name="nama_penandatangan" value="ARIF ROHMAN, S.H.M.H." required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">NIP Penandatangan</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                            </svg>
                        </div>
                        <input type="text" name="nip_penandatangan" value="19750531 200903 1 002" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Lampiran <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <input type="text" name="lampiran" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="1 (satu) berkas">
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200">Batal</button>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan Nota
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($edit_data): ?>
<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 transition-all duration-300 p-3 sm:p-6">
    <div class="bg-white rounded-3xl p-5 sm:p-6 w-full max-w-lg sm:max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 animate-modal">
        
        <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-base font-bold text-gray-800 leading-tight">Edit Nota Pengajuan</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Perbarui data nota yang sudah dibuat</p>
                </div>
            </div>
            <a href="nota.php?limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-200">
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
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tempat <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <input type="text" name="tempat" value="<?php echo htmlspecialchars($tempat_edit); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tanggal <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="date" name="tanggal" value="<?php echo $tanggal_edit; ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Kepada <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="kepada" value="<?php echo htmlspecialchars($edit_data['kepada']); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Konsep Naskah <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <input type="text" name="konsep_naskah" value="<?php echo htmlspecialchars($edit_data['konsep_naskah']); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Tentang <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </div>
                        <textarea name="tentang" rows="3" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 resize-none"><?php echo htmlspecialchars($edit_data['tentang']); ?></textarea>
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Catatan <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <textarea name="catatan" rows="3" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 resize-none"><?php echo htmlspecialchars($edit_data['catatan']); ?></textarea>
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Jabatan Penandatangan <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <input type="text" name="jabatan_penandatangan" value="<?php echo htmlspecialchars($edit_data['jabatan_penandatangan']); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Nama Penandatangan</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" name="nama_penandatangan" value="<?php echo htmlspecialchars($edit_data['nama_penandatangan'] ?? 'ARIF ROHMAN, S.H.M.H.'); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">NIP Penandatangan</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                            </svg>
                        </div>
                        <input type="text" name="nip_penandatangan" value="<?php echo htmlspecialchars($edit_data['nip_penandatangan'] ?? '19750531 200903 1 002'); ?>" required class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700">
                    </div>
                </div>
                
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Lampiran <span class="text-gray-400 font-normal normal-case">(opsional)</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <input type="text" name="lampiran" value="<?php echo htmlspecialchars($edit_data['lampiran']); ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-400/50 focus:border-emerald-400 focus:bg-white transition-all duration-200 outline-none text-sm text-gray-700 placeholder:text-gray-400" placeholder="1 (satu) berkas">
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3 flex-col-reverse sm:flex-row">
                <a href="nota.php?limit=<?php echo $limit; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-sm font-medium transition-all duration-200 text-center">Batal</a>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white rounded-xl text-sm font-medium transition-all duration-200 shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update Nota
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

#createModal > div::-webkit-scrollbar, 
#editModal > div::-webkit-scrollbar {
    display: none;
}

#createModal > div, 
#editModal > div {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>