<?php
/**
 * File: index.php
 * Chức năng: Điều hướng (Router) chính cho dự án webbanhang
 */

// Bắt đầu session để quản lý đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = ini_get('session.save_path');
    $sessionPath = $sessionPath !== false ? trim((string)$sessionPath) : '';
    if (str_contains($sessionPath, ';')) {
        $sessionPath = trim((string)substr($sessionPath, strrpos($sessionPath, ';') + 1));
    }

    if ($sessionPath === '' || !is_dir($sessionPath) || !is_writable($sessionPath)) {
        $fallbackSessionPath = __DIR__ . '/storage/sessions';
        if (!is_dir($fallbackSessionPath)) {
            @mkdir($fallbackSessionPath, 0775, true);
        }
        if (is_dir($fallbackSessionPath) && is_writable($fallbackSessionPath)) {
            session_save_path($fallbackSessionPath);
        }
    }

    session_start();
}

// 1. NHÚNG CÁC TỆP DÙNG CHUNG (Cấu hình và Helper)
$required_files = [
    'app/config/Database.php',
    'app/helpers/SessionHelper.php',
    'app/models/ProductModel.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// 2. XỬ LÝ URL
$url = $_GET['url'] ?? '';
$url = rtrim($url, '/');
$url = filter_var($url, FILTER_SANITIZE_URL);
$url = explode('/', $url);

// Xác định Controller và Action gốc
$controllerRaw = $url[0] ?? '';
$action = (isset($url[1]) && $url[1] != '') ? $url[1] : 'index';

// --- PHẦN 1: XỬ LÝ CÁC ROUTE ĐẶC BIỆT (Như Chatbot AI) ---
if ($controllerRaw === 'chat' && $action === 'sendMessage') {
    $chatFile = 'app/controllers/ChatController.php';
    if (file_exists($chatFile)) {
        require_once $chatFile;
        $chatController = new ChatController();
        $chatController->sendMessage();
        exit; 
    }
}

// --- PHẦN 2: XỬ LÝ API (Dành cho Mobile/AJAX) ---
$controllerName = (!empty($controllerRaw)) ? ucfirst($controllerRaw) . 'Controller' : 'ProductController';

if ($controllerRaw === 'api'
    && isset($url[1], $url[2])
    && $url[1] === 'product'
    && $url[2] === 'traceability'
) {
    $id = $url[3] ?? null;

    if ($id && is_numeric($id)) {
        require_once 'app/controllers/ProductApiController.php';

        $controller = new ProductApiController();
        $controller->traceability($id);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Thieu ID san pham'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($controllerName === 'ApiController' && isset($url[1])) {
    $apiControllerName = ucfirst($url[1]) . 'ApiController';
    $apiFile = 'app/controllers/' . $apiControllerName . '.php';

    if (file_exists($apiFile)) {
        require_once $apiFile;
        $controller = new $apiControllerName();
        $method = $_SERVER['REQUEST_METHOD'];
        $id = $url[2] ?? null;
        
        switch ($method) {
            case 'GET':    $action = $id ? 'show' : 'index'; break;
            case 'POST':   $action = 'store'; break;
            // LỖI Ở ĐÂY ĐÃ ĐƯỢC SỬA: Thay ':' thành '?'
            case 'PUT':    $action = $id ? 'update' : 'index'; break; 
            case 'DELETE': $action = $id ? 'destroy' : 'index'; break;
            default:
                http_response_code(405);
                echo json_encode(['message' => 'Method Not Allowed']);
                exit;
        }
        
        if (method_exists($controller, $action)) {
            call_user_func_array([$controller, $action], $id ? [$id] : []);
        } else {
            http_response_code(404);
            echo json_encode(['message' => "API Action '$action' not found"]);
        }
        exit;
    }
}
// --- PHẦN 3: XỬ LÝ WEB (Giao diện người dùng và Admin) ---
$controllerFile = 'app/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    if (class_exists($controllerName)) {
        $controller = new $controllerName();
    } else {
        die("❌ Lỗi: Class <b>$controllerName</b> không tồn tại.");
    }
} else {
    http_response_code(404);
    die("❌ Lỗi: Không tìm thấy Controller <b>$controllerName</b>.");
}

// Thực thi Action
if (method_exists($controller, $action)) {
    $params = array_slice($url, 2);
    call_user_func_array([$controller, $action], $params);
} else {
    http_response_code(404);
    die("❌ Lỗi: Action <b>$action</b> không tồn tại trong <b>$controllerName</b>.");
}
