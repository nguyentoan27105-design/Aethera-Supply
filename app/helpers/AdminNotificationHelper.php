<?php

if (!function_exists('adminShellNotificationConnection')) {
    function adminShellNotificationConnection(): ?PDO
    {
        if (!class_exists('Database')) {
            $databasePath = __DIR__ . '/../config/database.php';
            if (file_exists($databasePath)) {
                require_once $databasePath;
            }
        }

        if (!class_exists('Database')) {
            return null;
        }

        try {
            return (new Database())->getConnection();
        } catch (Throwable $e) {
            error_log('Cannot open admin notification database: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('adminShellOrderStatusLabel')) {
    function adminShellOrderStatusLabel(string $status): string
    {
        return [
            'pending' => 'Chờ duyệt',
            'confirmed' => 'Đã duyệt',
            'preparing' => 'Đang chuẩn bị',
            'packed' => 'Đã đóng gói',
            'shipped' => 'Đang giao',
            'processing' => 'Đang xử lý',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
        ][$status] ?? $status;
    }
}

if (!function_exists('adminShellPaymentStatusLabel')) {
    function adminShellPaymentStatusLabel(string $status): string
    {
        return [
            'paid' => 'Đã thanh toán',
            'partial' => 'Đã cọc',
            'awaiting_confirmation' => 'Chờ xác nhận CK',
            'pending' => 'Chưa thanh toán',
            'refunded' => 'Đã hoàn tiền',
            'cancelled' => 'Không thu tiền',
        ][$status] ?? $status;
    }
}

if (!function_exists('adminShellStockStatusLabel')) {
    function adminShellStockStatusLabel(string $status): string
    {
        return [
            'unchecked' => 'Chưa kiểm kho',
            'available' => 'Đủ kho',
            'sufficient' => 'Đủ hàng',
            'insufficient' => 'Thiếu hàng',
            'exported' => 'Đã xuất kho',
            'cancelled' => 'Hủy kho',
        ][$status] ?? $status;
    }
}

if (!function_exists('adminShellSupplierOrderNotificationMeta')) {
    function adminShellSupplierOrderNotificationMeta(string $status): array
    {
        return [
            'pending' => [
                'title' => 'Đơn nhập chờ nhà cung cấp gửi phiếu',
                'type' => 'info',
                'icon' => 'fa-file-circle-plus',
                'hint' => 'Nhà cung cấp cần gửi thông tin lô hàng.',
            ],
            'supplier_submitted' => [
                'title' => 'Phiếu lô chờ kho xác nhận',
                'type' => 'warning',
                'icon' => 'fa-truck-ramp-box',
                'hint' => 'Kho cần kiểm số lượng, chất lượng và giá nhập.',
            ],
            'warehouse_confirmed' => [
                'title' => 'Đơn nhập chờ thanh toán nhà cung cấp',
                'type' => 'warning',
                'icon' => 'fa-money-bill-transfer',
                'hint' => 'Hàng đã vào kho, cần ghi nhận thanh toán.',
            ],
            'completed' => [
                'title' => 'Đơn nhập hoàn tất kho, chờ thanh toán',
                'type' => 'warning',
                'icon' => 'fa-money-bill-transfer',
                'hint' => 'Kiểm tra số tiền còn phải trả nhà cung cấp.',
            ],
        ][$status] ?? [
            'title' => 'Đơn nhập nhà cung cấp cần xử lý',
            'type' => 'info',
            'icon' => 'fa-clipboard-list',
            'hint' => 'Mở màn hình nhập hàng để kiểm tra trạng thái.',
        ];
    }
}

if (!function_exists('adminShellOpenOrders')) {
    function adminShellOpenOrders(): array
    {
        $conn = adminShellNotificationConnection();
        if (!$conn) {
            return ['total' => 0, 'orders' => []];
        }

        try {
            $total = (int)$conn->query("
                SELECT COUNT(*)
                FROM orders
                WHERE COALESCE(status, 'pending') NOT IN ('completed', 'cancelled')
            ")->fetchColumn();

            $stmt = $conn->query("
                SELECT id, fullname, total, status, payment_status, stock_status,
                       delivery_scope, recipient_country, export_country, created_at
                FROM orders
                WHERE COALESCE(status, 'pending') NOT IN ('completed', 'cancelled')
                ORDER BY
                    CASE COALESCE(status, 'pending')
                        WHEN 'pending' THEN 0
                        WHEN 'confirmed' THEN 1
                        WHEN 'preparing' THEN 2
                        WHEN 'packed' THEN 3
                        WHEN 'shipped' THEN 4
                        ELSE 5
                    END,
                    created_at DESC,
                    id DESC
                LIMIT 8
            ");

            return [
                'total' => $total,
                'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
        } catch (Throwable $e) {
            error_log('Cannot load admin order notifications: ' . $e->getMessage());
            return ['total' => 0, 'orders' => []];
        }
    }
}

if (!function_exists('adminShellLowStockProducts')) {
    function adminShellLowStockProducts(): array
    {
        $conn = adminShellNotificationConnection();
        if (!$conn) {
            return [];
        }

        try {
            $stmt = $conn->query("
                SELECT id, name, stock
                FROM product
                WHERE stock <= 10
                ORDER BY stock ASC, name ASC
                LIMIT 5
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Cannot load low stock notifications: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('adminShellPendingBatches')) {
    function adminShellPendingBatches(): array
    {
        $conn = adminShellNotificationConnection();
        if (!$conn) {
            return [];
        }

        try {
            $stmt = $conn->query("
                SELECT
                    pb.id,
                    pb.batch_code,
                    COALESCE(p.name, so.requested_product_name, CONCAT('Sản phẩm #', pb.product_id)) AS product_name,
                    pb.quantity,
                    pb.remaining_quantity,
                    pb.status,
                    pb.created_at,
                    s.name AS supplier_name,
                    w.name AS warehouse_name
                FROM product_batch pb
                LEFT JOIN product p ON p.id = pb.product_id
                LEFT JOIN supplier_orders so ON so.id = pb.supplier_order_id
                LEFT JOIN supplier s ON s.id = pb.supplier_id
                LEFT JOIN warehouse w ON w.id = pb.warehouse_id
                WHERE pb.status IN ('supplier_submitted', 'pending', 'cho_kho', 'waiting_warehouse')
                ORDER BY pb.created_at DESC, pb.id DESC
                LIMIT 5
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Cannot load pending batch notifications: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('adminShellPendingSuppliers')) {
    function adminShellPendingSuppliers(): array
    {
        $conn = adminShellNotificationConnection();
        if (!$conn) {
            return ['total' => 0, 'suppliers' => []];
        }

        try {
            $where = "TRIM(LOWER(COALESCE(status, 'active'))) = 'pending'";
            $total = (int)$conn->query("SELECT COUNT(*) FROM supplier WHERE {$where}")->fetchColumn();

            $stmt = $conn->query("
                SELECT id, name, contact_person, phone, address, farm_location,
                       cultivation_area, products, created_at
                FROM supplier
                WHERE {$where}
                ORDER BY created_at DESC, id DESC
                LIMIT 5
            ");

            return [
                'total' => $total,
                'suppliers' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
        } catch (Throwable $e) {
            error_log('Cannot load pending supplier notifications: ' . $e->getMessage());
            return ['total' => 0, 'suppliers' => []];
        }
    }
}

if (!function_exists('adminShellSupplierOrderAlerts')) {
    function adminShellSupplierOrderAlerts(): array
    {
        $conn = adminShellNotificationConnection();
        if (!$conn) {
            return ['total' => 0, 'orders' => []];
        }

        $actionableWhere = "
            (
                TRIM(LOWER(COALESCE(so.status, 'pending'))) = 'pending'
                OR (
                    TRIM(LOWER(COALESCE(so.status, 'pending'))) = 'supplier_submitted'
                    AND NOT EXISTS (
                        SELECT 1
                        FROM product_batch pb_check
                        WHERE pb_check.supplier_order_id = so.id
                    )
                )
                OR (
                    TRIM(LOWER(COALESCE(so.status, 'pending'))) IN ('warehouse_confirmed', 'completed')
                    AND COALESCE(so.total_amount, 0) > COALESCE(so.paid_amount, 0)
                )
            )
        ";

        try {
            $totalStmt = $conn->query("
                SELECT COUNT(*)
                FROM supplier_orders so
                WHERE {$actionableWhere}
            ");
            $total = (int)$totalStmt->fetchColumn();

            $stmt = $conn->query("
                SELECT
                    so.id,
                    so.status,
                    so.requested_product_name,
                    so.requested_quantity,
                    so.suggested_import_price,
                    so.total_amount,
                    so.paid_amount,
                    so.created_at,
                    s.name AS supplier_name,
                    s.phone AS supplier_phone,
                    s.contact_person,
                    w.name AS warehouse_name,
                    b.id AS batch_id,
                    b.batch_code
                FROM supplier_orders so
                LEFT JOIN supplier s ON s.id = so.supplier_id
                LEFT JOIN warehouse w ON w.id = so.warehouse_id
                LEFT JOIN product_batch b ON b.supplier_order_id = so.id
                WHERE {$actionableWhere}
                ORDER BY
                    CASE TRIM(LOWER(COALESCE(so.status, 'pending')))
                        WHEN 'warehouse_confirmed' THEN 0
                        WHEN 'completed' THEN 1
                        WHEN 'supplier_submitted' THEN 2
                        WHEN 'pending' THEN 3
                        ELSE 4
                    END,
                    so.created_at DESC,
                    so.id DESC
                LIMIT 6
            ");

            return [
                'total' => $total,
                'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
        } catch (Throwable $e) {
            error_log('Cannot load supplier order notifications: ' . $e->getMessage());
            return ['total' => 0, 'orders' => []];
        }
    }
}
