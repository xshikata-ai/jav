<?php
// File: admin/cron_importer.php
// Skrip ini HANYA untuk dijalankan via Command Line (CLI) / Cron Job

// Keamanan: Pastikan skrip ini dijalankan dari CLI, bukan browser
if (php_sapi_name() !== 'cli') {
    die("Akses ditolak. Skrip ini hanya boleh dijalankan dari command line.");
}

// Set waktu eksekusi tak terbatas (penting untuk CLI)
set_time_limit(0);

// Panggil file konfigurasi dan database
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

// Fungsi helper untuk log yang rapi
function log_message($itemId, $level, $message) {
    $prefix = date('[Y-m-d H:i:s]');
    if ($itemId) {
        $prefix .= " [Item #$itemId]";
    }
    echo "$prefix [$level] $message\n";
}

log_message(null, 'INFO', "===== Memulai Proses Impor Cron =====");

// Ambil 5 item 'pending' dari antrian
$itemsToProcess = getPendingImportItems(5);
$totalItems = count($itemsToProcess);

if ($totalItems === 0) {
    log_message(null, 'INFO', "Tidak ada video baru di antrian. Selesai.");
    exit;
}

log_message(null, 'INFO', "Ditemukan $totalItems video untuk diproses...");

$successCount = 0;
$failCount = 0;

foreach ($itemsToProcess as $item) {
    $itemId = $item['id'];
    $line = $item['line_data'];
    $mass_category_id = $item['target_category_id'];
    $allowed_domains_input = $item['allowed_domains']; // Ini tetap berguna jika Anda ingin memfilter URL mana yang *boleh* diproses

    log_message($itemId, 'INFO', "--------------------------------------------------");
    log_message($itemId, 'INFO', "Memulai pemrosesan...");

    // Daftar domain yang dikenali dan API-nya
    $api_domain_map = [
        'ryderjet.com' => 'EV',
        'stbhg.click' => 'SW',
        'trailerhg.xyz' => 'SW', // Tambahkan trailerhg ke SW
        'dsvplay.com' => 'DD',
        // Tambahkan domain lain jika perlu di masa depan
    ];

    $parts = array_map('trim', explode('|', $line));

    if (count($parts) !== 12) {
        $errorMessage = 'Format salah, harus ada 12 kolom.';
        log_message($itemId, 'GAGAL', $errorMessage);
        updateImportQueueItemStatus($itemId, 'failed', $errorMessage);
        $failCount++;
        continue;
    }

    $title = $parts[0];
    if (empty($title)) {
        $errorMessage = 'Judul tidak boleh kosong.';
        log_message($itemId, 'GAGAL', $errorMessage);
        updateImportQueueItemStatus($itemId, 'failed', $errorMessage);
        $failCount++;
        continue;
    }
    list(, $description, $thumbnail, $embed_links_str, $trailer_link, $gallery_links_str, $genres, $category_from_csv, $release_date_str, $duration_str, $actresses_str, $studios) = $parts;

    log_message($itemId, 'INFO', "Judul Video: '" . htmlspecialchars($title) . "'");

    // --- Logika Cerdas Thumbnail ---
    $warning = null;
    if (empty($thumbnail)) {
        if (preg_match('/^([A-Z]+-\d+)/i', $title, $matches)) {
            $video_code = strtolower($matches[1]);
            $thumbnail = 'https://cdn002.imggle.net/webp/poster/' . $video_code . '.webp';
            $warning = 'Thumbnail otomatis dibuat dari judul.';
            log_message($itemId, 'INFO', $warning);
        } else {
            $warning = 'Thumbnail kosong dan tidak bisa dibuat otomatis.';
            log_message($itemId, 'PERINGATAN', $warning);
        }
    }

    if (doesVideoTitleExist($title)) {
        $errorMessage = 'Judul sudah ada di database.';
        log_message($itemId, 'GAGAL', $errorMessage);
        updateImportQueueItemStatus($itemId, 'failed', $errorMessage);
        $failCount++;
        continue;
    }

    // --- Proses Kloning Embed Player (Logika Baru) ---
    $original_embed_urls = !empty($embed_links_str) ? array_map('trim', explode(',', $embed_links_str)) : [];
    $cloned_embed_urls = [];
    $video_code_from_title = null;
    if (preg_match('/^([A-Z]+-\d+)/i', $title, $matches)) {
        $video_code_from_title = $matches[1];
    }

    if (!empty($original_embed_urls)) {
        foreach ($original_embed_urls as $url) {
            $host = str_replace('www.', '', parse_url($url, PHP_URL_HOST));
            $file_code = basename($url);
            $new_url = $url; // Default ke URL asli jika tidak ada yang cocok/berhasil
            $api_to_use = null;

            log_message($itemId, 'DEBUG', "Menganalisis URL asli: " . htmlspecialchars($url) . " (Host: $host, Code: $file_code)");

            // Tentukan API mana yang akan digunakan berdasarkan host
            foreach ($api_domain_map as $domain => $api_code) {
                if ($host === $domain) {
                    $api_to_use = $api_code;
                    break;
                }
            }

            if ($api_to_use) {
                log_message($itemId, 'INFO', "Domain '$host' terdeteksi, akan menggunakan API '$api_to_use'.");

                $clone_url = '';
                $list_url = '';
                $api_key_clone = '';
                $api_key_list = ''; // Bisa jadi sama atau beda
                $result_key = 'filecode'; // Default, Doodstream mungkin beda
                $embed_domain = '';
                $embed_path = '';

                // Siapkan variabel berdasarkan API yang dipilih
                switch ($api_to_use) {
                    case 'EV':
                        $api_key_clone = EARNVIDS_API_KEY;
                        $api_key_list = EARNVIDS_API_KEY;
                        $clone_url = EARNVIDS_CLONE_URL_BASE . "?key=" . urlencode($api_key_clone) . "&file_code=" . urlencode($file_code);
                        $list_url = EARNVIDS_LIST_URL . '?key=' . urlencode($api_key_list);
                        $embed_domain = EARNVIDS_EMBED_NEW_DOMAIN;
                        $embed_path = EARNVIDS_EMBED_NEW_PATH;
                        break;
                    case 'SW':
                        $api_key_clone = STREAMHG_CLONE_API_KEY;
                        $api_key_list = STREAMHG_CLONE_API_KEY; // Gunakan API Key Clone untuk list juga
                        $clone_url = STREAMHG_CLONE_URL_BASE . "?key=" . urlencode($api_key_clone) . "&file_code=" . urlencode($file_code);
                        $list_url = STREAMHG_LIST_URL . '?key=' . urlencode($api_key_list);
                        $embed_domain = STREAMHG_EMBED_NEW_DOMAIN;
                        $embed_path = STREAMHG_EMBED_NEW_PATH;
                        break;
                    case 'DD':
                        $api_key_clone = DOODSTREAM_API_KEY;
                        $api_key_list = DOODSTREAM_API_KEY;
                        $clone_url = DOODSTREAM_CLONE_URL_BASE . "?key=" . urlencode($api_key_clone) . "&file_code=" . urlencode($file_code);
                        $list_url = DOODSTREAM_LIST_URL . '?key=' . urlencode($api_key_list);
                        $embed_domain = DOODSTREAM_EMBED_NEW_DOMAIN;
                        $embed_path = DOODSTREAM_EMBED_NEW_PATH;
                        // Doodstream mungkin menggunakan 'filecode' atau 'file_code', cek respons API-nya
                        // Kita asumsikan 'filecode' dulu
                        break;
                }

                // 1. Coba Clone
                log_message($itemId, 'DEBUG', "Mencoba kloning $api_to_use via 'clone'...");
                $result_clone = makeExternalApiCall($clone_url);

                if (isset($result_clone['status']) && $result_clone['status'] === 200 && !empty($result_clone['result'][$result_key])) {
                    $new_file_code = $result_clone['result'][$result_key];
                    $new_url = $embed_domain . $embed_path . $new_file_code;
                    log_message($itemId, 'INFO', "Kloning $api_to_use berhasil via 'clone'. URL baru: $new_url");
                } else {
                    // 2. Gagal Clone, Coba List
                    log_message($itemId, 'PERINGATAN', "Kloning $api_to_use 'clone' gagal. Mencoba verifikasi 'file/list' $api_to_use...");
                    $result_list = makeExternalApiCall($list_url);
                    $found_in_list = false;

                    // Sesuaikan path ke daftar file berdasarkan API
                    $files_list = null;
                    if ($api_to_use === 'DD') {
                        $files_list = $result_list['result']['files'] ?? $result_list['result']['docs'] ?? null;
                    } else { // EV & SW
                        $files_list = $result_list['result']['files'] ?? null;
                    }

                    if ($video_code_from_title && is_array($files_list)) {
                        foreach ($files_list as $file_in_list) {
                            // Sesuaikan key title dan filecode berdasarkan API
                            $list_title_key = ($api_to_use === 'DD') ? 'title' : 'title'; // Sesuaikan jika beda
                            $list_filecode_key = ($api_to_use === 'DD') ? 'filecode' : 'file_code'; // Sesuaikan jika beda

                            if (isset($file_in_list[$list_title_key]) && stripos($file_in_list[$list_title_key], $video_code_from_title) !== false) {
                                $list_file_code = $file_in_list[$list_filecode_key] ?? null;
                                if ($list_file_code) {
                                    $new_url = $embed_domain . $embed_path . $list_file_code;
                                    $found_in_list = true;
                                    log_message($itemId, 'INFO', "Verifikasi 'file/list' $api_to_use berhasil. URL: $new_url");
                                    break;
                                }
                            }
                        }
                    }
                    if (!$found_in_list) {
                        log_message($itemId, 'PERINGATAN', "Gagal total kloning $api_to_use untuk URL ini. URL asli akan digunakan.");
                        // $new_url tetap URL asli
                    }
                }
            } else {
                log_message($itemId, 'DEBUG', "Domain '$host' tidak terdaftar untuk API kloning, URL asli akan digunakan.");
                // $new_url tetap URL asli
            }

            $cloned_embed_urls[] = $new_url;
        }
    }

    // --- Urutkan URL Kloning (Logika ini tetap berguna untuk prioritas player) ---
    $vh_urls = []; $sw_urls = []; $dd_urls = []; $other_urls = [];
    $vh_domains = [parse_url(EARNVIDS_EMBED_NEW_DOMAIN, PHP_URL_HOST)];
    $sw_domains = [parse_url(STREAMHG_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dhcplay.com', 'stbhg.click']; // Termasuk domain SW lama jika ada
    $dd_domains = [parse_url(DOODSTREAM_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dood.re']; // Termasuk domain DD lama jika ada

    foreach ($cloned_embed_urls as $url) {
        $host = str_replace('www.', '', parse_url($url, PHP_URL_HOST)); $found = false;
        // Cek domain BARU dulu
        if (strpos($host, parse_url(EARNVIDS_EMBED_NEW_DOMAIN, PHP_URL_HOST)) !== false) { $vh_urls[] = $url; $found = true; }
        if (!$found && strpos($host, parse_url(STREAMHG_EMBED_NEW_DOMAIN, PHP_URL_HOST)) !== false) { $sw_urls[] = $url; $found = true; }
        if (!$found && strpos($host, parse_url(DOODSTREAM_EMBED_NEW_DOMAIN, PHP_URL_HOST)) !== false) { $dd_urls[] = $url; $found = true; }
        // Cek domain LAMA/alternatif
        if (!$found) { foreach ($sw_domains as $d) { if (strpos($host, $d) !== false) { $sw_urls[] = $url; $found = true; break; } } }
        if (!$found) { foreach ($dd_domains as $d) { if (strpos($host, $d) !== false) { $dd_urls[] = $url; $found = true; break; } } }
        // Lainnya
        if (!$found) { $other_urls[] = $url; }
    }
    $sorted_urls = array_merge($vh_urls, $sw_urls, $dd_urls, $other_urls);
    log_message($itemId, 'DEBUG', "URL Embed setelah diurutkan: " . implode(', ', $sorted_urls));

    // --- Kloning Trailer (Logika ini bisa disesuaikan juga jika perlu) ---
    $final_trailer_url = $trailer_link;
    // (Misalnya, jika trailer hanya dari trailerhg.xyz, kloning pakai SW)
    $trailer_host = str_replace('www.', '', parse_url($trailer_link, PHP_URL_HOST));
    if ($trailer_host === 'trailerhg.xyz') {
        log_message($itemId, 'DEBUG', "Mencoba kloning trailer SW...");
        $trailer_file_code = basename($trailer_link);
        $clone_api_url_trailer = STREAMHG_CLONE_URL_BASE . "?key=" . urlencode(STREAMHG_CLONE_API_KEY) . "&file_code=" . urlencode($trailer_file_code);
        $result_trailer = makeExternalApiCall($clone_api_url_trailer);
        if (isset($result_trailer['status']) && $result_trailer['status'] === 200 && !empty($result_trailer['result']['filecode'])) {
            $cloned_trailer_url = STREAMHG_EMBED_NEW_DOMAIN . STREAMHG_EMBED_NEW_PATH . $result_trailer['result']['filecode'];
            $final_trailer_url = $cloned_trailer_url;
             log_message($itemId, 'INFO', "Kloning trailer SW berhasil. URL: $final_trailer_url");
        } else {
            log_message($itemId, 'PERINGATAN', "Gagal kloning trailer SW. URL asli akan digunakan.");
        }
    }


    $main_embed_url = array_shift($sorted_urls);
    $extra_embed_urls = !empty($sorted_urls) ? implode(',', $sorted_urls) : null;

    if (empty($main_embed_url)) {
        $errorMessage = 'Link embed player utama tidak ditemukan setelah diproses.';
        log_message($itemId, 'GAGAL', $errorMessage);
        updateImportQueueItemStatus($itemId, 'failed', $errorMessage);
        $failCount++;
        continue;
    }
     log_message($itemId, 'INFO', "URL Embed Utama: $main_embed_url");
     if ($extra_embed_urls) {
        log_message($itemId, 'INFO', "URL Embed Tambahan: $extra_embed_urls");
     }

    if (doesEmbedUrlExist($main_embed_url)) {
        $errorMessage = 'Link embed utama sudah ada di database.';
        log_message($itemId, 'GAGAL', $errorMessage);
        updateImportQueueItemStatus($itemId, 'failed', $errorMessage);
        $failCount++;
        continue;
    }

    // --- Persiapan Data Final & Simpan ---
    $final_category_id = $mass_category_id ?: insertCategoryIfNotExist($category_from_csv);
    if (!empty($actresses_str)) {
        $actressNamesArray = array_map('trim', explode(',', $actresses_str));
        addActressesIfNotExist($actressNamesArray);
        log_message($itemId, 'DEBUG', "Memastikan aktris ada: " . $actresses_str);
    }
    $duration_seconds = (int)filter_var($duration_str, FILTER_SANITIZE_NUMBER_INT) * 60;
    $release_date_obj = DateTime::createFromFormat('d-m-Y', $release_date_str);
    $release_date_mysql = ($release_date_obj) ? $release_date_obj->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    $randomViews = rand(1000, 10000); $randomLikes = rand(500, $randomViews);

    $videoData = [
        'original_title' => $title, 'description' => $description, 'tags' => $genres,
        'actresses' => $actresses_str, 'studios' => $studios, 'category_id' => $final_category_id,
        'embed_url' => $main_embed_url, 'extra_embed_urls' => $extra_embed_urls,
        'api_source' => 'mass_import_clone', 'image_url' => $thumbnail, 'duration' => $duration_seconds,
        'quality' => 'HD', 'views' => $randomViews, 'likes' => $randomLikes,
        'trailer_embed_url' => $final_trailer_url,
        'gallery_image_urls' => !empty($gallery_links_str) ? $gallery_links_str : null,
        'download_links' => null, 'cloned_at' => $release_date_mysql,
    ];

    log_message($itemId, 'DEBUG', "Menyimpan data ke database...");
    if (insertMassVideo($videoData)) {
        log_message($itemId, 'BERHASIL', "Video berhasil diimpor.");
        updateImportQueueItemStatus($itemId, 'completed', null);
        $successCount++;
    } else {
        $errorMessage = 'Gagal menyimpan ke DB (fungsi insertMassVideo gagal).';
        log_message($itemId, 'GAGAL', $errorMessage);
        updateImportQueueItemStatus($itemId, 'failed', $errorMessage);
        $failCount++;
    }
}

log_message(null, 'INFO', "--------------------------------------------------");
log_message(null, 'INFO', "===== Proses Selesai =====");
log_message(null, 'INFO', "Total Berhasil: $successCount");
log_message(null, 'INFO', "Total Gagal   : $failCount");
?>