<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;

// Ambil data untuk autocomplete
$allActressesData = getAllActresses();
$allActresses = $allActressesData['data'] ?? [];

$allCategories = getCategories();
$uniqueTags = getUniqueTags();
$uniqueStudios = getUniqueStudios();

// Fungsi untuk membuat thumbnail dari URL eksternal atau file upload
function saveThumbnailFromUrlOrUpload($imageUrl, $uploadedFile) {
    $upload_dir = __DIR__ . '/../assets/uploads/thumbnails/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $fileName = uniqid('thumb_') . '.jpg'; // Default ke JPG
    $targetFilePath = $upload_dir . $fileName;

    if (!empty($uploadedFile['name']) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        // Proses file yang diupload
        $imageFileType = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // Tambahkan webp
        
        if (!in_array($imageFileType, $allowedExtensions)) {
            return ['status' => 'error', 'message' => 'Tipe file gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, WEBP.'];
        }

        // Generate nama file unik dengan ekstensi asli
        $fileName = uniqid('thumb_') . '.' . $imageFileType;
        $targetFilePath = $upload_dir . $fileName;

        if (move_uploaded_file($uploadedFile['tmp_name'], $targetFilePath)) {
            return ['status' => 'success', 'path' => 'assets/uploads/thumbnails/' . $fileName];
        } else {
            return ['status' => 'error', 'message' => 'Gagal mengunggah file thumbnail.'];
        }
    } elseif (!empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        // Proses dari URL eksternal menggunakan cURL
        $ch = curl_init($imageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Ikuti redirect
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.88 Safari/537.36'); // Tambah User-Agent
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Opsional: Nonaktifkan verifikasi SSL jika ada masalah (tidak direkomendasikan untuk produksi tanpa alasan kuat)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Opsional: Nonaktifkan verifikasi SSL (tidak direkomendasikan)
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout 10 detik
        // Tambahkan header Referer untuk mengatasi hotlinking pada beberapa situs
        curl_setopt($ch, CURLOPT_REFERER, $imageUrl); 

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($imageData === false || $httpCode !== 200) {
            return ['status' => 'error', 'message' => "Gagal mengambil gambar dari URL eksternal. (HTTP: {$httpCode}, cURL Error: {$curlError})"];
        }

        // Deteksi tipe gambar
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);
        $extension = '';

        switch ($mimeType) {
            case 'image/jpeg':
                $extension = 'jpg';
                break;
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp': // Tambahkan penanganan WEBP
                $extension = 'webp';
                break;
            default:
                return ['status' => 'error', 'message' => 'Tipe gambar dari URL tidak didukung (hanya JPG, PNG, GIF, WEBP). Deteksi: ' . $mimeType];
        }
        
        // Generate nama file unik dengan ekstensi yang terdeteksi
        $fileName = uniqid('thumb_') . '.' . $extension;
        $targetFilePath = $upload_dir . $fileName;

        if (file_put_contents($targetFilePath, $imageData)) {
            return ['status' => 'success', 'path' => 'assets/uploads/thumbnails/' . $fileName];
        } else {
            return ['status' => 'error', 'message' => 'Gagal menyimpan gambar dari URL eksternal setelah diunduh.'];
        }
    }
    return ['status' => 'error', 'message' => 'Tidak ada gambar thumbnail yang disediakan.'];
}

// Fungsi untuk mengekstrak URL embed dari kode embed (sangat dasar, bisa diperluas)
function extractEmbedUrlFromCode($embedCode) {
    if (empty(trim($embedCode))) return null;
    if (preg_match('/<iframe.*?src=["\'](.*?)["\'].*?>/i', $embedCode, $matches)) {
        return $matches[1];
    }
    if (filter_var($embedCode, FILTER_VALIDATE_URL)) {
        return $embedCode;
    }
    return null; 
}

// Fungsi untuk memproses dan menyimpan gambar galeri
function processAndSaveGallery($uploadedFiles, $externalUrls) {
    $galleryPaths = [];
    $upload_dir = __DIR__ . '/../assets/uploads/gallery/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            // Gagal membuat folder, kembalikan error
            return ['status' => 'error', 'message' => 'Gagal membuat direktori galeri.'];
        }
    }

    // 1. Proses file yang diunggah
    if (!empty($uploadedFiles['name'][0])) {
        $files = [];
        // Restrukturisasi array $_FILES
        foreach ($uploadedFiles as $key => $all) {
            foreach ($all as $i => $val) {
                $files[$i][$key] = $val;
            }
        }

        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($imageFileType, $allowedExtensions)) {
                    $fileName = uniqid('gallery_') . '.' . $imageFileType;
                    $targetFilePath = $upload_dir . $fileName;
                    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                        $galleryPaths[] = 'assets/uploads/gallery/' . $fileName;
                    }
                }
            }
        }
    }

    // 2. Proses URL eksternal
    if (!empty($externalUrls)) {
        $urls = array_map('trim', explode("\n", $externalUrls));
        foreach ($urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $galleryPaths[] = $url;
            }
        }
    }
    
    $finalPaths = !empty($galleryPaths) ? implode(',', $galleryPaths) : null;
    return ['status' => 'success', 'paths' => $finalPaths];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_new_video') {
    $originalTitle = trim($_POST['original_title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $actressesInput = trim($_POST['actresses'] ?? '');
    $studios = trim($_POST['studios'] ?? '');
    $categoryName = trim($_POST['category_name'] ?? '');
    $imageUrlExternal = trim($_POST['image_url_external'] ?? '');
    $embedCode = trim($_POST['embed_code'] ?? '');
    $trailerEmbedCode = trim($_POST['trailer_embed_url'] ?? '');
    $downloadLinksInput = trim($_POST['download_links'] ?? '');
    $galleryUrlsExternal = trim($_POST['gallery_urls_external'] ?? '');
    $views = (int)($_POST['views'] ?? 0);
    $likes = (int)($_POST['likes'] ?? 0);
    
    // Validasi dasar
    if (empty($originalTitle)) {
        $error = "Judul video tidak boleh kosong.";
    }

    $finalImageUrl = null;
    if (!$error) {
        // Proses thumbnail
        $thumbnailResult = saveThumbnailFromUrlOrUpload($imageUrlExternal, $_FILES['image_upload'] ?? []);
        if ($thumbnailResult['status'] === 'error') {
            $error = $thumbnailResult['message'];
        } else {
            $finalImageUrl = $thumbnailResult['path']; // Path relatif ke root website
        }
    }

    // Proses gallery
    $finalGalleryUrls = null;
    if (!$error) {
        $galleryResult = processAndSaveGallery($_FILES['gallery_uploads'] ?? [], $galleryUrlsExternal);
        if ($galleryResult['status'] === 'error') {
            $error = $galleryResult['message'];
        } else {
            $finalGalleryUrls = $galleryResult['paths'];
        }
    }

    // Ekstrak URL embed trailer
    $finalTrailerUrl = extractEmbedUrlFromCode($trailerEmbedCode);

    // Proses link download
    $finalDownloadLinks = !empty($downloadLinksInput) ? implode(',', array_filter(array_map('trim', explode("\n", $downloadLinksInput)))) : null;

    $generatedEmbedId = null;
    $generatedEmbedUrl = null;
    $apiSource = 'manual';

    if (!$error) {
        if (!empty($embedCode)) {
            $generatedEmbedUrl = extractEmbedUrlFromCode($embedCode);
            if ($generatedEmbedUrl) {
                $generatedEmbedId = 'embed_' . md5($embedCode); 
                $apiSource = 'manual_embed';
            } else {
                $error = "Gagal mengekstrak URL dari kode embed. Pastikan kode embed valid.";
            }
        } else {
            $generatedEmbedId = 'manual_' . uniqid(); 
            $generatedEmbedUrl = BASE_URL . 'video/manual/' . $generatedEmbedId; 
            $apiSource = 'manual_upload';
        }
    }


    if (!$error) {
        if (!empty($actressesInput)) {
            $actressNames = array_map('trim', explode(',', $actressesInput));
            addActressesIfNotExist($actressNames);
        }
        
        $categoryId = insertCategoryIfNotExist($categoryName);
        
        $dataToInsert = [
            'original_title' => $originalTitle,
            'description' => $description,
            'tags' => $tags,
            'actresses' => $actressesInput,
            'studios' => $studios,
            'category_id' => $categoryId,
            'embed_id' => $generatedEmbedId,
            'embed_url' => $generatedEmbedUrl, 
            'api_source' => $apiSource, 
            'image_url' => $finalImageUrl,
            'duration' => null, 
            'quality' => 'Custom', 
            'views' => $views,
            'likes' => $likes,
            'trailer_embed_url' => $finalTrailerUrl,
            'gallery_image_urls' => $finalGalleryUrls,
            'download_links' => $finalDownloadLinks,
        ];

        if (insertClonedVideo($dataToInsert)) { 
            $message = "Video '$originalTitle' berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan video. Mungkin ada masalah database atau judul/embed_id duplikat.";
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Tambah Video Baru</h1>
<p class="page-desc">Isi detail video untuk menambahkan ke koleksi Anda.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Form Tambah Video</h3>
    </div>
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_new_video">

        <div class="form-group">
            <label for="original_title">Judul Video</label>
            <input type="text" name="original_title" id="original_title" class="form-input" value="<?php echo htmlspecialchars($_POST['original_title'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Deskripsi</label>
            <textarea name="description" id="description" class="form-textarea"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="actresses_add">Aktris (pisahkan dengan koma)</label>
            <input type="text" name="actresses" id="actresses_add" class="form-input" list="actress-list" value="<?php echo htmlspecialchars($_POST['actresses'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="tags">Genres (pisahkan koma)</label>
            <input type="text" name="tags" id="tags" class="form-input" list="tags-list" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="studios">Studio (pisahkan dengan koma)</label>
            <input type="text" name="studios" id="studios" class="form-input" list="studios-list" value="<?php echo htmlspecialchars($_POST['studios'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="category_name">Kategori</label>
            <input type="text" name="category_name" id="category_name" class="form-input" list="category-list" value="<?php echo htmlspecialchars($_POST['category_name'] ?? ''); ?>" placeholder="Ketik nama kategori">
        </div>

        <div class="form-group">
            <label for="embed_code">Kode Embed Video Utama</label>
            <textarea name="embed_code" id="embed_code" class="form-textarea" placeholder="<iframe src='...'></iframe> atau URL langsung"><?php echo htmlspecialchars($_POST['embed_code'] ?? ''); ?></textarea>
            <p style="font-size: 0.8rem; color: #666; margin-top: 5px;">*Jika diisi, video akan menggunakan kode embed ini. Jika kosong, video tidak akan bisa diputar.</p>
        </div>
        
        <div class="form-group">
            <label for="trailer_embed_url">Kode Embed Trailer</label>
            <textarea name="trailer_embed_url" id="trailer_embed_url" class="form-textarea" placeholder="<iframe src='...'></iframe> atau URL embed trailer"><?php echo htmlspecialchars($_POST['trailer_embed_url'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="image_upload">Unggah Thumbnail Utama</label>
            <input type="file" name="image_upload" id="image_upload" class="form-input" accept="image/*">
            <p style="font-size: 0.8rem; color: #666; margin-top: 5px;">Atau masukkan URL thumbnail eksternal:</p>
            <input type="url" name="image_url_external" id="image_url_external" class="form-input" placeholder="http://example.com/thumbnail.jpg" value="<?php echo htmlspecialchars($_POST['image_url_external'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="gallery_uploads">Unggah Gambar Galeri (bisa pilih banyak file)</label>
            <input type="file" name="gallery_uploads[]" id="gallery_uploads" class="form-input" accept="image/*" multiple>
             <p style="font-size: 0.8rem; color: #666; margin-top: 5px;">Atau masukkan URL gambar galeri eksternal (satu URL per baris):</p>
            <textarea name="gallery_urls_external" class="form-textarea" placeholder="http://example.com/gallery1.jpg&#10;http://example.com/gallery2.png"><?php echo htmlspecialchars($_POST['gallery_urls_external'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="download_links">Link Download (satu link per baris)</label>
            <textarea name="download_links" id="download_links" class="form-textarea" placeholder="http://download.server/file1.zip&#10;http://download.server/file2.rar"><?php echo htmlspecialchars($_POST['download_links'] ?? ''); ?></textarea>
        </div>

        <div style="display: flex; gap: 1rem;">
            <div class="form-group" style="flex: 1;">
                <label for="views">Jumlah Views</label>
                <input type="number" name="views" id="views" class="form-input" value="<?php echo htmlspecialchars($_POST['views'] ?? rand(100, 10000)); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="likes">Jumlah Likes</label>
                <input type="number" name="likes" id="likes" class="form-input" value="<?php echo htmlspecialchars($_POST['likes'] ?? rand(10, 5000)); ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Tambah Video</button>
        <a href="<?php echo ADMIN_PATH; ?>video_list.php" class="btn btn-secondary">Kembali ke Daftar</a>
    </form>
</div>

<datalist id="actress-list">
    <?php
    foreach ($allActresses as $actress):
        if (is_array($actress) && isset($actress['name'])):
    ?>
        <option value="<?php echo htmlspecialchars($actress['name'] ?? ''); ?>">
    <?php
        endif;
    endforeach;
    ?>
</datalist>
<datalist id="category-list">
    <?php
    foreach ($allCategories as $category):
        if (is_array($category) && isset($category['name'])):
    ?>
        <option value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>">
    <?php
        endif;
    endforeach;
    ?>
</datalist>
<datalist id="tags-list">
    <?php
    foreach ($uniqueTags as $tag):
        if ($tag !== null && $tag !== ''):
    ?>
        <option value="<?php echo htmlspecialchars($tag); ?>">
    <?php
        endif;
    endforeach;
    ?>
</datalist>
<datalist id="studios-list">
    <?php
    foreach ($uniqueStudios as $studio):
        if ($studio !== null && $studio !== ''):
    ?>
        <option value="<?php echo htmlspecialchars($studio); ?>">
    <?php
        endif;
    endforeach;
    ?>
</datalist>

<?php
require_once __DIR__ . '/templates/footer.php';
?>