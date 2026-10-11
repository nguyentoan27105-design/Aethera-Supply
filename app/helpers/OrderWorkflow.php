<?php

if (!class_exists('OrderWorkflow')) {
    class OrderWorkflow
    {
        public const STATUSES = ['pending', 'confirmed', 'preparing', 'packed', 'shipped', 'completed', 'cancelled'];
        public const PAYMENT_PAID = ['paid', 'captured', 'settled', 'success', 'completed'];

        public static function ensureSchema(PDO $conn): void
        {
            if (!self::tableExists($conn, 'order_events')) {
                self::createOrderEventsTable($conn);
            }
            self::ensureOrderColumns($conn);
            self::ensureOrderDetailColumns($conn);
            self::ensureInventoryLogColumns($conn);
            self::ensureShipmentTables($conn);
            self::ensureIndexes($conn);
            self::normalizeLegacyOrders($conn);
            self::seedLegacyEvents($conn);
        }

        public static function recordEvent(
            PDO $conn,
            int $orderId,
            ?int $actorId,
            string $eventType,
            ?string $fromStatus,
            ?string $toStatus,
            string $title,
            string $note = '',
            array $metadata = []
        ): void {
            if ($orderId <= 0) {
                return;
            }

            if (!self::tableExists($conn, 'order_events')) {
                self::createOrderEventsTable($conn);
            }
            $metadataJson = empty($metadata)
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt = $conn->prepare("
                INSERT INTO order_events (
                    order_id, actor_id, event_type, from_status, to_status, title, note, metadata_json, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $orderId,
                $actorId,
                $eventType,
                $fromStatus,
                $toStatus,
                $title,
                $note,
                $metadataJson,
            ]);
        }

        public static function syncOrderStatus(PDO $conn, int $orderId, string $status, ?string $fromStatus = null, ?int $actorId = null, string $note = ''): void
        {
            if ($orderId <= 0 || !in_array($status, self::STATUSES, true)) {
                return;
            }

            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);
            if (!$order) {
                return;
            }

            $fromStatus ??= (string)($order->status ?? 'pending');
            $deliveryScope = (string)($order->delivery_scope ?? 'domestic');
            $isInternational = $deliveryScope === 'international';
            $shippingStatus = self::shippingStatusForOrderStatus($status);

            $stockStatus = self::stockStatusForOrderStatus($status, (string)($order->stock_status ?? 'unchecked'));

            $sets = ['status = ?', 'shipping_status = ?'];
            $params = [$status, $shippingStatus];

            if (self::columnExists($conn, 'orders', 'stock_status')) {
                $sets[] = 'stock_status = ?';
                $params[] = $stockStatus;
            }

            if (self::columnExists($conn, 'orders', 'export_status')) {
                if ($isInternational) {
                    $sets[] = 'export_status = ?';
                    $params[] = $shippingStatus;
                } else {
                    $sets[] = "export_status = 'not_required'";
                }
            }

            if ($status === 'completed') {
                $sets[] = "payment_status = 'paid'";
            }

            if ($status === 'cancelled') {
                $paymentStatus = strtolower((string)($order->payment_status ?? 'pending'));
                $wasPaid = in_array($paymentStatus, array_merge(self::PAYMENT_PAID, ['refunded']), true);
                $sets[] = "cancelled_at = COALESCE(cancelled_at, NOW())";
                if ($wasPaid) {
                    $sets[] = "payment_status = 'refunded'";
                    $sets[] = "refund_status = 'refunded'";
                    $sets[] = "refund_amount = COALESCE(NULLIF(refund_amount, 0), total, 0)";
                    $sets[] = "refunded_at = COALESCE(refunded_at, NOW())";
                    $sets[] = "refund_note = COALESCE(NULLIF(refund_note, ''), 'Hoan tien do huy don')";
                } else {
                    $sets[] = "payment_status = 'cancelled'";
                    $sets[] = "refund_status = 'not_required'";
                    $sets[] = "refund_amount = 0";
                    $sets[] = "refund_note = COALESCE(NULLIF(refund_note, ''), 'Don chua thanh toan, khong can hoan tien')";
                }
            }

            $params[] = $orderId;
            $conn->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

            if (self::tableExists($conn, 'shipments') && in_array($status, ['shipped', 'completed', 'cancelled'], true)) {
                $conn->prepare("UPDATE shipments SET status = ? WHERE order_id = ?")->execute([$shippingStatus, $orderId]);
            }

            $eventType = match ($status) {
                'completed' => 'order_completed',
                'cancelled' => 'order_cancelled',
                'shipped' => 'shipment_created',
                default => 'order_status_changed',
            };

            $title = match ($status) {
                'completed' => 'Don hang hoan tat',
                'cancelled' => 'Don hang da huy',
                'shipped' => 'Don hang dang van chuyen',
                default => 'Cap nhat trang thai don hang',
            };

            self::recordEvent($conn, $orderId, $actorId, $eventType, $fromStatus, $status, $title, $note, [
                'shipping_status' => $shippingStatus,
                'delivery_scope' => $deliveryScope,
            ]);

            if ($status === 'cancelled') {
                $latest = self::getOrder($conn, $orderId);
                if (strtolower((string)($latest->payment_status ?? '')) === 'refunded') {
                    self::recordEvent($conn, $orderId, $actorId, 'refund_recorded', $fromStatus, $status, 'Da ghi nhan hoan tien', $note, [
                        'refund_amount' => (float)($latest->refund_amount ?? 0),
                    ]);
                }
            }
        }

        public static function recordInventoryEvent(PDO $conn, int $orderId, string $type, int $quantity, array $metadata = []): void
        {
            if ($orderId <= 0 || $quantity <= 0) {
                return;
            }

            $eventType = $type === 'import' ? 'inventory_returned' : 'inventory_exported';
            $title = $type === 'import' ? 'Hoan hang ve kho' : 'Xuat kho theo don hang';
            self::recordEvent($conn, $orderId, null, $eventType, null, null, $title, '', array_merge($metadata, [
                'quantity' => $quantity,
                'type' => $type,
            ]));
        }

        public static function getOrderEvents(PDO $conn, int $orderId): array
        {
            if ($orderId <= 0 || !self::tableExists($conn, 'order_events')) {
                return [];
            }

            $stmt = $conn->prepare("
                SELECT e.*, a.fullname AS actor_name, a.role AS actor_role
                FROM order_events e
                LEFT JOIN account a ON a.id = e.actor_id
                WHERE e.order_id = ?
                ORDER BY e.created_at ASC, e.id ASC
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        }

        private static function createOrderEventsTable(PDO $conn): void
        {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS order_events (
                    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    actor_id INT DEFAULT NULL,
                    event_type VARCHAR(60) NOT NULL,
                    from_status VARCHAR(40) DEFAULT NULL,
                    to_status VARCHAR(40) DEFAULT NULL,
                    title VARCHAR(180) NOT NULL,
                    note TEXT DEFAULT NULL,
                    metadata_json JSON DEFAULT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY order_id (order_id),
                    KEY event_type (event_type),
                    KEY actor_id (actor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        private static function ensureOrderColumns(PDO $conn): void
        {
            $columns = [
                'delivery_scope' => "VARCHAR(30) DEFAULT 'domestic'",
                'payment_method' => "VARCHAR(80) DEFAULT NULL",
                'payment_brand' => "VARCHAR(40) DEFAULT NULL",
                'payment_reference' => "VARCHAR(120) DEFAULT NULL",
                'payment_status' => "VARCHAR(40) DEFAULT 'pending'",
                'stock_status' => "VARCHAR(40) DEFAULT 'unchecked'",
                'subtotal_amount' => "DECIMAL(12,2) DEFAULT 0",
                'discount_code' => "VARCHAR(80) DEFAULT NULL",
                'discount_percent' => "DECIMAL(5,2) DEFAULT 0",
                'discount_amount' => "DECIMAL(12,2) DEFAULT 0",
                'refund_status' => "VARCHAR(40) DEFAULT 'not_required'",
                'refund_amount' => "DECIMAL(12,2) DEFAULT 0",
                'refund_note' => "VARCHAR(255) DEFAULT NULL",
                'cancelled_at' => "DATETIME DEFAULT NULL",
                'refunded_at' => "DATETIME DEFAULT NULL",
                'recipient_country' => "VARCHAR(120) DEFAULT NULL",
                'recipient_region' => "VARCHAR(120) DEFAULT NULL",
                'recipient_city' => "VARCHAR(120) DEFAULT NULL",
                'recipient_district' => "VARCHAR(120) DEFAULT NULL",
                'recipient_ward' => "VARCHAR(120) DEFAULT NULL",
                'postal_code' => "VARCHAR(30) DEFAULT NULL",
                'address_detail' => "TEXT DEFAULT NULL",
                'shipping_carrier' => "VARCHAR(150) DEFAULT NULL",
                'shipping_service' => "VARCHAR(120) DEFAULT NULL",
                'shipping_incoterm' => "VARCHAR(20) DEFAULT NULL",
                'shipping_fee' => "DECIMAL(12,2) DEFAULT 0",
                'currency' => "VARCHAR(10) DEFAULT 'VND'",
                'export_country' => "VARCHAR(120) DEFAULT NULL",
                'export_status' => "VARCHAR(40) DEFAULT 'pending'",
                'shipping_status' => "VARCHAR(40) DEFAULT 'pending'",
                'tracking_code' => "VARCHAR(120) DEFAULT NULL",
                'transport_method' => "VARCHAR(80) DEFAULT NULL",
                'port_of_loading' => "VARCHAR(120) DEFAULT NULL",
                'port_of_destination' => "VARCHAR(120) DEFAULT NULL",
                'export_note' => "TEXT DEFAULT NULL",
            ];

            foreach ($columns as $column => $definition) {
                self::addColumn($conn, 'orders', $column, $definition);
            }

            try {
                $conn->exec("
                    ALTER TABLE orders
                    MODIFY status ENUM('pending','confirmed','preparing','packed','shipped','completed','cancelled')
                    DEFAULT 'pending'
                ");
            } catch (Throwable $e) {
                error_log('Cannot normalize orders.status enum: ' . $e->getMessage());
            }
        }

        private static function ensureOrderDetailColumns(PDO $conn): void
        {
            if (!self::tableExists($conn, 'order_details')) {
                return;
            }

            self::addColumn($conn, 'order_details', 'batch_id', "INT DEFAULT NULL");
            self::addColumn($conn, 'order_details', 'warehouse_id', "INT DEFAULT NULL");
        }

        private static function ensureInventoryLogColumns(PDO $conn): void
        {
            if (!self::tableExists($conn, 'inventory_log')) {
                return;
            }

            self::addColumn($conn, 'inventory_log', 'order_id', "INT DEFAULT NULL");
            self::addColumn($conn, 'inventory_log', 'order_detail_id', "INT DEFAULT NULL");
            self::addColumn($conn, 'inventory_log', 'note', "VARCHAR(255) DEFAULT NULL");
        }

        private static function ensureShipmentTables(PDO $conn): void
        {
            $conn->exec("
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

            $conn->exec("
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

        private static function normalizeLegacyOrders(PDO $conn): void
        {
            try {
                $conn->exec("UPDATE orders SET delivery_scope = 'domestic' WHERE delivery_scope IS NULL OR delivery_scope = ''");
                $conn->exec("UPDATE orders SET payment_status = 'pending' WHERE payment_status IS NULL OR payment_status = ''");
                $conn->exec("UPDATE orders SET shipping_status = 'pending' WHERE shipping_status IS NULL OR shipping_status = ''");
                $conn->exec("UPDATE orders SET export_status = 'pending' WHERE export_status IS NULL OR export_status = ''");
                $conn->exec("UPDATE orders SET stock_status = 'unchecked' WHERE stock_status IS NULL OR stock_status = ''");
                $conn->exec("
                    UPDATE orders
                    SET delivery_scope = 'domestic', export_country = NULL
                    WHERE delivery_scope = 'international'
                      AND LOWER(COALESCE(NULLIF(export_country, ''), recipient_country, '')) IN ('viet nam', 'vietnam')
                ");
                $conn->exec("
                    UPDATE orders
                    SET export_status = 'not_required'
                    WHERE COALESCE(delivery_scope, 'domestic') <> 'international'
                ");
                $conn->exec("
                    UPDATE orders
                    SET payment_status = 'paid',
                        shipping_status = 'completed',
                        export_status = CASE WHEN delivery_scope = 'international' THEN 'completed' ELSE 'not_required' END,
                        stock_status = 'exported'
                    WHERE status = 'completed'
                      AND COALESCE(payment_status, 'pending') IN ('pending', 'awaiting_confirmation', '')
                ");
                $conn->exec("
                    UPDATE orders
                    SET stock_status = 'exported'
                    WHERE status IN ('packed', 'shipped', 'completed')
                ");
                $conn->exec("
                    UPDATE orders
                    SET shipping_status = CASE
                            WHEN status IN ('pending', 'confirmed') THEN 'pending'
                            WHEN status = 'preparing' THEN 'preparing'
                            WHEN status = 'packed' THEN 'packed'
                            WHEN status = 'shipped' THEN 'shipped'
                            WHEN status = 'completed' THEN 'completed'
                            WHEN status = 'cancelled' THEN 'cancelled'
                            ELSE COALESCE(shipping_status, 'pending')
                        END
                ");
                $conn->exec("
                    UPDATE orders
                    SET cancelled_at = COALESCE(cancelled_at, created_at, NOW()),
                        shipping_status = 'cancelled',
                        export_status = CASE WHEN delivery_scope = 'international' THEN 'cancelled' ELSE 'not_required' END,
                        stock_status = 'cancelled',
                        payment_status = CASE
                            WHEN payment_status IN ('paid', 'captured', 'settled', 'success', 'completed', 'refunded') THEN 'refunded'
                            ELSE 'cancelled'
                        END,
                        refund_status = CASE
                            WHEN payment_status IN ('paid', 'captured', 'settled', 'success', 'completed', 'refunded') THEN 'refunded'
                            ELSE 'not_required'
                        END,
                        refund_amount = CASE
                            WHEN payment_status IN ('paid', 'captured', 'settled', 'success', 'completed', 'refunded') THEN COALESCE(NULLIF(refund_amount, 0), total, 0)
                            ELSE 0
                        END,
                        refunded_at = CASE
                            WHEN payment_status IN ('paid', 'captured', 'settled', 'success', 'completed', 'refunded') THEN COALESCE(refunded_at, cancelled_at, NOW())
                            ELSE refunded_at
                        END
                    WHERE status = 'cancelled'
                ");
                $conn->exec("
                    UPDATE shipments s
                    JOIN orders o ON o.id = s.order_id
                    SET s.status = o.shipping_status
                    WHERE o.shipping_status IN ('pending', 'preparing', 'packed', 'shipped', 'completed', 'cancelled')
                ");
            } catch (Throwable $e) {
                error_log('Cannot normalize legacy order data: ' . $e->getMessage());
            }
        }

        private static function seedLegacyEvents(PDO $conn): void
        {
            try {
                $conn->exec("
                    INSERT INTO order_events (order_id, actor_id, event_type, from_status, to_status, title, note, metadata_json, created_at)
                    SELECT o.id, o.user_id, 'order_created', NULL, o.status, 'Don hang duoc tao', 'Du lieu khoi tao tu don hang hien co', NULL, COALESCE(o.created_at, NOW())
                    FROM orders o
                    WHERE NOT EXISTS (
                        SELECT 1 FROM order_events e WHERE e.order_id = o.id AND e.event_type = 'order_created'
                    )
                ");
                $conn->exec("
                    INSERT INTO order_events (order_id, actor_id, event_type, from_status, to_status, title, note, metadata_json, created_at)
                    SELECT o.id, NULL, 'payment_confirmed', NULL, o.status, 'Thanh toan da xac nhan', 'Dong bo tu payment_status cu', NULL, COALESCE(o.created_at, NOW())
                    FROM orders o
                    WHERE o.payment_status = 'paid'
                      AND NOT EXISTS (
                          SELECT 1 FROM order_events e WHERE e.order_id = o.id AND e.event_type = 'payment_confirmed'
                      )
                ");
                $conn->exec("
                    INSERT INTO order_events (order_id, actor_id, event_type, from_status, to_status, title, note, metadata_json, created_at)
                    SELECT o.id, NULL, 'order_cancelled', NULL, 'cancelled', 'Don hang da huy', 'Dong bo tu trang thai cu', NULL, COALESCE(o.cancelled_at, o.created_at, NOW())
                    FROM orders o
                    WHERE o.status = 'cancelled'
                      AND NOT EXISTS (
                          SELECT 1 FROM order_events e WHERE e.order_id = o.id AND e.event_type = 'order_cancelled'
                      )
                ");
                $conn->exec("
                    INSERT INTO order_events (order_id, actor_id, event_type, from_status, to_status, title, note, metadata_json, created_at)
                    SELECT o.id, NULL, 'order_completed', NULL, 'completed', 'Don hang hoan tat', 'Dong bo tu trang thai cu', NULL, COALESCE(o.created_at, NOW())
                    FROM orders o
                    WHERE o.status = 'completed'
                      AND NOT EXISTS (
                          SELECT 1 FROM order_events e WHERE e.order_id = o.id AND e.event_type = 'order_completed'
                      )
                ");
                if (self::tableExists($conn, 'inventory_log')) {
                    $conn->exec("
                        INSERT INTO order_events (order_id, actor_id, event_type, from_status, to_status, title, note, metadata_json, created_at)
                        SELECT l.order_id, NULL,
                               CASE WHEN l.type = 'import' THEN 'inventory_returned' ELSE 'inventory_exported' END,
                               NULL, NULL,
                               CASE WHEN l.type = 'import' THEN 'Hoan hang ve kho' ELSE 'Xuat kho theo don hang' END,
                               COALESCE(l.note, ''),
                               JSON_OBJECT('product_id', l.product_id, 'batch_id', l.batch_id, 'warehouse_id', l.warehouse_id, 'quantity', l.quantity),
                               COALESCE(l.created_at, NOW())
                        FROM inventory_log l
                        WHERE l.order_id IS NOT NULL
                          AND NOT EXISTS (
                              SELECT 1
                              FROM order_events e
                              WHERE e.order_id = l.order_id
                                AND e.event_type = CASE WHEN l.type = 'import' THEN 'inventory_returned' ELSE 'inventory_exported' END
                                AND e.created_at = l.created_at
                          )
                    ");
                }
            } catch (Throwable $e) {
                error_log('Cannot seed legacy order events: ' . $e->getMessage());
            }
        }

        private static function stockStatusForOrderStatus(string $status, string $current): string
        {
            return match ($status) {
                'packed', 'shipped', 'completed' => 'exported',
                'cancelled' => 'cancelled',
                default => in_array($current, ['sufficient', 'insufficient'], true) ? $current : 'unchecked',
            };
        }

        private static function shippingStatusForOrderStatus(string $status): string
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

        private static function getOrder(PDO $conn, int $orderId): ?object
        {
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);
            return $order ?: null;
        }

        private static function addColumn(PDO $conn, string $table, string $column, string $definition): void
        {
            if (!self::tableExists($conn, $table) || self::columnExists($conn, $table, $column)) {
                return;
            }

            try {
                $conn->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            } catch (Throwable $e) {
                error_log("Cannot add column {$table}.{$column}: " . $e->getMessage());
            }
        }

        private static function columnExists(PDO $conn, string $table, string $column): bool
        {
            $stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        }

        private static function tableExists(PDO $conn, string $table): bool
        {
            $stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        }

        private static function ensureIndexes(PDO $conn): void
        {
            self::addIndex($conn, 'orders', 'idx_orders_status', '`status`');
            self::addIndex($conn, 'orders', 'idx_orders_stock_status', '`stock_status`');
            self::addIndex($conn, 'orders', 'idx_orders_delivery_scope', '`delivery_scope`');
            self::addIndex($conn, 'order_details', 'idx_order_details_order_id', '`order_id`');
            self::addIndex($conn, 'order_details', 'idx_order_details_product_id', '`product_id`');
            self::addIndex($conn, 'order_details', 'idx_order_details_batch_id', '`batch_id`');
            self::addIndex($conn, 'inventory', 'idx_inventory_product_id', '`product_id`');
            self::addIndex($conn, 'inventory', 'idx_inventory_batch_id', '`batch_id`');
            self::addIndex($conn, 'inventory', 'idx_inventory_warehouse_id', '`warehouse_id`');
            self::addIndex($conn, 'inventory_log', 'idx_inventory_log_order_id', '`order_id`');
            self::addIndex($conn, 'inventory_log', 'idx_inventory_log_order_detail_id', '`order_detail_id`');
            self::addIndex($conn, 'shipments', 'idx_shipments_order_id', '`order_id`');
            self::addIndex($conn, 'shipment_tracking_events', 'idx_tracking_events_order_id', '`order_id`');
        }

        private static function addIndex(PDO $conn, string $table, string $index, string $columns): void
        {
            if (!self::tableExists($conn, $table) || self::indexExists($conn, $table, $index)) {
                return;
            }

            try {
                $conn->exec("ALTER TABLE `$table` ADD INDEX `$index` ($columns)");
            } catch (Throwable $e) {
                error_log("Cannot add index {$table}.{$index}: " . $e->getMessage());
            }
        }

        private static function indexExists(PDO $conn, string $table, string $index): bool
        {
            $stmt = $conn->prepare("
                SELECT COUNT(*)
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ");
            $stmt->execute([$table, $index]);
            return (int)$stmt->fetchColumn() > 0;
        }
    }
}
