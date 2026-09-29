<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Sistem Manajemen Surat - Kabupaten Banyumas'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php
    // ============================================
    // TENTUKAN BACKGROUND BERDASARKAN HALAMAN AKTIF
    // ============================================
    $page = $page ?? 'dashboard';
    
    $backgrounds = [
        'dashboard' => 'linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 50%, #F8FAFC 100%)', // Slate lembut
        'surat'     => 'linear-gradient(180deg, #BFDBFE 0%, #EFF6FF 40%, #FFFFFF 100%)',
        'nota'      => 'linear-gradient(135deg, #F8FAFC 0%, #ECFDF5 50%, #F8FAFC 100%)', // Hijau lembut
        'arsip'     => 'linear-gradient(135deg, #F8FAFC 0%, #FFFBEB 50%, #F8FAFC 100%)', // Kuning lembut
        'users'     => 'linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 50%, #F8FAFC 100%)',
        'profile'   => 'linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 50%, #F8FAFC 100%)',
    ];
    
    $bg_gradient = $backgrounds[$page] ?? $backgrounds['dashboard'];
    ?>
    
    <style>
    * { 
        font-family: 'Inter', sans-serif; 
    }
    
    /* ============================================
       BACKGROUND GRADIENT DINAMIS PER HALAMAN
       ============================================ */
    html {
        background: <?php echo $bg_gradient; ?> !important;
        background-attachment: fixed !important;
        min-height: 100vh;
    }
    
    body {
        background: transparent !important;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        font-size: 1rem !important;
        line-height: 1.5;
    }
    
    /* Semua wrapper konten transparan */
    #mainContent,
    #mainContent > main,
    #mainContent > main > div,
    main,
    main > div,
    main > div > div {
        background-color: transparent !important;
        background-image: none !important;
    }
    
    .kop-surat {
        border-bottom: 3px solid #000;
        border-top: 1px solid #000;
        padding: 10px 0;
        text-align: center;
        font-family: 'Times New Roman', serif;
        background: white;
    }
    .kop-surat h1 { font-size: 18px; font-weight: bold; margin: 0; letter-spacing: 2px; }
    .kop-surat h2 { font-size: 16px; font-weight: bold; margin: 0; letter-spacing: 1px; }
    .kop-surat p { font-size: 12px; margin: 2px 0; }
    
    .sidebar-transition {
        transition: transform 0.3s ease-in-out;
    }
    
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    </style>
</head>
<body>
<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>