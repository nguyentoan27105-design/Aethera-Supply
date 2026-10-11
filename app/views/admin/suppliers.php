<?php
$suppliers = $suppliers ?? [];

function supplierStatusMeta($status): array
{
    return match ($status ?? 'active') {
        'pending' => ['Chờ duyệt', 'pending', 'fa-clock'],
        'inactive' => ['Tạm ngưng', 'inactive', 'fa-circle-pause'],
        default => ['Đang hợp tác', 'active', 'fa-circle-check'],
    };
}

function supplierValue($value, string $fallback = 'Chưa cập nhật'): string
{
    $value = trim((string)($value ?? ''));
    return $value !== '' ? $value : $fallback;
}

$totalSuppliers = count($suppliers);
$activeSuppliers = count(array_filter($suppliers, fn($supplier) => ($supplier->status ?? 'active') === 'active'));
$inactiveSuppliers = max(0, $totalSuppliers - $activeSuppliers);
$totalArea = array_sum(array_map(fn($supplier) => (float)($supplier->cultivation_area ?? 0), $suppliers));
$totalSupplyQuantity = array_sum(array_map(fn($supplier) => (float)($supplier->supply_quantity_kg ?? 0), $suppliers));
?>

<style>
.supplier-page { padding:24px; color:#0f172a; }
.supplier-head { margin-bottom:18px; }
.supplier-hero {
    border:1px solid #0e6a57;
    border-radius:18px;
    background:linear-gradient(125deg,#062f2a 0%,#0a5f4d 58%,#10906e 100%);
    color:#fff;
    padding:20px;
    box-shadow:0 18px 38px rgba(6,47,42,.24);
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:14px;
    align-items:start;
}
.supplier-head h1 { margin:0; color:#fff; font-weight:950; letter-spacing:0; }
.supplier-head p { margin:7px 0 0; color:rgba(255,255,255,.9); font-weight:620; line-height:1.55; max-width:820px; }
.supplier-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.supplier-btn { min-height:42px; border:0; border-radius:10px; padding:0 15px; background:#059669; color:#fff; font-weight:900; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; white-space:nowrap; }
.supplier-btn.secondary { background:#fff; color:#065f46; }
.supplier-btn.light { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.28); }
.supplier-btn.danger { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
.supplier-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:16px; }
.supplier-stat { background:#fff; border:1px solid #dbeee4; border-radius:14px; padding:17px; box-shadow:0 14px 32px rgba(6,78,59,.07); }
.supplier-stat span { color:#64748b; font-size:.78rem; font-weight:900; text-transform:uppercase; }
.supplier-stat strong { display:block; margin-top:6px; color:#047857; font-size:1.7rem; font-weight:950; }
.supplier-panel { background:#fff; border:1px solid #dbeee4; border-radius:16px; box-shadow:0 14px 32px rgba(6,78,59,.07); overflow:hidden; margin-bottom:16px; }
.supplier-panel-head { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:16px 18px; border-bottom:1px solid #ecf3ef; background:#f8fffb; }
.supplier-panel-head strong { color:#052e2b; font-size:1.02rem; font-weight:950; display:flex; align-items:center; gap:9px; }
.supplier-panel-head span { color:#64748b; font-weight:800; font-size:.86rem; }
.supplier-panel-body { padding:18px; }
.supplier-form-grid { display:grid; grid-template-columns:1.1fr .8fr .85fr .7fr; gap:12px; align-items:start; }
.supplier-form-grid .wide { grid-column:span 2; }
.supplier-field label { display:block; color:#64748b; font-size:.72rem; font-weight:900; text-transform:uppercase; margin-bottom:6px; }
.supplier-field input,.supplier-field textarea,.supplier-field select { width:100%; min-height:42px; border:1px solid #d6e7dd; border-radius:10px; padding:9px 12px; color:#0f172a; font-weight:750; background:#fff; outline:0; }
.supplier-field textarea { min-height:86px; resize:vertical; line-height:1.45; }
.supplier-field input:focus,.supplier-field textarea:focus,.supplier-field select:focus { border-color:#059669; box-shadow:0 0 0 4px rgba(5,150,105,.12); }
.supplier-form-actions { margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.supplier-toolbar { display:grid; grid-template-columns:minmax(220px,1fr) 190px; gap:12px; align-items:center; margin-bottom:12px; }
.supplier-search { position:relative; }
.supplier-search i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#64748b; }
.supplier-search input,.supplier-toolbar select { width:100%; min-height:42px; border:1px solid #d6e7dd; border-radius:10px; padding:0 13px; color:#0f172a; font-weight:800; background:#fff; outline:0; }
.supplier-search input { padding-left:39px; }
.supplier-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.supplier-card { background:#fff; border:1px solid #dbeee4; border-radius:14px; box-shadow:0 14px 30px rgba(6,78,59,.07); overflow:hidden; }
.supplier-card-main { padding:16px; display:grid; gap:14px; }
.supplier-title-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:12px; align-items:start; }
.supplier-id { display:inline-flex; align-items:center; gap:6px; color:#047857; font-weight:950; font-size:.82rem; margin-bottom:5px; }
.supplier-title { color:#052e2b; font-weight:950; font-size:1.08rem; overflow-wrap:anywhere; }
.supplier-subtitle { color:#64748b; font-weight:800; margin-top:3px; overflow-wrap:anywhere; }
.status-pill { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:7px 10px; font-size:.78rem; font-weight:950; white-space:nowrap; }
.status-pill.active { background:#dcfce7; color:#047857; }
.status-pill.inactive { background:#f1f5f9; color:#475569; }
.status-pill.pending { background:#fef3c7; color:#b45309; }
.supplier-info-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
.supplier-info { border-top:1px solid #ecf3ef; padding-top:10px; min-width:0; }
.supplier-info small { display:block; color:#64748b; font-size:.7rem; font-weight:900; text-transform:uppercase; margin-bottom:3px; }
.supplier-info strong { display:block; color:#0f172a; font-weight:950; overflow-wrap:anywhere; }
.supplier-info span { display:block; color:#64748b; font-weight:750; overflow-wrap:anywhere; }
.product-tags { display:flex; gap:6px; flex-wrap:wrap; }
.product-tag { display:inline-flex; align-items:center; min-height:28px; border-radius:999px; padding:0 9px; background:#ecfdf5; color:#047857; font-weight:900; font-size:.78rem; }
.supplier-edit { border-top:1px solid #ecf3ef; }
.supplier-edit summary { min-height:48px; list-style:none; cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:0 16px; color:#052e2b; font-weight:950; background:#fbfefd; }
.supplier-edit summary::-webkit-details-marker { display:none; }
.supplier-edit[open] summary { border-bottom:1px solid #ecf3ef; }
.supplier-edit[open] .supplier-caret { transform:rotate(180deg); }
.supplier-edit-body { padding:16px; }
.supplier-edit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.supplier-edit-grid .full { grid-column:1 / -1; }
.supplier-empty { grid-column:1 / -1; background:#fff; border:1px solid #dbeee4; border-radius:14px; padding:40px; color:#64748b; text-align:center; font-weight:850; }
@media(max-width:1180px){ .supplier-form-grid{grid-template-columns:repeat(2,minmax(0,1fr));} .supplier-list{grid-template-columns:1fr;} .supplier-hero{grid-template-columns:1fr;} .supplier-actions{justify-content:flex-start;} }
@media(max-width:760px){ .supplier-page{padding:16px 10px;} .supplier-stats,.supplier-toolbar,.supplier-form-grid,.supplier-edit-grid,.supplier-info-grid{grid-template-columns:1fr;} .supplier-actions,.supplier-form-actions{justify-content:flex-start;} .supplier-form-grid .wide{grid-column:auto;} .supplier-title-row{grid-template-columns:1fr;} }
</style>

<div class="supplier-page">
    <div class="supplier-head">
        <div class="supplier-hero">
            <div>
                <h1><i class="fa-solid fa-handshake-angle me-2"></i>Quản lý nhà cung cấp</h1>
                <p>Hồ sơ vùng trồng, trang trại, hợp tác xã và nguồn cung nông sản được gom về một màn hình dễ quét và dễ cập nhật.</p>
            </div>
        </div>
    </div>

    <div class="supplier-stats">
        <div class="supplier-stat"><span>Tổng nhà cung cấp</span><strong><?= number_format($totalSuppliers, 0, ',', '.') ?></strong></div>
        <div class="supplier-stat"><span>Đang hợp tác</span><strong><?= number_format($activeSuppliers, 0, ',', '.') ?></strong></div>
        <div class="supplier-stat"><span>Lượng cung ứng</span><strong><?= number_format($totalSupplyQuantity, 0, ',', '.') ?> kg</strong></div>
        <div class="supplier-stat"><span>Diện tích ghi nhận</span><strong><?= number_format($totalArea, 2, ',', '.') ?> ha</strong></div>
    </div>

    <section class="supplier-panel">
        <div class="supplier-panel-head">
            <div>
                <strong><i class="fa-solid fa-plus"></i>Thêm nhà cung cấp</strong>
                <span>Tạo hồ sơ nguồn cung mới</span>
            </div>
        </div>
        <div class="supplier-panel-body">
            <form method="POST" action="/webbanhang/admin/saveSupplier">
                <div class="supplier-form-grid">
                    <div class="supplier-field">
                        <label>Tên nhà cung cấp</label>
                        <input name="name" required placeholder="HTX Xoài cát Hòa Lộc">
                    </div>
                    <div class="supplier-field">
                        <label>Người liên hệ</label>
                        <input name="contact_person" placeholder="Nguyễn Văn A">
                    </div>
                    <div class="supplier-field">
                        <label>Số điện thoại</label>
                        <input name="phone" placeholder="090...">
                    </div>
                    <div class="supplier-field">
                        <label>Trạng thái</label>
                        <select name="status">
                            <option value="active">Đang hợp tác</option>
                            <option value="pending">Chờ duyệt</option>
                            <option value="inactive">Tạm ngưng</option>
                        </select>
                    </div>
                    <div class="supplier-field">
                        <label>Vùng trồng</label>
                        <input name="farm_location" placeholder="Cái Bè, Tiền Giang">
                    </div>
                    <div class="supplier-field">
                        <label>Diện tích canh tác (ha)</label>
                        <input type="number" step="0.01" min="0" name="cultivation_area" value="0">
                    </div>
                    <div class="supplier-field">
                        <label>Số lượng cung ứng (kg)</label>
                        <input type="number" step="1" min="0" name="supply_quantity_kg" value="10">
                    </div>
                    <div class="supplier-field wide">
                        <label>Nông sản cung cấp</label>
                        <input name="products" placeholder="Xoài, cam, bưởi">
                    </div>
                    <div class="supplier-field wide">
                        <label>Địa chỉ</label>
                        <textarea name="address" placeholder="Địa chỉ liên hệ"></textarea>
                    </div>
                </div>
                <div class="supplier-form-actions">
                    <button class="supplier-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Lưu nhà cung cấp</button>
                </div>
            </form>
        </div>
    </section>

    <div class="supplier-toolbar">
        <div class="supplier-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="supplierSearch" type="search" placeholder="Tìm theo tên, vùng trồng, nông sản, số điện thoại">
        </div>
        <select id="supplierStatusFilter" aria-label="Lọc trạng thái nhà cung cấp">
            <option value="">Tất cả trạng thái</option>
            <option value="active">Đang hợp tác</option>
            <option value="pending">Chờ duyệt</option>
            <option value="inactive">Tạm ngưng</option>
        </select>
    </div>

    <section class="supplier-list" id="supplierList">
        <?php foreach ($suppliers as $supplier): ?>
            <?php
                [$statusLabel, $statusClass, $statusIcon] = supplierStatusMeta($supplier->status ?? 'active');
                $products = array_values(array_filter(array_map('trim', preg_split('/[,;\\n]+/', (string)($supplier->products ?? '')))));
                $searchText = strtolower(trim(implode(' ', [
                    $supplier->name ?? '',
                    $supplier->phone ?? '',
                    $supplier->contact_person ?? '',
                    $supplier->farm_location ?? '',
                    $supplier->address ?? '',
                    $supplier->products ?? '',
                    $supplier->supply_quantity_kg ?? '',
                ])));
            ?>
            <article class="supplier-card" data-status="<?= htmlspecialchars($supplier->status ?? 'active') ?>" data-search="<?= htmlspecialchars($searchText) ?>">
                <div class="supplier-card-main">
                    <div class="supplier-title-row">
                        <div>
                            <span class="supplier-id"><i class="fa-solid fa-hashtag"></i><?= (int)$supplier->id ?></span>
                            <div class="supplier-title"><?= htmlspecialchars(supplierValue($supplier->name ?? '', 'Nhà cung cấp chưa đặt tên')) ?></div>
                            <div class="supplier-subtitle"><?= htmlspecialchars(supplierValue($supplier->farm_location ?? $supplier->address ?? '', 'Chưa cập nhật vùng trồng')) ?></div>
                        </div>
                        <span class="status-pill <?= htmlspecialchars($statusClass) ?>"><i class="fa-solid <?= htmlspecialchars($statusIcon) ?>"></i><?= htmlspecialchars($statusLabel) ?></span>
                    </div>

                    <div class="supplier-info-grid">
                        <div class="supplier-info">
                            <small>Liên hệ</small>
                            <strong><?= htmlspecialchars(supplierValue($supplier->contact_person ?? '', 'Chưa có người liên hệ')) ?></strong>
                            <span><?= htmlspecialchars(supplierValue($supplier->phone ?? '', 'Chưa có số điện thoại')) ?></span>
                        </div>
                        <div class="supplier-info">
                            <small>Số lượng cung ứng</small>
                            <strong><?= number_format((float)($supplier->supply_quantity_kg ?? 0), 0, ',', '.') ?> kg</strong>
                            <span><?= htmlspecialchars(supplierValue($supplier->address ?? '', 'Chưa cập nhật địa chỉ')) ?></span>
                        </div>
                        <div class="supplier-info">
                            <small>Diện tích</small>
                            <strong><?= number_format((float)($supplier->cultivation_area ?? 0), 2, ',', '.') ?> ha</strong>
                            <span><?= htmlspecialchars(supplierValue($supplier->farm_location ?? '', 'Chưa cập nhật vùng trồng')) ?></span>
                        </div>
                    </div>

                    <div class="supplier-info">
                        <small>Nông sản</small>
                        <?php if ($products): ?>
                            <div class="product-tags">
                                <?php foreach (array_slice($products, 0, 4) as $product): ?>
                                    <span class="product-tag"><?= htmlspecialchars($product) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($products) > 4): ?>
                                    <span class="product-tag">+<?= count($products) - 4 ?></span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <strong>Chưa cập nhật nông sản</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <details class="supplier-edit">
                    <summary>
                        <span><i class="fa-solid fa-pen-to-square me-2"></i>Cập nhật hồ sơ</span>
                        <i class="fa-solid fa-chevron-down supplier-caret"></i>
                    </summary>
                    <div class="supplier-edit-body">
                        <form method="POST" action="/webbanhang/admin/saveSupplier">
                            <input type="hidden" name="id" value="<?= (int)$supplier->id ?>">
                            <?= CsrfHelper::field() ?>
                            <div class="supplier-edit-grid">
                                <div class="supplier-field">
                                    <label>Tên nhà cung cấp</label>
                                    <input name="name" value="<?= htmlspecialchars($supplier->name ?? '') ?>" required>
                                </div>
                                <div class="supplier-field">
                                    <label>Trạng thái</label>
                                    <select name="status">
                                        <option value="active" <?= ($supplier->status ?? 'active') === 'active' ? 'selected' : '' ?>>Đang hợp tác</option>
                                        <option value="pending" <?= ($supplier->status ?? 'active') === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                                        <option value="inactive" <?= ($supplier->status ?? 'active') === 'inactive' ? 'selected' : '' ?>>Tạm ngưng</option>
                                    </select>
                                </div>
                                <div class="supplier-field">
                                    <label>Người liên hệ</label>
                                    <input name="contact_person" value="<?= htmlspecialchars($supplier->contact_person ?? '') ?>">
                                </div>
                                <div class="supplier-field">
                                    <label>Số điện thoại</label>
                                    <input name="phone" value="<?= htmlspecialchars($supplier->phone ?? '') ?>">
                                </div>
                                <div class="supplier-field">
                                    <label>Vùng trồng</label>
                                    <input name="farm_location" value="<?= htmlspecialchars($supplier->farm_location ?? '') ?>">
                                </div>
                                <div class="supplier-field">
                                    <label>Diện tích (ha)</label>
                                    <input type="number" step="0.01" min="0" name="cultivation_area" value="<?= htmlspecialchars($supplier->cultivation_area ?? 0) ?>">
                                </div>
                                <div class="supplier-field">
                                    <label>Số lượng cung ứng (kg)</label>
                                    <input type="number" step="1" min="0" name="supply_quantity_kg" value="<?= htmlspecialchars($supplier->supply_quantity_kg ?? 10) ?>">
                                </div>
                                <div class="supplier-field full">
                                    <label>Nông sản cung cấp</label>
                                    <input name="products" value="<?= htmlspecialchars($supplier->products ?? '') ?>">
                                </div>
                                <div class="supplier-field full">
                                    <label>Địa chỉ</label>
                                    <textarea name="address"><?= htmlspecialchars($supplier->address ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="supplier-form-actions">
                                <?php if (($supplier->status ?? 'active') === 'pending'): ?>
                                    <button class="supplier-btn secondary" type="submit" formaction="/webbanhang/admin/approveSupplier/<?= (int)$supplier->id ?>" formmethod="POST">
                                        <i class="fa-solid fa-user-check"></i> Duyệt nhà cung cấp
                                    </button>
                                <?php endif; ?>
                                <button class="supplier-btn" type="submit"><i class="fa-solid fa-check"></i> Lưu cập nhật</button>
                                <button class="supplier-btn danger" type="submit" formaction="/webbanhang/admin/deleteSupplier/<?= (int)$supplier->id ?>" formmethod="POST" onclick="return confirm('Xóa hoặc tạm ngưng nhà cung cấp này?')" title="Xóa hoặc tạm ngưng">
                                    <i class="fa-solid fa-trash"></i> Xóa/Tạm ngưng
                                </button>
                            </div>
                        </form>
                    </div>
                </details>
            </article>
        <?php endforeach; ?>

        <div class="supplier-empty" id="supplierEmpty" style="<?= empty($suppliers) ? '' : 'display:none' ?>">
            <i class="fa-solid fa-box-open fa-2x d-block mb-2"></i>
            Chưa có nhà cung cấp phù hợp.
        </div>
    </section>
</div>

<script>
const supplierSearch = document.getElementById('supplierSearch');
const supplierStatusFilter = document.getElementById('supplierStatusFilter');
const supplierCards = Array.from(document.querySelectorAll('.supplier-card'));
const supplierEmpty = document.getElementById('supplierEmpty');

function normalizeSupplierText(value) {
    return (value || '').toString().trim().toLowerCase();
}

function syncSupplierFilter() {
    const keyword = normalizeSupplierText(supplierSearch?.value || '');
    const status = supplierStatusFilter?.value || '';
    let visible = 0;

    supplierCards.forEach(card => {
        const matchesKeyword = !keyword || normalizeSupplierText(card.dataset.search || '').includes(keyword);
        const matchesStatus = !status || (card.dataset.status || 'active') === status;
        const show = matchesKeyword && matchesStatus;
        card.style.display = show ? '' : 'none';
        if (show) visible += 1;
    });

    if (supplierEmpty) {
        supplierEmpty.style.display = visible === 0 ? '' : 'none';
    }
}

supplierSearch?.addEventListener('input', syncSupplierFilter);
supplierStatusFilter?.addEventListener('change', syncSupplierFilter);
</script>
