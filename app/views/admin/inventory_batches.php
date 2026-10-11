<?php
$products = $products ?? [];
$batches = $batches ?? [];
$warehouses = $warehouses ?? [];
$inventoryRows = $inventoryRows ?? [];
$inventoryOrders = $inventoryOrders ?? [];
$inventoryOrderItems = $inventoryOrderItems ?? [];
$inventoryLogs = $inventoryLogs ?? [];
$totalQuantity = array_sum(array_map(fn($row) => (int)($row->quantity ?? 0), $inventoryRows));
$unitLabel = 'kg';
$inventoryFlash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
$orderItemsById = [];
foreach ($inventoryOrderItems as $item) {
    $orderItemsById[(int)$item->order_id][] = [
        'product_id' => (int)($item->product_id ?? 0),
        'product_name' => (string)($item->product_name ?? 'Nông sản'),
        'category_name' => (string)($item->category_name ?? 'Chưa phân loại'),
        'quantity' => (int)($item->quantity ?? 0),
        'current_stock' => (int)($item->current_stock ?? 0),
    ];
}
$autoExportOrders = array_values(array_filter($inventoryOrders, function ($order) {
    return in_array((string)($order->status ?? 'pending'), ['pending', 'confirmed', 'preparing', 'packed'], true);
}));
?>

<style>
.sc-page { padding: 26px; background: #f4f8f6; }
.sc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; margin-bottom: 22px; }
.sc-head h1 { margin: 0; font-weight: 950; color: #052e2b; }
.sc-head p { margin: 7px 0 0; color: #64748b; font-weight: 650; line-height: 1.55; }
.sc-card { background: #fff; border: 1px solid #d9e8de; border-radius: 18px; box-shadow: 0 12px 30px rgba(6, 78, 59, .07); overflow: hidden; }
.sc-card-pad { padding: 20px; }
.sc-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
.sc-stat { background: #fff; border: 1px solid #d9e8de; border-radius: 16px; padding: 16px; }
.sc-stat small { color: #64748b; font-weight: 900; text-transform: uppercase; letter-spacing: .03em; font-size: .72rem; }
.sc-stat strong { display: block; margin-top: 8px; font-size: clamp(1.32rem, 2.4vw, 1.8rem); color: #047857; font-weight: 950; }
.sc-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.sc-field label { display: block; font-size: .76rem; font-weight: 900; color: #334155; margin-bottom: 7px; text-transform: uppercase; letter-spacing: .03em; }
.sc-field input, .sc-field select { width: 100%; min-height: 42px; border: 1px solid #d2e4d8; border-radius: 12px; padding: 9px 12px; outline: 0; }
.sc-field input:focus, .sc-field select:focus { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, .16); }
.adjust-grid { display: grid; grid-template-columns: 1.15fr 1fr .9fr 1.25fr .75fr auto; gap: 14px; align-items: end; }
.sc-btn { border: 0; border-radius: 12px; padding: 11px 16px; font-weight: 900; color: #fff; background: #059669; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
.sc-btn.secondary { background: #0f172a; }
.warehouse-edit-grid { display: grid; grid-template-columns: 1.2fr 1.4fr .7fr .7fr 1.2fr .8fr 42px; gap: 8px; align-items: end; }
.sc-table { width: 100%; border-collapse: collapse; }
.sc-table th { background: #eefbf3; color: #475569; font-size: .75rem; text-transform: uppercase; padding: 13px 14px; white-space: nowrap; letter-spacing: .03em; }
.sc-table td { padding: 14px; border-top: 1px solid #ecf3ef; vertical-align: top; }
.sc-table tbody tr:nth-child(even) td { background: #fbfefc; }
.sc-badge { display: inline-flex; align-items: center; justify-content: center; text-align: center; line-height: 1.2; border-radius: 999px; padding: 5px 10px; background: #dcfce7; color: #047857; font-size: .78rem; font-weight: 900; }
.sc-badge.export { background: #fee2e2; color: #b91c1c; }
.sc-badge.import { background: #dcfce7; color: #047857; }
.sc-alert { margin-bottom: 16px; border: 1px solid #a7f3d0; background: #ecfdf5; color: #047857; border-radius: 14px; padding: 12px 14px; font-weight: 850; }
.auto-export-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.auto-export-card { border: 1px solid #d9e8de; border-radius: 16px; background: #fbfefd; padding: 14px; display: grid; gap: 10px; }
.auto-export-card strong { color: #052e2b; font-weight: 950; }
.auto-export-meta { color: #64748b; font-size: .84rem; font-weight: 760; line-height: 1.45; }
.auto-export-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.sc-btn.light { color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; }
.sc-btn.disabled { color: #64748b; background: #f1f5f9; border: 1px solid #dbeee4; pointer-events: none; }
.order-link-field.is-hidden { display: none; }
.order-items-panel { grid-column: 1 / -1; border: 1px solid #dbeee4; border-radius: 14px; background: #fbfefd; padding: 12px 14px; }
.order-items-panel.is-hidden { display: none; }
.order-items-title { color: #052e2b; font-weight: 900; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.order-item-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.order-item-chip { border: 1px solid #dbeee4; border-radius: 12px; padding: 10px 12px; background: #fff; }
.order-item-chip strong { display: block; color: #0f172a; }
.order-item-chip span { color: #64748b; font-size: .82rem; font-weight: 800; }
.order-error { color: #b91c1c; font-weight: 900; }
@media (max-width: 1300px) { .adjust-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1100px) { .sc-grid, .sc-stats, .auto-export-list { grid-template-columns: repeat(2, minmax(0, 1fr)); } .warehouse-edit-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .sc-grid, .sc-stats, .warehouse-edit-grid, .adjust-grid, .auto-export-list { grid-template-columns: 1fr; } .sc-page { padding: 14px 10px; } .sc-table { min-width: 1000px; } }
</style>

<div class="sc-page">
    <div class="sc-head">
        <div>
            <h1><i class="fa-solid fa-warehouse me-2"></i>Kho theo lô hàng</h1>
            <p>Theo dõi tồn kho theo sản phẩm, kho lưu trữ và mã lô để phục vụ truy xuất nguồn gốc.</p>
        </div>
    </div>

    <div class="sc-stats">
        <div class="sc-stat"><small>Tổng tồn kho</small><strong><?= number_format($totalQuantity, 0, ',', '.') ?> <?= $unitLabel ?></strong></div>
        <div class="sc-stat"><small>Số lô</small><strong><?= count($batches) ?></strong></div>
        <div class="sc-stat"><small>Số kho</small><strong><?= count($warehouses) ?></strong></div>
        <div class="sc-stat"><small>Sản phẩm</small><strong><?= count($products) ?></strong></div>
    </div>

    <?php if ($inventoryFlash): ?>
        <div class="sc-alert"><i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($inventoryFlash) ?></div>
    <?php endif; ?>

    <section class="sc-card mb-4">
        <div class="sc-card-pad">
            <h5 class="fw-bold mb-2">Xuất kho theo đơn</h5>
            <p class="text-muted mb-3">Nhân viên kho kiểm đơn đã xác nhận, ưu tiên lô gần hạn hoặc lô nhập trước, trừ kho và chuyển đơn sang đã đóng gói.</p>
            <div class="auto-export-list">
                <?php foreach ($autoExportOrders as $order): ?>
                    <?php
                        $orderId = (int)($order->id ?? 0);
                        $items = $orderItemsById[$orderId] ?? [];
                        $orderStatus = (string)($order->status ?? 'pending');
                        $stockStatus = (string)($order->stock_status ?? 'unchecked');
                        $canConfirmForWarehouse = $orderStatus === 'pending';
                        $canRecheckForWarehouse = in_array($orderStatus, ['confirmed', 'preparing'], true) && in_array($stockStatus, ['unchecked', 'insufficient'], true);
                        $canAutoExport = in_array($orderStatus, ['confirmed', 'preparing'], true) && $stockStatus === 'sufficient';
                    ?>
                    <div class="auto-export-card">
                        <div>
                            <strong>#<?= $orderId ?> - <?= htmlspecialchars($order->fullname ?? 'Khách hàng') ?></strong>
                            <div class="auto-export-meta">
                                <?= htmlspecialchars(($order->delivery_scope ?? 'domestic') === 'international' ? 'Quốc tế' : 'Nội địa') ?>
                                · <?= number_format((float)($order->total ?? 0), 0, ',', '.') ?>đ
                                · Kho: <?= htmlspecialchars($stockStatus ?: 'unchecked') ?>
                            </div>
                        </div>
                        <div class="auto-export-meta">
                            <?php foreach (array_slice($items, 0, 3) as $item): ?>
                                <div><?= htmlspecialchars($item['product_name'] ?? 'Nông sản') ?> · <?= number_format((int)($item['quantity'] ?? 0), 0, ',', '.') ?> kg</div>
                            <?php endforeach; ?>
                            <?php if (count($items) > 3): ?>
                                <div>+<?= count($items) - 3 ?> sản phẩm khác</div>
                            <?php endif; ?>
                        </div>
                        <div class="auto-export-actions">
                            <?php if ($canAutoExport): ?>
                                <form method="POST" action="/webbanhang/admin/exportOrderInventory" class="m-0">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="redirect" value="/webbanhang/admin/inventory">
                                    <button class="sc-btn" type="submit"><i class="fa-solid fa-dolly"></i> Xuất kho theo đơn</button>
                                </form>
                            <?php elseif ($canConfirmForWarehouse): ?>
                                <form method="POST" action="/webbanhang/admin/confirmOrderForInventory" class="m-0">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="redirect" value="/webbanhang/admin/inventory">
                                    <button class="sc-btn" type="submit"><i class="fa-solid fa-clipboard-check"></i> Xác nhận & kiểm kho</button>
                                </form>
                            <?php elseif ($canRecheckForWarehouse): ?>
                                <form method="POST" action="/webbanhang/admin/confirmOrderForInventory" class="m-0">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="redirect" value="/webbanhang/admin/inventory">
                                    <button class="sc-btn light" type="submit"><i class="fa-solid fa-rotate"></i> Kiểm kho lại</button>
                                </form>
                            <?php elseif ($orderStatus === 'packed'): ?>
                                <span class="sc-btn light disabled"><i class="fa-solid fa-box"></i> Đã xuất kho</span>
                            <?php elseif ($stockStatus === 'insufficient'): ?>
                                <span class="sc-btn disabled"><i class="fa-solid fa-triangle-exclamation"></i> Thiếu hàng</span>
                            <?php else: ?>
                                <span class="sc-btn disabled"><i class="fa-solid fa-clock"></i> Chờ kiểm kho</span>
                            <?php endif; ?>
                            <a class="sc-btn light" href="/webbanhang/admin/orderDetail/<?= $orderId ?>"><i class="fa-solid fa-eye"></i> Chi tiết</a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($autoExportOrders)): ?>
                    <div class="auto-export-card">
                        <strong>Không có đơn cần xuất kho</strong>
                        <div class="auto-export-meta">Khi đơn được xác nhận và đủ hàng, hệ thống sẽ hiển thị tại đây để kho xử lý.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="sc-card mb-4">
        <div class="sc-card-pad">
            <h5 class="fw-bold mb-3">Thêm kho lưu trữ</h5>
            <form method="POST" action="/webbanhang/admin/saveWarehouse">
                <div class="sc-grid">
                    <div class="sc-field"><label>Tên kho</label><input name="name" required placeholder="Kho lạnh Aethera"></div>
                    <div class="sc-field"><label>Vị trí</label><input name="location" placeholder="TP.HCM"></div>
                    <div class="sc-field"><label>Nhiệt độ chuẩn (°C)</label><input type="number" step="0.1" name="temperature" placeholder="8"></div>
                    <div class="sc-field"><label>Độ ẩm chuẩn (%)</label><input type="number" step="0.1" name="humidity" placeholder="72"></div>
                    <div class="sc-field"><label>Phương pháp bảo quản</label><input name="storage_method" placeholder="Kho lạnh, thông gió..."></div>
                    <input type="hidden" name="status" value="active">
                    <div class="sc-field d-flex align-items-end"><button class="sc-btn" type="submit"><i class="fa-solid fa-plus"></i> Thêm kho</button></div>
                </div>
            </form>
        </div>
    </section>

    <section class="sc-card mb-4">
        <div class="sc-card-pad">
            <h5 class="fw-bold mb-3">Điều chỉnh thông tin kho hàng</h5>
            <div class="d-grid gap-2">
                <?php foreach ($warehouses as $warehouse): ?>
                    <form method="POST" action="/webbanhang/admin/saveWarehouse" class="warehouse-edit-grid">
                        <input type="hidden" name="id" value="<?= (int)$warehouse->id ?>">
                        <div class="sc-field"><label>Tên kho</label><input name="name" value="<?= htmlspecialchars($warehouse->name ?? '') ?>" required></div>
                        <div class="sc-field"><label>Vị trí</label><input name="location" value="<?= htmlspecialchars($warehouse->location ?? '') ?>"></div>
                        <div class="sc-field"><label>Nhiệt độ (°C)</label><input type="number" step="0.1" name="temperature" value="<?= htmlspecialchars((string)($warehouse->temperature ?? '')) ?>"></div>
                        <div class="sc-field"><label>Độ ẩm (%)</label><input type="number" step="0.1" name="humidity" value="<?= htmlspecialchars((string)($warehouse->humidity ?? '')) ?>"></div>
                        <div class="sc-field"><label>Bảo quản</label><input name="storage_method" value="<?= htmlspecialchars($warehouse->storage_method ?? '') ?>"></div>
                        <div class="sc-field">
                            <label>Trạng thái</label>
                            <select name="status">
                                <option value="active" <?= ($warehouse->status ?? 'active') === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                                <option value="inactive" <?= ($warehouse->status ?? 'active') === 'inactive' ? 'selected' : '' ?>>Tạm ngưng</option>
                            </select>
                        </div>
                        <button class="sc-btn" type="submit" title="Lưu kho"><i class="fa-solid fa-check"></i></button>
                    </form>
                <?php endforeach; ?>
                <?php if (empty($warehouses)): ?>
                    <p class="text-muted mb-0">Chưa có kho lưu trữ.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="sc-card">
        <div class="table-responsive">
            <table class="sc-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Mã lô</th>
                        <th>Kho</th>
                        <th>Khối lượng (kg)</th>
                        <th>Thu hoạch</th>
                        <th>Bảo quản</th>
                        <th>Giá trị ước tính</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryRows as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row->product_name ?? 'Nông sản') ?></strong><br><small class="text-muted"><?= htmlspecialchars($row->quality_standard ?? '') ?></small></td>
                            <td><span class="sc-badge"><?= htmlspecialchars($row->batch_code ?? 'N/A') ?></span></td>
                            <td><?= htmlspecialchars($row->warehouse_name ?? 'Chưa gán') ?><br><small class="text-muted"><?= htmlspecialchars($row->location ?? '') ?></small></td>
                            <td><strong><?= number_format((int)($row->quantity ?? 0), 0, ',', '.') ?> <?= htmlspecialchars($row->unit ?? $unitLabel) ?></strong></td>
                            <td><?= $row->harvest_date ? date('d/m/Y', strtotime($row->harvest_date)) : '-' ?></td>
                            <td><?= htmlspecialchars($row->preservation_method ?? '') ?></td>
                            <td><?= number_format((float)($row->price ?? 0) * (int)($row->quantity ?? 0), 0, ',', '.') ?>đ</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inventoryRows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">Chưa có dữ liệu kho theo lô</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
const inventoryAdjustType = document.getElementById('inventoryAdjustType');
const inventoryOrderField = document.getElementById('inventoryOrderField');
const inventoryOrderCode = document.getElementById('inventoryOrderCode');
const inventoryOrderId = document.getElementById('inventoryOrderId');
const inventoryBatchSelect = document.getElementById('inventoryBatchSelect');
const inventoryAdjustForm = document.getElementById('inventoryAdjustForm');
const orderItemsPanel = document.getElementById('orderItemsPanel');
const orderItemsContent = document.getElementById('orderItemsContent');
const inventoryOrderItems = <?= json_encode($orderItemsById, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function normalizeOrderCode(value) {
    const match = String(value || '').match(/\d+/);
    return match ? String(parseInt(match[0], 10)) : '';
}

function syncInventoryOrderField() {
    const isExport = inventoryAdjustType && inventoryAdjustType.value === 'export';
    if (inventoryOrderField) inventoryOrderField.classList.toggle('is-hidden', !isExport);
    if (inventoryOrderCode) inventoryOrderCode.required = !!isExport;
    if (!isExport) {
        if (inventoryOrderCode) inventoryOrderCode.value = '';
        if (inventoryOrderId) inventoryOrderId.value = '';
        renderOrderItems('');
        filterBatchOptions([]);
    }
}

function renderOrderItems(orderId) {
    if (!orderItemsPanel || !orderItemsContent) return;
    const items = inventoryOrderItems[orderId] || [];

    if (!orderId) {
        orderItemsPanel.classList.add('is-hidden');
        orderItemsContent.innerHTML = '';
        return;
    }

    orderItemsPanel.classList.remove('is-hidden');
    if (!items.length) {
        orderItemsContent.innerHTML = '<div class="order-error">Không tìm thấy sản phẩm trong mã đơn này hoặc đơn đã hoàn tất/hủy.</div>';
        return;
    }

    orderItemsContent.innerHTML = items.map(item => `
        <div class="order-item-chip">
            <strong>${escapeHtml(item.product_name || 'Nông sản')}</strong>
            <span>${escapeHtml(item.category_name || 'Chưa phân loại')} · Khối lượng đặt: ${Number(item.quantity || 0).toLocaleString('vi-VN')} kg · Tồn: ${Number(item.current_stock || 0).toLocaleString('vi-VN')} kg</span>
        </div>
    `).join('');
}

function filterBatchOptions(productIds) {
    const ids = productIds.map(String);
    if (!inventoryBatchSelect) return;

    Array.from(inventoryBatchSelect.options).forEach(option => {
        if (!option.value) {
            option.hidden = false;
            return;
        }
        const productId = option.dataset.productId || '';
        option.hidden = ids.length > 0 && !ids.includes(productId);
    });

    const selected = inventoryBatchSelect.selectedOptions[0];
    if (selected && selected.hidden) {
        inventoryBatchSelect.value = '';
    }
}

function syncOrderCode() {
    const orderId = normalizeOrderCode(inventoryOrderCode?.value || '');
    if (inventoryOrderId) inventoryOrderId.value = orderId && inventoryOrderItems[orderId] ? orderId : '';
    const items = inventoryOrderItems[orderId] || [];
    renderOrderItems(orderId);
    filterBatchOptions(items.map(item => item.product_id));
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

if (inventoryAdjustType) {
    inventoryAdjustType.addEventListener('change', syncInventoryOrderField);
    syncInventoryOrderField();
}
if (inventoryOrderCode) {
    inventoryOrderCode.addEventListener('input', syncOrderCode);
    inventoryOrderCode.addEventListener('change', syncOrderCode);
}
if (inventoryAdjustForm) {
    inventoryAdjustForm.addEventListener('submit', (event) => {
        const isExport = inventoryAdjustType && inventoryAdjustType.value === 'export';
        if (isExport && (!inventoryOrderId || !inventoryOrderId.value)) {
            event.preventDefault();
            alert('Vui lòng nhập mã đơn hợp lệ trước khi xuất kho.');
        }
    });
}
const inventoryParams = new URLSearchParams(window.location.search);
const initialInventoryType = inventoryParams.get('type');
const initialInventoryOrder = normalizeOrderCode(inventoryParams.get('order_id') || '');
if (initialInventoryType === 'export' && inventoryAdjustType) {
    inventoryAdjustType.value = 'export';
    syncInventoryOrderField();
}
if (initialInventoryOrder && inventoryOrderItems[initialInventoryOrder] && inventoryOrderCode) {
    inventoryOrderCode.value = `#${initialInventoryOrder}`;
    syncOrderCode();
    inventoryAdjustForm?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
