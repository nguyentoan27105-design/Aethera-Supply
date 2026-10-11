<?php

class TraceScanLogger
{
    public static function ensureSchema(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS qr_scan_history (
                id INT NOT NULL AUTO_INCREMENT,
                product_id INT DEFAULT NULL,
                batch_id INT DEFAULT NULL,
                batch_code VARCHAR(120) DEFAULT NULL,
                scanned_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                scanner_user_id INT DEFAULT NULL,
                scanner_name VARCHAR(150) DEFAULT NULL,
                ip_address VARCHAR(64) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                source VARCHAR(40) DEFAULT 'web',
                PRIMARY KEY (id),
                KEY idx_qr_scan_product (product_id),
                KEY idx_qr_scan_batch (batch_id),
                KEY idx_qr_scan_time (scanned_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function log(PDO $db, int $productId, ?int $batchId = null, ?string $batchCode = null, string $source = 'web'): void
    {
        try {
            self::ensureSchema($db);
            $stmt = $db->prepare("
                INSERT INTO qr_scan_history (
                    product_id, batch_id, batch_code, scanner_user_id,
                    scanner_name, ip_address, user_agent, source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId > 0 ? $productId : null,
                $batchId ?: null,
                $batchCode ?: null,
                $_SESSION['user_id'] ?? null,
                $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Khach truy xuat',
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                $source,
            ]);
        } catch (Throwable $e) {
            error_log('Cannot log QR scan: ' . $e->getMessage());
        }
    }

    public static function recent(PDO $db, int $productId, int $limit = 8): array
    {
        try {
            self::ensureSchema($db);
            $stmt = $db->prepare("
                SELECT *
                FROM qr_scan_history
                WHERE product_id = ?
                ORDER BY scanned_at DESC, id DESC
                LIMIT " . max(1, min(30, $limit))
            );
            $stmt->execute([$productId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        } catch (Throwable $e) {
            error_log('Cannot read QR scans: ' . $e->getMessage());
            return [];
        }
    }
}
