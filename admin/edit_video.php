<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$videoId = $_GET['id'] ?? null;
$video = null;
$message = null;
$error = null;

// Ambil data untuk autocomplete
$allActressesData = getAllActresses();
$allActresses = $allActressesData['data']; 

$allCategories = getCategories(); 
$uniqueTags = getUniqueTags();
$uniqueStudios = getUniqueStudios();

// Fungsi helper (jika belum ada di file ini, bisa ditambahkan)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_video_data') {
    $id = $_POST['video_id'];
    
    // Otomatis tambahkan aktris baru jika belum ada
    $actressesInput = trim($_POST['actresses'] ?? '');
    if (!empty($actressesInput)) {
        $actressNames = array_map('trim', explode(',', $actressesInput));
        addActressesIfNotExist($actressNames);
    }
    
    $categoryId = insertCategoryIfNotExist($_POST['category_name']);

    // Proses URL embed trailer
    $finalTrailerUrl = extractEmbedUrlFromCode(trim($_POST['trailer_embed_url'] ?? ''));

    // Proses URL galeri dari textarea
    $galleryUrlsFromText = !empty($_POST['gallery_image_urls']) ? array_filter(array_map('trim', explode("\n", $_POST['gallery_image_urls']))) : [];
    $finalGalleryUrls = !empty($galleryUrlsFromText) ? implode(',', $galleryUrlsFromText) : null;

    // Proses link download dari textarea
    $downloadLinksFromText = !empty($_POST['download_links']) ? array_filter(array_map('trim', explode("\n", $_POST['download_links']))) : [];
    $finalDownloadLinks = !empty($downloadLinksFromText) ? implode(',', $downloadLinksFromText) : null;

    // --- BARU: Proses URL Embed Utama & Tambahan ---
    $newEmbedUrl = trim($_POST['embed_url'] ?? '');
    $extraEmbedUrlsText = trim($_POST['extra_embed_urls_textarea'] ?? '');
    $extraEmbedUrlsArray = !empty($extraEmbedUrlsText) ? array_filter(array_map('trim', explode("\n", $extraEmbedUrlsText))) : [];
    $finalExtraEmbedUrls = !empty($extraEmbedUrlsArray) ? implode(',', $extraEmbedUrlsArray) : null;
    // --- AKHIR BAGIAN BARU ---
    
    $updateData = [
        'original_title' => $_POST['original_title'],
        'description' => $_POST['description'],
        'tags' => $_POST['tags'],
        'actresses' => $actressesInput,
        'studios' => $_POST['studios'],
        'category_id' => $categoryId,
        'image_url' => $_POST['image_url'],
        'views' => $_POST['views'],
        'likes' => $_POST['likes'],
        'trailer_embed_url' => $finalTrailerUrl,
        'gallery_image_urls' => $finalGalleryUrls,
        'download_links' => $finalDownloadLinks,
        'embed_url' => $newEmbedUrl,              // <-- Tambahkan ini
        'extra_embed_urls' => $finalExtraEmbedUrls, // <-- Tambahkan ini
    ];

    if (updateClonedVideo($id, $updateData)) {
        $message = "Video berhasil diperbarui!";
    } else {
        $error = "Gagal memperbarui video.";
    }
    // Muat ulang data video setelah update
    $video = getVideoByIdFromDB($id); 
} elseif ($videoId) {
    $video = getVideoByIdFromDB((int)$videoId);
    if (!$video) {
        $error = "Video tidak ditemukan.";
    }
} else {
    // Jika tidak ada ID, redirect ke daftar video
    header("Location: " . ADMIN_PATH . "video_list.php");
    exit();
}

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Edit Video</h1>
<p class="page-desc">Perbarui detail untuk video yang dipilih.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<?php if ($video): ?>
    <div class="card">
        <div class="card-header">
            <h3>Mengedit: <?php echo htmlspecialchars($video['original_title'] ?? 'Judul Tidak Diketahui'); ?></h3>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit_video_data">
            <input type="hidden" name="video_id" value="<?php echo htmlspecialchars($video['id'] ?? ''); ?>">

            <div class="form-group"><label for="original_title">Judul Video</label><input type="text" name="original_title" id="original_title" class="form-input" value="<?php echo htmlspecialchars($video['original_title'] ?? ''); ?>" required></div>
            
            <div class="form-group"><label for="embed_url">URL Embed Utama</label><input type="url" name="embed_url" id="embed_url" class="form-input" value="<?php echo htmlspecialchars($video['embed_url'] ?? ''); ?>" placeholder="https://..." required></div>

            <div class="form-group">
                <label for="extra_embed_urls_textarea">URL Embed Tambahan (satu URL per baris)</label>
                <textarea name="extra_embed_urls_textarea" id="extra_embed_urls_textarea" class="form-textarea" rows="4" placeholder="https://server2.com/...&#10;https://server3.com/..."><?php 
                    $extra_urls = !empty($video['extra_embed_urls']) ? explode(',', $video['extra_embed_urls']) : [];
                    echo htmlspecialchars(implode("\n", array_map('trim', $extra_urls))); 
                ?></textarea>
            </div>

            <div class="form-group"><label for="description">Deskripsi</label><textarea name="description" id="description" class="form-textarea"><?php echo htmlspecialchars($video['description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label for="tags">Genres (pisahkan koma)</label><input type="text" name="tags" id="tags" class="form-input" list="tags-list" value="<?php echo htmlspecialchars($video['tags'] ?? ''); ?>"></div>
            <div class="form-group"><label for="actresses_edit">Aktris (pisahkan dengan koma)</label><input type="text" name="actresses" id="actresses_edit" class="form-input" list="actress-list" value="<?php echo htmlspecialchars($video['actresses'] ?? ''); ?>"></div>
            <div class="form-group"><label for="studios">Studio (pisahkan dengan koma)</label><input type="text" name="studios" id="studios" class="form-input" list="studios-list" value="<?php echo htmlspecialchars($video['studios'] ?? ''); ?>"></div>
            <div class="form-group"><label for="category_name">Kategori</label><input type="text" name="category_name" id="category_name" class="form-input" list="category-list" value="<?php echo htmlspecialchars($video['category_name'] ?? ''); ?>" placeholder="Ketik nama kategori"></div>
            <div class="form-group"><label for="image_url">URL Thumbnail</label><input type="url" name="image_url" id="image_url" class="form-input" value="<?php echo htmlspecialchars($video['image_url'] ?? ''); ?>">
                <?php if (!empty($video['image_url'])): ?><img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="Current Thumbnail" style="max-width: 200px; margin-top: 10px; border-radius: 5px;"><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="trailer_embed_url">Kode Embed Trailer</label>
                <textarea name="trailer_embed_url" id="trailer_embed_url" class="form-textarea" placeholder="<iframe src='...'></iframe> atau URL embed trailer"><?php echo htmlspecialchars($video['trailer_embed_url'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="gallery_image_urls">URL Gambar Galeri (satu URL per baris)</label>
                <textarea name="gallery_image_urls" id="gallery_image_urls" class="form-textarea" rows="5" placeholder="http://example.com/gallery1.jpg&#10;http://example.com/gallery2.png"><?php 
                    $gallery_urls = !empty($video['gallery_image_urls']) ? explode(',', $video['gallery_image_urls']) : [];
                    echo htmlspecialchars(implode("\n", array_map('trim', $gallery_urls)));
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="download_links">Link Download (satu link per baris)</label>
                <textarea name="download_links" id="download_links" class="form-textarea" rows="5" placeholder="http://download.server/file1.zip&#10;http://download.server/file2.rar"><?php 
                    $download_links = !empty($video['download_links']) ? explode(',', $video['download_links']) : [];
                    echo htmlspecialchars(implode("\n", array_map('trim', $download_links)));
                ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;"><label for="views">Jumlah Views</label><input type="number" name="views" id="views" class="form-input" value="<?php echo htmlspecialchars($video['views'] ?? 0); ?>"></div>
                <div class="form-group" style="flex: 1;"><label for="likes">Jumlah Likes</label><input type="number" name="likes" id="likes" class="form-input" value="<?php echo htmlspecialchars($video['likes'] ?? 0); ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            <a href="<?php echo ADMIN_PATH; ?>video_list.php" class="btn btn-secondary">Kembali ke Daftar</a>
        </form>
    </div>
<?php elseif ($error): ?>
    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <a href="<?php echo ADMIN_PATH; ?>video_list.php" class="btn btn-secondary">Kembali ke Daftar Video</a>
<?php endif; ?>

<datalist id="actress-list">
    <?php foreach ($allActresses as $actress): if (is_array($actress) && isset($actress['name'])): ?>
        <option value="<?php echo htmlspecialchars($actress['name'] ?? ''); ?>">
    <?php endif; endforeach; ?>
</datalist>
<datalist id="category-list">
    <?php foreach ($allCategories as $category): if (is_array($category) && isset($category['name'])): ?>
        <option value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>">
    <?php endif; endforeach; ?>
</datalist>
<datalist id="tags-list">
    <?php foreach ($uniqueTags as $tag): if ($tag !== null && $tag !== ''): ?>
        <option value="<?php echo htmlspecialchars($tag); ?>">
    <?php endif; endforeach; ?>
</datalist>
<datalist id="studios-list">
    <?php foreach ($uniqueStudios as $studio): if ($studio !== null && $studio !== ''): ?>
        <option value="<?php echo htmlspecialchars($studio); ?>">
    <?php endif; endforeach; ?>
</datalist>

<?php
require_once __DIR__ . '/templates/footer.php';
?>