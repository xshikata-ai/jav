<?php
// File: generate_sitemaps.php
// Skrip ini dijalankan oleh Cron Job untuk membuat file sitemap statis.

// Keamanan: Pastikan skrip ini dijalankan dari CLI
if (php_sapi_name() !== 'cli') {
    die("Akses ditolak. Skrip ini hanya boleh dijalankan dari command line.");
}

// Set waktu eksekusi tak terbatas
set_time_limit(0);
// Tingkatkan batas memori jika perlu (sesuaikan nilainya)
ini_set('memory_limit', '256M');

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Lokasi penyimpanan file sitemap (root directory)
$sitemapPath = __DIR__ . '/';
// Batas URL per file sitemap
$limitPerSitemap = 10000; // Google merekomendasikan maks 50.000, tapi lebih kecil lebih baik untuk performa
$baseUrl = BASE_URL; // Ambil BASE_URL dari config
$today = date('Y-m-d');
$sitemapIndexEntries = []; // Untuk menyimpan daftar sitemap yang dibuat

echo "Memulai pembuatan sitemap...\n";

// --- Helper Functions ---
function start_sitemap_file($filepath) {
    $handle = fopen($filepath, 'w');
    if (!$handle) {
        echo "ERROR: Tidak bisa membuka file untuk ditulis: " . $filepath . "\n";
        return null;
    }
    fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
    fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
    return $handle;
}

function add_sitemap_url($handle, $loc, $lastmod = null, $changefreq = 'weekly', $priority = '0.8') {
    if (!$handle) return;
    fwrite($handle, "  <url>\n");
    fwrite($handle, "    <loc>" . htmlspecialchars($loc) . "</loc>\n");
    if ($lastmod) {
        fwrite($handle, "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n");
    }
    fwrite($handle, "    <changefreq>" . $changefreq . "</changefreq>\n");
    fwrite($handle, "    <priority>" . $priority . "</priority>\n");
    fwrite($handle, "  </url>\n");
}

function end_sitemap_file($handle) {
    if (!$handle) return;
    fwrite($handle, '</urlset>' . "\n");
    fclose($handle);
}

function start_sitemap_index_file($filepath) {
    $handle = fopen($filepath, 'w');
     if (!$handle) {
        echo "ERROR: Tidak bisa membuka file index untuk ditulis: " . $filepath . "\n";
        return null;
    }
    fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
    fwrite($handle, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
    return $handle;
}

function add_sitemap_index_entry($handle, $loc, $lastmod) {
     if (!$handle) return;
    fwrite($handle, "  <sitemap>\n");
    fwrite($handle, "    <loc>" . htmlspecialchars($loc) . "</loc>\n");
    fwrite($handle, "    <lastmod>" . $lastmod . "</lastmod>\n");
    fwrite($handle, "  </sitemap>\n");
}

function end_sitemap_index_file($handle) {
     if (!$handle) return;
    fwrite($handle, '</sitemapindex>' . "\n");
    fclose($handle);
}

// --- 1. Sitemap Halaman Statis & Utama (pages) ---
$staticPagesFile = $sitemapPath . 'sitemap-pages.xml';
echo "Membuat sitemap halaman statis: " . basename($staticPagesFile) . " ... ";
$pagesHandle = start_sitemap_file($staticPagesFile);
if ($pagesHandle) {
    // Homepage
    add_sitemap_url($pagesHandle, $baseUrl, $today, 'daily', '1.0');

    // Halaman list video utama
    add_sitemap_url($pagesHandle, $baseUrl . 'videos', $today, 'daily', '0.9');

    // Halaman list aktris (jika menu aktif)
    $menuItems = getMenuItemsFromDB(); // Fungsi yang sudah ada
    if (isset($menuItems['actress']) && $menuItems['actress']['is_visible']) {
        add_sitemap_url($pagesHandle, $baseUrl . 'actress', $today, 'weekly', '0.7');
    }
    // Halaman list genre (jika menu aktif)
    if (isset($menuItems['genres']) && $menuItems['genres']['is_visible']) {
        add_sitemap_url($pagesHandle, $baseUrl . 'genres', $today, 'weekly', '0.7');
    }

    end_sitemap_file($pagesHandle);
    $sitemapIndexEntries[] = ['loc' => $baseUrl . basename($staticPagesFile), 'lastmod' => $today];
    echo "Selesai.\n";
}

// --- 2. Sitemap Video Detail (videos) ---
$totalVideos = getTotalVideoCountForSitemap();
$videoSitemapCount = ceil($totalVideos / $limitPerSitemap);
echo "Total video ditemukan: $totalVideos. Akan membuat $videoSitemapCount file sitemap video.\n";

for ($i = 0; $i < $videoSitemapCount; $i++) {
    $offset = $i * $limitPerSitemap;
    $sitemapFile = $sitemapPath . 'sitemap-videos-' . ($i + 1) . '.xml';
    echo "Membuat " . basename($sitemapFile) . " (offset: $offset)... ";

    $videos = getVideoSlugsForSitemap($limitPerSitemap, $offset);
    if (empty($videos)) {
        echo "Tidak ada video pada offset ini. Melanjutkan...\n";
        continue;
    }

    $videoHandle = start_sitemap_file($sitemapFile);
    if ($videoHandle) {
        foreach ($videos as $video) {
            if (!empty($video['slug'])) {
                $videoUrl = $baseUrl . $video['slug'];
                $lastModDate = $video['cloned_at'] ?? $today;
                add_sitemap_url($videoHandle, $videoUrl, $lastModDate, 'monthly', '0.8'); // Ubah frekuensi jika perlu
            }
        }
        end_sitemap_file($videoHandle);
        $sitemapIndexEntries[] = ['loc' => $baseUrl . basename($sitemapFile), 'lastmod' => $today];
        echo "Selesai.\n";
    }
}

// --- 3. Sitemap Kategori (categories) ---
$categories = getCategorySlugsForSitemap();
if (!empty($categories)) {
    $categorySitemapFile = $sitemapPath . 'sitemap-categories.xml';
    echo "Membuat sitemap kategori: " . basename($categorySitemapFile) . " ... ";
    $categoryHandle = start_sitemap_file($categorySitemapFile);
    if ($categoryHandle) {
        foreach ($categories as $category) {
            if (!empty($category['slug'])) {
                $categoryUrl = $baseUrl . 'videos?category=' . urlencode($category['slug']);
                add_sitemap_url($categoryHandle, $categoryUrl, $today, 'weekly', '0.7');
            }
        }
        end_sitemap_file($categoryHandle);
        $sitemapIndexEntries[] = ['loc' => $baseUrl . basename($categorySitemapFile), 'lastmod' => $today];
        echo "Selesai.\n";
    }
}

// --- 4. Sitemap Aktris Detail (actresses) ---
if (isset($menuItems['actress']) && $menuItems['actress']['is_visible']) {
    $totalActresses = getTotalActressCountForSitemap();
    $actressSitemapCount = ceil($totalActresses / $limitPerSitemap);
    echo "Total aktris ditemukan: $totalActresses. Akan membuat $actressSitemapCount file sitemap aktris.\n";

    for ($i = 0; $i < $actressSitemapCount; $i++) {
        $offset = $i * $limitPerSitemap;
        $sitemapFile = $sitemapPath . 'sitemap-actresses-' . ($i + 1) . '.xml';
        echo "Membuat " . basename($sitemapFile) . " (offset: $offset)... ";

        $actresses = getActressSlugsForSitemap($limitPerSitemap, $offset);
         if (empty($actresses)) {
            echo "Tidak ada aktris pada offset ini. Melanjutkan...\n";
            continue;
        }

        $actressHandle = start_sitemap_file($sitemapFile);
        if ($actressHandle) {
            foreach ($actresses as $actress) {
                if (!empty($actress['slug'])) {
                    $actressUrl = $baseUrl . 'actress/' . $actress['slug'];
                    add_sitemap_url($actressHandle, $actressUrl, $today, 'monthly', '0.5');
                }
            }
            end_sitemap_file($actressHandle);
            $sitemapIndexEntries[] = ['loc' => $baseUrl . basename($sitemapFile), 'lastmod' => $today];
            echo "Selesai.\n";
        }
    }
}

// --- 5. Sitemap Tag/Genre (tags) ---
if (isset($menuItems['genres']) && $menuItems['genres']['is_visible']) {
    $tags = getUniqueTagsForSitemap();
    if (!empty($tags)) {
        $tagSitemapFile = $sitemapPath . 'sitemap-tags.xml';
        echo "Membuat sitemap tag/genre: " . basename($tagSitemapFile) . " ... ";
        $tagHandle = start_sitemap_file($tagSitemapFile);
        if ($tagHandle) {
            foreach ($tags as $tag) {
                if (!empty($tag)) {
                    $tagUrl = $baseUrl . 'videos?tag=' . urlencode($tag);
                    add_sitemap_url($tagHandle, $tagUrl, $today, 'weekly', '0.6');
                }
            }
            end_sitemap_file($tagHandle);
            $sitemapIndexEntries[] = ['loc' => $baseUrl . basename($tagSitemapFile), 'lastmod' => $today];
            echo "Selesai.\n";
        }
    }
}

// --- 6. Membuat Sitemap Index ---
if (!empty($sitemapIndexEntries)) {
    $sitemapIndexFile = $sitemapPath . 'sitemap_index.xml';
    echo "Membuat file sitemap index: " . basename($sitemapIndexFile) . " ... ";
    $indexHandle = start_sitemap_index_file($sitemapIndexFile);
    if ($indexHandle) {
        foreach ($sitemapIndexEntries as $entry) {
            add_sitemap_index_entry($indexHandle, $entry['loc'], $entry['lastmod']);
        }
        end_sitemap_index_file($indexHandle);
        echo "Selesai.\n";
    }
}

echo "Semua proses pembuatan sitemap selesai.\n";
?>
