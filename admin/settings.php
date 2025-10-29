<?php
// File: admin/settings.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $siteLogoUrl = getSetting('site_logo_url'); 
    $uploadSuccess = true;

    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $targetDir = __DIR__ . "/../assets/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = uniqid() . '-' . basename($_FILES["site_logo"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowTypes = ['jpg', 'png', 'jpeg', 'gif', 'webp', 'svg'];
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["site_logo"]["tmp_name"], $targetFilePath)) {
                $siteLogoUrl = ASSETS_PATH . "uploads/" . $fileName;
            } else {
                $error = "Gagal memindahkan file logo yang diunggah.";
                $uploadSuccess = false;
            }
        } else {
            $error = "Format file logo tidak diizinkan.";
            $uploadSuccess = false;
        }
    }

    if ($uploadSuccess) {
        $updates = [
            'site_title' => $_POST['site_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'meta_keywords' => $_POST['meta_keywords'] ?? '',
            'site_logo_url' => $siteLogoUrl,
            'grid_columns_desktop' => $_POST['grid_columns_desktop'] ?? 4,
            'grid_columns_mobile' => $_POST['grid_columns_mobile'] ?? 2,
            'video_card_layout' => $_POST['video_card_layout'] ?? 'landscape'
        ];
        $allSuccess = true;
        foreach ($updates as $key => $value) {
            if (!updateSetting($key, $value)) $allSuccess = false;
        }

        if ($allSuccess) {
            $message = "Pengaturan berhasil disimpan.";
        } else {
            $error = $error ?? "Gagal menyimpan beberapa pengaturan ke database.";
        }
    }
}

$settings = getAllSettings();
require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pengaturan Situs & Tampilan</h1>
<p class="page-desc">Kelola identitas situs, metadata SEO, logo, dan tata letak video.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_settings">

    <div class="card">
        <div class="card-header"><h3>Pengaturan SEO & Umum</h3></div>
        <div class="form-group"><label for="site_title">Judul Website</label><input type="text" name="site_title" id="site_title" class="form-input" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="meta_description">Meta Deskripsi</label><textarea name="meta_description" id="meta_description" class="form-textarea"><?php echo htmlspecialchars($settings['meta_description'] ?? ''); ?></textarea></div>
        <div class="form-group"><label for="meta_keywords">Meta Kata Kunci (pisahkan koma)</label><input type="text" name="meta_keywords" id="meta_keywords" class="form-input" value="<?php echo htmlspecialchars($settings['meta_keywords'] ?? ''); ?>"></div>
        <div class="form-group"><label for="site_logo">Logo Website</label><input type="file" name="site_logo" id="site_logo" class="form-input" accept="image/*">
            <?php if (!empty($settings['site_logo_url'])): ?>
                <div style="margin-top:1rem;"><p style="color:var(--text-secondary); margin-bottom:0.5rem;">Logo Saat Ini:</p><img src="<?php echo htmlspecialchars($settings['site_logo_url']); ?>" alt="Logo" style="max-height: 50px; background: #fff; padding: 5px; border-radius: 5px;"></div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3>Pengaturan Tata Letak</h3></div>
        
        <div class="form-group">
            <label>Gaya Tampilan Kartu Video</label>
            <div class="radio-group">
                <input type="radio" id="layout_landscape" name="video_card_layout" value="landscape" <?php echo (($settings['video_card_layout'] ?? 'landscape') === 'landscape') ? 'checked' : ''; ?>>
                <label for="layout_landscape">Melebar (Landscape)</label>
            </div>
            <div class="radio-group">
                <input type="radio" id="layout_portrait" name="video_card_layout" value="portrait" <?php echo (($settings['video_card_layout'] ?? '') === 'portrait') ? 'checked' : ''; ?>>
                <label for="layout_portrait">Poster (Portrait)</label>
            </div>
        </div>

        <div style="display: flex; gap: 1.5rem; border-top: 1px solid var(--border-color); margin-top: 1.5rem; padding-top: 1.5rem;">
            <div class="form-group" style="flex:1;"><label for="grid_columns_desktop">Kolom Video (Desktop)</label><input type="number" name="grid_columns_desktop" id="grid_columns_desktop" class="form-input" min="2" max="8" value="<?php echo htmlspecialchars($settings['grid_columns_desktop'] ?? 4); ?>"></div>
            <div class="form-group" style="flex:1;"><label for="grid_columns_mobile">Kolom Video (Mobile)</label><input type="number" name="grid_columns_mobile" id="grid_columns_mobile" class="form-input" min="1" max="3" value="<?php echo htmlspecialchars($settings['grid_columns_mobile'] ?? 2); ?>"></div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Semua Pengaturan</button>
</form>

<style>
    .radio-group { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
    .radio-group input[type="radio"] { width: auto; }
    .radio-group label { margin: 0; }
</style>

<?php
require_once __DIR__ . '/templates/footer.php';
?>