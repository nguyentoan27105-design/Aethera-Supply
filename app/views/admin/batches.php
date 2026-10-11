<?php
require_once __DIR__ . '/../../helpers/UrlHelper.php';

$products = $products ?? [];
$categories = $categories ?? [];
$suppliers = $suppliers ?? [];
$warehouses = $warehouses ?? [];
$batches = $batches ?? [];
$supplierOrders = $supplierOrders ?? [];
$supplierPaymentLogs = $supplierPaymentLogs ?? [];
$pendingSupplierOrders = array_values(array_filter($supplierOrders, static function ($order) {
    $status = strtolower((string)($order->status ?? 'pending'));
    return empty($order->batch_id) && in_array($status, ['pending'], true);
}));
$batchFlash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if (!function_exists('adminSupplierOrderMeta')) {
    function adminSupplierOrderMeta($status): array {
        $status = strtolower((string)$status);
        return [
            'pending' => ['Chờ nhà cung cấp', 'pending', 'Admin đã tạo đơn, nhà cung cấp cần điền phiếu lô.'],
            'supplier_submitted' => ['Chờ kho xác nhận', 'submitted', 'Nhà cung cấp đã gửi phiếu lô, kho cần kiểm số lượng và giá nhập.'],
            'warehouse_confirmed' => ['Đã nhập kho', 'confirmed', 'Kho đã xác nhận nhập, hàng đã vào tồn kho và đang chờ thanh toán.'],
            'completed' => ['Hoàn tất nhập', 'confirmed', 'Đơn nhập đã hoàn tất nghiệp vụ kho.'],
            'paid' => ['Đã thanh toán', 'paid', 'Admin đã thanh toán đủ tiền nhập cho nhà cung cấp.'],
            'rejected' => ['Từ chối', 'rejected', 'Phiếu lô không được duyệt.'],
            'cancelled' => ['Đã hủy', 'rejected', 'Đơn nhập đã bị hủy.'],
        ][$status] ?? ['Đang xử lý', 'pending', 'Đơn nhập đang được xử lý.'];
    }
}

if (!function_exists('adminSupplierMoney')) {
    function adminSupplierMoney($value): string {
        return number_format((float)$value, 0, ',', '.') . 'đ';
    }
}

if (!function_exists('adminSupplierQuantity')) {
    function adminSupplierQuantity($value): string {
        $value = (float)$value;
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
?>

<style>
.sc-page { width: min(1500px, 100%); margin: 0 auto; padding: 26px; background: #f4f8f6; }
.sc-head { margin-bottom: 22px; }
.sc-hero {
    border: 1px solid #0e6a57;
    border-radius: 18px;
    background: linear-gradient(125deg, #062f2a 0%, #0a5f4d 58%, #10906e 100%);
    color: #fff;
    box-shadow: 0 18px 38px rgba(6, 47, 42, .24);
    padding: 20px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 14px;
}
.sc-head h1 { margin: 0; font-weight: 950; color: #fff; }
.sc-head p { margin: 7px 0 0; color: rgba(255,255,255,.9); font-weight: 620; line-height: 1.55; max-width: 780px; }
.sc-card { background: #fff; border: 1px solid #d9e8de; border-radius: 18px; box-shadow: 0 12px 30px rgba(6, 78, 59, .07); overflow: hidden; }
.sc-card-pad { padding: 20px; }
.sc-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.sc-field.wide { grid-column: span 2; }
.sc-field label { display: block; font-size: .76rem; font-weight: 900; color: #334155; margin-bottom: 7px; text-transform: uppercase; letter-spacing: .03em; }
.sc-field input, .sc-field textarea, .sc-field select { width: 100%; min-height: 42px; border: 1px solid #d2e4d8; border-radius: 12px; padding: 9px 12px; outline: 0; }
.sc-field input:focus, .sc-field textarea:focus, .sc-field select:focus { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, .16); }
.sc-field textarea { min-height: 76px; resize: vertical; }
.sc-hint { display: block; margin-top: 6px; color: #64748b; font-size: .72rem; font-weight: 700; line-height: 1.35; }
.sc-btn { border: 0; border-radius: 12px; padding: 11px 16px; font-weight: 900; color: #fff; background: #059669; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
.sc-btn.secondary { background: #0f172a; }
.sc-table { width: 100%; border-collapse: collapse; }
.sc-table th { background: #eefbf3; color: #475569; font-size: .75rem; text-transform: uppercase; padding: 13px 14px; white-space: nowrap; letter-spacing: .03em; }
.sc-table td { padding: 14px; border-top: 1px solid #ecf3ef; vertical-align: top; }
.sc-table tbody tr:nth-child(even) td { background: #fbfefc; }
.sc-badge { display: inline-flex; align-items: center; justify-content: center; text-align: center; line-height: 1.2; border-radius: 999px; padding: 5px 10px; background: #dcfce7; color: #047857; font-size: .78rem; font-weight: 900; }
.sc-badge.recalled { background: #fee2e2; color: #b91c1c; }
.sc-badge.empty { background: #f1f5f9; color: #475569; }
.sc-badge.pending { background: #fff7ed; color: #c2410c; }
.sc-badge.submitted { background: #eff6ff; color: #2563eb; }
.sc-badge.confirmed { background: #ecfdf5; color: #047857; }
.sc-badge.paid { background: #e0f2fe; color: #0369a1; }
.sc-badge.rejected { background: #fef2f2; color: #b91c1c; }
.qr-link { font-family: Consolas, monospace; font-size: .78rem; color: #047857; word-break: break-all; }
.batch-actions { display: flex; flex-direction: column; gap: 8px; min-width: 150px; }
.batch-actions input { width: 100%; min-height: 36px; border: 1px solid #d2e4d8; border-radius: 10px; padding: 7px 9px; font-size: .82rem; }
.sc-danger-btn { border: 0; border-radius: 10px; min-height: 36px; padding: 8px 12px; color: #b91c1c; background: #fee2e2; font-weight: 900; display: inline-flex; align-items: center; justify-content: center; gap: 7px; }
.sc-danger-btn:hover { background: #fecaca; }
.sc-flash { margin-bottom: 16px; border: 1px solid #bbf7d0; background: #ecfdf5; color: #047857; border-radius: 14px; padding: 12px 14px; font-weight: 900; }
.supplier-order-create { display: grid; grid-template-columns: minmax(190px, 1.15fr) minmax(180px, 1fr) minmax(170px, 1fr) minmax(120px, .62fr) minmax(140px, .7fr) auto; gap: 12px; align-items: end; }
.supplier-order-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.supplier-order-card { border: 1px solid #d9e8de; border-radius: 16px; background: #fbfefd; padding: 16px; display: grid; gap: 14px; }
.supplier-order-top { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; }
.supplier-order-title strong { display: block; color: #052e2b; font-size: 1rem; }
.supplier-order-title span { display: block; margin-top: 4px; color: #64748b; font-size: .82rem; font-weight: 750; }
.supplier-order-note { border-left: 3px solid #10b981; padding-left: 10px; color: #475569; font-size: .86rem; font-weight: 750; line-height: 1.45; }
.supplier-contact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.supplier-contact-card { border: 1px solid #e4efe8; border-radius: 12px; background: #fff; padding: 10px 12px; min-width: 0; }
.supplier-contact-card small { display: block; color: #64748b; font-size: .68rem; font-weight: 900; text-transform: uppercase; }
.supplier-contact-card strong { display: block; margin-top: 3px; color: #052e2b; font-weight: 950; overflow-wrap: anywhere; }
.supplier-contact-card span { display: block; margin-top: 4px; color: #475569; font-size: .82rem; font-weight: 750; overflow-wrap: anywhere; }
.supplier-order-money { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.supplier-order-money div { border: 1px solid #e4efe8; border-radius: 12px; background: #fff; padding: 10px 12px; }
.supplier-order-money small { display: block; color: #64748b; font-weight: 850; text-transform: uppercase; font-size: .68rem; }
.supplier-order-money strong { display: block; margin-top: 4px; color: #052e2b; font-weight: 950; }
.supplier-price-formula { border: 1px solid #cdeee0; border-radius: 12px; background: #f6fffa; padding: 10px 12px; color: #334155; }
.supplier-price-formula small { display: block; color: #64748b; font-size: .72rem; font-weight: 850; text-transform: uppercase; }
.supplier-price-formula strong { display: block; margin-top: 4px; color: #052e2b; font-weight: 950; }
.supplier-price-formula span { display: block; margin-top: 4px; color: #475569; font-size: .82rem; font-weight: 750; }
.supplier-order-action-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; align-items: end; }
.supplier-order-action-grid .wide { grid-column: span 2; }
.supplier-order-action-grid input, .supplier-order-action-grid select { width: 100%; min-height: 40px; border: 1px solid #d2e4d8; border-radius: 11px; padding: 8px 10px; }
.supplier-payable-preview { grid-column: 1 / -1; border: 1px dashed #9ee7c3; border-radius: 12px; background: #f8fffb; padding: 9px 11px; color: #047857; font-weight: 900; }
.supplier-order-empty { grid-column: 1 / -1; border: 1px dashed #cbd5e1; border-radius: 16px; background: #fff; padding: 28px; text-align: center; color: #64748b; font-weight: 850; }
.history-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #e4efe8; }
.history-head h5 { margin: 0; font-weight: 900; color: #052e2b; }
.history-head p { margin: 4px 0 0; color: #64748b; font-weight: 650; }
.history-action-form { display: grid; gap: 7px; min-width: 170px; }
.history-action-form input, .history-action-form select { width: 100%; min-height: 34px; border: 1px solid #d2e4d8; border-radius: 9px; padding: 6px 8px; font-size: .82rem; }
.history-action-form .sc-btn, .history-action-form .sc-danger-btn { min-height: 34px; padding: 7px 10px; justify-content: center; }
@media (max-width: 1100px) { .sc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sc-hero { grid-template-columns: 1fr; } }
@media (max-width: 1100px) { .supplier-order-list { grid-template-columns: 1fr; } .supplier-order-create { grid-template-columns: 1fr 1fr; } }
@media (max-width: 700px) { .sc-grid, .supplier-order-create, .supplier-order-action-grid, .supplier-order-money, .supplier-contact-grid { grid-template-columns: 1fr; } .supplier-order-action-grid .wide { grid-column: auto; } .sc-page { padding: 14px 10px; } .sc-table { min-width: 1350px; } }
</style>

<div class="sc-page">
    <div class="sc-head">
        <div class="sc-hero">
            <div>
                <h1><i class="fa-solid fa-seedling me-2"></i>Nhập hàng & tạo lô</h1>
                <p>Mỗi lần nhập hàng sẽ tạo một lô mới, ghi số lượng vào đúng kho và tự đồng bộ sản phẩm còn tồn lên cửa hàng.</p>
            </div>
        </div>
    </div>

    <?php if ($batchFlash): ?>
        <div class="sc-flash"><i class="fa-solid fa-circle-info me-2"></i><?= htmlspecialchars($batchFlash) ?></div>
    <?php endif; ?>

    <section class="sc-card mb-4">
        <div class="sc-card-pad">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Đơn nhập lô từ nhà cung cấp</h5>
                    <p class="text-muted mb-0">Admin tạo đơn nhập, nhà cung cấp điền phiếu lô, kho xác nhận nhập và admin thanh toán tiền nhập.</p>
                </div>
            </div>

            <form method="POST" action="/webbanhang/admin/createSupplierOrder" class="supplier-order-create mb-4">
                <div class="sc-field">
                    <label>Nhà cung cấp</label>
                    <select id="supplierOrderSupplier" name="supplier_id" required>
                        <option value="">Chọn nhà cung cấp</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <?php $supplierOptionLabel = trim(($supplier->name ?? '') . (!empty($supplier->products) ? ' · ' . $supplier->products : '') . ' · ' . number_format((float)($supplier->supply_quantity_kg ?? 0), 0, ',', '.') . ' kg'); ?>
                            <option value="<?= (int)$supplier->id ?>"><?= htmlspecialchars($supplierOptionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-field">
                    <label>Tên sản phẩm</label>
                    <select id="supplierOrderProduct" name="requested_product_name" required>
                        <option value="">Chọn nhà cung cấp trước</option>
                    </select>
                </div>
                <div class="sc-field">
                    <label>Kho dự kiến</label>
                    <select name="warehouse_id">
                        <option value="">Tự gán kho mặc định khi xác nhận</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= (int)$warehouse->id ?>"><?= htmlspecialchars($warehouse->name ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-field">
                    <label>Số kg nhập</label>
                    <input id="supplierOrderQuantity" type="number" name="requested_quantity" min="1" step="1" placeholder="10" required>
                </div>
                <div class="sc-field">
                    <label>Giá đề xuất/kg</label>
                    <input type="number" name="suggested_import_price" min="1" step="1000" placeholder="VD: 25000" required>
                </div>
                <button class="sc-btn" type="submit"><i class="fa-solid fa-file-circle-plus"></i> Tạo đơn nhập</button>
            </form>

            <p class="text-muted fw-semibold mb-0">Đơn vừa tạo và các lô đã nhập sẽ được gom trong bảng lịch sử nhập hàng bên dưới.</p>
        </div>
    </section>

    <section class="sc-card mb-4">
        <div class="sc-card-pad">
            <h5 class="fw-bold mb-3">Phiếu nhập sản phẩm mới</h5>
            <form method="POST" action="/webbanhang/admin/saveBatch" enctype="multipart/form-data">
                <div class="sc-grid">
                    <div class="sc-field wide">
                        <label>Tên sản phẩm</label>
                        <input id="batchProductName" name="product_name" list="batchProductSuggestions" required placeholder="Gõ tên sản phẩm hoặc chọn sản phẩm đã có">
                        <input id="batchProductId" type="hidden" name="product_id" value="">
                        <datalist id="batchProductSuggestions">
                            <?php foreach ($products as $product): ?>
                                <option value="<?= htmlspecialchars($product->name) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <span class="sc-hint">Chọn sản phẩm có sẵn trong danh sách; nếu là hàng mới, nhập tên sản phẩm để lưu vào cửa hàng.</span>
                    </div>
                    <div class="sc-field">
                        <label>Danh mục sản phẩm</label>
                        <select id="batchCategoryId" name="category_id">
                            <option value="">Chọn danh mục</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category->id ?>"><?= htmlspecialchars($category->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label>Thêm danh mục mới</label>
                        <input name="new_category_name" placeholder="VD: Rau nhà kính">
                        <span class="sc-hint">Nếu nhập mục này, hệ thống sẽ dùng danh mục mới thay cho danh mục đã chọn.</span>
                    </div>
                    <div class="sc-field">
                        <label>Giá bán cho khách / kg</label>
                        <input id="batchProductPrice" type="number" step="1000" min="0" name="product_price" placeholder="VD: 50000">
                        <span class="sc-hint">Giá hiển thị trên cửa hàng khi bán cho khách.</span>
                    </div>
                    <div class="sc-field">
                        <label>Ảnh sản phẩm</label>
                        <input type="file" name="product_image" accept="image/*">
                    </div>
                    <div class="sc-field wide">
                        <label>Mô tả sản phẩm</label>
                        <textarea id="batchProductDescription" name="product_description" placeholder="Mô tả ngắn để hiển thị trên cửa hàng..."></textarea>
                    </div>
                    <div class="sc-field">
                        <label>Nhà cung cấp</label>
                        <select name="supplier_id">
                            <option value="">Chọn nhà cung cấp</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <?php $supplierOptionLabel = trim(($supplier->name ?? '') . (!empty($supplier->products) ? ' · ' . $supplier->products : '') . ' · ' . number_format((float)($supplier->supply_quantity_kg ?? 0), 0, ',', '.') . ' kg'); ?>
                                <option value="<?= (int)$supplier->id ?>"><?= htmlspecialchars($supplierOptionLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sc-field">
                        <label>Kho nhập</label>
                        <select name="warehouse_id">
                            <option value="">Tự gán kho mặc định</option>
                            <?php foreach ($warehouses as $warehouse): ?>
                                <option value="<?= (int)$warehouse->id ?>"><?= htmlspecialchars($warehouse->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sc-hint">Nếu bỏ trống, hệ thống vẫn tạo lô và tự nhập tồn vào kho mặc định.</span>
                    </div>
                    <div class="sc-field">
                        <label>Mã lô</label>
                        <input name="batch_code" placeholder="Tự sinh nếu bỏ trống">
                    </div>
                    <div class="sc-field">
                        <label>Ngày gieo trồng</label>
                        <input type="date" name="planting_date">
                    </div>
                    <div class="sc-field">
                        <label>Ngày thu hoạch</label>
                        <input type="date" name="harvest_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="sc-field">
                        <label>Diện tích canh tác (ha)</label>
                        <input type="number" step="0.01" name="area" value="0">
                    </div>
                    <div class="sc-field">
                        <label>Số lượng nhập vào lô mới (kg)</label>
                        <input type="number" name="quantity" min="1" value="1" required>
                    </div>
                    <div class="sc-field">
                        <label>Giá nhập trả NCC / kg</label>
                        <input type="number" name="import_price" min="0" step="1000" placeholder="VD: 25000">
                        <span class="sc-hint">Nếu chọn nhà cung cấp, hệ thống dùng giá này để tính tiền phải trả cho lô.</span>
                    </div>
                    <div class="sc-field">
                        <label>Tiêu chuẩn chất lượng</label>
                        <input name="quality_standard" placeholder="VietGAP, GlobalGAP...">
                    </div>
                    <div class="sc-field">
                        <label>Nhiệt độ bảo quản (°C)</label>
                        <input type="number" step="0.1" name="storage_temperature" placeholder="8">
                    </div>
                    <div class="sc-field">
                        <label>Độ ẩm bảo quản (%)</label>
                        <input type="number" step="0.1" name="storage_humidity" placeholder="72">
                    </div>
                    <div class="sc-field">
                        <label>Phương pháp bảo quản</label>
                        <input name="preservation_method" placeholder="Kho lạnh, hút chân không...">
                    </div>
                    <div class="sc-field">
                        <label>Phương pháp chăm sóc</label>
                        <textarea name="cultivation_method" placeholder="Tưới nhỏ giọt, canh tác hữu cơ..."></textarea>
                    </div>
                    <div class="sc-field">
                        <label>Phân bón sử dụng</label>
                        <textarea name="fertilizer" placeholder="Phân hữu cơ, NPK..."></textarea>
                    </div>
                    <div class="sc-field">
                        <label>Thuốc BVTV</label>
                        <textarea name="pesticide" placeholder="Không sử dụng / danh sách vật tư"></textarea>
                    </div>
                    <div class="sc-field">
                        <label>Ghi chú</label>
                        <textarea name="notes" placeholder="Ghi chú kiểm định, đóng gói..."></textarea>
                    </div>
                </div>
                <input type="hidden" name="status" value="stored">
                <div class="mt-3">
                    <button class="sc-btn" type="submit"><i class="fa-solid fa-seedling"></i> Tạo sản phẩm, QR & nhập kho</button>
                </div>
            </form>

        </div>
    </section>

    <section class="sc-card">
        <div class="history-head">
            <div>
                <h5>Lịch sử nhập hàng</h5>
                <p>Gồm đơn nhập từ nhà cung cấp và các lô đã tạo.</p>
            </div>
            <span class="sc-badge"><?= number_format(count($pendingSupplierOrders) + count($batches), 0, ',', '.') ?> dòng</span>
        </div>
        <div class="table-responsive">
            <table class="sc-table">
                <thead>
                    <tr>
                        <th>Mã lô</th>
                        <th>Sản phẩm</th>
                        <th>Nhà cung cấp</th>
                        <th>Kho</th>
                        <th>Thu hoạch</th>
                        <th>Khối lượng</th>
                        <th>Bảo quản</th>
                        <th>Thanh toán NCC</th>
                        <th>QR truy xuất</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSupplierOrders as $supplierOrder): ?>
                        <?php
                            [$pendingStatusLabel, $pendingStatusClass] = adminSupplierOrderMeta($supplierOrder->status ?? 'pending');
                            $pendingQuantity = (float)($supplierOrder->quantity ?? 0);
                            $pendingPrice = (float)($supplierOrder->import_price ?? 0);
                            $pendingTotal = $pendingQuantity > 0 && $pendingPrice > 0 ? $pendingQuantity * $pendingPrice : 0;
                            $pendingContact = trim((string)($supplierOrder->supplier_contact_person ?? ''));
                            if ($pendingContact === '') {
                                $pendingContact = trim((string)($supplierOrder->supplier_name ?? 'Nhà cung cấp'));
                            }
                            $pendingPhone = trim((string)($supplierOrder->supplier_phone ?? $supplierOrder->supplier_account_phone ?? ''));
                        ?>
                        <tr>
                            <td>
                                <strong>Đơn nhập #<?= (int)$supplierOrder->id ?></strong><br>
                                <span class="sc-badge <?= htmlspecialchars($pendingStatusClass) ?>"><?= htmlspecialchars($pendingStatusLabel) ?></span>
                            </td>
                            <td><?= htmlspecialchars($supplierOrder->product_name ?? 'Chưa chọn sản phẩm') ?></td>
                            <td>
                                <?= htmlspecialchars($supplierOrder->supplier_name ?? 'Nhà cung cấp') ?><br>
                                <small class="text-muted"><?= htmlspecialchars($pendingContact) ?><?= $pendingPhone !== '' ? ' · ' . htmlspecialchars($pendingPhone) : '' ?></small>
                            </td>
                            <td><?= htmlspecialchars($supplierOrder->warehouse_name ?? 'Chưa gán') ?></td>
                            <td>-</td>
                            <td>
                                <strong><?= adminSupplierQuantity($pendingQuantity) ?> kg</strong> dự kiến<br>
                                <small class="text-muted">Chờ nhà cung cấp gửi phiếu lô</small>
                            </td>
                            <td>-</td>
                            <td>
                                <span class="sc-badge pending">Dự tính</span><br>
                                <small class="text-muted"><?= adminSupplierMoney($pendingTotal) ?></small><br>
                                <small class="text-muted"><?= $pendingPrice > 0 ? adminSupplierQuantity($pendingQuantity) . ' kg x ' . adminSupplierMoney($pendingPrice) . '/kg' : 'Chưa có giá' ?></small>
                            </td>
                            <td>-</td>
                            <td><span class="text-muted">Chờ phiếu lô</span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($batches as $batch): ?>
                        <?php
                            $batchStatus = (string)($batch->status ?? 'stored');
                            $isRecalled = $batchStatus === 'recalled';
                            $isSupplierSubmitted = $batchStatus === 'supplier_submitted';
                            $batchRemaining = (int)($batch->calculated_remaining_quantity ?? $batch->inventory_quantity ?? $batch->remaining_quantity ?? 0);
                            $isEmptyBatch = !$isRecalled && !$isSupplierSubmitted && $batchRemaining <= 0;
                            $batchBadgeClass = $isRecalled ? 'recalled' : ($isSupplierSubmitted ? 'submitted' : ($isEmptyBatch ? 'empty' : ''));
                            $batchBadgeLabel = $isRecalled ? 'Đã thu hồi' : ($isSupplierSubmitted ? 'Chờ kho' : ($isEmptyBatch ? 'Hết hàng' : 'Đang tồn'));
                            $payableAmount = (float)($batch->payable_amount ?? $batch->supplier_order_total ?? 0);
                            $paidAmount = (float)($batch->supplier_order_paid ?? 0);
                            $paymentStatus = (string)($batch->payment_status ?? 'unpaid');
                            $paymentLabel = match ($paymentStatus) {
                                'paid' => 'Đã thanh toán',
                                'partial' => 'Thanh toán một phần',
                                default => $payableAmount > 0 ? 'Chưa thanh toán' : 'Chưa tính tiền',
                            };
                            $paymentClass = match ($paymentStatus) {
                                'paid' => 'paid',
                                'partial' => 'submitted',
                                default => 'pending',
                            };
                            $batchImportPrice = (float)($batch->import_price ?? 0);
                            $batchPayQuantity = (float)($batch->confirmed_quantity ?? 0);
                            if ($batchPayQuantity <= 0) {
                                $batchPayQuantity = (float)($batch->quantity ?? 0);
                            }
                            $batchSupplierOrderStatus = (string)($batch->supplier_order_status ?? '');
                            $batchSupplierOrderId = (int)($batch->supplier_order_id ?? 0);
                            $batchRemainingPayment = max(0, $payableAmount - $paidAmount);
                            $canConfirmBatchOrder = $batchSupplierOrderStatus === 'supplier_submitted' && $batchSupplierOrderId > 0;
                            $canPayBatchOrder = in_array($batchSupplierOrderStatus, ['warehouse_confirmed', 'completed'], true) && $batchSupplierOrderId > 0 && $batchRemainingPayment > 0;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($batch->batch_code ?? '') ?></strong><br><span class="sc-badge <?= htmlspecialchars($batchBadgeClass) ?>"><?= htmlspecialchars($batchBadgeLabel) ?></span></td>
                            <td>
                                <?php if ($isRecalled): ?>
                                    <span class="text-muted">Sản phẩm đã ẩn do thu hồi</span><br>
                                    <small class="text-muted">Không hiển thị trên cửa hàng/QR</small>
                                <?php else: ?>
                                    <?= htmlspecialchars($batch->product_name ?? 'Nông sản') ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($batch->quality_standard ?? '') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($batch->supplier_name ?? 'Chưa gán') ?><br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($batch->supplier_contact_person ?? '') ?>
                                    <?= !empty($batch->supplier_phone) ? ' · ' . htmlspecialchars($batch->supplier_phone) : '' ?>
                                </small>
                            </td>
                            <td><?= htmlspecialchars($batch->warehouse_name ?? 'Chưa gán') ?></td>
                            <td>
                                <?= $batch->harvest_date ? date('d/m/Y', strtotime($batch->harvest_date)) : '-' ?><br>
                                <small class="text-muted">Gieo: <?= $batch->planting_date ? date('d/m/Y', strtotime($batch->planting_date)) : '-' ?></small>
                            </td>
                            <td>
                                <strong><?= number_format($batchRemaining, 0, ',', '.') ?> <?= htmlspecialchars($batch->unit ?? 'kg') ?></strong> còn lại<br>
                                <small class="text-muted">Tổng <?= number_format((int)($batch->quantity ?? 0), 0, ',', '.') ?> <?= htmlspecialchars($batch->unit ?? 'kg') ?></small>
                            </td>
                            <td><?= htmlspecialchars($batch->preservation_method ?? '') ?><br><small class="text-muted"><?= htmlspecialchars((string)($batch->storage_temperature ?? '')) ?>°C / <?= htmlspecialchars((string)($batch->storage_humidity ?? '')) ?>%</small></td>
                            <td>
                                <span class="sc-badge <?= htmlspecialchars($paymentClass) ?>"><?= htmlspecialchars($paymentLabel) ?></span><br>
                                <small class="text-muted"><?= adminSupplierMoney($paidAmount) ?> / <?= adminSupplierMoney($payableAmount) ?></small><br>
                                <small class="text-muted"><?= $batchImportPrice > 0 ? adminSupplierQuantity($batchPayQuantity) . ' ' . htmlspecialchars($batch->unit ?? 'kg') . ' x ' . adminSupplierMoney($batchImportPrice) . '/kg' : 'Chưa chốt giá nhập' ?></small>
                            </td>
                            <td>
                                <?php if ($isRecalled): ?>
                                    <span class="text-muted">Đã khóa truy xuất</span>
                                <?php else: ?>
                                    <?php
                                        $qrPath = (string)($batch->qr_code ?? '');
                                        $qrUrl = $qrPath !== '' && preg_match('~^https?://~i', $qrPath)
                                            ? $qrPath
                                            : app_public_url($qrPath !== '' ? $qrPath : '#');
                                    ?>
                                    <a class="qr-link" href="<?= htmlspecialchars($qrUrl) ?>" target="_blank"><?= htmlspecialchars($qrUrl) ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canConfirmBatchOrder): ?>
                                    <form method="POST" action="/webbanhang/admin/confirmSupplierOrder" class="history-action-form supplier-confirm-form">
                                        <input type="hidden" name="supplier_order_id" value="<?= $batchSupplierOrderId ?>">
                                        <input type="number" name="confirmed_quantity" min="1" value="<?= htmlspecialchars((string)max(1, $batchPayQuantity)) ?>" required>
                                        <input type="number" name="import_price" min="1" step="1000" value="<?= $batchImportPrice > 0 ? htmlspecialchars((string)$batchImportPrice) : '' ?>" placeholder="Giá nhập/kg" required>
                                        <select name="warehouse_id">
                                            <?php foreach ($warehouses as $warehouse): ?>
                                                <option value="<?= (int)$warehouse->id ?>" <?= (int)($batch->warehouse_id ?? 0) === (int)$warehouse->id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($warehouse->name ?? '') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="sc-btn" type="submit"><i class="fa-solid fa-warehouse"></i> Xác nhận</button>
                                    </form>
                                <?php elseif ($canPayBatchOrder): ?>
                                   <form method="POST" action="/webbanhang/admin/paySupplierOrder" class="history-action-form">
                                    <input type="hidden" name="supplier_order_id" value="<?= $batchSupplierOrderId ?>">

                                    <input type="hidden"
                                        name="amount"
                                        value="<?= htmlspecialchars((string)$batchRemainingPayment) ?>">

                                    <button class="sc-btn" type="submit">
                                        <i class="fa-solid fa-money-bill-transfer"></i>
                                        Thanh toán đơn hàng
                                    </button>
                                </form>
                                <?php elseif (!$isRecalled): ?>
                                    <form class="history-action-form" method="POST" action="/webbanhang/admin/deleteBatch/<?= (int)$batch->id ?>" onsubmit="return confirmBatchRecall(this);">
                                        <?= CsrfHelper::field() ?>
                                        <input name="reason" placeholder="Lý do: đối tác, QC...">
                                        <button class="sc-danger-btn" type="submit"><i class="fa-solid fa-trash"></i> Xóa lô</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Đã khóa lô</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($batches) && empty($pendingSupplierOrders)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-5">Chưa có lô hàng</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
const batchProducts = <?= json_encode(array_map(static function ($product) {
    return [
        'id' => (int)($product->id ?? 0),
        'name' => (string)($product->name ?? ''),
        'category_id' => (int)($product->category_id ?? 0),
        'price' => (float)($product->price ?? 0),
        'description' => (string)($product->description ?? ''),
    ];
}, $products), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const supplierOrderProducts = <?= json_encode(array_reduce($suppliers, static function ($carry, $supplier) {
    $products = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string)($supplier->products ?? ''))))));
    $carry[(int)($supplier->id ?? 0)] = $products;
    return $carry;
}, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const supplierOrderQuantities = <?= json_encode(array_reduce($suppliers, static function ($carry, $supplier) {
    $carry[(int)($supplier->id ?? 0)] = (float)($supplier->supply_quantity_kg ?? 0);
    return $carry;
}, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const batchProductName = document.getElementById('batchProductName');
const batchProductId = document.getElementById('batchProductId');
const batchCategoryId = document.getElementById('batchCategoryId');
const batchProductPrice = document.getElementById('batchProductPrice');
const batchProductDescription = document.getElementById('batchProductDescription');
const supplierOrderSupplier = document.getElementById('supplierOrderSupplier');
const supplierOrderProduct = document.getElementById('supplierOrderProduct');
const supplierOrderQuantity = document.getElementById('supplierOrderQuantity');

function findBatchProductByName(name) {
    const normalized = name.trim().toLocaleLowerCase('vi-VN');
    return batchProducts.find((product) => product.name.trim().toLocaleLowerCase('vi-VN') === normalized) || null;
}

function syncBatchProductFields() {
    if (!batchProductName || !batchProductId) return;
    const product = findBatchProductByName(batchProductName.value);

    if (!product) {
        batchProductId.value = '';
        return;
    }

    batchProductId.value = product.id;
    if (batchCategoryId && product.category_id) batchCategoryId.value = product.category_id;
    if (batchProductPrice && !batchProductPrice.value && product.price) batchProductPrice.value = Math.round(product.price);
    if (batchProductDescription && !batchProductDescription.value && product.description) {
        batchProductDescription.value = product.description;
    }
}

if (batchProductName) {
    batchProductName.addEventListener('input', syncBatchProductFields);
    batchProductName.addEventListener('change', syncBatchProductFields);
}

function renderSupplierOrderProducts() {
    if (!supplierOrderSupplier || !supplierOrderProduct) return;
    const products = supplierOrderProducts[supplierOrderSupplier.value] || [];
    const quantity = Number(supplierOrderQuantities[supplierOrderSupplier.value] || 0);
    supplierOrderProduct.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = products.length ? 'Chọn sản phẩm cần nhập' : 'Nhà cung cấp chưa có sản phẩm';
    supplierOrderProduct.appendChild(placeholder);

    products.forEach((productName) => {
        const option = document.createElement('option');
        option.value = productName;
        option.textContent = productName;
        supplierOrderProduct.appendChild(option);
    });

    if (supplierOrderQuantity && quantity > 0) {
        supplierOrderQuantity.value = Number.isInteger(quantity) ? String(quantity) : String(quantity.toFixed(2));
    }
}

if (supplierOrderSupplier) {
    supplierOrderSupplier.addEventListener('change', renderSupplierOrderProducts);
    renderSupplierOrderProducts();
}

const formatVnd = (value) => `${Math.max(0, Number(value) || 0).toLocaleString('vi-VN')}đ`;

document.querySelectorAll('.supplier-confirm-form').forEach((form) => {
    const quantityInput = form.querySelector('input[name="confirmed_quantity"]');
    const priceInput = form.querySelector('input[name="import_price"]');
    const preview = form.querySelector('[data-payable-preview]');

    const renderPayable = () => {
        if (!quantityInput || !priceInput || !preview) return;
        const quantity = Number(quantityInput.value) || 0;
        const price = Number(priceInput.value) || 0;
        preview.textContent = `Phải trả NCC: ${quantity.toLocaleString('vi-VN')} kg x ${formatVnd(price)}/kg = ${formatVnd(quantity * price)}`;
    };

    quantityInput?.addEventListener('input', renderPayable);
    priceInput?.addEventListener('input', renderPayable);
    renderPayable();
});

function confirmBatchRecall(form) {
    const reasonInput = form.querySelector('input[name="reason"]');
    const reason = reasonInput ? reasonInput.value.trim() : '';
    if (!reason && reasonInput) {
        reasonInput.value = 'Đối tác hoặc chất lượng lô hàng có vấn đề';
    }
    return confirm('Xóa hẳn lô hàng này khỏi hệ thống? Tồn kho và dữ liệu truy xuất theo lô sẽ được dọn khỏi danh sách.');
}
</script>
