<?php

class CategoryModel
{
    private $conn;
    private $table_name = "category";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // 1. Lấy tất cả danh mục
    public function getCategories()
    {
        try {
            $query = "SELECT id, name, description FROM category ORDER BY id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Lỗi getCategories: " . $e->getMessage());
            return [];
        }
    }

    // 2. Lấy chi tiết 1 danh mục theo ID
    public function getCategoryById($id)
    {
        try {
           $query = "SELECT id, name, description FROM category WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Lỗi getCategoryById: " . $e->getMessage());
            return null;
        }
    }

    // 3. Thêm danh mục mới
    public function addCategory($name, $description)
    {
        try {
            $query = "INSERT INTO " . $this->table_name . " (name, description) VALUES (:name, :description)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                'name' => htmlspecialchars(strip_tags($name)),
                'description' => htmlspecialchars(strip_tags($description))
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi addCategory: " . $e->getMessage());
            return false;
        }
    }

    // 4. Cập nhật danh mục
    public function updateCategory($id, $name, $description)
    {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET name = :name, description = :description 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                'name' => htmlspecialchars(strip_tags($name)),
                'description' => htmlspecialchars(strip_tags($description)),
                'id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi updateCategory: " . $e->getMessage());
            return false;
        }
    }

    // 5. Xóa danh mục
    public function deleteCategory($id)
    {
        try {
            // Lưu ý: Nếu có ràng buộc khóa ngoại (Foreign Key) với bảng sản phẩm, 
            // việc xóa sẽ thất bại nếu danh mục này đang chứa sản phẩm.
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            // Log lỗi (ví dụ: lỗi vi phạm ràng buộc khóa ngoại)
            error_log("Lỗi deleteCategory: " . $e->getMessage());
            return false;
        }
    }
}
?>