<?php
class AdminModel
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Thống kê tổng quan: Doanh thu, Đơn hàng, Sản phẩm, Người dùng
     */
    public function getTotals()
    {
        try {
            $totals = [];

            // 1. Tổng doanh thu (Chỉ tính các đơn hàng đã hoàn thành)
            $stmtRevenue = $this->conn->query("SELECT SUM(total) FROM orders WHERE status = 'completed'");
            $totals['total_revenue'] = $stmtRevenue->fetchColumn() ?: 0;

            // 2. Tổng số đơn hàng
            $stmtOrders = $this->conn->query("SELECT COUNT(*) FROM orders");
            $totals['total_orders'] = $stmtOrders->fetchColumn() ?: 0;

            // 3. Tổng số sản phẩm
            $stmtProducts = $this->conn->query("SELECT COUNT(*) FROM product");
            $totals['total_products'] = $stmtProducts->fetchColumn() ?: 0;

            // 4. Tổng số thành viên (Bảng account)
            $stmtUsers = $this->conn->query("SELECT COUNT(*) FROM account");
            $totals['total_users'] = $stmtUsers->fetchColumn() ?: 0;

            $stmtStock = $this->conn->query("SELECT COALESCE(SUM(quantity), 0) FROM inventory");
            $totals['total_stock'] = $stmtStock->fetchColumn() ?: 0;

            $stmtCategories = $this->conn->query("SELECT COUNT(*) FROM category");
            $totals['total_categories'] = $stmtCategories->fetchColumn() ?: 0;

            return $totals;
        } catch (PDOException $e) {
            // Trả về mảng 0 nếu có lỗi để tránh sập giao diện View
            return [
                'total_revenue' => 0,
                'total_orders' => 0,
                'total_products' => 0,
                'total_users' => 0,
                'total_stock' => 0,
                'total_categories' => 0
            ];
        }
    }

    /**
     * Lấy doanh thu theo từng tháng trong năm hiện tại để vẽ biểu đồ
     */
    public function getMonthlyRevenue()
    {
        try {
            $sql = "SELECT MONTH(created_at) as month, SUM(total) as revenue 
                    FROM orders 
                    WHERE YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'
                    GROUP BY MONTH(created_at)
                    ORDER BY month ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Lấy danh sách đơn hàng mới nhất (Hiển thị tại trang Dashboard chính)
     */
    public function getRecentOrders($limit = 5)
    {
        try {
            $sql = "SELECT o.*, o.total as total_price, COALESCE(o.fullname, a.fullname, 'Khách lẻ') as fullname
                    FROM orders o 
                    LEFT JOIN account a ON o.user_id = a.id 
                    ORDER BY o.created_at DESC 
                    LIMIT :limit";
            $stmt = $this->conn->prepare($sql);
            // Ép kiểu limit về số nguyên để tránh lỗi SQL Injection hoặc cú pháp
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
