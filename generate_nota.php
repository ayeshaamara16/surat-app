<?php
require_once __DIR__ . '/middleware/auth_check.php';

if (!checkRole(['superadmin', 'admin', 'admin_nota'])) {
    die('Akses ditolak');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

$id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

if (!$id) {
    die('ID Nota tidak valid');
}

if ($action === 'download') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM nota_pengajuan WHERE id = ?");
        $stmt->execute([$id]);
        $nota = $stmt->fetch();
        
        if (!$nota) {
            die('Data nota tidak ditemukan');
        }
        
        $template_path = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'nota_pengajuan' . DIRECTORY_SEPARATOR . 'template.docx';
        
        if (!file_exists($template_path)) {
            die('Template tidak ditemukan di: ' . $template_path);
        }
        
        $templateProcessor = new TemplateProcessor($template_path);
        
        // ============================================
        // FORMAT TANGGAL
        // ============================================
        $bulan_indo = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $raw_tgl = $nota['tempat_tgl'] ?? '';
        
        $tempat = 'Purwokerto';
        $tanggal_nota = '';
        
        if (!empty($raw_tgl)) {
            $parts = explode(', ', $raw_tgl, 2);
            if (count($parts) === 2) {
                $tempat = $parts[0];
                $tanggal_nota = $parts[1];
            } else {
                $timestamp = strtotime($raw_tgl);
                if ($timestamp) {
                    $tanggal_nota = date('j', $timestamp) . ' ' . $bulan_indo[date('n', $timestamp)-1] . ' ' . date('Y', $timestamp);
                }
            }
        }
        
        if (empty($tanggal_nota)) {
            $tanggal_nota = date('j') . ' ' . $bulan_indo[date('n')-1] . ' ' . date('Y');
        }
        
        $tempat_tgl_final = $tempat . ', ' . $tanggal_nota;
        
        // ============================================
        // FORMAT CATATAN DENGAN PANAH
        // ============================================
        $catatan = $nota['catatan'] ?? '';
        $catatan_lines = array_filter(preg_split('/\r\n|\r|\n/', $catatan), fn($line) => trim($line) !== '');
        
        $catatan_formatted = '';
        if (!empty($catatan_lines)) {
            foreach ($catatan_lines as $line) {
                $catatan_formatted .= "➤ " . trim($line) . "\r\n";
            }
            // Hapus newline terakhir
            $catatan_formatted = rtrim($catatan_formatted, "\r\n");
        } else {
            $catatan_formatted = "➤ SUBSTANSI NASKAH KEPUTUSAN BUPATI BANYUMAS TELAH DIKOORDINASIKAN DENGAN BADAN KEPEGAWAIAN PENGEMBANGAN SUMBER DAYA MANUSIA KABUPATEN BANYUMAS\r\n➤ TATA NASKAH TELAH DIKOREKSI BAGIAN HUKUM SETDA KABUPATEN BANYUMAS\r\n➤ KONSEP TELAH SIAP UNTUK DITANDATANGANI BUPATI";
        }
        
        // ============================================
        // SET VALUE KE TEMPLATE
        // ============================================
        $templateProcessor->setValue('tempat_tgl', $tempat_tgl_final);
        $templateProcessor->setValue('kepada', $nota['kepada'] ?? 'BUPATI BANYUMAS');
        $templateProcessor->setValue('konsep_naskah', $nota['konsep_naskah'] ?? 'NASKAH KEPUTUSAN BUPATI BANYUMAS');
        $templateProcessor->setValue('tentang', $nota['tentang'] ?? '');
        $templateProcessor->setValue('catatan', $catatan_formatted);
        $templateProcessor->setValue('jabatan_penandatangan', $nota['jabatan_penandatangan'] ?? 'KEPALA BAGIAN HUKUM SETDA KABUPATEN BANYUMAS');
        $templateProcessor->setValue('nama_penandatangan', $nota['nama_penandatangan'] ?? 'ARIF ROHMAN, S.H.M.H.');
        $templateProcessor->setValue('nip_penandatangan', $nota['nip_penandatangan'] ?? '19750531 200903 1 002');
        $templateProcessor->setValue('lampiran', $nota['lampiran'] ?? '');
        
        // ============================================
        // LOG AKTIVITAS
        // ============================================
        $log = $db->prepare("INSERT INTO aktivitas_log (user_id, action, description) VALUES (?, 'download_nota', ?)");
        $log->execute([$_SESSION['user_id'], "Download nota ID: $id"]);
        
        // ============================================
        // OUTPUT
        // ============================================
        $filename = 'Nota_Pengajuan_' . $nota['id'] . '.docx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $templateProcessor->saveAs('php://output');
        exit;
        
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
}

header('Location: nota.php');
exit();
?>