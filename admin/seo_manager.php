<?php
// File: admin/seo_manager.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_seo_settings') {
    
    $settings_to_update = [
        'seo_videos_title', 'seo_videos_description', 'seo_videos_keywords',
        'seo_category_title', 'seo_category_description', 'seo_category_keywords',
        'seo_search_title', 'seo_search_description', 'seo_search_keywords',
        'seo_actress_list_title', 'seo_actress_list_description', 'seo_actress_list_keywords',
        'seo_actress_detail_title', 'seo_actress_detail_description', 'seo_actress_detail_keywords',
        'seo_genres_list_title', 'seo_genres_list_description', 'seo_genres_list_keywords',
        'seo_tag_title', 'seo_tag_description', 'seo_tag_keywords'
    ];

    $allSuccess = true;
    foreach ($settings_to_update as $key) {
        $value = $_POST[$key] ?? '';
        if (!updateSetting($key, $value)) {
            $allSuccess = false;
        }
    }

    if ($allSuccess) {
        $message = "Pengaturan SEO berhasil disimpan.";
    } else {
        $error = "Terjadi kesalahan saat menyimpan beberapa pengaturan.";
    }
}

$settings = getAllSettings();
require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pengelola SEO</h1>
<p class="page-desc">Atur template Judul, Deskripsi, dan Kata Kunci untuk halaman-halaman di situs Anda.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<form method="POST" action="">
    <input type="hidden" name="action" value="save_seo_settings">

    <div class="card">
        <div class="card-header"><h3>SEO Halaman Daftar Video (videos.php)</h3></div>
        <div class="form-group"><label for="seo_videos_title">Judul Halaman</label><input type="text" name="seo_videos_title" id="seo_videos_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_videos_title'] ?? ''); ?>"></div>
        <div class="form-group"><label for="seo_videos_description">Meta Deskripsi</label><textarea name="seo_videos_description" id="seo_videos_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_videos_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_videos_keywords">Meta Kata Kunci</label><input type="text" name="seo_videos_keywords" id="seo_videos_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_videos_keywords'] ?? ''); ?>"></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>SEO Halaman Kategori</h3></div>
        <p class="form-hint" style="margin-bottom: 1rem;">Gunakan <code>{category_name}</code> untuk memasukkan nama kategori.</p>
        <div class="form-group"><label for="seo_category_title">Template Judul</label><input type="text" name="seo_category_title" id="seo_category_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_category_title'] ?? ''); ?>" placeholder="Contoh: Video {category_name} Terbaru"></div>
        <div class="form-group"><label for="seo_category_description">Template Meta Deskripsi</label><textarea name="seo_category_description" id="seo_category_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_category_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_category_keywords">Template Meta Kata Kunci</S</label><input type="text" name="seo_category_keywords" id="seo_category_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_category_keywords'] ?? ''); ?>"></div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3>SEO Halaman Tag/Genre (videos.php?tag=...)</h3></div>
        <p class="form-hint" style="margin-bottom: 1rem;">Gunakan <code>{tag_name}</code> untuk memasukkan nama tag/genre.</p>
        <div class="form-group"><label for="seo_tag_title">Template Judul</label><input type="text" name="seo_tag_title" id="seo_tag_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_tag_title'] ?? ''); ?>" placeholder="Contoh: Video Genre {tag_name}"></div>
        <div class="form-group"><label for="seo_tag_description">Template Meta Deskripsi</label><textarea name="seo_tag_description" id="seo_tag_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_tag_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_tag_keywords">Template Meta Kata Kunci</label><input type="text" name="seo_tag_keywords" id="seo_tag_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_tag_keywords'] ?? ''); ?>"></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>SEO Halaman Pencarian</h3></div>
        <p class="form-hint" style="margin-bottom: 1rem;">Gunakan <code>{search_term}</code> untuk memasukkan kata kunci pencarian.</p>
        <div class="form-group"><label for="seo_search_title">Template Judul</label><input type="text" name="seo_search_title" id="seo_search_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_search_title'] ?? ''); ?>" placeholder="Contoh: Hasil Pencarian untuk {search_term}"></div>
        <div class="form-group"><label for="seo_search_description">Template Meta Deskripsi</label><textarea name="seo_search_description" id="seo_search_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_search_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_search_keywords">Template Meta Kata Kunci</label><input type="text" name="seo_search_keywords" id="seo_search_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_search_keywords'] ?? ''); ?>"></div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3>SEO Halaman Daftar Aktris (actress_list.php)</h3></div>
        <div class="form-group"><label for="seo_actress_list_title">Judul Halaman</label><input type="text" name="seo_actress_list_title" id="seo_actress_list_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_actress_list_title'] ?? ''); ?>"></div>
        <div class="form-group"><label for="seo_actress_list_description">Meta Deskripsi</label><textarea name="seo_actress_list_description" id="seo_actress_list_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_actress_list_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_actress_list_keywords">Meta Kata Kunci</label><input type="text" name="seo_actress_list_keywords" id="seo_actress_list_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_actress_list_keywords'] ?? ''); ?>"></div>
    </div>

    <div class="card">
        <div class="card-header"><h3>SEO Halaman Detail Aktris</h3></div>
        <p class="form-hint" style="margin-bottom: 1rem;">Gunakan <code>{actress_name}</code> untuk memasukkan nama aktris.</p>
        <div class="form-group"><label for="seo_actress_detail_title">Template Judul</label><input type="text" name="seo_actress_detail_title" id="seo_actress_detail_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_actress_detail_title'] ?? ''); ?>" placeholder="Contoh: Profil dan Video {actress_name}"></div>
        <div class="form-group"><label for="seo_actress_detail_description">Template Meta Deskripsi</label><textarea name="seo_actress_detail_description" id="seo_actress_detail_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_actress_detail_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_actress_detail_keywords">Template Meta Kata Kunci</label><input type="text" name="seo_actress_detail_keywords" id="seo_actress_detail_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_actress_detail_keywords'] ?? ''); ?>"></div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3>SEO Halaman Daftar Genre (genres.php)</h3></div>
        <div class="form-group"><label for="seo_genres_list_title">Judul Halaman</label><input type="text" name="seo_genres_list_title" id="seo_genres_list_title" class="form-input" value="<?php echo htmlspecialchars($settings['seo_genres_list_title'] ?? ''); ?>"></div>
        <div class="form-group"><label for="seo_genres_list_description">Meta Deskripsi</label><textarea name="seo_genres_list_description" id="seo_genres_list_description" class="form-textarea"><?php echo htmlspecialchars($settings['seo_genres_list_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="seo_genres_list_keywords">Meta Kata Kunci</label><input type="text" name="seo_genres_list_keywords" id="seo_genres_list_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['seo_genres_list_keywords'] ?? ''); ?>"></div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Semua Pengaturan SEO</button>
</form>

<style>
    .form-hint {
        font-size: 0.9em;
        color: var(--text-secondary);
        background-color: var(--bg-primary);
        padding: 0.5rem 1rem;
        border-radius: 6px;
        border: 1px dashed var(--border-color);
    }
    .form-hint code {
        color: var(--warning-accent);
        background-color: var(--bg-tertiary);
        padding: 2px 5px;
        border-radius: 4px;
        font-weight: bold;
    }
</style>

<?php
require_once __DIR__ . '/templates/footer.php';
?>