<?php
require_once __DIR__ . '/../../helpers/Rbac.php';
require_once __DIR__ . '/../../helpers/FormatHelper.php';
if (!isset($_SESSION['user_id']) || !Rbac::can($_SESSION['user_role'] ?? 'user', 'order_management')) {
    header("Location: /webbanhang/account/login");
    exit();
}

$orders = $orders ?? [];
$orderItems = $orderItems ?? [];
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

$totalRevenue = array_sum(array_map(fn($order) => (float)($order->total ?? 0), $orders));
$waitingOrders = count(array_filter($orders, fn($order) => in_array($order->status ?? 'pending', ['pending', 'confirmed', 'preparing', 'packed'], true)));
$shippingOrders = count(array_filter($orders, fn($order) => ($order->status ?? '') === 'shipped'));
$completedOrders = count(array_filter($orders, fn($order) => ($order->status ?? '') === 'completed'));
$adminFlash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

function domesticOrderAddress(object $order): string
{
    $parts = array_filter([
        FormatHelper::cleanText($order->address_detail ?? ''),
        FormatHelper::cleanText($order->address ?? ''),
        FormatHelper::cleanText($order->recipient_ward ?? ''),
        FormatHelper::cleanText($order->recipient_district ?? ''),
        FormatHelper::cleanText($order->recipient_city ?? ''),
        FormatHelper::cleanText($order->recipient_country ?? 'Việt Nam'),
    ]);
    return $parts ? implode(', ', array_unique($parts)) : 'Chưa có địa chỉ giao hàng';
}

function domesticOrderExported(array $items): bool
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

function domesticNextStatuses(string $status, string $stockStatus): array
{
    return match ($status) {
        'pending' => ['pending', 'confirmed', 'cancelled'],
        'confirmed' => ['confirmed', 'preparing', 'cancelled'],
        'preparing' => $stockStatus === 'exported'
            ? ['preparing', 'packed', 'cancelled']
            : ['preparing', 'cancelled'],
        'packed' => ['packed', 'shipped', 'cancelled'],
        'shipped' => ['shipped', 'cancelled'],
        default => [$status],
    };
}

function domesticDateInput($value): string
{
    return !empty($value) ? date('Y-m-d', strtotime((string)$value)) : '';
}

function domesticDateLabel($value): string
{
    return !empty($value) ? date('d/m/Y', strtotime((string)$value)) : 'Chưa đặt';
}

function domesticShippingOptions(): array
{
    return [
        'GHN - Giao Hàng Nhanh' => ['Nội thành 24h', 'Liên tỉnh 2-3 ngày', 'Hàng lạnh nông sản'],
        'Aethera Express' => ['Giao trong ngày 2-4 giờ', 'Giao hẹn giờ trong ngày', 'Giao lạnh trong ngày'],
        'GrabExpress' => ['Siêu tốc nội thành 1-2 giờ', 'Giao trong ngày'],
        'Viettel Post' => ['Chuyển phát tiêu chuẩn', 'Chuyển phát nhanh', 'Chuyển phát tiết kiệm'],
        'Ahamove' => ['Nội thành hỏa tốc', 'Xe tải lạnh nội thành', 'Giao hẹn giờ'],
    ];
}

function domesticShippingServices(): array
{
    $services = [];
    foreach (domesticShippingOptions() as $carrierServices) {
        foreach ($carrierServices as $service) {
            $services[$service] = $service;
        }
    }
    return array_values($services);
}

function domesticPaymentSummary(object $order): array
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

function domesticReceiptMeta(string $orderStatus, string $shipmentStatus = ''): array
{
    if ($orderStatus === 'completed' || $shipmentStatus === 'completed') {
        return ['Đã xác nhận nhận hàng', 'done', 'fa-handshake'];
    }

    if ($orderStatus === 'shipped' || $shipmentStatus === 'shipped') {
        return ['Chờ xác nhận nhận hàng', 'wait', 'fa-hand-holding-heart'];
    }

    return ['Chưa giao tới khách', 'wait', 'fa-clock'];
}
?>

<style>
.domestic-page { padding:24px; color:#0f172a; }
.domestic-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:start; margin-bottom:18px; }
.domestic-hero h1 { margin:0; color:#052e2b; font-weight:950; letter-spacing:-.02em; }
.domestic-hero p { margin:7px 0 0; color:#64748b; font-weight:650; line-height:1.55; max-width:780px; }
.domestic-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.domestic-btn { min-height:42px; border:0; border-radius:13px; padding:0 15px; background:#059669; color:#fff; font-weight:950; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; }
.domestic-btn.secondary { background:#0f172a; }
.domestic-btn.light { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
.sc-muted { color:#64748b; font-size:.85rem; font-weight:800; }
.domestic-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
.domestic-stat { background:#fff; border:1px solid #dbeee4; border-radius:18px; padding:17px; box-shadow:0 14px 32px rgba(6,78,59,.07); }
.domestic-stat span { color:#64748b; font-size:.78rem; font-weight:900; text-transform:uppercase; }
.domestic-stat strong { display:block; margin-top:6px; color:#047857; font-size:1.7rem; font-weight:950; }
.domestic-alert { margin-bottom:16px; border:1px solid #a7f3d0; background:#ecfdf5; color:#047857; border-radius:14px; padding:12px 14px; font-weight:850; }
.domestic-flow { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
.flow-card { background:#f7fffb; border:1px solid #dbeee4; border-radius:18px; padding:16px; display:flex; gap:12px; align-items:flex-start; }
.flow-card i { width:38px; height:38px; border-radius:12px; display:grid; place-items:center; background:#059669; color:#fff; flex:0 0 38px; }
.flow-card strong { display:block; color:#052e2b; font-weight:950; }
.flow-card span { display:block; margin-top:4px; color:#64748b; font-size:.9rem; font-weight:650; line-height:1.45; }
.order-grid { display:grid; grid-template-columns:1fr; gap:10px; }
.order-card { background:#fff; border:1px solid #dbeee4; border-radius:20px; box-shadow:0 16px 34px rgba(6,78,59,.08); overflow:hidden; }
.order-card summary { list-style:none; cursor:pointer; }
.order-card summary::-webkit-details-marker { display:none; }
.order-top { display:grid; grid-template-columns:minmax(120px,.8fr) minmax(170px,1.15fr) minmax(120px,.75fr) auto 24px; align-items:center; gap:14px; padding:16px 18px; background:linear-gradient(135deg,#f8fffb,#ffffff); }
.order-card[open] .order-top { border-bottom:1px solid #ecf3ef; }
.summary-cell small { display:block; color:#64748b; font-size:.75rem; font-weight:900; text-transform:uppercase; margin-bottom:3px; }
.summary-cell strong { display:block; color:#0f172a; font-weight:950; overflow-wrap:anywhere; }
.summary-cell span { display:block; color:#64748b; font-size:.82rem; font-weight:760; }
.summary-chevron { color:#059669; transition:.18s ease; }
.order-card[open] .summary-chevron { transform:rotate(180deg); }
.order-id { color:#052e2b; font-size:1.1rem; font-weight:950; }
.order-date { display:block; margin-top:3px; color:#64748b; font-size:.82rem; font-weight:750; }
.status-pill,.payment-pill,.export-pill,.receipt-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:7px 10px; font-size:.78rem; font-weight:950; white-space:nowrap; }
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
.domestic-btn.disabled { opacity:.55; pointer-events:none; background:#f1f5f9; color:#64748b; border:1px solid #dbeee4; }
.order-body { padding:16px 18px 18px; }
.order-info { display:grid; grid-template-columns:1.05fr .95fr; gap:12px; margin-bottom:14px; }
.info-box { border:1px solid #ecf3ef; background:#fbfefd; border-radius:14px; padding:12px; min-width:0; }
.info-box small { display:block; color:#64748b; font-weight:900; text-transform:uppercase; font-size:.68rem; margin-bottom:5px; }
.info-box strong { display:block; color:#0f172a; font-weight:950; overflow-wrap:anywhere; }
.info-box span { display:block; margin-top:3px; color:#64748b; font-weight:700; overflow-wrap:anywhere; }
.item-list { display:grid; gap:8px; margin:12px 0 14px; }
.item-chip { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:center; border:1px solid #ecf3ef; border-radius:13px; padding:10px 12px; background:#fff; }
.item-chip strong { display:block; color:#0f172a; font-weight:950; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.item-chip span { color:#64748b; font-size:.82rem; font-weight:760; }
.item-chip em { color:#047857; font-style:normal; font-weight:950; white-space:nowrap; }
.domestic-route { margin:12px 0 14px; border:1px solid #dbeee4; border-radius:15px; padding:13px; background:linear-gradient(135deg,#ecfdf5,#fff); }
.domestic-route-head { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:10px; color:#052e2b; font-weight:950; }
.route-map { display:grid; grid-template-columns:repeat(var(--route-count,5),minmax(0,1fr)); gap:8px; align-items:start; }
.route-node { position:relative; display:grid; justify-items:center; gap:6px; text-align:center; color:#064e3b; font-size:.78rem; font-weight:900; }
.route-node:not(:last-child)::after { content:""; position:absolute; top:18px; left:calc(50% + 18px); width:calc(100% - 28px); border-top:3px solid #10b981; opacity:.55; }
.route-icon { width:36px; height:36px; border-radius:50%; display:grid; place-items:center; background:#059669; color:#fff; box-shadow:0 8px 18px rgba(5,150,105,.18); }
.order-bottom { display:grid; grid-template-columns:1fr auto; gap:10px; align-items:end; border-top:1px solid #ecf3ef; padding-top:14px; }
.status-form { display:flex; gap:8px; align-items:center; }
.status-form select { min-height:40px; border:1px solid #d6e7dd; border-radius:12px; padding:0 10px; color:#0f172a; font-weight:800; outline:0; }
.status-form button { width:40px; height:40px; border:0; border-radius:12px; background:#059669; color:#fff; }
.schedule-form { display:grid; grid-template-columns:repeat(4,minmax(150px,1fr)) auto; gap:10px; align-items:end; border:1px solid #dbeee4; border-radius:15px; padding:13px; background:#fbfefd; margin:12px 0 14px; }
.schedule-form label { display:block; color:#64748b; font-size:.72rem; font-weight:900; text-transform:uppercase; margin-bottom:5px; }
.schedule-form input,.schedule-form select { width:100%; min-height:40px; border:1px solid #d6e7dd; border-radius:12px; padding:0 10px; color:#0f172a; font-weight:800; outline:0; background:#fff; }
.schedule-form button { min-height:40px; border:0; border-radius:12px; background:#059669; color:#fff; font-weight:950; padding:0 14px; display:inline-flex; align-items:center; gap:8px; }
.schedule-empty { border:1px dashed #cbd5e1; border-radius:15px; padding:13px; background:#fff; color:#64748b; font-weight:800; margin:12px 0 14px; }
.empty-state { grid-column:1 / -1; background:#fff; border:1px solid #dbeee4; border-radius:20px; padding:42px; color:#64748b; text-align:center; font-weight:800; }
@media(max-width:900px){ .domestic-hero,.order-info,.order-bottom{grid-template-columns:1fr;} .domestic-actions{justify-content:flex-start;} .domestic-stats,.domestic-flow{grid-template-columns:1fr 1fr;} .order-top{grid-template-columns:1fr 1fr;} .summary-chevron{display:none;} }
@media(max-width:620px){ .domestic-page{padding:16px 10px;} .domestic-stats,.domestic-flow,.schedule-form{grid-template-columns:1fr;} .order-top,.route-map{grid-template-columns:1fr;} .route-node:not(:last-child)::after{display:none;} .status-form{flex-direction:column; align-items:stretch;} .status-form button{width:100%;} }
</style>

<div class="domestic-page">
    <div class="domestic-hero">
        <div>
            <h1><i class="fa-solid fa-truck-fast text-success me-2"></i>Nội địa - điều phối đơn hàng</h1>
            <p>Trang này dùng để theo dõi đơn giao trong nước: kiểm tra thanh toán, địa chỉ, sản phẩm khách đã đặt, chuyển qua kho để xuất hàng theo mã đơn, rồi cập nhật trạng thái giao nhận.</p>
        </div>
    </div>

    <div class="domestic-stats">
        <div class="domestic-stat"><span>Tổng đơn nội địa</span><strong><?= count($orders) ?></strong></div>
        <div class="domestic-stat"><span>Chờ xử lý/xuất kho</span><strong><?= $waitingOrders ?></strong></div>
        <div class="domestic-stat"><span>Đang vận chuyển</span><strong><?= $shippingOrders ?></strong></div>
        <div class="domestic-stat"><span>Doanh thu đơn</span><strong><?= number_format($totalRevenue, 0, ',', '.') ?>đ</strong></div>
    </div>

    <?php if ($adminFlash): ?>
        <div class="domestic-alert"><i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($adminFlash) ?></div>
    <?php endif; ?>

    <div class="domestic-flow">
        <div class="flow-card">
            <i class="fa-solid fa-receipt"></i>
            <div><strong>1. Trước vận chuyển</strong><span>Xác nhận đơn, kiểm địa chỉ, thanh toán và đủ hàng. Chưa bàn giao thì chưa xem là đang vận chuyển.</span></div>
        </div>
        <div class="flow-card">
            <i class="fa-solid fa-boxes-packing"></i>
            <div><strong>2. Bàn giao đơn vị giao</strong><span>Kho xuất hàng xong mới bàn giao cho đơn vị giao, cập nhật ngày bàn giao và ngày nhận dự kiến.</span></div>
        </div>
        <div class="flow-card">
            <i class="fa-solid fa-route"></i>
            <div><strong>3. Theo dõi giao xong</strong><span>Theo dõi phân loại, đang giao và chờ khách xác nhận đã nhận hàng để hoàn tất.</span></div>
        </div>
    </div>

    <div class="order-grid">
        <?php foreach ($orders as $order): ?>
            <?php
                $orderId = (int)($order->id ?? 0);
                $items = $itemsByOrder[$orderId] ?? [];
                $status = $statusMap[$order->status ?? 'pending'] ?? $statusMap['pending'];
                $paymentStatus = strtolower((string)($order->payment_status ?? 'pending'));
                $payment = $paymentMap[$paymentStatus] ?? [$paymentStatus ?: 'Chưa thanh toán', 'unpaid'];
                $paymentSummary = domesticPaymentSummary($order);
                $stockStatus = strtolower((string)($order->stock_status ?? 'unchecked'));
                $stock = $stockMap[$stockStatus] ?? $stockMap['unchecked'];
                $receipt = domesticReceiptMeta((string)($order->status ?? 'pending'), (string)($order->shipment_status ?? ''));
                $isExported = $stockStatus === 'exported' || domesticOrderExported($items) || in_array($order->status ?? '', ['packed', 'shipped', 'completed'], true);
                $selectedCarrier = FormatHelper::cleanText($order->shipment_carrier ?? '') ?: FormatHelper::cleanText($order->shipping_carrier ?? '');
                $selectedService = FormatHelper::cleanText($order->shipment_method ?? '') ?: (FormatHelper::cleanText($order->transport_method ?? '') ?: FormatHelper::cleanText($order->shipping_service ?? ''));
                $carrier = $selectedCarrier !== '' ? $selectedCarrier : 'Chưa gán đơn vị';
                $service = $selectedService !== '' ? $selectedService : 'Giao nội địa';
                $departureDate = domesticDateInput($order->departure_date ?? '');
                $arrivalDate = domesticDateInput($order->arrival_date ?? '');
                $arrivalLabel = domesticDateLabel($order->arrival_date ?? '');
                $canEditSchedule = in_array((string)($order->status ?? 'pending'), ['packed', 'shipped', 'completed'], true);
            ?>
            <details class="order-card">
                <summary class="order-top">
                    <div>
                        <div class="order-id">Đơn #<?= $orderId ?></div>
                        <span class="order-date"><?= !empty($order->created_at) ? date('d/m/Y H:i', strtotime($order->created_at)) : '-' ?></span>
                    </div>
                    <div class="summary-cell">
                        <small>Khách hàng</small>
                        <strong><?= FormatHelper::escape($order->fullname ?? 'Khách hàng') ?></strong>
                        <span><?= FormatHelper::escape($order->phone ?? '') ?></span>
                    </div>
                    <div class="summary-cell">
                        <small>Tổng tiền</small>
                        <strong><?= number_format((float)($order->total ?? 0), 0, ',', '.') ?>đ</strong>
                        <span><?= FormatHelper::escape($paymentSummary['summary']) ?></span>
                        <span>Dự kiến nhận: <?= htmlspecialchars($arrivalLabel) ?></span>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <span class="status-pill status-<?= htmlspecialchars($status[1]) ?>"><i class="fa-solid <?= htmlspecialchars($status[2]) ?>"></i><?= htmlspecialchars($status[0]) ?></span>
                        <span class="payment-pill payment-<?= htmlspecialchars($payment[1]) ?>"><?= htmlspecialchars($payment[0]) ?></span>
                        <span class="payment-pill stock-<?= htmlspecialchars($stock[1]) ?>"><?= htmlspecialchars($stock[0]) ?></span>
                        <span class="receipt-pill receipt-<?= htmlspecialchars($receipt[1]) ?>"><i class="fa-solid <?= htmlspecialchars($receipt[2]) ?>"></i><?= htmlspecialchars($receipt[0]) ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-down summary-chevron"></i>
                </summary>

                <div class="order-body">
                    <div class="order-info">
                        <div class="info-box">
                            <small>Khách hàng</small>
                            <strong><?= FormatHelper::escape($order->fullname ?? 'Khách hàng') ?></strong>
                            <span><?= FormatHelper::escape($order->phone ?? '') ?></span>
                        </div>
                        <div class="info-box">
                            <small>Tổng thanh toán</small>
                            <strong><?= number_format((float)($order->total ?? 0), 0, ',', '.') ?>đ</strong>
                            <span><?= FormatHelper::escape($order->payment_method ?? 'Tài khoản ngân hàng / VISA') ?></span>
                            <span>Đã thu: <?= number_format((float)$paymentSummary['paid'], 0, ',', '.') ?>đ</span>
                            <span><?= FormatHelper::escape($paymentSummary['summary']) ?></span>
                        </div>
                        <div class="info-box">
                            <small>Địa chỉ giao</small>
                            <strong><?= FormatHelper::escape(domesticOrderAddress($order)) ?></strong>
                        </div>
                        <div class="info-box">
                            <small>Vận chuyển</small>
                            <strong><?= FormatHelper::escape($carrier) ?></strong>
                            <span><?= FormatHelper::escape($service) ?></span>
                            <span>Nhận dự kiến: <?= htmlspecialchars($arrivalLabel) ?></span>
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
                                    <strong><?= FormatHelper::escape($item->product_name ?? 'Nông sản') ?></strong>
                                    <span><?= FormatHelper::escape($item->category_name ?? 'Chưa phân loại') ?> · đã xuất <?= number_format((int)($item->exported_quantity ?? 0), 0, ',', '.') ?> kg</span>
                                </div>
                                <em><?= number_format((int)($item->quantity ?? 0), 0, ',', '.') ?> kg</em>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                            <div class="item-chip"><strong>Chưa có chi tiết sản phẩm</strong><em>-</em></div>
                        <?php endif; ?>
                    </div>

                    <?php
                        $hasLeftWarehouse = $isExported || in_array($order->status ?? 'pending', ['shipped', 'completed'], true);
                        $routeDestination = trim(implode(', ', array_filter([
                            FormatHelper::cleanText($order->recipient_ward ?? ''),
                            FormatHelper::cleanText($order->recipient_district ?? ''),
                            FormatHelper::cleanText($order->recipient_city ?? ''),
                        ]))) ?: 'Điểm giao';
                        $routeNodes = $hasLeftWarehouse
                            ? [
                                ['Kho Aethera', 'fa-warehouse'],
                                ['Phân loại', 'fa-boxes-stacked'],
                                ['Trung chuyển', 'fa-truck-fast'],
                                ['Đang giao', 'fa-person-biking'],
                                [$routeDestination, 'fa-location-dot'],
                            ]
                            : [
                                ['Kho Aethera', 'fa-warehouse'],
                            ];
                    ?>
                    <div class="domestic-route">
                        <div class="domestic-route-head">
                            <span><i class="fa-solid fa-route text-success me-2"></i>Chặng đường vận chuyển</span>
                            <span class="sc-muted"><?= $hasLeftWarehouse ? 'Đơn đã rời kho' : 'Đơn vẫn ở kho' ?></span>
                        </div>
                        <div class="route-map" style="--route-count: <?= max(1, count($routeNodes)) ?>">
                            <?php foreach ($routeNodes as $node): ?>
                                <div class="route-node">
                                    <span class="route-icon"><i class="fa-solid <?= htmlspecialchars($node[1]) ?>"></i></span>
                                    <span><?= FormatHelper::escape($node[0]) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($canEditSchedule): ?>
                        <form method="POST" action="/webbanhang/admin/saveDomesticShipmentSchedule" class="schedule-form">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <input type="hidden" name="export_country" value="<?= htmlspecialchars($order->recipient_country ?? 'Việt Nam') ?>">
                            <input type="hidden" name="tracking_code" value="<?= htmlspecialchars($order->shipment_tracking ?? $order->tracking_code ?? '') ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($order->status ?? 'packed') ?>">
                            <input type="hidden" name="notes" value="<?= htmlspecialchars($order->shipment_notes ?? $order->export_note ?? '') ?>">
                            <div>
                                <label>Đơn vị giao</label>
                                <select name="carrier" required>
                                    <option value="">Chọn đơn vị</option>
                                    <?php if ($selectedCarrier !== '' && !array_key_exists($selectedCarrier, domesticShippingOptions())): ?>
                                        <option value="<?= htmlspecialchars($selectedCarrier) ?>" selected><?= htmlspecialchars($selectedCarrier) ?></option>
                                    <?php endif; ?>
                                    <?php foreach (domesticShippingOptions() as $carrierName => $carrierServices): ?>
                                        <option value="<?= htmlspecialchars($carrierName) ?>" <?= $selectedCarrier === $carrierName ? 'selected' : '' ?>><?= htmlspecialchars($carrierName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Hình thức giao nhận</label>
                                <select name="transport_method" required>
                                    <?php if ($service !== '' && !in_array($service, domesticShippingServices(), true)): ?>
                                        <option value="<?= htmlspecialchars($service) ?>" selected><?= htmlspecialchars($service) ?></option>
                                    <?php endif; ?>
                                    <?php foreach (domesticShippingServices() as $serviceName): ?>
                                        <option value="<?= htmlspecialchars($serviceName) ?>" <?= $service === $serviceName ? 'selected' : '' ?>><?= htmlspecialchars($serviceName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Ngày bàn giao VC</label>
                                <input type="date" name="departure_date" value="<?= htmlspecialchars($departureDate) ?>">
                            </div>
                            <div>
                                <label>Ngày nhận dự kiến</label>
                                <input type="date" name="arrival_date" value="<?= htmlspecialchars($arrivalDate) ?>">
                            </div>
                            <button type="submit"><i class="fa-solid fa-calendar-check"></i> Lưu ngày</button>
                        </form>
                    <?php else: ?>
                        <div class="schedule-empty">
                            <i class="fa-solid fa-calendar-days me-2"></i>Ngày nhận dự kiến sẽ được cập nhật sau khi đơn xuất kho.
                        </div>
                    <?php endif; ?>

                    <div class="order-bottom">
                        <form action="/webbanhang/admin/updateOrderStatus" method="POST" class="status-form">
                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                            <select name="status">
                                <?php foreach (domesticNextStatuses((string)($order->status ?? 'pending'), $stockStatus) as $value): ?>
                                    <?php $meta = $statusMap[$value] ?? [$value, '', '']; ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= ($order->status ?? 'pending') === $value ? 'selected' : '' ?>><?= htmlspecialchars($meta[0]) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" title="Cập nhật trạng thái"><i class="fa-solid fa-check"></i></button>
                        </form>

                        <?php if (in_array($order->status ?? 'pending', ['confirmed', 'preparing'], true) && $stockStatus === 'sufficient'): ?>
                            <a class="domestic-btn light" href="/webbanhang/admin/inventory">
                                <i class="fa-solid fa-warehouse"></i> Mở kho hàng
                            </a>
                        <?php elseif (($order->status ?? 'pending') === 'shipped'): ?>
                            <span class="domestic-btn light disabled">
                                <i class="fa-solid fa-hand-holding-heart"></i> Chờ khách xác nhận nhận hàng
                            </span>
                        <?php elseif (($order->status ?? 'pending') === 'pending'): ?>
                            <span class="domestic-btn light disabled"><i class="fa-solid fa-circle-check"></i> Cần xác nhận đơn</span>
                        <?php elseif ($stockStatus === 'insufficient'): ?>
                            <a class="domestic-btn light" href="/webbanhang/admin/inventory"><i class="fa-solid fa-triangle-exclamation"></i> Bổ sung kho</a>
                        <?php else: ?>
                            <a class="domestic-btn light" href="/webbanhang/admin/orderDetail/<?= $orderId ?>">
                                <i class="fa-solid fa-route"></i> <?= ($order->status ?? '') === 'cancelled' ? 'Chi tiết đơn' : 'Xem tuyến giao' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open fa-2x d-block mb-2"></i>
                Chưa có đơn nội địa nào. Đơn quốc tế được tách riêng ở mục Xuất khẩu.
            </div>
        <?php endif; ?>
    </div>
</div>
