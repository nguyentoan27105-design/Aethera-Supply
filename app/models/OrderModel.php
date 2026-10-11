<?php
require_once __DIR__ . '/../helpers/OrderWorkflow.php';

class OrderModel {
    private $conn;

    // ===== TRẠNG THÁI =====
    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_PACKED    = 'packed';
    const STATUS_SHIPPED   = 'shipped';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    private const PAID_PAYMENT_STATUSES = ['paid', 'partial', 'captured', 'settled', 'success', 'completed'];

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureCheckoutColumns();
        $this->ensureInventoryLogOrderColumn();
        OrderWorkflow::ensureSchema($this->conn);
    }

    // ================= TẠO ĐƠN HÀNG =================
    public function createOrder($user_id, $total, $fullname, $email, $phone, $address, $cart_items, array $meta = []) {
        $startedTransaction = false;
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $startedTransaction = true;
            }

            // INSERT ORDER
            // Lưu ý: Thêm các cột fullname, email, phone, address vào câu lệnh INSERT nếu muốn lưu thông tin lúc đặt hàng
            $sqlOrder = "INSERT INTO orders (user_id, total, status, created_at, fullname, email, phone, address)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtOrder = $this->conn->prepare($sqlOrder);
            $stmtOrder->execute([
                (int)$user_id,
                (float)$total,
                self::STATUS_PENDING,
                date('Y-m-d H:i:s'),
                $fullname,
                $email,
                $phone,
                $address
            ]);

            $order_id = $this->conn->lastInsertId();

            if (!$order_id) {
                throw new Exception("Không tạo được order");
            }

            if (!empty($meta)) {
                $this->updateOrderMeta((int)$order_id, $meta);
            }

            OrderWorkflow::recordEvent(
                $this->conn,
                (int)$order_id,
                (int)$user_id,
                'order_created',
                null,
                self::STATUS_PENDING,
                'Don hang duoc tao',
                'Khach hang dat don tu trang thanh toan',
                [
                    'delivery_scope' => $meta['delivery_scope'] ?? 'domestic',
                    'payment_status' => $meta['payment_status'] ?? 'pending',
                    'total' => (float)$total,
                    'deposit_percent' => $meta['deposit_percent'] ?? null,
                    'deposit_amount' => $meta['deposit_amount'] ?? null,
                    'remaining_amount' => $meta['remaining_amount'] ?? null,
                ]
            );

            if (in_array(($meta['payment_status'] ?? ''), ['paid', 'partial'], true)) {
                $paidInFull = ($meta['payment_status'] ?? '') === 'paid';
                OrderWorkflow::recordEvent(
                    $this->conn,
                    (int)$order_id,
                    (int)$user_id,
                    $paidInFull ? 'payment_confirmed' : 'deposit_received',
                    null,
                    self::STATUS_PENDING,
                    $paidInFull ? 'Thanh toan da xac nhan' : 'Da ghi nhan dat coc',
                    $paidInFull ? 'He thong ghi nhan thanh toan sau khi dat hang' : 'He thong ghi nhan thanh toan tien coc sau khi dat hang',
                    [
                        'payment_method' => $meta['payment_method'] ?? '',
                        'payment_brand' => $meta['payment_brand'] ?? '',
                        'deposit_percent' => $meta['deposit_percent'] ?? 100,
                        'deposit_amount' => $meta['deposit_amount'] ?? $total,
                        'remaining_amount' => $meta['remaining_amount'] ?? 0,
                    ]
                );
            }
            // INSERT DETAILS
            // Checkout chi tao don va kiem tra ton kha dung. Ton kho that su chi bi tru khi admin/kho xac nhan xuat kho theo order_id.
            $canUseBatchInventory = $this->tableExists('inventory') && $this->tableExists('product_batch');

            $stmtDetail = $this->conn->prepare(
                "INSERT INTO order_details (order_id, product_id, quantity, price, product_name_snapshot)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmtCheck = $this->conn->prepare("SELECT stock, price, name FROM product WHERE id = ? LIMIT 1");
            $insertedDetails = 0;

            foreach ($cart_items as $cartKey => $item) {
                $item = is_object($item) ? (array)$item : (array)$item;
                $p_id    = (int)($item['product_id'] ?? ($item['id'] ?? ($item['productId'] ?? (is_numeric($cartKey) ? $cartKey : 0))));
                $p_qty   = max(0, (int)($item['quantity'] ?? ($item['qty'] ?? 0)));
                $p_price = (float)($item['price'] ?? 0);

                if ($p_id <= 0 || $p_qty <= 0) {
                    continue;
                }

                $stmtCheck->execute([$p_id]);
                $productRow = $stmtCheck->fetch(PDO::FETCH_OBJ);
                if (!$productRow) {
                    throw new Exception("Sáº£n pháº©m trong giá» hĂ ng khĂ´ng cĂ²n tá»“n táº¡i.");
                }
                $stockCache = (int)$productRow->stock;
                if ($p_price <= 0) {
                    $p_price = (float)$productRow->price;
                }
                $batchAvailable = $canUseBatchInventory ? $this->getBatchInventoryAvailable($p_id) : 0;
                $availableForSale = $canUseBatchInventory ? $batchAvailable : $stockCache;

                if ($availableForSale < $p_qty) {
                    throw new Exception("Sáº£n pháº©m khĂ´ng Ä‘á»§ tá»“n kho Ä‘á»ƒ táº¡o Ä‘Æ¡n.");
                }

                $stmtDetail->execute([
                    $order_id,
                    $p_id,
                    $p_qty,
                    $p_price,
                    (string)($productRow->name ?? ('Sản phẩm #' . $p_id))
                ]);
                $insertedDetails++;
            }

            if ($insertedDetails <= 0) {
                throw new Exception("KhĂ´ng thá»ƒ táº¡o Ä‘Æ¡n vĂ¬ giá» hĂ ng khĂ´ng cĂ³ sáº£n pháº©m há»£p lá»‡.");
            }

            if ($this->columnExists('orders', 'stock_status')) {
                $this->conn->prepare("UPDATE orders SET stock_status = 'unchecked' WHERE id = ?")->execute([(int)$order_id]);
            }

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }
            return $order_id;

        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    // ================= ADMIN: LẤY TẤT CẢ ĐƠN =================
    public function getAllOrders() {
        try {
            // SỬA: JOIN với bảng 'account' thay vì 'users'
            // Lấy fullname và phone từ bảng account nếu thông tin trong đơn hàng bị NULL
            $sql = "SELECT o.*, 
                           o.total as total_price,
                           COALESCE(o.fullname, u.fullname) as fullname,
                           COALESCE(o.phone, u.phone) as phone
                    FROM orders o
                    LEFT JOIN account u ON o.user_id = u.id
                    ORDER BY o.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (PDOException $e) {
            error_log("getAllOrders lỗi: " . $e->getMessage());
            return [];
        }
    }

    public function getDomesticOrders(): array {
        try {
            $sql = "SELECT o.*,
                           o.total as total_price,
                           COALESCE(o.fullname, u.fullname, 'Khách hàng') as fullname,
                           COALESCE(o.phone, u.phone, '') as phone,
                           COALESCE(o.email, u.email, '') as email,
                           s.carrier AS shipment_carrier,
                           s.tracking_code AS shipment_tracking,
                           s.transport_method AS shipment_method,
                           s.departure_date,
                           s.arrival_date,
                           s.status AS shipment_status,
                           s.notes AS shipment_notes
                    FROM orders o
                    LEFT JOIN account u ON o.user_id = u.id
                    LEFT JOIN shipments s ON s.order_id = o.id
                    WHERE COALESCE(o.delivery_scope, 'domestic') = 'domestic'
                    ORDER BY
                        CASE o.status
                            WHEN 'pending' THEN 1
                            WHEN 'confirmed' THEN 2
                            WHEN 'preparing' THEN 3
                            WHEN 'packed' THEN 4
                            WHEN 'shipped' THEN 5
                            WHEN 'completed' THEN 6
                            WHEN 'cancelled' THEN 7
                            ELSE 8
                        END,
                        o.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("getDomesticOrders error: " . $e->getMessage());
            return [];
        }
    }

    public function getOrderItemsForOrders(array $orderIds): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($ids)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "
                SELECT od.order_id,
                       od.product_id,
                       COALESCE(p.name, od.product_name_snapshot, CONCAT('Sản phẩm #', COALESCE(od.product_id, 'đã xóa'))) AS product_name,
                       COALESCE(c.name, 'Sản phẩm đã xóa') AS category_name,
                       COALESCE(SUM(od.quantity), 0) AS quantity,
                       MAX(od.price) AS price,
                       COALESCE(logs.exported_quantity, 0) AS exported_quantity
                FROM order_details od
                LEFT JOIN product p ON p.id = od.product_id
                LEFT JOIN category c ON c.id = p.category_id
                LEFT JOIN (
                    SELECT order_id, product_id, COALESCE(SUM(quantity), 0) AS exported_quantity
                    FROM inventory_log
                    WHERE type = 'export'
                    GROUP BY order_id, product_id
                ) logs ON logs.order_id = od.order_id AND logs.product_id = od.product_id
                WHERE od.order_id IN ($placeholders)
                GROUP BY od.order_id, od.product_id, product_name, category_name, logs.exported_quantity
                ORDER BY od.order_id DESC, product_name ASC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($ids);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            error_log("getOrderItemsForOrders error: " . $e->getMessage());
            return [];
        }
    }

    // ================= LẤY ĐƠN THEO ID =================
    public function getOrderById($id) {
        try {
            $sql = "SELECT *, total as total_price FROM orders WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([(int)$id]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            return null;
        }
    }

    // ================= CHI TIẾT ĐƠN =================
    public function getOrderDetails($order_id) {
        try {
            $sql = "SELECT od.*,
                           COALESCE(p.name, od.product_name_snapshot, CONCAT('Sản phẩm #', COALESCE(od.product_id, 'đã xóa'))) AS name,
                           p.image
                    FROM order_details od
                    LEFT JOIN product p ON od.product_id = p.id
                    WHERE od.order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([(int)$order_id]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            return [];
        }
    }

    // ================= UPDATE STATUS =================
    public function updateStatus($order_id, $status) {
        $startedTransaction = false;
        try {
            $valid = [
                self::STATUS_PENDING,
                self::STATUS_CONFIRMED,
                self::STATUS_PREPARING,
                self::STATUS_PACKED,
                self::STATUS_SHIPPED,
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED
            ];

            if (!in_array($status, $valid, true)) {
                return false;
            }

            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $startedTransaction = true;
            }

            $order = $this->getOrderById($order_id);
            if (!$order) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            $oldStatus = (string)($order->status ?? self::STATUS_PENDING);
            if ($oldStatus === $status) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->commit();
                }
                return true;
            }

            if (!$this->canTransitionOrder($order, $status)) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            if ($status === self::STATUS_CANCELLED && $oldStatus !== self::STATUS_CANCELLED) {
                $this->restoreOrderInventory((int)$order_id);
                $this->finalizeCancelledOrder((int)$order_id, $order);
            }

            OrderWorkflow::syncOrderStatus(
                $this->conn,
                (int)$order_id,
                $status,
                $oldStatus,
                $this->currentActorId(),
                'Cap nhat tu quan tri don hang'
            );

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('updateStatus error: ' . $e->getMessage());
            return false;
        }
    }

    private function canTransitionOrder(object $order, string $target): bool
    {
        $current = (string)($order->status ?? self::STATUS_PENDING);
        if ($current === $target) {
            return true;
        }
        if (in_array($current, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            return false;
        }

        $allowed = [
            self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
            self::STATUS_PREPARING => [self::STATUS_PACKED, self::STATUS_CANCELLED],
            self::STATUS_PACKED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        ];

        if (!in_array($target, $allowed[$current] ?? [], true)) {
            return false;
        }

        if ($target !== self::STATUS_CANCELLED && !$this->hasOrderItems((int)$order->id)) {
            return false;
        }
        if (in_array($target, [self::STATUS_CONFIRMED, self::STATUS_PREPARING], true) && !$this->paymentAllowsProcessing($order)) {
            return false;
        }
        if ($target === self::STATUS_PACKED && (string)($order->stock_status ?? 'unchecked') !== 'exported') {
            return false;
        }
        if ($target === self::STATUS_SHIPPED && !$this->hasValidShipment((int)$order->id)) {
            return false;
        }
        if ($target === self::STATUS_COMPLETED && ($current !== self::STATUS_SHIPPED || !$this->hasValidShipment((int)$order->id))) {
            return false;
        }

        return true;
    }

    public function hasOrderItems(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM order_details
                WHERE order_id = ?
                  AND product_id IS NOT NULL
                  AND COALESCE(quantity, 0) > 0
            ");
            $stmt->execute([$orderId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log('hasOrderItems error: ' . $e->getMessage());
            return false;
        }
    }

    private function paymentAllowsProcessing(object $order): bool
    {
        $paymentStatus = strtolower(trim((string)($order->payment_status ?? 'pending')));
        if (in_array($paymentStatus, ['paid', 'partial', 'awaiting_confirmation'], true)) {
            return true;
        }

        $method = strtolower(trim((string)($order->payment_method ?? '')));
        if ($method === '') {
            return true;
        }
        $methodAscii = $method;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $method);
            if ($converted !== false) {
                $methodAscii = strtolower((string)$converted);
            }
        }

        return str_contains($method, 'cod')
            || str_contains($method, 'cash')
            || str_contains($methodAscii, 'nhan hang')
            || str_contains($method, 'nhan hang')
            || str_contains($method, 'nhận hàng')
            || str_contains($method, 'nhận hàng');
    }

    private function hasValidShipment(int $orderId): bool
    {
        if ($orderId <= 0 || !$this->tableExists('shipments')) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT s.id,
                   COALESCE(NULLIF(s.carrier, ''), NULLIF(o.shipping_carrier, '')) AS carrier,
                   COALESCE(NULLIF(s.transport_method, ''), NULLIF(o.shipping_service, ''), NULLIF(o.transport_method, '')) AS service
            FROM shipments s
            JOIN orders o ON o.id = s.order_id
            WHERE s.order_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $shipment = $stmt->fetch(PDO::FETCH_OBJ);

        return $shipment
            && trim((string)($shipment->carrier ?? '')) !== ''
            && trim((string)($shipment->service ?? '')) !== '';
    }

    public function cancelOrder($order_id, $user_id) {
        try {
            $order = $this->getOrderById($order_id);

            if (!$order || $order->status !== self::STATUS_PENDING || (int)$order->user_id !== (int)$user_id) {
                return false;
            }

            $startedTransaction = false;
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $startedTransaction = true;
            }

            $this->conn->prepare(
                "UPDATE orders SET status = ? WHERE id = ? AND user_id = ?"
            )->execute([self::STATUS_CANCELLED, $order_id, $user_id]);

            $this->restoreOrderInventory((int)$order_id);
            $this->finalizeCancelledOrder((int)$order_id, $order);
            OrderWorkflow::syncOrderStatus(
                $this->conn,
                (int)$order_id,
                self::STATUS_CANCELLED,
                (string)($order->status ?? self::STATUS_PENDING),
                (int)$user_id,
                'Khach hang huy don'
            );

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }
            return true;

        } catch (Throwable $e) {
            if (isset($startedTransaction) && $startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    // ================= USER: LỊCH SỬ =================
    public function confirmCustomerReceiptAndPayment(int $orderId, int $userId): bool
    {
        if ($orderId <= 0 || $userId <= 0) {
            return false;
        }

        $startedTransaction = false;
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $startedTransaction = true;
            }

            $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$order || (int)($order->user_id ?? 0) !== $userId) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            $oldStatus = (string)($order->status ?? self::STATUS_PENDING);
            if ($oldStatus === self::STATUS_CANCELLED) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            if ($oldStatus !== self::STATUS_COMPLETED && $oldStatus !== self::STATUS_SHIPPED) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                return false;
            }

            $total = max(0, (float)($order->total ?? $order->total_price ?? 0));
            $remaining = max(0, (float)($order->remaining_amount ?? ($total - (float)($order->deposit_amount ?? 0))));

            if ($oldStatus !== self::STATUS_COMPLETED) {
                OrderWorkflow::syncOrderStatus(
                    $this->conn,
                    $orderId,
                    self::STATUS_COMPLETED,
                    $oldStatus,
                    $userId,
                    'Khach hang xac nhan da nhan hang'
                );
            }

            $sets = [];
            $params = [];
            if ($this->columnExists('orders', 'payment_status')) {
                $sets[] = "payment_status = ?";
                $params[] = 'paid';
            }
            if ($this->columnExists('orders', 'deposit_percent')) {
                $sets[] = "deposit_percent = ?";
                $params[] = 100;
            }
            if ($this->columnExists('orders', 'deposit_amount')) {
                $sets[] = "deposit_amount = ?";
                $params[] = $total;
            }
            if ($this->columnExists('orders', 'remaining_amount')) {
                $sets[] = "remaining_amount = ?";
                $params[] = 0;
            }
            if ($this->columnExists('orders', 'refund_status')) {
                $sets[] = "refund_status = ?";
                $params[] = 'not_required';
            }

            if (!empty($sets)) {
                $params[] = $orderId;
                $this->conn->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
            }

            if ($this->tableExists('shipments')) {
                $this->conn->prepare("UPDATE shipments SET status = 'completed' WHERE order_id = ?")->execute([$orderId]);
            }
            $this->completeShipmentTimeline($orderId);

            OrderWorkflow::recordEvent(
                $this->conn,
                $orderId,
                $userId,
                'customer_receipt_confirmed',
                $oldStatus,
                self::STATUS_COMPLETED,
                'Khach xac nhan da nhan hang',
                $remaining > 0
                    ? 'Da ghi nhan thanh toan phan con lai ' . number_format($remaining, 0, ',', '.') . 'd'
                    : 'Khach xac nhan da nhan hang va don da thanh toan du',
                [
                    'remaining_paid' => $remaining,
                    'payment_status' => 'paid',
                    'total' => $total,
                ]
            );

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('confirmCustomerReceiptAndPayment error: ' . $e->getMessage());
            return false;
        }
    }

    private function completeShipmentTimeline(int $orderId): void
    {
        if ($orderId <= 0 || !$this->tableExists('shipment_tracking_events')) {
            return;
        }

        $this->conn->prepare("
            UPDATE shipment_tracking_events
            SET is_completed = 1,
                event_time = COALESCE(event_time, NOW())
            WHERE order_id = ?
        ")->execute([$orderId]);
    }

    public function getOrdersByUserId($user_id) {
        try {
            $sql = "SELECT o.*, o.total as total_price,
                           e.event_type AS latest_event_type,
                           e.title AS latest_event_title,
                           e.created_at AS latest_event_at
                    FROM orders o
                    LEFT JOIN order_events e ON e.id = (
                        SELECT e2.id
                        FROM order_events e2
                        WHERE e2.order_id = o.id
                        ORDER BY e2.created_at DESC, e2.id DESC
                        LIMIT 1
                    )
                    WHERE o.user_id = ? 
                    ORDER BY o.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([(int)$user_id]);

            return $stmt->fetchAll(PDO::FETCH_OBJ);

        } catch (PDOException $e) {
            return [];
        }
    }
    // ================= GIAO DIỆN THÔNG BÁO HẾT HÀNG (FULL BLACK TEXT) =================
    private function renderOutOfStockUI() {
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Thông báo hết hàng - Agrisupply</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
            
            body { 
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                font-family: 'Plus Jakarta Sans', sans-serif; 
                height: 100vh; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                margin: 0;
            }

            .error-card { 
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(10px);
                border-radius: 32px; 
                padding: 60px 40px; 
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); 
                text-align: center; 
                max-width: 450px;
                width: 90%;
                border: 1px solid rgba(255, 255, 255, 1);
                transition: transform 0.3s ease;
            }

            .error-card:hover {
                transform: translateY(-5px);
            }

            .icon-circle { 
                width: 120px; 
                height: 120px; 
                background: #fff1f2; 
                color: #e11d48; 
                border-radius: 40px; /* Bo góc kiểu hiện đại */
                display: flex; 
                align-items: center; 
                justify-content: center; 
                font-size: 56px; 
                margin: 0 auto 30px;
                transform: rotate(-10deg);
                box-shadow: 0 10px 20px rgba(225, 29, 72, 0.1);
            }

            h2 { 
                color: #0f172a !important; 
                font-weight: 800; 
                margin-bottom: 16px; 
                letter-spacing: -1px;
                text-transform: uppercase;
            }

            p { 
                color: #475569 !important; 
                font-size: 1.1rem; 
                line-height: 1.6; 
                margin-bottom: 30px;
            }

            b { 
                color: #e11d48 !important; 
                font-weight: 700;
            }
            
            .btn-back { 
                background: #ffffff; /* Màu đen sang trọng */
                color: #ffffff !important; 
                border-radius: 16px; 
                padding: 16px 32px; 
                text-decoration: none; 
                font-weight: 700; 
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                transition: all 0.3s ease;
                border: none;
            }

            .btn-back:hover { 
                background: #ffffff; 
                transform: scale(1.05);
                box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
                color: #ffffff !important;
            }
        </style>

        <body>
            <div class="error-card">
                <div class="icon-circle">
                    <i class="fas fa-box-open"></i>
                </div>
                <h2>Rất tiếc, hết hàng!</h2>
                <p>
                    Sản phẩm này hiện tại đã <b>hết hàng trong kho</b>.<br>
                    Bạn có thể quay lại cửa hàng để chọn sản phẩm tương tự đang còn hàng.
                </p>
                <a href="/webbanhang/product" class="btn-back">
                    <i class="fas fa-arrow-left"></i> QUAY LẠI CỬA HÀNG
                </a>
            </div>
        </body>
        </html>
        <?php
    }

    private function ensureCheckoutColumns(): void
    {
        $columns = [
            'delivery_scope' => "VARCHAR(30) DEFAULT 'domestic'",
            'payment_method' => "VARCHAR(80) DEFAULT NULL",
            'payment_brand' => "VARCHAR(40) DEFAULT NULL",
            'payment_reference' => "VARCHAR(120) DEFAULT NULL",
            'payment_status' => "VARCHAR(40) DEFAULT 'pending'",
            'deposit_percent' => "DECIMAL(5,2) DEFAULT 100",
            'deposit_amount' => "DECIMAL(12,2) DEFAULT 0",
            'remaining_amount' => "DECIMAL(12,2) DEFAULT 0",
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
        ];

        foreach ($columns as $column => $definition) {
            $this->addColumn('orders', $column, $definition);
        }

        if ($this->tableExists('order_details')) {
            $this->addColumn('order_details', 'batch_id', "INT DEFAULT NULL");
            $this->addColumn('order_details', 'warehouse_id', "INT DEFAULT NULL");
            $this->addColumn('order_details', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
        }
    }

    private function ensureInventoryLogOrderColumn(): void
    {
        if (!$this->tableExists('inventory_log')) {
            return;
        }

        $this->addColumn('inventory_log', 'order_id', "INT DEFAULT NULL");
        $this->addColumn('inventory_log', 'order_detail_id', "INT DEFAULT NULL");
        $this->addColumn('inventory_log', 'note', "VARCHAR(255) DEFAULT NULL");
        $this->addColumn('inventory_log', 'product_name_snapshot', "VARCHAR(255) DEFAULT NULL");
    }

    private function updateOrderMeta(int $orderId, array $meta): void
    {
        $allowed = [
            'delivery_scope',
            'payment_method',
            'payment_brand',
            'payment_reference',
            'payment_status',
            'deposit_percent',
            'deposit_amount',
            'remaining_amount',
            'stock_status',
            'subtotal_amount',
            'discount_code',
            'discount_percent',
            'discount_amount',
            'recipient_country',
            'recipient_region',
            'recipient_city',
            'recipient_district',
            'recipient_ward',
            'postal_code',
            'address_detail',
            'shipping_carrier',
            'shipping_service',
            'shipping_incoterm',
            'shipping_fee',
            'currency',
            'export_country',
            'transport_method',
            'tracking_code',
        ];

        $sets = [];
        $params = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $meta) && $this->columnExists('orders', $column)) {
                $sets[] = "`$column` = ?";
                $params[] = $meta[$column];
            }
        }

        if (empty($sets)) {
            return;
        }

        $params[] = $orderId;
        $sql = "UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
    }

    private function getBatchInventoryAvailable(int $productId): int
    {
        if ($productId <= 0 || !$this->tableExists('inventory')) {
            return 0;
        }

        try {
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
        } catch (Throwable $e) {
            error_log('getBatchInventoryAvailable lỗi: ' . $e->getMessage());
            return 0;
        }
    }

    private function allocateInventoryByBatch(int $productId, int $quantity, ?int $orderId = null): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT i.id, i.quantity, i.warehouse_id, i.batch_id
                FROM inventory i
                JOIN product_batch b ON b.id = i.batch_id
                JOIN warehouse w ON w.id = i.warehouse_id
                WHERE i.product_id = ? AND i.quantity > 0
                  AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  AND COALESCE(w.status, 'active') = 'active'
                ORDER BY COALESCE(b.harvest_date, b.created_at), i.id
                FOR UPDATE
            ");
            $stmt->execute([$productId]);
            $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

            $available = 0;
            foreach ($rows as $row) {
                $available += (int)$row->quantity;
            }
            if ($available < $quantity) {
                return [];
            }

            $remaining = $quantity;
            $allocations = [];
            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (int)$row->quantity);
                $newQuantity = (int)$row->quantity - $take;
                $this->conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$newQuantity, (int)$row->id]);
                $this->logInventory((int)$productId, (int)$row->warehouse_id, (int)$row->batch_id, 'export', $take, $orderId);
                $this->syncBatchRemaining((int)$row->batch_id);

                $allocations[] = [
                    'batch_id' => (int)$row->batch_id,
                    'warehouse_id' => (int)$row->warehouse_id,
                    'quantity' => $take
                ];
                $remaining -= $take;
            }

            return $allocations;
        } catch (Throwable $e) {
            error_log('allocateInventoryByBatch lỗi: ' . $e->getMessage());
            return [];
        }
    }

    private function restoreOrderInventory(int $orderId): void
    {
        if (!$this->orderHasExportedInventory($orderId)) {
            return;
        }

        if ($this->restoreOrderInventoryFromLogs($orderId)) {
            return;
        }

        $items = $this->getOrderDetails($orderId);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            if (!empty($item->batch_id) && !empty($item->warehouse_id) && $this->tableExists('inventory')) {
                $this->restoreInventoryByBatch(
                    (int)$item->product_id,
                    (int)$item->warehouse_id,
                    (int)$item->batch_id,
                    (int)$item->quantity,
                    $orderId
                );
                $this->syncProductStockFromInventory((int)$item->product_id);
            }
        }
    }

    private function orderHasExportedInventory(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        if ($this->tableExists('inventory_log') && $this->columnExists('inventory_log', 'order_id')) {
            $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory_log WHERE order_id = ? AND type = 'export'");
            $stmt->execute([$orderId]);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }
        }

        if ($this->columnExists('orders', 'stock_status')) {
            $stmt = $this->conn->prepare("SELECT stock_status FROM orders WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            return (string)$stmt->fetchColumn() === 'exported';
        }

        return false;
    }

    private function restoreOrderInventoryFromLogs(int $orderId): bool
    {
        if (
            !$this->tableExists('inventory')
            || !$this->tableExists('inventory_log')
            || !$this->columnExists('inventory_log', 'order_id')
        ) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT product_id, warehouse_id, batch_id,
                   SUM(CASE
                       WHEN type = 'export' THEN quantity
                       WHEN type = 'import' THEN -quantity
                       ELSE 0
                   END) AS quantity_to_restore
            FROM inventory_log
            WHERE order_id = ?
            GROUP BY product_id, warehouse_id, batch_id
            HAVING quantity_to_restore > 0
        ");
        $stmt->execute([$orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        if (empty($rows)) {
            return false;
        }

        $restored = false;
        foreach ($rows as $row) {
            $productId = (int)($row->product_id ?? 0);
            $warehouseId = (int)($row->warehouse_id ?? 0);
            $batchId = (int)($row->batch_id ?? 0);
            $quantity = (int)($row->quantity_to_restore ?? 0);

            if ($productId <= 0 || $warehouseId <= 0 || $batchId <= 0 || $quantity <= 0) {
                continue;
            }

            $this->restoreInventoryByBatch($productId, $warehouseId, $batchId, $quantity, $orderId);
            $this->syncProductStockFromInventory($productId);
            $restored = true;
        }

        return $restored;
    }

    private function restoreInventoryByBatch(int $productId, int $warehouseId, int $batchId, int $quantity, ?int $orderId = null): void
    {
        $batchStmt = $this->conn->prepare("
            SELECT b.id
            FROM product_batch b
            JOIN warehouse w ON w.id = ?
            WHERE b.id = ?
              AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
              AND COALESCE(w.status, 'active') = 'active'
            LIMIT 1
        ");
        $batchStmt->execute([$warehouseId, $batchId]);
        if (!$batchStmt->fetchColumn()) {
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT id, quantity
            FROM inventory
            WHERE product_id = ? AND warehouse_id = ? AND batch_id = ?
            LIMIT 1
        ");
        $stmt->execute([$productId, $warehouseId, $batchId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        if ($row) {
            $this->conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")
                ->execute([(int)$row->quantity + $quantity, (int)$row->id]);
        } else {
            $this->conn->prepare("
                INSERT INTO inventory (product_id, warehouse_id, batch_id, quantity)
                VALUES (?, ?, ?, ?)
            ")->execute([$productId, $warehouseId, $batchId, $quantity]);
        }

        $this->logInventory($productId, $warehouseId, $batchId, 'import', $quantity, $orderId, 'Hoan hang do huy don');
        $this->syncBatchRemaining($batchId);
    }

    private function finalizeCancelledOrder(int $orderId, object $order): void
    {
        $paymentStatus = strtolower(trim((string)($order->payment_status ?? 'pending')));
        $total = (float)($order->total ?? $order->total_price ?? 0);
        $depositAmount = (float)($order->deposit_amount ?? 0);
        $paidAmount = ($paymentStatus === 'partial' && $depositAmount > 0) ? $depositAmount : $total;
        $wasPaid = in_array($paymentStatus, self::PAID_PAYMENT_STATUSES, true);

        $sets = [];
        $params = [];

        if ($this->columnExists('orders', 'cancelled_at')) {
            $sets[] = "cancelled_at = COALESCE(cancelled_at, NOW())";
        }
        if ($this->columnExists('orders', 'shipping_status')) {
            $sets[] = "shipping_status = ?";
            $params[] = self::STATUS_CANCELLED;
        }
        if ($this->columnExists('orders', 'export_status')) {
            $sets[] = "export_status = ?";
            $params[] = (($order->delivery_scope ?? 'domestic') === 'international') ? self::STATUS_CANCELLED : 'not_required';
        }
        if ($this->columnExists('orders', 'stock_status')) {
            $sets[] = "stock_status = ?";
            $params[] = self::STATUS_CANCELLED;
        }

        if ($wasPaid) {
            if ($this->columnExists('orders', 'payment_status')) {
                $sets[] = "payment_status = ?";
                $params[] = 'refunded';
            }
            if ($this->columnExists('orders', 'refund_status')) {
                $sets[] = "refund_status = ?";
                $params[] = 'refunded';
            }
            if ($this->columnExists('orders', 'refund_amount')) {
                $sets[] = "refund_amount = ?";
                $params[] = $paidAmount;
            }
            if ($this->columnExists('orders', 'refunded_at')) {
                $sets[] = "refunded_at = COALESCE(refunded_at, NOW())";
            }
            if ($this->columnExists('orders', 'refund_note')) {
                $sets[] = "refund_note = ?";
                $params[] = 'Da hoan tien do huy don';
            }
        } else {
            if ($this->columnExists('orders', 'payment_status')) {
                $sets[] = "payment_status = ?";
                $params[] = 'cancelled';
            }
            if ($this->columnExists('orders', 'refund_status')) {
                $sets[] = "refund_status = ?";
                $params[] = 'not_required';
            }
            if ($this->columnExists('orders', 'refund_amount')) {
                $sets[] = "refund_amount = 0";
            }
            if ($this->columnExists('orders', 'refund_note')) {
                $sets[] = "refund_note = ?";
                $params[] = 'Don chua thanh toan, khong can hoan tien';
            }
        }

        if (empty($sets)) {
            return;
        }

        $params[] = $orderId;
        $stmt = $this->conn->prepare("UPDATE orders SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);

        if ($this->tableExists('shipments')) {
            $this->conn->prepare("
                UPDATE shipments
                SET status = 'cancelled',
                    notes = COALESCE(NULLIF(notes, ''), 'Don hang da huy, khong van chuyen')
                WHERE order_id = ?
            ")->execute([$orderId]);
        }
    }

    private function syncBatchRemaining(int $batchId): void
    {
        if ($batchId <= 0 || !$this->tableExists('product_batch')) {
            return;
        }
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE batch_id = ?");
        $stmt->execute([$batchId]);
        $remaining = (int)$stmt->fetchColumn();
        $this->conn->prepare("UPDATE product_batch SET remaining_quantity = ? WHERE id = ?")->execute([$remaining, $batchId]);
    }

    private function syncProductStockFromInventory(int $productId): void
    {
        if ($productId <= 0 || !$this->tableExists('inventory')) {
            return;
        }
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

    private function logInventory(int $productId, int $warehouseId, int $batchId, string $type, int $quantity, ?int $orderId = null, ?string $note = null, ?int $orderDetailId = null): void
    {
        if (!$this->tableExists('inventory_log')) {
            return;
        }

        $hasOrderId = $this->columnExists('inventory_log', 'order_id');
        $hasOrderDetailId = $this->columnExists('inventory_log', 'order_detail_id');
        $hasNote = $this->columnExists('inventory_log', 'note');

        if ($hasOrderDetailId && $orderDetailId === null && $type === 'export' && $orderId !== null) {
            $orderDetailId = $this->findOrderDetailId($orderId, $productId, $warehouseId, $batchId);
        }

        $columns = ['product_id', 'warehouse_id', 'batch_id'];
        $values = [$productId, $warehouseId, $batchId];

        if ($hasOrderId) {
            $columns[] = 'order_id';
            $values[] = $orderId;
        }

        if ($hasOrderDetailId) {
            $columns[] = 'order_detail_id';
            $values[] = $orderDetailId;
        }

        $columns[] = 'type';
        $values[] = $type;
        $columns[] = 'quantity';
        $values[] = $quantity;

        if ($hasNote) {
            $columns[] = 'note';
            $values[] = $note;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->conn->prepare("
            INSERT INTO inventory_log (`" . implode('`, `', $columns) . "`)
            VALUES ($placeholders)
        ");
        $stmt->execute($values);

        $metadata = [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
        ];
        if ($orderDetailId !== null) {
            $metadata['order_detail_id'] = $orderDetailId;
        }
        if ($note !== null) {
            $metadata['note'] = $note;
        }

        OrderWorkflow::recordInventoryEvent($this->conn, (int)($orderId ?? 0), $type, $quantity, $metadata);
    }

    private function findOrderDetailId(?int $orderId, int $productId, int $warehouseId, int $batchId): ?int
    {
        if ($orderId === null || $orderId <= 0 || !$this->tableExists('order_details')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT id
            FROM order_details
            WHERE order_id = ?
              AND product_id = ?
              AND (batch_id = ? OR batch_id IS NULL)
              AND (warehouse_id = ? OR warehouse_id IS NULL)
            ORDER BY
              CASE WHEN batch_id = ? AND warehouse_id = ? THEN 0 ELSE 1 END,
              id
            LIMIT 1
        ");
        $stmt->execute([$orderId, $productId, $batchId, $warehouseId, $batchId, $warehouseId]);
        $id = (int)$stmt->fetchColumn();

        return $id > 0 ? $id : null;
    }

    public function getOrderEvents(int $orderId): array
    {
        return OrderWorkflow::getOrderEvents($this->conn, $orderId);
    }

    public function getRecentOrderEvents(int $limit = 80, ?string $fromDate = null, ?string $toDate = null, string $statusFilter = '', string $scopeFilter = ''): array
    {
        $limit = max(1, min(200, $limit));
        if (!$this->tableExists('order_events')) {
            return [];
        }

        $where = [];
        $params = [];
        if ($fromDate && $toDate) {
            $where[] = "DATE(e.created_at) BETWEEN ? AND ?";
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
            SELECT e.*, o.total, o.status AS order_status, o.payment_status, o.shipping_status,
                   o.delivery_scope, o.export_country, o.recipient_country,
                   COALESCE(o.fullname, a.fullname, 'Khach hang') AS order_customer,
                   actor.fullname AS actor_name
            FROM order_events e
            LEFT JOIN orders o ON o.id = e.order_id
            LEFT JOIN account a ON a.id = o.user_id
            LEFT JOIN account actor ON actor.id = e.actor_id
            {$whereSql}
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function currentActorId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
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
            return false;
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
            error_log("Không thể thêm cột {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function columnExists(string $table, string $column): bool
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
            return false;
        }
    }
}
