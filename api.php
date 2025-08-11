<?php
// Error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/tournament_data.json';

function loadData($file) {
    if (!file_exists($file)) return null;
    $content = file_get_contents($file);
    if ($content === false) return null;
    $data = json_decode($content, true);
    return $data ?? null;
}

function saveData($file, $data) {
    $data['lastUpdated'] = date('c');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    // Backup
    if (file_exists($file)) {
        $backupFile = $file . '.backup.' . date('Ymd_His');
        copy($file, $backupFile);
        $backups = glob($file . '.backup.*');
        if (count($backups) > 5) {
            sort($backups);
            foreach (array_slice($backups, 0, -5) as $old) unlink($old);
        }
    }

    return file_put_contents($file, $json) !== false;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_GET['_method']) && $_GET['_method'] === 'DELETE') {
    $method = 'DELETE';
}

$response = ['success' => false];

try {
    if ($method === 'GET') {
        $data = loadData($dataFile);
        if ($data === null) throw new Exception('Không thể đọc dữ liệu');
        $response['success'] = true;
        $response['data'] = $data;
        $response['message'] = 'Đã tải dữ liệu';
    }

    elseif ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!$data || !isset($data['matchResults'], $data['teamStats'])) {
            throw new Exception('Dữ liệu không hợp lệ');
        }
        if (!saveData($dataFile, $data)) {
            throw new Exception('Không thể lưu dữ liệu');
        }
        $response['success'] = true;
        $response['message'] = 'Đã lưu dữ liệu';
    }

    elseif ($method === 'DELETE') {
        if (file_exists($dataFile)) {
            $backupFile = $dataFile . '.deleted.' . date('Ymd_His');
            copy($dataFile, $backupFile);
            unlink($dataFile);
            $response['success'] = true;
            $response['message'] = 'Đã xóa dữ liệu';
        } else {
            $response['success'] = true;
            $response['message'] = 'Không có dữ liệu để xóa';
        }
    }

    else {
        http_response_code(405);
        throw new Exception('Phương thức không hỗ trợ');
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log('API Error: ' . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
