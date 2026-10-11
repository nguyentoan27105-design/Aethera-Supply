<?php
require_once('app/config/database.php');
require_once('app/models/CategoryModel.php');
require_once('app/helpers/Rbac.php');

class CategoryApiController
{
    private $categoryModel;
    private $db;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = (new Database())->getConnection();
        $this->categoryModel = new CategoryModel($this->db);
        header('Content-Type: application/json; charset=utf-8');
    }

    private function requireAdmin(): bool
    {
        $role = Rbac::normalizeRole($_SESSION['user_role'] ?? null);
        if (!isset($_SESSION['user_id']) || !Rbac::can($role, 'categories')) {
            http_response_code(403);
            echo json_encode(['message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return true;
    }

    // ================================
    // 🔥 LẤY TẤT CẢ DANH MỤC
    // ================================
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        $categories = $this->categoryModel->getCategories();
        
        // Đảm bảo trả về mảng rỗng nếu không có dữ liệu
        echo json_encode($categories ?: [], JSON_UNESCAPED_UNICODE);
    }

    // ================================
    // 🔥 LẤY CHI TIẾT 1 DANH MỤC
    // ================================
    public function show($id)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Giả sử Model của bạn có hàm getCategoryById
        $category = $this->categoryModel->getCategoryById($id);

        if ($category) {
            echo json_encode($category, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Không tìm thấy danh mục'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================
    // 🔥 THÊM DANH MỤC MỚI
    // ================================
    public function store()
{
    if (!$this->requireAdmin()) {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');

    // 🔥 FIX: nhận cả JSON + FORM
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        $data = $_POST;
    }

    if (empty(trim($data['name'] ?? ''))) {
        http_response_code(400);
        echo json_encode(['message' => 'Tên danh mục không được để trống'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');

    $result = $this->categoryModel->addCategory($name, $description);

    if ($result) {
        echo json_encode(['message' => 'Tạo danh mục thành công'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Lỗi khi thêm danh mục'], JSON_UNESCAPED_UNICODE);
    }
}

    // ================================
    // 🔥 CẬP NHẬT DANH MỤC
    // ================================
    public function update($id)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || empty(trim($data['name'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['message' => 'Dữ liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');

        // Giả sử Model có hàm updateCategory
        $result = $this->categoryModel->updateCategory($id, $name, $description);

        if ($result) {
            echo json_encode(['message' => 'Cập nhật danh mục thành công'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Cập nhật thất bại'], JSON_UNESCAPED_UNICODE);
        }
    }

    // ================================
    // 🔥 XÓA DANH MỤC
    // ================================
    public function destroy($id)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        // Lưu ý: Cần kiểm tra xem danh mục có đang chứa sản phẩm nào không trước khi xóa
        $result = $this->categoryModel->deleteCategory($id);

        if ($result) {
            echo json_encode(['message' => 'Xóa danh mục thành công'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Không thể xóa (danh mục có thể đang chứa sản phẩm)'], JSON_UNESCAPED_UNICODE);
        }
    }
}
