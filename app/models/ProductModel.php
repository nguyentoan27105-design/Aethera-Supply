<?php

class ProductModel
{
    private $conn;
    private $table_name = "product"; 

    public function __construct($db)
    {
        if (!$db) {
            die("❌ Database connection is null");
        }
        $this->conn = $db;
        $this->ensureSupplyColumns();
        $this->ensureMarketingColumns();
    }

    private function ensureSupplyColumns(): void
    {
        $this->addColumnIfMissing('origin', "VARCHAR(255) DEFAULT NULL");
        $this->addColumnIfMissing('supplier', "VARCHAR(150) DEFAULT NULL");
        $this->addColumnIfMissing('standard', "VARCHAR(100) DEFAULT NULL");
        $this->addColumnIfMissing('harvest_date', "DATE DEFAULT NULL");
        $this->addColumnIfMissing('expiry_days', "INT DEFAULT 10");
        $this->addColumnIfMissing('warehouse_address', "TEXT DEFAULT NULL");
    }

    private function ensureMarketingColumns(): void
    {
        $this->addColumnIfMissing('is_flash_sale', "TINYINT DEFAULT 0");
        $this->addColumnIfMissing('flash_discount', "INT DEFAULT 0");
        $this->addColumnIfMissing('is_featured', "TINYINT DEFAULT 0");
    }

    private function addColumnIfMissing(string $column, string $definition): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$this->table_name, $column]);
            if ((int)$stmt->fetchColumn() === 0) {
                $this->conn->exec("ALTER TABLE {$this->table_name} ADD COLUMN {$column} {$definition}");
            }
        } catch (Throwable $e) {
            error_log("Cannot add product marketing column {$column}: " . $e->getMessage());
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log("Cannot inspect table {$table}: " . $e->getMessage());
            return false;
        }
    }

    private function columnExistsInTable(string $table, string $column): bool
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log("Cannot inspect column {$table}.{$column}: " . $e->getMessage());
            return false;
        }
    }

    private function addColumnToTableIfMissing(string $table, string $column, string $definition): void
    {
        if (!$this->tableExists($table) || $this->columnExistsInTable($table, $column)) {
            return;
        }

        $this->conn->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }

    private function foreignKeyDeleteRule(string $table, string $constraint): ?string
    {
        $stmt = $this->conn->prepare("
            SELECT DELETE_RULE
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
        ");
        $stmt->execute([$table, $constraint]);
        $rule = $stmt->fetchColumn();
        return $rule !== false ? strtoupper((string)$rule) : null;
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyDeleteRule($table, $constraint) === null) {
            return;
        }

        $this->conn->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    }

    private function ensureSetNullForeignKey(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $deleteRule = $this->foreignKeyDeleteRule($table, $constraint);
        if ($deleteRule === 'SET NULL') {
            return;
        }

        if ($deleteRule !== null) {
            $this->dropForeignKeyIfExists($table, $constraint);
        }

        $this->conn->exec("
            ALTER TABLE `{$table}`
            ADD CONSTRAINT `{$constraint}`
            FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}`(`{$referencedColumn}`)
            ON DELETE SET NULL
        ");
    }

    private function ensureDeletionHistorySchema(): void
    {
        $this->addColumnToTableIfMissing('order_details', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        $this->addColumnToTableIfMissing('product_batch', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        $this->addColumnToTableIfMissing('inventory_log', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        $this->addColumnToTableIfMissing('product_traceability', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        $this->addColumnToTableIfMissing('qr_scan_history', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        $this->addColumnToTableIfMissing('cultivation_logs', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");

        if ($this->tableExists('product_batch')) {
            $this->dropForeignKeyIfExists('product_batch', 'fk_product_batch_product');
            $this->conn->exec("ALTER TABLE product_batch MODIFY product_id INT DEFAULT NULL");
            $this->ensureSetNullForeignKey('product_batch', 'fk_product_batch_product', 'product_id', 'product', 'id');
        }

        if ($this->tableExists('product_traceability')) {
            $this->ensureSetNullForeignKey('product_traceability', 'fk_product_trace', 'product_id', 'product', 'id');
        }
    }

    private function setSnapshotForProductRows(string $table, int $productId, string $productName): void
    {
        if (!$this->tableExists($table) || !$this->columnExistsInTable($table, 'product_id')) {
            return;
        }

        $setParts = [];
        $params = [];
        if ($this->columnExistsInTable($table, 'product_name_snapshot')) {
            $setParts[] = "product_name_snapshot = COALESCE(NULLIF(product_name_snapshot, ''), ?)";
            $params[] = $productName;
        }

        $setParts[] = "product_id = NULL";
        $params[] = $productId;

        $this->conn->prepare("
            UPDATE `{$table}`
            SET " . implode(', ', $setParts) . "
            WHERE product_id = ?
        ")->execute($params);
    }

    /**
     * Lấy danh sách tất cả sản phẩm
     */
    public function getAll()
    {
        try {
            $query = "SELECT p.*, c.name as category_name
                      FROM " . $this->table_name . " p
                      LEFT JOIN category c ON p.category_id = c.id
                      ORDER BY p.id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ); 
        } catch (PDOException $e) {
            error_log("Lỗi getAll: " . $e->getMessage());
            return [];
        }
    }

    private function availableProductJoins(): string
    {
        return "
                LEFT JOIN (
                    SELECT i.product_id, COALESCE(SUM(i.quantity), 0) AS available_stock
                    FROM inventory i
                    JOIN product_batch b ON b.id = i.batch_id
                    JOIN warehouse w ON w.id = i.warehouse_id
                    WHERE COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                      AND COALESCE(w.status, 'active') = 'active'
                      AND COALESCE(i.quantity, 0) > 0
                    GROUP BY i.product_id
                ) active_stock ON active_stock.product_id = p.id
        ";
    }

    private function latestAvailableBatchJoins(): string
    {
        return "
                LEFT JOIN product_batch latest_batch ON latest_batch.id = (
                    SELECT b.id
                    FROM product_batch b
                    JOIN inventory i ON i.batch_id = b.id
                    JOIN warehouse w ON w.id = i.warehouse_id
                    WHERE b.product_id = p.id
                      AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                      AND COALESCE(w.status, 'active') = 'active'
                      AND COALESCE(i.quantity, 0) > 0
                    ORDER BY COALESCE(b.harvest_date, DATE(b.created_at)) DESC, b.id DESC
                    LIMIT 1
                )
                LEFT JOIN supplier latest_supplier ON latest_supplier.id = latest_batch.supplier_id
                LEFT JOIN warehouse latest_warehouse ON latest_warehouse.id = latest_batch.warehouse_id
                LEFT JOIN (
                    SELECT batch_id, COALESCE(SUM(quantity), 0) AS quantity
                    FROM inventory
                    WHERE quantity > 0
                    GROUP BY batch_id
                ) latest_inventory ON latest_inventory.batch_id = latest_batch.id
        ";
    }

    private function latestAvailableBatchFields(): string
    {
        return "
                       latest_batch.id AS latest_batch_id,
                       latest_batch.batch_code AS latest_batch_code,
                       latest_batch.qr_code AS latest_qr_code,
                       latest_batch.harvest_date AS latest_harvest_date,
                       latest_batch.quality_standard AS latest_quality_standard,
                       latest_batch.preservation_method AS latest_preservation_method,
                       latest_batch.storage_temperature AS latest_storage_temperature,
                       latest_batch.storage_humidity AS latest_storage_humidity,
                       COALESCE(latest_inventory.quantity, latest_batch.remaining_quantity, 0) AS latest_batch_remaining_quantity,
                       latest_supplier.name AS latest_supplier_name,
                       latest_supplier.farm_location AS latest_supplier_farm_location,
                       latest_supplier.address AS latest_supplier_address,
                       latest_warehouse.name AS latest_warehouse_name,
                       latest_warehouse.location AS latest_warehouse_location
        ";
    }

    private function availableProductCondition(): string
    {
        return "
                COALESCE(active_stock.available_stock, 0) > 0
        ";
    }

    private function hydrateAvailableProduct(object $product): void
    {
        $product->stock = (int)($product->available_stock ?? $product->stock ?? 0);

        $batchCode = trim((string)($product->latest_batch_code ?? ''));
        $product->trace_batch_code = $batchCode;
        $product->trace_url = '/webbanhang/product/trace/' . (int)($product->id ?? 0)
            . ($batchCode !== '' ? '?batch=' . rawurlencode($batchCode) : '');

        if (trim((string)($product->origin ?? '')) === '') {
            $product->origin = trim((string)($product->latest_supplier_farm_location ?? ''));
            if ($product->origin === '') {
                $product->origin = trim((string)($product->latest_supplier_address ?? ''));
            }
        }
        if (trim((string)($product->supplier ?? '')) === '') {
            $product->supplier = trim((string)($product->latest_supplier_name ?? ''));
        }
        if (trim((string)($product->standard ?? '')) === '') {
            $product->standard = trim((string)($product->latest_quality_standard ?? ''));
        }
        if (empty($product->harvest_date) && !empty($product->latest_harvest_date)) {
            $product->harvest_date = $product->latest_harvest_date;
        }
        if (trim((string)($product->warehouse_address ?? '')) === '') {
            $warehouse = trim((string)($product->latest_warehouse_location ?? ''));
            if ($warehouse === '') {
                $warehouse = trim((string)($product->latest_warehouse_name ?? ''));
            }
            $product->warehouse_address = $warehouse;
        }
    }

    /**
     * Lấy sản phẩm đang được phép bán trên cửa hàng.
     * Nếu sản phẩm có lô hàng, chỉ tính tồn từ lô chưa bị thu hồi.
     */
    public function getAvailableProducts($keyword = null)
    {
        try {
            $where = "WHERE " . $this->availableProductCondition();
            $params = [];

            if ($keyword !== null && trim((string)$keyword) !== '') {
                $where .= " AND (p.name LIKE :keyword OR p.description LIKE :keyword)";
                $params[':keyword'] = '%' . trim((string)$keyword) . '%';
            }

            $availabilityJoins = $this->availableProductJoins();
            $latestBatchJoins = $this->latestAvailableBatchJoins();
            $latestBatchFields = $this->latestAvailableBatchFields();
            $query = "
                SELECT p.*, c.name AS category_name,
                       COALESCE(active_stock.available_stock, 0) AS available_stock,
                       $latestBatchFields
                FROM " . $this->table_name . " p
                LEFT JOIN category c ON p.category_id = c.id
                $availabilityJoins
                $latestBatchJoins
                $where
                ORDER BY p.id DESC
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_OBJ);

            foreach ($products as $product) {
                $this->hydrateAvailableProduct($product);
            }

            return $products;
        } catch (PDOException $e) {
            error_log("Lỗi getAvailableProducts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Chi lay san pham con ban duoc de cau hinh Flash Sale / Noi bat.
     */
    public function getMarketingEligibleProducts($keyword = null)
    {
        $this->clearMarketingForUnavailableProducts();
        return $this->getAvailableProducts($keyword);
    }

    public function clearMarketingForUnavailableProducts(): int
    {
        try {
            $availabilityJoins = $this->availableProductJoins();
            $availabilityCondition = $this->availableProductCondition();
            $stmt = $this->conn->prepare("
                UPDATE {$this->table_name} p
                $availabilityJoins
                SET p.is_flash_sale = 0,
                    p.flash_discount = 0,
                    p.is_featured = 0
                WHERE (
                    COALESCE(p.is_flash_sale, 0) = 1
                    OR COALESCE(p.is_featured, 0) = 1
                    OR COALESCE(p.flash_discount, 0) > 0
                )
                  AND NOT ($availabilityCondition)
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log("Cannot clear unavailable marketing products: " . $e->getMessage());
            return 0;
        }
    }

    public function getProducts()
    {
        return $this->getAll();
    }

    /**
     * Bí danh giúp tương thích với ChatController
     */
    public function getAllProducts()
    {
        return $this->getAll();
    }

    public function updateFlashSaleSettings(array $productIds, array $discounts): bool
    {
        try {
            $this->conn->beginTransaction();
            $this->conn->exec("UPDATE {$this->table_name} SET is_flash_sale = 0, flash_discount = 0");

            $availabilityJoins = $this->availableProductJoins();
            $availabilityCondition = $this->availableProductCondition();
            $stmt = $this->conn->prepare("
                UPDATE {$this->table_name} p
                $availabilityJoins
                SET p.is_flash_sale = 1, p.flash_discount = :discount
                WHERE p.id = :id
                  AND $availabilityCondition
            ");

            foreach ($productIds as $productId) {
                $id = (int)$productId;
                if ($id <= 0) {
                    continue;
                }

                $discount = (int)($discounts[$id] ?? 10);
                $discount = max(1, min(90, $discount));
                $stmt->execute([
                    ':id' => $id,
                    ':discount' => $discount
                ]);
            }

            return $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Cannot update flash sale settings: " . $e->getMessage());
            return false;
        }
    }

    public function updateFeaturedSettings(array $productIds): bool
    {
        try {
            $this->conn->beginTransaction();
            $this->conn->exec("UPDATE {$this->table_name} SET is_featured = 0");

            $availabilityJoins = $this->availableProductJoins();
            $availabilityCondition = $this->availableProductCondition();
            $stmt = $this->conn->prepare("
                UPDATE {$this->table_name} p
                $availabilityJoins
                SET p.is_featured = 1
                WHERE p.id = :id
                  AND $availabilityCondition
            ");

            foreach ($productIds as $productId) {
                $id = (int)$productId;
                if ($id > 0) {
                    $stmt->execute([':id' => $id]);
                }
            }

            return $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Cannot update featured product settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy chi tiết một sản phẩm theo ID
     */
        /**
     * Lấy chi tiết một sản phẩm theo ID - ĐÃ CÓ DEBUG
     */
    public function getProductById($id)
    {
        try {
            $query = "
                SELECT 
                    p.*,
                    c.name as category_name
                FROM product p
                LEFT JOIN category c ON c.id = p.category_id 
                WHERE p.id = ?
                LIMIT 1
            ";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            
            // === DEBUG ===
            if ($result) {
                error_log("✅ Tìm thấy sản phẩm ID $id: " . $result->name);
            } else {
                error_log("❌ KHÔNG tìm thấy sản phẩm ID: $id");
                
                // Kiểm tra sản phẩm có tồn tại không (bỏ qua join)
                $check = $this->conn->prepare("SELECT id, name FROM product WHERE id = ?");
                $check->execute([$id]);
                $exists = $check->fetch(PDO::FETCH_OBJ);
                
                if ($exists) {
                    error_log("→ Sản phẩm tồn tại nhưng JOIN category bị lỗi");
                }
            }
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("❌ Lỗi SQL getProductById: " . $e->getMessage());
            return null;
        }
    }

    public function getAvailableProductById($id)
    {
        try {
            $availabilityJoins = $this->availableProductJoins();
            $latestBatchJoins = $this->latestAvailableBatchJoins();
            $latestBatchFields = $this->latestAvailableBatchFields();
            $availabilityCondition = $this->availableProductCondition();
            $query = "
                SELECT p.*, c.name AS category_name,
                       COALESCE(active_stock.available_stock, 0) AS available_stock,
                       $latestBatchFields
                FROM product p
                LEFT JOIN category c ON c.id = p.category_id
                $availabilityJoins
                $latestBatchJoins
                WHERE p.id = ?
                  AND $availabilityCondition
                LIMIT 1
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([(int)$id]);
            $product = $stmt->fetch(PDO::FETCH_OBJ);
            if ($product) {
                $this->hydrateAvailableProduct($product);
            }
            return $product ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getAvailableProductById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tìm kiếm sản phẩm theo từ khóa
     */
    public function getLatestAvailableBatchForProduct(int $productId): ?object
    {
        if ($productId <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT b.*, s.name AS supplier_name, s.farm_location, s.address AS supplier_address,
                       w.name AS warehouse_name, w.location AS warehouse_location,
                       COALESCE(inv.quantity, 0) AS calculated_remaining_quantity
                FROM product_batch b
                LEFT JOIN supplier s ON s.id = b.supplier_id
                LEFT JOIN warehouse w ON w.id = b.warehouse_id
                LEFT JOIN (
                    SELECT batch_id, COALESCE(SUM(quantity), 0) AS quantity
                    FROM inventory
                    WHERE quantity > 0
                    GROUP BY batch_id
                ) inv ON inv.batch_id = b.id
                WHERE b.product_id = ?
                  AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  AND COALESCE(inv.quantity, 0) > 0
                  AND COALESCE(w.status, 'active') = 'active'
                ORDER BY COALESCE(b.harvest_date, DATE(b.created_at)) DESC, b.id DESC
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (PDOException $e) {
            error_log("Cannot load latest available batch: " . $e->getMessage());
            return null;
        }
    }

    public function searchProducts($keyword)
    {
        try {
            $query = "SELECT p.*, c.name as category_name 
                      FROM " . $this->table_name . " p
                      LEFT JOIN category c ON p.category_id = c.id
                      WHERE p.name LIKE :keyword OR p.description LIKE :keyword";
            $stmt = $this->conn->prepare($query);
            $search = "%{$keyword}%";
            $stmt->execute([':keyword' => $search]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Lỗi searchProducts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Thêm sản phẩm mới
     */
    public function addProduct($name, $description, $price, $category_id, $image, $stock = 0)
    {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      (name, description, price, category_id, image, stock)
                      VALUES (:name, :description, :price, :category_id, :image, :stock)";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':name'         => $name,
                ':description'  => $description,
                ':price'        => (float)$price,
                ':category_id'  => (int)$category_id,
                ':image'        => $image,
                ':stock'        => 0
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi addProduct: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật thông tin sản phẩm
     */
    public function updateProduct($id, $name, $description, $price, $category_id, $image = null, $stock = 0)
    {
        try {
            $sql_image = !empty($image) ? ", image = :image" : "";
            
            $query = "UPDATE " . $this->table_name . "
                      SET name = :name, 
                          description = :description, 
                          price = :price, 
                          category_id = :category_id 
                          $sql_image
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $params = [
                ':id'           => (int)$id,
                ':name'         => $name,
                ':description'  => $description,
                ':price'        => (float)$price,
                ':category_id'  => (int)$category_id
            ];
            
            if (!empty($image)) {
                $params[':image'] = $image;
            }

            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Lỗi updateProduct: " . $e->getMessage());
            return false;
        }
    }

    public function updateProductCategory(int $productId, int $categoryId): bool
    {
        if ($productId <= 0 || $categoryId <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE {$this->table_name}
                SET category_id = :category_id
                WHERE id = :id
            ");
            return $stmt->execute([
                ':category_id' => $categoryId,
                ':id' => $productId,
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi updateProductCategory: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cập nhật số lượng tồn kho
     */
    public function updateStock($id, $stock)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE " . $this->table_name . " p
                SET stock = (
                    SELECT COALESCE(SUM(i.quantity), 0)
                    FROM inventory i
                    JOIN product_batch b ON b.id = i.batch_id
                    JOIN warehouse w ON w.id = i.warehouse_id
                    WHERE i.product_id = p.id
                      AND i.quantity > 0
                      AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                      AND COALESCE(w.status, 'active') = 'active'
                )
                WHERE p.id = :id
            ");
            return $stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            error_log("Lỗi updateStock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa sản phẩm
     */
    public function deleteProduct($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        $startedTransaction = false;

        try {
            $this->ensureDeletionHistorySchema();

            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $startedTransaction = true;
            }

            $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$product) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            $productName = trim((string)($product->name ?? ''));
            if ($productName === '') {
                $productName = 'Sản phẩm #' . $id;
            }

            $this->setSnapshotForProductRows('order_details', $id, $productName);
            $this->setSnapshotForProductRows('inventory_log', $id, $productName);
            $this->setSnapshotForProductRows('product_traceability', $id, $productName);
            $this->setSnapshotForProductRows('qr_scan_history', $id, $productName);
            $this->setSnapshotForProductRows('cultivation_logs', $id, $productName);

            if ($this->tableExists('inventory')) {
                $this->conn->prepare("DELETE FROM inventory WHERE product_id = ?")->execute([$id]);
            }

            if ($this->tableExists('product_batch')) {
                $fields = [];
                $params = [];
                if ($this->columnExistsInTable('product_batch', 'product_name_snapshot')) {
                    $fields[] = "product_name_snapshot = COALESCE(NULLIF(product_name_snapshot, ''), ?)";
                    $params[] = $productName;
                }
                if ($this->columnExistsInTable('product_batch', 'remaining_quantity')) {
                    $fields[] = "remaining_quantity = 0";
                }
                if ($this->columnExistsInTable('product_batch', 'status')) {
                    $fields[] = "status = 'archived'";
                }
                if ($this->columnExistsInTable('product_batch', 'notes')) {
                    $fields[] = "notes = CONCAT(COALESCE(notes, ''), ?)";
                    $params[] = "\n[Đã xóa sản phẩm {$id}] {$productName} đã được gỡ khỏi danh mục, lô này chỉ còn dùng cho lịch sử.";
                }
                $fields[] = "product_id = NULL";
                $params[] = $id;

                $this->conn->prepare("
                    UPDATE product_batch
                    SET " . implode(', ', $fields) . "
                    WHERE product_id = ?
                ")->execute($params);
            }

            $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = :id");
            $deleted = $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return $deleted;
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Lỗi deleteProduct: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Lấy thông tin truy xuất nguồn gốc của sản phẩm
     * Dành cho dự án Agrisupply - quản lý nông sản
     */
    public function getTraceability($product_id)
    {
        try {
            $query = "
                SELECT pt.*
                FROM product_traceability pt
                LEFT JOIN product_batch b ON b.id = pt.batch_id
                WHERE pt.product_id = :product_id
                  AND (
                      pt.batch_id IS NULL
                      OR COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  )
                ORDER BY
                    CASE WHEN pt.batch_id IS NULL THEN 1 ELSE 0 END,
                    pt.batch_id DESC
                LIMIT 1
            ";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':product_id' => (int)$product_id]);
            
            // Trả về đối tượng chứa thông tin nông trại, ngày thu hoạch...
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("Lỗi getTraceability: " . $e->getMessage());
            return null;
        }
    }
    public function getProductsByCategory($categoryId)
    {
        try {

            $query = "
                SELECT p.*, c.name AS category_name
                FROM product p
                LEFT JOIN category c ON p.category_id = c.id
                WHERE p.category_id = :category_id
                ORDER BY p.id DESC
            ";

            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                ':category_id' => $categoryId
            ]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (PDOException $e) {

            error_log($e->getMessage());

            return [];
        }
    }
}
