<?php
$orders = $orders ?? [];
$orderItems = $orderItems ?? [];
$itemsByOrder = [];
foreach ($orderItems as $item) {
    $itemsByOrder[(int)($item->order_id ?? 0)][] = $item;
}

function cancelledText($value, string $fallback = 'Chưa cập nhật'): string
{
    $value = trim((string)($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

$totalValue = array_sum(array_map(fn($order) => (float)($order->total ?? 0), $orders));
$domesticCount = count(array_filter($orders, fn($order) => ($order->delivery_scope ?? 'domestic') !== 'international'));
$exportCount = max(0, count($orders) - $domesticCount);
?>

<style>
.cancelled-page { padding:24px; color:#0f172a; }
.cancelled-hero { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:16px; align-items:start; padding:20px; border:1px solid #fecaca; border-radius:16px; background:linear-gradient(120deg,#7f1d1d 0%,#b91c1c 58%,#ef4444 100%); color:#fff; box-shadow:0 18px 38px rgba(127,29,29,.2); margin-bottom:16px; }
.cancelled-hero h1 { margin:0; color:#fff; font-weight:950; letter-spacing:0; }
.cancelled-hero p { margin:7px 0 0; max-width:840px; color:rgba(255,255,255,.9); font-weight:650; line-height:1.55; }
.cancelled-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.cancelled-btn { min-height:42px; border:0; border-radius:10px; padding:0 14px; display:inline-flex; align-items:center; justify-content:center; gap:8px; background:#fff; color:#991b1b; font-weight:900; text-decoration:none; white-space:nowrap; }
.cancelled-btn.alt { background:rgba(255,255,255,.16); color:#fff; border:1px solid rgba(255,255,255,.32); }
.cancelled-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
.cancelled-stat { background:#fff; border:1px solid #fee2e2; border-radius:14px; padding:16px; box-shadow:0 12px 28px rgba(127,29,29,.06); }
.cancelled-stat span { display:block; color:#64748b; font-size:.76rem; font-weight:900; text-transform:uppercase; }
.cancelled-stat strong { display:block; margin-top:6px; color:#b91c1c; font-size:1.55rem; font-weight:950; }
.cancelled-list { display:grid; gap:12px; }
.cancelled-card { background:#fff; border:1px solid #fee2e2; border-radius:14px; box-shadow:0 12px 28px rgba(127,29,29,.06); overflow:hidden; }
.cancelled-summary { display:grid; grid-template-columns:130px 1.1fr 1fr 150px auto; gap:12px; align-items:start; padding:15px; cursor:pointer; list-style:none; }
.cancelled-summary::-webkit-details-marker { display:none; }
.cancelled-id { color:#991b1b; font-weight:950; }
.cancelled-date { display:block; color:#64748b; font-weight:750; font-size:.84rem; margin-top:3px; }
.cancelled-cell small { display:block; color:#64748b; font-size:.72rem; font-weight:900; text-transform:uppercase; margin-bottom:3px; }
.cancelled-cell strong { display:block; color:#0f172a; font-weight:950; overflow-wrap:anywhere; }
.cancelled-cell span { display:block; color:#64748b; font-weight:760; overflow-wrap:anywhere; }
.cancelled-pill { display:inline-flex; align-items:center; gap:6px; min-height:30px; border-radius:999px; padding:0 10px; background:#fef2f2; color:#b91c1c; font-weight:950; font-size:.78rem; white-space:nowrap; }
.cancelled-body { border-top:1px solid #fee2e2; padding:15px; background:#fffafa; }
.cancelled-products { display:grid; gap:8px; margin-bottom:12px; }
.cancelled-product { display:grid; grid-template-columns:minmax(0,1fr) 80px 130px; gap:10px; align-items:center; padding:10px 12px; border:1px solid #fee2e2; border-radius:10px; background:#fff; }
.cancelled-product strong { overflow-wrap:anywhere; }
.cancelled-note { color:#64748b; font-weight:760; }
.cancelled-empty { padding:36px; text-align:center; background:#fff; border:1px solid #fee2e2; border-radius:14px; color:#64748b; font-weight:850; }
@media(max-width:980px){ .cancelled-hero,.cancelled-summary{grid-template-columns:1fr;} .cancelled-actions{justify-content:flex-start;} .cancelled-stats{grid-template-columns:1fr 1fr;} }
@media(max-width:620px){ .cancelled-page{padding:16px 10px;} .cancelled-stats,.cancelled-product{grid-template-columns:1fr;} }
</style>

<div class="cancelled-page">
    <section class="cancelled-hero">
        <div>
            <h1><i class="fa-solid fa-ban me-2"></i>Đơn đã hủy</h1>
            <p>Theo dõi riêng các đơn đã hủy để đối chiếu hoàn kho, thanh toán, lý do hủy và báo cáo rủi ro vận hành.</p>
        </div>
        <div class="cancelled-actions">
            <a class="cancelled-btn" href="/webbanhang/admin/order_management"><i class="fa-solid fa-file-invoice"></i> Đơn nội địa</a>
            <a class="cancelled-btn alt" href="/webbanhang/admin/export_orders"><i class="fa-solid fa-truck-fast"></i> Đơn xuất khẩu</a>
        </div>
    </section>

    <div class="cancelled-stats">
        <div class="cancelled-stat"><span>Tổng đơn hủy</span><strong><?= number_format(count($orders), 0, ',', '.') ?></strong></div>
        <div class="cancelled-stat"><span>Nội địa</span><strong><?= number_format($domesticCount, 0, ',', '.') ?></strong></div>
        <div class="cancelled-stat"><span>Xuất khẩu</span><strong><?= number_format($exportCount, 0, ',', '.') ?></strong></div>
        <div class="cancelled-stat"><span>Giá trị đơn</span><strong><?= number_format($totalValue, 0, ',', '.') ?>đ</strong></div>
    </div>

    <section class="cancelled-list">
        <?php foreach ($orders as $order): ?>
            <?php
                $orderId = (int)($order->id ?? 0);
                $items = $itemsByOrder[$orderId] ?? [];
                $scope = ($order->delivery_scope ?? 'domestic') === 'international' ? 'Xuất khẩu' : 'Nội địa';
                $cancelDate = $order->cancelled_at ?? $order->updated_at ?? $order->created_at ?? '';
                $reason = cancelledText($order->cancel_reason ?? $order->note ?? $order->notes ?? '', 'Chưa ghi lý do hủy');
            ?>
            <details class="cancelled-card">
                <summary class="cancelled-summary">
                    <div>
                        <div class="cancelled-id">Đơn #<?= $orderId ?></div>
                        <span class="cancelled-date"><?= $cancelDate ? date('d/m/Y H:i', strtotime((string)$cancelDate)) : '-' ?></span>
                    </div>
                    <div class="cancelled-cell">
                        <small>Khách hàng</small>
                        <strong><?= htmlspecialchars(cancelledText($order->customer_name ?? $order->fullname ?? '', 'Khách hàng')) ?></strong>
                        <span><?= htmlspecialchars(cancelledText($order->customer_phone ?? $order->phone ?? '', 'Chưa có SĐT')) ?></span>
                    </div>
                    <div class="cancelled-cell">
                        <small>Phạm vi / Thanh toán</small>
                        <strong><?= htmlspecialchars($scope) ?></strong>
                        <span><?= htmlspecialchars(cancelledText(($order->payment_method ?? '') . ' / ' . ($order->payment_status ?? ''), 'Chưa rõ thanh toán')) ?></span>
                    </div>
                    <div class="cancelled-cell">
                        <small>Tổng tiền</small>
                        <strong><?= number_format((float)($order->total ?? 0), 0, ',', '.') ?>đ</strong>
                        <span><?= number_format(count($items), 0, ',', '.') ?> dòng sản phẩm</span>
                    </div>
                    <span class="cancelled-pill"><i class="fa-solid fa-ban"></i>Đã hủy</span>
                </summary>
                <div class="cancelled-body">
                    <div class="cancelled-products">
                        <?php foreach ($items as $item): ?>
                            <div class="cancelled-product">
                                <strong><?= htmlspecialchars(cancelledText($item->product_name ?? $item->name ?? '', 'Sản phẩm')) ?></strong>
                                <span>SL: <?= number_format((float)($item->quantity ?? 0), 0, ',', '.') ?></span>
                                <span><?= number_format((float)($item->price ?? 0), 0, ',', '.') ?>đ</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <div class="cancelled-note">Không có dòng sản phẩm để hiển thị.</div>
                        <?php endif; ?>
                    </div>
                    <div class="cancelled-note">
                        <strong>Lý do hủy:</strong> <?= htmlspecialchars($reason) ?>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>

        <?php if (!$orders): ?>
            <div class="cancelled-empty">
                <i class="fa-solid fa-circle-check fa-2x d-block mb-2"></i>
                Chưa có đơn hàng bị hủy.
            </div>
        <?php endif; ?>
    </section>
</div>
