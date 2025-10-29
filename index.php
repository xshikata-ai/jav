<?php
// File: index.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Fungsi helper untuk memotong judul
function truncateTitle($title, $length = 45, $ellipsis = '...') {
    if (strlen($title) > $length) {
        return substr($title, 0, $length) . $ellipsis;
    }
    return $title;
}

$desktopCols = getSetting('grid_columns_desktop') ?? 4;
if ($desktopCols == 6) {
    $videosToShowOnHomepage = 12;
} elseif ($desktopCols == 5) {
    $videosToShowOnHomepage = 10;
} else {
    $videosToShowOnHomepage = 8;
}

$latestCacheKey = 'homepage_latest_videos_' . $videosToShowOnHomepage;
$popularCacheKey = 'homepage_popular_videos_' . $videosToShowOnHomepage;
$cacheTTL = 300; // Cache homepage selama 5 menit

$latestVideos = get_from_cache($latestCacheKey, $cacheTTL);
if ($latestVideos === false) {
    $latestVideos = getVideosFromDB($videosToShowOnHomepage, 0, '', null, null, 'id', 'DESC');
    save_to_cache($latestCacheKey, $latestVideos);
}

$popularVideos = get_from_cache($popularCacheKey, $cacheTTL);
if ($popularVideos === false) {
    $popularVideos = getVideosFromDB($videosToShowOnHomepage, 0, '', null, null, 'views', 'DESC');
    save_to_cache($popularCacheKey, $popularVideos);
}

$enable_border = getSetting('enable_category_border');
$storyVideos = getVideosFromDB(8, 0, '', null, null, 'views', 'DESC'); // Story mungkin tidak perlu di-cache seketat video utama

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
    <div class="section-header">
        <h2 class="section-title">
            <i class="ph-fill ph-sparkle icon"></i>
            <span>Latest Videos</span>
        </h2>
        <div class="header-connector-line"></div> <a href="<?php echo BASE_URL; ?>videos?sort=latest" class="more-link">
            <span>More</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"></path></svg>
        </a>
    </div>
    
    <?php if (!empty($latestVideos)): ?>
        <div class="video-grid">
            <?php foreach ($latestVideos as $video): ?>
                <?php
                $border_style = '';
                if ($enable_border === '1' && !empty($video['category_color'])) {
                    $border_style = 'style="border-color: ' . htmlspecialchars($video['category_color']) . ';"';
                }
                ?>
                <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($video['slug']); ?>" class="video-card" <?php echo $border_style; ?>>
                    <div class="thumbnail-container">
                        <img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="<?php echo htmlspecialchars($video['original_title']); ?>" loading="lazy">
                        
                        <?php if (!empty($video['category_name'])): ?>
                            <span class="badge category-badge" style="background-color: <?php echo htmlspecialchars($video['category_color'] ?? '#D91881'); ?>;"><?php echo htmlspecialchars($video['category_name']); ?></span>
                        <?php endif; ?>
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
    <?php else: ?>
        <p>No videos yet.</p>
    <?php endif; ?>
</section>

<section class="video-section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="ph-fill ph-fire icon"></i>
            <span>Most Popular Videos</span>
        </h2>
        <div class="header-connector-line"></div> <a href="<?php echo BASE_URL; ?>videos?sort=views" class="more-link">
            <span>More</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"></path></svg>
        </a>
    </div>

    <?php if (!empty($popularVideos)): ?>
        <div class="video-grid">
            <?php foreach ($popularVideos as $video): ?>
                 <?php
                $border_style = '';
                if ($enable_border === '1' && !empty($video['category_color'])) {
                    $border_style = 'style="border-color: ' . htmlspecialchars($video['category_color']) . ';"';
                }
                ?>
                <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($video['slug']); ?>" class="video-card" <?php echo $border_style; ?>>
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
    <?php else: ?>
        <p>Popular video data is not yet available.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
