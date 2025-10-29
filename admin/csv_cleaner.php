<?php
// File: admin/csv_cleaner.php
require_once __DIR__ . '/../include/config.php'; // Tidak perlu checkUrlExists
// Mulai sesi untuk menyimpan data sementara
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$output_data = '';
$error_message = '';
$processed_rows = 0;
$unique_videos = 0;
$allowed_domains_input = $_POST['allowed_domains'] ?? ($_SESSION['csv_cleaner_allowed_domains'] ?? '');
$step = 'upload'; // Langkah awal: 'upload', 'fix_thumbs', 'show_result'
$videos_to_fix = []; // Video yang butuh konfirmasi/koreksi thumbnail
$grouped_videos_from_session = null; // Data video sementara

// --- Logika Penanganan Langkah ---

// Langkah 3: Menampilkan Hasil Akhir (setelah koreksi atau jika tidak ada masalah)
if (isset($_SESSION['csv_cleaner_final_output'])) {
    $step = 'show_result';
    $output_data = $_SESSION['csv_cleaner_final_output'];
    $unique_videos = substr_count($output_data, "\n") + 1; // Hitung baris
    // Hapus data session setelah ditampilkan
    unset($_SESSION['csv_cleaner_grouped_videos']);
    unset($_SESSION['csv_cleaner_videos_to_fix']);
    unset($_SESSION['csv_cleaner_allowed_domains']);
    unset($_SESSION['csv_cleaner_final_output']);
}

// Langkah 2b: Memproses Koreksi Thumbnail yang Disubmit
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_thumbnail_corrections') {
    if (isset($_SESSION['csv_cleaner_grouped_videos']) && isset($_SESSION['csv_cleaner_videos_to_fix'])) {
        $grouped_videos = $_SESSION['csv_cleaner_grouped_videos'];
        $submitted_thumbnails = $_POST['thumbnails'] ?? [];

        // Update thumbnail di data grup berdasarkan input user
        foreach ($_SESSION['csv_cleaner_videos_to_fix'] as $key => $details) {
            $unique_key = $details['unique_key'];
             if (isset($submitted_thumbnails[$unique_key])) {
                $submitted_url = trim($submitted_thumbnails[$unique_key]);
                 // Validasi URL sederhana saat submit
                 if (!empty($submitted_url) && filter_var($submitted_url, FILTER_VALIDATE_URL)) {
                    $grouped_videos[$unique_key]['thumbnail'] = $submitted_url;
                 } else {
                    // Jika user mengosongkan atau memasukkan URL tidak valid, kosongkan saja
                    $grouped_videos[$unique_key]['thumbnail'] = '';
                    if (!empty($submitted_url)) { // Beri tahu jika input tidak valid
                         $error_message .= "URL Thumbnail untuk " . $details['code'] . " tidak valid. ";
                    }
                 }
            } else {
                 // Jika tidak ada input (seharusnya tidak terjadi), kosongkan
                $grouped_videos[$unique_key]['thumbnail'] = '';
            }
        }

        if (empty($error_message)) {
            // Proses ke Output Final setelah koreksi
            $final_output_lines = process_grouped_videos_to_output($grouped_videos, $_SESSION['csv_cleaner_allowed_domains']);
            $_SESSION['csv_cleaner_final_output'] = implode("\n", $final_output_lines);
            // Tidak perlu notifikasi auto thumb lagi di sini
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
             // Tampilkan lagi form jika ada URL invalid
             $step = 'fix_thumbs';
             $videos_to_fix = $_SESSION['csv_cleaner_videos_to_fix']; // Kirim lagi data asli
        }
    } else {
        // Error Sesi (Sama)
        $error_message = "Data sesi hilang. Silakan unggah ulang file CSV.";
        $step = 'upload';
        unset($_SESSION['csv_cleaner_grouped_videos']);
        unset($_SESSION['csv_cleaner_videos_to_fix']);
        unset($_SESSION['csv_cleaner_allowed_domains']);
    }
}

// Langkah 1 & 2a: Upload CSV & Deteksi + Generate Thumbnail Otomatis (Tanpa Validasi Server)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Hapus sesi lama (Sama)
    unset($_SESSION['csv_cleaner_grouped_videos']);
    unset($_SESSION['csv_cleaner_videos_to_fix']);
    unset($_SESSION['csv_cleaner_allowed_domains']);
    unset($_SESSION['csv_cleaner_final_output']);

    $_SESSION['csv_cleaner_allowed_domains'] = $allowed_domains_input;

    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['csv_file']['tmp_name'];
        $grouped_videos = [];
        $videos_to_fix = []; // Video yang perlu ditampilkan di form koreksi
        // KEMBALIKAN: $thumbnail_map diperlukan untuk logika asli
        $thumbnail_map = [];

        if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
            $headers = array_map('trim', fgetcsv($handle));
             if ($headers === false || empty($headers)) { $error_message = "Gagal membaca header dari file CSV atau header kosong."; }
             elseif (!in_array('thumbnail-src', $headers) || !in_array('link-href', $headers) || !in_array('judul', $headers)) { $error_message = "Header CSV tidak valid. Pastikan kolom 'thumbnail-src', 'link-href', dan 'judul' ada."; }
             else {
                // ========================================================================
                // TAHAP 1: KEMBALIKAN LOGIKA ASLI - Baca CSV, pisahkan detail & thumbnail map
                // ========================================================================
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($headers) !== count($data)) { $data = array_pad($data, count($headers), null); if (count($headers) !== count($data)) continue; }
                    $row = @array_combine($headers, $data); if ($row === false) continue;

                    // KEMBALIKAN: Logika asli identifikasi baris thumbnail vs detail
                    if (!empty($row['thumbnail-src']) && empty($row['link-href'])) {
                        $thumbnail_url = trim($row['thumbnail-src']);
                        // KEMBALIKAN: Ekstrak kode dari URL thumbnail asli
                        $video_code = strtolower(pathinfo($thumbnail_url, PATHINFO_FILENAME));
                        if (!isset($thumbnail_map[$video_code])) {
                            $thumbnail_map[$video_code] = $thumbnail_url;
                        }
                    }
                    else if (!empty($row['link-href'])) {
                        $unique_key = trim($row['link-href']);
                        if (!isset($grouped_videos[$unique_key])) {
                            $grouped_videos[$unique_key] = [
                                'judul' => trim($row['judul'] ?? ''),
                                'deskripsi' => trim($row['deskripsi'] ?? ''),
                                'thumbnail' => '', // <-- Biarkan kosong dulu
                                'release date' => trim($row['release date'] ?? ''),
                                'link embed trailer' => trim($row['link embed trailer'] ?? ''),
                                'studio' => trim($row['studio'] ?? ''),
                                'durasi' => trim($row['durasi'] ?? ''),
                                'embeds' => [], 'gallery_images' => [], 'all_genres' => [], 'all_actresses' => [],
                                // Simpan thumbnail dari baris detail sebagai fallback
                                'thumbnail_from_detail' => trim($row['thumbnail-src'] ?? '')
                            ];
                        }
                        // Kumpulkan data lain (Sama seperti sebelumnya)
                        if (!empty($row['link embed player'])) $grouped_videos[$unique_key]['embeds'][] = trim($row['link embed player']);
                        if (!empty($row['gallery-src']))     $grouped_videos[$unique_key]['gallery_images'][] = trim($row['gallery-src']);
                        if (!empty($row['genres']))          $grouped_videos[$unique_key]['all_genres'][] = trim($row['genres']);
                        if (!empty($row['actress']))         $grouped_videos[$unique_key]['all_actresses'][] = trim($row['actress']);
                        // Update fallback thumbnail jika baris duplikat punya
                        if (empty($grouped_videos[$unique_key]['thumbnail_from_detail']) && !empty($row['thumbnail-src'])) {
                             $grouped_videos[$unique_key]['thumbnail_from_detail'] = trim($row['thumbnail-src']);
                        }
                    }
                } // Akhir While
                fclose($handle);

                // ========================================================================
                // TAHAP 2: KEMBALIKAN LOGIKA ASLI - Mencocokkan dengan peta + Fallback + DETEKSI BARU
                // ========================================================================
                foreach ($grouped_videos as $key => &$video_data) {
                    $raw_judul = $video_data['judul'];
                    $video_code_from_title = null;
                    $code_for_noti = 'UNKNOWN_CODE'; // Untuk form koreksi
                    $generated_thumb_url = ''; // Simpan URL otomatis jika dibuat
                    $needs_confirmation_or_fix = false;
                    $reason_for_form = '';

                    // KEMBALIKAN: Cari kode video dalam judul
                    if (preg_match('/([a-z]+-\d+)/i', $raw_judul, $matches)) {
                        $video_code_from_title = strtolower($matches[1]);
                        $code_for_noti = strtoupper($matches[1]); // Simpan kode untuk notif/form

                        // KEMBALIKAN: Jika kode dari judul ditemukan di peta thumbnail, pasangkan!
                        if (isset($thumbnail_map[$video_code_from_title])) {
                            $video_data['thumbnail'] = $thumbnail_map[$video_code_from_title];
                        }
                    } else {
                         // Coba ekstrak kode dari tempat lain di judul jika format awal gagal
                         if (preg_match('/([A-Z]+-\d+)/i', $raw_judul, $code_match_fallback)) {
                             $code_for_noti = strtoupper($code_match_fallback[1]);
                         }
                    }


                    // KEMBALIKAN: Fallback ke thumbnail dari baris detail jika peta gagal ATAU jika tidak ada baris thumbnail terpisah
                    if (empty($video_data['thumbnail']) && !empty($video_data['thumbnail_from_detail'])) {
                        $video_data['thumbnail'] = $video_data['thumbnail_from_detail'];
                    }
                    unset($video_data['thumbnail_from_detail']); // Hapus field sementara


                    // --- MODIFIKASI DIMULAI DI SINI: Cek Thumbnail Final & Generate Otomatis ---
                    $final_thumbnail = $video_data['thumbnail'];

                    // Cek jika thumbnail kosong atau default
                    if (empty($final_thumbnail) || $final_thumbnail === '/images/default-cover.jpg') {
                        $needs_confirmation_or_fix = true; // Tandai untuk ditampilkan di form
                        // Coba generate URL otomatis JIKA ADA KODE
                        if ($code_for_noti !== 'UNKNOWN_CODE') {
                            $generated_thumb_url = 'https://cdn002.imggle.net/webp/poster/' . strtolower($code_for_noti) . '.webp';
                            $video_data['thumbnail'] = $generated_thumb_url; // Langsung SET URL otomatis ini (sementara)
                            $reason_for_form = 'Otomatis dibuat. Mohon konfirmasi/koreksi.';
                        } else {
                            // Tidak ada kode, tidak bisa buat otomatis
                            $video_data['thumbnail'] = ''; // Pastikan kosong
                            $reason_for_form = 'Kosong/Default & tidak ada kode. Mohon isi manual.';
                        }
                    } // Jika thumbnail sudah ada dari CSV/Map dan BUKAN default, tidak perlu ditampilkan di form

                    // Jika perlu ditampilkan di form, tambahkan ke daftar
                    if ($needs_confirmation_or_fix) {
                        $videos_to_fix[$key] = [
                            'code' => $code_for_noti,
                            'reason' => $reason_for_form,
                            'generated_url' => $generated_thumb_url, // Kirim URL otomatis ke form
                            'unique_key' => $key
                        ];
                    }
                     // --- AKHIR MODIFIKASI ---
                }
                unset($video_data); // Hapus referensi


                // Putuskan langkah selanjutnya (Sama seperti sebelumnya)
                if (!empty($videos_to_fix)) {
                    $step = 'fix_thumbs';
                    $_SESSION['csv_cleaner_grouped_videos'] = $grouped_videos;
                    $_SESSION['csv_cleaner_videos_to_fix'] = $videos_to_fix;
                } else {
                     $final_output_lines = process_grouped_videos_to_output($grouped_videos, $allowed_domains_input);
                     $_SESSION['csv_cleaner_final_output'] = implode("\n", $final_output_lines);
                     header("Location: " . $_SERVER['REQUEST_URI']);
                     exit;
                }
            } // Akhir else header valid
        } else { $error_message = "Gagal membuka file..."; } // Gagal buka file
    } else { $error_message = "Gagal upload file..."; } // Gagal upload
} // Akhir if POST upload


// Fungsi process_grouped_videos_to_output (KEMBALIKAN LOGIKA ASLI)
function process_grouped_videos_to_output(array $grouped_videos, string $allowed_domains_input): array {
    $output_lines = [];
    $allowed_domains = [];
     if (!empty($allowed_domains_input)) { $allowed_domains = array_filter(array_map('trim', explode("\n", $allowed_domains_input))); }

    foreach ($grouped_videos as $video_group) {
        // KEMBALIKAN: Logika pembersihan Judul asli
        $raw_judul = $video_group['judul']; $judul = 'Judul Tidak Ditemukan';
         if (preg_match('/^(.*?\])/', $raw_judul, $matches)) { $judul = trim($matches[1]); }
         else if (preg_match('/([a-z]+-\d+.*)/i', $raw_judul, $matches)) { $judul = trim($matches[1]); }
         else { $judul = $raw_judul; }

        $deskripsi = $video_group['deskripsi'];
        $thumbnail = $video_group['thumbnail']; // Ambil thumbnail yang sudah final
        $trailer = $video_group['link embed trailer'];

        // KEMBALIKAN: Logika Embed asli
        $collected_embeds = array_unique(array_filter($video_group['embeds'])); $final_embeds = [];
        if (!empty($allowed_domains)) { foreach ($collected_embeds as $embed_url) { $host = str_replace('www.', '', parse_url($embed_url, PHP_URL_HOST)); if (in_array($host, $allowed_domains)) { $url_parts = parse_url($embed_url); if(isset($url_parts['scheme'], $url_parts['host'], $url_parts['path'])){ $final_embeds[] = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path']; } } } }
        else { foreach ($collected_embeds as $embed_url) { $url_parts = parse_url($embed_url); if(isset($url_parts['scheme'], $url_parts['host'], $url_parts['path'])){ $final_embeds[] = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path']; } } }
        $embed_links = implode(',', $final_embeds);

        // KEMBALIKAN: Logika Galeri asli (filter GIF)
        $clean_gallery_urls = array_filter(array_unique($video_group['gallery_images']), function($url) { $isValidUrl = filter_var($url, FILTER_VALIDATE_URL); $isNotGif = strtolower(pathinfo($url, PATHINFO_EXTENSION)) !== 'gif'; return $isValidUrl && $isNotGif; });
        $hd_gallery_urls = []; foreach ($clean_gallery_urls as $url) { if (strpos($url, 'pics.dmm.co.jp') !== false) { $pattern = '/(-\d+\.jpg)$/i'; $replacement = 'jp\1'; $hd_gallery_urls[] = preg_replace($pattern, $replacement, $url); } else { $hd_gallery_urls[] = $url; } }
        $gallery = implode(',', $hd_gallery_urls);

        // KEMBALIKAN: Logika Genres, Actress, Studio asli
        $genres = implode(',', array_unique(array_filter($video_group['all_genres'])));
        $clean_actresses = []; foreach(array_unique(array_filter($video_group['all_actresses'])) as $actress_str) { $actress_name = str_ireplace('Cast(s):', '', $actress_str); $actress_name = preg_replace('/\s*\(.*?\)/', '', $actress_name); $clean_actresses[] = trim($actress_name); } $actress = implode(',', array_unique($clean_actresses));
        $studio = trim(str_ireplace('Studio:', '', $video_group['studio']));

        // KEMBALIKAN: Logika Tanggal asli
        $release_date_raw = $video_group['release date']; $release_date = !empty($release_date_raw) ? date('d-m-Y', strtotime($release_date_raw)) : date('d-m-Y');

        // KEMBALIKAN: Logika Durasi asli
        $duration = trim($video_group['durasi']); if (is_numeric($duration)) { $duration .= ' min'; } elseif (empty($duration)) { $duration = '0 min'; }
        $kategori = '';

        $output_columns = array_map('trim', [ $judul, $deskripsi, $thumbnail, $embed_links, $trailer, $gallery, $genres, $kategori, $release_date, $duration, $actress, $studio ]);
        $output_columns = array_pad($output_columns, 12, ''); $output_lines[] = implode('|', $output_columns);
    }
    return $output_lines;
}


require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pembersih & Formatter CSV</h1>
<p class="page-desc">Unggah file CSV dari web scrapper Anda untuk merapikannya ke dalam format Mass Import.</p>

<?php if ($error_message): ?> <div class="message error"><?php echo htmlspecialchars($error_message); ?></div> <?php endif; ?>

<?php if ($step === 'upload'): ?>
<div class="card">
    <div class="card-header"> <h3>1. Konfigurasi & Unggah</h3> </div>
    <form method="POST" action="" enctype="multipart/form-data" style="padding: 1.5rem;">
        <div class="form-group">
            <label for="allowed_domains">Domain Embed yang Diizinkan (Opsional)</label>
            <textarea name="allowed_domains" id="allowed_domains" class="form-textarea" rows="4" placeholder="Masukkan domain yang ingin dipakai, satu per baris. Contoh:&#10;turboplayers.xyz&#10;stbhg.click"><?php echo htmlspecialchars($allowed_domains_input); ?></textarea>
            <p class="form-hint">Jika dikosongkan, semua link embed dari CSV akan dipakai.</p>
        </div>
        <div class="form-group">
            <label for="csv_file">Pilih File CSV Anda</label>
            <input type="file" name="csv_file" id="csv_file" class="form-input" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-cog"></i> Proses File</button>
    </form>
</div>
<?php endif; ?>


<?php if ($step === 'fix_thumbs' && !empty($videos_to_fix)): ?>
<div class="card">
    <div class="card-header"> <h3 style="color: var(--warning-accent);"><i class="fas fa-edit"></i> Konfirmasi / Perbaiki URL Thumbnail</h3> </div>
    <form method="POST" action="" style="padding: 1.5rem;">
        <input type="hidden" name="action" value="submit_thumbnail_corrections">
        <input type="hidden" name="allowed_domains" value="<?php echo htmlspecialchars($allowed_domains_input); ?>">
        <p>Thumbnail berikut kosong/invalid atau dibuat otomatis. Silakan periksa preview dan URL, lalu koreksi jika perlu:</p>

        <?php foreach ($videos_to_fix as $key => $details):
              $generated_url = $details['generated_url'] ?? ''; // Ambil URL otomatis, default string kosong jika tidak ada
              $unique_form_key = htmlspecialchars($details['unique_key']);
              $image_id = "preview_" . $key;
              $input_id = "thumb_" . $key;
              $placeholder_id = "placeholder_" . $key; // ID untuk placeholder
        ?>
            {/* KEMBALIKAN: Div pembungkus dengan display: flex */}
            <div class="form-group" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;">
                {/* KEMBALIKAN: Kolom Input URL */}
                <div style="flex-grow: 1;">
                    <label for="<?php echo $input_id; ?>">
                        <strong><?php echo htmlspecialchars($details['code']); ?></strong>
                        <span style="color: var(--text-secondary); font-size: 0.8em;"> (<?php echo htmlspecialchars($details['reason']); ?>)</span>
                    </label>
                    <input type="url"
                           name="thumbnails[<?php echo $unique_form_key; ?>]"
                           id="<?php echo $input_id; ?>"
                           class="form-input"
                           value="<?php echo htmlspecialchars($generated_url); ?>"
                           placeholder="Masukkan URL thumbnail..."
                           oninput="updatePreview('<?php echo $image_id; ?>', '<?php echo $placeholder_id; ?>', this.value)"> {/* <-- Perbarui argumen JS */}
                </div>
                {/* KEMBALIKAN: Kolom Preview Gambar */}
                <div style="flex-shrink: 0; width: 150px; text-align: center;">
                     <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 5px;">Preview</label>
                    <img id="<?php echo $image_id; ?>"
                         src="<?php echo !empty($generated_url) ? htmlspecialchars($generated_url) : ''; ?>"
                         alt="Preview"
                         style="max-width: 100%; height: auto; border: 1px solid var(--border-color); background-color: var(--bg-tertiary); min-height: 50px; display: <?php echo !empty($generated_url) ? 'block' : 'none'; ?>;" {/* <-- Atur display awal */}
                         onerror="this.style.display='none'; document.getElementById('<?php echo $placeholder_id; ?>').style.display='block';"
                         onload="this.style.display='block'; document.getElementById('<?php echo $placeholder_id; ?>').style.display='none';">
                     {/* KEMBALIKAN: Placeholder */}
                     <span id="<?php echo $placeholder_id; ?>" style="display: <?php echo !empty($generated_url) ? 'none' : 'block'; ?>; font-size: 0.8em; color: var(--text-secondary);">(Gambar tidak tersedia)</span>
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan Semua & Lanjutkan</button>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">Batal & Unggah Ulang</a>
    </form>
     {/* KEMBALIKAN JAVASCRIPT LENGKAP */}
    <script>
        function updatePreview(imageId, placeholderId, url) {
            const img = document.getElementById(imageId);
            const placeholder = document.getElementById(placeholderId);
            if (img && placeholder) {
                 if (url && url.trim() !== '') {
                    img.src = url;
                    // Tampilkan gambar dan sembunyikan placeholder saat input diubah
                    // onerror/onload akan menangani tampilan akhir
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                 } else {
                     // Jika URL dikosongkan manual oleh user
                     img.src = ''; // Kosongkan src juga
                     img.style.display = 'none';
                     placeholder.style.display = 'block';
                 }
            }
        }
        // Pastikan preview awal benar (optional, onload/onerror should handle this)
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($videos_to_fix as $key => $details):
                  $image_id_init = "preview_" . $key;
                  $placeholder_id_init = "placeholder_" . $key; // Tambahkan ini
                  $generated_url_init = $details['generated_url'] ?? '';
                  if (!empty($generated_url_init)):
            ?>
                 updatePreview('<?php echo $image_id_init; ?>', '<?php echo $placeholder_id_init; ?>', '<?php echo htmlspecialchars($generated_url_init); ?>');
            <?php endif; endforeach; ?>
        });
    </script>
</div>
<?php endif; ?>


<?php if ($step === 'show_result' && $output_data): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header"> <h3>2. Salin Hasil</h3> </div>
    <div style="padding: 1.5rem;">
        <p class="message success">Berhasil memproses data menjadi <strong><?php echo $unique_videos; ?></strong> video unik. Salin teks di bawah ini dan tempel ke fitur Mass Import.</p>
        <div class="form-group"> <label for="output_data">Data Siap Pakai</label> <textarea id="output_data" class="form-textarea" rows="20" readonly><?php echo htmlspecialchars($output_data); ?></textarea> </div>
        <button class="btn btn-secondary" onclick="copyToClipboard()"><i class="fas fa-copy"></i> Salin ke Clipboard</button>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary" style="margin-left: 10px;">Proses File Lain</a>
    </div>
</div>
<script>
function copyToClipboard() {
    const textarea = document.getElementById('output_data');
    textarea.select();
    try {
        navigator.clipboard.writeText(textarea.value).then(function() {
            alert('Teks berhasil disalin ke clipboard!');
        }, function(err) {
             // Fallback ke execCommand jika API Clipboard gagal
            document.execCommand('copy');
            alert('Teks berhasil disalin (fallback)!');
        });
    } catch (err) {
        // Fallback total jika API Clipboard tidak didukung
        document.execCommand('copy');
        alert('Teks berhasil disalin (fallback total)!');
    }
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/templates/footer.php';
?>