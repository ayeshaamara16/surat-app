<?php
require_once __DIR__ . '/middleware/auth_check.php';

$title = 'Dashboard - Sistem Manajemen Surat';
$page = 'dashboard';
include 'layout/header.php';

try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    // Statistik
    $total_surat = $db->query("SELECT COUNT(*) as count FROM surat_masuk")->fetch()['count'];
    $total_nota = $db->query("SELECT COUNT(*) as count FROM nota_pengajuan")->fetch()['count'];
    $total_arsip = $db->query("SELECT COUNT(*) as count FROM arsip_dokumen")->fetch()['count'];
    $total_users_all = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    
    $user_active = $total_users_all;
    
    // 4 terbaru (Nota Pengajuan)
    $recent_nota = $db->query("SELECT n.*, u.name as creator_name 
                               FROM nota_pengajuan n 
                               LEFT JOIN users u ON n.created_by = u.id 
                               ORDER BY n.created_at DESC LIMIT 4")->fetchAll();
    
    // =====================================================
    // QUERY DATA DISPOSISI UNTUK GRAFIK (DATA ASLI)
    // Sumber: tabel surat_masuk, kolom `dispo`
    // =====================================================
    $query_disposisi = $db->query("
        SELECT 
            CASE 
                WHEN dispo IS NULL OR dispo = '' THEN 'BELUM ISI'
                ELSE dispo 
            END AS penerima,
            COUNT(*) as total 
        FROM surat_masuk 
        GROUP BY penerima 
        ORDER BY total DESC
    ");
    $data_disposisi = $query_disposisi->fetchAll();
    
    // Pisahkan menjadi array label dan values untuk Chart.js
    $chart_labels = [];
    $chart_values = [];
    foreach ($data_disposisi as $row) {
        $chart_labels[] = $row['penerima'];
        $chart_values[] = (int)$row['total'];
    }
    
} catch (PDOException $e) {
    $error = 'Gagal memuat data: ' . $e->getMessage();
}

// =====================================================
// WARNA NAMA ROLE DI SAPAAN
// =====================================================
$role = $_SESSION['role'] ?? 'staff';
$role_colors = [
    'superadmin'  => 'text-slate-700',
    'admin_surat' => 'text-blue-600',
    'admin_nota'  => 'text-emerald-600',
    'admin_arsip' => 'text-amber-600',
];
$role_color = $role_colors[$role] ?? 'text-gray-700';

$role_labels = [
    'superadmin'  => 'Superadmin',
    'admin_surat' => 'Admin Surat',
    'admin_nota'  => 'Admin Nota',
    'admin_arsip' => 'Admin Arsip',
];
$role_label = $role_labels[$role] ?? ucfirst($role);
?>

<?php include 'layout/navbar.php'; ?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
    <?php if (isset($error)): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 text-rose-700 px-5 py-3.5 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-lg sm:text-2xl font-bold text-gray-800 flex items-center gap-2">
                <span class="w-7 h-7 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shadow-lg shadow-slate-700/30 shrink-0">
                    <svg class="w-3.5 h-3.5 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-slate-700 to-slate-500 bg-clip-text text-transparent">Dashboard</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-9 sm:ml-12">
                Selamat datang, <span class="font-semibold <?php echo $role_color; ?>"><?php echo htmlspecialchars($role_label); ?></span>
            </p>
        </div>
        
        <div class="date-card flex items-center gap-3 text-sm bg-white px-4 py-2 rounded-xl shadow-md border border-slate-100">
            <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-gray-700 font-medium"><?php 
                $hari_indo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                $bulan_indo = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                echo $hari_indo[date('w')] . ', ' . date('d') . ' ' . $bulan_indo[date('n')-1] . ' ' . date('Y');
            ?></span>
        </div>
    </div>
    
    <!-- STATISTIK -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
        
        <div class="group bg-gradient-to-br from-blue-500 to-cyan-400 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 p-6 border border-white/20 hover:-translate-y-1.5 hover:scale-[1.02] relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -ml-12 -mb-12"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-white/80 tracking-wide uppercase">Total Surat Masuk</p>
                        <h3 class="text-3xl font-bold text-white mt-1.5"><?php echo number_format($total_surat ?? 0); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/20">
                    <a href="surat.php" class="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-white hover:text-white/90 transition-colors group-hover:gap-3 duration-300">
                        Lihat semua surat
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="group bg-gradient-to-br from-emerald-500 to-teal-400 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 p-6 border border-white/20 hover:-translate-y-1.5 hover:scale-[1.02] relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -ml-12 -mb-12"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-white/80 tracking-wide uppercase">Total Nota Pengajuan</p>
                        <h3 class="text-3xl font-bold text-white mt-1.5"><?php echo number_format($total_nota ?? 0); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/20">
                    <a href="nota.php" class="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-white hover:text-white/90 transition-colors group-hover:gap-3 duration-300">
                        Lihat semua nota
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="group bg-gradient-to-br from-amber-500 to-orange-400 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 p-6 border border-white/20 hover:-translate-y-1.5 hover:scale-[1.02] relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -ml-12 -mb-12"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-white/80 tracking-wide uppercase">Total Arsip Dokumen</p>
                        <h3 class="text-3xl font-bold text-white mt-1.5"><?php echo number_format($total_arsip ?? 0); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/20">
                    <a href="arsip.php" class="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-white hover:text-white/90 transition-colors group-hover:gap-3 duration-300">
                        Lihat semua arsip
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="group bg-gradient-to-br from-slate-700 to-slate-900 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500 p-6 border border-white/20 hover:-translate-y-1.5 hover:scale-[1.02] relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full -ml-12 -mb-12"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-white/80 tracking-wide uppercase">Total Pengguna</p>
                        <h3 class="text-3xl font-bold text-white mt-1.5"><?php echo number_format($total_users_all ?? 0); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all shrink-0">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/20">
                    <span class="text-xs sm:text-sm text-white/80">
                        <span class="font-semibold"><?php echo $user_active; ?></span> user aktif
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- GRID UTAMA: KIRI 2 TABEL, KANAN USER + AKSI  -->
    <!-- ============================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- KOLOM KIRI: 2 Tabel (2/3 lebar) -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Grafik Disposisi (Data Asli dari tabel surat_masuk kolom `dispo`) -->
            <div class="dashboard-card bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden hover:shadow-xl transition-all duration-500">
                <div class="px-6 py-4 border-b border-blue-100 flex justify-between items-center bg-gradient-to-r from-blue-50 via-white to-cyan-50">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-md shadow-blue-400/30 shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </span>
                        <span class="bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">Grafik Disposisi</span>
                    </h3>
                    <a href="surat.php" class="text-xs sm:text-sm font-medium text-blue-600 hover:text-blue-700 transition-colors flex items-center gap-1.5 group bg-blue-50 px-3 py-1.5 rounded-xl hover:bg-blue-100">
                        Lihat Detail
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
                <div class="p-3 sm:p-4">
                    <?php if (empty($chart_labels)): ?>
                        <div class="flex flex-col items-center justify-center h-40 text-gray-400">
                            <svg class="w-10 h-10 text-blue-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="text-xs sm:text-sm">Belum ada data disposisi</span>
                        </div>
                    <?php else: ?>
                        <div class="relative h-48 sm:h-56 w-full">
                            <canvas id="chartDisposisi"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Nota Pengajuan Terbaru -->
            <div class="dashboard-card bg-white rounded-2xl shadow-lg border border-emerald-100 overflow-hidden hover:shadow-xl transition-all duration-500">
                <div class="px-6 py-4 border-b border-emerald-100 flex justify-between items-center bg-gradient-to-r from-emerald-50 via-white to-teal-50">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center shadow-md shadow-emerald-400/30 shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <span class="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">Nota Pengajuan Terbaru</span>
                    </h3>
                    <a href="nota.php" class="text-xs sm:text-sm font-medium text-emerald-600 hover:text-emerald-700 transition-colors flex items-center gap-1.5 group bg-emerald-50 px-3 py-1.5 rounded-xl hover:bg-emerald-100">
                        Lihat semua
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-emerald-50 to-teal-50">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">Konsep Naskah</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">Tentang</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-emerald-600/70 uppercase tracking-wider">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_nota)): ?>
                                <?php foreach ($recent_nota as $nota): ?>
                                    <tr class="border-t border-emerald-100/50 hover:bg-emerald-50/50 transition-all duration-200">
                                        <td class="px-6 py-3 max-w-[140px] truncate text-emerald-700 font-medium text-xs sm:text-sm" title="<?php echo htmlspecialchars($nota['konsep_naskah'] ?? 'NASKAH KEPUTUSAN BUPATI BANYUMAS'); ?>">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="w-6 h-6 rounded-full bg-gradient-to-br from-emerald-400 to-teal-400 flex items-center justify-center text-white text-[9px] font-bold shrink-0">
                                                    KN
                                                </span>
                                                <?php 
                                                    $konsep = $nota['konsep_naskah'] ?? 'NASKAH KEPUTUSAN BUPATI BANYUMAS';
                                                    echo htmlspecialchars(substr($konsep, 0, 25)) . (strlen($konsep) > 25 ? '...' : ''); 
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 max-w-[160px] truncate text-gray-700 text-xs sm:text-sm" title="<?php echo htmlspecialchars($nota['tentang']); ?>">
                                            <?php 
                                                $tentang = $nota['tentang'] ?? '';
                                                $tentang_first = explode("\n", $tentang)[0];
                                                echo htmlspecialchars(substr($tentang_first, 0, 35)) . (strlen($tentang_first) > 35 ? '...' : ''); 
                                            ?>
                                        </td>
                                        <td class="px-6 py-3 text-gray-400 text-[11px] sm:text-xs font-medium whitespace-nowrap">
                                            <span class="bg-emerald-50 px-2.5 py-1 rounded-lg text-emerald-600">
                                                <?php echo date('d/m/Y', strtotime($nota['created_at'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-12 h-12 text-emerald-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span class="text-gray-400 text-xs sm:text-sm">Belum ada data nota</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- KOLOM KANAN: User Info + Aksi Cepat (1/3 lebar) -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- User Info -->
            <div class="dashboard-card bg-white rounded-2xl shadow-lg border border-slate-100 p-5 hover:shadow-xl transition-all duration-500">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center text-white text-lg font-bold shadow-lg shadow-slate-700/30 shrink-0 relative">
                        <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 2)); ?>
                        <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-400 border-2 border-white rounded-full"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 text-base truncate"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></p>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                                Online
                            </span>
                            <span class="w-px h-3 bg-gray-200"></span>
                            <span class="px-2.5 py-0.5 text-[10px] font-semibold rounded-lg bg-slate-100 text-slate-700">
                                <?php echo ucfirst($_SESSION['role'] ?? 'staff'); ?>
                            </span>
                        </div>
                        <?php if (!empty($_SESSION['last_login'])): ?>
                            <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php echo date('d/m/Y H:i', strtotime($_SESSION['last_login'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <a href="profile.php" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Profil
                    </a>
                </div>
            </div>
            
            <!-- Aksi Cepat -->
            <div class="dashboard-card bg-white rounded-2xl shadow-lg border border-slate-100 p-5 hover:shadow-xl transition-all duration-500">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-7 h-7 rounded-lg bg-gradient-to-br from-slate-700 to-slate-900 flex items-center justify-center shadow-md shadow-slate-700/30 shrink-0">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-800">Aksi Cepat</h3>
                </div>
                
                <div class="space-y-2">
                    <a href="surat.php" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-blue-50 transition-colors group">
                        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center shadow-sm shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </span>
                        <div class="flex-1 text-left min-w-0">
                            <p class="text-xs font-semibold text-gray-800 group-hover:text-blue-600 transition-colors">Tambah Surat</p>
                            <p class="text-[10px] text-gray-400 truncate">Catat surat masuk baru</p>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-blue-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    
                    <a href="nota.php" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-emerald-50 transition-colors group">
                        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center shadow-sm shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </span>
                        <div class="flex-1 text-left min-w-0">
                            <p class="text-xs font-semibold text-gray-800 group-hover:text-emerald-600 transition-colors">Buat Nota</p>
                            <p class="text-[10px] text-gray-400 truncate">Buat nota pengajuan</p>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-emerald-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    
                    <a href="arsip.php" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-amber-50 transition-colors group">
                        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-400 flex items-center justify-center shadow-sm shrink-0">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </span>
                        <div class="flex-1 text-left min-w-0">
                            <p class="text-xs font-semibold text-gray-800 group-hover:text-amber-600 transition-colors">Upload Arsip</p>
                            <p class="text-[10px] text-gray-400 truncate">Tambah arsip dokumen</p>
                        </div>
                        <svg class="w-3.5 h-3.5 text-slate-300 group-hover:text-amber-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- BACKGROUND SLATE GRADIENT VERTIKAL - DASHBOARD -->
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

/* Paksa card-card dashboard jadi putih solid */
.dashboard-card,
.date-card {
    background: #FFFFFF !important;
    background-image: none !important;
}
</style>

<!-- ============================================ -->
<!-- SCRIPT CHART.JS UNTUK GRAFIK DISPOSISI       -->
<!-- ============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('chartDisposisi');
        if (!canvas) return; // Skip jika tidak ada data
        
        const ctx = canvas.getContext('2d');
        
        // Data asli dari database (di-passing dari PHP)
        const labels = <?php echo json_encode($chart_labels); ?>;
        const dataValues = <?php echo json_encode($chart_values); ?>;

        // ====== GRADIENT BIRU (setone dengan card "Total Surat Masuk") ======
        // from-blue-500 (#3B82F6) -> to-cyan-400 (#22D3EE)
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.95)');  // blue-500
        gradient.addColorStop(1, 'rgba(34, 211, 238, 0.75)');  // cyan-400

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Disposisi',
                    data: dataValues,
                    backgroundColor: gradient,                  // Gradient biru
                    borderColor: 'rgba(37, 99, 235, 1)',        // blue-600
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 13, family: 'system-ui' },
                        bodyFont: { size: 12, family: 'system-ui' },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Total: ' + context.parsed.y + ' surat';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(191, 219, 254, 0.5)',   // biru muda transparan
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 1,
                            color: '#3B82F6',                    // biru
                            font: { size: 11, weight: '600' },
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: '#2563EB',                    // biru lebih gelap
                            font: { size: 10, weight: '600' },
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    });
</script>

<?php include 'layout/footer.php'; ?>