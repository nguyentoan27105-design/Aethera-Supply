<?php
class ReviewModel {
    private $db;
    private string $table = 'comments';

    public function __construct($db) {
        $this->db = $db;
    }

    // Reviews are stored in comments; there is no separate reviews table.
    public function addReview($productId, $username, $rating, $comment) {
        $sql = "INSERT INTO {$this->table} (product_id, user_name, rating, content, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([(int)$productId, trim((string)$username), $this->normalizeRating($rating), trim((string)$comment)]);
    }

    // 2. Lấy danh sách đánh giá của một sản phẩm (Dùng ở trang View chi tiết)
    public function getReviewsByProduct($productId) {
        $sql = "SELECT c.*, COALESCE(NULLIF(c.user_name, ''), a.fullname, a.username, 'Khách hàng') AS username
                FROM {$this->table} c
                LEFT JOIN account a ON a.id = c.user_id
                WHERE c.product_id = ?
                ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$productId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // 3. Tính điểm trung bình và tổng số lượt đánh giá từ comments.
    public function getRatingStats($productId) {
        $sql = "SELECT 
                    COALESCE(AVG(rating), 0) as avg_rating,
                    COUNT(*) as total_reviews 
                FROM {$this->table}
                WHERE product_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$productId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // 4. MỚI: Kiểm tra xem user đã mua hàng chưa mới cho đánh giá (Tùy chọn nâng cao)
    // Nếu bạn muốn bảo mật hơn, có thể dùng hàm này trong Controller trước khi gọi addReview
    public function canUserReview($productId, $userId) {
        $sql = "SELECT o.id FROM orders o 
                JOIN order_details od ON o.id = od.order_id 
                WHERE o.user_id = ? AND od.product_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $productId]);
        return $stmt->fetch() ? true : false;
    }

    private function normalizeRating($rating): int
    {
        return max(1, min(5, (int)$rating));
    }
}
