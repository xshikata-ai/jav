<?php
// File: videos.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Ambil parameter dari URL
$searchKeyword = $_GET['search'] ?? null;
$categorySlug = $_GET['category'] ?? null;
$tag = isset($_GET['tag']) ? urldecode($_GET['tag']) : null;
$sort = $_GET['sort'] ?? 'latest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$desktopCols = getSetting('grid_columns_desktop') ?? 4;
if ($desktopCols == 6) {
    $videosPerPage = 18; 
} elseif ($desktopCols == 5) {
    $videosPerPage = 15;
} else {
    $videosPerPage = 12;
}

$offset = ($page - 1) * $videosPerPage;

// --- Variabel ini akan dibaca oleh header.php untuk SEO ---
$category = null;
$categoryId = null;
if ($categorySlug) {
    $allCategories = getCategories();
    foreach ($allCategories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $category = $cat;
            $categoryId = $cat['id'];
            break;
        }
    }
}

// --- Logika Sorting ---
$orderBy = 'id';
$sortLabel = 'Latest'; // Default label

if (empty($searchKeyword)) { // Sorting tidak berlaku jika sedang mencari
    switch ($sort) {
        case 'views':
            $orderBy = 'views';
            $sortLabel = 'Most Viewed'; // Label untuk bar
            break;
        case 'likes':
            $orderBy = 'likes';
            $sortLabel = 'Most Liked'; // Label untuk bar
            break;
        case 'latest':
        default:
            $orderBy = 'id';
            $sortLabel = 'Latest'; // Label untuk bar
            break;
    }
}

// --- MODIFIKASI: Tentukan judul HANYA untuk bar oranye ---
// Variabel $pageTitle (untuk SEO <head>) akan di-set secara otomatis di header.php
// Kita buat variabel baru $orangeBarTitle untuk <h2> di <body>

$orangeBarTitle = ''; // Kosongkan

if ($searchKeyword) {
    $orangeBarTitle = 'Search Results';
} elseif ($category) {
    $orangeBarTitle = "Category: " . htmlspecialchars($category['name']);
} elseif ($tag) {
    $orangeBarTitle = "Genre: " . htmlspecialchars($tag);
} else {
    // Ini adalah halaman /videos (default)
    // Gunakan $sortLabel yang sudah kita tentukan
    if ($sortLabel == 'Latest') {
         $orangeBarTitle = 'New Videos'; // Sesuai permintaan
    } elseif ($sortLabel == 'Most Viewed') {
         $orangeBarTitle = 'Most Viewed Videos';
    } elseif ($sortLabel == 'Most Liked') {
         $orangeBarTitle = 'Most Liked Videos';
    } else {
        // Fallback
         $orangeBarTitle = $sortLabel . " Videos";
    }
}
// --- AKHIR MODIFIKASI ---

// Panggilan fungsi ke database
$videos = getVideosFromDB($videosPerPage, $offset, $searchKeyword, $categoryId, $tag, $orderBy, 'DESC');
$totalVideos = getTotalVideoCountDB($searchKeyword, $categoryId, $tag);
$totalPages = ceil($totalVideos / $videosPerPage);

$enable_border = getSetting('enable_category_border');

$storyVideos = getVideosFromDB(8, 0, '', null, null, 'views', 'DESC');

// --- PENTING: header.php dipanggil SETELAH $searchKeyword, $category, $tag didefinisikan ---
// Ini akan mengatur $pageTitle di <head> secara terpisah menggunakan data SEO
require_once __DIR__ . '/templates/header.php';
?>

<?php if (!empty($storyVideos)): ?>
<section class="story-section">
    <div class="story-scroll-container">

        <?php foreach ($storyVideos as $storyVideo): ?>
            <?php
            // Ambil gambar pertama dari galeri
            $gallery_images = !empty($storyVideo['gallery_image_urls']) ? explode(',', $storyVideo['gallery_image_urls']) : [];

            // Jika galeri tidak ada, gunakan thumbnail utama sebagai fallback
            $story_image_url = !empty($gallery_images) ? trim($gallery_images[0]) : htmlspecialchars($storyVideo['image_url']);

            // Jika fallback juga kosong, gunakan default
            if (empty($story_image_url)) {
                $story_image_url = ASSETS_PATH . 'images/actress/default.jpg';
            }
            ?>

            <a href="<?php echo BASE_URL . htmlspecialchars($storyVideo['slug']); ?>" class="story-item">
                <div class="story-image-wrapper">
                    <img src="<?php echo htmlspecialchars($story_image_url); ?>" alt="<?php echo htmlspecialchars($storyVideo['original_title']); ?>">
                </div>
            </a>
        <?php endforeach; ?>

    </div>
</section>
<?php endif; ?>

<section class="video-section">
    <div class="section-header"> <h2 class="section-title">
            <i class="ph-fill ph-grid-four icon"></i>
            <span><?php echo $orangeBarTitle; // Menggunakan variabel baru ?></span>
        </h2>
    </div> <?php if (!empty($videos)): ?>
        <div class="video-grid">
            <?php foreach ($videos as $video): ?>
                <?php
                $border_style = '';
                if ($enable_border === '1' && !empty($video['category_color'])) {
                    $border_style = 'style="border-color: ' . htmlspecialchars($video['category_color']) . ';"';
                }
                ?>
                <a href="<?php echo BASE_URL . htmlspecialchars($video['slug']); ?>" class="video-card" <?php echo $border_style; ?>>
                    <div class="thumbnail-container">
                        <?php if (!empty($video['category_name'])): ?>
                            <span class="badge category-badge" style="background-color: <?php echo htmlspecialchars($video['category_color'] ?? '#D91881'); ?>;"><?php echo htmlspecialchars($video['category_name']); ?></span>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="<?php echo htmlspecialchars($video['original_title']); ?>" loading="lazy">
                        <span class="badge duration-badge"><i class="ph ph-timer"></i><?php echo formatDuration($video['duration']); ?></span>
                        <?php if (!empty($video['quality'])): ?>
                            <span class="badge quality-badge"><?php echo htmlspecialchars($video['quality']); ?></span>
                        <?php endif; ?>

                        <div class="portrait-info-overlay">
                            <div class="portrait-meta">
                                <?php if (!empty($video['quality'])): ?>
                                    <span class="portrait-meta-item meta-quality"><?php echo htmlspecialchars($video['quality']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($video['duration'])): ?>
                                    <span class="portrait-meta-item meta-duration"><?php echo formatDurationToMinutes($video['duration']); ?></span>
                                <?php endif; ?>
                                <span class="portrait-title"><?php echo htmlspecialchars(getThumbnailTitle($video['original_title'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="video-info">
                        <h3><?php echo htmlspecialchars($video['original_title']); ?></h3>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php 
        // --- PAGINATION LAMA DIHAPUS DAN DIGANTI INI ---
        if ($totalPages > 1) {
            // Panggil fungsi pagination baru
            // Parameter ke-3 adalah 'videos' (nama file ini)
            // Parameter ke-4 adalah $_GET (untuk menyimpan filter 'sort', 'search', dll)
            generatePagination($page, $totalPages, 'videos', $_GET);
        }
        // --- AKHIR BLOK PAGINATION BARU ---
        ?>
       
        
<?php else: ?>
        <div style="text-align: center; padding: 2rem 1rem; color: var(--text-secondary); border: 1px dashed var(--border-color); border-radius: var(--border-radius-md); background: var(--bg-secondary);">
            <?php if ($searchKeyword): // Cek apakah ini adalah halaman pencarian ?>
                <p style="font-size: 1.2rem; color: var(--text-primary); margin-bottom: 0.75rem; font-weight: 600;">No videos found for "<?php echo htmlspecialchars($searchKeyword); ?>"</p>
                <p style="font-size: 0.9rem;">Please check your spelling or try searching for a specific video code (e.g., "SSIS-123") or a actress name (e.g., "Anri okita").</p>
            <?php else: // Ini adalah halaman kategori/tag/dll yang kosong ?>
                <p style="font-size: 1.2rem; color: var(--text-primary);">No videos were found for these criteria.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>