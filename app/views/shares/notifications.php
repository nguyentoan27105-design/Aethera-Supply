<?php
$adminNotificationHelperPath = __DIR__ . '/../../helpers/AdminNotificationHelper.php';
if (file_exists($adminNotificationHelperPath)) {
    require_once $adminNotificationHelperPath;
}

if (!isset($adminOpenOrderData)) {
    $adminOpenOrderData = function_exists('adminShellOpenOrders')
        ? adminShellOpenOrders()
        : ['total' => 0, 'orders' => []];
}
$adminOpenOrderTotal = (int)($adminOpenOrderData['total'] ?? 0);
$adminOpenOrders = $adminOpenOrderData['orders'] ?? [];

if (!isset($adminLowStockProducts)) {
    $adminLowStockProducts = function_exists('adminShellLowStockProducts') ? adminShellLowStockProducts() : [];
}

if (!isset($adminPendingBatches)) {
    $adminPendingBatches = function_exists('adminShellPendingBatches') ? adminShellPendingBatches() : [];
}

if (!isset($adminPendingSupplierData)) {
    $adminPendingSupplierData = function_exists('adminShellPendingSuppliers')
        ? adminShellPendingSuppliers()
        : ['total' => 0, 'suppliers' => []];
}
$adminPendingSuppliers = $adminPendingSupplierData['suppliers'] ?? ($adminPendingSuppliers ?? []);
$adminPendingSupplierTotal = (int)($adminPendingSupplierData['total'] ?? ($adminPendingSupplierTotal ?? count($adminPendingSuppliers)));

if (!isset($adminSupplierOrderAlertData)) {
    $adminSupplierOrderAlertData = function_exists('adminShellSupplierOrderAlerts')
        ? adminShellSupplierOrderAlerts()
        : ['total' => 0, 'orders' => []];
}
$adminSupplierOrderAlerts = $adminSupplierOrderAlertData['orders'] ?? ($adminSupplierOrderAlerts ?? []);
$adminSupplierOrderAlertTotal = (int)($adminSupplierOrderAlertData['total'] ?? ($adminSupplierOrderAlertTotal ?? count($adminSupplierOrderAlerts)));

if (!function_exists('adminShellOrderStatusLabel')) {
    function adminShellOrderStatusLabel($status) {
        $statuses = ['pending' => 'Chờ xử lý', 'shipped' => 'Đang giao hàng', 'processing' => 'Đang xử lý', 'completed' => 'Đã hoàn thành'];
        return $statuses[$status] ?? 'Không xác định';
    }
}
if (!function_exists('adminShellPaymentStatusLabel')) {
    function adminShellPaymentStatusLabel($status) {
        return $status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán';
    }
}
if (!function_exists('adminShellStockStatusLabel')) {
    function adminShellStockStatusLabel($status) {
        return $status === 'available' ? 'Đủ kho' : 'Chờ kiểm hàng';
    }
}
if (!function_exists('adminShellSupplierOrderNotificationMeta')) {
    function adminShellSupplierOrderNotificationMeta(string $status): array {
        return ['title' => 'Đơn nhập nhà cung cấp cần xử lý', 'type' => 'info', 'icon' => 'fa-clipboard-list', 'hint' => 'Mở màn hình nhập hàng để kiểm tra trạng thái.'];
    }
}

$adminNotificationTotal =
    $adminOpenOrderTotal
    + $adminPendingSupplierTotal
    + $adminSupplierOrderAlertTotal
    + count($adminLowStockProducts)
    + count($adminPendingBatches);
?>
<style>
/* Toàn bộ khung Drawer thông báo trượt */
.admin-notification-drawer {
    position: fixed;
    top: 0;
    right: -420px;
    width: min(390px, calc(100vw - 18px));
    height: 100vh;
    background: #ffffff;
    box-shadow: -3px 0 15px rgba(0, 0, 0, 0.1);
    transition: right 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
    display: flex;
    flex-direction: column;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.admin-notification-drawer.is-active {
    right: 0 !important;
}

/* Header bên trong Drawer */
.admin-notification-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #edf2f7;
    background: #f8fafc;
}
.admin-notification-head strong { font-size: 16px; color: #1a202c; display: block; margin-bottom: 2px; }
.admin-notification-head span { font-size: 13px; color: #718096; }

/* Nút bấm icon đóng (X) */
.admin-icon-btn {
    background: none;
    border: none;
    font-size: 18px;
    color: #a0aec0;
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    transition: background 0.2s, color 0.2s;
}
.admin-icon-btn:hover { background: #edf2f7; color: #4a5568; }

/* Vùng cuộn danh sách thẻ */
.admin-notification-list {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: stretch;
}

/* Thiết kế thẻ chung dạng link <a> */
.admin-notification-card {
    display: grid;
    grid-template-columns: 22px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    min-height: 64px;
    height: auto;
    padding: 10px 12px;
    margin-bottom: 0;
    border-radius: 8px;
    text-decoration: none;
    border-left: 4px solid #cbd5e0;
    background: #fff;
    transition: transform 0.2s, box-shadow 0.2s;
}
.admin-notification-card:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
.admin-notification-card i { font-size: 16px; width: 20px; text-align: center; }
.admin-notification-card span {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        "title tag"
        "desc tag";
    column-gap: 10px;
    row-gap: 2px;
    min-width: 0;
}
.admin-notification-card strong {
    grid-area: title;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
    line-height: 1.35;
    margin-bottom: 0;
    color: #1a202c;
}
.admin-notification-card small {
    grid-area: desc;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    line-height: 1.35;
    margin-bottom: 0;
    color: #4a5568;
}
.admin-notification-card em {
    grid-area: tag;
    align-self: center;
    max-width: 118px;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 999px;
    background: rgba(113, 128, 150, .12);
    padding: 4px 8px;
    font-style: normal;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
    color: #718096;
}

/* Thiết lập màu cảnh báo theo loại lớp dữ liệu */
.admin-notification-card.danger { background: #fff5f5; border-left-color: #e53e3e; }
.admin-notification-card.danger strong, .admin-notification-card.danger i { color: #c53030; }

.admin-notification-card.warning { background: #fffaf0; border-left-color: #dd6b20; }
.admin-notification-card.warning strong, .admin-notification-card.warning i { color: #c05621; }

.admin-notification-card.success { background: #f0fff4; border-left-color: #38a169; }
.admin-notification-card.success strong, .admin-notification-card.success i { color: #2f855a; }

.admin-notification-card.info { background: #ebf8ff; border-left-color: #3182ce; }
.admin-notification-card.info strong, .admin-notification-card.info i { color: #2b6cb0; }

/* Trạng thái trống (Hoàn tất toàn bộ công việc) */
.admin-notification-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 40px 20px;
    color: #4a5568;
}
.admin-notification-empty i {
    font-size: 48px;
    color: #38a169;
    margin-bottom: 16px;
}
.admin-notification-empty strong {
    font-size: 16px;
    color: #2d3748;
    margin-bottom: 6px;
}
.admin-notification-empty p {
    font-size: 13px;
    color: #718096;
    margin: 0;
}
</style>

<aside class="admin-notification-drawer" data-admin-notification-panel aria-label="Trung tâm cảnh báo">
    <div class="admin-notification-head">
        <div>
            <strong>Trung tâm thông báo</strong>
            <span>
                <?= $adminNotificationTotal > 0
                    ? $adminNotificationTotal . ' thông báo cần xử lý'
                    : 'Tuyệt vời! Không có cảnh báo'
                ?>
            </span>
        </div>

        <button type="button" class="admin-icon-btn" data-admin-notifications-close aria-label="Đóng cảnh báo">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="admin-notification-list">
        <?php if (!empty($adminPendingSuppliers) || !empty($adminSupplierOrderAlerts) || !empty($adminPendingBatches) || !empty($adminOpenOrders) || !empty($adminLowStockProducts)): ?>

            <?php foreach ($adminPendingSuppliers as $supplier): ?>
                <?php
                    $supplierName = trim((string)($supplier['name'] ?? '')) ?: 'Nhà cung cấp mới';
                    $supplierContact = trim((string)($supplier['contact_person'] ?? '')) ?: 'Chưa có người liên hệ';
                    $supplierPhone = trim((string)($supplier['phone'] ?? '')) ?: 'Chưa có SĐT';
                    $supplierProducts = trim((string)($supplier['products'] ?? '')) ?: 'Chưa cập nhật nông sản';
                    $supplierPlace = trim((string)($supplier['farm_location'] ?? $supplier['address'] ?? ''));
                ?>
                <a href="/webbanhang/admin/suppliers" class="admin-notification-card warning">
                    <i class="fa-solid fa-user-clock"></i>
                    <span>
                        <strong>Nhà cung cấp mới chờ duyệt: <?= htmlspecialchars($supplierName) ?></strong>
                        <small>
                            <?= htmlspecialchars($supplierContact) ?>
                            · <?= htmlspecialchars($supplierPhone) ?>
                            · <?= htmlspecialchars($supplierProducts) ?>
                            <?= $supplierPlace !== '' ? ' · ' . htmlspecialchars($supplierPlace) : '' ?>
                        </small>
                        <em>Cần duyệt hồ sơ nhà cung cấp</em>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php foreach ($adminSupplierOrderAlerts as $supplierOrder): ?>
                <?php
                    $supplierOrderStatus = strtolower((string)($supplierOrder['status'] ?? 'pending'));
                    $supplierOrderMeta = adminShellSupplierOrderNotificationMeta($supplierOrderStatus);
                    $supplierOrderName = trim((string)($supplierOrder['supplier_name'] ?? '')) ?: 'Nhà cung cấp';
                    $supplierOrderProduct = trim((string)($supplierOrder['requested_product_name'] ?? '')) ?: 'Sản phẩm';
                    $supplierOrderQuantity = (float)($supplierOrder['requested_quantity'] ?? 0);
                    $supplierOrderTotal = (float)($supplierOrder['total_amount'] ?? 0);
                    $supplierOrderPaid = (float)($supplierOrder['paid_amount'] ?? 0);
                    $supplierOrderRemaining = max(0, $supplierOrderTotal - $supplierOrderPaid);
                    $supplierOrderMoneyText = $supplierOrderRemaining > 0
                        ? ' · còn ' . number_format($supplierOrderRemaining, 0, ',', '.') . 'đ'
                        : '';
                    $supplierOrderBatchText = !empty($supplierOrder['batch_code'])
                        ? ' · lô ' . $supplierOrder['batch_code']
                        : (!empty($supplierOrder['batch_id']) ? ' · lô #' . (int)$supplierOrder['batch_id'] : '');
                ?>
                <a href="/webbanhang/admin/batches" class="admin-notification-card <?= htmlspecialchars($supplierOrderMeta['type']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($supplierOrderMeta['icon']) ?>"></i>
                    <span>
                        <strong><?= htmlspecialchars($supplierOrderMeta['title']) ?> #<?= (int)$supplierOrder['id'] ?></strong>
                        <small>
                            <?= htmlspecialchars($supplierOrderName) ?>
                            · <?= htmlspecialchars($supplierOrderProduct) ?>
                            · <?= number_format($supplierOrderQuantity, 0, ',', '.') ?> kg
                            <?= htmlspecialchars($supplierOrderBatchText) ?>
                            <?= htmlspecialchars($supplierOrderMoneyText) ?>
                        </small>
                        <em><?= htmlspecialchars($supplierOrderMeta['hint']) ?></em>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php foreach ($adminOpenOrders as $order): ?>
                <?php
                    $status = (string)($order['status'] ?? 'pending');
                    $paymentStatus = (string)($order['payment_status'] ?? 'pending');
                    $stockStatus = (string)($order['stock_status'] ?? 'unchecked');

                    $country = trim((string)($order['export_country'] ?? '')) ?: trim((string)($order['recipient_country'] ?? ''));
                    $normalizedCountry = function_exists('mb_strtolower') ? mb_strtolower($country, 'UTF-8') : strtolower($country);

                    $isExportOrder = in_array((string)($order['delivery_scope'] ?? ''), ['export', 'international'], true)
                        || ($country !== '' && !in_array($normalizedCountry, ['việt nam', 'viet nam', 'vietnam'], true));

                    $targetUrl = $isExportOrder ? '/webbanhang/admin/export_orders' : '/webbanhang/admin/order_management';
                    $cardType = $status === 'pending' ? 'danger' : ($status === 'shipped' ? 'success' : 'warning');
                    $icon = $status === 'pending' ? 'fa-triangle-exclamation' : ($status === 'shipped' ? 'fa-truck-fast' : 'fa-file-invoice');
                ?>

                <a href="<?= $targetUrl ?>" class="admin-notification-card <?= $cardType ?>">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <span>
                        <strong>Đơn #<?= (int)$order['id'] ?> · <?= htmlspecialchars(adminShellOrderStatusLabel($status)) ?></strong>
                        <small>
                            <?= htmlspecialchars(($order['fullname'] ?? '') ?: 'Khách hàng') ?>
                            · <?= number_format((float)($order['total'] ?? 0), 0, ',', '.') ?>đ
                            · <?= htmlspecialchars(adminShellPaymentStatusLabel($paymentStatus)) ?>
                            · <?= htmlspecialchars(adminShellStockStatusLabel($stockStatus)) ?>
                        </small>
                        <em><?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime((string)$order['created_at'])) : 'Chưa có thời gian' ?></em>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php foreach ($adminLowStockProducts as $product): ?>
                <a href="/webbanhang/admin/inventory" class="admin-notification-card danger">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>
                        <strong>Tồn kho thấp: <?= htmlspecialchars($product['name'] ?? 'Sản phẩm') ?></strong>
                        <small>Số lượng còn lại: <?= (int)($product['stock'] ?? 0) ?>. Cần kiểm tra hoặc nhập thêm hàng.</small>
                        <em>Kho hàng</em>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php foreach ($adminPendingBatches as $batch): ?>
                <a href="/webbanhang/admin/batches" class="admin-notification-card warning">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span>
                        <strong>Lô chờ kho #<?= (int)$batch['id'] ?></strong>
                        <small>
                            <?= htmlspecialchars($batch['product_name'] ?? 'Sản phẩm') ?>
                            · <?= number_format((float)($batch['quantity'] ?? $batch['remaining_quantity'] ?? 0), 0, ',', '.') ?> kg
                        </small>
                        <em>
                            <?= ($batch['status'] ?? '') === 'supplier_submitted' ? 'Nhà cung cấp đã gửi phiếu' : 'Chờ xử lý' ?>
                        </em>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php if ($adminOpenOrderTotal > count($adminOpenOrders)): ?>
                <a href="/webbanhang/admin/order_management" class="admin-notification-card info">
                    <i class="fa-solid fa-list-check"></i>
                    <span>
                        <strong>Còn <?= $adminOpenOrderTotal - count($adminOpenOrders) ?> đơn khác</strong>
                        <small>Mở màn hình điều phối để xử lý toàn bộ đơn chưa hoàn tất.</small>
                    </span>
                </a>
            <?php endif; ?>

        <?php else: ?>
            <div class="admin-notification-empty">
                <i class="fa-solid fa-circle-check"></i>
                <strong>Đã xử lý hoàn tất!</strong>
                <p>Không có nhà cung cấp mới, đơn nhập, lô hàng, đơn bán hoặc sản phẩm thấp kho cần xử lý lúc này.</p>
            </div>
        <?php endif; ?>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const notificationPanel = document.querySelector('[data-admin-notification-panel]');
    const closeButton = document.querySelector('[data-admin-notifications-close]');
    
    let openButton = document.getElementById('open-admin-notification-btn');
    if (!openButton) {
        openButton = document.querySelector('.fa-bell')?.closest('button') || document.querySelector('.fa-bell')?.closest('a');
    }

    if (openButton && notificationPanel) {
        openButton.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation(); 
            notificationPanel.classList.add('is-active');
        });
    }

    if (closeButton && notificationPanel) {
        closeButton.addEventListener('click', function (e) {
            e.preventDefault();
            notificationPanel.classList.remove('is-active');
        });
    }

    document.addEventListener('click', function (event) {
        if (notificationPanel && notificationPanel.classList.contains('is-active')) {
            if (!notificationPanel.contains(event.target) && openButton && !openButton.contains(event.target)) {
                notificationPanel.classList.remove('is-active');
            }
        }
    });
});
</script>
