<?php
class PromotionModel {
    private $db;
    private $table = "promotions";

    public function __construct($db) {
        if (!$db) {
            die("Database connection in PromotionModel is null");
        }
        $this->db = $db;
        $this->ensureTable();
    }

    private function ensureTable(): void {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(80) NOT NULL UNIQUE,
                    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
                    expiry_date DATE NOT NULL,
                    status TINYINT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            error_log("Cannot ensure promotions table: " . $e->getMessage());
        }
    }

    public function checkVoucher($code) {
        try {
            $code = strtoupper(trim((string)$code));
            if ($code === '') {
                return false;
            }

            $sql = "SELECT *
                    FROM {$this->table}
                    WHERE UPPER(code) = ?
                      AND status = 1
                      AND expiry_date >= CURDATE()
                      AND discount_percent > 0
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$code]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("checkVoucher error: " . $e->getMessage());
            return false;
        }
    }

    public function getAllPromotions() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY id DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("getAllPromotions error: " . $e->getMessage());
            return [];
        }
    }

    public function getBestActivePromotion() {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM {$this->table}
                WHERE status = 1
                  AND expiry_date >= CURDATE()
                  AND discount_percent > 0
                ORDER BY discount_percent DESC, expiry_date ASC, id DESC
                LIMIT 1
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (PDOException $e) {
            error_log("getBestActivePromotion error: " . $e->getMessage());
            return null;
        }
    }

    public function addPromotion($code, $discount_percent, $expiry_date) {
        try {
            $code = strtoupper(trim((string)$code));
            $discount_percent = max(1, min(100, (float)$discount_percent));
            $expiry_date = trim((string)$expiry_date);

            if ($code === '' || $expiry_date === '') {
                return false;
            }

            $checkStmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE UPPER(code) = ? LIMIT 1");
            $checkStmt->execute([$code]);
            if ($checkStmt->fetch()) {
                return false;
            }

            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (code, discount_percent, expiry_date, status)
                VALUES (?, ?, ?, 1)
            ");
            return $stmt->execute([$code, $discount_percent, $expiry_date]);
        } catch (PDOException $e) {
            error_log("addPromotion error: " . $e->getMessage());
            return false;
        }
    }

    public function deletePromotion($id) {
        try {
            return $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([(int)$id]);
        } catch (PDOException $e) {
            error_log("deletePromotion error: " . $e->getMessage());
            return false;
        }
    }

    public function toggleStatus($id, $currentStatus) {
        try {
            $newStatus = ((int)$currentStatus === 1) ? 0 : 1;
            return $this->db->prepare("UPDATE {$this->table} SET status = ? WHERE id = ?")->execute([$newStatus, (int)$id]);
        } catch (PDOException $e) {
            error_log("toggleStatus error: " . $e->getMessage());
            return false;
        }
    }

    public function toggleStatusById($id) {
        try {
            $stmt = $this->db->prepare("SELECT status FROM {$this->table} WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$id]);
            $status = $stmt->fetchColumn();
            if ($status === false) {
                return false;
            }
            return $this->toggleStatus((int)$id, (int)$status);
        } catch (PDOException $e) {
            error_log("toggleStatusById error: " . $e->getMessage());
            return false;
        }
    }
}
