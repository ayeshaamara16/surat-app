<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin', 'admin', 'admin_nota'])) {
    die('Akses ditolak');
}

require_once __DIR__ . '/config/database.php';

$id = $_GET['id'] ?? 0;
if (!$id) {
    die('ID Nota tidak valid');
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM nota_pengajuan WHERE id = ?");
    $stmt->execute([$id]);
    $nota = $stmt->fetch();
    
    if (!$nota) {
        die('Data nota tidak ditemukan');
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

$title = 'Preview Nota - Sistem Manajemen Surat';
$page = 'nota';
include 'layout/header.php';
?>

<?php include 'layout/navbar.php'; ?>

<div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 max-w-7xl">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/30 shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <span class="bg-gradient-to-r from-emerald-600 to-cyan-500 bg-clip-text text-transparent">Preview Nota</span>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 ml-10 sm:ml-12">Nota Pengajuan Konsep Naskah Dinas</p>
        </div>
        <a href="nota.php" 
   class="inline-flex items-center gap-2 px-4 py-2 sm:px-5 sm:py-2.5 bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-600 hover:to-teal-500 text-white rounded-2xl text-xs sm:text-sm font-medium transition-all duration-300 shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:-translate-y-0.5 justify-center">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Kembali
</a>
    </div>
    
    <!-- Preview Card -->
    <div class="preview-card bg-white rounded-2xl shadow-lg border border-emerald-100 overflow-hidden hover:shadow-xl transition-all duration-500">
        <div class="p-4 sm:p-6">
            <!-- Grid 2 Kolom -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Konsep Naskah -->
                <div class="bg-emerald-50/50 rounded-xl p-3">
                    <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Konsep Naskah</label>
                    <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($nota['konsep_naskah'] ?? '-'); ?></p>
                </div>
                
                <!-- Kepada -->
                <div class="bg-emerald-50/50 rounded-xl p-3">
                    <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Kepada</label>
                    <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($nota['kepada'] ?? '-'); ?></p>
                </div>
                
                <!-- Tempat / Tanggal -->
                <div class="bg-emerald-50/50 rounded-xl p-3">
                    <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Tempat / Tanggal</label>
                    <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($nota['tempat_tgl'] ?? '-'); ?></p>
                </div>
                
                <!-- Lampiran -->
                <div class="bg-emerald-50/50 rounded-xl p-3">
                    <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Lampiran</label>
                    <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($nota['lampiran'] ?? '-'); ?></p>
                </div>
            </div>
            
            <!-- Tentang (Full width) -->
            <div class="mt-4 bg-emerald-50/50 rounded-xl p-3">
                <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Tentang</label>
                <div class="mt-1">
                    <?php 
                    $tentang_lines = array_filter(preg_split('/\r\n|\r|\n/', $nota['tentang'] ?? ''), fn($line) => trim($line) !== '');
                    if (!empty($tentang_lines)): 
                        foreach ($tentang_lines as $line): 
                    ?>
                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars(trim($line)); ?></p>
                    <?php endforeach; else: ?>
                        <p class="text-sm text-gray-400 italic"><?php echo htmlspecialchars($nota['tentang'] ?? '-'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- CATATAN DENGAN ICON PANAH -->
            <div class="mt-4 bg-amber-50/60 border border-amber-200/50 rounded-xl p-3">
                <label class="text-[10px] font-medium text-amber-600 uppercase tracking-wider flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Catatan
                    <span class="text-[10px] text-amber-400 font-normal">
                        (<?php 
                            $catatan_raw = $nota['catatan'] ?? '';
                            $catatan_lines = array_filter(preg_split('/\r\n|\r|\n/', $catatan_raw), fn($line) => trim($line) !== '');
                            $count = count($catatan_lines);
                            echo $count > 0 ? $count . ' catatan' : 'kosong';
                        ?>)
                    </span>
                </label>
                <div class="mt-1 space-y-1">
                    <?php 
                    if (!empty($catatan_lines)): 
                        foreach ($catatan_lines as $line): 
                    ?>
                        <div class="flex items-start gap-2.5 hover:bg-amber-100/50 -mx-1 px-2 py-1 rounded-lg transition-all duration-200">
                            <span class="text-amber-500 mt-0.5 text-sm">➤</span>
                            <span class="text-sm text-gray-700"><?php echo htmlspecialchars(trim($line)); ?></span>
                        </div>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <p class="text-sm text-gray-400 italic text-center py-2">Tidak ada catatan</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tanda Tangan -->
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-emerald-50/50 rounded-xl p-3">
                    <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Jabatan Penandatangan</label>
                    <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($nota['jabatan_penandatangan'] ?? '-'); ?></p>
                </div>
                
                <div class="bg-emerald-50/50 rounded-xl p-3">
                    <label class="text-[10px] font-medium text-emerald-600/70 uppercase tracking-wider">Nama Penandatangan</label>
                    <p class="text-sm font-semibold text-gray-800 mt-1"><?php echo htmlspecialchars($nota['nama_penandatangan'] ?? '-'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tombol Download -->
    <div class="mt-6 flex justify-center">
        <a href="generate_nota.php?action=download&id=<?php echo $nota['id']; ?>" 
           class="inline-flex items-center gap-3 px-6 py-3 sm:px-8 sm:py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-700 hover:to-emerald-600 text-white rounded-2xl text-sm sm:text-base font-medium transition-all duration-300 shadow-lg shadow-emerald-600/20 hover:shadow-xl hover:-translate-y-1">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Download Word
        </a>
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

/* Card putih solid */
.preview-card {
    background: #FFFFFF !important;
    background-image: none !important;
}
</style>

<?php include 'layout/footer.php'; ?>