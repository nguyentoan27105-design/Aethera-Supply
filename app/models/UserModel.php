<?php
require_once __DIR__ . '/../helpers/Rbac.php';

class UserModel {
    private $conn;
    private $table_name = "account"; 

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureRoleSchema();
    }

    private function ensureRoleSchema(): void {
        try {
            $this->conn->exec("
                ALTER TABLE {$this->table_name}
                MODIFY role ENUM('user','admin','staff','warehouse_staff','export_staff','manager','supplier') DEFAULT 'user'
            ");
        } catch (Throwable $e) {
            error_log("Không thể mở rộng account.role trong UserModel: " . $e->getMessage());
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'supplier_id'
            ");
            $stmt->execute([$this->table_name]);
            if ((int)$stmt->fetchColumn() === 0) {
                $this->conn->exec("ALTER TABLE {$this->table_name} ADD supplier_id INT NULL");
            }
        } catch (Throwable $e) {
            error_log("Không thể thêm account.supplier_id trong UserModel: " . $e->getMessage());
        }
    }

    // 1. Lấy danh sách thành viên (Đã sửa để đồng bộ cách gọi dữ liệu)
    public function getAllUsers() {
        $query = "SELECT id, username, fullname, email, role, phone, status, supplier_id FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        // Thay đổi thành FETCH_OBJ để dùng $user->fullname đồng nhất với getUserById
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // 2. Lấy thông tin 1 thành viên theo ID
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // 3. Cập nhật thông tin thành viên
    public function updateUser($id, $fullname, $email, $phone, $role, $supplierId = null) {
        $role = Rbac::normalizeRole($role);
        $query = "UPDATE " . $this->table_name . " 
                  SET fullname = ?, email = ?, phone = ?, role = ?, supplier_id = ?
                  WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$fullname, $email, $phone, $role, $supplierId ?: null, $id]);
    }

    // 4. Xóa thành viên
    public function deleteUser($id) {

    // kiểm tra user có đơn hàng không
    $check = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $check->execute([$id]);

    if ($check->fetchColumn() > 0) {
        return false; // không cho xóa
    }

    $query = "DELETE FROM account WHERE id = ?";
    $stmt = $this->conn->prepare($query);

    return $stmt->execute([$id]);
}
}
