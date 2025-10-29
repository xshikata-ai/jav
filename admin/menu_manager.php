<?php
// File: admin/menu_manager.php - Halaman untuk mengelola visibilitas menu
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;

// Tangani permintaan POST untuk mengubah status menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menu_id'])) {
    $menu_id = (int)$_POST['menu_id'];
    $is_visible = (int)$_POST['is_visible'];
    
    if (updateMenuItemVisibility($menu_id, $is_visible)) {
        $message = "Status menu berhasil diperbarui.";
    } else {
        $error = "Gagal memperbarui status menu.";
    }
}

// Ambil semua item menu untuk ditampilkan
$menuItems = getMenuItemsFromDB();

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Pengelola Menu</h1>
<p class="page-desc">Atur item menu mana yang ingin Anda tampilkan atau sembunyikan di halaman depan website.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Daftar Menu Utama</h3>
    </div>
    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nama Menu</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($menuItems as $item): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($item['display_name']); ?></strong></td>
                    <td>
                        <?php if ($item['is_visible']): ?>
                            <span style="color: var(--success-accent); font-weight: bold;">Tampil</span>
                        <?php else: ?>
                            <span style="color: var(--danger-accent);">Tersembunyi</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <form method="POST" action="">
                            <input type="hidden" name="menu_id" value="<?php echo $item['id']; ?>">
                            <?php if ($item['is_visible']): ?>
                                <input type="hidden" name="is_visible" value="0">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-eye-slash"></i> Sembunyikan
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="is_visible" value="1">
                                <button type="submit" class="btn btn-primary btn-sm" style="background-color: var(--success-accent);">
                                    <i class="fas fa-eye"></i> Tampilkan
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>