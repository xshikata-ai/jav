<?php
// File: admin/index.php - Admin Dashboard
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';
require_once __DIR__ . '/templates/header.php';

function getStatistics() {
    $conn = connectDB();
    if (!$conn) return ['total_videos' => 0, 'total_categories' => 0];
    
    $stats = [];
    $stats['total_videos'] = $conn->query("SELECT COUNT(id) FROM videos")->fetch_row()[0] ?? 0;
    $stats['total_categories'] = $conn->query("SELECT COUNT(id) FROM categories")->fetch_row()[0] ?? 0;
    $conn->close();
    return $stats;
}

$stats = getStatistics();
?>

<h1 class="page-title">Dashboard</h1>
<p class="page-desc">Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>! Berikut adalah ringkasan situs Anda.</p>

<div class="dashboard-grid">
    <div class="card stat-card">
        <div class="icon"><i class="fas fa-video"></i></div>
        <div class="info">
            <div class="value"><?php echo number_format($stats['total_videos']); ?></div>
            <div class="label">Total Video</div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="icon"><i class="fas fa-folder-open"></i></div>
        <div class="info">
            <div class="value"><?php echo number_format($stats['total_categories']); ?></div>
            <div class="label">Total Kategori</div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3>Akses Cepat</h3>
    </div>
    <div class="dashboard-grid">
        <a href="<?php echo ADMIN_PATH; ?>search_clone.php" class="btn btn-primary"><i class="fas fa-search-plus"></i> Cari & Kloning Video Baru</a>
        <a href="<?php echo ADMIN_PATH; ?>mass_import.php" class="btn btn-success"><i class="fas fa-upload"></i> Mass Import Video</a>
        <a href="<?php echo ADMIN_PATH; ?>add_video.php" class="btn btn-info"><i class="fas fa-plus-square"></i> Tambah Video Baru</a>
        <a href="<?php echo ADMIN_PATH; ?>video_list.php" class="btn btn-secondary"><i class="fas fa-list-ul"></i> Kelola Daftar Video</a>
        <a href="<?php echo ADMIN_PATH; ?>seo_manager.php" class="nav-link <?php echo ($current_page == 'seo_manager.php') ? 'active' : ''; ?>"><i class="fas fa-search-dollar"></i> Pengelola SEO</a>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>