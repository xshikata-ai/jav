<?php
// File: include/config.php - Konfigurasi Global & Fungsi Helper

// --- Pengaturan Error Reporting (Aktifkan di Development, Matikan di Production) ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // Ganti ke 0 (nol) saat situs live/produksi!

// --- Mulai Sesi PHP (Penting: harus dipanggil sebelum output apapun) ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Pengaturan Jalur URL ---
define('BASE_URL', 'https://stepmomhub.com/'); // UBAH INI! SESUAIKAN DENGAN DOMAIN ROOT ANDA
define('ADMIN_PATH', BASE_URL . 'admin/');
define('ASSETS_PATH', BASE_URL . 'assets/');

// --- Definisi Konstanta API Backend ---
define('BACKEND_API_URL', ADMIN_PATH . 'backend_api.php');

// --- Earnvids API Keys & Endpoints ---
define('EARNVIDS_API_KEY', '38466zfjyydjcz90k2k5o');
define('EARNVIDS_SEARCH_URL', 'https://search.earnvidsapi.com/files');
define('EARNVIDS_CLONE_URL_BASE', 'https://earnvidsapi.com/api/file/clone');
define('EARNVIDS_LIST_URL', 'https://earnvidsapi.com/api/file/list');
define('EARNVIDS_EMBED_NEW_DOMAIN', 'https://dingtezuni.com');
define('EARNVIDS_EMBED_NEW_PATH', '/v/');

// --- Streamhg API Keys & Endpoints ---
define('STREAMHG_API_KEY', '10400scgn1qre5c1on2l0');
define('STREAMHG_CLONE_API_KEY', '27814i1rpu6ycnk94ugki');
define('STREAMHG_SEARCH_URL', 'https://search.streamhgapi.com/files');
define('STREAMHG_CLONE_URL_BASE', 'https://streamhgapi.com/api/file/clone');
define('STREAMHG_LIST_URL', 'https://streamhgapi.com/api/file/list'); // <-- TAMBAHKAN BARIS INI
define('STREAMHG_EMBED_NEW_DOMAIN', 'https://gradehgplus.com');
define('STREAMHG_EMBED_NEW_PATH', '/e/');

// --- Doodstream API Keys & Endpoints ---
define('DOODSTREAM_API_KEY', '229870x8szwvdp7v5iyiyf');
// === PERUBAHAN DI SINI: Mengganti URL API Doodstream ===
define('DOODSTREAM_CLONE_URL_BASE', 'https://doodapi.co/api/file/clone');
define('DOODSTREAM_LIST_URL', 'https://doodapi.co/api/file/list'); // <-- TAMBAHKAN BARIS INI
// =======================================================
define('DOODSTREAM_EMBED_NEW_DOMAIN', 'https://dsvplay.com'); 
define('DOODSTREAM_EMBED_NEW_PATH', '/e/');

// --- Konfigurasi Database ---
define('DB_HOST', 'localhost');
define('DB_USER', 'earnvids_db');
define('DB_PASS', '58fGjRpyCYsEhHYT');
define('DB_NAME', 'earnvids_db');

// --- Fungsi Helper: Format Durasi (hh:mm:ss) ---
function formatDuration($seconds) {
    if (!is_numeric($seconds) || $seconds < 0) {
        return 'N/A';
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

// --- Fungsi Helper: Melakukan Panggilan cURL ke API Eksternal ---
function makeExternalApiCall($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $apiResponse = curl_exec($ch);
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => 'Gagal koneksi ke API eksternal: ' . $curlError];
    }
    $data = json_decode($apiResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonError = json_last_error_msg();
        curl_close($ch);
        return ['status' => 'error', 'message' => 'Format respons JSON dari API eksternal tidak valid: ' . $jsonError, 'response_body' => $apiResponse];
    }
    curl_close($ch);
    return $data;
}

// --- Fungsi Helper: Melakukan Panggilan cURL Internal ke backend_api.php ---
function callBackendApi($url, $method = 'GET', $data = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        curl_close($ch);
        return ['status' => 'error', 'message' => 'Gagal komunikasi dengan layanan backend: ' . $curlError];
    }
    $decodedData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonError = json_last_error_msg();
        curl_close($ch);
        return ['status' => 'error', 'message' => 'Respons tidak valid dari layanan backend: ' . $jsonError];
    }
    curl_close($ch);
    return $decodedData;
}
// ==================================================
// FUNGSI HELPER BARU UNTUK TAMPILAN PORTRAIT
// ==================================================

function formatDurationToMinutes($seconds) {
    if (!is_numeric($seconds) || $seconds <= 0) {
        return '';
    }
    $minutes = floor($seconds / 60);
    return $minutes . ' min';
}

function getThumbnailTitle($title) {
    if (preg_match('/^([A-Z]+-\d+)/i', $title, $matches)) {
        return strtoupper($matches[1]);
    }
    
    $words = explode(' ', $title);
    return implode(' ', array_slice($words, 0, 3));
}
// ==================================================
// FUNGSI BARU UNTUK PAGINATION MODERN (HYBRID) - VERSI 2 (Mobile Scroll)
// ==================================================
function generatePagination($currentPage, $totalPages, $baseUrl, $queryParams = []) {
    if ($totalPages <= 1) {
        return;
    }

    echo '<nav class="pagination-nav"><ul class="pagination-list">';

    // --- Tombol Previous ---
    if ($currentPage > 1) {
        $queryParams['page'] = $currentPage - 1;
        $prevUrl = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
        echo '<li class="pagination-item"><a href="' . htmlspecialchars($prevUrl) . '" class="pagination-link pagination-prev">';
        echo '<span class="pagination-arrow">&laquo;</span> <span class="pagination-text">Prev</span>';
        echo '</a></li>';
    }

    // --- Halaman 1 ---
    $queryParams['page'] = 1;
    $url1 = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
    echo '<li class="pagination-item"><a href="' . htmlspecialchars($url1) . '" class="pagination-link ' . ($currentPage == 1 ? 'active' : '') . '">1</a></li>';

    

    // --- Halaman Sekitar Halaman Aktif ---
    if ($currentPage > 2) {
        $queryParams['page'] = $currentPage - 1;
        $urlPrev1 = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
        echo '<li class="pagination-item"><a href="' . htmlspecialchars($urlPrev1) . '" class="pagination-link">' . ($currentPage - 1) . '</a></li>';
    }

    if ($currentPage != 1 && $currentPage != $totalPages) {
        $queryParams['page'] = $currentPage;
        $urlCurrent = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
        echo '<li class="pagination-item"><a href="' . htmlspecialchars($urlCurrent) . '" class="pagination-link active">' . $currentPage . '</a></li>';
    }

    if ($currentPage < $totalPages - 1) {
        $queryParams['page'] = $currentPage + 1;
        $urlNext1 = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
        echo '<li class="pagination-item"><a href="' . htmlspecialchars($urlNext1) . '" class="pagination-link">' . ($currentPage + 1) . '</a></li>';
    }

    // --- Ellipsis (...) Akhir ---
    // BLOK INI TELAH DIHAPUS SESUAI PERMINTAAN ANDA
    /*
    if ($currentPage < $totalPages - 2) {
        echo '<li class="pagination-item"><span class="pagination-ellipsis">&hellip;</span></li>';
    }
    */

    // --- Halaman Terakhir ---
    if ($totalPages > 1) {
        $queryParams['page'] = $totalPages;
        $urlLast = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
        echo '<li class="pagination-item"><a href="' . htmlspecialchars($urlLast) . '" class="pagination-link ' . ($currentPage == $totalPages ? 'active' : '') . '">' . $totalPages . '</a></li>';
    }

    // --- Tombol Next ---
    if ($currentPage < $totalPages) {
        $queryParams['page'] = $currentPage + 1;
        $nextUrl = BASE_URL . $baseUrl . '?' . http_build_query($queryParams);
        echo '<li class="pagination-item"><a href="' . htmlspecialchars($nextUrl) . '" class="pagination-link pagination-next">';
        echo '<span class="pagination-text">Next</span> <span class="pagination-arrow">&raquo;</span>';
        echo '</a></li>';
    }

    echo '</ul></nav>';
}
?>