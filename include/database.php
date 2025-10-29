<?php
// File: include/database.php - Fungsi Interaksi Database
require_once __DIR__ . '/config.php';

function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Koneksi database gagal: " . $conn->connect_error);
        die("Terjadi kesalahan koneksi database.");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function createVideosTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NULL,
        description TEXT NULL,
        embed_id VARCHAR(255) UNIQUE NOT NULL,
        embed_url VARCHAR(512) NOT NULL,
        extra_embed_urls TEXT NULL,
        trailer_embed_url VARCHAR(512) NULL,
        gallery_image_urls TEXT NULL,
        download_links TEXT NULL,
        api_source VARCHAR(50) NULL,
        image_url VARCHAR(512),
        duration INT,
        quality VARCHAR(50),
        tags TEXT NULL,
        actresses TEXT NULL,
        studios TEXT NULL,
        category_id INT NULL,
        views INT DEFAULT 0,
        likes INT DEFAULT 0,
        cloned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === FALSE) {
        error_log("Error membuat tabel 'videos': " . $conn->error);
    }
    $conn->close();
}
createVideosTable();

function generateUniqueSlug($title, $id = null) {
    $conn = connectDB();
    $slug = str_replace('&', 'and', $title);
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    if (empty($slug)) {
        return 'video-' . uniqid();
    }
    $originalSlug = $slug;
    $counter = 1;
    while (true) {
        $checkSlug = $slug;
        $sql = "SELECT id FROM videos WHERE slug = ?";
        if ($id !== null) {
            $sql .= " AND id != ?";
        }
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($id !== null) {
                $stmt->bind_param("si", $checkSlug, $id);
            } else {
                $stmt->bind_param("s", $checkSlug);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 0) {
                $stmt->close();
                $conn->close();
                return $slug;
            }
            $stmt->close();
        } else {
            error_log("Error menyiapkan pernyataan cek slug: " . $conn->error);
            $conn->close();
            return $originalSlug . '-' . uniqid();
        }
        $slug = $originalSlug . '-' . $counter++;
    }
}

function insertClonedVideo($video) {
    $conn = connectDB();
    $title = $video['original_title'] ?? 'Judul Tidak Diketahui';
    $slug = generateUniqueSlug($title);
    
    // Explicitly list columns to avoid issues with new `extra_embed_urls` column
    $stmt = $conn->prepare("INSERT INTO videos (original_title, slug, description, embed_id, embed_url, extra_embed_urls, api_source, image_url, duration, quality, tags, actresses, studios, category_id, views, likes, trailer_embed_url, gallery_image_urls, download_links) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        $conn->close(); return false;
    }
    $duration = isset($video['duration']) ? (int)$video['duration'] : null;
    $categoryId = isset($video['category_id']) ? (int)$video['category_id'] : null;
    $views = isset($video['views']) ? (int)$video['views'] : 0;
    $likes = isset($video['likes']) ? (int)$video['likes'] : 0;
    
    $trailer_embed_url = $video['trailer_embed_url'] ?? null;
    $gallery_image_urls = $video['gallery_image_urls'] ?? null;
    $download_links = $video['download_links'] ?? null;
    $extra_embed_urls = null; // API cloning doesn't have extra URLs

    $stmt->bind_param("ssssssssissssiiisss",
        $title, $slug, $video['description'], $video['embed_id'], $video['embed_url'],
        $extra_embed_urls, $video['api_source'], $video['image_url'], $duration, $video['quality'],
        $video['tags'], $video['actresses'], $video['studios'], $categoryId,
        $views, $likes, $trailer_embed_url, $gallery_image_urls, $download_links
    );
    $success = $stmt->execute();
    if (!$success) { error_log("Error menyisipkan video: " . $stmt->error); }
    $stmt->close();
    $conn->close();
    return $success;
}

function insertMassVideo($video) {
    $conn = connectDB();
    $title = $video['original_title'] ?? 'Judul Tidak Diketahui';
    $slug = generateUniqueSlug($title); 
    
    $embed_id = 'mass-' . uniqid();

    $stmt = $conn->prepare("INSERT INTO videos (original_title, slug, description, embed_id, embed_url, extra_embed_urls, api_source, image_url, duration, quality, tags, actresses, studios, category_id, views, likes, trailer_embed_url, gallery_image_urls, download_links, cloned_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare statement failed (mass import): " . $conn->error);
        $conn->close(); return false;
    }
    
    $duration = isset($video['duration']) ? (int)$video['duration'] : null;
    $categoryId = isset($video['category_id']) ? (int)$video['category_id'] : null;
    $views = isset($video['views']) ? (int)$video['views'] : 0;
    $likes = isset($video['likes']) ? (int)$video['likes'] : 0;
    
    $trailer_embed_url = $video['trailer_embed_url'] ?? null;
    $gallery_image_urls = $video['gallery_image_urls'] ?? null;
    $download_links = $video['download_links'] ?? null;
    $cloned_at = $video['cloned_at'];
    $extra_embed_urls = $video['extra_embed_urls'] ?? null;

    $stmt->bind_param("ssssssssissssiiissss",
        $title, $slug, $video['description'], $embed_id, $video['embed_url'],
        $extra_embed_urls, $video['api_source'], $video['image_url'], $duration, $video['quality'],
        $video['tags'], $video['actresses'], $video['studios'], $categoryId,
        $views, $likes, $trailer_embed_url, $gallery_image_urls, $download_links,
        $cloned_at
    );

    $success = $stmt->execute();
    if (!$success) { 
        error_log("Error menyisipkan video (mass import): " . $stmt->error); 
    }
    $stmt->close();
    $conn->close();
    return $success;
}

function updateClonedVideo($id, $data) {
    $conn = connectDB();
    $slug = generateUniqueSlug($data['original_title'], $id);
    $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : null;
    $views = isset($data['views']) ? (int)$data['views'] : 0;
    $likes = isset($data['likes']) ? (int)$data['likes'] : 0;
    
    // --- QUERY DIPERBARUI: Tambahkan embed_url & extra_embed_urls ---
    $stmt = $conn->prepare("UPDATE videos SET 
                                original_title = ?, slug = ?, description = ?, tags = ?, 
                                actresses = ?, studios = ?, category_id = ?, image_url = ?, 
                                views = ?, likes = ?, trailer_embed_url = ?, gallery_image_urls = ?, 
                                download_links = ?, embed_url = ?, extra_embed_urls = ? 
                            WHERE id = ?");
    // --- AKHIR PERUBAHAN QUERY ---

    if (!$stmt) {
        error_log("Prepare statement failed for update: " . $conn->error);
        $conn->close(); return false;
    }
    
    $trailer_embed_url = $data['trailer_embed_url'] ?? null;
    $gallery_image_urls = $data['gallery_image_urls'] ?? null;
    $download_links = $data['download_links'] ?? null;
    $embed_url = $data['embed_url'] ?? ''; // Pastikan string jika null
    $extra_embed_urls = $data['extra_embed_urls'] ?? null; // Bisa null jika kosong

    // --- bind_param DIPERBARUI: Tambahkan tipe 'ss' dan variabel baru ---
    $stmt->bind_param("ssssssisiisssssi",  // Tambahkan 'ss' di akhir sebelum 'i'
        $data['original_title'], $slug, $data['description'], $data['tags'],
        $data['actresses'], $data['studios'], $categoryId, $data['image_url'],
        $views, $likes, $trailer_embed_url, $gallery_image_urls, $download_links, 
        $embed_url, $extra_embed_urls, // Tambahkan variabel baru
        $id
    );
    // --- AKHIR PERUBAHAN bind_param ---

    $success = $stmt->execute();
    if (!$success) { error_log("Error memperbarui video ID $id: " . $stmt->error); }
    $stmt->close();
    $conn->close();
    return $success;
}

function getVideosFromDB($limit = 12, $offset = 0, $searchKeyword = '', $categoryId = null, $tag = null, $orderBy = 'cloned_at', $orderDirection = 'DESC') {
    $conn = connectDB();
    // Tambahkan score untuk relevansi pencarian
    $selectFields = "v.*, c.name as category_name, c.color_hex as category_color";
    
    $sql = "SELECT {$selectFields}
            FROM videos v
            LEFT JOIN categories c ON v.category_id = c.id";
    $params = [];
    $types = '';
    $whereClauses = [];

    // --- LOGIKA PENCARIAN DIPERBARUI (Menggunakan LIKE untuk presisi judul) ---
    $orderByClause = "";
    
    if (!empty($searchKeyword)) {
        // Kita akan mencari di judul, tag, dan aktris
        $likeTerm = '%' . $conn->real_escape_string($searchKeyword) . '%';
        $whereClauses[] = "(v.original_title LIKE ? OR v.tags LIKE ? OR v.actresses LIKE ?)";
        
        // Tambahkan parameter 3x
        $params[] = $likeTerm;
        $params[] = $likeTerm;
        $params[] = $likeTerm;
        $types .= 'sss';
        
        // Urutkan berdasarkan prioritas:
        // 1. Judul TEPAT (atau dimulai dengan kode, misal "NKKD-123")
        // 2. Judul mengandung kata kunci
        // 3. Sisanya, urutkan berdasarkan ID (terbaru)
        $orderByClause = "
            CASE
                WHEN v.original_title LIKE ? THEN 1  /* Prioritas 1: Judul dimulai dengan keyword */
                WHEN v.original_title LIKE ? THEN 2  /* Prioritas 2: Judul mengandung keyword */
                ELSE 3
            END ASC, 
            v.id DESC 
        ";
        
        // Parameter untuk ORDER BY
        $likeStartTerm = $conn->real_escape_string(trim($searchKeyword)) . '%';
        $params[] = $likeStartTerm; // Untuk WHEN v.original_title LIKE ? (mulai dengan)
        $params[] = $likeTerm;      // Untuk WHEN v.original_title LIKE ? (mengandung)
        $types .= 'ss';

    } else {
        // Jika tidak ada pencarian, gunakan sorting yang diminta (latest, views, likes)
        $allowedOrderBy = ['cloned_at', 'views', 'likes', 'id'];
        // Pastikan $orderBy aman
        if (in_array($orderBy, $allowedOrderBy)) {
             $orderByClause = " v.{$orderBy} ";
             $orderDirection = strtoupper($orderDirection) === 'ASC' ? 'ASC' : 'DESC';
             $orderByClause .= $orderDirection;
        } else {
             $orderByClause = " v.id DESC "; // Fallback aman
        }
    }
    // --- AKHIR MODIFIKASI ---

    if ($categoryId !== null && $categoryId > 0) {
        $whereClauses[] = "v.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
    }
    if ($tag !== null && !empty($tag)) {
        $whereClauses[] = "v.tags LIKE ?";
        $params[] = '%' . $tag . '%';
        $types .= 's';
    }
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    
    // Terapkan klausa ORDER BY
    if (!empty($orderByClause)) {
        $sql .= " ORDER BY {$orderByClause}";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $videos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Error menyiapkan pernyataan select: " . $conn->error);
        $videos = [];
    }
    $conn->close();
    return $videos;
}

function getRandomVideosFromDB($limit = 4, $excludeIds = []) { // Diubah dari $excludeId ke $excludeIds
    $conn = connectDB();
    $sql_ids = "SELECT id FROM videos";
    
    // --- MODIFIKASI DIMULAI ---
    $exclusionClause = '';
    if (!empty($excludeIds)) {
        // Pastikan semua ID adalah integer
        $safeIds = array_map('intval', $excludeIds);
        $idList = implode(',', $safeIds);
        if (!empty($idList)) {
            $exclusionClause = " WHERE id NOT IN ($idList)";
        }
    }
    $sql_ids .= $exclusionClause;
    // --- MODIFIKASI SELESAI ---

    $result = $conn->query($sql_ids);
    if (!$result || $result->num_rows === 0) {
        $conn->close();
        return [];
    }
    $ids = $result->fetch_all(MYSQLI_NUM);
    $conn->close();
    shuffle($ids);
    $random_ids = array_slice($ids, 0, $limit);
    if (empty($random_ids)) {
        return [];
    }
    $id_list = implode(',', array_map('intval', array_column($random_ids, 0)));
    
    $conn = connectDB();
    $sql_videos = "SELECT v.*, c.name as category_name, c.color_hex as category_color 
                   FROM videos v
                   LEFT JOIN categories c ON v.category_id = c.id
                   WHERE v.id IN ($id_list)";
    $result_videos = $conn->query($sql_videos);
    $videos = $result_videos ? $result_videos->fetch_all(MYSQLI_ASSOC) : [];
    $conn->close();
    return $videos;
}

function getRelatedVideosByCategoryFromDB($categoryId, $excludeIds = [], $limit = 8) { // Diubah
    if (empty($categoryId)) {
        return [];
    }
    $conn = connectDB();
    $videos = [];
    
    // --- MODIFIKASI DIMULAI ---
    $params = [$categoryId];
    $types = 'i';

    $exclusionClause = '';
    if (!empty($excludeIds)) {
        $idPlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $exclusionClause = "AND v.id NOT IN ($idPlaceholders)";
        foreach ($excludeIds as $id) {
            $params[] = (int)$id;
            $types .= 'i';
        }
    }
    
    $sql = "SELECT v.*, c.name as category_name, c.color_hex as category_color 
            FROM videos v
            LEFT JOIN categories c ON v.category_id = c.id
            WHERE v.category_id = ? $exclusionClause
            ORDER BY v.cloned_at DESC
            LIMIT ?";
    
    $params[] = $limit;
    $types .= 'i';
    // --- MODIFIKASI SELESAI ---

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params); // Diubah
        $stmt->execute();
        $result = $stmt->get_result();
        $videos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Error preparing getRelatedVideosByCategory: " . $conn->error);
    }
    $conn->close();
    return $videos;
}
// --- FUNGSI BARU UNTUK RELATED VIDEOS ---
function getRelatedVideosByActress($actressString, $excludeId, $limit = 8) {
    if (empty(trim($actressString))) {
        return [];
    }
    $conn = connectDB();
    $actresses = array_filter(array_map('trim', explode(',', $actressString)));
    if (empty($actresses)) {
        $conn->close();
        return [];
    }

    $whereClauses = [];
    $params = [];
    $types = '';

    foreach ($actresses as $actressName) {
        $whereClauses[] = "v.actresses LIKE ?";
        $params[] = '%' . $conn->real_escape_string($actressName) . '%';
        $types .= 's';
    }
    
    $sql = "SELECT v.*, c.name as category_name, c.color_hex as category_color 
            FROM videos v
            LEFT JOIN categories c ON v.category_id = c.id
            WHERE (" . implode(' OR ', $whereClauses) . ") AND v.id != ?
            ORDER BY v.cloned_at DESC
            LIMIT ?";
    
    $params[] = $excludeId;
    $types .= 'i';
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $videos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Error preparing getRelatedVideosByActress: " . $conn->error);
        $videos = [];
    }
    $conn->close();
    return $videos;
}

function getRelatedVideosByTags($tagString, $excludeIds = [], $limit = 8) {
    if (empty(trim($tagString))) {
        return [];
    }
    $conn = connectDB();
    $tags = array_filter(array_map('trim', explode(',', $tagString)));
    if (empty($tags)) {
        $conn->close();
        return [];
    }

    $whereClauses = [];
    $params = [];
    $types = '';

    foreach ($tags as $tag) {
        $whereClauses[] = "v.tags LIKE ?";
        $params[] = '%' . $conn->real_escape_string($tag) . '%';
        $types .= 's';
    }
    
    // Build the exclusion list
    $exclusionClause = '';
    if (!empty($excludeIds)) {
        $idPlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $exclusionClause = "AND v.id NOT IN ($idPlaceholders)";
        foreach ($excludeIds as $id) {
            $params[] = (int)$id;
            $types .= 'i';
        }
    }

    $sql = "SELECT v.*, c.name as category_name, c.color_hex as category_color 
            FROM videos v
            LEFT JOIN categories c ON v.category_id = c.id
            WHERE (" . implode(' OR ', $whereClauses) . ") $exclusionClause
            ORDER BY v.cloned_at DESC
            LIMIT ?";
    
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $videos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Error preparing getRelatedVideosByTags: " . $conn->error);
        $videos = [];
    }
    $conn->close();
    return $videos;
}
// --- AKHIR FUNGSI BARU ---
function getTotalVideoCountDB($searchKeyword = '', $categoryId = null, $tag = null) {
    $conn = connectDB();
    $sql = "SELECT COUNT(*) as count FROM videos v";
    $params = [];
    $types = '';
    $whereClauses = [];

    // --- LOGIKA PENCARIAN DIPERBARUI (Cocokkan dengan getVideosFromDB) ---
    if (!empty($searchKeyword)) {
        $likeTerm = '%' . $conn->real_escape_string($searchKeyword) . '%';
        $whereClauses[] = "(v.original_title LIKE ? OR v.tags LIKE ? OR v.actresses LIKE ?)";
        $params[] = $likeTerm;
        $params[] = $likeTerm;
        $params[] = $likeTerm;
        $types .= 'sss';
    }
    // ------------------------------------

    if ($categoryId !== null && $categoryId > 0) {
        $whereClauses[] = "v.category_id = ?";
        $params[] = $categoryId;
        $types .= 'i';
    }
    if ($tag !== null && !empty($tag)) {
        $whereClauses[] = "v.tags LIKE ?";
        $params[] = '%' . $tag . '%';
        $types .= 's';
    }
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row['count'];
    } else {
        error_log("Error menyiapkan pernyataan hitungan total: " . $conn->error);
        $conn->close();
        return 0;
    }
}

function getVideoByIdFromDB($id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.color_hex as category_color FROM videos v LEFT JOIN categories c ON v.category_id = c.id WHERE v.id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $video = $result->fetch_assoc();
        $stmt->close();
    } else {
        error_log("Error preparing select by ID statement: " . $conn->error);
        $video = null;
    }
    $conn->close();
    return $video;
}

function getVideoBySlugFromDB($slug) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.color_hex as category_color FROM videos v LEFT JOIN categories c ON v.category_id = c.id WHERE v.slug = ?");
    if ($stmt) {
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $video = $result->fetch_assoc();
        $stmt->close();
    } else {
        error_log("Error preparing select by slug statement: " . $conn->error);
        $video = null;
    }
    $conn->close();
    return $video;
}

function deleteVideo($id) {
    $conn = connectDB();
    $stmt = $conn->prepare("DELETE FROM videos WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        if (!$success) { error_log("Error deleting video: " . $stmt->error); }
        $stmt->close();
    } else {
        error_log("Error preparing delete statement: " . $conn->error);
        $success = false;
    }
    $conn->close();
    return $success;
}

function createCategoriesTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        description TEXT NULL,
        color_hex VARCHAR(7) DEFAULT '#D91881',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === FALSE) {
        error_log("Error creating 'categories' table: " . $conn->error);
    }
    $conn->close();
}
createCategoriesTable();

function insertCategory($name, $description, $color_hex = '#D91881') {
    $conn = connectDB();
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, color_hex) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $name, $slug, $description, $color_hex);
        $success = $stmt->execute();
        if (!$success) { error_log("Error inserting category: " . $stmt->error); }
        $stmt->close();
    } else {
        error_log("Error preparing insert category statement: " . $conn->error);
        $success = false;
    }
    $conn->close();
    return $success;
}

function updateCategory($id, $name, $description, $color_hex) {
    $conn = connectDB();
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, color_hex = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssssi", $name, $slug, $description, $color_hex, $id);
        $success = $stmt->execute();
        if (!$success) { error_log("Error updating category: " . $stmt->error); }
        $stmt->close();
    } else {
        error_log("Error preparing update category statement: " . $conn->error);
        $success = false;
    }
    $conn->close();
    return $success;
}

function deleteCategory($id) {
    $conn = connectDB();
    $updateVideosStmt = $conn->prepare("UPDATE videos SET category_id = NULL WHERE category_id = ?");
    if ($updateVideosStmt) {
        $updateVideosStmt->bind_param("i", $id);
        $updateVideosStmt->execute();
        $updateVideosStmt->close();
    } else {
        error_log("Error updating videos on category deletion: " . $conn->error);
    }
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        if (!$success) { error_log("Error deleting category: " . $stmt->error); }
        $stmt->close();
    } else {
        error_log("Error preparing delete category statement: " . $conn->error);
        $success = false;
    }
    $conn->close();
    return $success;
}

function getCategories($id = null) {
    $conn = connectDB();
    if ($id) {
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = $result->fetch_assoc();
    } else {
        $result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
        $categories = $result->fetch_all(MYSQLI_ASSOC);
    }
    $conn->close();
    return $categories;
}

function insertCategoryIfNotExist($name, $description = null, $color_hex = '#D91881') {
    if (empty(trim($name))) {
        return null;
    }
    $conn = connectDB();
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $stmt = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
    if ($stmt) {
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        $stmt->close();
        if ($category) {
            $conn->close();
            return $category['id'];
        }
    } else {
        error_log("Error preparing check category statement: " . $conn->error);
        $conn->close(); return false;
    }
    $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, color_hex) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $name, $slug, $description, $color_hex);
        $success = $stmt->execute();
        $newId = $stmt->insert_id;
        if (!$success) { error_log("Error inserting new category: " . $stmt->error); }
        $stmt->close();
        $conn->close();
        return $success ? $newId : false;
    } else {
        error_log("Error preparing insert new category statement: " . $conn->error);
        $conn->close();
        return false;
    }
}

function createSettingsTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT NULL,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === FALSE) { error_log("Error membuat tabel 'settings': " . $conn->error); }
    else {
        $check = $conn->query("SELECT COUNT(*) FROM settings")->fetch_row()[0];
        if ($check == 0) {
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_title', 'Situs Video Kloning Saya')");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_description', 'Koleksi video kloning terbaik.')");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('meta_keywords', 'video, kloning, hiburan, film')");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('site_logo_url', '')");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('grid_columns_desktop', '4')");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('grid_columns_mobile', '2')");
            $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('enable_category_border', '1')");
        }
    }
    $conn->close();
}
createSettingsTable();

function getSetting($key) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row['setting_value'] ?? null;
    } else {
        error_log("Error menyiapkan pernyataan getSetting: " . $conn->error);
        $conn->close();
        return null;
    }
}

function getAllSettings() {
    $conn = connectDB();
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $conn->close();
    return $settings;
}

function updateSetting($key, $value) {
    $conn = connectDB();
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $key, $value, $value);
        $success = $stmt->execute();
        if (!$success) { error_log("Error memperbarui pengaturan '$key': " . $stmt->error); }
        $stmt->close();
    } else {
        error_log("Error menyiapkan pernyataan updateSetting: " . $conn->error);
        $success = false;
    }
    $conn->close();
    return $success;
}

function createUsersTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === FALSE) {
        error_log("Error membuat tabel 'users': " . $conn->error);
    }
    $conn->close();
}
createUsersTable();

function verifyUser($username, $password) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Error menyiapkan pernyataan verifikasi user: " . $conn->error);
        $conn->close();
        return false;
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    if ($user && password_verify($password, $user['password_hash'])) {
        return true;
    }
    return false;
}

function createMenuItemsTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_key VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        is_visible TINYINT(1) DEFAULT 1,
        menu_order INT DEFAULT 0
    )";
    if ($conn->query($sql) === TRUE) {
        $check = $conn->query("SELECT COUNT(*) FROM menu_items")->fetch_row()[0];
        if ($check == 0) {
            $default_items = [
                ['home', 'Home', 1, 0],
                ['videos', 'Videos', 1, 10],
                ['categories', 'Categories', 1, 15],
                ['actress', 'Actress', 1, 20],
                ['genres', '18+ Genres', 1, 30],
                ['studios', 'Studios', 1, 40]
            ];
            $stmt = $conn->prepare("INSERT INTO menu_items (menu_key, display_name, is_visible, menu_order) VALUES (?, ?, ?, ?)");
            foreach ($default_items as $item) {
                $stmt->bind_param("ssii", $item[0], $item[1], $item[2], $item[3]);
                $stmt->execute();
            }
            $stmt->close();
        }
    } else {
        error_log("Error membuat tabel 'menu_items': " . $conn->error);
    }
    $conn->close();
}
createMenuItemsTable();

function getMenuItemsFromDB() {
    $conn = connectDB();
    $result = $conn->query("SELECT * FROM menu_items ORDER BY menu_order ASC");
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();
    $menu_map = [];
    foreach ($items as $item) {
        $menu_map[$item['menu_key']] = $item;
    }
    return $menu_map;
}

function updateMenuItemVisibility($id, $is_visible) {
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE menu_items SET is_visible = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_visible, $id);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

function getAllClonedEmbedIds() {
    $conn = connectDB();
    $ids = [];
    $sql = "SELECT embed_id FROM videos WHERE embed_id IS NOT NULL";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $ids[] = $row['embed_id'];
        }
    }
    $conn->close();
    return $ids;
}

function doesVideoTitleExist($title) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT id FROM videos WHERE original_title = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $conn->close();
        return false;
    }
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $stmt->store_result();
    $num_rows = $stmt->num_rows;
    $stmt->close();
    $conn->close();
    return $num_rows > 0;
}

function doesEmbedUrlExist($url) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT id FROM videos WHERE embed_url = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $conn->close();
        return false; 
    }
    $stmt->bind_param("s", $url);
    $stmt->execute();
    $stmt->store_result();
    $num_rows = $stmt->num_rows;
    $stmt->close();
    $conn->close();
    return $num_rows > 0;
}

function createActressesTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS actresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === FALSE) {
        error_log("Error creating 'actresses' table: " . $conn->error);
    }
    $conn->close();
}
createActressesTable();

function getAllActresses($limit = null, $offset = 0, $letter = null) {
    $connTotal = connectDB();
    $total = 0;
    
    $countWhereClause = '';
    $countParams = [];
    $countTypes = '';

    if ($letter !== null && preg_match('/^[A-Z]$/', $letter)) {
        $countWhereClause = "WHERE name LIKE ?";
        $countParams[] = $letter . '%';
        $countTypes .= 's';
    }

    $countSql = "SELECT COUNT(*) as count FROM actresses " . $countWhereClause;
    $stmtCount = $connTotal->prepare($countSql);
    if ($stmtCount) {
        if (!empty($countParams)) {
            $stmtCount->bind_param($countTypes, ...$countParams);
        }
        $stmtCount->execute();
        $resultCount = $stmtCount->get_result();
        $total = $resultCount->fetch_assoc()['count'] ?? 0;
        $stmtCount->close();
    } else {
        error_log("Error menyiapkan pernyataan hitung aktris: " . $connTotal->error);
    }
    $connTotal->close();

    $actresses = [];
    if ($total > 0) {
        $connData = connectDB();
        $dataWhereClause = '';
        $dataParams = [];
        $dataTypes = '';

        if ($letter !== null && preg_match('/^[A-Z]$/', $letter)) {
            $dataWhereClause = "WHERE name LIKE ?";
            $dataParams[] = $letter . '%';
            $dataTypes .= 's';
        }

        $sql = "SELECT * FROM actresses " . $dataWhereClause . " ORDER BY name ASC";
        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $dataParams[] = $limit;
            $dataParams[] = $offset;
            $dataTypes .= 'ii';
        }
        
        $stmtData = $connData->prepare($sql);
        if ($stmtData) {
            if (!empty($dataParams)) {
                $stmtData->bind_param($dataTypes, ...$dataParams);
            }
            $stmtData->execute();
            $resultData = $stmtData->get_result();
            $actresses = $resultData->fetch_all(MYSQLI_ASSOC);
            $stmtData->close();
        } else {
            error_log("Error menyiapkan pernyataan select aktris: " . $connData->error);
        }
        $connData->close();
    }

    return ['data' => $actresses, 'total' => $total];
}

function addActress($name) {
    $conn = connectDB();
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name)));
    $stmt = $conn->prepare("INSERT INTO actresses (name, slug) VALUES (?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("ss", $name, $slug);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Execute failed: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    return $success;
}

function getActressById($id) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM actresses WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $actress = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $actress;
}

function updateActress($id, $name) {
    $conn = connectDB();
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name)));
    
    $stmt = $conn->prepare("UPDATE actresses SET name = ?, slug = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $slug, $id);

    $success = $stmt->execute();
    if (!$success) {
        error_log("Update actress failed: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();
    return $success;
}

function deleteActress($id) {
    $conn = connectDB();
    $stmt = $conn->prepare("DELETE FROM actresses WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $success;
}

function getActressBySlug($slug) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM actresses WHERE slug = ?");
    if (!$stmt) {
        error_log("Prepare failed for getActressBySlug: " . $conn->error);
        $conn->close();
        return null;
    }
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $actress = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $actress;
}

function getVideosByActressName($name, $limit, $offset) {
    $conn = connectDB();
    $searchTerm = '%' . $conn->real_escape_string($name) . '%';
    
    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM videos WHERE actresses LIKE ?");
    if (!$countStmt) {
        error_log("Prepare failed for getVideosByActressName count: " . $conn->error);
        $conn->close();
        return ['videos' => [], 'total' => 0];
    }
    $countStmt->bind_param("s", $searchTerm);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['count'];
    $countStmt->close();

    $stmt = $conn->prepare("SELECT v.*, c.name as category_name, c.color_hex as category_color 
                            FROM videos v 
                            LEFT JOIN categories c ON v.category_id = c.id 
                            WHERE v.actresses LIKE ? 
                            ORDER BY v.cloned_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) {
        error_log("Prepare failed for getVideosByActressName data: " . $conn->error);
        $conn->close();
        return ['videos' => [], 'total' => $total];
    }
    $stmt->bind_param("sii", $searchTerm, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $videos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    return ['videos' => $videos, 'total' => $total];
}

function addActressesIfNotExist(array $actressNames) {
    if (empty($actressNames)) {
        return;
    }

    $conn = connectDB();
    
    $stmtCheck = $conn->prepare("SELECT name FROM actresses WHERE name = ?");
    if (!$stmtCheck) {
        error_log("Prepare failed for addActressesIfNotExist check: " . $conn->error);
        $conn->close();
        return;
    }

    $stmtInsert = $conn->prepare("INSERT INTO actresses (name, slug) VALUES (?, ?)");
    if (!$stmtInsert) {
        error_log("Prepare failed for addActressesIfNotExist insert: " . $conn->error);
        $stmtCheck->close();
        $conn->close();
        return;
    }

    foreach ($actressNames as $name) {
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            continue;
        }

        $stmtCheck->bind_param("s", $trimmedName);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows === 0) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $trimmedName)));
            $stmtInsert->bind_param("ss", $trimmedName, $slug);
            if (!$stmtInsert->execute()) {
                error_log("Execute failed for addActressesIfNotExist insert: " . $stmtInsert->error);
            }
        }
    }

    $stmtCheck->close();
    $stmtInsert->close();
    $conn->close();
}

function getUniqueTags() {
    $conn = connectDB();
    $result = $conn->query("SELECT tags FROM videos WHERE tags IS NOT NULL AND tags != ''");
    $allTags = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tags = array_map('trim', explode(',', $row['tags']));
            $allTags = array_merge($allTags, $tags);
        }
    }
    $conn->close();
    return array_unique(array_filter($allTags));
}

function getUniqueStudios() {
    $conn = connectDB();
    $result = $conn->query("SELECT studios FROM videos WHERE studios IS NOT NULL AND studios != ''");
    $allStudios = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $studios = array_map('trim', explode(',', $row['studios']));
            $allStudios = array_merge($allStudios, $studios);
        }
    }
    $conn->close();
    return array_unique(array_filter($allStudios));
}
function createImportQueueTable() {
    $conn = connectDB();
    $sql = "CREATE TABLE IF NOT EXISTS `import_queue` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `line_data` TEXT NOT NULL,
        `target_category_id` INT NULL,
        `allowed_domains` TEXT NULL,
        `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        `error_message` TEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `processed_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    if ($conn->query($sql) === FALSE) {
        error_log("Error membuat tabel 'import_queue': " . $conn->error);
    }
    $conn->close();
}
// Panggil fungsi ini agar tabel otomatis dibuat
createImportQueueTable();

/**
 * Menambahkan daftar video ke antrian impor.
 * @param array $lines Array dari baris data video.
 * @param int|null $categoryId ID kategori target.
 * @param string $allowedDomains String domain yang diizinkan (dipisahkan newline).
 * @return int Jumlah baris yang berhasil ditambahkan.
 */
function addVideosToImportQueue(array $lines, $categoryId, $allowedDomains) {
    $conn = connectDB();
    $sql = "INSERT INTO import_queue (line_data, target_category_id, allowed_domains, status) VALUES (?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed for addVideosToImportQueue: " . $conn->error);
        $conn->close();
        return 0;
    }
    
    $successCount = 0;
    $target_category_id = !empty($categoryId) ? (int)$categoryId : null;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine)) continue;
        
        $stmt->bind_param("sis", $trimmedLine, $target_category_id, $allowedDomains);
        if ($stmt->execute()) {
            $successCount++;
        } else {
            error_log("Failed to insert line into queue: " . $stmt->error);
        }
    }
    
    $stmt->close();
    $conn->close();
    return $successCount;
}

/**
 * Mengambil item dari antrian untuk diproses.
 * Mengunci baris dengan status 'processing'.
 * @param int $limit Jumlah item yang diambil.
 * @return array Daftar item antrian.
 */
function getPendingImportItems($limit = 5) {
    $conn = connectDB();
    $items = [];
    
    // Mulai transaksi
    $conn->begin_transaction();
    try {
        // Ambil item yang pending (FOR UPDATE mengunci baris ini)
        $sqlSelect = "SELECT * FROM import_queue WHERE status = 'pending' ORDER BY id ASC LIMIT ? FOR UPDATE";
        $stmtSelect = $conn->prepare($sqlSelect);
        if (!$stmtSelect) throw new Exception("Prepare select failed: " . $conn->error);
        
        $stmtSelect->bind_param("i", $limit);
        $stmtSelect->execute();
        $result = $stmtSelect->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmtSelect->close();
        
        if (!empty($items)) {
            // Dapatkan ID dari item yang akan diproses
            $ids = array_map(function($item) { return $item['id']; }, $items);
            $id_placeholders = implode(',', array_fill(0, count($ids), '?'));
            $id_types = str_repeat('i', count($ids));
            
            // Tandai item-item ini sebagai 'processing'
            $sqlUpdate = "UPDATE import_queue SET status = 'processing' WHERE id IN ($id_placeholders)";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            if (!$stmtUpdate) throw new Exception("Prepare update failed: " . $conn->error);
            
            $stmtUpdate->bind_param($id_types, ...$ids);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
        
        // Selesaikan transaksi
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error getPendingImportItems: " . $e->getMessage());
        $items = [];
    }
    
    $conn->close();
    return $items;
}

/**
 * Memperbarui status item antrian setelah diproses.
 * @param int $id ID item antrian.
 * @param string $status 'completed' atau 'failed'.
 * @param string|null $message Pesan error jika gagal.
 * @return bool
 */
function updateImportQueueItemStatus($id, $status, $message = null) {
    $conn = connectDB();
    $sql = "UPDATE import_queue SET status = ?, error_message = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed for updateImportQueueItemStatus: " . $conn->error);
        $conn->close();
        return false;
    }
    
    $stmt->bind_param("ssi", $status, $message, $id);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Failed to update queue status for ID $id: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    return $success;
}
function getUncategorizedVideos($limit = 20, $offset = 0) {
    $conn = connectDB();
    $total = 0;
    $videos = [];

    // Pertama, hitung total
    $countSql = "SELECT COUNT(id) as count FROM videos WHERE category_id IS NULL OR category_id = 0";
    $resultCount = $conn->query($countSql);
    if ($resultCount) {
        $total = $resultCount->fetch_assoc()['count'] ?? 0;
    } else {
        error_log("Error counting uncategorized videos: " . $conn->error);
    }

    // Kedua, ambil data dengan limit dan offset
    if ($total > 0) {
        $dataSql = "SELECT id, original_title, image_url, slug FROM videos 
                    WHERE category_id IS NULL OR category_id = 0 
                    ORDER BY id DESC 
                    LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($dataSql);
        if ($stmt) {
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $resultData = $stmt->get_result();
            $videos = $resultData->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            error_log("Error fetching uncategorized videos: " . $conn->error);
        }
    }

    $conn->close();
    return ['videos' => $videos, 'total' => $total];
}

/**
 * Memperbarui kategori untuk sekumpulan video secara massal.
 * @param array $videoIds Array berisi ID video yang akan diperbarui.
 * @param int $categoryId ID kategori baru.
 * @return bool True jika berhasil, false jika gagal.
 */
function updateVideoCategoriesBulk(array $videoIds, $categoryId) {
    if (empty($videoIds) || empty($categoryId)) {
        return false;
    }

    $conn = connectDB();
    
    // Buat placeholder (?) sebanyak jumlah ID
    $placeholders = implode(',', array_fill(0, count($videoIds), '?'));
    
    // Tipe data: 'i' untuk category_id, lalu 'i' untuk setiap video ID
    $types = 'i' . str_repeat('i', count($videoIds));
    
    // Gabungkan parameter
    $params = array_merge([$categoryId], $videoIds);
    
    $sql = "UPDATE videos SET category_id = ? WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for updateVideoCategoriesBulk: " . $conn->error);
        $conn->close();
        return false;
    }
    
    $stmt->bind_param($types, ...$params);
    
    $success = $stmt->execute();
    if (!$success) {
        error_log("Execute failed for updateVideoCategoriesBulk: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    return $success;
}
?>