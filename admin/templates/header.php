<?php
// File: admin/templates/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (basename($_SERVER['PHP_SELF']) != 'login.php') {
        header("Location: " . ADMIN_PATH . "login.php");
        exit();
    }
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js" defer></script>
    
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/admin.css?v=1.2">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
            </div>
            <nav class="sidebar-nav">
                <a href="<?php echo ADMIN_PATH; ?>index.php" class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="<?php echo ADMIN_PATH; ?>search_clone.php" class="nav-link <?php echo ($current_page == 'search_clone.php') ? 'active' : ''; ?>"><i class="fas fa-search-plus"></i> Cari & Kloning</a>
                <a href="<?php echo ADMIN_PATH; ?>video_list.php" class="nav-link <?php echo ($current_page == 'video_list.php') ? 'active' : ''; ?>"><i class="fas fa-list-ul"></i> Daftar Video</a>
                <a href="<?php echo ADMIN_PATH; ?>categories.php" class="nav-link <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>"><i class="fas fa-folder"></i> Kategori</a>
                <a href="<?php echo ADMIN_PATH; ?>actress_manager.php" class="nav-link <?php echo ($current_page == 'actress_manager.php') ? 'active' : ''; ?>"><i class="fas fa-user-tag"></i> Pengelola Aktris</a>
                <a href="<?php echo ADMIN_PATH; ?>menu_manager.php" class="nav-link <?php echo ($current_page == 'menu_manager.php') ? 'active' : ''; ?>"><i class="fas fa-bars"></i> Pengelola Menu</a>
                <a href="<?php echo ADMIN_PATH; ?>settings.php" class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Pengaturan</a>
                <a href="<?php echo ADMIN_PATH; ?>logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>
        <main class="admin-main-content">