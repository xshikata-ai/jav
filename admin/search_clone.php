<?php
// File: admin/search_clone.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$searchKeyword = $_GET['keyword'] ?? '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$apiSource = $_GET['api_source'] ?? 'earnvids'; 

$searchResults = null;
$cloneMessage = null;
$cloneError = null;
$selectedVideoForEdit = null;

// Ambil data untuk autocomplete
$allCategories = getCategories();

$allActressesData = getAllActresses();
$allActresses = $allActressesData['data'];

$uniqueTags = getUniqueTags();
$uniqueStudios = getUniqueStudios();

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


// Penanganan untuk kloning TUNGGAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_clone') {
    
    $actressesInput = trim($_POST['actresses'] ?? '');
    if (!empty($actressesInput)) {
        $actressNames = array_map('trim', explode(',', $actressesInput));
        addActressesIfNotExist($actressNames);
    }

    $fileCodeToClone = $_POST['file_code'];
    $apiSourceToClone = $_POST['api_source'];
    $cloneUrl = '';
    $embedBaseDomain = '';
    $embedBasePath = '';
    if ($apiSourceToClone === 'earnvids') {
        $cloneUrl = EARNVIDS_CLONE_URL_BASE . "?key=" . urlencode(EARNVIDS_API_KEY) . "&file_code=" . urlencode($fileCodeToClone);
        $embedBaseDomain = EARNVIDS_EMBED_NEW_DOMAIN;
        $embedBasePath = EARNVIDS_EMBED_NEW_PATH;
    } elseif ($apiSourceToClone === 'streamhg') {
        $cloneUrl = STREAMHG_CLONE_URL_BASE . "?key=" . urlencode(STREAMHG_CLONE_API_KEY) . "&file_code=" . urlencode($fileCodeToClone);
        $embedBaseDomain = STREAMHG_EMBED_NEW_DOMAIN;
        $embedBasePath = STREAMHG_EMBED_NEW_PATH;
    }

    if (empty($cloneUrl)) {
        $cloneError = "Sumber API untuk kloning tidak valid.";
    } else {
        $cloneResults = makeExternalApiCall($cloneUrl);
        if (isset($cloneResults['status']) && $cloneResults['status'] === 200 && isset($cloneResults['result'])) {
            $clonedVideoData = $cloneResults['result'];
            $generatedEmbedUrl = $embedBaseDomain . $embedBasePath . ($clonedVideoData['filecode'] ?? $fileCodeToClone);
            $categoryId = insertCategoryIfNotExist($_POST['category_name']);
            
            $randomViews = rand(1000, 10000);
            $randomLikes = rand(500, $randomViews);

            // Proses data baru dari form
            $finalTrailerUrl = extractEmbedUrlFromCode(trim($_POST['trailer_embed_url'] ?? ''));
            $galleryUrls = !empty($_POST['gallery_image_urls']) ? implode(',', array_filter(array_map('trim', explode("\n", $_POST['gallery_image_urls'])))) : null;
            $downloadLinks = !empty($_POST['download_links']) ? implode(',', array_filter(array_map('trim', explode("\n", $_POST['download_links'])))) : null;

            $dataToInsert = [
                'original_title' => $_POST['original_title'], 
                'description' => $_POST['description'],
                'tags' => $_POST['tags'], 
                'actresses' => $actressesInput, 
                'studios' => $_POST['studios'],
                'category_id' => $categoryId, 
                'embed_id' => $clonedVideoData['filecode'] ?? null,
                'embed_url' => $generatedEmbedUrl, 
                'api_source' => $apiSourceToClone,
                'image_url' => $_POST['original_image'], 
                'duration' => $_POST['original_duration'],
                'quality' => 'HD', 
                'views' => $randomViews, 
                'likes' => $randomLikes,
                'trailer_embed_url' => $finalTrailerUrl,
                'gallery_image_urls' => $galleryUrls,
                'download_links' => $downloadLinks,
            ];

            if (insertClonedVideo($dataToInsert)) {
                $cloneMessage = "Video berhasil dikloning dan disimpan.";
                $searchKeyword = '';
            } else {
                $cloneError = "Gagal menyimpan video (mungkin duplikat).";
            }
        } else {
            $cloneError = "Gagal kloning dari API eksternal: " . ($cloneResults['msg'] ?? 'Error');
        }
    }
}

// Logika untuk menampilkan hasil pencarian
if (!empty($searchKeyword)) {
    $searchUrl = BACKEND_API_URL . "?action=search&q=" . urlencode($searchKeyword) . "&page=" . $currentPage . "&api_source=" . urlencode($apiSource);
    $apiResults = callBackendApi($searchUrl);

    if ($apiResults && $apiResults['status'] === 'success' && !empty($apiResults['data'])) {
        $clonedEmbedIds = getAllClonedEmbedIds();
        $filteredVideos = [];
        foreach ($apiResults['data'] as $video) {
            if (isset($video['embed_id']) && !in_array($video['embed_id'], $clonedEmbedIds)) {
                $filteredVideos[] = $video;
            }
        }
        $searchResults = $apiResults;
        $searchResults['data'] = $filteredVideos;
    } else {
        $searchResults = $apiResults;
    }
}

// Logika untuk menampilkan form edit tunggal
if (isset($_GET['action']) && $_GET['action'] === 'select_for_edit' && isset($_GET['embed_id'])) {
    if (!$searchResults && !empty($_GET['keyword'])) {
         $searchUrl = BACKEND_API_URL . "?action=search&q=" . urlencode($_GET['keyword']) . "&page=" . (int)$_GET['page'] . "&api_source=" . urlencode($_GET['api_source']);
         $apiResults = callBackendApi($searchUrl);
         if ($apiResults && $apiResults['status'] === 'success' && !empty($apiResults['data'])) {
            $searchResults['data'] = $apiResults['data'];
         }
    }
    if ($searchResults && isset($searchResults['data'])) {
        foreach ($searchResults['data'] as $video) {
            if ($video['embed_id'] === $_GET['embed_id']) {
                $selectedVideoForEdit = $video;
                $selectedVideoForEdit['api_source'] = $apiSource;
                break;
            }
        }
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div id="loading-overlay">
    <div class="spinner"></div>
    <p id="loading-text">Sedang memproses, mohon tunggu...</p>
</div>

<h1 class="page-title">Cari & Kloning Video</h1>
<p class="page-desc">Cari video dari API eksternal, edit detail, atau kloning secara massal (Maks. 20 video per proses).</p>
<div class="card">
    <form method="GET" action="" class="form-inline">
        <div class="form-group"><label for="keyword">Kata Kunci</label><input type="text" name="keyword" id="keyword" class="form-input" placeholder="cth: big tits" value="<?php echo htmlspecialchars($searchKeyword); ?>" required></div>
        <div class="form-group" style="max-width: 120px;"><label for="page">Halaman</label><input type="number" name="page" id="page" class="form-input" placeholder="1" value="<?php echo htmlspecialchars($currentPage); ?>" min="1"></div>
        <div class="form-group" style="max-width: 180px;"><label for="api_source">Sumber API</label><select name="api_source" id="api_source" class="form-select"><option value="earnvids" <?php echo ($apiSource === 'earnvids') ? 'selected' : ''; ?>>Earnvids</option><option value="streamhg" <?php echo ($apiSource === 'streamhg') ? 'selected' : ''; ?>>Streamhg</option></select></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
    </form>
</div>
<?php if (isset($cloneMessage)): ?><div class="message success"><?php echo htmlspecialchars($cloneMessage); ?></div><?php endif; ?>
<?php if (isset($cloneError)): ?><div class="message error"><?php echo htmlspecialchars($cloneError); ?></div><?php endif; ?>

<?php if ($selectedVideoForEdit): ?>
    <div class="card">
        <div class="card-header"><h3>Edit & Kloning: <?php echo htmlspecialchars($selectedVideoForEdit['name'] ?? 'Video'); ?></h3></div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="process_clone">
            <input type="hidden" name="file_code" value="<?php echo htmlspecialchars($selectedVideoForEdit['embed_id'] ?? ''); ?>">
            <input type="hidden" name="original_image" value="<?php echo htmlspecialchars($selectedVideoForEdit['image'] ?? $selectedVideoForEdit['thumb'] ?? ''); ?>">
            <input type="hidden" name="original_duration" value="<?php echo htmlspecialchars($selectedVideoForEdit['duration'] ?? ''); ?>">
            <input type="hidden" name="api_source" value="<?php echo htmlspecialchars($selectedVideoForEdit['api_source'] ?? ''); ?>">
            <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($searchKeyword); ?>">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($currentPage); ?>">
            
            <div class="form-group"><label for="original_title">Judul Video</label><input type="text" name="original_title" id="original_title" class="form-input" value="<?php echo htmlspecialchars($selectedVideoForEdit['name'] ?? ''); ?>" required></div>
            <div class="form-group"><label for="description">Deskripsi</label><textarea name="description" id="description" class="form-textarea"><?php echo htmlspecialchars($selectedVideoForEdit['description'] ?? ''); ?></textarea></div>
            <div class="form-group"><label for="tags">Genres (pisahkan koma)</label><input type="text" name="tags" id="tags" class="form-input" list="tags-list" value="<?php echo htmlspecialchars($selectedVideoForEdit['tags'] ?? ''); ?>"></div>
            <div class="form-group"><label for="actresses_single">Aktris (pisahkan dengan koma)</label><input type="text" name="actresses" id="actresses_single" class="form-input" list="actress-list" value="<?php echo htmlspecialchars($selectedVideoForEdit['actresses'] ?? ''); ?>"></div>
            <div class="form-group"><label for="studios">Studio (pisahkan dengan koma)</label><input type="text" name="studios" id="studios" class="form-input" list="studios-list" value="<?php echo htmlspecialchars($selectedVideoForEdit['studios'] ?? ''); ?>"></div>
            <div class="form-group"><label for="category_name">Kategori</label><input type="text" name="category_name" id="category_name" class="form-input" list="category-list" value="<?php echo htmlspecialchars($selectedVideoForEdit['category_name'] ?? ''); ?>" placeholder="Ketik nama kategori baru atau yang sudah ada"></div>
            
            <div class="form-group">
                <label for="trailer_embed_url">Kode Embed Trailer</label>
                <textarea name="trailer_embed_url" id="trailer_embed_url" class="form-textarea" placeholder="<iframe src='...'></iframe> atau URL embed trailer"></textarea>
            </div>
            
            <div class="form-group">
                <label for="gallery_image_urls">URL Gambar Galeri (satu URL per baris)</label>
                <textarea name="gallery_image_urls" id="gallery_image_urls" class="form-textarea" rows="4" placeholder="http://example.com/gallery1.jpg&#10;http://example.com/gallery2.png"></textarea>
            </div>
            
            <div class="form-group">
                <label for="download_links">Link Download (satu link per baris)</label>
                <textarea name="download_links" id="download_links" class="form-textarea" rows="4" placeholder="http://download.server/file1.zip&#10;http://download.server/file2.rar"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem;"><div class="form-group" style="flex: 1;"><label for="views">Jumlah Views</label><input type="number" name="views" id="views" class="form-input" value="<?php echo rand(1500, 10000); ?>" max="10000"></div><div class="form-group" style="flex: 1;"><label for="likes">Jumlah Likes</label><input type="number" name="likes" id="likes" class="form-input" value="<?php echo rand(100, 5000); ?>" max="10000"></div></div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Kloning & Simpan</button>
            <a href="<?php echo ADMIN_PATH; ?>search_clone.php?keyword=<?php echo urlencode($searchKeyword); ?>&page=<?php echo urlencode($currentPage); ?>&api_source=<?php echo urlencode($apiSource); ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
        </form>
    </div>
<?php endif; ?>


<?php if (!empty($searchKeyword) && !$selectedVideoForEdit): ?>
    <form id="bulk-clone-form">
        <div class="bulk-actions-bar card">
            <div class="select-all-container"><input type="checkbox" id="select_all_videos"><label for="select_all_videos">Pilih 20 Teratas</label></div>
            <div class="bulk-category-inputs"><select name="existing_category_id" id="existing_category_id" class="form-select"><option value="">Pilih Kategori Yang Ada</option><?php foreach ($allCategories as $category): ?><option value="<?php echo htmlspecialchars($category['id'] ?? ''); ?>"><?php echo htmlspecialchars($category['name'] ?? ''); ?></option><?php endforeach; ?></select><input type="text" name="new_category_name" id="new_category_name" class="form-input" placeholder="Atau buat kategori baru..."></div>
            <div class="bulk-exclude-input"><input type="text" name="exclude_keywords" id="exclude_keywords" class="form-input" placeholder="Kecualikan keyword dari judul..."></div>
            <button type="button" id="start-bulk-clone-btn" class="btn btn-primary"><i class="fas fa-clone"></i> Kloning Video Terpilih</button>
        </div>
        <div class="card" style="border: none; padding: 0; background: transparent; box-shadow: none;">
            <div class="card-header"><h3>Hasil Pencarian untuk "<?php echo htmlspecialchars($searchKeyword); ?>" (<?php echo count($searchResults['data'] ?? []); ?> video ditemukan)</h3></div>
            <?php if ($searchResults && isset($searchResults['status']) && $searchResults['status'] === 'success' && !empty($searchResults['data'])): ?>
                <div class="video-grid">
                    <?php foreach ($searchResults['data'] as $video): $embed_id = htmlspecialchars($video['embed_id'] ?? ''); ?>
                        <div class="video-card selectable" data-embed-id="<?php echo $embed_id; ?>"><div class="card-checkbox"><input type="checkbox" class="video-checkbox" data-title="<?php echo htmlspecialchars($video['name'] ?? ''); ?>" data-image="<?php echo htmlspecialchars($video['image'] ?? ''); ?>" data-duration="<?php echo htmlspecialchars($video['duration'] ?? ''); ?>" data-api-source="<?php echo htmlspecialchars($apiSource); ?>"></div><div class="thumbnail-container"><img src="<?php echo htmlspecialchars($video['image'] ?? ''); ?>" alt="<?php echo htmlspecialchars($video['name'] ?? ''); ?>" class="thumbnail" loading="lazy"></div><div class="video-info"><h4 class="video-title"><?php echo htmlspecialchars($video['name'] ?? ''); ?></h4><div class="video-actions"><a href="?keyword=<?php echo urlencode($searchKeyword); ?>&page=<?php echo $currentPage; ?>&api_source=<?php echo $apiSource; ?>&action=select_for_edit&embed_id=<?php echo urlencode($video['embed_id'] ?? ''); ?>" class="btn btn-secondary btn-sm action-btn"><i class="fas fa-edit"></i> Edit</a></div></div></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="message info">Tidak ada video baru yang ditemukan untuk kata kunci ini (mungkin semua sudah dikloning).</p>
            <?php endif; ?>
        </div>
    </form>
<?php elseif(!$selectedVideoForEdit): ?>
    <div class="message info">Silakan masukkan kata kunci untuk memulai pencarian video.</div>
<?php endif; ?>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startCloneBtn = document.getElementById('start-bulk-clone-btn');
    const loadingOverlay = document.getElementById('loading-overlay');
    const loadingText = document.getElementById('loading-text');
    const selectAllCheckbox = document.getElementById('select_all_videos');
    const allVideoCheckboxes = document.querySelectorAll('.video-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxesArray = Array.from(allVideoCheckboxes);
            const videosToSelect = checkboxesArray.slice(0, 20);
            videosToSelect.forEach(checkbox => { checkbox.checked = this.checked; });
            if(this.checked) {
                checkboxesArray.slice(20).forEach(checkbox => checkbox.checked = false);
            }
        });
    }

    if (startCloneBtn) {
        startCloneBtn.addEventListener('click', async function() {
            const checkedBoxes = document.querySelectorAll('.video-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Pilih setidaknya satu video untuk dikloning.');
                return;
            }
            if (checkedBoxes.length > 20) {
                alert('Anda hanya bisa memilih maksimal 20 video untuk dikloning secara massal.');
                return;
            }
            const newCategory = document.getElementById('new_category_name').value;
            const existingCategory = document.getElementById('existing_category_id').value;
            const excludeKeywords = document.getElementById('exclude_keywords').value;
            const videosToClone = Array.from(checkedBoxes).map(cb => ({
                embed_id: cb.closest('.selectable').dataset.embedId,
                title: cb.dataset.title,
                image: cb.dataset.image,
                duration: cb.dataset.duration,
                api_source: cb.dataset.apiSource
            }));
            loadingOverlay.style.display = 'flex';
            let successCount = 0;
            let failedCount = 0;
            const totalVideos = videosToClone.length;
            for (let i = 0; i < totalVideos; i++) {
                const video = videosToClone[i];
                loadingText.textContent = `Mengkloning video ${i + 1}/${totalVideos}...`;
                const formData = new FormData();
                formData.append('action', 'ajax_clone_single');
                formData.append('embed_id', video.embed_id);
                formData.append('original_title', video.title);
                formData.append('original_image', video.image);
                formData.append('original_duration', video.duration);
                formData.append('api_source', video.api_source);
                formData.append('new_category_name', newCategory);
                formData.append('existing_category_id', existingCategory);
                formData.append('exclude_keywords', excludeKeywords);
                try {
                    const response = await fetch('<?php echo BACKEND_API_URL; ?>', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        successCount++;
                    } else {
                        failedCount++;
                        console.error('Gagal kloning:', result.message);
                    }
                } catch (error) {
                    failedCount++;
                    console.error('Error koneksi:', error);
                }
            }
            loadingOverlay.style.display = 'none';
            alert(`Proses kloning selesai.\nBerhasil: ${successCount}\nGagal: ${failedCount}`);
            window.location.reload();
        });
    }
});
</script>

<?php
require_once __DIR__ . '/templates/footer.php';
?>