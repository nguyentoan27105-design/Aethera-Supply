<?php
class CommentModel
{
    private $conn;
    private $table_name = "comments";

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Lấy danh sách comment theo sản phẩm
    public function getCommentsByProduct($product_id)
    {
        $query = "SELECT c.*,
                         COALESCE(NULLIF(c.user_name, ''), a.fullname, a.username, 'Khách hàng') AS user_name
                  FROM " . $this->table_name . " c
                  LEFT JOIN account a ON a.id = c.user_id
                  WHERE c.product_id = :product_id 
                  ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // Thêm comment
    public function addComment($product_id, $user_name, $content, $rating = 5, $user_id = null)
    {
        $query = "INSERT INTO " . $this->table_name . " 
                  (product_id, user_id, user_name, content, rating, created_at)
                  VALUES (:product_id, :user_id, :user_name, :content, :rating, NOW())";

        $stmt = $this->conn->prepare($query);
        $productId = (int)$product_id;
        $userId = $user_id !== null ? (int)$user_id : null;
        $userName = trim((string)$user_name);
        $body = trim((string)$content);
        $safeRating = max(1, min(5, (int)$rating));
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':user_name', $userName);
        $stmt->bindParam(':content', $body);
        $stmt->bindParam(':rating', $safeRating, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function getRatingStats($product_id)
    {
        $query = "SELECT COALESCE(AVG(rating), 0) AS avg_rating,
                         COUNT(*) AS total_reviews
                  FROM " . $this->table_name . "
                  WHERE product_id = :product_id";

        $stmt = $this->conn->prepare($query);
        $productId = (int)$product_id;
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_OBJ);
    }
}
?>
