<?php
// File: admin/backend_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'search':
        $searchKeyword = $_GET['q'] ?? '';
        $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $apiSource = $_GET['api_source'] ?? 'earnvids';
        if (empty($searchKeyword)) {
            echo json_encode(['status' => 'error', 'message' => 'Kata kunci pencarian diperlukan.']);
            exit;
        }
        $searchUrl = '';
        if ($apiSource === 'earnvids') {
            $searchUrl = EARNVIDS_SEARCH_URL . "?key=" . urlencode(EARNVIDS_API_KEY) . "&q=" . urlencode($searchKeyword) . "&page=" . urlencode($currentPage);
        } elseif ($apiSource === 'streamhg') {
            $searchUrl = STREAMHG_SEARCH_URL . "?key=" . urlencode(STREAMHG_API_KEY) . "&q=" . urlencode($searchKeyword) . "&page=" . urlencode($currentPage);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Sumber API tidak valid.']);
            exit;
        }
        $results = makeExternalApiCall($searchUrl);
        echo json_encode($results);
        break;

    case 'ajax_clone_single':
        $embed_id = $_POST['embed_id'] ?? '';
        if (empty($embed_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Embed ID tidak ada.']);
            exit;
        }

        $finalCategoryId = null;
        $newCategoryName = trim($_POST['new_category_name'] ?? '');
        $existingCategoryId = (int)($_POST['existing_category_id'] ?? 0);
        if (!empty($newCategoryName)) {
            $finalCategoryId = insertCategoryIfNotExist($newCategoryName);
        } elseif ($existingCategoryId > 0) {
            $finalCategoryId = $existingCategoryId;
        }

        $originalTitle = $_POST['original_title'] ?? 'Judul Tidak Diketahui';
        $excludeKeywords = $_POST['exclude_keywords'] ?? '';
        $finalTitle = $originalTitle;
        if (!empty($excludeKeywords)) {
            $keywordsArray = array_map('trim', explode(',', $excludeKeywords));
            foreach ($keywordsArray as $keyword) {
                if (!empty($keyword)) {
                    $finalTitle = str_ireplace($keyword, '', $finalTitle);
                }
            }
            $finalTitle = trim(preg_replace('/\s+/', ' ', $finalTitle));
            $finalTitle = trim(preg_replace('/-\s*$/', '', $finalTitle));
        }

        if (doesVideoTitleExist($finalTitle)) {
            echo json_encode(['status' => 'error', 'message' => 'Judul video sudah ada di database.']);
            exit;
        }

        $apiSourceToClone = $_POST['api_source'] ?? 'earnvids';
        $cloneUrl = '';
        $embedBaseDomain = '';
        $embedBasePath = '';
        if ($apiSourceToClone === 'earnvids') {
            $cloneUrl = EARNVIDS_CLONE_URL_BASE . "?key=" . urlencode(EARNVIDS_API_KEY) . "&file_code=" . urlencode($embed_id);
            $embedBaseDomain = EARNVIDS_EMBED_NEW_DOMAIN;
            $embedBasePath = EARNVIDS_EMBED_NEW_PATH;
        } elseif ($apiSourceToClone === 'streamhg') {
            $cloneUrl = STREAMHG_CLONE_URL_BASE . "?key=" . urlencode(STREAMHG_CLONE_API_KEY) . "&file_code=" . urlencode($embed_id);
            $embedBaseDomain = STREAMHG_EMBED_NEW_DOMAIN;
            $embedBasePath = STREAMHG_EMBED_NEW_PATH;
        }

        if (empty($cloneUrl)) {
             echo json_encode(['status' => 'error', 'message' => 'Sumber API Kloning tidak valid.']);
             exit;
        }

        $cloneResults = makeExternalApiCall($cloneUrl);
        if (isset($cloneResults['status']) && $cloneResults['status'] === 200 && isset($cloneResults['result'])) {
            $clonedVideoData = $cloneResults['result'];
            $generatedEmbedUrl = $embedBaseDomain . $embedBasePath . ($clonedVideoData['filecode'] ?? $embed_id);
            
            $randomViews = rand(1000, 10000);
            $randomLikes = rand(500, $randomViews);

            $dataToInsert = [
                'original_title'    => $finalTitle,
                'embed_id'          => $clonedVideoData['filecode'] ?? null,
                'embed_url'         => $generatedEmbedUrl,
                'api_source'        => $apiSourceToClone,
                'image_url'         => $_POST['original_image'],
                'duration'          => $_POST['original_duration'],
                'quality'           => 'HD',
                'category_id'       => $finalCategoryId,
                'views'             => $randomViews,
                'likes'             => $randomLikes,
                'description'       => '', 'tags' => '', 'actresses' => '', 'studios' => ''
            ];

            if (insertClonedVideo($dataToInsert)) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke DB (mungkin duplikat).']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal kloning dari API: ' . ($cloneResults['msg'] ?? 'Error')]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid.']);
        break;
}
?>