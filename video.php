<?php
// File: video.php
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

$videoSlug = $_GET['slug'] ?? null;
$video = $videoSlug ? getVideoBySlugFromDB($videoSlug) : null;

$video_card_layout = getSetting('video_card_layout') ?? 'landscape';
$enable_border = getSetting('enable_category_border');

// Logika untuk Mengumpulkan, Mengidentifikasi, dan Mengurutkan Server
$sorted_servers = [];
if ($video) {
    $all_embed_servers = [];
    if (!empty($video['embed_url'])) { $all_embed_servers[] = $video['embed_url']; }
    if (!empty($video['extra_embed_urls'])) {
        $extra_servers = array_map('trim', explode(',', $video['extra_embed_urls']));
        $all_embed_servers = array_merge($all_embed_servers, $extra_servers);
    }
    $all_embed_servers = array_unique(array_filter($all_embed_servers));

    $vh_urls = []; $sw_urls = []; $dd_urls = []; $other_urls = [];
    $vh_domains = [parse_url(EARNVIDS_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dingtezuni.com', 'movearnpre.com', 'ryderjet.com'];
    $sw_domains = [parse_url(STREAMHG_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dhcplay.com', 'stbhg.click'];
    
    // --- MODIFIKASI 1: Menambahkan domain vidpply/vidply ke daftar DD ---
    $dd_domains = [parse_url(DOODSTREAM_EMBED_NEW_DOMAIN, PHP_URL_HOST), 'dsvplay.com', 'vidpply.com', 'vidply.com'];

    foreach ($all_embed_servers as $url) {
        $host = str_replace('www.', '', parse_url($url, PHP_URL_HOST));
        $found = false;
        foreach ($vh_domains as $d) { if (strpos($host, $d) !== false) { $vh_urls[] = $url; $found = true; break; } } if ($found) continue;
        foreach ($sw_domains as $d) { if (strpos($host, $d) !== false) { $sw_urls[] = $url; $found = true; break; } } if ($found) continue;
        foreach ($dd_domains as $d) { if (strpos($host, $d) !== false) { $dd_urls[] = $url; $found = true; break; } } if ($found) continue;
        $other_urls[] = $url;
    }
    $sorted_servers = array_merge($vh_urls, $sw_urls, $dd_urls, $other_urls);
}

function time_ago_en($datetime) { // Ganti nama fungsi
    if (empty($datetime)) { return "a moment ago"; } // Ganti
    $time = strtotime($datetime); $now = time(); $diff = $now - $time;
    if ($diff < 60) { return "just now"; } // Ganti
    $minute = 60; $hour = $minute * 60; $day = $hour * 24; $month = $day * 30; $year = $day * 365;
    if ($diff < $hour) { $val = floor($diff / $minute); return "$val minutes ago"; } // Ganti
    if ($diff < $day) { $val = floor($diff / $hour); return "$val hours ago"; } // Ganti
    if ($diff < $month) { $val = floor($diff / $day); return "$val days ago"; } // Ganti
    if ($diff < $year) { $val = floor($diff / $month); return "$val months ago"; } // Ganti
    $val = floor($diff / $year); return "$val years ago"; // Ganti
}

require_once __DIR__ . '/templates/header.php';
?>

<?php if ($video): ?>
<div class="container">
    <div class="main-player-area">
         <div class="player-wrapper">
                <iframe id="video-player-iframe" src="<?php echo htmlspecialchars($sorted_servers[0] ?? ''); ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="<?php echo htmlspecialchars($video['original_title'] ?? 'Video'); ?>"></iframe>
        </div>
    </div>

    <?php if (count($sorted_servers) > 1): ?>
    <div class="server-buttons-wrapper">
        <?php foreach ($sorted_servers as $index => $server_url): ?>
            <?php
            // --- MODIFIKASI 2: Logika pelabelan tombol ---
            
            // Ambil host
            $host = str_replace('www.', '', parse_url($server_url, PHP_URL_HOST));
            $label = ''; // Kosongkan label awal

            // Cek domain VH, SW, DD (sesuai permintaan Anda)
            $is_vh = false; foreach($vh_domains as $d) { if(strpos($host, $d) !== false) { $is_vh = true; break; }}
            $is_sw = false; foreach($sw_domains as $d) { if(strpos($host, $d) !== false) { $is_sw = true; break; }}
            $is_dd = false; foreach($dd_domains as $d) { if(strpos($host, $d) !== false) { $is_dd = true; break; }}

            if ($is_vh) {
                $label = 'VH';
            } elseif ($is_sw) {
                $label = 'SW';
            } elseif ($is_dd) {
                $label = 'DD'; // Sekarang 'vidpply' akan masuk ke sini
            } else {
                // Fallback: Jika ada server lain yang tidak dikenal
                $domain_parts = explode('.', $host);
                $domain_name = $domain_parts[0];
                $label = ucfirst(strtolower($domain_name));
            }
            
            // Fallback jika label masih kosong
            if (empty(trim($label))) {
                $label = 'Server ' . ($index + 1);
            }
            ?>
            <a href="#" class="server-btn <?php echo ($index === 0) ? 'active' : ''; ?>" data-embed-url="<?php echo htmlspecialchars($server_url); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 256 256"><path d="M224,88H32a8,8,0,0,0-8,8V152a8,8,0,0,0,8,8H224a8,8,0,0,0,8-8V96A8,8,0,0,0,224,88Zm-8,56H40V104H216ZM128,128a12,12,0,1,1,12-12A12,12,0,0,1,128,128Zm88-64H40a16,16,0,0,0-16,16v8H224V80A16,16,0,0,0,216,64Zm0,128H40a16,16,0,0,0-16,16v8H224v-8A16,16,0,0,0,216,192Z"></path></svg>
                <?php echo htmlspecialchars($label); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="video-title-box">
       <h1 class="title-main"><?php echo htmlspecialchars($video['original_title'] ?? 'Unknown Title'); ?></h1>
    </div>

    <div class="detail-wrapper">
        <nav class="detail-tabs">
            <a href="#info" class="tab-link active">» INFO</a>
            <?php if (!empty($video['trailer_embed_url'])): ?><a href="#trailer" class="tab-link">» TRAILER</a><?php endif; ?>
            <?php if (!empty($video['gallery_image_urls'])): ?><a href="#gallery" class="tab-link">» GALLERY</a><?php endif; ?>
            <?php if (!empty($video['download_links'])): ?><a href="#download" class="tab-link">» DOWNLOAD</a><?php endif; ?>
        </nav>

        <div class="detail-content-wrapper">
            <div id="info-panel" class="detail-content-panel active">
                <div class="detail-content-grid">
                    <div class="detail-poster"><img src="<?php echo htmlspecialchars($video['image_url'] ?? ''); ?>" alt="<?php echo htmlspecialchars($video['original_title'] ?? ''); ?>"></div>
                    <div class="detail-meta">
                        <ul>
                            <?php if (!empty($video['studios'])): ?><li><i class="ph-fill ph-camera icon"></i><strong>Studio:</strong><span class="value"><?php echo htmlspecialchars($video['studios']); ?></span></li><?php endif; ?>
                            <?php if (!empty($video['actresses'])): ?><li><i class="ph-fill ph-user icon"></i><strong>Cast(s):</strong><span class="value"><?php $actressesArray = array_filter(array_map('trim', explode(',', $video['actresses']))); $actressLinks = []; foreach ($actressesArray as $actressName) { $actressSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $actressName))); $actressLinks[] = '<a href="' . BASE_URL . 'actress/' . htmlspecialchars($actressSlug) . '">' . htmlspecialchars($actressName) . '</a>'; } echo implode(', ', $actressLinks); ?></span></li><?php endif; ?>
                            <?php if (!empty($video['tags'])): ?><li><i class="ph-fill ph-list icon"></i><strong>Genre(s):</strong><span class="value"><?php $tagsArray = array_filter(array_map('trim', explode(',', $video['tags']))); $tagLinks = []; foreach ($tagsArray as $tag) { $tagLinks[] = '<a href="' . BASE_URL . 'genres/tag/' . urlencode($tag) . '">' . htmlspecialchars($tag) . '</a>'; } echo implode(', ', $tagLinks); ?></span></li><?php endif; ?>
                            <li><i class="ph-fill ph-star icon"></i><strong>Quality:</strong><span class="value"><?php echo htmlspecialchars($video['quality'] ?? 'N/A'); ?></span></li>
                            <?php if (!empty($video['cloned_at'])): ?><li><i class="ph-fill ph-calendar icon"></i><strong>Release Date:</strong><span class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime($video['cloned_at']))); ?></span></li><?php endif; ?>
                            <li><i class="ph-fill ph-timer icon"></i><strong>Runtimes:</strong><span class="value"><?php echo formatDurationToMinutes($video['duration'] ?? 0); ?></span></li>
                            <li><i class="ph-fill ph-eye icon"></i><strong>Views:</strong><span class="value"><?php echo number_format($video['views'] ?? 0); ?></span></li>
                            <li><i class="ph-fill ph-thumbs-up icon"></i><strong>Likes:</strong><span class="value"><?php echo number_format($video['likes'] ?? 0); ?></span></li>
                        </ul>
                        <?php $description = $video['description'] ?? ''; $is_long_description = strlen($description) > 300; if (!empty($description)): ?>
                        <div class="detail-description"><div class="description-inner-wrapper"><i class="ph-fill ph-quotes icon-quote-start"></i><div class="description-text-wrapper <?php if ($is_long_description) echo 'is-collapsible'; ?>"><p><?php echo nl2br(htmlspecialchars($description)); ?></p></div><i class="ph-fill ph-quotes icon-quote-end"></i></div><?php if ($is_long_description): ?><a href="#" class="show-more-btn" data-text-more="Show more" data-text-less="Show less">Show more</a><?php endif; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($video['trailer_embed_url'])): ?><div id="trailer-panel" class="detail-content-panel"><div class="player-wrapper trailer-player"><iframe src="<?php echo htmlspecialchars($video['trailer_embed_url']); ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div></div><?php endif; ?>
            <?php if (!empty($video['gallery_image_urls'])): ?><div id="gallery-panel" class="detail-content-panel"><div class="gallery-grid"><?php $gallery_images = array_filter(array_map('trim', explode(',', $video['gallery_image_urls']))); foreach ($gallery_images as $index => $img_url): ?><a href="<?php echo htmlspecialchars($img_url); ?>" class="gallery-item" data-index="<?php echo $index; ?>"><img src="<?php echo htmlspecialchars($img_url); ?>" loading="lazy" alt="Gallery image <?php echo $index + 1; ?>"></a><?php endforeach; ?></div></div><?php endif; ?>
        </div>
    </div>
    
   <div class="related-video-wrapper">
        <section class="video-section"><div class="section-header"><h2 class="section-title"><i class="ph-fill ph-film-strip icon"></i><span>Related Videos</span></h2><div class="header-connector-line"></div></div>
            <?php
            $relatedVideos = [];
            $excludeIds = [$video['id']]; // Mulai dengan mengecualikan video saat ini
            $limit = 8; // Kita ingin total 8 video

            // Prioritas 1: Dapatkan berdasarkan Aktris
            if (!empty($video['actresses'])) {
                $relatedVideos = getRelatedVideosByActress($video['actresses'], $video['id'], $limit);
            }

            // Hitung berapa banyak yang kita temukan
            $foundCount = count($relatedVideos);
            
            // Prioritas 2: Dapatkan berdasarkan Tags (jika kita masih butuh)
            if ($foundCount < $limit && !empty($video['tags'])) {
                $needed = $limit - $foundCount;
                
                // Update daftar pengecualian
                foreach ($relatedVideos as $relVideo) {
                    $excludeIds[] = $relVideo['id'];
                }
                $excludeIds = array_unique($excludeIds);
                
                $tagVideos = getRelatedVideosByTags($video['tags'], $excludeIds, $needed);
                
                // Gabungkan hasilnya
                $relatedVideos = array_merge($relatedVideos, $tagVideos);
            }
            
            // Hitung lagi
            $foundCount = count($relatedVideos);

            // Prioritas 3: Fallback ke Kategori (Logika asli)
            if ($foundCount < $limit && !empty($video['category_id'])) {
                $needed = $limit - $foundCount;
                
                // Update daftar pengecualian
                foreach ($relatedVideos as $relVideo) {
                    $excludeIds[] = $relVideo['id'];
                }
                $excludeIds = array_unique($excludeIds);

                $categoryVideos = getRelatedVideosByCategoryFromDB($video['category_id'], $excludeIds, $needed);
                $relatedVideos = array_merge($relatedVideos, $categoryVideos);
            }

            // Hitung lagi
            $foundCount = count($relatedVideos);
            
            // Prioritas 4: Fallback ke Acak (Jika semua di atas gagal mengisi)
            if ($foundCount < $limit) { 
                $needed = $limit - $foundCount;
                
                // Update daftar pengecualian
                foreach ($relatedVideos as $relVideo) {
                    $excludeIds[] = $relVideo['id'];
                }
                $excludeIds = array_unique($excludeIds);

                $randomVideos = getRandomVideosFromDB($needed, $excludeIds); 
                $relatedVideos = array_merge($relatedVideos, $randomVideos);
            } 
            
            // Pastikan kita hanya memiliki $limit video
            $relatedVideos = array_slice($relatedVideos, 0, $limit);
            
            if (!empty($relatedVideos)): 
            ?>
                <div class="video-grid <?php echo $video_card_layout === 'portrait' ? 'layout-portrait' : 'layout-landscape'; ?>">
                    <?php foreach ($relatedVideos as $related): ?>
                         <?php
                        $border_style = '';
                        if ($enable_border === '1' && !empty($related['category_color'])) {
                            $border_style = 'style="border-color: ' . htmlspecialchars($related['category_color']) . ';"';
                        }
                        ?>
                        <div class="video-card" <?php echo $border_style; ?>>
                            <a href="<?php echo BASE_URL . htmlspecialchars($related['slug']); ?>" class="thumbnail-container">
                                <img src="<?php echo htmlspecialchars($related['image_url']); ?>" alt="<?php echo htmlspecialchars($related['original_title']); ?>" loading="lazy">
                                <?php if (!empty($related['category_name'])): ?>
                                    <span class="badge category-badge" style="background-color: <?php echo htmlspecialchars($related['category_color'] ?? '#D91881'); ?>;"><?php echo htmlspecialchars($related['category_name']); ?></span>
                                <?php endif; ?>
                                <span class="badge duration-badge"><i class="ph ph-timer"></i><?php echo formatDuration($related['duration']); ?></span>
                                <?php if (!empty($related['quality'])): ?>
                                    <span class="badge quality-badge"><?php echo htmlspecialchars($related['quality']); ?></span>
                                <?php endif; ?>
                                <div class="portrait-info-overlay">
                                    <div class="portrait-meta">
                                        <span class="portrait-meta-item meta-quality"><?php echo htmlspecialchars($related['quality']); ?></span>
                                        <span class="portrait-meta-item meta-duration"><?php echo formatDurationToMinutes($related['duration']); ?></span>
                                        <span class="portrait-title"><?php echo htmlspecialchars(getThumbnailTitle($related['original_title'])); ?></span>
                                    </div>
                                </div>
                            </a>
                            <div class="video-info">
                                <h3><a href="<?php echo BASE_URL . htmlspecialchars($related['slug']); ?>"><?php echo htmlspecialchars($related['original_title']); ?></a></h3>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p class="message info" style="text-align: center; padding: 2rem;">No related videos found.</p><?php endif; ?>
        </section>
    </div>
</div>
<?php else: ?>
<div class="message error container">Video not found</div>
<?php endif; ?>

<div id="gallery-lightbox" class="lightbox"><div class="lightbox-overlay"></div><div class="lightbox-content"><img src="" alt="Lightbox image" class="lightbox-image"><button class="lightbox-close">&times;</button><button class="lightbox-prev">&#10094;</button><button class="lightbox-next">&#10095;</button></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Server button switcher
    const serverButtons = document.querySelectorAll('.server-btn');
    const playerIframe = document.getElementById('video-player-iframe');
    if (serverButtons.length && playerIframe) {
        serverButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const newUrl = this.dataset.embedUrl;
                if (playerIframe.src !== newUrl) { playerIframe.src = newUrl; }
                serverButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }

    // Tombol "Tampilkan lebih banyak"
    const showMoreBtn = document.querySelector('.show-more-btn');
    if (showMoreBtn) {
        showMoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const textWrapper = this.parentElement.querySelector('.description-text-wrapper');
            if (textWrapper) {
                textWrapper.classList.toggle('is-expanded');
                this.textContent = textWrapper.classList.contains('is-expanded') ? this.dataset.textLess : this.dataset.textMore;
            }
        });
    }

    // Fungsionalitas Tab
    const tabs = document.querySelectorAll('.tab-link');
    const panels = document.querySelectorAll('.detail-content-panel');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const targetPanelId = this.getAttribute('href').substring(1) + '-panel';
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            panels.forEach(p => p.id === targetPanelId ? p.classList.add('active') : p.classList.remove('active'));
        });
    });

    // Fungsionalitas Lightbox Galeri
    const lightbox = document.getElementById('gallery-lightbox');
    if (lightbox) {
        const galleryItems = document.querySelectorAll('.gallery-item');
        const lightboxImage = lightbox.querySelector('.lightbox-image');
        const closeBtn = lightbox.querySelector('.lightbox-close');
        const prevBtn = lightbox.querySelector('.lightbox-prev');
        const nextBtn = lightbox.querySelector('.lightbox-next');
        const overlay = lightbox.querySelector('.lightbox-overlay');
        let currentIndex = 0;
        function showImage(index) {
            if (index >= galleryItems.length) index = 0;
            if (index < 0) index = galleryItems.length - 1;
            lightboxImage.src = galleryItems[index].href;
            currentIndex = index;
        }
        function openLightbox(e) {
            e.preventDefault();
            currentIndex = parseInt(e.currentTarget.dataset.index, 10);
            showImage(currentIndex);
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeLightbox() {
            lightbox.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        galleryItems.forEach(item => item.addEventListener('click', openLightbox));
        closeBtn.addEventListener('click', closeLightbox);
        overlay.addEventListener('click', closeLightbox);
        nextBtn.addEventListener('click', () => showImage(currentIndex + 1));
        prevBtn.addEventListener('click', () => showImage(currentIndex - 1));
        document.addEventListener('keydown', e => {
            if (lightbox.classList.contains('active')) {
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowRight') showImage(currentIndex + 1);
                if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>