<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin', 'admin', 'admin_surat'])) {
    die('Akses ditolak');
}

require_once __DIR__ . '/config/database.php';

// ============================================
// AMBIL PARAMETER
// ============================================
$format        = $_GET['format'] ?? 'pdf';
$id            = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$jenis_periode = $_GET['jenis_periode'] ?? '';
$tahun_filter  = $_GET['tahun'] ?? '';
// >>> REVISI: $jenis_rancangan dihapus

// ============================================
// BUILD QUERY WHERE
// ============================================
$where  = "WHERE 1=1";
$params = [];

if ($id > 0) {
    $where .= " AND s.id = ?";
    $params[] = $id;
} elseif ($jenis_periode === 'semua') {
    // Export semua data
} elseif ($jenis_periode === 'tanggal') {
    $start_date = $_GET['start_date'] ?? '';
    $end_date   = $_GET['end_date'] ?? '';
    if (!empty($start_date) && !empty($end_date)) {
        if (strtotime($start_date) > strtotime($end_date)) {
            die('<h3>Error: Tanggal mulai tidak boleh lebih besar dari tanggal akhir.</h3><a href="surat.php">Kembali</a>');
        }
        $where .= " AND tgl_masuk BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif (!empty($start_date)) {
        $where .= " AND tgl_masuk = ?";
        $params[] = $start_date;
    }
} elseif ($jenis_periode === 'bulan') {
    $start_month = $_GET['start_month'] ?? '';
    $end_month   = $_GET['end_month'] ?? '';
    if (!empty($start_month) && !empty($end_month)) {
        $start_date = $start_month . '-01';
        $end_date   = date('Y-m-t', strtotime($end_month . '-01'));
        if (strtotime($start_date) > strtotime($end_date)) {
            die('<h3>Error: Bulan mulai tidak boleh lebih besar dari bulan akhir.</h3><a href="surat.php">Kembali</a>');
        }
        $where .= " AND tgl_masuk BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif (!empty($start_month)) {
        $start_date = $start_month . '-01';
        $end_date   = date('Y-m-t', strtotime($start_month . '-01'));
        $where .= " AND tgl_masuk BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
} elseif ($jenis_periode === 'tahun') {
    if (!empty($tahun_filter)) {
        $where .= " AND YEAR(tgl_masuk) = ?";
        $params[] = $tahun_filter;
    }
}

// >>> REVISI: Filter jenis rancangan DIHAPUS

// ============================================
// AMBIL DATA
// ============================================
try {
    $db   = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT s.*, u.name as created_by_name 
                          FROM surat_masuk s 
                          LEFT JOIN users u ON s.created_by = u.id 
                          $where
                          ORDER BY s.tgl_masuk ASC, s.created_at ASC");
    $stmt->execute($params);
    $surats = $stmt->fetchAll();
    
    if (empty($surats)) {
        die('<h3>Tidak ada data pada periode tersebut.</h3><a href="surat.php">Kembali</a>');
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// ============================================
// FUNGSI TANGGAL INDONESIA
// ============================================
function tgl_indo($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', strtotime($tanggal));
    $bln = $bulan[(int)date('m', strtotime($tanggal))];
    $thn = date('Y', strtotime($tanggal));
    return $tgl . ' ' . $bln . ' ' . $thn;
}

// ============================================
// FUNGSI STATUS TERAKHIR
// ============================================
function getStatusTerakhir($surat) {
    $status_list = [
        'koreksi' => ['tgl' => $surat['tgl_koreksi'] ?? null, 'label' => 'KOREKSI'],
        'asmin'   => ['tgl' => $surat['tgl_asmin'] ?? null,   'label' => 'ASMIN'],
        'sekda'   => ['tgl' => $surat['tgl_sekda'] ?? null,   'label' => 'SEKDA'],
        'bupati'  => ['tgl' => $surat['tgl_bupati'] ?? null,  'label' => 'BUPATI'],
    ];
    $last_status = null;
    $last_date   = null;
    foreach ($status_list as $status) {
        if (!empty($status['tgl'])) {
            if ($last_date === null || strtotime($status['tgl']) > strtotime($last_date)) {
                $last_date   = $status['tgl'];
                $last_status = $status['label'];
            }
        }
    }
    if ($last_status && $last_date) {
        $d = (int)date('d', strtotime($last_date));
        $m = (int)date('m', strtotime($last_date));
        $y = date('Y', strtotime($last_date));
        return $d . '/' . $m . '/' . $y . ' ' . $last_status;
    }
    return '-';
}

// ============================================
// FUNGSI FILE URL (tidak dipakai, tapi disimpan)
// ============================================
function getFileUrl($file_path) {
    if (empty($file_path)) return '';
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . '://' . $host;
    $file_path = ltrim($file_path, '/');
    $script_name = $_SERVER['SCRIPT_NAME'];
    $script_dir  = dirname($script_name);
    if ($script_dir != '/' && $script_dir != '\\') {
        $base_url .= $script_dir . '/';
    } else {
        $base_url .= '/';
    }
    return $base_url . $file_path;
}

// ============================================
// FUNGSI CLEAN TEXT
// ============================================
function cleanText($text) {
    if (empty($text)) return '-';
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    return $text;
}

// ============================================
// FUNGSI JUDUL EXPORT (STATIS: DRAFT PRODUK HUKUM)
// ============================================
function getJudulExport() {
    // >>> REVISI: Judul statis, tidak lagi berdasarkan jenis rancangan
    return 'DRAFT PRODUK HUKUM';
}

$judul_export = getJudulExport();

// ============================================
// EXPORT EXCEL
// ============================================
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . str_replace(' ', '_', $judul_export) . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    echo "\xEF\xBB\xBF";
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
          xmlns:x="urn:schemas-microsoft-com:office:excel" 
          xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Draft Produk Hukum</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->';
    echo '<style>
        th {
            background-color: #C00000;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            padding: 5px;
            font-size: 10pt;
        }
        td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 10pt;
            vertical-align: top;
        }
        .judul {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
        }
        .sub-judul {
            font-size: 10pt;
            text-align: center;
            border: 1px solid #000;
        }
    </style>';
    echo '</head>';
    echo '<body>';
    
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
    
    // Judul
    echo '<tr><td colspan="9" class="judul">' . strtoupper($judul_export) . '</td></tr>';
    echo '<tr><td colspan="9" class="sub-judul">Purwokerto, ' . tgl_indo(date('Y-m-d')) . '</td></tr>';
    echo '<tr><td colspan="9" style="height:10px;border:1px solid #000;"></td></tr>';
    
    // Header
    echo '<tr>';
    echo '<th style="width:4%;">NOMOR</th>';
    echo '<th style="width:8%;">TANGGAL MASUK</th>';
    echo '<th style="width:8%;">TANGGAL SURAT</th>';
    echo '<th style="width:12%;">NOMOR SURAT</th>';
    echo '<th style="width:10%;">PENGIRIM</th>';
    echo '<th style="width:20%;">PERIHAL</th>';
    echo '<th style="width:8%;">DISPO</th>';
    echo '<th style="width:12%;">PROSES</th>';
    echo '<th style="width:18%;">KET</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($surats as $surat) {
        $status_terakhir = getStatusTerakhir($surat);
        $tgl_masuk = !empty($surat['tgl_masuk']) ? date('d/m/Y', strtotime($surat['tgl_masuk'])) : '-';
        $tgl_surat = !empty($surat['tanggal_surat']) ? date('d/m/Y', strtotime($surat['tanggal_surat'])) : '-';
        
        echo '<tr>';
        echo '<td style="text-align:center;">' . $no++ . '</td>';
        echo '<td style="text-align:center;">' . $tgl_masuk . '</td>';
        echo '<td style="text-align:center;">' . $tgl_surat . '</td>';
        echo '<td>' . htmlspecialchars($surat['nomor_surat'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($surat['pengirim'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($surat['perihal'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td style="text-align:center;">' . htmlspecialchars($surat['dispo'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $status_terakhir . '</td>';
        echo '<td>' . htmlspecialchars($surat['ket'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// ============================================
// FUNGSI LOGO BASE64
// ============================================
function getLogoBase64() {
    $possible_paths = [
        __DIR__ . '/assets/img/lambang-banyumas.png',
        __DIR__ . '/assets/img/Lambang_Kabupaten_Banyumas.png',
        __DIR__ . '/assets/img/logo.png',
        __DIR__ . '/assets/img/lambang.png',
        __DIR__ . '/assets/img/kabupaten_banyumas.png',
        __DIR__ . '/../assets/img/lambang-banyumas.png',
    ];
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return base64_encode(file_get_contents($path));
        }
    }
    return null;
}

$logo_base64 = getLogoBase64();

// ============================================
// BUILD HTML UNTUK PDF
// ============================================
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 10mm 10mm 10mm 10mm;
            size: A4 landscape;
        }
        
        body { 
            font-family: Arial, Helvetica, sans-serif; 
            margin: 0; 
            padding: 0;
            font-size: 9pt; 
            line-height: 1.3;
            color: #000;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            border: none;
        }
        
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        
        .header-logo {
            width: 65px; 
            text-align: center;
            padding-right: 8px;
        }
        
        .header-logo img {
            width: 65px; 
            height: auto;
        }
        
        .header-text {
            text-align: center;
            vertical-align: middle;
        }
        
        .header-text .kop-title { 
            font-size: 12pt; 
            font-weight: bold; 
            margin: 0; 
            letter-spacing: 0.5px;
        }
        .header-text .sub-title { 
            font-size: 12pt; 
            font-weight: bold; 
            margin: 0; 
            letter-spacing: 0.5px;
        }
        .header-text .alamat { 
            font-size: 7.5pt; 
            margin: 0; 
            line-height: 1.2; 
            text-align: center;
        }
        .header-text .alamat2 { 
            font-size: 8pt; 
            margin: 0; 
            line-height: 1.2;
            text-align: center;
        }
        .header-text .alamat3 { 
            font-size: 10pt; 
            margin: 0; 
            line-height: 1.2;
            text-align: center;
            font-weight: bold;
        }
        
        .garis-kop {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin-top: 4px;
            margin-bottom: 10px;
        }
        
        .tanggal-surat {
            text-align: right;
            font-size: 9pt;
            margin-bottom: 8px;
            margin-top: 3px;
        }
        
        .judul-laporan {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            margin-top: 5px; 
            margin-bottom: 10px; 
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        table.table-data { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 0;
            font-size: 7.5pt;
            font-family: Arial, sans-serif;
        }
        
        table.table-data th { 
            background: #C00000;
            color: #ffffff;
            border: 1px solid #000; 
            padding: 4px 3px; 
            text-align: center;
            font-weight: bold;
            font-size: 7.5pt;
            font-family: Arial, sans-serif;
        }
        
        table.table-data td { 
            border: 1px solid #000; 
            padding: 4px 3px; 
            font-size: 7.5pt;
            font-family: Arial, sans-serif;
            vertical-align: top;
        }
        
        table.table-data td.no {
            text-align: center;
            font-weight: bold;
        }
        
        table.table-data td.tanggal {
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    
    <!-- KOP SURAT -->
    <table class="header-table">
        <tr>';
            
if (!empty($logo_base64)) {
    $html .= '
            <td class="header-logo">
                <img src="data:image/png;base64,' . $logo_base64 . '" alt="Lambang Kabupaten Banyumas">
            </td>';
}

$html .= '
            <td class="header-text">
                <div class="kop-title">PEMERINTAH KABUPATEN BANYUMAS</div>
                <div class="sub-title">SEKRETARIAT DAERAH</div>
                <div class="alamat">Jl. Kabupaten No. 1 Telp. 635231, 636004, 636005, 636006 636266, 636079, 637009, 637823</div>
                <div class="alamat2">Telex. 25642 Faxcimile : 635332 (0281)</div>
                <div class="alamat3">PURWOKERTO 53115</div>
            </td>
        </tr>
    </table>
    <div class="garis-kop"></div>
    
    <!-- TANGGAL -->
    <div class="tanggal-surat">
        Purwokerto, ' . tgl_indo(date('Y-m-d')) . '
    </div>
    
    <!-- JUDUL -->
    <div class="judul-laporan">
        ' . strtoupper($judul_export) . '
    </div>
    
    <!-- TABEL -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width:4%;">NOMOR</th>
                <th style="width:8%;">TANGGAL MASUK</th>
                <th style="width:8%;">TANGGAL SURAT</th>
                <th style="width:12%;">NOMOR SURAT</th>
                <th style="width:10%;">PENGIRIM</th>
                <th style="width:20%;">PERIHAL</th>
                <th style="width:8%;">DISPO</th>
                <th style="width:12%;">PROSES</th>
                <th style="width:18%;">KET</th>
            </tr>
        </thead>
        <tbody>';

if (empty($surats)) {
    $html .= '<tr><td colspan="9" style="text-align:center;padding:20px;">Tidak ada data</td></tr>';
} else {
    $no = 1;
    foreach ($surats as $surat) {
        $nomor_surat  = cleanText($surat['nomor_surat'] ?? '-');
        $pengirim     = cleanText($surat['pengirim'] ?? '-');
        $perihal      = cleanText($surat['perihal'] ?? '-');
        $dispo        = cleanText($surat['dispo'] ?? '-');
        $ket          = cleanText($surat['ket'] ?? '-');
        $tgl_masuk    = !empty($surat['tgl_masuk']) ? date('d/m/Y', strtotime($surat['tgl_masuk'])) : '-';
        $tgl_surat    = !empty($surat['tanggal_surat']) ? date('d/m/Y', strtotime($surat['tanggal_surat'])) : '-';
        $status_akhir = getStatusTerakhir($surat);
        
        $html .= '<tr>
            <td class="no">' . $no++ . '</td>
            <td class="tanggal">' . $tgl_masuk . '</td>
            <td class="tanggal">' . $tgl_surat . '</td>
            <td>' . $nomor_surat . '</td>
            <td>' . $pengirim . '</td>
            <td>' . $perihal . '</td>
            <td style="text-align:center;">' . $dispo . '</td>
            <td>' . $status_akhir . '</td>
            <td>' . $ket . '</td>
        </tr>';
    }
}

$html .= '
        </tbody>
    </table>

</body>
</html>';

// ============================================
// RENDER PDF
// ============================================
require_once __DIR__ . '/vendor/autoload.php';

$dompdf = new Dompdf\Dompdf();
$options = new Dompdf\Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf->setOptions($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$dompdf->stream(str_replace(' ', '_', $judul_export) . '_' . date('Ymd_His') . '.pdf', ['Attachment' => 0]);
exit();
?>