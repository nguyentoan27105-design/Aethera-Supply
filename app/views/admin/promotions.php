<?php
$promotions = $promotions ?? [];
$products = $products ?? [];
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
?>

<style>
.promo-page { padding:24px; }
.promo-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; }
.promo-head h1 { margin:0; color:#052e2b; font-weight:950; }
.promo-head p { margin:6px 0 0; color:#64748b; font-weight:650; }
.promo-card { background:#fff; border:1px solid #dbeee4; border-radius:18px; box-shadow:0 14px 32px rgba(6,78,59,.08); overflow:hidden; scroll-margin-top:18px; }
.promo-pad { padding:22px; }
.promo-form { display:grid; grid-template-columns:1.2fr .7fr .8fr auto; gap:12px; align-items:end; }
.promo-field label { display:block; color:#334155; font-weight:900; font-size:.78rem; margin-bottom:7px; text-transform:uppercase; }
.promo-field input { width:100%; min-height:44px; border:1px solid #d6e7dd; border-radius:12px; padding:10px 12px; outline:0; font-weight:800; }
.promo-field input:focus { border-color:#059669; box-shadow:0 0 0 4px rgba(5,150,105,.12); }
.promo-btn { border:0; border-radius:12px; min-height:44px; padding:0 16px; background:#059669; color:#fff; font-weight:950; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
.promo-btn.dark { background:#0f172a; }
.promo-btn.danger { background:#dc2626; }
.promo-btn.muted { background:#64748b; }
.promo-table { width:100%; border-collapse:collapse; }
.promo-table th { background:#f0fdf4; color:#475569; font-size:.75rem; text-transform:uppercase; padding:13px 14px; white-space:nowrap; }
.promo-table td { padding:14px; border-top:1px solid #ecf3ef; vertical-align:middle; }
.code-badge { display:inline-flex; align-items:center; border-radius:12px; padding:8px 11px; background:#ecfdf5; color:#047857; font-weight:950; letter-spacing:.04em; }
.status-badge { display:inline-flex; border-radius:999px; padding:6px 10px; font-size:.78rem; font-weight:950; }
.status-badge.on { background:#dcfce7; color:#047857; }
.status-badge.off { background:#fee2e2; color:#b91c1c; }
.promo-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
.promo-flash { margin-bottom:14px; padding:12px 14px; border-radius:14px; background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; font-weight:900; }
.marketing-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:14px; }
.marketing-head h5 { margin:0; color:#052e2b; font-weight:950; }
.marketing-head p { margin:4px 0 0; color:#64748b; font-weight:650; }
.marketing-scroll { max-height:520px; overflow:auto; border-top:1px solid #ecf3ef; }
.marketing-product { display:grid; grid-template-columns:44px 64px minmax(180px,1fr) 110px 120px; gap:14px; align-items:center; padding:12px 18px; border-top:1px solid #ecf3ef; }
.marketing-product:first-child { border-top:0; }
.marketing-product input[type="checkbox"] { width:22px; height:22px; accent-color:#059669; }
.marketing-product img { width:64px; height:54px; object-fit:cover; border-radius:12px; background:#f1f5f9; }
.marketing-product strong { display:block; color:#0f172a; font-weight:950; }
.marketing-product small { color:#64748b; font-weight:750; }
.marketing-product .price { color:#047857; font-weight:950; }
.marketing-product input[type="number"] { width:100%; min-height:40px; border:1px solid #d6e7dd; border-radius:11px; padding:8px 10px; font-weight:900; }
.marketing-empty { padding:34px; color:#64748b; text-align:center; font-weight:800; }
@media(max-width:900px){ .promo-form{grid-template-columns:1fr;} .promo-table{min-width:760px;} }
@media(max-width:900px){ .marketing-product{grid-template-columns:38px 56px 1fr;}.marketing-product .price,.marketing-product input[type="number"]{grid-column:3;} }
</style>

<div class="promo-page">
    <div class="promo-head">
        <div>
            <h1><i class="fa-solid fa-tags me-2 text-success"></i>Khuyến mãi</h1>
            <p>Tạo mã giảm giá để khách nhập ở giỏ hàng trước khi thanh toán.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="promo-flash"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <section class="promo-card mb-4">
        <div class="promo-pad">
            <h5 class="fw-bold mb-3">Tạo mã khuyến mãi</h5>
            <form method="POST" action="/webbanhang/admin/savePromotion" class="promo-form">
                <div class="promo-field">
                    <label>Mã giảm giá</label>
                    <input name="code" placeholder="VD: RAUCU10" required>
                </div>
                <div class="promo-field">
                    <label>% giảm</label>
                    <input type="number" name="discount_percent" min="1" max="100" step="1" placeholder="10" required>
                </div>
                <div class="promo-field">
                    <label>Hạn dùng</label>
                    <input type="date" name="expiry_date" required>
                </div>
                <button class="promo-btn" type="submit"><i class="fa-solid fa-plus"></i> Tạo mã</button>
            </form>
        </div>
    </section>

    <section class="promo-card">
        <div class="table-responsive">
            <table class="promo-table">
                <thead>
                    <tr>
                        <th>Mã</th>
                        <th>Giảm</th>
                        <th>Hạn dùng</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promotions as $promo): ?>
                        <?php
                            $active = (int)($promo->status ?? 0) === 1;
                            $expired = !empty($promo->expiry_date) && strtotime($promo->expiry_date) < strtotime(date('Y-m-d'));
                        ?>
                        <tr>
                            <td><span class="code-badge"><?= htmlspecialchars($promo->code ?? '') ?></span></td>
                            <td><strong><?= number_format((float)($promo->discount_percent ?? 0), 0, ',', '.') ?>%</strong></td>
                            <td><?= !empty($promo->expiry_date) ? date('d/m/Y', strtotime($promo->expiry_date)) : '-' ?></td>
                            <td>
                                <span class="status-badge <?= $active && !$expired ? 'on' : 'off' ?>">
                                    <?= $expired ? 'Hết hạn' : ($active ? 'Đang dùng' : 'Tạm tắt') ?>
                                </span>
                            </td>
                            <td>
                                <div class="promo-actions">
                                    <form method="POST" action="/webbanhang/admin/togglePromotion/<?= (int)$promo->id ?>" class="m-0">
                                        <?= CsrfHelper::field() ?>
                                    <button class="promo-btn muted" type="submit">
                                        <?= $active ? 'Tắt' : 'Bật' ?>
                                    </button>
                                    </form>
                                    <form method="POST" action="/webbanhang/admin/deletePromotion/<?= (int)$promo->id ?>" class="m-0" onsubmit="return confirm('Xóa mã khuyến mãi này?')">
                                        <?= CsrfHelper::field() ?>
                                    <button class="promo-btn danger" type="submit">
                                        Xóa
                                    </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($promotions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">Chưa có mã khuyến mãi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="flash-sale-admin" class="promo-card mt-4">
        <form method="POST" action="/webbanhang/admin/saveFlashSale">
            <div class="promo-pad">
                <div class="marketing-head">
                    <div>
                        <h5><i class="fa-solid fa-fire text-success me-2"></i>Chỉnh sửa Flash Sale</h5>
                        <p>Chọn sản phẩm hiển thị ở khu Flash Sale và nhập phần trăm giảm giá cho từng món.</p>
                    </div>
                    <button class="promo-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Lưu Flash Sale</button>
                </div>
            </div>
            <div class="marketing-scroll">
                <?php foreach ($products as $product): ?>
                    <?php
                        $productId = (int)($product->id ?? 0);
                        $checked = (int)($product->is_flash_sale ?? 0) === 1;
                        $discount = max(1, (int)($product->flash_discount ?? 10));
                        $image = !empty($product->image) ? "/webbanhang/public/uploads/" . basename($product->image) : "https://placehold.co/120x90/f1f5f9/64748b?text=Aethera";
                    ?>
                    <label class="marketing-product">
                        <input type="checkbox" name="flash_product_ids[]" value="<?= $productId ?>" <?= $checked ? 'checked' : '' ?>>
                        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product->name ?? 'Sản phẩm') ?>" onerror="this.src='https://placehold.co/120x90/f1f5f9/64748b?text=Aethera'">
                        <span>
                            <strong><?= htmlspecialchars($product->name ?? 'Sản phẩm') ?></strong>
                            <small><?= htmlspecialchars($product->category_name ?? 'Chưa phân loại') ?> · Tồn: <?= number_format((int)($product->stock ?? 0), 0, ',', '.') ?></small>
                        </span>
                        <span class="price"><?= number_format((float)($product->price ?? 0), 0, ',', '.') ?>đ</span>
                        <input type="number" name="flash_discounts[<?= $productId ?>]" min="1" max="90" value="<?= $discount ?>" aria-label="Phần trăm giảm giá">
                    </label>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <div class="marketing-empty">Chưa có sản phẩm để cấu hình Flash Sale.</div>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section id="featured-products-admin" class="promo-card mt-4">
        <form method="POST" action="/webbanhang/admin/saveFeaturedProducts">
            <div class="promo-pad">
                <div class="marketing-head">
                    <div>
                        <h5><i class="fa-regular fa-bookmark text-success me-2"></i>Chỉnh sửa sản phẩm nổi bật</h5>
                        <p>Chọn các sản phẩm sẽ xuất hiện trong khu Sản phẩm nổi bật ở trang cửa hàng.</p>
                    </div>
                    <button class="promo-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Lưu nổi bật</button>
                </div>
            </div>
            <div class="marketing-scroll">
                <?php foreach ($products as $product): ?>
                    <?php
                        $productId = (int)($product->id ?? 0);
                        $checked = (int)($product->is_featured ?? 0) === 1;
                        $image = !empty($product->image) ? "/webbanhang/public/uploads/" . basename($product->image) : "https://placehold.co/120x90/f1f5f9/64748b?text=Aethera";
                    ?>
                    <label class="marketing-product">
                        <input type="checkbox" name="featured_product_ids[]" value="<?= $productId ?>" <?= $checked ? 'checked' : '' ?>>
                        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product->name ?? 'Sản phẩm') ?>" onerror="this.src='https://placehold.co/120x90/f1f5f9/64748b?text=Aethera'">
                        <span>
                            <strong><?= htmlspecialchars($product->name ?? 'Sản phẩm') ?></strong>
                            <small><?= htmlspecialchars($product->category_name ?? 'Chưa phân loại') ?> · Tồn: <?= number_format((int)($product->stock ?? 0), 0, ',', '.') ?></small>
                        </span>
                        <span class="price"><?= number_format((float)($product->price ?? 0), 0, ',', '.') ?>đ</span>
                        <small><?= $checked ? 'Đang nổi bật' : 'Chưa chọn' ?></small>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                    <div class="marketing-empty">Chưa có sản phẩm để cấu hình nổi bật.</div>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>
