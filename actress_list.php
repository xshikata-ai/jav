<?php
// File: actress_list.php (TAMPILAN ACCORDION - MODERN)
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/database.php';

// Ambil semua data aktris dari database
$actressData = getAllActresses(null); // null untuk mendapatkan semua
$allActresses = $actressData['data'];

// Buat array untuk mengelompokkan aktris berdasarkan abjad
$groupedActresses = [];
foreach ($allActresses as $actress) {
    // Ambil huruf pertama, pastikan uppercase, dan valid
    $firstLetter = strtoupper(substr($actress['name'], 0, 1));
    if (ctype_alpha($firstLetter)) {
        $groupedActresses[$firstLetter][] = $actress;
    } else {
        // Kelompokkan yang bukan huruf ke dalam '#'
        $groupedActresses['#'][] = $actress;
    }
}
// Urutkan grup berdasarkan kuncinya (A, B, C, ...)
ksort($groupedActresses);

// --- PERUBAHAN 1: Judul diubah ke Bahasa Inggris ---
$pageTitle = 'Explore Actresses';

require_once __DIR__ . '/templates/header.php';
?>

<style>
/* --- PERUBAHAN 2: Gaya Tombol Modern (diterapkan untuk Aktris) --- */

/* Hapus filter A-Z lama */
.az-filter-actresses {
    display: none;
}

.actress-accordion-container {
    max-width: 900px; /* Batasi lebar agar rapi */
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem; /* Jarak antar item accordion */
}

.actress-accordion-item {
    background-color: var(--bg-secondary);
    border-radius: var(--border-radius-md);
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: background-color 0.3s ease;
}

/* Ini adalah tombol 'A', 'B', 'C' */
.actress-accordion-summary {
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

.actress-accordion-summary::-webkit-details-marker {
    display: none; /* Hapus panah default (Chrome) */
}

/* Efek hover pada tombol */
.actress-accordion-summary:hover {
    background-color: var(--bg-tertiary);
}

/* Ikon panah kustom (+) */
.actress-accordion-summary::after {
    content: '+'; /* Ikon Plus */
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-left: auto;
    transition: transform 0.3s ease, color 0.3s ease;
}

/* Saat accordion terbuka */
.actress-accordion-item[open] {
    /* Beri sedikit perbedaan warna saat terbuka */
    background-color: var(--bg-tertiary); 
}

.actress-accordion-item[open] > .actress-accordion-summary {
    background-color: var(--bg-tertiary);
    color: var(--primary-accent); /* Judul menjadi pink saat terbuka */
}

/* Ikon berubah menjadi minus (-) saat terbuka */
.actress-accordion-item[open] > .actress-accordion-summary::after {
    content: '\2212'; /* Ikon Minus */
    transform: rotate(180deg); /* Animasi putar (opsional) */
    color: var(--primary-accent); /* Ikon ikut jadi pink */
}

.actress-accordion-content {
    padding: 0 1.5rem 1.5rem 1.5rem;
    /* Hapus border-top agar lebih bersih */
}

/* Gaya daftar tag (dari file asli actress_list.php) */
.actresses-list {
    display: flex; 
    flex-wrap: wrap; 
    gap: 1rem;
    padding-top: 1.5rem;
}
.actress-tag-link {
    padding: 0.6rem 1.2rem; 
    background-color: var(--bg-secondary);
    border-radius: 8px; 
    font-weight: 500; 
    transition: background-color 0.3s, color 0.3s;
}
.actress-tag-link:hover {
    background-color: var(--primary-accent);
    color: #fff;
}
</style>

<section class="generic-section">
    <div class="container">
        <div class="page-header" style="margin-bottom: 2rem; padding-top: 2.5rem;">
            <h1 class="page-title" style="font-size: 1.8rem; text-align: center;"><?php echo $pageTitle; ?></h1>
        </div>
        
        <?php if (!empty($groupedActresses)): ?>
            <div class="actress-accordion-container">
                <?php foreach ($groupedActresses as $letter => $actresses): ?>
                    <details class="actress-accordion-item">
                        <summary class="actress-accordion-summary">
                            <?php echo $letter; ?>
                        </summary>
                        
                        <div class="actress-accordion-content">
                            <div class="actresses-list">
                                <?php 
                                // Urutkan aktris dalam grup ini (dari file asli)
                                usort($actresses, function($a, $b) {
                                    return strcmp($a['name'], $b['name']);
                                });
                                foreach ($actresses as $actress): 
                                ?>
                                    <a href="<?php echo BASE_URL; ?>actress/<?php echo urlencode($actress['slug']); ?>" class="actress-tag-link">
                                        <?php echo htmlspecialchars($actress['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center;">Belum ada aktris yang tersedia.</p>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/templates/footer.php'; ?>