<?php
// File: admin/video_list.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video_from_list') {
    $videoId = $_POST['video_id'] ?? null;
    if ($videoId && deleteVideo($videoId)) {
        $message = "Video berhasil dihapus.";
    } else {
        $error = "Gagal menghapus video.";
    }
}

// --- MODIFIKASI: Ambil data pencarian ---
$searchKeyword = $_GET['search'] ?? '';

$videosPerPage = 15;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $videosPerPage;

// --- MODIFIKASI: Gunakan $searchKeyword dalam query ---
$videos = getVideosFromDB($videosPerPage, $offset, $searchKeyword, null, null, 'id', 'DESC');
$totalVideos = getTotalVideoCountDB($searchKeyword); // Hitung total berdasarkan pencarian
$totalPages = ceil($totalVideos / $videosPerPage);

require_once __DIR__ . '/templates/header.php';
?>

<h1 class="page-title">Daftar Video</h1>
<p class="page-desc">Kelola semua video yang telah Anda kloning ke database.</p>

<?php if (isset($message)): ?><p class="message success"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p class="message error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

<div class="card">
    <form method="GET" action="" class="form-inline">
        <div class="form-group" style="flex-grow: 1;">
            <label for="search">Cari Judul Video</label>
            <input type="text" name="search" id="search" class="form-input" placeholder="Masukkan judul video..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
        <?php if (!empty($searchKeyword)): ?>
            <a href="<?php echo ADMIN_PATH; ?>video_list.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>


<div class="card">
    <div class="card-header">
        <h3>
            <?php if (!empty($searchKeyword)): ?>
                Menampilkan <?php echo count($videos); ?> hasil (dari total <?php echo $totalVideos; ?>) untuk "<?php echo htmlspecialchars($searchKeyword); ?>"
            <?php else: ?>
                Menampilkan <?php echo count($videos); ?> dari <?php echo $totalVideos; ?> Total Video
            <?php endif; ?>
        </h3>
    </div>
    <?php if (!empty($videos)): ?>
        <div class="table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Thumbnail</th>
                        <th>Judul</th>
                        <th>Kategori</th>
                        <th>Durasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($video['id']); ?></td>
                        <td><img src="<?php echo htmlspecialchars($video['image_url']); ?>" alt="Thumb"></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($video['slug']); ?>" target="_blank">
                                <?php echo htmlspecialchars($video['original_title']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($video['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo formatDuration($video['duration']); ?></td>
                        <td class="actions">
                            <a href="<?php echo ADMIN_PATH; ?>edit_video.php?id=<?php echo $video['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" action="" style="display: inline-block;">
                                <input type="hidden" name="action" value="delete_video_from_list">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Anda yakin ingin menghapus video ini?');">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 2rem; display:flex; justify-content:center; align-items: center; gap: 1rem;">
            <?php
            // Logika untuk Tombol Previous
            if ($currentPage > 1) {
                $prevParams = ['page' => $currentPage - 1];
                if (!empty($searchKeyword)) {
                    $prevParams['search'] = $searchKeyword; // Pertahankan query pencarian
                }
                echo '<a href="?' . http_build_query($prevParams) . '" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">&laquo; Sebelumnya</a>';
            }
            ?>

            <?php
            // Logika untuk Tombol Next
            if ($currentPage < $totalPages) {
                $nextParams = ['page' => $currentPage + 1];
                if (!empty($searchKeyword)) {
                    $nextParams['search'] = $searchKeyword; // Pertahankan query pencarian
                }
                echo '<a href="?' . http_build_query($nextParams) . '" class="page-link" style="display:block; padding: 0.5rem 1rem; border:1px solid var(--border-color); border-radius:8px; background: var(--bg-tertiary); color: var(--text-primary); text-decoration: none;">Berikutnya &raquo;</a>';
            }
            ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <?php if (!empty($searchKeyword)): ?>
            <p class="message info">Tidak ada video yang ditemukan untuk kata kunci "<?php echo htmlspecialchars($searchKeyword); ?>".</p>
        <?php else: ?>
            <p class="message info">Belum ada video yang dikloning. Mulai dari <a href="<?php echo ADMIN_PATH; ?>search_clone.php">halaman Kloning</a>.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>