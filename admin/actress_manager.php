<?php
// File: admin/actress_manager.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;
$editActress = null;

// Penanganan Aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_actress') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $error = "Nama aktris tidak boleh kosong.";
        } else {
            if (addActress($name)) {
                $message = "Aktris berhasil ditambahkan.";
            } else {
                $error = "Gagal menambahkan aktris. Mungkin nama sudah ada.";
            }
        }
    } elseif ($_POST['action'] === 'update_actress') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (empty($name) || $id === 0) {
            $error = "Data tidak lengkap untuk pembaruan.";
        } else {
            if (updateActress($id, $name)) {
                $message = "Aktris berhasil diperbarui.";
            } else {
                $error = "Gagal memperbarui aktris.";
            }
        }
    } elseif ($_POST['action'] === 'delete_actress') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && deleteActress($id)) {
            $message = "Aktris berhasil dihapus.";
        } else {
            $error = "Gagal menghapus aktris.";
        }
    }
}


if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editActress = getActressById((int)$_GET['id']);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$actressesPerPage = 50;
$offset = ($page - 1) * $actressesPerPage;

$actressData = getAllActresses($actressesPerPage, $offset);
$actresses = $actressData['data'];
$totalActresses = $actressData['total'];
$totalPages = ceil($totalActresses / $actressesPerPage);

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pengelola Aktris</h1>
<p class="page-desc">Tambah, edit, atau hapus data aktris.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?php echo $editActress ? 'Edit Aktris' : 'Tambah Aktris Baru'; ?></h3></div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editActress ? 'update_actress' : 'add_actress'; ?>">
        <?php if ($editActress): ?><input type="hidden" name="id" value="<?php echo htmlspecialchars($editActress['id']); ?>"><?php endif; ?>
        <div class="form-group">
            <label for="name">Nama Aktris</label>
            <input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($editActress['name'] ?? ''); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editActress ? 'Simpan Perubahan' : 'Tambah Aktris'; ?></button>
        <?php if ($editActress): ?><a href="<?php echo ADMIN_PATH; ?>actress_manager.php" class="btn btn-secondary">Batal Edit</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3>Daftar Aktris</h3></div>
    <div class="table-container">
        <table class="admin-table">
            <thead><tr><th>Nama</th><th>Slug</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($actresses as $actress): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($actress['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($actress['slug']); ?></td>
                    <td class="actions">
                        <a href="?action=edit&id=<?php echo $actress['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                        <form method="POST" action="" style="display:inline;"><input type="hidden" name="action" value="delete_actress"><input type="hidden" name="id" value="<?php echo $actress['id']; ?>"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus aktris ini?');"><i class="fas fa-trash"></i> Hapus</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <div class="page-list" style="display: flex; align-items: center; gap: 1rem;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="page-link">&laquo; Previous</a>
            <?php endif; ?>

            <span class="page-indicator" style="color: var(--text-secondary);">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </span>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="page-link">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>