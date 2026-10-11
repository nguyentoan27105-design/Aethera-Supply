<?php
require_once __DIR__ . '/../../helpers/UrlHelper.php';
require_once __DIR__ . '/../../helpers/FormatHelper.php';

$productName = FormatHelper::cleanText($product->name ?? 'Sản phẩm nông sản');
$categoryName = FormatHelper::cleanText($product->category_name ?? 'Nông sản');
$description = FormatHelper::cleanText($product->description ?? 'Thông tin sản phẩm đang được cập nhật.');
$imageName = trim((string)($product->image ?? ''));
$imageSrc = $imageName !== ''
    ? '/webbanhang/public/uploads/' . rawurlencode($imageName)
    : '/webbanhang/public/uploads/default-product.svg';
$stock = (int)($product->stock ?? 0);
$priceLabel = number_format((float)($product->price ?? 0), 0, ',', '.') . 'đ';
$origin = FormatHelper::cleanText($product->origin ?? 'Đang cập nhật');
$supplier = FormatHelper::cleanText($product->supplier ?? 'Aethera Supply');
$standard = FormatHelper::cleanText($product->standard ?? 'Kiểm định nội bộ');
$warehouse = FormatHelper::cleanText($product->warehouse_address ?? 'Kho Aethera');
$harvestRaw = trim((string)($product->harvest_date ?? ''));
$harvestLabel = $harvestRaw !== '' ? date('d/m/Y', strtotime($harvestRaw)) : 'Theo từng lô';
$comments = $comments ?? [];
$commentCount = is_countable($comments) ? count($comments) : 0;
$productId = (int)($product->id ?? 0);
$traceUrl = app_trace_url($productId, null, 'qrcode');
?>

<style>
.product-detail-page {
    --pd-ink: #071329;
    --pd-muted: #637163;
    --pd-green: #00875f;
    --pd-green-dark: #00583f;
    --pd-green-bright: #00c98b;
    --pd-mint: #e7fff4;
    --pd-cream: #fff7ed;
    --pd-line: #e5eee8;
    --pd-soft: #f7fbf6;
    --pd-shadow: 0 24px 70px rgba(15, 23, 42, .10);
    --pd-shadow-soft: 0 14px 36px rgba(15, 23, 42, .07);
    background:
        radial-gradient(circle at 8% 0%, rgba(0, 201, 139, .14), transparent 28%),
        radial-gradient(circle at 92% 12%, rgba(255, 197, 87, .18), transparent 30%),
        linear-gradient(180deg, #fbfff8 0%, #ffffff 58%, #f6fbf7 100%);
    color: var(--pd-ink);
    padding: 26px 0 64px;
}

.product-detail-shell {
    width: min(1240px, calc(100% - 32px));
    margin: 0 auto;
}

.detail-breadcrumb {
    min-height: 40px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #7b887e;
    font-size: .9rem;
    font-weight: 800;
}

.detail-breadcrumb a {
    color: var(--pd-green-dark);
    text-decoration: none;
}

.detail-breadcrumb span:last-child {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-back-button {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 12px 16px;
    background: #ffffff;
    border: 1px solid rgba(0, 135, 79, .16);
    border-radius: 999px;
    color: #047857;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 12px 28px rgba(4, 120, 87, .08);
    transition: transform .18s ease, background .18s ease, box-shadow .18s ease;
}

.detail-back-button:hover {
    transform: translateY(-1px);
    background: rgba(4, 120, 87, .08);
}

.detail-hero {
    display: grid;
    grid-template-columns: minmax(320px, .92fr) minmax(0, 1.08fr);
    gap: 28px;
    align-items: start;
}

.product-gallery-panel,
.product-info-panel,
.detail-comments-panel,
.detail-review-panel {
    border: 1px solid rgba(229, 238, 232, .95);
    border-radius: 28px;
    background: rgba(255, 255, 255, .92);
    box-shadow: var(--pd-shadow);
}

.product-gallery-panel {
    position: sticky;
    top: 106px;
    padding: 16px;
}

.product-image-frame {
    position: relative;
    aspect-ratio: 1 / 1;
    border-radius: 24px;
    background:
        linear-gradient(135deg, rgba(231, 255, 244, .8), rgba(255, 247, 237, .65)),
        #f5faf5;
    overflow: hidden;
}

.product-image-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .35s ease;
}

.product-gallery-panel:hover .product-image-frame img {
    transform: scale(1.035);
}

.image-badge,
.stock-badge {
    position: absolute;
    z-index: 2;
    min-height: 34px;
    padding: 0 13px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #ffffff;
    font-size: .78rem;
    font-weight: 950;
    box-shadow: 0 12px 26px rgba(0, 88, 63, .20);
}

.image-badge {
    left: 14px;
    top: 14px;
    background: var(--pd-green);
}

.stock-badge {
    right: 14px;
    bottom: 14px;
    background: rgba(7, 19, 41, .82);
    backdrop-filter: blur(10px);
}

.gallery-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}

.gallery-meta article {
    min-height: 82px;
    border: 1px solid var(--pd-line);
    border-radius: 18px;
    background: #ffffff;
    padding: 13px;
    display: grid;
    align-content: center;
    gap: 4px;
}

.gallery-meta i {
    color: var(--pd-green);
    font-size: 1rem;
}

.gallery-meta span,
.pd-meta-chip span,
.trace-copy,
.weight-hint,
.comment-date {
    color: var(--pd-muted);
}

.gallery-meta strong,
.pd-meta-chip strong {
    color: var(--pd-ink);
    font-size: .84rem;
    line-height: 1.3;
    font-weight: 900;
}

.product-info-panel {
    padding: clamp(22px, 3vw, 34px);
}

.detail-kicker {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 12px;
}

.detail-kicker span {
    min-height: 34px;
    padding: 0 14px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--pd-mint);
    color: var(--pd-green-dark);
    font-size: .82rem;
    font-weight: 950;
}

.product-info-panel h1 {
    margin: 0;
    color: var(--pd-ink);
    font-size: clamp(2rem, 4vw, 3.35rem);
    line-height: 1.02;
    font-weight: 950;
}

.price-row {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 14px;
    margin-top: 16px;
}

.product-price-main {
    color: var(--pd-green);
    font-size: clamp(1.8rem, 3vw, 2.55rem);
    line-height: 1;
    font-weight: 950;
}

.price-note {
    margin-bottom: 3px;
    color: var(--pd-muted);
    font-weight: 800;
}

.pd-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 190px), 1fr));
    gap: 12px;
    margin: 22px 0;
}

.pd-meta-chip {
    border: 1px solid var(--pd-line);
    border-radius: 18px;
    background: #ffffff;
    padding: 14px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    min-height: 78px;
}

.pd-meta-chip i {
    width: 38px;
    height: 38px;
    border-radius: 14px;
    display: inline-grid;
    place-items: center;
    flex: 0 0 auto;
    background: var(--pd-mint);
    color: var(--pd-green);
}

.pd-meta-chip span,
.pd-meta-chip strong {
    display: block;
}

.pd-meta-chip > div {
    min-width: 0;
    width: 100%;
}

.pd-meta-chip span {
    line-height: 1.25;
}

.pd-meta-chip strong {
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.description-card {
    border: 1px solid var(--pd-line);
    border-radius: 22px;
    background:
        linear-gradient(135deg, rgba(231, 255, 244, .64), rgba(255, 255, 255, .96)),
        #ffffff;
    padding: 20px;
}

.section-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 10px;
    color: var(--pd-ink);
    font-size: 1.05rem;
    font-weight: 950;
}

.section-label i {
    color: var(--pd-green);
}

.description-card p {
    margin: 0;
    color: #26382f;
    font-size: 1.02rem;
    line-height: 1.75;
    font-weight: 600;
}

.detail-action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 290px), 1fr));
    gap: 16px;
    margin-top: 16px;
}

.trace-card,
.buy-weight-card {
    border: 1px solid var(--pd-line);
    border-radius: 24px;
    background: #ffffff;
    box-shadow: var(--pd-shadow-soft);
}

.trace-card {
    padding: 18px;
    text-align: center;
}

.trace-title,
.buy-weight-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 13px;
    color: var(--pd-green-dark);
    font-size: 1rem;
    font-weight: 950;
}

.trace-title {
    justify-content: center;
}

.trace-title i,
.buy-weight-title i {
    width: 34px;
    height: 34px;
    border-radius: 13px;
    display: inline-grid;
    place-items: center;
    background: var(--pd-mint);
    color: var(--pd-green);
}

#qrcode {
    display: inline-grid;
    place-items: center;
    width: min(100%, 260px);
    aspect-ratio: 1;
    padding: 14px;
    border: 1px solid var(--pd-line);
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

#qrcode img,
#qrcode canvas {
    width: 100% !important;
    height: auto !important;
    border-radius: 12px;
}

.trace-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 12px;
    color: #047857;
    font-weight: 700;
    text-decoration: none;
    background: rgba(4, 120, 87, .08);
    border-radius: 999px;
    padding: 10px 14px;
    transition: background .18s ease, transform .18s ease;
}

.trace-link:hover {
    background: rgba(4, 120, 87, .14);
    transform: translateY(-1px);
}

.trace-copy {
    max-width: 220px;
    margin: 12px auto 0;
    font-size: .86rem;
    line-height: 1.55;
    font-weight: 700;
}

.buy-weight-card {
    padding: 18px;
}

.weight-picker {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 118px;
    gap: 10px;
}

.weight-picker .form-control,
.weight-picker .form-select {
    min-height: 54px;
    border: 1px solid #cfe5d8 !important;
    border-radius: 16px !important;
    background: #ffffff !important;
    color: var(--pd-ink) !important;
    font-size: 1.08rem;
    font-weight: 950;
    box-shadow: none !important;
}

.weight-picker .form-control:focus,
.weight-picker .form-select:focus {
    border-color: var(--pd-green-bright) !important;
    box-shadow: 0 0 0 4px rgba(0, 201, 139, .16) !important;
}

.weight-hint {
    margin-top: 11px;
    line-height: 1.58;
    font-size: .92rem;
    font-weight: 750;
}

.buy-weight-card .btn {
    min-height: 58px;
    border: 0 !important;
    border-radius: 17px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--pd-green-dark), var(--pd-green-bright)) !important;
    color: #ffffff !important;
    font-size: 1.04rem;
    font-weight: 950;
    box-shadow: 0 18px 34px rgba(0, 135, 95, .24) !important;
}

.buy-weight-card .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 22px 40px rgba(0, 135, 95, .32) !important;
}

.detail-comments-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-top: 26px;
}

.detail-comments-panel {
    padding: clamp(20px, 3vw, 28px);
}

.comments-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.comments-head h2 {
    margin: 0;
    color: var(--pd-ink);
    font-size: clamp(1.35rem, 2.2vw, 1.8rem);
    font-weight: 950;
}

.comments-count {
    min-height: 34px;
    padding: 0 13px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    background: var(--pd-mint);
    color: var(--pd-green-dark);
    font-size: .84rem;
    font-weight: 950;
    white-space: nowrap;
}

.comment-list {
    display: grid;
    gap: 12px;
}

.comment-card {
    border: 1px solid var(--pd-line);
    border-radius: 20px;
    background: #ffffff;
    padding: 16px;
}

.comment-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 8px;
}

.comment-user {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    color: var(--pd-ink);
    font-weight: 950;
}

.comment-user i {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    display: inline-grid;
    place-items: center;
    flex: 0 0 auto;
    background: var(--pd-mint);
    color: var(--pd-green);
}

.comment-date {
    font-size: .82rem;
    font-weight: 750;
    white-space: nowrap;
}

.comment-rating {
    margin-bottom: 8px;
    color: #f59e0b;
    font-size: .86rem;
}

.comment-card p {
    margin: 0;
    color: #32463b;
    line-height: 1.65;
    font-weight: 600;
}

.empty-comments {
    min-height: 142px;
    border: 1px dashed #c8dbd0;
    border-radius: 22px;
    background: #ffffff;
    display: grid;
    place-items: center;
    text-align: center;
    padding: 28px;
    color: var(--pd-muted);
    font-weight: 750;
}

.empty-comments i {
    width: 44px;
    height: 44px;
    border-radius: 16px;
    display: inline-grid;
    place-items: center;
    margin: 0 auto 10px;
    background: var(--pd-mint);
    color: var(--pd-green);
}

@media (max-width: 1100px) {
    .detail-hero,
    .detail-action-grid {
        grid-template-columns: 1fr;
    }

    .product-gallery-panel {
        position: relative;
        top: auto;
    }
}

@media (max-width: 768px) {
    .product-detail-page {
        padding-top: 18px;
    }

    .product-detail-shell {
        width: min(100% - 22px, 1240px);
    }

    .product-gallery-panel,
    .product-info-panel,
    .detail-comments-panel {
        border-radius: 22px;
    }

    .gallery-meta,
    .pd-meta-grid {
        grid-template-columns: 1fr;
    }

    .weight-picker {
        grid-template-columns: 1fr;
    }

    .comments-head,
    .comment-top {
        align-items: flex-start;
        flex-direction: column;
    }
}

/* Professional refresh for review and comment area */
.detail-comments-grid {
    margin-top: 26px;
}

.detail-comments-panel {
    border-radius: 20px;
    border: 1px solid #dbe9df;
    box-shadow: 0 14px 34px rgba(15, 23, 42, .08);
}

.comments-head {
    padding: 16px 18px;
    border-bottom: 1px solid #ebf2ed;
    margin-bottom: 16px;
}

.comments-count {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #cfe6d8;
}

.detail-review-panel {
    display: grid;
    gap: 14px;
    padding: clamp(16px, 2vw, 22px);
    border-radius: 16px;
    border: 1px solid #dbe9df;
    background: #f9fdfb;
}

.detail-review-panel .section-label {
    margin-bottom: 0;
}

.detail-review-panel .review-field {
    display: grid;
    gap: 8px;
}

.detail-review-panel .form-label {
    margin: 0;
    color: #0f172a;
}

.detail-review-panel .form-control,
.detail-review-panel .form-select {
    width: 100%;
    min-height: 46px;
    border: 1px solid #cfe3d5;
    border-radius: 12px;
    padding: 10px 12px;
}

.detail-review-panel textarea.form-control {
    min-height: 108px;
    line-height: 1.5;
    resize: vertical;
}

.detail-review-panel .form-control:focus,
.detail-review-panel .form-select:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, .15);
}

.detail-review-panel .btn {
    justify-self: start;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding-inline: 16px;
}

.detail-review-panel .review-login-copy,
.detail-review-panel p.mb-3 {
    margin: 0;
    color: #475569;
    line-height: 1.6;
    font-weight: 650;
}

@media (max-width: 768px) {
    .detail-review-panel .btn {
        width: 100%;
    }
}

.comment-card {
    border: 1px solid #dbe9df;
    border-radius: 14px;
    background: #fff;
    box-shadow: none;
}

.comment-card p {
    color: #334155;
}

.empty-comments {
    border-radius: 14px;
    border: 1px dashed #cfe3d5;
    background: #f8fcf9;
}
</style>

<main class="product-detail-page">
    <div class="product-detail-shell">
        <nav class="detail-breadcrumb" aria-label="breadcrumb">
            <a href="/webbanhang/product"><i class="fa-solid fa-store"></i> Cửa hàng</a>
            <span>/</span>
            <span><?= htmlspecialchars($productName) ?></span>
        </nav>
        <a href="/webbanhang/product" class="detail-back-button">
            <i class="fa-solid fa-arrow-left-long"></i>
            Quay lại cửa hàng
        </a>

        <section class="detail-hero">
            <aside class="product-gallery-panel" aria-label="Hình ảnh sản phẩm">
                <div class="product-image-frame">
                    <span class="image-badge"><i class="fa-solid fa-qrcode"></i> QR truy xuất</span>
                    <img src="<?= htmlspecialchars($imageSrc) ?>"
                         alt="<?= htmlspecialchars($productName) ?>"
                         onerror="this.src='https://placehold.co/720x720/f1f5f9/0f172a?text=No+Image'">
                    <span class="stock-badge"><i class="fa-solid fa-boxes-stacked"></i> Còn <?= number_format($stock, 0, ',', '.') ?> kg</span>
                </div>

                <div class="gallery-meta">
                    <article>
                        <i class="fa-solid fa-leaf"></i>
                        <span>Danh mục</span>
                        <strong><?= htmlspecialchars($categoryName) ?></strong>
                    </article>
                    <article>
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Thu hoạch</span>
                        <strong><?= htmlspecialchars($harvestLabel) ?></strong>
                    </article>
                    <article>
                        <i class="fa-solid fa-shield-heart"></i>
                        <span>Tiêu chuẩn</span>
                        <strong><?= htmlspecialchars($standard) ?></strong>
                    </article>
                </div>
            </aside>

            <section class="product-info-panel" aria-label="Thông tin sản phẩm">
                <div class="detail-kicker">
                    <span><i class="fa-solid fa-seedling"></i> Nông sản được kiểm soát</span>
                    <span><i class="fa-solid fa-truck-fast"></i> Giao nhanh toàn quốc</span>
                </div>

                <h1><?= htmlspecialchars($productName) ?></h1>

                <div class="price-row">
                    <div class="product-price-main"><?= htmlspecialchars($priceLabel) ?></div>
                    <div class="price-note">Giá theo kg, hỗ trợ chọn tấn khi mua khối lượng lớn</div>
                </div>

                <div class="pd-meta-grid">
                    <div class="pd-meta-chip">
                        <i class="fa-solid fa-location-dot"></i>
                        <div>
                            <span>Nguồn gốc</span>
                            <strong title="<?= htmlspecialchars($origin) ?>"><?= htmlspecialchars($origin) ?></strong>
                        </div>
                    </div>
                    <div class="pd-meta-chip">
                        <i class="fa-solid fa-handshake"></i>
                        <div>
                            <span>Nhà cung cấp</span>
                            <strong title="<?= htmlspecialchars($supplier) ?>"><?= htmlspecialchars($supplier) ?></strong>
                        </div>
                    </div>
                    <div class="pd-meta-chip">
                        <i class="fa-solid fa-warehouse"></i>
                        <div>
                            <span>Kho lưu trữ</span>
                            <strong title="<?= htmlspecialchars($warehouse) ?>"><?= htmlspecialchars($warehouse) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="description-card">
                    <h2 class="section-label"><i class="fa-solid fa-align-left"></i> Mô tả sản phẩm</h2>
                    <p><?= nl2br(htmlspecialchars($description)) ?></p>
                </div>

                <div class="detail-action-grid">
                    <div class="trace-card">
                        <div class="trace-title">
                            <i class="fa-solid fa-qrcode"></i>
                            <span>Truy xuất nguồn gốc</span>
                        </div>
                        <div id="qrcode"></div>
                        <br>
                        <a class="trace-link"
                            href="<?= htmlspecialchars($traceUrl, ENT_QUOTES, 'UTF-8') ?>"
                            target="_blank"
                            rel="noopener">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                Truy cập mã QR
                            </a>
                        <p class="trace-copy">Quét mã để xem thông tin lô hàng, hoặc mở liên kết trực tiếp nếu bạn đang dùng điện thoại.</p>
                    </div>

                    <form action="/webbanhang/Cart/add" method="POST" class="buy-weight-card">
                        <input type="hidden" name="product_id" value="<?= (int)$product->id ?>">
                        <div class="buy-weight-title">
                            <i class="fa-solid fa-scale-balanced"></i>
                            <span>Khối lượng muốn mua</span>
                        </div>
                        <div class="weight-picker">
                            <input type="number" name="quantity" value="1" min="1" step="1" class="form-control" aria-label="Khối lượng muốn mua" required>
                            <select name="quantity_unit" class="form-select" aria-label="Đơn vị khối lượng">
                                <option value="kg">kg</option>
                                <option value="ton">tấn</option>
                            </select>
                        </div>
                        <div class="weight-hint">
                            Tồn khả dụng: <strong><?= number_format($stock, 0, ',', '.') ?> kg</strong>.
                            Khi chọn tấn, hệ thống tự quy đổi về kg để giữ đúng tồn kho và xuất kho.
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3">
                            <i class="fa-solid fa-cart-plus"></i> Thêm vào giỏ hàng
                        </button>
                    </form>
                </div>

            </section>
        </section>

       <section class="detail-comments-grid" aria-label="Bình luận sản phẩm">
    <div class="detail-comments-panel">
        <div class="comments-head">
            <h2><i class="fa-solid fa-comments"></i> Bình luận & đánh giá</h2>
            <span class="comments-count"><?= number_format($commentCount, 0, ',', '.') ?> phản hồi</span>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
            <form action="/webbanhang/comment/add" method="POST" class="detail-review-panel mb-4">
                <input type="hidden" name="product_id" value="<?= (int)$product->id ?>">

                <h3 class="section-label">
                    <i class="fa-solid fa-star"></i>
                    Viết đánh giá của bạn
                </h3>

                <div class="review-field">
                    <label class="form-label fw-bold">Chọn số sao</label>
                    <select name="rating" class="form-select" required>
                        <option value="5">⭐⭐⭐⭐⭐ - Rất tốt</option>
                        <option value="4">⭐⭐⭐⭐ - Tốt</option>
                        <option value="3">⭐⭐⭐ - Bình thường</option>
                        <option value="2">⭐⭐ - Chưa ổn</option>
                        <option value="1">⭐ - Tệ</option>
                    </select>
                </div>

                <div class="review-field">
                    <label class="form-label fw-bold">Bình luận</label>
                    <textarea 
                        name="content" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Nhập bình luận của bạn về sản phẩm..."
                        required></textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-paper-plane"></i>
                    Gửi đánh giá
                </button>
            </form>
        <?php else: ?>
            <div class="detail-review-panel mb-4">
                <h3 class="section-label">
                    <i class="fa-solid fa-star"></i>
                    Viết đánh giá của bạn
                </h3>
                <p class="mb-3">Bạn cần đăng nhập để gửi bình luận và đánh giá sản phẩm.</p>
                <a href="/webbanhang/account/login" class="btn btn-success">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Đăng nhập
                </a>
            </div>
        <?php endif; ?>

        <?php if (!empty($comments)): ?>
            <div class="comment-list">
                <?php foreach ($comments as $c): ?>
                    <?php
                    $commentUser = FormatHelper::cleanText($c->user_name ?? $c->username ?? 'Khách hàng');
                    $rating = max(1, min(5, (int)($c->rating ?? 5)));
                    $createdAt = trim((string)($c->created_at ?? ''));
                    $createdLabel = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : 'Vừa xong';
                    ?>
                    <article class="comment-card">
                        <div class="comment-top">
                            <div class="comment-user">
                                <i class="fa-solid fa-user"></i>
                                <span><?= FormatHelper::escape($commentUser) ?></span>
                            </div>
                            <span class="comment-date"><?= htmlspecialchars($createdLabel) ?></span>
                        </div>

                        <div class="comment-rating" aria-label="<?= $rating ?> sao">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa<?= $i <= $rating ? 's' : 'r' ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>

                        <p><?= FormatHelper::escapeMultiline($c->content ?? '') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-comments">
                <div>
                    <i class="fa-regular fa-message"></i>
                    <div>Chưa có bình luận nào cho sản phẩm này.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const traceUrl = <?= json_encode($traceUrl, JSON_UNESCAPED_SLASHES) ?>;

    const qrContainer = document.getElementById("qrcode");

    if (!qrContainer || typeof QRCode === "undefined") {
        return;
    }

    const isMobile = window.matchMedia("(max-width: 640px)").matches;
    const qrSize = isMobile ? 220 : 260;

    qrContainer.innerHTML = "";

    new QRCode(qrContainer, {
        text: traceUrl,
        width: qrSize,
        height: qrSize,
        colorDark: "#00875f",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    console.log("QR Link:", traceUrl);
});
</script>
