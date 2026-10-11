<?php
require_once __DIR__ . '/../helpers/OrderWorkflow.php';

class SupplyChainModel
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function ensureSchema(): void
    {
        $this->createCoreTables();
        $this->ensureSupplierColumns();
        $this->ensureWarehouseColumns();
        $this->ensureBatchColumns();
        $this->ensureSupplierOrderColumns();
        $this->ensureInventoryLogColumns();
        $this->ensureOrderColumns();
        $this->ensureShipmentTrackingColumns();
        $this->ensureTraceabilityColumns();
        $this->ensureOrderStatusEnum();
        OrderWorkflow::ensureSchema($this->conn);
        $this->repairInventoryConsistency();
        $this->cleanupLegacyStockArtifacts();
        $this->hardenIntegrityConstraints();
    }

    private function createCoreTables(): void
    {
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS supplier (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                contact_person VARCHAR(150) DEFAULT NULL,
                farm_location VARCHAR(255) DEFAULT NULL,
                cultivation_area DECIMAL(10,2) DEFAULT 0,
                products TEXT DEFAULT NULL,
                supply_quantity_kg DECIMAL(12,2) DEFAULT 10,
                status VARCHAR(30) DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS warehouse (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) DEFAULT NULL,
                location TEXT DEFAULT NULL,
                temperature DECIMAL(5,2) DEFAULT NULL,
                humidity DECIMAL(5,2) DEFAULT NULL,
                storage_method VARCHAR(150) DEFAULT NULL,
                status VARCHAR(30) DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS product_batch (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id INT DEFAULT NULL,
                supplier_id INT DEFAULT NULL,
                supplier_order_id INT DEFAULT NULL,
                warehouse_id INT NOT NULL,
                batch_code VARCHAR(100) DEFAULT NULL UNIQUE,
                qr_code VARCHAR(255) DEFAULT NULL UNIQUE,
                planting_date DATE DEFAULT NULL,
                harvest_date DATE DEFAULT NULL,
                cultivation_method TEXT DEFAULT NULL,
                fertilizer TEXT DEFAULT NULL,
                pesticide TEXT DEFAULT NULL,
                area DECIMAL(10,2) DEFAULT 0,
                quantity INT DEFAULT 0,
                remaining_quantity INT DEFAULT 0,
                unit VARCHAR(20) DEFAULT 'kg',
                quality_standard VARCHAR(100) DEFAULT NULL,
                storage_temperature DECIMAL(5,2) DEFAULT NULL,
                storage_humidity DECIMAL(5,2) DEFAULT NULL,
                preservation_method VARCHAR(150) DEFAULT NULL,
                status VARCHAR(30) DEFAULT 'stored',
                import_price DECIMAL(12,2) DEFAULT 0,
                confirmed_quantity DECIMAL(12,2) DEFAULT 0,
                payable_amount DECIMAL(12,2) DEFAULT 0,
                payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS supplier_orders (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                supplier_id INT NOT NULL,
                warehouse_id INT NULL,
                status ENUM(
                    'pending',
                    'supplier_submitted',
                    'warehouse_confirmed',
                    'completed',
                    'paid',
                    'rejected',
                    'cancelled'
                ) DEFAULT 'pending',
                total_amount DECIMAL(12,2) DEFAULT 0,
                paid_amount DECIMAL(12,2) DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_supplier_orders_supplier_id (supplier_id),
                KEY idx_supplier_orders_warehouse_id (warehouse_id),
                KEY idx_supplier_orders_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS supplier_payment_logs (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                supplier_order_id INT NOT NULL,
                supplier_id INT NOT NULL,
                batch_id INT DEFAULT NULL,
                amount DECIMAL(12,2) DEFAULT 0,
                note VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_supplier_payment_order_id (supplier_order_id),
                KEY idx_supplier_payment_supplier_id (supplier_id),
                KEY idx_supplier_payment_batch_id (batch_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS inventory (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                warehouse_id INT NOT NULL,
                batch_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 0,
                UNIQUE KEY product_id (product_id, warehouse_id, batch_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS inventory_log (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id INT DEFAULT NULL,
                warehouse_id INT DEFAULT NULL,
                batch_id INT DEFAULT NULL,
                order_id INT DEFAULT NULL,
                order_detail_id INT DEFAULT NULL,
                product_name_snapshot VARCHAR(255) DEFAULT NULL,
                type ENUM('import','export') DEFAULT NULL,
                quantity INT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS product_traceability (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                product_id INT DEFAULT NULL,
                product_name_snapshot VARCHAR(255) DEFAULT NULL,
                batch_id INT DEFAULT NULL,
                farm_name VARCHAR(255) DEFAULT NULL,
                harvest_date DATE DEFAULT NULL,
                certification VARCHAR(100) DEFAULT NULL,
                origin_location VARCHAR(255) DEFAULT NULL,
                packaging_date DATE DEFAULT NULL,
                storage_info TEXT DEFAULT NULL,
                transport_info TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS storage_monitoring (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                batch_id INT DEFAULT NULL,
                warehouse_id INT DEFAULT NULL,
                temperature DECIMAL(5,2) DEFAULT NULL,
                humidity DECIMAL(5,2) DEFAULT NULL,
                method VARCHAR(150) DEFAULT NULL,
                checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                notes TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS cultivation_logs (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                supplier_id INT DEFAULT NULL,
                product_id INT DEFAULT NULL,
                product_name_snapshot VARCHAR(255) DEFAULT NULL,
                batch_id INT DEFAULT NULL,
                activity_type VARCHAR(40) DEFAULT 'care',
                activity_date DATE DEFAULT NULL,
                quantity_kg DECIMAL(12,2) DEFAULT 0,
                area DECIMAL(10,2) DEFAULT 0,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cultivation_supplier_id (supplier_id),
                KEY idx_cultivation_product_id (product_id),
                KEY idx_cultivation_batch_id (batch_id),
                KEY idx_cultivation_activity_date (activity_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS shipments (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id INT DEFAULT NULL UNIQUE,
                export_country VARCHAR(120) DEFAULT NULL,
                carrier VARCHAR(150) DEFAULT NULL,
                tracking_code VARCHAR(120) DEFAULT NULL,
                transport_method VARCHAR(80) DEFAULT NULL,
                departure_date DATE DEFAULT NULL,
                arrival_date DATE DEFAULT NULL,
                status VARCHAR(40) DEFAULT 'pending',
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS shipment_tracking_events (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                order_id INT DEFAULT NULL,
                status_code VARCHAR(40) DEFAULT NULL,
                title VARCHAR(150) DEFAULT NULL,
                description VARCHAR(255) DEFAULT NULL,
                location VARCHAR(150) DEFAULT NULL,
                sort_order INT DEFAULT 0,
                is_completed TINYINT DEFAULT 0,
                event_time DATETIME DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY order_step (order_id, status_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensureSupplierColumns(): void
    {
        $this->addColumn('supplier', 'phone', "VARCHAR(50) DEFAULT NULL");
        $this->addColumn('supplier', 'address', "TEXT DEFAULT NULL");
        $this->addColumn('supplier', 'contact_person', "VARCHAR(150) DEFAULT NULL");
        $this->addColumn('supplier', 'farm_location', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('supplier', 'cultivation_area', "DECIMAL(10,2) DEFAULT 0");
        $this->addColumn('supplier', 'products', "TEXT DEFAULT NULL");
        $this->addColumn('supplier', 'supply_quantity_kg', "DECIMAL(12,2) DEFAULT 10");
        $this->addColumn('supplier', 'status', "VARCHAR(30) DEFAULT 'active'");
        $this->addColumn('supplier', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    private function ensureWarehouseColumns(): void
    {
        $this->addColumn('warehouse', 'temperature', "DECIMAL(5,2) DEFAULT NULL");
        $this->addColumn('warehouse', 'humidity', "DECIMAL(5,2) DEFAULT NULL");
        $this->addColumn('warehouse', 'storage_method', "VARCHAR(150) DEFAULT NULL");
        $this->addColumn('warehouse', 'status', "VARCHAR(30) DEFAULT 'active'");
        $this->addColumn('warehouse', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    private function ensureBatchColumns(): void
    {
        $columns = [
            'supplier_id' => "INT DEFAULT NULL",
            'supplier_order_id' => "INT DEFAULT NULL",
            'warehouse_id' => "INT NOT NULL",
            'planting_date' => "DATE DEFAULT NULL",
            'harvest_date' => "DATE DEFAULT NULL",
            'cultivation_method' => "TEXT DEFAULT NULL",
            'fertilizer' => "TEXT DEFAULT NULL",
            'pesticide' => "TEXT DEFAULT NULL",
            'area' => "DECIMAL(10,2) DEFAULT 0",
            'quantity' => "INT DEFAULT 0",
            'remaining_quantity' => "INT DEFAULT 0",
            'unit' => "VARCHAR(20) DEFAULT 'kg'",
            'quality_standard' => "VARCHAR(100) DEFAULT NULL",
            'storage_temperature' => "DECIMAL(5,2) DEFAULT NULL",
            'storage_humidity' => "DECIMAL(5,2) DEFAULT NULL",
            'preservation_method' => "VARCHAR(150) DEFAULT NULL",
            'status' => "VARCHAR(30) DEFAULT 'stored'",
            'import_price' => "DECIMAL(12,2) DEFAULT 0",
            'confirmed_quantity' => "DECIMAL(12,2) DEFAULT 0",
            'payable_amount' => "DECIMAL(12,2) DEFAULT 0",
            'payment_status' => "ENUM('unpaid','partial','paid') DEFAULT 'unpaid'",
            'product_name_snapshot' => "VARCHAR(255) DEFAULT NULL",
            'notes' => "TEXT DEFAULT NULL",
            'created_at' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        ];

        foreach ($columns as $column => $definition) {
            $this->addColumn('product_batch', $column, $definition);
        }
    }

    private function ensureSupplierOrderColumns(): void
    {
        if (!$this->tableExists('supplier_orders')) {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS supplier_orders (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    supplier_id INT NOT NULL,
                    warehouse_id INT NULL,
                    status ENUM(
                        'pending',
                        'supplier_submitted',
                        'warehouse_confirmed',
                        'completed',
                        'paid',
                        'rejected',
                        'cancelled'
                    ) DEFAULT 'pending',
                    requested_product_name VARCHAR(255) DEFAULT NULL,
                    requested_quantity DECIMAL(12,2) DEFAULT 0,
                    suggested_import_price DECIMAL(12,2) DEFAULT 0,
                    total_amount DECIMAL(12,2) DEFAULT 0,
                    paid_amount DECIMAL(12,2) DEFAULT 0,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->tableExists('supplier_payment_logs')) {
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS supplier_payment_logs (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    supplier_order_id INT NOT NULL,
                    supplier_id INT NOT NULL,
                    batch_id INT DEFAULT NULL,
                    amount DECIMAL(12,2) DEFAULT 0,
                    note VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $this->addColumn('supplier_orders', 'supplier_id', "INT NOT NULL");
        $this->addColumn('supplier_orders', 'warehouse_id', "INT NULL");
        $this->addColumn('supplier_orders', 'status', "ENUM('pending','supplier_submitted','warehouse_confirmed','completed','paid','rejected','cancelled') DEFAULT 'pending'");
        $this->addColumn('supplier_orders', 'requested_product_name', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('supplier_orders', 'requested_quantity', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('supplier_orders', 'suggested_import_price', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('supplier_orders', 'total_amount', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('supplier_orders', 'paid_amount', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('supplier_orders', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");

        $this->addColumn('supplier_payment_logs', 'supplier_order_id', "INT NOT NULL");
        $this->addColumn('supplier_payment_logs', 'supplier_id', "INT NOT NULL");
        $this->addColumn('supplier_payment_logs', 'batch_id', "INT DEFAULT NULL");
        $this->addColumn('supplier_payment_logs', 'amount', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('supplier_payment_logs', 'note', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('supplier_payment_logs', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    private function hardenIntegrityConstraints(): void
    {
        $defaultWarehouseId = $this->ensureDefaultWarehouse();

        // Backfill lot foreign keys before tightening constraints.
        $this->conn->prepare("
            UPDATE product_batch
            SET warehouse_id = ?
            WHERE warehouse_id IS NULL OR warehouse_id = 0
        ")->execute([$defaultWarehouseId]);

        $nullProductBatchIds = $this->conn->query("
            SELECT id
            FROM product_batch
            WHERE (product_id IS NULL OR product_id = 0)
              AND COALESCE(status, 'stored') NOT IN ('archived', 'deleted_product', 'product_deleted')
              AND COALESCE(product_name_snapshot, '') = ''
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($nullProductBatchIds as $batchId) {
            $this->ensureBatchProductLinked((int)$batchId);
        }

        // Keep inventory aligned with lot linkage.
        $this->conn->exec("
            UPDATE inventory i
            JOIN product_batch b ON b.id = i.batch_id
            SET i.product_id = b.product_id,
                i.warehouse_id = COALESCE(i.warehouse_id, b.warehouse_id, {$defaultWarehouseId})
            WHERE b.product_id IS NOT NULL
              AND (i.product_id <> b.product_id
                   OR i.product_id IS NULL
                   OR i.warehouse_id IS NULL
                   OR i.warehouse_id = 0)
        ");
        $this->conn->exec("UPDATE inventory SET quantity = 0 WHERE quantity IS NULL OR quantity < 0");
        $this->conn->exec("UPDATE product_batch SET quantity = 0 WHERE quantity IS NULL OR quantity < 0");
        $this->conn->exec("UPDATE product_batch SET remaining_quantity = 0 WHERE remaining_quantity IS NULL OR remaining_quantity < 0");

        // Keep batch rows available as history even when the catalog product is deleted.
        $this->dropForeignKeyIfExists('product_batch', 'fk_product_batch_warehouse', 'SET NULL');

        // Warehouse/inventory remain strict, product link may be null for archived history rows.
        $this->conn->exec("ALTER TABLE product_batch MODIFY product_id INT DEFAULT NULL");
        $this->conn->exec("ALTER TABLE product_batch MODIFY warehouse_id INT NOT NULL");
        $this->conn->exec("ALTER TABLE inventory MODIFY product_id INT NOT NULL");
        $this->conn->exec("ALTER TABLE inventory MODIFY warehouse_id INT NOT NULL");
        $this->conn->exec("ALTER TABLE inventory MODIFY batch_id INT NOT NULL");
        $this->conn->exec("ALTER TABLE inventory MODIFY quantity INT NOT NULL DEFAULT 0");

        $this->rebindBatchForeignKeys();
        $this->ensureCheckConstraint('product_batch', 'chk_product_batch_quantity_nonnegative', 'quantity >= 0');
        $this->ensureCheckConstraint('product_batch', 'chk_product_batch_remaining_nonnegative', 'remaining_quantity >= 0');
        $this->ensureCheckConstraint('inventory', 'chk_inventory_quantity_nonnegative', 'quantity >= 0');
        $this->ensureInventorySyncTriggers();
    }

    private function rebindBatchForeignKeys(): void
    {
        $this->ensureSetNullForeignKey('product_batch', 'fk_product_batch_product', 'product_id', 'product', 'id');
        $this->ensureRestrictForeignKey('product_batch', 'fk_product_batch_warehouse', 'warehouse_id', 'warehouse', 'id');
    }

    private function ensureCheckConstraint(string $table, string $constraint, string $expression): void
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'CHECK'
        ");
        $stmt->execute([$table, $constraint]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $this->conn->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` CHECK ({$expression})");
    }

    private function ensureRestrictForeignKey(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): void
    {
        $deleteRule = $this->foreignKeyDeleteRule($table, $constraint);
        if ($deleteRule === 'RESTRICT' || $deleteRule === 'NO ACTION') {
            return;
        }

        if ($deleteRule !== null) {
            $this->dropForeignKeyIfExists($table, $constraint);
        }

        $this->conn->exec("
            ALTER TABLE `{$table}`
            ADD CONSTRAINT `{$constraint}`
            FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}`(`{$referencedColumn}`)
            ON DELETE RESTRICT
        ");
    }

    private function ensureSetNullForeignKey(string $table, string $constraint, string $column, string $referencedTable, string $referencedColumn): void
    {
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
        $deleteRule = $stmt->fetchColumn();
        return $deleteRule !== false ? strtoupper((string)$deleteRule) : null;
    }

    private function dropForeignKeyIfExists(string $table, string $constraint, ?string $onlyDeleteRule = null): bool
    {
        $deleteRule = $this->foreignKeyDeleteRule($table, $constraint);
        if ($deleteRule === null) {
            return false;
        }
        if ($onlyDeleteRule !== null && $deleteRule !== strtoupper($onlyDeleteRule)) {
            return false;
        }

        try {
            $this->conn->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            return true;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "Can't DROP") !== false) {
                return false;
            }
            throw $e;
        }
    }

    private function ensureInventorySyncTriggers(): void
    {
        $this->conn->exec("DROP TRIGGER IF EXISTS bi_inventory_sync_from_batch");
        $this->conn->exec("DROP TRIGGER IF EXISTS bu_inventory_sync_from_batch");

        $this->conn->exec("
            CREATE TRIGGER bi_inventory_sync_from_batch
            BEFORE INSERT ON inventory
            FOR EACH ROW
            SET NEW.product_id = (SELECT b.product_id FROM product_batch b WHERE b.id = NEW.batch_id),
                NEW.warehouse_id = COALESCE(NEW.warehouse_id, (SELECT b.warehouse_id FROM product_batch b WHERE b.id = NEW.batch_id))
        ");
        $this->conn->exec("
            CREATE TRIGGER bu_inventory_sync_from_batch
            BEFORE UPDATE ON inventory
            FOR EACH ROW
            SET NEW.product_id = (SELECT b.product_id FROM product_batch b WHERE b.id = NEW.batch_id),
                NEW.warehouse_id = COALESCE(NEW.warehouse_id, (SELECT b.warehouse_id FROM product_batch b WHERE b.id = NEW.batch_id))
        ");
    }

    private function ensureInventoryLogColumns(): void
    {
        $this->addColumn('inventory_log', 'order_id', "INT DEFAULT NULL");
        $this->addColumn('inventory_log', 'order_detail_id', "INT DEFAULT NULL");
        $this->addColumn('inventory_log', 'note', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('inventory_log', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
    }

    private function ensureOrderColumns(): void
    {
        $this->addColumn('orders', 'export_country', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'export_status', "VARCHAR(40) DEFAULT 'pending'");
        $this->addColumn('orders', 'shipping_status', "VARCHAR(40) DEFAULT 'pending'");
        $this->addColumn('orders', 'tracking_code', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'transport_method', "VARCHAR(80) DEFAULT NULL");
        $this->addColumn('orders', 'port_of_loading', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'port_of_destination', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'export_note', "TEXT DEFAULT NULL");
        $this->addColumn('orders', 'delivery_scope', "VARCHAR(30) DEFAULT 'domestic'");
        $this->addColumn('orders', 'payment_method', "VARCHAR(80) DEFAULT NULL");
        $this->addColumn('orders', 'payment_brand', "VARCHAR(40) DEFAULT NULL");
        $this->addColumn('orders', 'payment_reference', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'payment_status', "VARCHAR(40) DEFAULT 'pending'");
        $this->addColumn('orders', 'stock_status', "VARCHAR(40) DEFAULT 'unchecked'");
        $this->addColumn('orders', 'subtotal_amount', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('orders', 'discount_code', "VARCHAR(80) DEFAULT NULL");
        $this->addColumn('orders', 'discount_percent', "DECIMAL(5,2) DEFAULT 0");
        $this->addColumn('orders', 'discount_amount', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('orders', 'refund_status', "VARCHAR(40) DEFAULT 'not_required'");
        $this->addColumn('orders', 'refund_amount', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('orders', 'refund_note', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('orders', 'cancelled_at', "DATETIME DEFAULT NULL");
        $this->addColumn('orders', 'refunded_at', "DATETIME DEFAULT NULL");
        $this->addColumn('orders', 'recipient_country', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'recipient_region', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'recipient_city', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'recipient_district', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'recipient_ward', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'postal_code', "VARCHAR(30) DEFAULT NULL");
        $this->addColumn('orders', 'address_detail', "TEXT DEFAULT NULL");
        $this->addColumn('orders', 'shipping_carrier', "VARCHAR(150) DEFAULT NULL");
        $this->addColumn('orders', 'shipping_service', "VARCHAR(120) DEFAULT NULL");
        $this->addColumn('orders', 'shipping_incoterm', "VARCHAR(20) DEFAULT NULL");
        $this->addColumn('orders', 'shipping_fee', "DECIMAL(12,2) DEFAULT 0");
        $this->addColumn('orders', 'currency', "VARCHAR(10) DEFAULT 'VND'");
        if ($this->tableExists('order_details')) {
            $this->addColumn('order_details', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        }
    }

    private function ensureShipmentTrackingColumns(): void
    {
        $this->addColumn('shipment_tracking_events', 'order_id', "INT DEFAULT NULL");
        $this->addColumn('shipment_tracking_events', 'status_code', "VARCHAR(40) DEFAULT NULL");
        $this->addColumn('shipment_tracking_events', 'title', "VARCHAR(150) DEFAULT NULL");
        $this->addColumn('shipment_tracking_events', 'description', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('shipment_tracking_events', 'location', "VARCHAR(150) DEFAULT NULL");
        $this->addColumn('shipment_tracking_events', 'sort_order', "INT DEFAULT 0");
        $this->addColumn('shipment_tracking_events', 'is_completed', "TINYINT DEFAULT 0");
        $this->addColumn('shipment_tracking_events', 'event_time', "DATETIME DEFAULT NULL");
        $this->addColumn('shipment_tracking_events', 'created_at', "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
    }

    private function ensureTraceabilityColumns(): void
    {
        $this->addColumn('product_traceability', 'batch_id', "INT DEFAULT NULL");
        $this->addColumn('product_traceability', 'storage_info', "TEXT DEFAULT NULL");
        $this->addColumn('product_traceability', 'transport_info', "TEXT DEFAULT NULL");
        $this->addColumn('product_traceability', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        $this->ensureSetNullForeignKey('product_traceability', 'fk_product_trace', 'product_id', 'product', 'id');
        if ($this->tableExists('cultivation_logs')) {
            $this->addColumn('cultivation_logs', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        }
        $this->backfillTraceabilityFromBatches();
    }

    private function ensureOrderStatusEnum(): void
    {
        try {
            $this->conn->exec("
                ALTER TABLE orders
                MODIFY status ENUM('pending','confirmed','preparing','packed','shipped','completed','cancelled')
                DEFAULT 'pending'
            ");
        } catch (Throwable $e) {
            error_log('Cannot widen order status enum: ' . $e->getMessage());
        }
    }

    private function addColumn(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        try {
            $this->conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        } catch (Throwable $e) {
            error_log("Cannot add column {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function seedFromProducts(): void
    {
        // Disabled: product.stock is only a cache and must not seed real inventory.
    }

    private function ensureDefaultWarehouse(): int
    {
        $stmt = $this->conn->prepare("SELECT id FROM warehouse WHERE name = ? LIMIT 1");
        $stmt->execute(['Tổng kho Aethera']);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO warehouse (name, location, temperature, humidity, storage_method, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute(['Tổng kho Aethera', 'TP.HCM', 8, 72, 'Kho lạnh tiêu chuẩn']);
        return (int)$this->conn->lastInsertId();
    }

    private function findOrCreateSupplier(string $name, string $origin = '', string $productName = ''): ?int
    {
        if ($name === '') {
            $name = 'Nhà cung cấp chưa phân loại';
        }

        $stmt = $this->conn->prepare("SELECT id FROM supplier WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO supplier (name, phone, address, contact_person, farm_location, cultivation_area, products, status)
            VALUES (?, '', ?, '', ?, 0, ?, 'active')
        ");
        $stmt->execute([$name, $origin, $origin, $productName]);
        return (int)$this->conn->lastInsertId();
    }

    private function ensureLegacyBatch(object $product, ?int $supplierId, int $warehouseId): void
    {
        $stmt = $this->conn->prepare("SELECT id FROM product_batch WHERE product_id = ? LIMIT 1");
        $stmt->execute([(int)$product->id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        return;

        $harvestDate = $product->harvest_date ?: date('Y-m-d');
        $batchCode = 'LOT-P' . (int)$product->id . '-' . date('Ymd', strtotime($harvestDate));
        $qr = '/webbanhang/product/trace/' . (int)$product->id . '?batch=' . rawurlencode($batchCode);

        $stmt = $this->conn->prepare("
            INSERT INTO product_batch (
                product_id, supplier_id, warehouse_id, batch_code, qr_code, harvest_date,
                cultivation_method, quantity, remaining_quantity, unit, quality_standard,
                storage_temperature, storage_humidity, preservation_method, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'stored', ?)
        ");
        $stmt->execute([
            (int)$product->id,
            $supplierId,
            $warehouseId,
            $batchCode,
            $qr,
            $harvestDate,
            'Kế thừa từ dữ liệu sản phẩm hiện có',
            $stock,
            $stock,
            'kg',
            $product->standard ?? null,
            8,
            72,
            'Kho lạnh tiêu chuẩn',
            'Lô tạo để tương thích dữ liệu cũ'
        ]);

        $batchId = (int)$this->conn->lastInsertId();
        $this->upsertInventory((int)$product->id, $warehouseId, $batchId, $stock);
    }

    private function repairInventoryConsistency(): void
    {
        try {
            $this->conn->exec("
                DELETE i
                FROM inventory i
                LEFT JOIN product p ON p.id = i.product_id
                LEFT JOIN product_batch b ON b.id = i.batch_id
                LEFT JOIN warehouse w ON w.id = i.warehouse_id
                WHERE p.id IS NULL OR b.id IS NULL OR w.id IS NULL OR i.quantity <= 0
            ");

            $batches = $this->conn->query("
                SELECT b.id, b.product_id, b.warehouse_id,
                       COALESCE(SUM(i.quantity), 0) AS inventory_quantity
                FROM product_batch b
                LEFT JOIN inventory i ON i.batch_id = b.id
                GROUP BY b.id, b.product_id, b.warehouse_id
            ")->fetchAll(PDO::FETCH_OBJ);

            foreach ($batches as $batch) {
                $batchId = (int)$batch->id;
                $productId = (int)$batch->product_id;
                $inventoryQuantity = (int)$batch->inventory_quantity;

                if ($productId <= 0) {
                    continue;
                }

                $this->conn->prepare("UPDATE product_batch SET remaining_quantity = ? WHERE id = ?")
                    ->execute([$inventoryQuantity, $batchId]);
            }

            $products = $this->conn->query("SELECT id FROM product")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($products as $productId) {
                $this->syncProductStock((int)$productId);
            }
        } catch (Throwable $e) {
            error_log('Cannot repair inventory consistency: ' . $e->getMessage());
        }
    }

    private function cleanupLegacyStockArtifacts(): void
    {
        try {
            $this->conn->exec("
                UPDATE warehouse
                SET status = 'inactive'
                WHERE name = 'Legacy data warehouse'
            ");

            $this->conn->exec("
                DELETE i
                FROM inventory i
                JOIN product_batch b ON b.id = i.batch_id
                WHERE COALESCE(b.status, '') = 'legacy'
                   OR COALESCE(b.batch_code, '') LIKE 'LEGACY-%'
            ");

            $this->conn->exec("
                UPDATE product_batch
                SET remaining_quantity = 0
                WHERE COALESCE(status, '') = 'legacy'
                   OR COALESCE(batch_code, '') LIKE 'LEGACY-%'
            ");

            $products = $this->conn->query("SELECT id FROM product")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($products as $productId) {
                $this->syncProductStock((int)$productId);
            }
        } catch (Throwable $e) {
            error_log('Cannot cleanup legacy stock artifacts: ' . $e->getMessage());
        }
    }

    public function getSuppliers(): array
    {
        return $this->conn->query("SELECT * FROM supplier ORDER BY id DESC")->fetchAll(PDO::FETCH_OBJ);
    }

    public function saveSupplier(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $status = trim($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive', 'pending'], true)) {
            $status = 'active';
        }

        $params = [
            trim($data['name'] ?? ''),
            trim($data['phone'] ?? ''),
            trim($data['address'] ?? ''),
            trim($data['contact_person'] ?? ''),
            trim($data['farm_location'] ?? ''),
            (float)($data['cultivation_area'] ?? 0),
            trim($data['products'] ?? ''),
            max(0, (float)($data['supply_quantity_kg'] ?? 10)),
            $status
        ];

        if ($id > 0) {
            $params[] = $id;
            $stmt = $this->conn->prepare("
                UPDATE supplier
                SET name = ?, phone = ?, address = ?, contact_person = ?, farm_location = ?,
                    cultivation_area = ?, products = ?, supply_quantity_kg = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute($params);
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO supplier (name, phone, address, contact_person, farm_location, cultivation_area, products, supply_quantity_kg, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($params);
    }

    public function deleteSupplier(int $id): void
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM product_batch WHERE supplier_id = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $this->conn->prepare("UPDATE supplier SET status = 'inactive' WHERE id = ?")->execute([$id]);
            return;
        }
        $this->conn->prepare("DELETE FROM supplier WHERE id = ?")->execute([$id]);
    }

    public function approveSupplier(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $this->conn->prepare("UPDATE supplier SET status = 'active' WHERE id = ?")->execute([$id]);
        if ($this->tableExists('account') && $this->columnExists('account', 'supplier_id')) {
            if ($this->columnExists('account', 'verified_at') && $this->columnExists('account', 'verification_code')) {
                $this->conn->prepare("
                    UPDATE account
                    SET status = CASE
                        WHEN verified_at IS NOT NULL OR verification_code IS NULL THEN 1
                        ELSE status
                    END
                    WHERE supplier_id = ? AND role = 'supplier'
                ")->execute([$id]);
            } else {
                $this->conn->prepare("
                    UPDATE account
                    SET status = 1
                    WHERE supplier_id = ? AND role = 'supplier'
                ")->execute([$id]);
            }
        }

        return true;
    }

    public function getWarehouses(): array
    {
        return $this->conn->query("
            SELECT *
            FROM warehouse
            WHERE COALESCE(name, '') <> 'Legacy data warehouse'
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function saveWarehouse(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        $params = [
            trim($data['name'] ?? ''),
            trim($data['location'] ?? ''),
            $data['temperature'] !== '' ? (float)$data['temperature'] : null,
            $data['humidity'] !== '' ? (float)$data['humidity'] : null,
            trim($data['storage_method'] ?? ''),
            trim($data['status'] ?? 'active')
        ];

        if ($id > 0) {
            $params[] = $id;
            $stmt = $this->conn->prepare("
                UPDATE warehouse
                SET name = ?, location = ?, temperature = ?, humidity = ?, storage_method = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute($params);
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO warehouse (name, location, temperature, humidity, storage_method, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute($params);
    }

    public function getBatches(): array
    {
        $stmt = $this->conn->query("
            SELECT b.*, COALESCE(p.name, b.product_name_snapshot, so.requested_product_name, 'Sản phẩm đã xóa') AS product_name, p.image,
                   s.name AS supplier_name, s.phone AS supplier_phone, s.contact_person AS supplier_contact_person,
                   w.name AS warehouse_name,
                   so.status AS supplier_order_status, so.total_amount AS supplier_order_total, so.paid_amount AS supplier_order_paid,
                   COALESCE(inv.quantity, 0) AS inventory_quantity,
                   COALESCE(inv.quantity, 0) AS calculated_remaining_quantity
            FROM product_batch b
            LEFT JOIN product p ON p.id = b.product_id
            LEFT JOIN supplier s ON s.id = b.supplier_id
            LEFT JOIN warehouse w ON w.id = b.warehouse_id
            LEFT JOIN supplier_orders so ON so.id = b.supplier_order_id
            LEFT JOIN (
                SELECT batch_id, COALESCE(SUM(quantity), 0) AS quantity
                FROM inventory
                WHERE quantity > 0
                GROUP BY batch_id
            ) inv ON inv.batch_id = b.id
            WHERE COALESCE(b.status, 'stored') <> 'legacy'
            ORDER BY b.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getBatchesBySupplier(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT b.*, COALESCE(p.name, b.product_name_snapshot, so.requested_product_name, 'Sản phẩm đã xóa') AS product_name, p.image, w.name AS warehouse_name, w.location AS warehouse_location,
                   so.status AS supplier_order_status, so.total_amount AS supplier_order_total, so.paid_amount AS supplier_order_paid,
                   COALESCE(inv.quantity, 0) AS inventory_quantity,
                   COALESCE(inv.quantity, 0) AS calculated_remaining_quantity
            FROM product_batch b
            LEFT JOIN product p ON p.id = b.product_id
            LEFT JOIN warehouse w ON w.id = b.warehouse_id
            LEFT JOIN supplier_orders so ON so.id = b.supplier_order_id
            LEFT JOIN (
                SELECT batch_id, COALESCE(SUM(quantity), 0) AS quantity
                FROM inventory
                WHERE quantity > 0
                GROUP BY batch_id
            ) inv ON inv.batch_id = b.id
            WHERE b.supplier_id = ?
              AND COALESCE(b.status, 'stored') <> 'legacy'
            ORDER BY b.id DESC
            LIMIT 20
        ");
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getSupplierOrders(?int $supplierId = null, int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $where = '';
        $params = [];
        if ($supplierId !== null && $supplierId > 0) {
            $where = 'WHERE so.supplier_id = ?';
            $params[] = $supplierId;
        }

        $stmt = $this->conn->prepare("
            SELECT so.*, s.name AS supplier_name, s.phone AS supplier_phone,
                   s.contact_person AS supplier_contact_person, s.address AS supplier_address,
                   s.farm_location AS supplier_farm_location, s.products AS supplier_products,
                   acc.fullname AS supplier_account_name, acc.username AS supplier_account_username,
                   acc.email AS supplier_account_email, acc.phone AS supplier_account_phone,
                   w.name AS warehouse_name, w.location AS warehouse_location,
                   b.id AS batch_id, b.batch_code, b.product_id,
                   COALESCE(b.quantity, so.requested_quantity, 0) AS quantity,
                   b.confirmed_quantity,
                   COALESCE(b.import_price, so.suggested_import_price, 0) AS import_price,
                   b.payable_amount, b.payment_status, b.status AS batch_status,
                   b.harvest_date, b.planting_date, b.unit, b.quality_standard, b.notes AS batch_notes,
                   COALESCE(inv.quantity, 0) AS inventory_quantity,
                    COALESCE(p.name, b.product_name_snapshot, so.requested_product_name) AS product_name
            FROM supplier_orders so
            LEFT JOIN supplier s ON s.id = so.supplier_id
            LEFT JOIN (
                SELECT supplier_id, MIN(id) AS account_id
                FROM account
                WHERE supplier_id IS NOT NULL
                GROUP BY supplier_id
            ) supplier_account ON supplier_account.supplier_id = s.id
            LEFT JOIN account acc ON acc.id = supplier_account.account_id
            LEFT JOIN warehouse w ON w.id = so.warehouse_id
            LEFT JOIN product_batch b ON b.supplier_order_id = so.id
            LEFT JOIN product p ON p.id = b.product_id
            LEFT JOIN (
                SELECT batch_id, COALESCE(SUM(quantity), 0) AS quantity
                FROM inventory
                GROUP BY batch_id
            ) inv ON inv.batch_id = b.id
            {$where}
            ORDER BY so.created_at DESC, so.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getSupplierOrdersBySupplier(int $supplierId): array
    {
        return $this->getSupplierOrders($supplierId, 100);
    }

    public function getSupplierPaymentLogs(int $limit = 60): array
    {
        $limit = max(1, min(200, $limit));
        return $this->conn->query("
            SELECT spl.*, so.status AS supplier_order_status, so.total_amount, so.paid_amount,
                   s.name AS supplier_name, b.batch_code, COALESCE(p.name, b.product_name_snapshot, so.requested_product_name, 'Sản phẩm đã xóa') AS product_name
            FROM supplier_payment_logs spl
            LEFT JOIN supplier_orders so ON so.id = spl.supplier_order_id
            LEFT JOIN supplier s ON s.id = spl.supplier_id
            LEFT JOIN product_batch b ON b.id = spl.batch_id
            LEFT JOIN product p ON p.id = b.product_id
            ORDER BY spl.created_at DESC, spl.id DESC
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function createSupplierOrder(array $data): array
    {
        $supplierId = (int)($data['supplier_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $requestedProductName = trim((string)($data['requested_product_name'] ?? ''));
        $requestedQuantity = max(0, (float)($data['requested_quantity'] ?? 0));
        $suggestedImportPrice = max(0, (float)($data['suggested_import_price'] ?? 0));
        if ($supplierId <= 0) {
            return ['success' => false, 'message' => 'Vui lòng chọn nhà cung cấp để tạo đơn nhập lô.'];
        }
        if ($requestedProductName === '') {
            return ['success' => false, 'message' => 'Vui lòng chọn tên sản phẩm nhà cung cấp sẽ giao.'];
        }
        if ($requestedQuantity <= 0) {
            return ['success' => false, 'message' => 'Vui lòng nhập số kg cần nhập lớn hơn 0.'];
        }
        if ($suggestedImportPrice <= 0) {
            return ['success' => false, 'message' => 'Vui lòng nhập giá đề xuất/kg lớn hơn 0.'];
        }

        $stmt = $this->conn->prepare("SELECT id, products FROM supplier WHERE id = ? AND COALESCE(status, 'active') = 'active' LIMIT 1");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$supplier) {
            return ['success' => false, 'message' => 'Nhà cung cấp không hợp lệ hoặc đang tạm ngưng.'];
        }
        $registeredProducts = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string)($supplier->products ?? '')))));
        $registeredLookup = array_map(static function ($value) {
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        }, $registeredProducts);
        $requestedLookup = function_exists('mb_strtolower') ? mb_strtolower($requestedProductName, 'UTF-8') : strtolower($requestedProductName);
        if (empty($registeredProducts) || !in_array($requestedLookup, $registeredLookup, true)) {
            return ['success' => false, 'message' => 'Sản phẩm cần nhập phải nằm trong danh sách nông sản cung ứng của nhà cung cấp.'];
        }

        $stmt = $this->conn->prepare("
            SELECT id
            FROM supplier_orders
            WHERE supplier_id = ?
              AND requested_product_name = ?
              AND status IN ('pending', 'supplier_submitted')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$supplierId, $requestedProductName]);
        $openOrderId = (int)$stmt->fetchColumn();
        if ($openOrderId > 0) {
            return [
                'success' => false,
                'message' => 'Đã có đơn nhập #' . $openOrderId . ' đang mở cho sản phẩm này. Hãy xử lý đơn đó trước khi tạo đơn mới.',
            ];
        }

        $this->conn->prepare("
            INSERT INTO supplier_orders (
                supplier_id, warehouse_id, status, requested_product_name, requested_quantity, suggested_import_price, total_amount, paid_amount
            )
            VALUES (?, ?, 'pending', ?, ?, ?, 0, 0)
        ")->execute([
            $supplierId,
            $warehouseId > 0 ? $warehouseId : null,
            $requestedProductName,
            $requestedQuantity,
            $suggestedImportPrice,
        ]);

        return [
            'success' => true,
            'supplier_order_id' => (int)$this->conn->lastInsertId(),
            'message' => 'Đã tạo đơn nhập lô ' . $requestedProductName . ': ' . number_format($requestedQuantity, 0, ',', '.') . ' kg, giá đề xuất ' . number_format($suggestedImportPrice, 0, ',', '.') . 'đ/kg.',
        ];
    }

    public function submitSupplierOrderBatch(int $supplierId, array $data): array
    {
        $supplierOrderId = (int)($data['supplier_order_id'] ?? 0);
        if ($supplierId <= 0 || $supplierOrderId <= 0) {
            return ['success' => false, 'message' => 'Đơn nhập lô không hợp lệ.'];
        }

        $quantity = max(0, (int)($data['quantity'] ?? 0));
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Số lượng lô phải lớn hơn 0 kg.'];
        }

        $startedTransaction = false;
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM supplier_orders
                WHERE id = ? AND supplier_id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$supplierOrderId, $supplierId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$order) {
                throw new RuntimeException('Không tìm thấy đơn nhập lô của nhà cung cấp.');
            }
            if ((string)($order->status ?? 'pending') !== 'pending') {
                throw new RuntimeException('Đơn nhập lô này đã được gửi phiếu hoặc đã kết thúc.');
            }
            $requestedProductName = trim((string)($order->requested_product_name ?? ''));
            $submittedProductName = trim((string)($data['product_name'] ?? ''));
            if ($requestedProductName !== '') {
                $requestedLookup = function_exists('mb_strtolower') ? mb_strtolower($requestedProductName, 'UTF-8') : strtolower($requestedProductName);
                $submittedLookup = function_exists('mb_strtolower') ? mb_strtolower($submittedProductName, 'UTF-8') : strtolower($submittedProductName);
                if ($submittedLookup !== $requestedLookup) {
                    throw new RuntimeException('Phiếu lô chỉ được gửi cho sản phẩm admin đã yêu cầu: ' . $requestedProductName . '.');
                }
            }

            $stmt = $this->conn->prepare("SELECT id FROM product_batch WHERE supplier_order_id = ? LIMIT 1");
            $stmt->execute([$supplierOrderId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('Đơn nhập này đã có phiếu lô.');
            }

            $warehouseId = (int)($order->warehouse_id ?? 0);
            if ($warehouseId <= 0) {
                $warehouseId = $this->ensureDefaultWarehouse();
            }
            $harvestDate = !empty($data['harvest_date']) ? $data['harvest_date'] : date('Y-m-d');
            $productId = $this->resolveBatchProduct($data, $supplierId, $warehouseId, $harvestDate);
            if ($productId <= 0) {
                throw new RuntimeException('Vui lòng nhập tên sản phẩm hợp lệ.');
            }

            $batchCode = trim($data['batch_code'] ?? '');
            if ($batchCode === '') {
                $batchCode = 'LOT-SO' . $supplierOrderId . '-' . date('Ymd') . '-' . random_int(10, 99);
            }
            $qr = '/webbanhang/product/trace/' . $productId . '?batch=' . rawurlencode($batchCode);
            $importPrice = max(0, (float)($data['import_price'] ?? 0));

            $stmt = $this->conn->prepare("
                INSERT INTO product_batch (
                    product_id, supplier_id, supplier_order_id, warehouse_id, batch_code, qr_code,
                    planting_date, harvest_date, cultivation_method, fertilizer, pesticide, area,
                    quantity, remaining_quantity, unit, quality_standard, storage_temperature,
                    storage_humidity, preservation_method, status, import_price, confirmed_quantity,
                    payable_amount, payment_status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'unpaid', ?)
            ");
            $stmt->execute([
                $productId,
                $supplierId,
                $supplierOrderId,
                $warehouseId,
                $batchCode,
                $qr,
                !empty($data['planting_date']) ? $data['planting_date'] : null,
                $harvestDate,
                trim($data['cultivation_method'] ?? ''),
                trim($data['fertilizer'] ?? ''),
                trim($data['pesticide'] ?? ''),
                (float)($data['area'] ?? 0),
                $quantity,
                'kg',
                trim($data['quality_standard'] ?? ''),
                ($data['storage_temperature'] ?? '') !== '' ? (float)$data['storage_temperature'] : null,
                ($data['storage_humidity'] ?? '') !== '' ? (float)$data['storage_humidity'] : null,
                trim($data['preservation_method'] ?? ''),
                'supplier_submitted',
                $importPrice,
                trim($data['notes'] ?? '')
            ]);

            $batchId = (int)$this->conn->lastInsertId();

            $this->conn->prepare("
                UPDATE supplier_orders
                SET status = 'supplier_submitted',
                    warehouse_id = COALESCE(warehouse_id, ?)
                WHERE id = ?
            ")->execute([$warehouseId, $supplierOrderId]);

            $this->updateProductSupplyInfo($productId, $supplierId, $warehouseId, $harvestDate, $data);
            $this->upsertTraceabilityForBatch($batchId);

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return [
                'success' => true,
                'batch_id' => $batchId,
                'message' => 'Đã gửi phiếu lô. Kho/Admin sẽ xác nhận số lượng trước khi hàng vào tồn kho.',
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Cannot submit supplier order batch: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function confirmSupplierOrder(array $data): array
    {
        $supplierOrderId = (int)($data['supplier_order_id'] ?? 0);
        if ($supplierOrderId <= 0) {
            return ['success' => false, 'message' => 'Đơn nhập lô không hợp lệ.'];
        }

        $confirmedQuantity = max(0, (int)($data['confirmed_quantity'] ?? 0));
        $importPrice = max(0, (float)($data['import_price'] ?? 0));
        if ($confirmedQuantity <= 0) {
            return ['success' => false, 'message' => 'Số lượng kho xác nhận phải lớn hơn 0 kg.'];
        }
        if ($importPrice <= 0) {
            return ['success' => false, 'message' => 'Cần nhập giá nhập/kg để tính tiền cho lô hàng.'];
        }

        $startedTransaction = false;
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT so.*, b.id AS batch_id, b.product_id, b.warehouse_id AS batch_warehouse_id, b.status AS batch_status
                FROM supplier_orders so
                JOIN product_batch b ON b.supplier_order_id = so.id
                WHERE so.id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$supplierOrderId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$order) {
                throw new RuntimeException('Không tìm thấy phiếu lô cần xác nhận.');
            }
            if ((string)($order->status ?? '') !== 'supplier_submitted') {
                throw new RuntimeException('Chỉ xác nhận được đơn đang chờ kho duyệt.');
            }

            $batchId = (int)$order->batch_id;
            $productId = (int)$order->product_id;
            $warehouseId = (int)($data['warehouse_id'] ?? 0);
            if ($warehouseId <= 0) {
                $warehouseId = (int)($order->warehouse_id ?? $order->batch_warehouse_id ?? 0);
            }
            if ($warehouseId <= 0) {
                $warehouseId = $this->ensureDefaultWarehouse();
            }

            $payableAmount = $confirmedQuantity * $importPrice;

            $this->conn->prepare("
                UPDATE product_batch
                SET warehouse_id = ?,
                    quantity = ?,
                    remaining_quantity = ?,
                    status = 'stored',
                    import_price = ?,
                    confirmed_quantity = ?,
                    payable_amount = ?,
                    payment_status = 'unpaid'
                WHERE id = ?
            ")->execute([
                $warehouseId,
                $confirmedQuantity,
                $confirmedQuantity,
                $importPrice,
                $confirmedQuantity,
                $payableAmount,
                $batchId
            ]);

            $this->conn->prepare("DELETE FROM inventory WHERE batch_id = ? AND (product_id <> ? OR warehouse_id <> ?)")
                ->execute([$batchId, $productId, $warehouseId]);
            $this->upsertInventory($productId, $warehouseId, $batchId, $confirmedQuantity);
            $this->logInventory($productId, $warehouseId, $batchId, 'import', $confirmedQuantity, null, 'Nhập kho theo đơn nhập nhà cung cấp #' . $supplierOrderId);
            $this->syncBatchRemaining($batchId);
            $this->syncProductStock($productId);
            $this->upsertTraceabilityForBatch($batchId);

            $this->conn->prepare("
                UPDATE supplier_orders
                SET warehouse_id = ?,
                    status = 'warehouse_confirmed',
                    total_amount = ?,
                    paid_amount = LEAST(COALESCE(paid_amount, 0), ?)
                WHERE id = ?
            ")->execute([$warehouseId, $payableAmount, $payableAmount, $supplierOrderId]);

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return [
                'success' => true,
                'message' => 'Đã xác nhận nhập kho: ' . number_format($confirmedQuantity, 0, ',', '.') . ' kg x ' . number_format($importPrice, 0, ',', '.') . 'đ/kg = ' . number_format($payableAmount, 0, ',', '.') . 'đ phải trả nhà cung cấp.',
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Cannot confirm supplier order: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function paySupplierOrder(array $data): array
    {
        $supplierOrderId = (int)($data['supplier_order_id'] ?? 0);
        $amount = max(0, (float)($data['amount'] ?? 0));
        if ($supplierOrderId <= 0 || $amount <= 0) {
            return ['success' => false, 'message' => 'Số tiền thanh toán không hợp lệ.'];
        }

        $startedTransaction = false;
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT so.*
                FROM supplier_orders so
                WHERE so.id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$supplierOrderId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$order) {
                throw new RuntimeException('Không tìm thấy đơn nhập lô cần thanh toán.');
            }
            if (!in_array((string)($order->status ?? ''), ['warehouse_confirmed', 'completed', 'paid'], true)) {
                throw new RuntimeException('Chỉ thanh toán sau khi kho đã xác nhận nhập hàng.');
            }

            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(payable_amount), 0) AS batch_total,
                       MIN(id) AS batch_id
                FROM product_batch
                WHERE supplier_order_id = ?
            ");
            $stmt->execute([$supplierOrderId]);
            $batchTotals = $stmt->fetch(PDO::FETCH_OBJ) ?: (object)['batch_total' => 0, 'batch_id' => null];
            $totalAmount = max((float)($order->total_amount ?? 0), (float)($batchTotals->batch_total ?? 0));
            if ($totalAmount <= 0) {
                throw new RuntimeException('Đơn nhập chưa có số tiền phải trả.');
            }

            $paidAmount = (float)($order->paid_amount ?? 0);
            $remaining = max(0, $totalAmount - $paidAmount);
            if ($remaining <= 0) {
                throw new RuntimeException('Đơn nhập này đã thanh toán đủ.');
            }
            if ($amount > $remaining) {
                throw new RuntimeException('Số tiền thanh toán không được vượt quá số còn lại: ' . number_format($remaining, 0, ',', '.') . 'đ.');
            }

            $paidNow = $amount;
            $newPaidAmount = $paidAmount + $paidNow;
            $paymentStatus = $newPaidAmount >= $totalAmount ? 'paid' : 'partial';
            $orderStatus = $paymentStatus === 'paid' ? 'paid' : 'warehouse_confirmed';

            $this->conn->prepare("
                INSERT INTO supplier_payment_logs (supplier_order_id, supplier_id, batch_id, amount, note)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $supplierOrderId,
                (int)$order->supplier_id,
                !empty($batchTotals->batch_id) ? (int)$batchTotals->batch_id : null,
                $paidNow,
                trim($data['note'] ?? '')
            ]);

            $this->conn->prepare("
                UPDATE supplier_orders
                SET total_amount = ?,
                    paid_amount = ?,
                    status = ?
                WHERE id = ?
            ")->execute([$totalAmount, $newPaidAmount, $orderStatus, $supplierOrderId]);

            $this->conn->prepare("
                UPDATE product_batch
                SET payment_status = ?
                WHERE supplier_order_id = ?
            ")->execute([$paymentStatus, $supplierOrderId]);

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return [
                'success' => true,
                'message' => 'Đã ghi nhận thanh toán ' . number_format($paidNow, 0, ',', '.') . 'đ cho nhà cung cấp. Còn lại: ' . number_format(max(0, $totalAmount - $newPaidAmount), 0, ',', '.') . 'đ.',
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Cannot pay supplier order: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function resolveBatchProduct(array $data, ?int $supplierId, ?int $warehouseId, string $harvestDate): int
    {
        $productId = (int)($data['product_id'] ?? 0);
        if ($productId > 0) {
            return $productId;
        }

        $productName = trim($data['product_name'] ?? '');
        if ($productName === '') {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT p.id
            FROM product p
            LEFT JOIN (
                SELECT product_id, COUNT(*) AS batch_count
                FROM product_batch
                GROUP BY product_id
            ) batch_refs ON batch_refs.product_id = p.id
            WHERE p.name = ?
            ORDER BY COALESCE(batch_refs.batch_count, 0) DESC,
                     COALESCE(p.stock, 0) DESC,
                     p.id ASC
            LIMIT 1
        ");
        $stmt->execute([$productName]);
        $existingId = (int)$stmt->fetchColumn();
        if ($existingId > 0) {
            return $existingId;
        }

        $categoryId = $this->resolveBatchCategory($data);
        $price = max(0, (float)($data['product_price'] ?? 0));
        $image = trim($data['product_image'] ?? '');
        $description = trim($data['product_description'] ?? '');
        if ($description === '') {
            $description = 'Sản phẩm được tạo từ lô thu hoạch ' . $productName . ', dùng cho quản lý kho và truy xuất nguồn gốc.';
        }

        $stmt = $this->conn->prepare("
            INSERT INTO product (name, price, category_id, stock, image, description)
            VALUES (?, ?, ?, 0, ?, ?)
        ");
        $stmt->execute([
            $productName,
            $price,
            $categoryId > 0 ? $categoryId : null,
            $image,
            $description
        ]);

        $productId = (int)$this->conn->lastInsertId();
        $this->updateProductSupplyInfo($productId, $supplierId, $warehouseId, $harvestDate, $data);
        return $productId;
    }

    private function resolveBatchCategory(array $data): int
    {
        $newCategory = trim($data['new_category_name'] ?? '');
        if ($newCategory !== '') {
            $stmt = $this->conn->prepare("SELECT id FROM category WHERE name = ? LIMIT 1");
            $stmt->execute([$newCategory]);
            $id = (int)$stmt->fetchColumn();
            if ($id > 0) {
                return $id;
            }

            $stmt = $this->conn->prepare("INSERT INTO category (name, description) VALUES (?, ?)");
            $stmt->execute([$newCategory, trim($data['new_category_description'] ?? '')]);
            return (int)$this->conn->lastInsertId();
        }

        return (int)($data['category_id'] ?? 0);
    }

    private function updateProductSupplyInfo(int $productId, ?int $supplierId, ?int $warehouseId, string $harvestDate, array $data): void
    {
        if ($productId <= 0) {
            return;
        }

        $supplier = null;
        if ($supplierId) {
            $stmt = $this->conn->prepare("SELECT * FROM supplier WHERE id = ? LIMIT 1");
            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        }

        $warehouse = null;
        if ($warehouseId) {
            $stmt = $this->conn->prepare("SELECT * FROM warehouse WHERE id = ? LIMIT 1");
            $stmt->execute([$warehouseId]);
            $warehouse = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        }

        $fields = [];
        $params = [];

        if ($this->columnExists('product', 'origin')) {
            $fields[] = 'origin = ?';
            $params[] = trim((string)($supplier->farm_location ?? $supplier->address ?? ''));
        }
        if ($this->columnExists('product', 'supplier')) {
            $fields[] = 'supplier = ?';
            $params[] = trim((string)($supplier->name ?? ''));
        }
        if ($this->columnExists('product', 'standard')) {
            $fields[] = 'standard = ?';
            $params[] = trim($data['quality_standard'] ?? '');
        }
        if ($this->columnExists('product', 'harvest_date')) {
            $fields[] = 'harvest_date = ?';
            $params[] = $harvestDate ?: null;
        }
        if ($this->columnExists('product', 'expiry_days')) {
            $fields[] = 'expiry_days = ?';
            $params[] = max(1, (int)($data['expiry_days'] ?? 10));
        }
        if ($this->columnExists('product', 'warehouse_address')) {
            $fields[] = 'warehouse_address = ?';
            $params[] = trim((string)($warehouse->location ?? ''));
        }

        if (empty($fields)) {
            return;
        }

        $params[] = $productId;
        $stmt = $this->conn->prepare("UPDATE product SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
    }

    private function ensureBatchProductLinked(int $batchId, array $data = []): int
    {
        if ($batchId <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT id, product_id, supplier_id, warehouse_id, batch_code, harvest_date, status, product_name_snapshot
            FROM product_batch
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$batch) {
            return 0;
        }

        $currentProductId = (int)($batch->product_id ?? 0);
        if ($currentProductId > 0) {
            return $currentProductId;
        }
        if (in_array((string)($batch->status ?? ''), ['archived', 'deleted_product', 'product_deleted'], true)) {
            return 0;
        }
        if (trim((string)($batch->product_name_snapshot ?? '')) !== '') {
            return 0;
        }

        $inventoryProductId = 0;
        $stmt = $this->conn->prepare("
            SELECT product_id
            FROM inventory
            WHERE batch_id = ?
              AND product_id IS NOT NULL
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$batchId]);
        $inventoryProductId = (int)$stmt->fetchColumn();

        if ($inventoryProductId > 0) {
            $this->conn->prepare("UPDATE product_batch SET product_id = ? WHERE id = ?")
                ->execute([$inventoryProductId, $batchId]);
            return $inventoryProductId;
        }

        $productName = trim((string)($data['product_name'] ?? ''));
        if ($productName === '') {
            $productName = 'Sản phẩm từ lô ' . trim((string)($batch->batch_code ?? ('BATCH-' . $batchId)));
        }

        $resolvedData = $data;
        $resolvedData['product_name'] = $productName;
        $productId = $this->resolveBatchProduct(
            $resolvedData,
            !empty($batch->supplier_id) ? (int)$batch->supplier_id : null,
            !empty($batch->warehouse_id) ? (int)$batch->warehouse_id : null,
            !empty($batch->harvest_date) ? (string)$batch->harvest_date : date('Y-m-d')
        );

        if ($productId > 0) {
            $this->conn->prepare("UPDATE product_batch SET product_id = ? WHERE id = ?")
                ->execute([$productId, $batchId]);
        }

        return $productId;
    }

    public function saveBatch(array $data): array
    {
        $supplierId = (int)($data['supplier_id'] ?? 0);
        $supplierOrderId = (int)($data['supplier_order_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $stockWarehouseId = $warehouseId > 0 ? $warehouseId : $this->ensureDefaultWarehouse();
        $batchWarehouseId = $stockWarehouseId;
        $quantity = max(0, (int)($data['quantity'] ?? 0));
        $importPrice = max(0, (float)($data['import_price'] ?? 0));
        $confirmedQuantity = $quantity;
        $payableAmount = $importPrice > 0 ? $confirmedQuantity * $importPrice : 0;
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Số lượng nhập vào lô mới phải lớn hơn 0 kg.'];
        }

        $harvestDate = !empty($data['harvest_date']) ? $data['harvest_date'] : date('Y-m-d');
        $startedTransaction = false;
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $productId = $this->resolveBatchProduct($data, $supplierId ?: null, $stockWarehouseId, $harvestDate);
            if ($productId <= 0) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return ['success' => false, 'message' => 'Vui lòng chọn hoặc nhập tên sản phẩm hợp lệ.'];
            }

            if ($supplierOrderId <= 0 && $supplierId > 0 && $payableAmount > 0) {
                $this->conn->prepare("
                    INSERT INTO supplier_orders (supplier_id, warehouse_id, status, total_amount, paid_amount)
                    VALUES (?, ?, 'warehouse_confirmed', ?, 0)
                ")->execute([$supplierId, $stockWarehouseId, $payableAmount]);
                $supplierOrderId = (int)$this->conn->lastInsertId();
            }

            $batchCode = trim($data['batch_code'] ?? '');
            if ($batchCode === '') {
                $batchCode = 'LOT-' . date('Ymd-His') . '-' . random_int(10, 99);
            }
            $qr = '/webbanhang/product/trace/' . $productId . '?batch=' . rawurlencode($batchCode);

            $stmt = $this->conn->prepare("
                INSERT INTO product_batch (
                    product_id, supplier_id, supplier_order_id, warehouse_id, batch_code, qr_code, planting_date, harvest_date,
                    cultivation_method, fertilizer, pesticide, area, quantity, remaining_quantity, unit,
                    quality_standard, storage_temperature, storage_humidity, preservation_method, status,
                    import_price, confirmed_quantity, payable_amount, payment_status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $productId,
                $supplierId ?: null,
                $supplierOrderId > 0 ? $supplierOrderId : null,
                $batchWarehouseId,
                $batchCode,
                $qr,
                !empty($data['planting_date']) ? $data['planting_date'] : null,
                $harvestDate,
                trim($data['cultivation_method'] ?? ''),
                trim($data['fertilizer'] ?? ''),
                trim($data['pesticide'] ?? ''),
                (float)($data['area'] ?? 0),
                $quantity,
                $quantity,
                'kg',
                trim($data['quality_standard'] ?? ''),
                ($data['storage_temperature'] ?? '') !== '' ? (float)$data['storage_temperature'] : null,
                ($data['storage_humidity'] ?? '') !== '' ? (float)$data['storage_humidity'] : null,
                trim($data['preservation_method'] ?? ''),
                'stored',
                $importPrice,
                $confirmedQuantity,
                $payableAmount,
                'unpaid',
                trim($data['notes'] ?? '')
            ]);

            $batchId = (int)$this->conn->lastInsertId();
            $this->upsertInventory($productId, $stockWarehouseId, $batchId, $quantity);
            $this->logInventory($productId, $stockWarehouseId, $batchId, 'import', $quantity, null, 'Nhập hàng tạo lô mới');
            $this->syncProductStock($productId);
            $this->updateProductSupplyInfo($productId, $supplierId ?: null, $stockWarehouseId, $harvestDate, $data);
            $this->upsertTraceabilityForBatch($batchId);

            if ($supplierOrderId > 0) {
                $this->conn->prepare("
                    UPDATE supplier_orders
                    SET warehouse_id = ?,
                        status = 'warehouse_confirmed',
                        total_amount = ?,
                        paid_amount = LEAST(COALESCE(paid_amount, 0), ?)
                    WHERE id = ?
                ")->execute([$stockWarehouseId, $payableAmount, $payableAmount, $supplierOrderId]);
            }

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            $warehouseNote = $warehouseId > 0 ? '' : ' (tự gán kho mặc định)';
            return [
                'success' => true,
                'batch_id' => $batchId,
                'product_id' => $productId,
                'message' => 'Đã tạo lô mới, nhập kho và đồng bộ sản phẩm lên cửa hàng' . $warehouseNote . '.',
            ];
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Cannot save product batch: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Không thể tạo lô hàng. Kiểm tra mã lô trùng hoặc dữ liệu nhập.'];
        }
    }

    public function deleteBatch(int $batchId, string $reason = ''): string
    {
        if ($batchId <= 0) {
            return 'invalid';
        }

        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Đối tác hoặc chất lượng lô hàng có vấn đề';
        }

        $startedTransaction = false;
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }
        try {
            $stmt = $this->conn->prepare("SELECT * FROM product_batch WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$batch) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return 'missing';
            }

            $productId = (int)$batch->product_id;
            $hasOrderDetails = $this->countRows("SELECT COUNT(*) FROM order_details WHERE batch_id = ?", [$batchId]) > 0;
            $hasLogs = $this->countRows("SELECT COUNT(*) FROM inventory_log WHERE batch_id = ?", [$batchId]) > 0;

            if (!$hasOrderDetails && !$hasLogs) {
                $this->conn->prepare("DELETE FROM inventory WHERE batch_id = ?")->execute([$batchId]);
                if ($this->tableExists('storage_monitoring')) {
                    $this->conn->prepare("DELETE FROM storage_monitoring WHERE batch_id = ?")->execute([$batchId]);
                }
                if ($this->tableExists('product_traceability')) {
                    $this->conn->prepare("DELETE FROM product_traceability WHERE batch_id = ?")->execute([$batchId]);
                }
                $this->conn->prepare("DELETE FROM product_batch WHERE id = ?")->execute([$batchId]);
                if ($productId > 0) {
                    $this->syncProductStock($productId);
                }
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->commit();
                }
                return 'deleted';
            }

            $rows = $this->conn->prepare("
                SELECT product_id, warehouse_id, batch_id, quantity
                FROM inventory
                WHERE batch_id = ? AND quantity > 0
            ");
            $rows->execute([$batchId]);
            foreach ($rows->fetchAll(PDO::FETCH_OBJ) as $row) {
                $qty = (int)$row->quantity;
                $this->conn->prepare("
                    UPDATE inventory
                    SET quantity = 0
                    WHERE product_id = ? AND warehouse_id = ? AND batch_id = ?
                ")->execute([(int)$row->product_id, (int)$row->warehouse_id, (int)$row->batch_id]);
                $this->logInventory(
                    (int)$row->product_id,
                    (int)$row->warehouse_id,
                    (int)$row->batch_id,
                    'export',
                    $qty,
                    null,
                    'Thu hồi lô: ' . $reason
                );
            }
            $this->conn->prepare("DELETE FROM inventory WHERE batch_id = ?")->execute([$batchId]);

            $notes = trim((string)($batch->notes ?? ''));
            $notes = trim($notes . "\n[Thu hồi " . date('d/m/Y H:i') . "] " . $reason);
            $this->conn->prepare("
                UPDATE product_batch
                SET status = 'recalled', remaining_quantity = 0, notes = ?
                WHERE id = ?
            ")->execute([$notes, $batchId]);

            if ($productId > 0) {
                $this->syncProductStock($productId);
            }

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }
            return 'recalled';
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Cannot delete/recall batch: ' . $e->getMessage());
            return 'error';
        }
    }

    public function getInventoryRows(): array
    {
        return $this->conn->query("
            SELECT i.*, COALESCE(p.name, b.product_name_snapshot, 'Sản phẩm đã xóa') AS product_name, p.price, p.image, b.batch_code, b.harvest_date, b.unit,
                   b.quality_standard, b.preservation_method, w.name AS warehouse_name, w.location
            FROM inventory i
            JOIN product p ON p.id = i.product_id
            JOIN product_batch b ON b.id = i.batch_id
            JOIN warehouse w ON w.id = i.warehouse_id
            WHERE i.quantity > 0
              AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
              AND COALESCE(w.status, 'active') = 'active'
            ORDER BY i.id DESC
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function getInventoryOrders(): array
    {
        return $this->conn->query("
            SELECT o.id, o.total, o.status, o.delivery_scope, o.export_country, o.recipient_country,
                   o.shipping_carrier, o.shipping_service, o.created_at,
                   o.payment_status, o.stock_status, o.shipping_status,
                   COALESCE(o.fullname, a.fullname, 'Khách hàng') AS fullname,
                   COALESCE(o.phone, a.phone, '') AS phone
            FROM orders o
            LEFT JOIN account a ON a.id = o.user_id
            WHERE o.status NOT IN ('completed', 'cancelled')
            ORDER BY o.created_at DESC
            LIMIT 80
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function getInventoryOrderItems(): array
    {
        return $this->conn->query("
            SELECT od.order_id, od.product_id,
                   COALESCE(p.name, od.product_name_snapshot, CONCAT('Sản phẩm #', COALESCE(od.product_id, 'đã xóa'))) AS product_name,
                   COALESCE(c.name, 'Sản phẩm đã xóa') AS category_name, COALESCE(SUM(od.quantity), 0) AS quantity,
                   COALESCE(active_stock.current_stock, 0) AS current_stock
            FROM order_details od
            JOIN orders o ON o.id = od.order_id
            LEFT JOIN product p ON p.id = od.product_id
            LEFT JOIN category c ON c.id = p.category_id
            LEFT JOIN (
                SELECT i.product_id, COALESCE(SUM(i.quantity), 0) AS current_stock
                FROM inventory i
                JOIN product_batch b ON b.id = i.batch_id
                JOIN warehouse w ON w.id = i.warehouse_id
                WHERE i.quantity > 0
                  AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  AND COALESCE(w.status, 'active') = 'active'
                GROUP BY i.product_id
            ) active_stock ON active_stock.product_id = od.product_id
            WHERE o.status NOT IN ('completed', 'cancelled')
            GROUP BY od.order_id, od.product_id, product_name, category_name, active_stock.current_stock
            ORDER BY od.order_id DESC, product_name ASC
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function getInventoryLogs(int $limit = 40, ?string $fromDate = null, ?string $toDate = null, string $statusFilter = '', string $scopeFilter = ''): array
    {
        $limit = max(1, min(100, $limit));
        $where = [];
        $params = [];
        if ($fromDate && $toDate) {
            $where[] = "DATE(l.created_at) BETWEEN ? AND ?";
            $params[] = $fromDate;
            $params[] = $toDate;
        }
        if ($statusFilter !== '') {
            $where[] = "o.status = ?";
            $params[] = $statusFilter;
        }
        if ($scopeFilter !== '') {
            $where[] = "COALESCE(o.delivery_scope, 'domestic') = ?";
            $params[] = $scopeFilter;
        }
        $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

        $stmt = $this->conn->prepare("
            SELECT l.*, COALESCE(p.name, l.product_name_snapshot, b.product_name_snapshot, 'Sản phẩm đã xóa') AS product_name, b.batch_code, b.unit, w.name AS warehouse_name,
                   o.total AS order_total, o.status AS order_status, o.delivery_scope,
                   o.export_country, o.recipient_country,
                   COALESCE(o.fullname, a.fullname, 'Khách hàng') AS order_customer
            FROM inventory_log l
            LEFT JOIN product p ON p.id = l.product_id
            LEFT JOIN product_batch b ON b.id = l.batch_id
            LEFT JOIN warehouse w ON w.id = l.warehouse_id
            LEFT JOIN orders o ON o.id = l.order_id
            LEFT JOIN account a ON a.id = o.user_id
            {$whereSql}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function checkOrderStock(int $orderId, bool $persist = true): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'status' => 'unchecked', 'message' => 'Don khong hop le.', 'items' => []];
        }

        $items = $this->getOrderRequirements($orderId);
        if (empty($items)) {
            if ($persist) {
                $this->setOrderStockStatus($orderId, 'unchecked');
            }
            return ['success' => false, 'status' => 'unchecked', 'message' => 'Don hang chua co san pham.', 'items' => []];
        }

        $checked = [];
        $shortages = [];
        foreach ($items as $item) {
            $productId = (int)$item->product_id;
            $required = (int)$item->quantity;
            $available = $this->availableInventoryForProduct($productId);
            $row = [
                'product_id' => $productId,
                'product_name' => (string)($item->product_name ?? 'Nong san'),
                'required' => $required,
                'available' => $available,
                'missing' => max(0, $required - $available),
            ];
            $checked[] = $row;
            if ($row['missing'] > 0) {
                $shortages[] = $row;
            }
        }

        $status = empty($shortages) ? 'sufficient' : 'insufficient';
        if ($persist) {
            $this->setOrderStockStatus($orderId, $status);
            OrderWorkflow::recordEvent(
                $this->conn,
                $orderId,
                $this->currentActorId(),
                'stock_checked',
                null,
                null,
                $status === 'sufficient' ? 'Ton kho du cho don hang' : 'Ton kho khong du cho don hang',
                '',
                ['items' => $checked]
            );
        }

        return [
            'success' => empty($shortages),
            'status' => $status,
            'message' => empty($shortages) ? 'Du hang de xuat kho.' : 'Don hang dang thieu ton kho.',
            'items' => $checked,
            'shortages' => $shortages,
        ];
    }

    public function exportOrderInventory(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'order_id' => $orderId, 'message' => 'Don hang khong hop le.'];
        }

        $started = false;
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $started = true;
            }

            $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$order) {
                throw new RuntimeException('Khong tim thay don hang.');
            }

            $currentStatus = (string)($order->status ?? 'pending');
            if (in_array($currentStatus, ['cancelled', 'completed'], true)) {
                throw new RuntimeException('Don da ket thuc, khong the xuat kho.');
            }
            if ($currentStatus === 'pending') {
                $this->setOrderStockStatus($orderId, 'unchecked');
                throw new RuntimeException('Can xac nhan don truoc khi xuat kho.');
            }
            if (in_array($currentStatus, ['packed', 'shipped'], true) || (string)($order->stock_status ?? '') === 'exported') {
                if (in_array($currentStatus, ['confirmed', 'preparing'], true)) {
                    OrderWorkflow::syncOrderStatus($this->conn, $orderId, 'packed', $currentStatus, $this->currentActorId(), 'Dong bo don da xuat kho truoc do');
                }
                if ($started && $this->conn->inTransaction()) {
                    $this->conn->commit();
                }
                return ['success' => true, 'order_id' => $orderId, 'message' => 'Don da xuat kho truoc do.'];
            }
            if (!in_array($currentStatus, ['confirmed', 'preparing'], true)) {
                throw new RuntimeException('Chi don da xac nhan hoac dang chuan bi moi duoc xuat kho.');
            }

            $check = $this->checkOrderStock($orderId, false);
            if (!($check['success'] ?? false)) {
                $this->setOrderStockStatus($orderId, 'insufficient');
                if ($started && $this->conn->inTransaction()) {
                    $this->conn->commit();
                }
                return ['success' => false, 'order_id' => $orderId, 'message' => $check['message'], 'shortages' => $check['shortages'] ?? []];
            }

            $items = $this->getOrderRequirements($orderId);
            $insertDetail = $this->conn->prepare("INSERT INTO order_details (order_id, product_id, batch_id, warehouse_id, quantity, price, product_name_snapshot) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $deleteDetail = $this->conn->prepare("DELETE FROM order_details WHERE order_id = ? AND product_id = ?");

            foreach ($items as $item) {
                $productId = (int)$item->product_id;
                $required = (int)$item->quantity;
                $price = (float)$item->price;
                $alreadyExported = $this->exportedQuantityForOrderProduct($orderId, $productId);
                $remaining = max(0, $required - $alreadyExported);
                if ($remaining <= 0) {
                    continue;
                }

                $allocations = $this->allocatableInventoryRows($productId, $remaining, true);
                $available = array_sum(array_map(fn($row) => (int)$row->quantity, $allocations));
                if ($available < $remaining) {
                    throw new RuntimeException('Ton kho thay doi trong luc xuat kho, vui long kiem tra lai.');
                }

                $deleteDetail->execute([$orderId, $productId]);
                foreach ($allocations as $row) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min($remaining, (int)$row->quantity);
                    if ($take <= 0) {
                        continue;
                    }
                    $newQty = (int)$row->quantity - $take;
                    $this->conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$newQty, (int)$row->id]);
                    $insertDetail->execute([$orderId, $productId, (int)$row->batch_id, (int)$row->warehouse_id, $take, $price, (string)($item->product_name ?? ('Sản phẩm #' . $productId))]);
                    $this->logInventory($productId, (int)$row->warehouse_id, (int)$row->batch_id, 'export', $take, $orderId, 'Xuat kho tu dong theo don hang');
                    $this->syncBatchRemaining((int)$row->batch_id);
                    $remaining -= $take;
                }
                $this->syncProductStock($productId);
            }

            $this->setOrderStockStatus($orderId, 'exported');
            OrderWorkflow::syncOrderStatus($this->conn, $orderId, 'packed', $currentStatus, $this->currentActorId(), 'Kho da xuat hang va dong goi theo don');

            if ($started && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return ['success' => true, 'order_id' => $orderId, 'message' => 'Da xuat kho va chuyen don sang da dong goi.'];
        } catch (Throwable $e) {
            if ($started && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('exportOrderInventory error: ' . $e->getMessage());
            return ['success' => false, 'order_id' => $orderId, 'message' => $e->getMessage()];
        }
    }

    public function isOrderFullyExported(int $orderId): bool
    {
        $items = $this->getOrderRequirements($orderId);
        if (empty($items)) {
            return false;
        }
        foreach ($items as $item) {
            if ($this->exportedQuantityForOrderProduct($orderId, (int)$item->product_id) < (int)$item->quantity) {
                return false;
            }
        }
        return true;
    }

    private function getOrderRequirements(int $orderId): array
    {
        $stmt = $this->conn->prepare("
            SELECT od.product_id,
                   MAX(od.price) AS price,
                   COALESCE(SUM(od.quantity), 0) AS quantity,
                   COALESCE(p.name, od.product_name_snapshot, CONCAT('Sản phẩm #', COALESCE(od.product_id, 'đã xóa'))) AS product_name
            FROM order_details od
            LEFT JOIN product p ON p.id = od.product_id
            WHERE od.order_id = ?
            GROUP BY od.product_id, product_name
            ORDER BY product_name ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function availableInventoryForProduct(int $productId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(i.quantity), 0)
            FROM inventory i
            JOIN product_batch b ON b.id = i.batch_id
            JOIN warehouse w ON w.id = i.warehouse_id
            WHERE i.product_id = ?
              AND i.quantity > 0
              AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
              AND COALESCE(w.status, 'active') = 'active'
        ");
        $stmt->execute([$productId]);
        return (int)$stmt->fetchColumn();
    }

    private function exportedQuantityForOrderProduct(int $orderId, int $productId): int
    {
        if (!$this->columnExists('inventory_log', 'order_id')) {
            return 0;
        }
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'export' THEN quantity WHEN type = 'import' THEN -quantity ELSE 0 END), 0) FROM inventory_log WHERE order_id = ? AND product_id = ?");
        $stmt->execute([$orderId, $productId]);
        return max(0, (int)$stmt->fetchColumn());
    }

    private function allocatableInventoryRows(int $productId, int $required, bool $forUpdate = false): array
    {
        $sql = "SELECT i.id, i.quantity, i.warehouse_id, i.batch_id
                FROM inventory i
                JOIN product_batch b ON b.id = i.batch_id
                JOIN warehouse w ON w.id = i.warehouse_id
                WHERE i.product_id = ?
                  AND i.quantity > 0
                  AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  AND COALESCE(w.status, 'active') = 'active'
                ORDER BY COALESCE(b.harvest_date, DATE(b.created_at)), i.id";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        $picked = [];
        $remaining = $required;
        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }
            $picked[] = $row;
            $remaining -= (int)$row->quantity;
        }
        return $picked;
    }

    private function setOrderStockStatus(int $orderId, string $status): void
    {
        if (!$this->columnExists('orders', 'stock_status')) {
            return;
        }
        $this->conn->prepare("UPDATE orders SET stock_status = ? WHERE id = ?")->execute([$status, $orderId]);
    }

    public function adjustInventory(array $data): array
    {
        $batchId = (int)($data['batch_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        $type = ($data['type'] ?? 'import') === 'export' ? 'export' : 'import';
        if ($type === 'export') {
            return [
                'success' => false,
                'order_id' => (int)($data['order_id'] ?? 0),
                'type' => 'export',
                'message' => 'Xuat kho thu cong da khoa. Hay dung nut Xuat kho tu dong theo don hang.'
            ];
        }
        $quantity = max(0, (int)($data['quantity'] ?? 0));
        $orderId = $type === 'export' ? (int)($data['order_id'] ?? 0) : 0;
        if ($batchId <= 0 || $quantity <= 0 || ($type === 'export' && $orderId <= 0)) {
            return ['success' => false, 'order_id' => $orderId, 'type' => $type, 'message' => 'Thiếu dữ liệu nhập/xuất kho.'];
        }

        $stmt = $this->conn->prepare("SELECT product_id, warehouse_id, status FROM product_batch WHERE id = ?");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$batch) {
            return ['success' => false, 'order_id' => $orderId, 'type' => $type, 'message' => 'Không tìm thấy lô hàng.'];
        }

        if ($warehouseId <= 0) {
            $warehouseId = (int)($batch->warehouse_id ?? 0);
            if ($warehouseId <= 0) {
                $warehouseId = $this->ensureDefaultWarehouse();
            }
        }

        $productId = (int)($batch->product_id ?? 0);
        if ($productId <= 0) {
            $productId = $this->ensureBatchProductLinked($batchId, $data);
        }
        if ($productId <= 0) {
            return ['success' => false, 'order_id' => $orderId, 'type' => $type, 'message' => 'Không thể liên kết lô với sản phẩm.'];
        }
        if (($batch->status ?? '') === 'recalled') {
            return ['success' => false, 'order_id' => $orderId, 'type' => $type, 'message' => 'Lô hàng đã thu hồi.'];
        }
        if (!in_array((string)($batch->status ?? 'stored'), ['stored', 'active', 'available'], true)) {
            return ['success' => false, 'order_id' => $orderId, 'type' => $type, 'message' => 'Lô lịch sử không dùng để nhập/xuất kho. Hãy tạo lô nhập hàng mới.'];
        }

        if ($type === 'export' && $orderId > 0 && $this->isOrderBatchAlreadyReserved($orderId, $productId, $warehouseId, $batchId)) {
            return [
                'success' => true,
                'order_id' => $orderId,
                'type' => $type,
                'product_id' => $productId,
                'batch_id' => $batchId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'confirmed_only' => true,
                'message' => 'Đơn đã giữ tồn theo lô, chỉ xác nhận xuất kho và chuyển vận chuyển.',
            ];
        }

        $stmt = $this->conn->prepare("
            SELECT id, quantity FROM inventory
            WHERE product_id = ? AND warehouse_id = ? AND batch_id = ?
            LIMIT 1
        ");
        $stmt->execute([$productId, $warehouseId, $batchId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        $current = $row ? (int)$row->quantity : 0;
        if ($type === 'export') {
            if ($current <= 0) {
                return ['success' => false, 'order_id' => $orderId, 'type' => $type, 'message' => 'Lô hàng không còn tồn kho.'];
            }
            $quantity = min($quantity, $current);
        }
        $newQuantity = $type === 'import' ? $current + $quantity : $current - $quantity;

        $this->upsertInventory($productId, $warehouseId, $batchId, $newQuantity);
        $this->logInventory($productId, $warehouseId, $batchId, $type, $quantity, $orderId ?: null);
        $this->syncBatchRemaining($batchId);
        $this->syncProductStock($productId);

        return [
            'success' => true,
            'order_id' => $orderId,
            'type' => $type,
            'product_id' => $productId,
            'batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
        ];
    }

    private function isOrderBatchAlreadyReserved(int $orderId, int $productId, int $warehouseId, int $batchId): bool
    {
        if (
            $orderId <= 0
            || !$this->columnExists('order_details', 'batch_id')
            || !$this->columnExists('order_details', 'warehouse_id')
            || !$this->columnExists('inventory_log', 'order_id')
        ) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(quantity), 0)
            FROM order_details
            WHERE order_id = ? AND product_id = ? AND warehouse_id = ? AND batch_id = ?
        ");
        $stmt->execute([$orderId, $productId, $warehouseId, $batchId]);
        $reserved = (int)$stmt->fetchColumn();
        if ($reserved <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(quantity), 0)
            FROM inventory_log
            WHERE order_id = ? AND product_id = ? AND warehouse_id = ? AND batch_id = ? AND type = 'export'
        ");
        $stmt->execute([$orderId, $productId, $warehouseId, $batchId]);
        $exported = (int)$stmt->fetchColumn();

        return $exported >= $reserved;
    }

    private function upsertInventory(int $productId, int $warehouseId, int $batchId, int $quantity): void
    {
        $stmt = $this->conn->prepare("
            SELECT id FROM inventory
            WHERE product_id = ? AND warehouse_id = ? AND batch_id = ?
            LIMIT 1
        ");
        $stmt->execute([$productId, $warehouseId, $batchId]);
        $id = (int)$stmt->fetchColumn();

        if ($id > 0) {
            $this->conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$quantity, $id]);
            return;
        }

        $this->conn->prepare("
            INSERT INTO inventory (product_id, warehouse_id, batch_id, quantity)
            VALUES (?, ?, ?, ?)
        ")->execute([$productId, $warehouseId, $batchId, $quantity]);
    }

    private function countRows(string $sql, array $params = []): int
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function currentActorId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    private function logInventory(int $productId, int $warehouseId, int $batchId, string $type, int $quantity, ?int $orderId = null, string $note = ''): void
    {
        $orderDetailId = $this->findOrderDetailId($orderId, $productId, $warehouseId, $batchId);
        $hasOrderDetailId = $this->columnExists('inventory_log', 'order_detail_id');
        $hasProductNameSnapshot = $this->columnExists('inventory_log', 'product_name_snapshot');
        $productNameSnapshot = null;
        if ($hasProductNameSnapshot) {
            $stmt = $this->conn->prepare("
                SELECT COALESCE(p.name, b.product_name_snapshot, CONCAT('Sản phẩm #', ?))
                FROM product_batch b
                LEFT JOIN product p ON p.id = ?
                WHERE b.id = ?
                LIMIT 1
            ");
            $stmt->execute([$productId, $productId, $batchId]);
            $productNameSnapshot = (string)($stmt->fetchColumn() ?: ('Sản phẩm #' . $productId));
        }

        if ($this->columnExists('inventory_log', 'order_id') && $hasOrderDetailId && $this->columnExists('inventory_log', 'note') && $hasProductNameSnapshot) {
            $this->conn->prepare("
                INSERT INTO inventory_log (product_id, warehouse_id, batch_id, order_id, order_detail_id, product_name_snapshot, type, quantity, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$productId, $warehouseId, $batchId, $orderId, $orderDetailId, $productNameSnapshot, $type, $quantity, $note]);
            OrderWorkflow::recordInventoryEvent($this->conn, (int)($orderId ?? 0), $type, $quantity, [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
                'order_detail_id' => $orderDetailId,
                'note' => $note,
            ]);
            return;
        }

        if ($this->columnExists('inventory_log', 'order_id') && $this->columnExists('inventory_log', 'note')) {
            $this->conn->prepare("
                INSERT INTO inventory_log (product_id, warehouse_id, batch_id, order_id, type, quantity, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$productId, $warehouseId, $batchId, $orderId, $type, $quantity, $note]);
            OrderWorkflow::recordInventoryEvent($this->conn, (int)($orderId ?? 0), $type, $quantity, [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
                'note' => $note,
            ]);
            return;
        }

        if ($this->columnExists('inventory_log', 'order_id')) {
            $this->conn->prepare("
                INSERT INTO inventory_log (product_id, warehouse_id, batch_id, order_id, type, quantity)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$productId, $warehouseId, $batchId, $orderId, $type, $quantity]);
            OrderWorkflow::recordInventoryEvent($this->conn, (int)($orderId ?? 0), $type, $quantity, [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
            ]);
            return;
        }

        $this->conn->prepare("
            INSERT INTO inventory_log (product_id, warehouse_id, batch_id, type, quantity)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$productId, $warehouseId, $batchId, $type, $quantity]);
        OrderWorkflow::recordInventoryEvent($this->conn, (int)($orderId ?? 0), $type, $quantity, [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
        ]);
    }

    private function findOrderDetailId(?int $orderId, int $productId, int $warehouseId, int $batchId): ?int
    {
        if (
            empty($orderId)
            || !$this->tableExists('order_details')
            || !$this->columnExists('order_details', 'batch_id')
            || !$this->columnExists('order_details', 'warehouse_id')
        ) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT id
            FROM order_details
            WHERE order_id = ? AND product_id = ? AND warehouse_id = ? AND batch_id = ?
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$orderId, $productId, $warehouseId, $batchId]);
        $id = (int)$stmt->fetchColumn();
        return $id > 0 ? $id : null;
    }

    private function syncBatchRemaining(int $batchId): void
    {
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE batch_id = ?");
        $stmt->execute([$batchId]);
        $remaining = (int)$stmt->fetchColumn();
        $this->conn->prepare("UPDATE product_batch SET remaining_quantity = ? WHERE id = ?")->execute([$remaining, $batchId]);
    }

    public function syncProductStock(int $productId): void
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(i.quantity), 0)
            FROM inventory i
            JOIN product_batch b ON b.id = i.batch_id
            JOIN warehouse w ON w.id = i.warehouse_id
            WHERE i.product_id = ?
              AND i.quantity > 0
              AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
              AND COALESCE(w.status, 'active') = 'active'
        ");
        $stmt->execute([$productId]);
        $stock = (int)$stmt->fetchColumn();
        $this->conn->prepare("UPDATE product SET stock = ? WHERE id = ?")->execute([$stock, $productId]);
    }

    public function getExportOrders(): array
    {
        return $this->conn->query("
            SELECT o.*, o.total AS total_price, COALESCE(o.fullname, a.fullname, 'Khách lẻ') AS fullname,
                   COALESCE(o.phone, a.phone) AS phone,
                   s.export_country AS shipment_country, s.carrier, s.tracking_code AS shipment_tracking,
                   s.transport_method AS shipment_method, s.departure_date, s.arrival_date,
                   s.status AS shipment_status, s.notes AS shipment_notes
            FROM orders o
            LEFT JOIN account a ON a.id = o.user_id
            LEFT JOIN shipments s ON s.order_id = o.id
            WHERE COALESCE(o.delivery_scope, 'domestic') = 'international'
            ORDER BY o.created_at DESC
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function getShipmentTimelinesForOrders(array $orders): array
    {
        $ids = [];
        foreach ($orders as $order) {
            $id = (int)($order->id ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return [];
        }

        foreach ($orders as $order) {
            $status = (string)($order->shipment_status ?? $order->shipping_status ?? $order->status ?? 'pending');
            if (in_array($status, ['shipped', 'completed'], true)) {
                $this->ensureShipmentTimeline((int)$order->id);
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("
            SELECT *
            FROM shipment_tracking_events
            WHERE order_id IN ($placeholders)
            ORDER BY order_id ASC, sort_order ASC, id ASC
        ");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $timelines = [];
        foreach ($rows as $row) {
            $timelines[(int)$row->order_id][] = $row;
        }
        return $timelines;
    }

    public function getShipmentForOrder(int $orderId): ?object
    {
        if ($orderId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM shipments
            WHERE order_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $shipment = $stmt->fetch(PDO::FETCH_OBJ);

        return $shipment ?: null;
    }

    public function startShipmentAfterExport(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT o.*, s.id AS shipment_id
            FROM orders o
            LEFT JOIN shipments s ON s.order_id = o.id
            WHERE o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$order || (string)($order->status ?? '') !== 'packed') {
            return;
        }

        $country = trim((string)($order->export_country ?: ($order->recipient_country ?? '')));
        $carrier = trim((string)($order->shipping_carrier ?? ''));
        $method = trim((string)(($order->transport_method ?? '') ?: ($order->shipping_service ?? '')));
        $tracking = trim((string)($order->tracking_code ?? ''));
        $notes = 'Don da xuat kho va san sang ban giao van chuyen';

        if (!empty($order->shipment_id)) {
            $this->conn->prepare("
                UPDATE shipments
                SET export_country = COALESCE(NULLIF(export_country, ''), ?),
                    carrier = COALESCE(NULLIF(carrier, ''), ?),
                    tracking_code = COALESCE(NULLIF(tracking_code, ''), ?),
                    transport_method = COALESCE(NULLIF(transport_method, ''), ?),
                    status = 'packed',
                    notes = COALESCE(NULLIF(notes, ''), ?)
                WHERE order_id = ?
            ")->execute([$country, $carrier, $tracking, $method, $notes, $orderId]);
        } else {
            $this->conn->prepare("
                INSERT INTO shipments (order_id, export_country, carrier, tracking_code, transport_method, status, notes)
                VALUES (?, ?, ?, ?, ?, 'packed', ?)
            ")->execute([$orderId, $country, $carrier, $tracking, $method, $notes]);
        }

        $this->conn->prepare("
            UPDATE orders
            SET shipping_status = 'packed',
                export_status = CASE WHEN COALESCE(delivery_scope, 'domestic') = 'international' THEN 'packed' ELSE 'not_required' END
            WHERE id = ?
        ")->execute([$orderId]);

        $this->ensureShipmentTimeline($orderId);
        $this->syncTimelineByStatus($orderId, 'packed');
        OrderWorkflow::recordEvent($this->conn, $orderId, $this->currentActorId(), 'shipment_prepared', null, 'packed', 'Don san sang van chuyen', $notes, [
            'carrier' => $carrier,
            'tracking_code' => $tracking,
            'transport_method' => $method,
            'export_country' => $country,
        ]);
    }

    public function advanceShipmentStep(int $orderId): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'order_status' => null, 'message' => 'Don hang khong hop le.'];
        }

        $stmt = $this->conn->prepare("
            SELECT o.status AS order_status, s.id AS shipment_id, s.status AS shipment_status
            FROM orders o
            LEFT JOIN shipments s ON s.order_id = o.id
            WHERE o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$order || empty($order->shipment_id)) {
            return ['success' => false, 'order_status' => null, 'message' => 'Don chua co van chuyen hop le.'];
        }
        if (!in_array((string)$order->order_status, ['shipped', 'completed'], true)) {
            return ['success' => false, 'order_status' => null, 'message' => 'Can chuyen don sang dang van chuyen truoc khi cap nhat chang.'];
        }

        $this->ensureShipmentTimeline($orderId);
        $stmt = $this->conn->prepare("
            SELECT *
            FROM shipment_tracking_events
            WHERE order_id = ? AND COALESCE(is_completed, 0) = 0
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $next = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$next) {
            if ((string)$order->order_status === 'completed') {
                return ['success' => true, 'order_status' => 'completed'];
            }

            return [
                'success' => false,
                'order_status' => (string)$order->order_status,
                'message' => 'Cho khach hang xac nhan da nhan hang va thanh toan phan con lai.',
                'awaiting_customer_receipt' => true,
            ];
        }

        if ((string)($next->status_code ?? '') === 'delivered') {
            return [
                'success' => false,
                'order_status' => (string)$order->order_status,
                'message' => 'Cho khach hang xac nhan da nhan hang va thanh toan phan con lai.',
                'awaiting_customer_receipt' => true,
            ];
        }

        $this->conn->prepare("
            UPDATE shipment_tracking_events
            SET is_completed = 1, event_time = COALESCE(event_time, NOW())
            WHERE id = ?
        ")->execute([(int)$next->id]);

        $orderStatus = ((string)$next->status_code === 'delivered') ? 'completed' : 'shipped';
        $shipmentStatus = $orderStatus === 'completed' ? 'completed' : 'shipped';
        $this->conn->prepare("
            UPDATE shipments
            SET status = ?
            WHERE order_id = ?
        ")->execute([$shipmentStatus, $orderId]);

        $this->conn->prepare("
            UPDATE orders
            SET shipping_status = ?,
                export_status = CASE WHEN COALESCE(delivery_scope, 'domestic') = 'international' THEN ? ELSE 'not_required' END
            WHERE id = ?
        ")->execute([$shipmentStatus, $shipmentStatus, $orderId]);

        OrderWorkflow::recordEvent($this->conn, $orderId, $this->currentActorId(), 'shipment_step_completed', null, $orderStatus, (string)$next->title, (string)($next->description ?? ''), [
            'status_code' => (string)$next->status_code,
            'location' => (string)($next->location ?? ''),
            'shipment_status' => $shipmentStatus,
        ]);

        return [
            'success' => true,
            'order_status' => $orderStatus,
            'completed_step' => (string)$next->status_code,
        ];
    }

    public function saveShipment(array $data): array
    {
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Don hang khong hop le.'];
        }

        $orderStmt = $this->conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_OBJ);
        if (!$order) {
            return ['success' => false, 'message' => 'Khong tim thay don hang.'];
        }

        $orderStatus = (string)($order->status ?? 'pending');
        if (!in_array($orderStatus, ['packed', 'shipped', 'completed'], true)) {
            return ['success' => false, 'message' => 'Chi duoc tao/cap nhat van chuyen sau khi don da xuat kho va dong goi.'];
        }

        $country = trim($data['export_country'] ?? '');
        $carrier = trim($data['carrier'] ?? '');
        $tracking = trim($data['tracking_code'] ?? '');
        $method = trim($data['transport_method'] ?? '');
        $status = trim($data['status'] ?? 'packed');
        $notes = trim($data['notes'] ?? '');
        $departure = trim((string)($data['departure_date'] ?? '')) ?: null;
        $arrival = trim((string)($data['arrival_date'] ?? '')) ?: null;

        if (!in_array($status, ['packed', 'shipped', 'completed'], true)) {
            $status = 'packed';
        }
        if ($orderStatus === 'completed' && $status !== 'completed') {
            return ['success' => false, 'message' => 'Don da hoan tat, khong duoc ha trang thai van chuyen.'];
        }
        if ($orderStatus === 'shipped' && $status === 'packed') {
            $status = 'shipped';
        }
        if (in_array($status, ['shipped', 'completed'], true) && ($carrier === '' || $method === '')) {
            return ['success' => false, 'message' => 'Can co don vi van chuyen va dich vu truoc khi giao hang.'];
        }
        if ($status === 'completed' && $orderStatus !== 'shipped' && $orderStatus !== 'completed') {
            return ['success' => false, 'message' => 'Can giao hang truoc khi hoan tat.'];
        }

        $processStatus = $this->shippingStatusForOrderStatus($orderStatus);
        $shipmentPersistStatus = $orderStatus === $status ? $status : $processStatus;

        $stmt = $this->conn->prepare("SELECT id FROM shipments WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $shipmentId = (int)$stmt->fetchColumn();

        if ($shipmentId > 0) {
            $stmt = $this->conn->prepare("
                UPDATE shipments
                SET export_country = ?, carrier = ?, tracking_code = ?, transport_method = ?,
                    departure_date = ?, arrival_date = ?, status = ?, notes = ?
                WHERE order_id = ?
            ");
            $stmt->execute([$country, $carrier, $tracking, $method, $departure, $arrival, $shipmentPersistStatus, $notes, $orderId]);
        } else {
            $stmt = $this->conn->prepare("
                INSERT INTO shipments (order_id, export_country, carrier, tracking_code, transport_method, departure_date, arrival_date, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$orderId, $country, $carrier, $tracking, $method, $departure, $arrival, $shipmentPersistStatus, $notes]);
        }

        $stmt = $this->conn->prepare("
            UPDATE orders
            SET export_country = CASE WHEN COALESCE(delivery_scope, 'domestic') = 'international' THEN ? ELSE export_country END,
                export_status = CASE WHEN COALESCE(delivery_scope, 'domestic') = 'international' THEN ? ELSE 'not_required' END,
                shipping_status = ?, tracking_code = ?,
                transport_method = ?, export_note = ?
            WHERE id = ?
        ");
        $stmt->execute([$country, $processStatus, $processStatus, $tracking, $method, $notes, $orderId]);

        if (in_array($shipmentPersistStatus, ['packed', 'shipped', 'completed'], true)) {
            $this->ensureShipmentTimeline($orderId);
            $this->syncTimelineByStatus($orderId, $shipmentPersistStatus);
        }

        OrderWorkflow::recordEvent($this->conn, $orderId, $this->currentActorId(), 'shipment_updated', null, $shipmentPersistStatus, 'Cap nhat van chuyen', $notes, [
            'carrier' => $carrier,
            'tracking_code' => $tracking,
            'transport_method' => $method,
            'export_country' => $country,
            'departure_date' => $departure,
            'arrival_date' => $arrival,
            'requested_status' => $status,
        ]);

        return ['success' => true, 'message' => 'Da cap nhat van chuyen.', 'status' => $status];
    }

    public function syncOrderProcessStatus(int $orderId, string $status): void
    {
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT o.*, s.id AS shipment_id
            FROM orders o
            LEFT JOIN shipments s ON s.order_id = o.id
            WHERE o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$order) {
            return;
        }

        $isInternational = (string)($order->delivery_scope ?? 'domestic') === 'international';
        $shippingStatus = $this->shippingStatusForOrderStatus($status);
        $exportStatus = $isInternational ? $shippingStatus : 'not_required';
        $this->conn->prepare("
            UPDATE orders
            SET shipping_status = ?, export_status = ?
            WHERE id = ?
        ")->execute([$shippingStatus, $exportStatus, $orderId]);

        $shouldHaveShipment = in_array($shippingStatus, ['packed', 'shipped', 'completed', 'cancelled'], true);
        if (!$shouldHaveShipment) {
            return;
        }

        if (!empty($order->shipment_id)) {
            $this->conn->prepare("
                UPDATE shipments
                SET status = ?
                WHERE order_id = ?
            ")->execute([$shippingStatus, $orderId]);
            if (in_array($shippingStatus, ['packed', 'shipped', 'completed'], true)) {
                $this->ensureShipmentTimeline($orderId);
                $this->syncTimelineByStatus($orderId, $shippingStatus);
            }
            return;
        }

        if ($status === 'cancelled' || in_array($shippingStatus, ['shipped', 'completed'], true)) {
            return;
        }

        $country = trim((string)($order->export_country ?: ($order->recipient_country ?? '')));
        $carrier = trim((string)($order->shipping_carrier ?? ''));
        $method = trim((string)(($order->transport_method ?? '') ?: ($order->shipping_service ?? '')));
        $tracking = trim((string)($order->tracking_code ?? ''));
        $notes = $isInternational ? 'Tao tu tien do don hang xuat khau' : 'Tao tu tien do don hang noi dia';

        $this->conn->prepare("
            INSERT INTO shipments (order_id, export_country, carrier, tracking_code, transport_method, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$orderId, $country, $carrier, $tracking, $method, $shippingStatus, $notes]);

        $this->ensureShipmentTimeline($orderId);
        $this->syncTimelineByStatus($orderId, $shippingStatus);
    }

    private function shippingStatusForOrderStatus(string $status): string
    {
        return match ($status) {
            'preparing' => 'preparing',
            'packed' => 'packed',
            'shipped' => 'shipped',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function ensureShipmentTimeline(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT o.*, s.export_country AS shipment_country, s.carrier, s.transport_method AS shipment_method
            FROM orders o
            LEFT JOIN shipments s ON s.order_id = o.id
            WHERE o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$order) {
            return;
        }

        $status = (string)($order->shipment_status ?? $order->shipping_status ?? $order->status ?? 'pending');
        $country = trim((string)($order->shipment_country ?? $order->export_country ?? $order->recipient_country ?? ''));
        $normalizedCountry = function_exists('mb_strtolower')
            ? mb_strtolower($country, 'UTF-8')
            : strtolower($country);
        $isVietnamDestination = $country === '' || in_array($normalizedCountry, ['việt nam', 'viet nam', 'vietnam'], true);
        $isInternational = !$isVietnamDestination && (
            ($order->delivery_scope ?? '') === 'international'
            || trim((string)($order->export_country ?? '')) !== ''
            || trim((string)($order->shipment_country ?? '')) !== ''
        );
        $destinationParts = array_filter([
            (string)($order->recipient_ward ?? ''),
            (string)($order->recipient_district ?? ''),
            (string)($order->recipient_city ?? ''),
            $country,
        ]);
        $destination = $destinationParts ? implode(', ', $destinationParts) : 'Điểm giao hàng';
        $carrier = trim((string)($order->carrier ?: ($order->shipping_carrier ?? ($isInternational ? 'Đơn vị vận chuyển quốc tế' : 'Đơn vị vận chuyển nội địa'))));
        $method = trim((string)($order->shipment_method ?: ($order->transport_method ?? ($order->shipping_service ?? ($isInternational ? 'International Priority' : 'Giao nội địa')))));
        if (!$isInternational && preg_match('/DHL|FedEx|UPS|Maersk|International/i', $carrier . ' ' . $method)) {
            $carrier = trim((string)($order->shipping_carrier ?? '')) ?: 'Đơn vị vận chuyển nội địa';
            $method = trim((string)($order->shipping_service ?? '')) ?: 'Giao nội địa';
        }
        $events = $this->buildShipmentRouteEvents($isInternational, $destination, $carrier, $method);

        $expectedCodes = array_map(fn($event) => $event[0], $events);
        $existing = $this->getShipmentTimelineRows($orderId);
        $existingCodes = array_map(fn($event) => (string)$event->status_code, $existing);
        $missingTemplateCodes = !empty(array_diff($expectedCodes, $existingCodes));
        $completedSort = 0;
        foreach ($existing as $event) {
            if ((int)($event->is_completed ?? 0) === 1) {
                $completedSort = max($completedSort, (int)($event->sort_order ?? 0));
            }
        }

        if ($missingTemplateCodes && !empty($existing)) {
            $this->conn->prepare("DELETE FROM shipment_tracking_events WHERE order_id = ?")->execute([$orderId]);
            $existing = [];
        }

        $insert = $this->conn->prepare("
            INSERT INTO shipment_tracking_events (order_id, status_code, title, description, location, sort_order, is_completed, event_time)
            VALUES (?, ?, ?, ?, ?, ?, 0, NULL)
        ");
        $exists = $this->conn->prepare("
            SELECT COUNT(*)
            FROM shipment_tracking_events
            WHERE order_id = ? AND status_code = ?
        ");

        foreach ($events as $event) {
            $exists->execute([$orderId, $event[0]]);
            if ((int)$exists->fetchColumn() > 0) {
                continue;
            }
            $insert->execute([$orderId, $event[0], $event[1], $event[2], $event[3], $event[4]]);
        }

        $statusSort = $this->shipmentCompletedSortForStatus($status);
        $maxSort = max($completedSort, $statusSort);
        if ($maxSort > 0) {
            $this->markTimelineCompletedToSort($orderId, $maxSort);
        }
    }

    private function buildShipmentRouteEvents(bool $isInternational, string $destination, string $carrier, string $method): array
    {
        if ($isInternational) {
            return [
                ['ready_for_handoff', 'Sẵn sàng bàn giao vận chuyển', 'Kho đã xuất hàng, đối chiếu lô và chuẩn bị hồ sơ xuất khẩu.', 'Kho Aethera', 10],
                ['handoff_carrier', 'Bàn giao cho đơn vị vận chuyển', 'Đơn được bàn giao cho ' . $carrier . ' bằng dịch vụ ' . $method . '.', $carrier, 20],
                ['export_customs', 'Làm thủ tục xuất khẩu', 'Hồ sơ xuất khẩu được kiểm tra tại điểm đi trước khi rời Việt Nam.', 'Cảng/Sân bay xuất', 30],
                ['international_transit', 'Đang vận chuyển quốc tế', 'Đơn đang di chuyển trên tuyến quốc tế.', 'Tuyến vận chuyển quốc tế', 40],
                ['import_customs', 'Làm thủ tục nhập khẩu', 'Đơn đến nước nhận và chờ thông quan nhập khẩu.', $destination, 50],
                ['last_mile', 'Giao chặng cuối', 'Đối tác địa phương đang giao đến địa chỉ nhận.', $destination, 60],
                ['delivered', 'Đã giao cho khách', 'Khách hàng hoặc đối tác xác nhận đã nhận đủ hàng.', $destination, 70],
            ];
        }

        return [
            ['ready_for_handoff', 'Sẵn sàng bàn giao vận chuyển', 'Kho đã xuất hàng, kiểm lô và đóng gói xong.', 'Kho Aethera', 10],
            ['handoff_carrier', 'Bàn giao cho đơn vị vận chuyển', 'Đơn được bàn giao cho ' . $carrier . ' bằng dịch vụ ' . $method . '.', $carrier, 20],
            ['domestic_sorting', 'Phân loại theo tuyến giao', 'Đơn được phân loại theo tuyến nội địa.', 'Trung tâm phân loại nội địa', 30],
            ['in_transit', 'Đang di chuyển', 'Đơn đang trên đường đến khu vực nhận.', 'Tuyến vận chuyển nội địa', 40],
            ['out_for_delivery', 'Đang giao đến khách', 'Nhân viên giao hàng đang đưa đơn đến địa chỉ nhận.', $destination, 50],
            ['delivered', 'Đã giao cho khách', 'Khách hàng xác nhận đã nhận đủ hàng.', $destination, 60],
        ];
    }

    private function getShipmentTimelineRows(int $orderId): array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM shipment_tracking_events
            WHERE order_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function shipmentCompletedSortForStatus(string $status): int
    {
        return match ($status) {
            'packed' => 10,
            'shipped' => 20,
            'completed' => 999,
            default => 0,
        };
    }

    private function markTimelineCompletedToSort(int $orderId, int $maxSort): void
    {
        $stmt = $this->conn->prepare("
            UPDATE shipment_tracking_events
            SET is_completed = 1, event_time = COALESCE(event_time, NOW())
            WHERE order_id = ? AND sort_order <= ?
        ");
        $stmt->execute([$orderId, $maxSort]);
    }

    private function syncTimelineByStatus(int $orderId, string $status): void
    {
        if ($orderId <= 0) {
            return;
        }

        $maxSort = $this->shipmentCompletedSortForStatus($status);

        if ($maxSort <= 0) {
            return;
        }

        $this->markTimelineCompletedToSort($orderId, $maxSort);
    }

    public function getCultivationLogs(int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        if (!$this->tableExists('cultivation_logs')) {
            return [];
        }

        return $this->conn->query("
            SELECT cl.*, s.name AS supplier_name,
                   COALESCE(p.name, cl.product_name_snapshot, b.product_name_snapshot, 'Sản phẩm đã xóa') AS product_name,
                   b.batch_code
            FROM cultivation_logs cl
            LEFT JOIN supplier s ON s.id = cl.supplier_id
            LEFT JOIN product p ON p.id = cl.product_id
            LEFT JOIN product_batch b ON b.id = cl.batch_id
            ORDER BY COALESCE(cl.activity_date, DATE(cl.created_at)) DESC, cl.id DESC
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function saveCultivationLog(array $data): array
    {
        if (!$this->tableExists('cultivation_logs')) {
            $this->createCoreTables();
        }

        $batchId = (int)($data['batch_id'] ?? 0);
        $supplierId = (int)($data['supplier_id'] ?? 0);
        $productId = (int)($data['product_id'] ?? 0);
        $batch = null;

        if ($batchId > 0) {
            $stmt = $this->conn->prepare("
                SELECT b.product_id, b.supplier_id, COALESCE(p.name, b.product_name_snapshot) AS product_name
                FROM product_batch b
                LEFT JOIN product p ON p.id = b.product_id
                WHERE b.id = ?
                LIMIT 1
            ");
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch(PDO::FETCH_OBJ);
            if ($batch) {
                $productId = $productId > 0 ? $productId : (int)$batch->product_id;
                $supplierId = $supplierId > 0 ? $supplierId : (int)$batch->supplier_id;
            }
        }

        $activityType = trim((string)($data['activity_type'] ?? 'care'));
        $allowed = ['planting', 'care', 'harvest', 'inspection'];
        if (!in_array($activityType, $allowed, true)) {
            $activityType = 'care';
        }

        $activityDate = trim((string)($data['activity_date'] ?? ''));
        if ($activityDate === '') {
            $activityDate = date('Y-m-d');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO cultivation_logs (
                supplier_id, product_id, batch_id, activity_type,
                activity_date, quantity_kg, area, notes, product_name_snapshot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $supplierId > 0 ? $supplierId : null,
            $productId > 0 ? $productId : null,
            $batchId > 0 ? $batchId : null,
            $activityType,
            $activityDate,
            max(0, (float)($data['quantity_kg'] ?? 0)),
            max(0, (float)($data['area'] ?? 0)),
            trim((string)($data['notes'] ?? '')),
            trim((string)($batch->product_name ?? '')) ?: null,
        ]);

        if ($batchId > 0) {
            if ($activityType === 'planting') {
                $this->conn->prepare("UPDATE product_batch SET planting_date = COALESCE(planting_date, ?) WHERE id = ?")
                    ->execute([$activityDate, $batchId]);
            }
            if ($activityType === 'harvest') {
                $this->conn->prepare("UPDATE product_batch SET harvest_date = ? WHERE id = ?")
                    ->execute([$activityDate, $batchId]);
            }
            $this->upsertTraceabilityForBatch($batchId);
        }

        return ['success' => true, 'message' => 'Da ghi nhan nhat ky canh tac/thu hoach.'];
    }

    public function getStorageMonitoring(int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        if (!$this->tableExists('storage_monitoring')) {
            return [];
        }

        return $this->conn->query("
            SELECT sm.*, b.batch_code, COALESCE(p.name, b.product_name_snapshot, 'Sản phẩm đã xóa') AS product_name, w.name AS warehouse_name
            FROM storage_monitoring sm
            LEFT JOIN product_batch b ON b.id = sm.batch_id
            LEFT JOIN product p ON p.id = b.product_id
            LEFT JOIN warehouse w ON w.id = sm.warehouse_id
            ORDER BY sm.checked_at DESC, sm.id DESC
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function saveStorageMonitoring(array $data): array
    {
        $batchId = (int)($data['batch_id'] ?? 0);
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        if ($batchId > 0 && $warehouseId <= 0) {
            $stmt = $this->conn->prepare("SELECT warehouse_id FROM product_batch WHERE id = ? LIMIT 1");
            $stmt->execute([$batchId]);
            $warehouseId = (int)$stmt->fetchColumn();
        }
        if ($batchId <= 0 || $warehouseId <= 0) {
            return ['success' => false, 'message' => 'Can chon lo hang va kho luu tru.'];
        }

        $temperature = ($data['temperature'] ?? '') !== '' ? (float)$data['temperature'] : null;
        $humidity = ($data['humidity'] ?? '') !== '' ? (float)$data['humidity'] : null;
        if ($temperature === null || $humidity === null || $humidity < 0 || $humidity > 100) {
            return ['success' => false, 'message' => 'Nhiet do/do am bao quan khong hop le.'];
        }

        $checkedAt = str_replace('T', ' ', trim((string)($data['checked_at'] ?? '')));
        if ($checkedAt !== '' && strlen($checkedAt) === 16) {
            $checkedAt .= ':00';
        }

        $stmt = $this->conn->prepare("
            INSERT INTO storage_monitoring (
                batch_id, warehouse_id, temperature, humidity, method, checked_at, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $batchId,
            $warehouseId,
            $temperature,
            $humidity,
            trim((string)($data['method'] ?? '')),
            $checkedAt ?: date('Y-m-d H:i:s'),
            trim((string)($data['notes'] ?? '')),
        ]);

        $this->conn->prepare("
            UPDATE product_batch
            SET storage_temperature = ?,
                storage_humidity = ?,
                preservation_method = COALESCE(NULLIF(?, ''), preservation_method)
            WHERE id = ?
        ")->execute([$temperature, $humidity, trim((string)($data['method'] ?? '')), $batchId]);

        $this->upsertTraceabilityForBatch($batchId);

        return ['success' => true, 'message' => 'Da ghi nhan kiem tra bao quan kho.'];
    }

    public function getCancelledOrders(): array
    {
        return $this->conn->query("
            SELECT o.*, COALESCE(o.fullname, a.fullname, 'Khach hang') AS customer_name,
                   COALESCE(o.email, a.email, '') AS customer_email,
                   COALESCE(o.phone, a.phone, '') AS customer_phone
            FROM orders o
            LEFT JOIN account a ON a.id = o.user_id
            WHERE o.status = 'cancelled'
            ORDER BY COALESCE(o.cancelled_at, o.created_at) DESC, o.id DESC
            LIMIT 120
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    private function backfillTraceabilityFromBatches(): void
    {
        if (!$this->tableExists('product_traceability') || !$this->tableExists('product_batch')) {
            return;
        }

        $ids = $this->conn->query("
            SELECT b.id
            FROM product_batch b
            LEFT JOIN product_traceability pt ON pt.batch_id = b.id
            WHERE pt.id IS NULL
            ORDER BY b.id DESC
            LIMIT 300
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->upsertTraceabilityForBatch((int)$id);
        }
    }

    private function upsertTraceabilityForBatch(int $batchId): void
    {
        if ($batchId <= 0 || !$this->tableExists('product_traceability')) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT b.*, p.origin, p.standard, p.warehouse_address, s.name AS supplier_name,
                   s.farm_location, s.address AS supplier_address, w.name AS warehouse_name,
                   w.location AS warehouse_location
            FROM product_batch b
            LEFT JOIN product p ON p.id = b.product_id
            LEFT JOIN supplier s ON s.id = b.supplier_id
            LEFT JOIN warehouse w ON w.id = b.warehouse_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$batch || (int)($batch->product_id ?? 0) <= 0) {
            return;
        }

        $farmName = trim((string)($batch->supplier_name ?? ''));
        if ($farmName === '') {
            $farmName = trim((string)($batch->origin ?? ''));
        }
        $origin = trim((string)($batch->farm_location ?? ''));
        if ($origin === '') {
            $origin = trim((string)($batch->supplier_address ?? $batch->origin ?? ''));
        }
        $storageParts = array_filter([
            trim((string)($batch->warehouse_name ?? '')),
            trim((string)($batch->warehouse_location ?? $batch->warehouse_address ?? '')),
            trim((string)($batch->preservation_method ?? '')),
            ($batch->storage_temperature ?? '') !== '' ? ((float)$batch->storage_temperature . 'C') : '',
            ($batch->storage_humidity ?? '') !== '' ? ((float)$batch->storage_humidity . '% RH') : '',
        ]);
        $transportInfo = 'Cho phep truy xuat theo ma QR ' . (string)($batch->qr_code ?? '');
        $certification = trim((string)($batch->quality_standard ?? $batch->standard ?? ''));

        $existing = $this->conn->prepare("SELECT id FROM product_traceability WHERE batch_id = ? LIMIT 1");
        $existing->execute([$batchId]);
        $traceId = (int)$existing->fetchColumn();

        if ($traceId > 0) {
            $update = $this->conn->prepare("
                UPDATE product_traceability
                SET product_id = ?, farm_name = ?, harvest_date = ?, certification = ?,
                    origin_location = ?, packaging_date = ?, storage_info = ?, transport_info = ?
                WHERE id = ?
            ");
            $update->execute([
                (int)$batch->product_id,
                $farmName,
                $batch->harvest_date ?: null,
                $certification,
                $origin,
                $batch->created_at ? date('Y-m-d', strtotime((string)$batch->created_at)) : null,
                implode(' | ', $storageParts),
                $transportInfo,
                $traceId,
            ]);
            return;
        }

        $insert = $this->conn->prepare("
            INSERT INTO product_traceability (
                product_id, batch_id, farm_name, harvest_date, certification,
                origin_location, packaging_date, storage_info, transport_info
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            (int)$batch->product_id,
            $batchId,
            $farmName,
            $batch->harvest_date ?: null,
            $certification,
            $origin,
            $batch->created_at ? date('Y-m-d', strtotime((string)$batch->created_at)) : null,
            implode(' | ', $storageParts),
            $transportInfo,
        ]);
    }

    public function getSupplyReports(?string $fromDate = null, ?string $toDate = null, string $statusFilter = '', string $scopeFilter = ''): array
    {
        $one = function (string $sql, array $params = []): float {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return (float)($stmt->fetchColumn() ?: 0);
        };

        $orderWhere = [];
        $orderParams = [];
        if ($fromDate && $toDate) {
            $orderWhere[] = "DATE(o.created_at) BETWEEN ? AND ?";
            $orderParams[] = $fromDate;
            $orderParams[] = $toDate;
        }
        if ($statusFilter !== '') {
            $orderWhere[] = "o.status = ?";
            $orderParams[] = $statusFilter;
        }
        if ($scopeFilter !== '') {
            $orderWhere[] = "COALESCE(o.delivery_scope, 'domestic') = ?";
            $orderParams[] = $scopeFilter;
        }
        $orderWhereSql = $orderWhere ? ("WHERE " . implode(" AND ", $orderWhere)) : "";

        $supplierWhere = ["COALESCE(status, 'active') = 'active'"];
        $supplierParams = [];
        if ($fromDate && $toDate) {
            $supplierWhere[] = "DATE(created_at) BETWEEN ? AND ?";
            $supplierParams[] = $fromDate;
            $supplierParams[] = $toDate;
        }

        $batchWhere = [];
        $batchParams = [];
        if ($fromDate && $toDate) {
            $batchWhere[] = "DATE(COALESCE(harvest_date, created_at)) BETWEEN ? AND ?";
            $batchParams[] = $fromDate;
            $batchParams[] = $toDate;
        }
        $batchWhereSql = $batchWhere ? ("WHERE " . implode(" AND ", $batchWhere)) : "";

        $harvestWhere = ["harvest_date IS NOT NULL"];
        $harvestParams = [];
        if ($fromDate && $toDate) {
            $harvestWhere[] = "DATE(harvest_date) BETWEEN ? AND ?";
            $harvestParams[] = $fromDate;
            $harvestParams[] = $toDate;
        }

        $countryStmt = $this->conn->prepare("
            SELECT
                CASE
                    WHEN COALESCE(o.delivery_scope, 'domestic') = 'international'
                        THEN COALESCE(NULLIF(o.recipient_country, ''), NULLIF(o.export_country, ''), 'Quốc tế')
                    ELSE 'Nội địa'
                END AS name,
                COUNT(*) AS total
            FROM orders o
            {$orderWhereSql}
            GROUP BY name
            ORDER BY total DESC, name ASC
            LIMIT 6
        ");
        $countryStmt->execute($orderParams);

        $harvestStmt = $this->conn->prepare("
            SELECT DATE_FORMAT(harvest_date, '%m/%Y') AS label, COALESCE(SUM(quantity), 0) AS total
            FROM product_batch
            WHERE " . implode(" AND ", $harvestWhere) . "
            GROUP BY DATE_FORMAT(harvest_date, '%m/%Y')
            ORDER BY MIN(harvest_date) ASC
            LIMIT 8
        ");
        $harvestStmt->execute($harvestParams);

        return [
            'suppliers' => $one("SELECT COUNT(*) FROM supplier WHERE " . implode(" AND ", $supplierWhere), $supplierParams),
            'warehouses' => $one("SELECT COUNT(*) FROM warehouse"),
            'batches' => $one("SELECT COUNT(*) FROM product_batch {$batchWhereSql}", $batchParams),
            'inventory_quantity' => $one("SELECT COALESCE(SUM(quantity), 0) FROM inventory"),
            'export_orders' => $one(
                "SELECT COUNT(*) FROM orders o {$orderWhereSql}" . ($orderWhereSql ? " AND " : " WHERE ") . "COALESCE(o.delivery_scope, 'domestic') = 'international'",
                $orderParams
            ),
            'countries' => $countryStmt->fetchAll(PDO::FETCH_ASSOC),
            'harvests' => $harvestStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
