<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';

$title = 'Log Aktivitas - Sistem Manajemen Surat';
$page = 'log';
include 'layout/header.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $search = $_GET['search'] ?? '';
    $page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page_num - 1) * $limit;
    
    $where = "WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $where .= " AND (description LIKE ? OR action LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM aktivitas_log $where");
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);
    
    $sql = "SELECT l.*, u.name as user_name, u.username 
            FROM aktivitas_log l 
            LEFT JOIN users u ON l.user_id = u.id 
            $where 
            ORDER BY l.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Gagal memuat log: ' . $e->getMessage();
    $logs = [];
    $total = 0;
    $total_pages = 0;
}
?>

<?php include 'layout/navbar.php'; ?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
    <?php if (isset($error)): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 px-4 py-3 rounded-2xl mb-6 flex items-center gap-3 text-sm shadow-lg">
            <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-8 h-8 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-400 flex items-center justify-center shadow-lg shadow-purple-500/30 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-purple-600 to-indigo-600 bg-clip-text text-transparent">Log Aktivitas</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-12">Riwayat aktivitas pengguna sistem</p>
        </div>
    </div>
    
    <!-- Search -->
    <div class="search-card bg-white rounded-2xl shadow-lg border border-purple-100 p-4 sm:p-5 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <div class="flex-1 min-w-[200px] relative">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Cari aktivitas..." class="w-full py-2.5 pl-11 pr-4 text-sm bg-purple-50/50 border border-purple-200/50 rounded-xl focus:ring-2 focus:ring-purple-400/50 focus:border-purple-400 focus:bg-white transition-all duration-200 outline-none placeholder:text-gray-400 text-gray-700">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-400 hover:from-purple-600 hover:to-indigo-500 text-white rounded-xl text-sm font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Cari
            </button>
        </form>
    </div>
    
    <!-- Table -->
    <div class="table-card bg-white rounded-2xl shadow-lg border border-purple-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px]">
                <thead>
                    <tr class="bg-gradient-to-r from-purple-50 via-white to-indigo-50 border-b border-purple-100">
                        <th class="px-4 py-3.5 text-left text-xs font-semibold text-purple-600/70 uppercase tracking-wider">Waktu</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold text-purple-600/70 uppercase tracking-wider">User</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold text-purple-600/70 uppercase tracking-wider">Action</th>
                        <th class="px-4 py-3.5 text-left text-xs font-semibold text-purple-600/70 uppercase tracking-wider">Deskripsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-purple-100/40">
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-purple-50/50 transition-all duration-200">
                                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                    <span class="bg-purple-50 px-2.5 py-1 rounded-lg text-purple-600 text-xs font-medium">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span class="inline-flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-gradient-to-br from-purple-400 to-indigo-400 flex items-center justify-center text-white text-[8px] font-bold shrink-0">
                                            <?php echo strtoupper(substr($log['user_name'] ?? 'U', 0, 2)); ?>
                                        </span>
                                        <?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-block px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-purple-100 text-purple-700">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 max-w-[300px] truncate" title="<?php echo htmlspecialchars($log['description']); ?>">
                                    <?php echo htmlspecialchars($log['description']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-16 h-16 text-purple-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-gray-400 font-medium">Belum ada log aktivitas</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-4 sm:px-6 py-4 border-t border-purple-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gradient-to-r from-purple-50/40 to-indigo-50/40">
                <div class="text-sm text-gray-500">
                    Menampilkan <span class="font-medium text-gray-700"><?php echo $total > 0 ? $offset + 1 : 0; ?></span> - 
                    <span class="font-medium text-gray-700"><?php echo min($offset + $limit, $total); ?></span> 
                    dari <span class="font-medium text-gray-700"><?php echo $total; ?></span> data
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($page_num > 1): ?>
                        <a href="?page=<?php echo $page_num - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-purple-100 text-purple-600 hover:bg-purple-200 transition-all duration-200">‹</a>
                    <?php endif; ?>
                    
                    <span class="text-sm text-gray-500">Halaman <strong><?php echo $page_num; ?></strong> dari <strong><?php echo $total_pages; ?></strong></span>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?page=<?php echo $page_num + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                           class="px-3 py-1.5 text-sm font-medium rounded-xl bg-purple-100 text-purple-600 hover:bg-purple-200 transition-all duration-200">›</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
html {
    background: linear-gradient(180deg, #E9D5FF 0%, #F5F3FF 70%, #FFFFFF 100%) !important;
    background-attachment: fixed !important;
    background-repeat: no-repeat !important;
    min-height: 100vh;
}
body, #mainContent, main, main > div, main > div > div {
    background: transparent !important;
    background-image: none !important;
}
.search-card, .table-card {
    background: #FFFFFF !important;
    background-image: none !important;
}
</style>

<?php include 'layout/footer.php'; ?>