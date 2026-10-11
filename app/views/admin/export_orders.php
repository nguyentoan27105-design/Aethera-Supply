<?php
$orders = $orders ?? [];
$orderItems = $orderItems ?? [];
$shipmentTimelines = $shipmentTimelines ?? [];
$adminFlash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

$itemsByOrder = [];
foreach ($orderItems as $item) {
    $itemsByOrder[(int)$item->order_id][] = $item;
}

$statusMap = [
    'pending' => ['Chờ xử lý', 'pending', 'fa-clock'],
    'confirmed' => ['Đã xác nhận', 'confirmed', 'fa-circle-check'],
    'preparing' => ['Chuẩn bị hàng', 'preparing', 'fa-box-open'],
    'packed' => ['Đã đóng gói', 'packed', 'fa-box'],
    'shipped' => ['Đang vận chuyển', 'shipped', 'fa-truck-fast'],
    'completed' => ['Hoàn tất', 'completed', 'fa-check'],
    'cancelled' => ['Đã hủy', 'cancelled', 'fa-ban'],
];

$paymentMap = [
    'paid' => ['Đã thanh toán', 'paid'],
    'partial' => ['Đã cọc', 'partial'],
    'awaiting_confirmation' => ['Chờ xác nhận CK', 'pending'],
    'refunded' => ['Đã hoàn tiền', 'refunded'],
    'cancelled' => ['Không thu tiền', 'unpaid'],
    'pending' => ['Chưa thanh toán', 'unpaid'],
];

$stockMap = [
    'unchecked' => ['Chưa kiểm kho', 'wait'],
    'sufficient' => ['Đủ hàng', 'ok'],
    'insufficient' => ['Thiếu hàng', 'bad'],
    'exported' => ['Đã xuất kho', 'done'],
    'cancelled' => ['Đã hủy kho', 'bad'],
];

$shipmentStatusOptions = [
    'packed' => 'Sẵn sàng bàn giao',
    'shipped' => 'Đã bàn giao vận chuyển',
];

if (!function_exists('exportOrderValue')) {
    function exportOrderValue($order, $shipmentKey, $orderKey = null, $default = '')
    {
        $value = $order->$shipmentKey ?? null;
        if (($value === null || $value === '') && $orderKey) {
            $value = $order->$orderKey ?? null;
        }
        return $value ?? $default;
    }
}

function exportDateInput($value): string
{
    return !empty($value) ? date('Y-m-d', strtotime((string)$value)) : '';
}

function exportDateLabel($value): string
{
    return !empty($value) ? date('d/m/Y', strtotime((string)$value)) : 'Chưa đặt';
}

function exportOrderItemsDone(array $items): bool
{
    if (empty($items)) {
        return false;
    }
    foreach ($items as $item) {
        if ((int)($item->exported_quantity ?? 0) < (int)($item->quantity ?? 0)) {
            return false;
        }
    }
    return true;
}

function exportPaymentSummary(object $order): array
{
    $total = (float)($order->total ?? $order->total_price ?? 0);
    $status = strtolower((string)($order->payment_status ?? 'pending'));
    $deposit = max(0, (float)($order->deposit_amount ?? 0));
    $remaining = max(0, (float)($order->remaining_amount ?? 0));

    if (in_array($status, ['paid', 'captured', 'settled', 'success', 'completed'], true)) {
        $remaining = 0;
        $paid = $total;
    } elseif (in_array($status, ['cancelled', 'refunded'], true)) {
        $remaining = 0;
        $paid = 0;
    } elseif ($status === 'partial') {
        if ($remaining <= 0) {
            $remaining = max(0, $total - $deposit);
        }
        $paid = max(0, $total - $remaining);
    } else {
        $paid = 0;
        $remaining = $total;
    }

    return [
        'paid' => $paid,
        'remaining' => $remaining,
        'summary' => $remaining > 0
            ? 'Còn phải thu: ' . number_format($remaining, 0, ',', '.') . 'đ'
            : 'Đã thu đủ',
    ];
}

function exportReceiptMeta(string $orderStatus, string $shipmentStatus = ''): array
{
    if ($orderStatus === 'completed' || $shipmentStatus === 'completed') {
        return ['Đã xác nhận nhận hàng', 'done', 'fa-handshake'];
    }

    if ($orderStatus === 'shipped' || $shipmentStatus === 'shipped') {
        return ['Chờ xác nhận nhận hàng', 'wait', 'fa-hand-holding-heart'];
    }

    return ['Chưa giao tới khách', 'wait', 'fa-clock'];
}

$totalRevenue = array_sum(array_map(fn($order) => (float)($order->total ?? $order->total_price ?? 0), $orders));
$waitingOrders = count(array_filter($orders, fn($order) => in_array($order->status ?? 'pending', ['pending', 'confirmed', 'preparing', 'packed'], true)));
$shippingOrders = count(array_filter($orders, fn($order) => (string)($order->status ?? '') === 'shipped' || (string)($order->shipment_status ?? '') === 'shipped'));
$completedOrders = count(array_filter($orders, fn($order) => (string)($order->status ?? '') === 'completed' || (string)($order->shipment_status ?? '') === 'completed'));
?>

<style>
.export-page { padding:24px; color:#0f172a; }
.export-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:start; margin-bottom:18px; }
.export-hero h1 { margin:0; color:#052e2b; font-weight:950; letter-spacing:0; }
.export-hero p { margin:7px 0 0; color:#64748b; font-weight:650; line-height:1.55; max-width:820px; }
.export-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.export-btn { min-height:42px; border:0; border-radius:13px; padding:0 15px; background:#059669; color:#fff; font-weight:950; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; }
.export-btn.secondary { background:#0f172a; }
.export-btn.light { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
.export-btn.disabled { opacity:.55; pointer-events:none; background:#f1f5f9; color:#64748b; border:1px solid #dbeee4; }
.sc-muted { color:#64748b; font-size:.85rem; font-weight:800; }
.export-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
.export-stat { background:#fff; border:1px solid #dbeee4; border-radius:18px; padding:17px; box-shadow:0 14px 32px rgba(6,78,59,.07); }
.export-stat span { color:#64748b; font-size:.78rem; font-weight:900; text-transform:uppercase; }
.export-stat strong { display:block; margin-top:6px; color:#047857; font-size:1.7rem; font-weight:950; }
.export-alert { margin-bottom:16px; border:1px solid #a7f3d0; background:#ecfdf5; color:#047857; border-radius:14px; padding:12px 14px; font-weight:850; }
.export-flow { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
.flow-card { background:#f7fffb; border:1px solid #dbeee4; border-radius:18px; padding:16px; display:flex; gap:12px; align-items:flex-start; }
.flow-card i { width:38px; height:38px; border-radius:12px; display:grid; place-items:center; background:#059669; color:#fff; flex:0 0 38px; }
.flow-card strong { display:block; color:#052e2b; font-weight:950; }
.flow-card span { display:block; margin-top:4px; color:#64748b; font-size:.9rem; font-weight:650; line-height:1.45; }
.order-grid { display:grid; grid-template-columns:1fr; gap:10px; }
.order-card { background:#fff; border:1px solid #dbeee4; border-radius:20px; box-shadow:0 16px 34px rgba(6,78,59,.08); overflow:hidden; }
.order-card summary { list-style:none; cursor:pointer; }
.order-card summary::-webkit-details-marker { display:none; }
.order-top { display:grid; grid-template-columns:minmax(120px,.72fr) minmax(170px,1fr) minmax(140px,.82fr) minmax(150px,.88fr) auto 24px; align-items:center; gap:14px; padding:16px 18px; background:linear-gradient(135deg,#f8fffb,#ffffff); }
.order-card[open] .order-top { border-bottom:1px solid #ecf3ef; }
.summary-cell small { display:block; color:#64748b; font-size:.75rem; font-weight:900; text-transform:uppercase; margin-bottom:3px; }
.summary-cell strong { display:block; color:#0f172a; font-weight:950; overflow-wrap:anywhere; }
.summary-cell span { display:block; color:#64748b; font-size:.82rem; font-weight:760; }
.summary-chevron { color:#059669; transition:.18s ease; }
.order-card[open] .summary-chevron { transform:rotate(180deg); }
.order-id { color:#052e2b; font-size:1.1rem; font-weight:950; }
.order-date { display:block; margin-top:3px; color:#64748b; font-size:.82rem; font-weight:750; }
.status-pill,.payment-pill,.stock-pill,.export-pill,.receipt-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:7px 10px; font-size:.78rem; font-weight:950; white-space:nowrap; }
.status-pending { background:#fff7ed; color:#c2410c; }
.status-confirmed { background:#eff6ff; color:#2563eb; }
.status-preparing { background:#fef3c7; color:#b45309; }
.status-packed { background:#f5f3ff; color:#6d28d9; }
.status-shipped { background:#ecfeff; color:#0891b2; }
.status-completed { background:#ecfdf5; color:#047857; }
.status-cancelled { background:#fef2f2; color:#dc2626; }
.payment-paid,.export-done,.stock-ok,.stock-done { background:#dcfce7; color:#047857; }
.payment-partial { background:#fef3c7; color:#b45309; }
.payment-pending { background:#e0f2fe; color:#0369a1; }
.payment-refunded { background:#e8fff4; color:#047857; }
.payment-unpaid,.export-wait,.stock-wait { background:#f1f5f9; color:#475569; }
.receipt-done { background:#dcfce7; color:#047857; }
.receipt-wait { background:#fef3c7; color:#b45309; }
.stock-bad { background:#fef2f2; color:#dc2626; }
.order-body { padding:16px 18px 18px; }
.order-info { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:14px; }
.info-box { border:1px solid #ecf3ef; background:#fbfefd; border-radius:14px; padding:12px; min-width:0; }
.info-box small { display:block; color:#64748b; font-weight:900; text-transform:uppercase; font-size:.68rem; margin-bottom:5px; }
.info-box strong { display:block; color:#0f172a; font-weight:950; overflow-wrap:anywhere; }
.info-box span { display:block; margin-top:3px; color:#64748b; font-weight:700; overflow-wrap:anywhere; }
.item-list { display:grid; gap:8px; margin:12px 0 14px; }
.item-chip { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:center; border:1px solid #ecf3ef; border-radius:13px; padding:10px 12px; background:#fff; }
.item-chip strong { display:block; color:#0f172a; font-weight:950; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.item-chip span { color:#64748b; font-size:.82rem; font-weight:760; }
.item-chip em { color:#047857; font-style:normal; font-weight:950; white-space:nowrap; }
.export-route { margin:12px 0 14px; border:1px solid #dbeee4; border-radius:15px; padding:13px; background:linear-gradient(135deg,#ecfdf5,#fff); }
.export-route-head { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:10px; color:#052e2b; font-weight:950; }
.route-map { display:grid; grid-template-columns:repeat(var(--route-count,5),minmax(0,1fr)); gap:8px; align-items:start; }
.route-node { position:relative; display:grid; justify-items:center; gap:6px; text-align:center; color:#064e3b; font-size:.78rem; font-weight:900; }
.route-node:not(:last-child)::after { content:""; position:absolute; top:18px; left:calc(50% + 18px); width:calc(100% - 28px); border-top:3px solid #10b981; opacity:.55; }
.route-icon { width:36px; height:36px; border-radius:50%; display:grid; place-items:center; background:#059669; color:#fff; box-shadow:0 8px 18px rgba(5,150,105,.18); }
.route-node.route-destination .route-icon { background:#0f766e; }
.shipment-form { display:grid; grid-template-columns:repeat(4,minmax(130px,1fr)) auto; gap:10px; align-items:end; border:1px solid #dbeee4; border-radius:15px; padding:13px; background:#fbfefd; margin:12px 0 14px; }
.shipment-form label { display:block; color:#64748b; font-size:.72rem; font-weight:900; text-transform:uppercase; margin-bottom:5px; }
.shipment-form input,.shipment-form select { width:100%; min-height:40px; border:1px solid #d6e7dd; border-radius:12px; padding:0 10px; color:#0f172a; font-weight:800; outline:0; }
.shipment-form button { min-height:40px; border:0; border-radius:12px; background:#059669; color:#fff; font-weight:950; padding:0 14px; display:inline-flex; align-items:center; gap:8px; }
.shipping-empty { border:1px dashed #cbd5e1; border-radius:15px; padding:13px; background:#fff; color:#64748b; font-weight:800; margin:12px 0 14px; }
.shipping-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; align-items:center; margin:12px 0 14px; }
.shipping-actions form { margin:0; }
.shipping-timeline { display:grid; grid-template-columns:repeat(6,minmax(120px,1fr)); gap:10px; margin-top:12px; }
.shipping-step { position:relative; border:1px solid #e2eee8; border-radius:14px; padding:12px; background:#fff; min-height:122px; }
.shipping-step.done { border-color:#a7f3d0; background:#ecfdf5; }
.shipping-dot { width:24px; height:24px; border-radius:50%; display:grid; place-items:center; background:#e2e8f0; color:#64748b; margin-bottom:8px; font-size:.72rem; }
.shipping-step.done .shipping-dot { background:#059669; color:#fff; }
.shipping-step strong { display:block; color:#0f172a; line-height:1.2; }
.shipping-step p { margin:6px 0 8px; color:#64748b; font-size:.78rem; line-height:1.35; }
.shipping-step small { color:#047857; font-weight:900; }
.empty-state { grid-column:1 / -1; background:#fff; border:1px solid #dbeee4; border-radius:20px; padding:42px; color:#64748b; text-align:center; font-weight:800; }
@media(max-width:1180px){ .order-top{grid-template-columns:1fr 1fr;} .summary-chevron{display:none;} .export-flow,.export-stats{grid-template-columns:1fr 1fr;} .shipment-form{grid-template-columns:repeat(2,minmax(0,1fr));} }
@media(max-width:760px){ .export-page{padding:16px 10px;} .export-hero,.order-info,.export-flow,.export-stats,.shipment-form{grid-template-columns:1fr;} .export-actions{justify-content:flex-start;} .order-top,.route-map,.shipping-timeline{grid-template-columns:1fr;} .route-node:not(:last-child)::after{display:none;} .shipping-actions{justify-content:flex-start;} }
</style>

<div class="export-page">
    <div class="export-hero">
        <div>
            <h1><i class="fa-solid fa-globe text-success me-2"></i>Xuất khẩu - điều phối đơn hàng</h1>
            <p>Theo dõi đơn quốc tế theo cùng cấu trúc nội địa: Kho xác nhận, kiểm hàng và xử lý xuất kho tại trang Kho hàng, sau đó bộ phận xuất khẩu cập nhật vận chuyển, ngày giao và ngày nhận dự kiến.</p>
        </div>
    </div>

    <div class="export-stats">
        <div class="export-stat"><span>Tổng đơn xuất khẩu</span><strong><?= count($orders) ?></strong></div>
        <div class="export-stat"><span>Chờ xử lý/xuất kho</span><strong><?= $waitingOrders ?></strong></div>
        <div class="export-stat"><span>Đang vận chuyển</span><strong><?= $shippingOrders ?></strong></div>
        <div class="export-stat"><span>Doanh thu đơn</span><strong><?= number_format($totalRevenue, 0, ',', '.') ?>đ</strong></div>
    </div>

    <?php if ($adminFlash): ?>
        <div class="export-alert"><i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($adminFlash) ?></div>
    <?php endif; ?>

    <div class="export-flow">
        <div class="flow-card">
            <i class="fa-solid fa-clipboard-check"></i>
            <div><strong>1. Chuẩn bị trước vận chuyển</strong><span>Kho xác nhận đơn, kiểm đủ hàng, xuất kho và chuẩn bị chứng từ. Giai đoạn này chưa tính là đang vận chuyển.</span></div>
        </div>
        <div class="flow-card">
            <i class="fa-solid fa-dolly"></i>
            <div><strong>2. Bàn giao vận chuyển</strong><span>Chỉ bàn giao khi đã có đơn vị vận chuyển, dịch vụ, tracking hoặc lịch nhận hàng rõ ràng.</span></div>
        </div>
        <div class="flow-card">
            <i class="fa-solid fa-plane-departure"></i>
            <div><strong>3. Theo dõi đến khi giao xong</strong><span>Cập nhật từng chặng đến giao chặng cuối, sau đó chờ khách xác nhận nhận hàng để hoàn tất.</span></div>
        </div>
    </div>

    <div class="order-grid">
        <?php foreach ($orders as $order): ?>
            <?php
                $orderId = (int)($order->id ?? 0);
                $items = $itemsByOrder[$orderId] ?? [];
                $orderStatus = (string)($order->status ?? 'pending');
                $status = $statusMap[$orderStatus] ?? $statusMap['pending'];
                $paymentStatus = strtolower((string)($order->payment_status ?? 'pending'));
                $payment = $paymentMap[$paymentStatus] ?? [$paymentStatus ?: 'Chưa thanh toán', 'unpaid'];
                $paymentSummary = exportPaymentSummary($order);
                $stockStatus = strtolower((string)($order->stock_status ?? 'unchecked'));
                $stock = $stockMap[$stockStatus] ?? $stockMap['unchecked'];
                $currentStatus = exportOrderValue($order, 'shipment_status', 'shipping_status', $orderStatus);
                $receipt = exportReceiptMeta($orderStatus, (string)$currentStatus);
                $shipmentFormStatus = in_array($orderStatus, ['packed', 'shipped', 'completed'], true) ? $orderStatus : $currentStatus;
                $shipmentStatusOptionsForOrder = $shipmentStatusOptions;
                if ($shipmentFormStatus === 'completed') {
                    $shipmentStatusOptionsForOrder['completed'] = 'Khách đã xác nhận nhận hàng';
                }
                $currentCountry = exportOrderValue($order, 'shipment_country', 'export_country', $order->recipient_country ?? '');
                $currentTracking = exportOrderValue($order, 'shipment_tracking', 'tracking_code');
                $currentMethod = exportOrderValue($order, 'shipment_method', 'transport_method', $order->shipping_service ?? '');
                $currentCarrier = trim((string)($order->carrier ?? '')) ?: trim((string)($order->shipping_carrier ?? ''));
                $departureDate = exportDateInput($order->departure_date ?? '');
                $arrivalDate = exportDateInput($order->arrival_date ?? '');
                $departureLabel = exportDateLabel($order->departure_date ?? '');
                $arrivalLabel = exportDateLabel($order->arrival_date ?? '');
                $timeline = $shipmentTimelines[$orderId] ?? [];
                $nextEvent = null;
                foreach ($timeline as $event) {
                    if ((int)($event->is_completed ?? 0) === 0) {
                        $nextEvent = $event;
                        break;
                    }
                }

                $canConfirmForWarehouse = $orderStatus === 'pending';
                $canRecheckForWarehouse = in_array($orderStatus, ['confirmed', 'preparing'], true) && in_array($stockStatus, ['unchecked', 'insufficient'], true);
                $canManageShipment = in_array($orderStatus, ['packed', 'shipped', 'completed'], true);
                $canAutoExport = in_array($orderStatus, ['confirmed', 'preparing'], true) && $stockStatus === 'sufficient';
                $isExported = $stockStatus === 'exported' || exportOrderItemsDone($items) || in_array($orderStatus, ['packed', 'shipped', 'completed'], true);
                $hasLeftWarehouse = in_array($orderStatus, ['shipped', 'completed'], true) || in_array($currentStatus, ['shipped', 'completed'], true);
                $canAdvanceShipment = $hasLeftWarehouse && (in_array($currentStatus, ['shipped', 'completed'], true) || !empty($timeline));
                $destination = trim(implode(', ', array_filter([
                    $order->recipient_city ?? '',
                    $currentCountry ?: ($order->recipient_country ?? '')
                ]))) ?: 'Điểm giao quốc tế';
                $routeNodes = $hasLeftWarehouse
                    ? [
                        ['Kho Aethera', 'fa-warehouse'],
                        ['Cảng xuất', 'fa-ship'],
                        ['Hải quan', 'fa-passport'],
                        ['Quốc tế', 'fa-plane'],
                        [$destination, 'fa-location-dot'],
                    ]
                    : [
                        ['Kho Aethera', 'fa-warehouse'],
                    ];
            ?>
            <details class="order-card">
                <summary class="order-top">
                    <div>
                        <div class="order-id">Đơn #<?= $orderId ?></div>
                        <span class="order-date"><?= !empty($order->created_at) ? date('d/m/Y H:i', strtotime($order->created_at)) : '-' ?></span>
                    </div>
                    <div class="summary-cell">
                        <small>Khách hàng</small>
                        <strong><?= htmlspecialchars($order->fullname ?? 'Khách hàng') ?></strong>
                        <span><?= htmlspecialchars($order->phone ?? '') ?></span>
                    </div>
                    <div class="summary-cell">
                        <small>Tổng tiền</small>
                        <strong><?= number_format((float)($order->total ?? $order->total_price ?? 0), 0, ',', '.') ?>đ</strong>
                        <span><?= htmlspecialchars($paymentSummary['summary']) ?></span>
                        <span>Nhận dự kiến: <?= htmlspecialchars($arrivalLabel) ?></span>
                    </div>
                    <div class="summary-cell">
                        <small>Quốc gia</small>
                        <strong><?= htmlspecialchars($currentCountry ?: 'Chưa gán') ?></strong>
                        <span><?= htmlspecialchars($currentCarrier ?: 'Chưa gán vận chuyển') ?></span>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <span class="status-pill status-<?= htmlspecialchars($status[1]) ?>"><i class="fa-solid <?= htmlspecialchars($status[2]) ?>"></i><?= htmlspecialchars($status[0]) ?></span>
                        <span class="payment-pill payment-<?= htmlspecialchars($payment[1]) ?>"><?= htmlspecialchars($payment[0]) ?></span>
                        <span class="stock-pill stock-<?= htmlspecialchars($stock[1]) ?>"><?= htmlspecialchars($stock[0]) ?></span>
                        <span class="receipt-pill receipt-<?= htmlspecialchars($receipt[1]) ?>"><i class="fa-solid <?= htmlspecialchars($receipt[2]) ?>"></i><?= htmlspecialchars($receipt[0]) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down summary-chevron"></i>
                </summary>

                <div class="order-body">
                    <div class="order-info">
                        <div class="info-box">
                            <small>Khách hàng</small>
                            <strong><?= htmlspecialchars($order->fullname ?? 'Khách hàng') ?></strong>
                            <span><?= htmlspecialchars($order->phone ?? '') ?></span>
                            <span><?= htmlspecialchars($order->email ?? '') ?></span>
                        </div>
                        <div class="info-box">
                            <small>Thanh toán</small>
                            <strong><?= htmlspecialchars(trim(($order->payment_method ?? '') . ' ' . ($order->payment_brand ?? '')) ?: 'Chưa có thông tin') ?></strong>
                            <span><?= htmlspecialchars($payment[0]) ?></span>
                            <span>Đã thu: <?= number_format((float)$paymentSummary['paid'], 0, ',', '.') ?>đ</span>
                            <span><?= htmlspecialchars($paymentSummary['summary']) ?></span>
                        </div>
                        <div class="info-box">
                            <small>Điểm đến</small>
                            <strong><?= htmlspecialchars($destination) ?></strong>
                            <span><?= htmlspecialchars($order->shipping_incoterm ?? 'Chưa gán Incoterm') ?></span>
                        </div>
                        <div class="info-box">
                            <small>Vận chuyển</small>
                            <strong><?= htmlspecialchars($currentCarrier ?: 'Chưa gán đơn vị') ?></strong>
                            <span><?= htmlspecialchars($currentMethod ?: 'Chưa gán dịch vụ') ?></span>
                            <span>Tracking: <?= htmlspecialchars($currentTracking ?: 'Chưa có') ?></span>
                        </div>
                        <div class="info-box">
                            <small>Ngày giao</small>
                            <strong><?= htmlspecialchars($departureLabel) ?></strong>
                            <span>Ngày nhận dự kiến: <?= htmlspecialchars($arrivalLabel) ?></span>
                        </div>
                        <div class="info-box">
                            <small>Kho hàng</small>
                            <strong><?= $isExported ? 'Đã xuất kho/đang giao' : 'Chờ xuất kho' ?></strong>
                            <span><?= htmlspecialchars($stock[0]) ?></span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <strong class="text-success"><i class="fa-solid fa-box-open me-1"></i>Sản phẩm trong đơn</strong>
                        <span class="export-pill <?= $isExported ? 'export-done' : 'export-wait' ?>">
                            <?= $isExported ? 'Đã xuất kho/đang giao' : 'Chờ xuất kho' ?>
                        </span>
                    </div>

                    <div class="item-list">
                        <?php foreach ($items as $item): ?>
                            <div class="item-chip">
                                <div>
                                    <strong><?= htmlspecialchars($item->product_name ?? 'Nông sản') ?></strong>
                                    <span><?= htmlspecialchars($item->category_name ?? 'Chưa phân loại') ?> · đã xuất <?= number_format((int)($item->exported_quantity ?? 0), 0, ',', '.') ?> kg</span>
                                </div>
                                <em><?= number_format((int)($item->quantity ?? 0), 0, ',', '.') ?> kg</em>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                            <div class="item-chip"><strong>Chưa có chi tiết sản phẩm</strong><em>-</em></div>
                        <?php endif; ?>
                    </div>

                    <div class="export-route">
                        <div class="export-route-head">
                            <span><i class="fa-solid fa-route text-success me-2"></i>Chặng đường vận chuyển</span>
                            <span class="sc-muted"><?= $hasLeftWarehouse ? 'Đơn đã rời kho' : 'Đơn vẫn ở kho' ?></span>
                        </div>
                        <div class="route-map" style="--route-count: <?= max(1, count($routeNodes)) ?>">
                            <?php foreach ($routeNodes as $index => $node): ?>
                                <div class="route-node <?= $hasLeftWarehouse && $index === count($routeNodes) - 1 ? 'route-destination' : '' ?>">
                                    <span class="route-icon"><i class="fa-solid <?= htmlspecialchars($node[1]) ?>"></i></span>
                                    <span><?= htmlspecialchars($node[0]) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!$canManageShipment): ?>
                        <div class="shipping-empty">
                            <strong>Chưa thể tạo vận chuyển.</strong><br>
                            Đơn xuất khẩu phải được Kho xác nhận, kiểm đủ hàng và hoàn tất xuất kho tại trang Kho hàng trước.
                        </div>
                        <div class="shipping-actions">
                            <?php if ($canAutoExport): ?>
                                <a class="export-btn light" href="/webbanhang/admin/inventory">
                                    <i class="fa-solid fa-warehouse"></i> Mở kho hàng
                                </a>
                            <?php elseif ($canConfirmForWarehouse): ?>
                                <form method="POST" action="/webbanhang/admin/confirmOrderForInventory">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="redirect" value="/webbanhang/admin/export_orders">
                                    <button class="export-btn light" type="submit"><i class="fa-solid fa-clipboard-check"></i> Xác nhận & kiểm kho</button>
                                </form>
                            <?php elseif ($canRecheckForWarehouse): ?>
                                <form method="POST" action="/webbanhang/admin/confirmOrderForInventory">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="redirect" value="/webbanhang/admin/export_orders">
                                    <button class="export-btn light" type="submit"><i class="fa-solid fa-rotate"></i> Kiểm kho lại</button>
                                </form>
                            <?php elseif ($stockStatus === 'insufficient'): ?>
                                <a class="export-btn light" href="/webbanhang/admin/inventory"><i class="fa-solid fa-triangle-exclamation"></i> Bổ sung kho</a>
                            <?php else: ?>
                                <a class="export-btn light" href="/webbanhang/admin/orderDetail/<?= $orderId ?>"><i class="fa-solid fa-eye"></i> Chi tiết</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/webbanhang/admin/saveShipment" class="shipment-form">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <div>
                                <label>Quốc gia</label>
                                <input name="export_country" value="<?= htmlspecialchars($currentCountry) ?>" placeholder="Nhật Bản">
                            </div>
                            <div>
                                <label>Đơn vị VC</label>
                                <input name="carrier" list="carrierList" value="<?= htmlspecialchars($currentCarrier) ?>" placeholder="DHL Express">
                            </div>
                            <div>
                                <label>Dịch vụ</label>
                                <select name="transport_method">
                                    <?php foreach (['Nội thành 24h','Liên tỉnh 2-3 ngày','Hàng lạnh nông sản','Express Worldwide','International Priority','Container lạnh đường biển','Door-to-port export','Worldwide Expedited'] as $method): ?>
                                        <option value="<?= htmlspecialchars($method) ?>" <?= $currentMethod === $method ? 'selected' : '' ?>><?= htmlspecialchars($method) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Tracking</label>
                                <input name="tracking_code" value="<?= htmlspecialchars($currentTracking) ?>" placeholder="EXP...">
                            </div>
                            <div>
                                <label>Ngày bàn giao VC</label>
                                <input type="date" name="departure_date" value="<?= htmlspecialchars($departureDate) ?>">
                            </div>
                            <div>
                                <label>Ngày nhận dự kiến</label>
                                <input type="date" name="arrival_date" value="<?= htmlspecialchars($arrivalDate) ?>">
                            </div>
                            <div>
                                <label>Trạng thái</label>
                                <select name="status">
                                    <?php foreach ($shipmentStatusOptionsForOrder as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $shipmentFormStatus === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="notes" value="<?= htmlspecialchars($order->shipment_notes ?? $order->export_note ?? '') ?>">
                            <button type="submit"><i class="fa-solid fa-calendar-check"></i> Lưu vận chuyển</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$canAdvanceShipment): ?>
                        <div class="shipping-empty">
                            <?= $hasLeftWarehouse ? 'Tuyến vận chuyển sẽ bắt đầu sau khi bấm lưu trạng thái Đã bàn giao vận chuyển.' : 'Đơn đang ở giai đoạn trước vận chuyển. Sau khi kho xuất hàng và bàn giao cho đơn vị vận chuyển, tuyến giao mới bắt đầu chạy.' ?>
                        </div>
                    <?php else: ?>
                        <div class="shipping-timeline">
                            <?php foreach ($timeline as $event): ?>
                                <?php $done = (int)($event->is_completed ?? 0) === 1; ?>
                                <div class="shipping-step <?= $done ? 'done' : '' ?>">
                                    <div class="shipping-dot">
                                        <i class="fa-solid <?= $done ? 'fa-check' : 'fa-clock' ?>"></i>
                                    </div>
                                    <strong><?= htmlspecialchars($event->title ?? '') ?></strong>
                                    <p><?= htmlspecialchars($event->description ?? '') ?></p>
                                    <small>
                                        <?= htmlspecialchars($event->location ?? '') ?>
                                        <?php if (!empty($event->event_time)): ?>
                                            · <?= date('d/m H:i', strtotime($event->event_time)) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="shipping-actions">
                            <?php if ($currentStatus === 'completed' || !$nextEvent): ?>
                                <span class="status-pill status-completed"><i class="fa-solid fa-circle-check"></i>Quy trình giao hàng đã hoàn tất</span>
                            <?php elseif (($nextEvent->status_code ?? '') === 'delivered'): ?>
                                <span class="export-btn light disabled">
                                    <i class="fa-solid fa-hand-holding-heart"></i> Chờ khách xác nhận nhận hàng
                                </span>
                            <?php else: ?>
                                <form method="POST" action="/webbanhang/admin/advanceShipmentStep">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <button class="export-btn" type="submit">
                                        <i class="fa-solid fa-forward-step"></i>
                                        Cập nhật chặng tiếp theo
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open fa-2x d-block mb-2"></i>
                Chưa có đơn xuất khẩu nào. Đơn nội địa được tách riêng ở mục Nội địa.
            </div>
        <?php endif; ?>
    </div>

    <datalist id="carrierList">
        <option value="GHN - Giao Hàng Nhanh"></option>
        <option value="Viettel Post"></option>
        <option value="Ahamove"></option>
        <option value="DHL Express"></option>
        <option value="FedEx International"></option>
        <option value="UPS"></option>
        <option value="Maersk Logistics"></option>
    </datalist>
</div>
