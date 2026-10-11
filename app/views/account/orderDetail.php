<?php
require_once __DIR__ . '/../../helpers/FormatHelper.php';

if (!isset($order) || !$order) {
    echo "<div class='container py-5 text-center'>
            <div class='card p-5 border-0 shadow-sm'>
                <i class='fas fa-box-open fa-3x mb-3 text-muted'></i>
                <h3 class='text-dark'>Không tìm thấy đơn hàng</h3>
                <div class='mt-4'>
                    <a href='/webbanhang/account/profile' class='btn btn-success rounded-pill px-4'>Quay lại</a>
                </div>
          </div></div>";
    return;
}

$order_items = $order_items ?? ($order_details ?? []);
$shipment = $shipment ?? null;
$shipmentTimeline = $shipmentTimeline ?? [];
$orderEvents = $orderEvents ?? [];
$order = is_array($order) ? (object)$order : $order;
$shipment = is_array($shipment) ? (object)$shipment : $shipment;
$order_items = array_map(static fn($item) => is_array($item) ? (object)$item : $item, is_array($order_items) ? $order_items : []);
$shipmentTimeline = array_map(static fn($event) => is_array($event) ? (object)$event : $event, is_array($shipmentTimeline) ? $shipmentTimeline : []);
$orderEvents = array_map(static fn($event) => is_array($event) ? (object)$event : $event, is_array($orderEvents) ? $orderEvents : []);
$backUrl = $backUrl ?? '/webbanhang/account/profile?tab=orders';
$backLabel = $backLabel ?? 'Quay lại đơn hàng';
$orderId = (int)($order->id ?? 0);
$shippingStatus = (string)($shipment->status ?? $order->shipping_status ?? $order->status ?? 'pending');
$trackingCode = FormatHelper::cleanText($shipment->tracking_code ?? $order->tracking_code ?? '');
$carrier = FormatHelper::cleanText($shipment->carrier ?? $order->shipping_carrier ?? 'Đơn vị vận chuyển');
$method = FormatHelper::cleanText($shipment->transport_method ?? $order->transport_method ?? $order->shipping_service ?? 'Dịch vụ giao hàng');
$country = FormatHelper::cleanText($shipment->export_country ?? $order->export_country ?? $order->recipient_country ?? '');
$normalizedCountry = function_exists('mb_strtolower') ? mb_strtolower($country, 'UTF-8') : strtolower($country);
$isVietnamDestination = $country === '' || in_array($normalizedCountry, ['việt nam', 'viet nam', 'vietnam'], true);
$isInternational = !$isVietnamDestination && (
    ($order->delivery_scope ?? '') === 'international'
    || trim((string)($order->export_country ?? '')) !== ''
    || trim((string)($shipment->export_country ?? '')) !== ''
);
if (!$isInternational && preg_match('/DHL|FedEx|UPS|Maersk|International/i', $carrier . ' ' . $method)) {
    $carrier = FormatHelper::cleanText($order->shipping_carrier ?? '', 'Đơn vị vận chuyển nội địa');
    $method = FormatHelper::cleanText($order->shipping_service ?? '', 'Giao nội địa');
}
$destination = trim(implode(', ', array_filter([
    FormatHelper::cleanText($order->recipient_ward ?? ''),
    FormatHelper::cleanText($order->recipient_district ?? ''),
    FormatHelper::cleanText($order->recipient_city ?? ''),
    $country ?: FormatHelper::cleanText($order->recipient_country ?? '')
]))) ?: 'Điểm giao';
$hasLeftWarehouse = in_array((string)($order->status ?? 'pending'), ['shipped', 'completed'], true)
    || in_array($shippingStatus, ['shipped', 'completed'], true);

$routeNodes = !$hasLeftWarehouse
    ? [
        ['Kho Aethera', 'fa-warehouse'],
    ]
    : ($isInternational
        ? [
        ['Kho Aethera', 'fa-warehouse'],
        ['Cảng xuất', 'fa-ship'],
        ['Hải quan', 'fa-passport'],
        ['Quốc tế', 'fa-plane'],
        [$destination, 'fa-location-dot'],
    ]
        : [
        ['Kho Aethera', 'fa-warehouse'],
        ['Phân loại', 'fa-boxes-stacked'],
        ['Trung chuyển', 'fa-truck-fast'],
        ['Đang giao', 'fa-person-biking'],
        [$destination, 'fa-location-dot'],
    ]);

$statusMap = [
    'pending' => ['Chờ xử lý', 'fa-clock', 'pending'],
    'confirmed' => ['Đã xác nhận', 'fa-circle-check', 'pending'],
    'preparing' => ['Chuẩn bị hàng', 'fa-box-open', 'pending'],
    'packed' => ['Đã đóng gói', 'fa-box', 'pending'],
    'shipped' => ['Đang vận chuyển', 'fa-truck-fast', 'shipping'],
    'completed' => ['Hoàn tất', 'fa-check-double', 'completed'],
    'cancelled' => ['Đã hủy', 'fa-xmark', 'cancelled'],
];
$statusMeta = $statusMap[$order->status ?? 'pending'] ?? $statusMap['pending'];
$isCancelled = ($order->status ?? '') === 'cancelled';
$paymentStatus = strtolower((string)($order->payment_status ?? 'pending'));
$refundStatus = strtolower((string)($order->refund_status ?? ''));
$paymentLabel = match (true) {
    $refundStatus === 'refunded' || $paymentStatus === 'refunded' => 'Đã hoàn tiền',
    $paymentStatus === 'paid' => 'Đã thanh toán',
    $paymentStatus === 'partial' => 'Đã cọc',
    $paymentStatus === 'awaiting_confirmation' => 'Chờ xác nhận thanh toán',
    $paymentStatus === 'cancelled' => 'Không thu tiền',
    default => 'Chưa thanh toán',
};
$refundAmount = (float)($order->refund_amount ?? 0);
$cancelAmountLabel = ($refundStatus === 'refunded' || $paymentStatus === 'refunded' || $refundAmount > 0) ? 'Số tiền hoàn' : 'Số tiền đơn';
$cancelAmount = $refundAmount > 0 ? $refundAmount : (float)($order->total ?? 0);
$shippingFee = (float)($order->shipping_fee ?? 0);
$subtotalAmount = max(0, (float)($order->total ?? 0) - $shippingFee);
$depositPercent = (float)($order->deposit_percent ?? 0);
$depositAmount = (float)($order->deposit_amount ?? 0);
if ($depositPercent <= 0 && $paymentStatus === 'paid') {
    $depositPercent = 100;
}
if ($depositAmount <= 0 && $depositPercent > 0) {
    $depositAmount = round((float)($order->total ?? 0) * ($depositPercent / 100));
}
$remainingAmount = (float)($order->remaining_amount ?? max(0, (float)($order->total ?? 0) - $depositAmount));
$canCustomerConfirmReceipt = !$isCancelled && ($order->status ?? '') === 'shipped';
$needsRemainingPayment = $remainingAmount > 0 && !in_array($paymentStatus, ['paid', 'refunded', 'cancelled'], true);
$receiptConfirmText = $needsRemainingPayment
    ? 'Xác nhận đã nhận hàng và thanh toán phần còn lại ' . number_format($remainingAmount, 0, ',', '.') . 'đ'
    : 'Xác nhận đã nhận hàng';
$progressStep = match ($order->status ?? 'pending') {
    'cancelled' => 0,
    'confirmed' => 2,
    'preparing' => 3,
    'packed' => 4,
    'shipped' => 5,
    'completed' => 6,
    default => 1,
};
$statusMessage = match ($order->status ?? 'pending') {
    'completed' => 'Đơn hàng đã giao thành công. Cảm ơn bạn đã mua nông sản tại Aethera.',
    'shipped' => 'Đơn hàng đang trên đường vận chuyển. Hành trình bên dưới sẽ được cập nhật theo từng chặng.',
    'packed' => 'Đơn hàng đã được đóng gói và sẵn sàng bàn giao cho đơn vị vận chuyển.',
    'preparing' => 'Kho đang chuẩn bị hàng theo từng lô và kiểm tra tồn kho.',
    'confirmed' => 'Đơn hàng đã được xác nhận và chuyển sang bước chuẩn bị.',
    'cancelled' => 'Đơn hàng đã hủy. Nếu đã thanh toán, trạng thái hoàn tiền sẽ hiển thị trong thông tin thanh toán.',
    default => 'Đơn hàng đã được ghi nhận và đang chờ kho xử lý xuất hàng.',
};
?>

<style>
:root {
    --od-bg: #f3fbf7;
    --od-card: #ffffff;
    --od-border: #cfe9db;
    --od-green: #059669;
    --od-green-dark: #047857;
    --od-green-soft: #e8f8ef;
    --od-text: #0f172a;
    --od-muted: #64748b;
    --od-danger: #dc2626;
}

body {
    background: radial-gradient(circle at top left, #dff7eb 0, #f7fbf8 38%, #f3fbf7 100%);
    color: var(--od-text);
    font-family: 'Be Vietnam Pro', 'Inter', Arial, sans-serif;
}

.order-detail-page {
    width: min(1180px, calc(100% - 32px));
    margin: 0 auto;
    padding: 26px 0 52px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    color: var(--od-green-dark);
    font-weight: 900;
    text-decoration: none;
}

.od-card {
    background: var(--od-card);
    border: 1px solid var(--od-border);
    border-radius: 18px;
    box-shadow: 0 18px 42px rgba(6, 78, 59, .09);
}

.order-hero {
    overflow: hidden;
    margin-bottom: 18px;
}

.hero-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 18px;
    padding: 24px 28px;
    background: linear-gradient(135deg, #065f46, #06b67b);
    color: #ffffff;
}

.hero-top h1 {
    margin: 0;
    font-size: clamp(1.6rem, 3vw, 2.25rem);
    font-weight: 950;
    letter-spacing: -.02em;
}

.hero-top p {
    margin: 8px 0 0;
    color: rgba(255, 255, 255, .82);
    font-weight: 700;
}

.hero-badges {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.status-pill,
.payment-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 999px;
    font-weight: 900;
    background: rgba(255, 255, 255, .16);
    border: 1px solid rgba(255, 255, 255, .28);
    color: #ffffff;
}

.status-pill.cancelled {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #fecaca;
}

.hero-body {
    padding: 24px 28px 26px;
}

.order-progress {
    position: relative;
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 14px;
    margin-top: 8px;
}

.order-progress::before {
    content: "";
    position: absolute;
    left: 12%;
    right: 12%;
    top: 25px;
    height: 4px;
    border-radius: 999px;
    background: #dbeee4;
}

.progress-step {
    position: relative;
    z-index: 1;
    display: grid;
    justify-items: center;
    gap: 8px;
    text-align: center;
}

.progress-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #ffffff;
    border: 3px solid #dbeee4;
    color: #94a3b8;
    font-size: 1.15rem;
}

.progress-step.is-done .progress-icon,
.progress-step.is-active .progress-icon {
    background: var(--od-green);
    border-color: #a7f3d0;
    color: #ffffff;
    box-shadow: 0 10px 22px rgba(5, 150, 105, .23);
}

.order-progress.cancel-progress {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    max-width: 640px;
    margin: 8px auto 0;
}

.order-progress.cancel-progress::before {
    left: 24%;
    right: 24%;
    background: #fecaca;
}

.progress-step.is-cancelled .progress-icon {
    background: #dc2626;
    border-color: #fecaca;
    color: #ffffff;
    box-shadow: 0 10px 22px rgba(220, 38, 38, .18);
}

.progress-step.is-cancelled span {
    color: #b91c1c;
}

.progress-step span {
    color: var(--od-text);
    font-weight: 900;
}

.progress-step small {
    color: var(--od-muted);
    font-weight: 700;
}

.tracking-card {
    padding: 22px;
    margin-bottom: 18px;
}

.cancel-card {
    padding: 22px;
    margin-bottom: 18px;
    border-color: #fecaca;
    background: linear-gradient(135deg, #fff7f7, #ffffff);
}

.cancel-card .cancel-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 14px;
}

.cancel-card h2 {
    margin: 0;
    color: #991b1b;
    font-size: 1.25rem;
    font-weight: 950;
}

.cancel-card p {
    margin: 6px 0 0;
    color: var(--od-muted);
    font-weight: 700;
}

.cancel-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 13px;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    font-weight: 950;
    white-space: nowrap;
}

.section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
}

.section-head h2 {
    margin: 0;
    color: var(--od-text);
    font-size: 1.25rem;
    font-weight: 950;
}

.section-head p {
    margin: 6px 0 0;
    color: var(--od-muted);
    font-weight: 650;
}

.route-kind {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 13px;
    border-radius: 999px;
    background: var(--od-green-soft);
    color: var(--od-green-dark);
    border: 1px solid #a7f3d0;
    font-size: .86rem;
    font-weight: 950;
    white-space: nowrap;
}

.route-map {
    display: grid;
    grid-template-columns: repeat(var(--route-count, 5), minmax(0, 1fr));
    gap: 10px;
    padding: 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #f0fdf4, #ffffff);
    border: 1px solid #dbeee4;
}

.route-node {
    position: relative;
    display: grid;
    justify-items: center;
    align-content: start;
    gap: 8px;
    min-height: 86px;
    text-align: center;
    color: var(--od-text);
    font-size: .82rem;
    font-weight: 900;
}

.route-node:not(:last-child)::after {
    content: "";
    position: absolute;
    top: 20px;
    left: calc(50% + 22px);
    width: calc(100% - 34px);
    border-top: 3px solid #10b981;
    opacity: .55;
}

.route-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #ffffff;
    background: var(--od-green);
    box-shadow: 0 10px 20px rgba(5,150,105,.2);
}

.route-node.route-destination .route-icon {
    background: #0f766e;
}

.tracking-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin: 14px 0;
}

.meta-chip {
    border: 1px solid #dbeee4;
    border-radius: 14px;
    padding: 12px;
    background: #fbfefd;
}

.meta-chip small {
    display: block;
    color: var(--od-muted);
    font-weight: 900;
    text-transform: uppercase;
    font-size: .68rem;
}

.meta-chip strong {
    display: block;
    margin-top: 4px;
    color: var(--od-text);
    font-weight: 950;
}

.shipping-timeline {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.shipping-step {
    border: 1px solid #e2eee8;
    border-radius: 15px;
    padding: 14px;
    background: #ffffff;
}

.shipping-step.done {
    border-color: #a7f3d0;
    background: #ecfdf5;
}

.shipping-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #e2e8f0;
    color: #64748b;
    margin-bottom: 9px;
}

.shipping-step.done .shipping-dot {
    background: var(--od-green);
    color: #fff;
}

.shipping-step strong {
    display: block;
    color: var(--od-text);
    line-height: 1.25;
}

.shipping-step p {
    margin: 7px 0 9px;
    color: var(--od-muted);
    font-size: .84rem;
    line-height: 1.45;
}

.shipping-step small {
    color: var(--od-green-dark);
    font-weight: 900;
}

.track-empty {
    padding: 15px;
    border: 1px dashed #9bd8bd;
    border-radius: 14px;
    background: #f8fffb;
    color: #475569;
    font-weight: 800;
}

.event-card {
    padding: 20px;
    margin: 18px 0;
}

.order-event-list {
    display: grid;
    gap: 10px;
}

.order-event-row {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
    padding: 12px;
    border: 1px solid #e2eee8;
    border-radius: 14px;
    background: #fbfefd;
}

.order-event-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: var(--od-green-soft);
    color: var(--od-green-dark);
}

.order-event-row strong {
    display: block;
    color: var(--od-text);
    line-height: 1.3;
}

.order-event-row p {
    margin: 4px 0 0;
    color: var(--od-muted);
    font-weight: 700;
    line-height: 1.45;
}

.order-event-row time {
    color: var(--od-muted);
    font-size: .82rem;
    font-weight: 900;
    white-space: nowrap;
}

.detail-grid {
    display: grid;
    grid-template-columns: minmax(260px, .38fr) minmax(0, 1fr);
    gap: 18px;
}

.side-stack {
    display: grid;
    gap: 18px;
    align-content: start;
}

.panel-card {
    padding: 20px;
}

.panel-title {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0 0 14px;
    color: var(--od-text);
    font-size: 1rem;
    font-weight: 950;
}

.info-box {
    border-bottom: 1px solid #e8f3ee;
    padding: 0 0 12px;
    margin-bottom: 12px;
}

.info-box:last-child {
    border-bottom: 0;
    margin-bottom: 0;
    padding-bottom: 0;
}

.info-label {
    display: block;
    color: var(--od-muted);
    font-size: .72rem;
    font-weight: 950;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.info-box p {
    color: var(--od-text);
}

.total-box {
    padding: 20px;
    color: #ffffff;
    background: linear-gradient(135deg, #047857, #10b981);
}

.total-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    font-weight: 750;
}

.total-row.final {
    padding-top: 12px;
    border-top: 1px dashed rgba(255,255,255,.35);
    margin-bottom: 0;
    font-size: 1.08rem;
    font-weight: 950;
}

.btn-cancel {
    width: 100%;
    min-height: 44px;
    margin-top: 14px;
    border: 0;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    font-weight: 950;
}

.receipt-action {
    margin-top: 14px;
    padding: 13px;
    border-radius: 16px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .28);
}

.receipt-action p {
    margin: 0 0 10px;
    color: rgba(255, 255, 255, .9);
    font-weight: 750;
    line-height: 1.45;
}

.receipt-action strong {
    display: block;
    margin-bottom: 4px;
    color: #ffffff;
    font-weight: 950;
}

.btn-receipt {
    width: 100%;
    min-height: 44px;
    border: 0;
    border-radius: 999px;
    background: #ffffff;
    color: var(--od-green-dark);
    font-weight: 950;
    box-shadow: 0 10px 22px rgba(4, 120, 87, .22);
}

.receipt-done-note {
    margin-top: 14px;
    padding: 12px 13px;
    border-radius: 14px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .28);
    color: #ffffff;
    font-weight: 900;
}

.products-card {
    overflow: hidden;
}

.products-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    border-bottom: 1px solid #e8f3ee;
}

.products-head h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 950;
}

.product-table {
    width: 100%;
    margin: 0;
}

.product-table thead th {
    background: #f0fdf4;
    color: #475569;
    font-size: .74rem;
    font-weight: 950;
    text-transform: uppercase;
    border: 0;
    padding: 12px 16px;
}

.product-table td {
    padding: 14px 16px;
    vertical-align: middle;
    border-color: #edf4f0;
}

.product-img {
    width: 62px;
    height: 62px;
    border-radius: 13px;
    object-fit: cover;
    border: 1px solid #e2eee8;
}

@media (max-width: 991px) {
    .hero-top,
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .hero-badges {
        justify-content: flex-start;
    }

    .tracking-meta,
    .shipping-timeline {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .order-detail-page {
        width: min(100% - 20px, 1180px);
        padding-top: 18px;
    }

    .hero-top,
    .hero-body,
    .tracking-card,
    .cancel-card,
    .panel-card,
    .total-box {
        padding: 18px;
    }

    .order-progress,
    .route-map {
        grid-template-columns: 1fr;
    }

    .order-progress::before,
    .route-node:not(:last-child)::after {
        display: none;
    }

    .section-head {
        flex-direction: column;
    }
}
</style>

<div class="order-detail-page">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> <?= htmlspecialchars($backLabel) ?>
    </a>

    <section class="od-card order-hero">
        <div class="hero-top">
            <div>
                <h1>Đơn hàng #<?= $orderId ?></h1>
                <p><?= FormatHelper::escape($statusMessage) ?></p>
            </div>
            <div class="hero-badges">
                <span class="status-pill <?= htmlspecialchars($statusMeta[2]) ?>">
                    <i class="fas <?= htmlspecialchars($statusMeta[1]) ?>"></i>
                    <?= htmlspecialchars($statusMeta[0]) ?>
                </span>
                <span class="payment-pill">
                    <i class="fas fa-credit-card"></i>
                    <?= FormatHelper::escape($paymentLabel) ?>
                </span>
            </div>
        </div>
        <div class="hero-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <strong>Ngày đặt: <?= FormatHelper::dateTime($order->created_at ?? null) ?></strong>
                <span class="text-muted fw-bold"><?= $isInternational ? 'Đơn quốc tế' : 'Đơn nội địa' ?></span>
            </div>
            <?php if ($isCancelled): ?>
                <div class="order-progress cancel-progress">
                    <div class="progress-step is-done">
                        <div class="progress-icon"><i class="fas fa-clock"></i></div>
                        <span>Chờ xử lý</span>
                        <small>Đơn đã được ghi nhận</small>
                    </div>
                    <div class="progress-step is-cancelled">
                        <div class="progress-icon"><i class="fas fa-xmark"></i></div>
                        <span>Đã hủy đơn</span>
                        <small><?= FormatHelper::escape($paymentLabel) ?></small>
                    </div>
                </div>
            <?php else: ?>
                <div class="order-progress">
                    <?php
                    $steps = [
                        [1, 'Chờ xử lý', 'Kho tiếp nhận đơn', 'fa-clock'],
                        [2, 'Đã xác nhận', 'Đơn được xác nhận', 'fa-circle-check'],
                        [3, 'Chuẩn bị', 'Kho đang soạn hàng', 'fa-box-open'],
                        [4, 'Đóng gói', 'Đơn đã xuất kho', 'fa-box'],
                        [5, 'Đang giao', 'Đơn đang vận chuyển', 'fa-truck-fast'],
                        [6, 'Hoàn tất', 'Khách đã nhận hàng', 'fa-check'],
                    ];
                    foreach ($steps as $step):
                        $stepClass = $progressStep > $step[0] ? 'is-done' : ($progressStep === $step[0] ? 'is-active' : '');
                    ?>
                        <div class="progress-step <?= $stepClass ?>">
                            <div class="progress-icon"><i class="fas <?= $step[3] ?>"></i></div>
                            <span><?= htmlspecialchars($step[1]) ?></span>
                            <small><?= htmlspecialchars($step[2]) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($isCancelled): ?>
        <section class="od-card cancel-card">
            <div class="cancel-head">
                <div>
                    <h2><i class="fas fa-ban me-2"></i>Đơn hàng đã bị hủy</h2>
                    <p>Quy trình giao nhận đã dừng ở bước chờ xử lý. Đơn này không xuất kho và không tạo tuyến vận chuyển.</p>
                </div>
                <span class="cancel-badge">
                    <i class="fas fa-circle-xmark"></i>
                    Không giao hàng
                </span>
            </div>
            <div class="tracking-meta">
                <div class="meta-chip">
                    <small>Trạng thái đơn</small>
                    <strong>Đã hủy</strong>
                </div>
                <div class="meta-chip">
                    <small>Thanh toán</small>
                    <strong><?= FormatHelper::escape($paymentLabel) ?></strong>
                </div>
                <div class="meta-chip">
                    <small><?= htmlspecialchars($cancelAmountLabel) ?></small>
                    <strong><?= number_format($cancelAmount, 0, ',', '.') ?>đ</strong>
                </div>
            </div>
        </section>
    <?php else: ?>
    <section class="od-card tracking-card">
        <div class="section-head">
            <div>
                <h2><i class="fas fa-route me-2 text-success"></i>Theo dõi vận chuyển</h2>
                <p>Khách hàng sẽ nhìn thấy hành trình này giống trang theo dõi đơn: tuyến dự kiến luôn hiện, timeline cập nhật sau khi kho xuất hàng.</p>
            </div>
            <span class="route-kind">
                <i class="fa-solid <?= !$hasLeftWarehouse ? 'fa-warehouse' : ($isInternational ? 'fa-globe' : 'fa-truck-fast') ?>"></i>
                <?= !$hasLeftWarehouse ? 'Đang ở kho' : ($isInternational ? 'Tuyến quốc tế' : 'Tuyến nội địa') ?>
            </span>
        </div>

        <div class="route-map" style="--route-count: <?= max(1, count($routeNodes)) ?>">
            <?php foreach ($routeNodes as $index => $node): ?>
                <div class="route-node <?= $hasLeftWarehouse && $index === count($routeNodes) - 1 ? 'route-destination' : '' ?>">
                    <span class="route-icon"><i class="fa-solid <?= htmlspecialchars($node[1]) ?>"></i></span>
                    <span><?= htmlspecialchars($node[0]) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tracking-meta">
            <div class="meta-chip">
                <small>Đơn vị vận chuyển</small>
                    <strong><?= FormatHelper::escape($carrier) ?></strong>
            </div>
            <div class="meta-chip">
                <small>Dịch vụ</small>
                <strong><?= FormatHelper::escape($method) ?></strong>
            </div>
            <div class="meta-chip">
                <small>Tracking</small>
                <strong><?= $trackingCode !== '' ? FormatHelper::escape($trackingCode) : 'Chưa cấp mã' ?></strong>
            </div>
        </div>

        <?php if (empty($shipmentTimeline)): ?>
            <div class="track-empty">
                <?= $hasLeftWarehouse
                    ? 'Timeline chi tiết sẽ xuất hiện sau khi kho xuất hàng và bàn giao cho ' . FormatHelper::escape($carrier) . '.'
                    : 'Đơn đang chờ xử lý tại Kho Aethera. Khi admin xuất kho, tuyến vận chuyển sẽ bắt đầu cập nhật.' ?>
            </div>
        <?php else: ?>
            <div class="shipping-timeline">
                <?php foreach ($shipmentTimeline as $event): ?>
                    <?php $done = (int)($event->is_completed ?? 0) === 1; ?>
                    <div class="shipping-step <?= $done ? 'done' : '' ?>">
                        <div class="shipping-dot">
                            <i class="fa-solid <?= $done ? 'fa-check' : 'fa-clock' ?>"></i>
                        </div>
                        <strong><?= FormatHelper::escape($event->title ?? '') ?></strong>
                        <p><?= FormatHelper::escape($event->description ?? '') ?></p>
                        <small>
                            <?= FormatHelper::escape($event->location ?? '') ?>
                            <?php if (!empty($event->event_time)): ?>
                                · <?= FormatHelper::dateTime($event->event_time, 'd/m H:i', '') ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="od-card event-card">
        <div class="section-head">
            <div>
                <h2><i class="fas fa-clock-rotate-left me-2 text-success"></i>Lịch sử xử lý đơn</h2>
                <p>Mọi bước tạo đơn, thanh toán, xuất kho, vận chuyển, hủy đơn và hoàn tiền được gom về một lịch sử chung theo mã đơn.</p>
            </div>
        </div>
        <?php if (!empty($orderEvents)): ?>
            <div class="order-event-list">
                <?php foreach ($orderEvents as $event): ?>
                    <?php
                        $eventType = (string)($event->event_type ?? '');
                        $eventIcon = match ($eventType) {
                            'payment_confirmed', 'deposit_received' => 'fa-credit-card',
                            'inventory_exported' => 'fa-warehouse',
                            'inventory_returned' => 'fa-rotate-left',
                            'shipment_created', 'shipment_step_completed', 'shipment_updated' => 'fa-route',
                            'order_cancelled' => 'fa-ban',
                            'refund_recorded' => 'fa-money-bill-transfer',
                            'order_completed' => 'fa-circle-check',
                            default => 'fa-file-lines',
                        };
                    ?>
                    <div class="order-event-row">
                        <span class="order-event-icon"><i class="fa-solid <?= htmlspecialchars($eventIcon) ?>"></i></span>
                        <div>
                            <strong><?= FormatHelper::escape($event->title ?? $eventType) ?></strong>
                            <?php if (!empty($event->note)): ?>
                                <p><?= FormatHelper::escape($event->note) ?></p>
                            <?php endif; ?>
                        </div>
                        <time><?= !empty($event->created_at) ? FormatHelper::dateTime($event->created_at, 'd/m H:i', '') : '' ?></time>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="track-empty">Chưa có lịch sử xử lý chi tiết cho đơn này.</div>
        <?php endif; ?>
    </section>

    <div class="detail-grid">
        <aside class="side-stack">
            <section class="od-card panel-card">
                <h2 class="panel-title"><i class="fas fa-location-dot text-success"></i>Thông tin nhận hàng</h2>
                <div class="info-box">
                    <span class="info-label">Người nhận</span>
                    <p class="fw-bold mb-0"><?= FormatHelper::escape($order->fullname ?? '') ?></p>
                </div>
                <div class="info-box">
                    <span class="info-label">Điện thoại</span>
                    <p class="fw-bold mb-0"><?= FormatHelper::escape($order->phone ?? '') ?></p>
                </div>
                <div class="info-box">
                    <span class="info-label">Địa chỉ</span>
                    <p class="small mb-0"><?= FormatHelper::escapeMultiline($order->address ?? '') ?></p>
                </div>
            </section>

            <section class="od-card total-box">
                <h2 class="panel-title text-white"><i class="fas fa-receipt"></i>Tổng thanh toán</h2>
                <div class="total-row">
                    <span>Tạm tính</span>
                    <span><?= number_format($subtotalAmount, 0, ',', '.') ?>đ</span>
                </div>
                <div class="total-row">
                    <span>Vận chuyển</span>
                    <span><?= $shippingFee > 0 ? number_format($shippingFee, 0, ',', '.') . 'đ' : 'Miễn phí' ?></span>
                </div>
                <?php if ($depositPercent > 0): ?>
                    <div class="total-row">
                        <span>Thanh toán trước <?= number_format($depositPercent, $depositPercent == (int)$depositPercent ? 0 : 2, ',', '.') ?>%</span>
                        <span><?= number_format($depositAmount, 0, ',', '.') ?>đ</span>
                    </div>
                    <?php if ($remainingAmount > 0): ?>
                        <div class="total-row">
                            <span>Còn lại</span>
                            <span><?= number_format($remainingAmount, 0, ',', '.') ?>đ</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="total-row final">
                    <span>Tổng cộng</span>
                    <span><?= number_format((float)($order->total ?? 0), 0, ',', '.') ?>đ</span>
                </div>

                <?php if ($canCustomerConfirmReceipt): ?>
                    <div class="receipt-action">
                        <strong><i class="fas fa-handshake me-2"></i>Đơn đã tới bước khách xác nhận</strong>
                        <p>
                            <?= $needsRemainingPayment
                                ? 'Khi bạn đã nhận đủ hàng, hãy xác nhận và thanh toán phần còn lại để hoàn tất đơn.'
                                : 'Khi bạn đã nhận đủ hàng, hãy xác nhận để hoàn tất đơn.' ?>
                        </p>
                        <form method="POST" action="/webbanhang/account/confirmReceipt/<?= $orderId ?>" onsubmit="return confirm('<?= $needsRemainingPayment ? 'Xác nhận bạn đã nhận hàng và thanh toán phần còn lại?' : 'Xác nhận bạn đã nhận hàng?' ?>')">
                            <button type="submit" class="btn-receipt">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($receiptConfirmText) ?>
                            </button>
                        </form>
                    </div>
                <?php elseif (($order->status ?? '') === 'completed'): ?>
                    <div class="receipt-done-note">
                        <i class="fas fa-circle-check me-2"></i>Thanh toán đã hoàn tất và đơn đã hoàn thành. Cảm ơn bạn đã mua hàng tại Aethera!
                    </div>
                <?php endif; ?>

                <?php if (($order->status ?? '') === 'pending'): ?>
                    <form method="POST" action="/webbanhang/account/cancelOrder/<?= $orderId ?>" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này?')">
                        <button type="submit" class="btn-cancel">
                            Hủy đơn hàng
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </aside>

        <section class="od-card products-card">
            <div class="products-head">
                <h2><i class="fas fa-box me-2 text-success"></i>Sản phẩm trong đơn</h2>
                <span class="text-muted fw-bold"><?= count($order_items) ?> dòng</span>
            </div>
            <div class="table-responsive">
                <table class="table product-table">
                    <thead>
                        <tr>
                            <th>Sản phẩm</th>
                            <th class="text-center">Khối lượng</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="/webbanhang/public/uploads/<?= FormatHelper::escape($item->image ?? 'default.jpg') ?>" class="product-img" alt="<?= FormatHelper::escape($item->name ?? 'Sản phẩm') ?>">
                                        <div>
                                            <div class="fw-bold"><?= FormatHelper::escape($item->name ?? 'Sản phẩm') ?></div>
                                            <small class="text-muted">ID: #<?= (int)($item->product_id ?? 0) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center fw-bold"><?= number_format((int)($item->quantity ?? 0), 0, ',', '.') ?> kg</td>
                                <td class="text-end"><?= number_format((float)($item->price ?? 0), 0, ',', '.') ?>đ</td>
                                <td class="text-end fw-bold text-success">
                                    <?= number_format((float)($item->price ?? 0) * (int)($item->quantity ?? 0), 0, ',', '.') ?>đ
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($order_items)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Chưa có dữ liệu sản phẩm cho đơn này.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
