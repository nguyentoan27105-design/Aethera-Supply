<?php
// Require SessionHelper and other necessary files
require_once(__DIR__ . '/../config/database.php');
require_once(__DIR__ . '/../models/CategoryModel.php');
require_once(__DIR__ . '/../helpers/Rbac.php');
class CategoryController
{
private $categoryModel;
private $db;
private $baseUrl = "/webbanhang";
public function __construct()
{
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$this->db = (new Database())->getConnection();
$this->categoryModel = new CategoryModel($this->db);
}
private function requireCategoryAccess()
{
    $role = Rbac::normalizeRole($_SESSION['user_role'] ?? null);
    if (!isset($_SESSION['user_id']) || !Rbac::can($role, 'categories')) {
        header("Location: " . $this->baseUrl . "/account/login");
        exit;
    }
}
public function edit($id)
{
    $this->requireCategoryAccess();
    header("Location: " . $this->baseUrl . "/admin/edit_category/" . (int)$id);
    exit;
}
public function update($id)
{
    $this->requireCategoryAccess();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $_SESSION['error'] = "Tên không được để trống";
            header("Location: " . $this->baseUrl . "/admin/edit_category/" . (int)$id);
            exit;
        }

        $result = $this->categoryModel->updateCategory($id, $name, $description);

        if ($result) {
            $_SESSION['success'] = "Cập nhật thành công";
        } else {
            $_SESSION['error'] = "Cập nhật thất bại";
        }

        // 🔥 redirect về list
        header("Location: " . $this->baseUrl . "/admin/categories");
        exit;
    }
    header("Location: " . $this->baseUrl . "/admin/edit_category/" . (int)$id);
    exit;
}
public function list()
{
    $this->requireCategoryAccess();
    header("Location: " . $this->baseUrl . "/admin/categories");
    exit;
}
}
?>
