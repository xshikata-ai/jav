<?php
// File: genres.php (REKOMENDASI 3: TAMPILAN ACCORDION - MODERN)
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Logika PHP tetap SAMA persis seperti file asli Anda
$allGenres = getUniqueTags();
$groupedGenres = [];
foreach ($allGenres as $genre) {
    $firstLetter = strtoupper(substr($genre, 0, 1));
    if (ctype_alpha($firstLetter)) {
        $groupedGenres[$firstLetter][] = $genre;
    } else {
        $groupedGenres['#'][] = $genre;
    }
}
ksort($groupedGenres);

// --- PERUBAHAN 1: Judul diubah ke Bahasa Inggris ---
$pageTitle = 'Explore Genres';

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* --- PERUBAHAN 2: Gaya Tombol Dibuat Lebih Modern --- */

/* Hapus filter A-Z karena accordion sudah menjadi filter */
.az-filter-genres {
    display: none;
}

.genre-accordion-container {
    max-width: 900px; /* Batasi lebar agar rapi */
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem; /* Jarak antar item accordion */
}

.genre-accordion-item {
    background-color: var(--bg-secondary);
    border-radius: var(--border-radius-md);
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: background-color 0.3s ease;
}

/* Ini adalah tombol 'A', 'B', 'C' */
.genre-accordion-summary {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    cursor: pointer;
    list-style: none; /* Hapus panah default */
    font-size: 1.5rem; /* Ukuran font diubah */
    font-weight: 700;
    color: var(--text-primary); /* Warna teks normal */
    background-color: var(--bg-secondary);
    transition: background-color 0.3s ease;
}

.genre-accordion-summary::-webkit-details-marker {
    display: none; /* Hapus panah default (Chrome) */
}

/* Efek hover pada tombol */
.genre-accordion-summary:hover {
    background-color: var(--bg-tertiary);
}

/* Ikon panah kustom (+) */
.genre-accordion-summary::after {
    content: '+'; /* Ikon Plus */
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-left: auto;
    transition: transform 0.3s ease, color 0.3s ease;
}

/* Saat accordion terbuka */
.genre-accordion-item[open] {
    /* Beri sedikit perbedaan warna saat terbuka */
    background-color: var(--bg-tertiary); 
}

.genre-accordion-item[open] > .genre-accordion-summary {
    background-color: var(--bg-tertiary);
    color: var(--primary-accent); /* Judul menjadi pink saat terbuka */
}

/* Ikon berubah menjadi minus (-) saat terbuka */
.genre-accordion-item[open] > .genre-accordion-summary::after {
    content: '\2212'; /* Ikon Minus */
    transform: rotate(180deg); /* Animasi putar (opsional) */
    color: var(--primary-accent); /* Ikon ikut jadi pink */
}

.genre-accordion-content {
    padding: 0 1.5rem 1.5rem 1.5rem;
    /* Hapus border-top agar lebih bersih */
}

/* Gaya daftar tag (tetap sama) */
.genres-list {
    display: flex; 
    flex-wrap: wrap; 
    gap: 1rem;
    padding-top: 1.5rem; 
}
.genre-tag-link {
    padding: 0.6rem 1.2rem; 
    background-color: var(--bg-secondary); /* Ubah ke bg-secondary agar kontras */
    border-radius: 8px; 
    font-weight: 500; 
    transition: background-color 0.3s, color 0.3s;
}
.genre-tag-link:hover {
    background-color: var(--primary-accent);
    color: #fff;
}
</style>

<section class="generic-section">
    <div class="container">
        <div class="page-header" style="margin-bottom: 2rem; padding-top: 2.5rem;">
            <h1 class="page-title" style="font-size: 1.8rem; text-align: center;"><?php echo $pageTitle; ?></h1>
        </div>
        
        <?php if (!empty($groupedGenres)): ?>
            <div class="genre-accordion-container">
                <?php foreach ($groupedGenres as $letter => $genres): ?>
                    <details class="genre-accordion-item">
                        <summary class="genre-accordion-summary">
                            <?php echo $letter; ?>
                        </summary>
                        
                        <div class="genre-accordion-content">
                            <div class="genres-list">
                                <?php 
                                sort($genres);
                                foreach ($genres as $genre): 
                                ?>
                                    <a href="<?php echo BASE_URL; ?>videos?tag=<?php echo urlencode($genre); ?>" class="genre-tag-link">
                                        <?php echo htmlspecialchars($genre); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center;">Belum ada genre yang tersedia.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>