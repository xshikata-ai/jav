<?php
// File: admin/categories.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;
$editCategory = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category' || $action === 'update_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color_hex = trim($_POST['color_hex'] ?? '#D91881');

        if (empty($name)) {
            $error = "Nama kategori tidak boleh kosong.";
        } else {
            if ($action === 'add_category') {
                $success = insertCategory($name, $description, $color_hex);
                if($success) $message = "Kategori '$name' berhasil ditambahkan.";
                else $error = "Gagal menambahkan. Mungkin nama sudah ada?";
            } elseif ($action === 'update_category') {
                $id = (int)($_POST['id'] ?? 0);
                $success = updateCategory($id, $name, $description, $color_hex);
                if($success) $message = "Kategori '$name' berhasil diperbarui.";
                else $error = "Gagal memperbarui kategori.";
            }
        }
    } elseif ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        $success = deleteCategory($id);
        if ($success) {
            $message = "Kategori berhasil dihapus.";
        } else {
            $error = "Gagal menghapus kategori.";
        }
    } 
    // LOGIKA PENGATURAN TAMPILAN: Menangani submit form untuk pengaturan border
    elseif ($action === 'save_display_settings') {
        $border_enabled = isset($_POST['enable_category_border']) ? '1' : '0';
        if (updateSetting('enable_category_border', $border_enabled)) {
            $message = "Pengaturan tampilan berhasil disimpan.";
        } else {
            $error = "Gagal menyimpan pengaturan tampilan.";
        }
    }
    // --- BARU: LOGIKA UNTUK ASSIGN VIDEO TANPA KATEGORI ---
    elseif ($action === 'assign_uncategorized') {
        $videoIds = $_POST['video_ids'] ?? [];
        $targetCategoryId = (int)($_POST['target_category_id'] ?? 0);

        if (empty($videoIds)) {
            $error = "Tidak ada video yang dipilih.";
        } elseif ($targetCategoryId === 0) {
            $error = "Anda harus memilih kategori tujuan.";
        } else {
            if (updateVideoCategoriesBulk($videoIds, $targetCategoryId)) {
                $message = count($videoIds) . " video berhasil dipindahkan ke kategori baru.";
            } else {
                $error = "Terjadi kesalahan saat memindahkan video.";
            }
        }
    }
    // --- AKHIR LOGIKA BARU ---
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editCategory = getCategories((int)$_GET['id']);
}

// LOGIKA PENGATURAN TAMPILAN: Mengambil data pengaturan border untuk ditampilkan di form
$enable_border_setting = getSetting('enable_category_border');

$categories = getCategories(); // Ambil semua kategori untuk form

// --- BARU: Ambil Video Tanpa Kategori ---
$uncategorizedPage = isset($_GET['uncat_page']) ? (int)$_GET['uncat_page'] : 1;
$videosPerUncategorizedPage = 20; // Tampilkan 20 per halaman
$uncategorizedOffset = ($uncategorizedPage - 1) * $videosPerUncategorizedPage;

$uncategorizedData = getUncategorizedVideos($videosPerUncategorizedPage, $uncategorizedOffset);
$uncategorizedVideos = $uncategorizedData['videos'];
$totalUncategorized = $uncategorizedData['total'];
$totalUncategorizedPages = ceil($totalUncategorized / $videosPerUncategorizedPage);
// --- AKHIR BARU ---


require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Kelola Kategori</h1>
<p class="page-desc">Tambah, edit, atau hapus kategori untuk mengelompokkan video Anda. Anda juga bisa mengatur tampilan terkait kategori di sini.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?php echo $editCategory ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h3></div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editCategory ? 'update_category' : 'add_category'; ?>">
        <?php if ($editCategory): ?><input type="hidden" name="id" value="<?php echo htmlspecialchars($editCategory['id']); ?>"><?php endif; ?>

        <div class="form-group"><label for="name">Nama Kategori</label><input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="description">Deskripsi (Opsional)</label><textarea name="description" id="description" class="form-textarea"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea></div>
        
        <div class="form-group">
            <label for="color_hex">Warna Latar Badge</label>
            <input type="color" name="color_hex" id="color_hex" value="<?php echo htmlspecialchars($editCategory['color_hex'] ?? '#D91881'); ?>" style="height: 40px; width: 100px;">
        </div>
        
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editCategory ? 'Simpan Perubahan' : 'Tambah Kategori'; ?></button>
        <?php if ($editCategory): ?><a href="<?php echo ADMIN_PATH; ?>categories.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal Edit</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Pengaturan Tampilan Border Kategori</h3></div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="save_display_settings">
        <div class="form-group">
            <label for="enable_category_border">Border Kategori pada Thumbnail</label>
            <div class="checkbox-wrapper">
                <input type="checkbox" name="enable_category_border" id="enable_category_border" value="1" <?php if ($enable_border_setting === '1') echo 'checked'; ?>>
                <label for="enable_category_border" class="checkbox-label">Aktifkan border dengan warna sesuai kategori.</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan Tampilan</button>
    </form>
</div>


<div class="card">
    <div class="card-header"><h3>Video Tanpa Kategori (Total: <?php echo $totalUncategorized; ?>)</h3></div>
    
    <?php if (!empty($uncategorizedVideos)): ?>
    <form method="POST" action="">
        <input type="hidden" name="action" value="assign_uncategorized">
        
        <div class="bulk-actions-bar" style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
            <div class="form-inline" style="gap: 1rem;">
                <div class="form-group" style="flex-grow: 1;">
                    <label for="target_category_id" style="margin-bottom: 0;">Pindahkan video terpilih ke:</label>
                    <select name="target_category_id" id="target_category_id" class="form-select" required>
                        <option value="">-- Pilih Kategori Tujuan --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['id']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Terapkan</button>
            </div>
        </div>

        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <input type="checkbox" id="check-all-uncategorized" title="Pilih Semua di Halaman Ini">
                        </th>
                        <th>Thumbnail</th>
                        <th>Judul</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uncategorizedVideos as $video): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="video_ids[]" class="video-check-item" value="<?php echo $video['id']; ?>">
                        </td>
                        <td>
                            <?php if (!empty($video['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="Thumb" style="width: 120px; height: auto;">
                            <?php else: ?>
                                (No Image)
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($video['original_title']); ?></strong>
                        </td>
                        <td>
                            <a href="<?php echo ADMIN_PATH; ?>edit_video.php?id=<?php echo $video['id']; ?>" class="btn btn-secondary btn-sm" target="_blank">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="<?php echo BASE_URL . htmlspecialchars($video['slug']); ?>" class="btn btn-secondary btn-sm" target="_blank">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalUncategorizedPages > 1): ?>
        <div class="pagination" style="margin-top: 2rem; display:flex; justify-content:center; align-items: center; gap: 1rem; padding: 1.5rem;">
            <?php if ($uncategorizedPage > 1): ?>
                <a href="?uncat_page=<?php echo $uncategorizedPage - 1; ?>" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">&laquo; Sebelumnya</a>
            <?php endif; ?>

            <span style="color: var(--text-secondary);">
                Halaman <?php echo $uncategorizedPage; ?> dari <?php echo $totalUncategorizedPages; ?>
            </span>

            <?php if ($uncategorizedPage < $totalUncategorizedPages): ?>
                <a href="?uncat_page=<?php echo $uncategorizedPage + 1; ?>" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">Berikutnya &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </form>
    <?php else: ?>
        <p class="message info" style="margin: 1.5rem;">Bagus! Tidak ada video tanpa kategori yang ditemukan.</p>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-header"><h3>Daftar Kategori Saat Ini</h3></div>
     <?php if (!empty($categories)): ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Warna</th>
                        <th>Nama</th>
                        <th>Deskripsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><div style="width: 30px; height: 30px; background-color: <?php echo htmlspecialchars($category['color_hex']); ?>; border-radius: 50%;"></div></td>
                        <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($category['description'] ?? '...'); ?></td>
                        <td class="actions">
                            <a href="?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin hapus kategori ini? Video terkait akan menjadi tidak berkategori.');"><i class="fas fa-trash"></i> Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="message info" style="padding: 1.5rem;">Belum ada kategori yang dibuat.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('check-all-uncategorized');
    const checkboxes = document.querySelectorAll('.video-check-item');

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }
});
</script>
<?php
require_once __DIR__ . '/templates/footer.php';
?>