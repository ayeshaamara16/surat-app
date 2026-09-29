<?php
// =====================================================
// MAPPING WARNA & AVATAR ROLE UNTUK NAVBAR
// =====================================================
$nav_role = $_SESSION['role'] ?? 'staff';

$nav_role_colors = [
    'superadmin'  => 'text-slate-800',
    'admin_surat' => 'text-blue-600',
    'admin_nota'  => 'text-emerald-600',
    'admin_arsip' => 'text-amber-600',
];
$nav_role_color = $nav_role_colors[$nav_role] ?? 'text-gray-800';

$nav_avatar_class = [
    'superadmin'  => 'avatar-slate',
    'admin'       => 'avatar-slate',
    'admin_surat' => 'avatar-blue',
    'admin_nota'  => 'avatar-emerald',
    'admin_arsip' => 'avatar-amber',
][$nav_role] ?? 'avatar-slate';

// =====================================================
// LABEL ROLE UNTUK DITAMPILKAN DI NAVBAR
// =====================================================
$nav_role_labels = [
    'superadmin'  => 'Superadmin',
    'admin_surat' => 'Admin Surat',
    'admin_nota'  => 'Admin Nota',
    'admin_arsip' => 'Admin Arsip',
];
$nav_role_label = $nav_role_labels[$nav_role] ?? ucfirst($nav_role);
?>

<!-- 
  ========================================
  NAVBAR & SIDEBAR - COLOR CODED PROFESSIONAL
  Warna per Fitur: Hitam, Biru, Hijau, Kuning
  ========================================
-->

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 z-30 transition-all duration-400 ease-[cubic-bezier(0.4,0,0.2,1)] flex flex-col bg-white border-r border-gray-100 shadow-2xl -translate-x-full">
    
    <!-- Logo -->
    <div class="flex items-center gap-3 px-6 h-16 border-b border-gray-100 shrink-0">
        <div>
            <span class="font-bold text-gray-800 text-lg tracking-tight">Surat<span class="logo-text-accent">App</span></span>
            <p class="text-[10px] text-gray-400 font-medium tracking-wider -mt-0.5">MANAGEMENT SYSTEM</p>
        </div>
        <button onclick="closeSidebar()" class="ml-auto lg:hidden p-1.5 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-6 overflow-hidden">
        <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-[0.15em] mb-3">Main Menu</p>
        
        <!-- Dashboard -->
        <a href="index.php" class="nav-item <?php echo ($page ?? '') === 'dashboard' ? 'active active-dashboard' : ''; ?>">
            <span class="nav-icon-wrapper">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </span>
            <span class="nav-text">Dashboard</span>
            <?php if (($page ?? '') === 'dashboard'): ?>
                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-slate-600 shrink-0"></span>
            <?php endif; ?>
        </a>
        
        <!-- Surat Masuk -->
        <?php if (checkRole(['superadmin', 'admin_surat'])): ?>
        <a href="surat.php" class="nav-item <?php echo ($page ?? '') === 'surat' ? 'active active-surat' : ''; ?>">
            <span class="nav-icon-wrapper icon-surat">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </span>
            <span class="nav-text">Surat Masuk</span>
            <?php if (($page ?? '') === 'surat'): ?>
                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <!-- Nota Pengajuan -->
        <?php if (checkRole(['superadmin', 'admin_nota'])): ?>
        <a href="nota.php" class="nav-item <?php echo ($page ?? '') === 'nota' ? 'active active-nota' : ''; ?>">
            <span class="nav-icon-wrapper icon-nota">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </span>
            <span class="nav-text">Nota Pengajuan</span>
            <?php if (($page ?? '') === 'nota'): ?>
                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Arsip Dokumen -->
        <?php if (checkRole(['superadmin', 'admin_arsip'])): ?>
        <a href="arsip.php" class="nav-item <?php echo ($page ?? '') === 'arsip' ? 'active active-arsip' : ''; ?>">
            <span class="nav-icon-wrapper icon-arsip">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
            </span>
            <span class="nav-text">Arsip Dokumen</span>
            <?php if (($page ?? '') === 'arsip'): ?>
                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <!-- Kelola Pengguna -->
        <?php if (checkRole(['superadmin'])): ?>
        <a href="users.php" class="nav-item <?php echo ($page ?? '') === 'users' ? 'active active-dashboard' : ''; ?>" title="Manajemen Pengguna">
            <span class="nav-icon-wrapper">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </span>
            <span class="nav-text">Kelola Pengguna</span>
            <?php if (($page ?? '') === 'users'): ?>
                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-slate-600 shrink-0"></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
        <div class="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent my-4"></div>
        
        <p class="px-3 text-[11px] font-bold text-gray-400 uppercase tracking-[0.15em] mb-3">Account</p>
        
        <a href="profile.php" class="nav-item <?php echo ($page ?? '') === 'profile' ? 'active active-dashboard' : ''; ?>">
            <span class="nav-icon-wrapper">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </span>
            <span class="nav-text">Profil Saya</span>
            <?php if (($page ?? '') === 'profile'): ?>
                <span class="ml-auto w-1.5 h-1.5 rounded-full bg-slate-600 shrink-0"></span>
            <?php endif; ?>
        </a>
        
        <a href="auth/logout.php" class="nav-item nav-item-logout">
            <span class="nav-icon-wrapper">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </span>
            <span class="nav-text">Keluar</span>
        </a>
    </nav>
</aside>

<!-- OVERLAY -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/20 backdrop-blur-sm z-20 hidden transition-all duration-300" onclick="closeSidebar()"></div>

<!-- NAVBAR ATAS -->
<nav class="fixed top-0 left-0 right-0 z-40 bg-white border-b border-gray-100 shadow-sm">
    <div class="px-6 sm:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Left -->
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-all duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                
                <a href="index.php" class="lg:hidden flex items-center gap-2">
                    <span class="font-bold text-gray-800 text-lg">Surat<span class="logo-text-accent">App</span></span>
                </a>
                
                <span class="hidden lg:flex items-center gap-2 text-sm text-gray-400">
                    <span class="text-gray-300">/</span>
                    <span class="font-medium text-gray-700">Dashboard</span>
                </span>
            </div>
            
            <!-- Center Breadcrumb -->
            <div class="hidden lg:flex flex-1 items-center px-4">
                <nav class="flex items-center gap-2 text-sm" aria-label="Breadcrumb">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="text-gray-300">/</span>
                    <a href="index.php" class="text-gray-500 hover:text-gray-700 transition hover:bg-gray-50 px-2 py-1 rounded-lg">Home</a>
                    <?php 
                    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
                    if ($currentPage != 'index'): 
                        $pageColors = [
                            'surat' => 'text-blue-600',
                            'nota' => 'text-emerald-600',
                            'arsip' => 'text-amber-600',
                            'users' => 'text-slate-600',
                            'profile' => 'text-slate-600'
                        ];
                        $colorClass = $pageColors[$currentPage] ?? 'text-slate-600';
                        
                        // Label breadcrumb dalam Bahasa Indonesia
                        $pageLabels = [
                            'surat'   => 'Surat Masuk',
                            'nota'    => 'Nota Pengajuan',
                            'arsip'   => 'Arsip Dokumen',
                            'users'   => 'Kelola Pengguna',
                            'profile' => 'Profil Saya',
                        ];
                        $pageLabel = $pageLabels[$currentPage] ?? ucfirst(str_replace('_', ' ', $currentPage));
                    ?>
                        <span class="text-gray-300">/</span>
                        <span class="<?php echo $colorClass; ?> font-semibold"><?php echo $pageLabel; ?></span>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- Right User Info -->
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-3 p-1 rounded-xl hover:bg-gray-50 transition-all duration-200 cursor-default">
                    <div class="w-8 h-8 rounded-xl <?php echo $nav_avatar_class; ?> flex items-center justify-center text-white text-xs font-bold">
                        <?php echo strtoupper(substr($nav_role_label, 0, 2)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-semibold leading-none <?php echo $nav_role_color; ?>">
                            <?php echo htmlspecialchars($nav_role_label); ?>
                        </p>
                        <p class="text-[11px] text-gray-400 leading-none mt-0.5">
                            <?php echo ucfirst($_SESSION['role'] ?? 'Staff'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================================== -->
<!-- CONTENT WRAPPER - TANPA BACKGROUND (BIAR BODY GRADIENT TEMBUS) -->
<!-- ============================================================== -->
<div class="min-h-screen flex flex-col lg:pl-0 transition-all duration-400 ease-[cubic-bezier(0.4,0,0.2,1)]" id="mainContent">
    
    <!-- SPACER NAVBAR FIXED -->
    <div class="h-16 shrink-0"></div>
    
    <!-- KONTEN UTAMA -->
    <main class="flex-1 w-full">

<style>
    /* Akhiran "App" - warna abu netral seperti di login */
    .logo-text-accent {
        color: #94A3B8;
    }
    
    /* ============ AVATAR PER ROLE ============ */
    .avatar-slate {
        background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
    }
    .avatar-blue {
        background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }
    .avatar-emerald {
        background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    .avatar-amber {
        background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    .avatar-rose {
        background: linear-gradient(135deg, #F43F5E 0%, #E11D48 100%);
        box-shadow: 0 4px 12px rgba(244, 63, 94, 0.3);
    }
    
    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #6b7280;
        border-radius: 0.75rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        cursor: pointer;
        position: relative;
        margin-bottom: 2px;
    }
    
    .nav-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
        min-width: 0;
    }
    
    .nav-item:hover {
        background: #f3f4f6;
        color: #1f2937;
        transform: translateX(4px);
    }
    
    .icon-surat .nav-icon { color: #93C5FD; }
    .nav-item:hover .icon-surat .nav-icon { color: #3B82F6; }
    
    .icon-nota .nav-icon { color: #6EE7B7; }
    .nav-item:hover .icon-nota .nav-icon { color: #10B981; }
    
    .icon-arsip .nav-icon { color: #FCD34D; }
    .nav-item:hover .icon-arsip .nav-icon { color: #F59E0B; }
    
    .nav-item.active-dashboard {
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
        color: #0f172a;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }
    .nav-item.active-dashboard::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 28px;
        background: linear-gradient(180deg, #475569, #0f172a);
        border-radius: 0 6px 6px 0;
        box-shadow: 0 0 20px rgba(15, 23, 42, 0.3);
    }
    .nav-item.active-dashboard .nav-icon-wrapper {
        color: #0f172a;
    }
    
    .nav-item.active-surat {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: #1e40af;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.12);
    }
    .nav-item.active-surat::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 28px;
        background: linear-gradient(180deg, #60a5fa, #3b82f6);
        border-radius: 0 6px 6px 0;
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.4);
    }
    .nav-item.active-surat .nav-icon-wrapper {
        color: #2563eb;
    }
    
    .nav-item.active-nota {
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        color: #065f46;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.12);
    }
    .nav-item.active-nota::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 28px;
        background: linear-gradient(180deg, #34d399, #10b981);
        border-radius: 0 6px 6px 0;
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
    }
    .nav-item.active-nota .nav-icon-wrapper {
        color: #059669;
    }
    
    .nav-item.active-arsip {
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        color: #92400e;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.12);
    }
    .nav-item.active-arsip::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 28px;
        background: linear-gradient(180deg, #fbbf24, #f59e0b);
        border-radius: 0 6px 6px 0;
        box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
    }
    .nav-item.active-arsip .nav-icon-wrapper {
        color: #d97706;
    }
    
    .nav-item-logout { color: #ef4444; }
    .nav-item-logout:hover { background: #fef2f2; color: #dc2626; }
    .nav-item-logout .nav-icon-wrapper { color: #f87171; }
    .nav-item-logout:hover .nav-icon-wrapper { color: #dc2626; }
    
    .nav-icon-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.25rem;
        height: 1.25rem;
        flex-shrink: 0;
        color: #94a3b8;
        transition: color 0.2s ease;
    }
    
    .nav-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    
    .nav-item:hover .nav-icon-wrapper {
        color: #1f2937;
    }
    
    #sidebar.open {
        transform: translateX(0) !important;
    }
    
    #mainContent.shifted {
        padding-left: 16rem;
    }
    
    .backdrop-blur-sm {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const mainContent = document.getElementById('mainContent');
    const isDesktop = window.innerWidth >= 1024;
    
    sidebar.classList.toggle('open');
    
    if (sidebar.classList.contains('open')) {
        if (!isDesktop) {
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        if (isDesktop) {
            mainContent.classList.add('shifted');
        }
    } else {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
        if (isDesktop) {
            mainContent.classList.remove('shifted');
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const mainContent = document.getElementById('mainContent');
    const isDesktop = window.innerWidth >= 1024;
    
    sidebar.classList.remove('open');
    overlay.classList.add('hidden');
    document.body.style.overflow = '';
    if (isDesktop) {
        mainContent.classList.remove('shifted');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSidebar();
    }
});

let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const mainContent = document.getElementById('mainContent');
        const isDesktop = window.innerWidth >= 1024;
        
        if (isDesktop) {
            sidebar.classList.add('open');
            mainContent.classList.add('shifted');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.remove('open');
            mainContent.classList.remove('shifted');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }, 100);
});

(function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const isDesktop = window.innerWidth >= 1024;
    
    if (isDesktop) {
        sidebar.classList.add('open');
        mainContent.classList.add('shifted');
    }
})();
</script>