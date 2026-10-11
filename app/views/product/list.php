<?php

// View rendered by ProductController::index/search.
$initialSearchKeyword = trim((string)($searchKeyword ?? ($_GET['keyword'] ?? $_GET['search'] ?? '')));
$activeVoucher = $activeVoucher ?? null;
$promoProducts = isset($products) && is_array($products) ? $products : [];
$promoFlashProducts = array_values(array_filter($promoProducts, static function ($product) {
    return (int)($product->is_flash_sale ?? 0) === 1
        && (int)($product->flash_discount ?? 0) > 0;
}));
usort($promoFlashProducts, static function ($a, $b) {
    return ((int)($b->flash_discount ?? 0) <=> (int)($a->flash_discount ?? 0))
        ?: ((int)($b->id ?? 0) <=> (int)($a->id ?? 0));
});
$promoFeaturedProducts = array_values(array_filter($promoProducts, static function ($product) {
    return (int)($product->is_featured ?? 0) === 1;
}));
$promoHeroProduct = $promoFlashProducts[0] ?? $promoFeaturedProducts[0] ?? null;
$promoHeroIsFlash = $promoHeroProduct && (int)($promoHeroProduct->is_flash_sale ?? 0) === 1;
$promoHeroDiscount = $promoHeroProduct ? max(0, min(90, (int)($promoHeroProduct->flash_discount ?? 0))) : 0;
$promoHeroPrice = $promoHeroProduct ? (float)($promoHeroProduct->price ?? 0) : 0;
$promoHeroSalePrice = $promoHeroIsFlash && $promoHeroDiscount > 0
    ? max(0, $promoHeroPrice * (100 - $promoHeroDiscount) / 100)
    : $promoHeroPrice;
$promoHeroStock = $promoHeroProduct ? (float)($promoHeroProduct->stock ?? 0) : 0;
$promoHeroDetailUrl = $promoHeroProduct ? '/webbanhang/product/detail/' . (int)($promoHeroProduct->id ?? 0) : '#all-products';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<main class="figma-store">
    <section class="figma-hero">
        <div class="figma-hero-overlay"></div>
        <div class="figma-hero-content">
            <div class="figma-kicker"><i class="fa-solid fa-leaf"></i> 100% Nguồn gốc rõ ràng</div>
            <h1>Nông Sản Sạch <span>Trực Tiếp Từ Trang Trại</span></h1>
            <p>Rau củ, trái cây, gạo và đặc sản vùng miền có chứng nhận VietGAP, truy xuất QR và giao hàng nhanh toàn quốc trong 2-3 ngày.</p>
            <div class="figma-hero-actions">
                <a href="#flash-section" class="figma-btn figma-btn-primary">Mua sắm ngay <i class="fa-solid fa-arrow-right"></i></a>
                <a href="/webbanhang/account/profile?tab=orders" class="figma-btn figma-btn-glass">Theo dõi đơn hàng</a>
            </div>
        </div>
        <div class="figma-floating-badges">
            <span class="sale"><i class="fa-solid fa-fire"></i> Flash Sale -30%</span>
            <span><i class="fa-solid fa-seedling"></i> VietGAP</span>
        </div>
    </section>

    <section class="figma-benefits" aria-label="Cam kết dịch vụ">
        <article>
            <i class="fa-solid fa-truck-fast"></i>
            <div><strong>Miễn phí vận chuyển</strong><span>Đơn từ 300.000đ, tối đa 20kg</span></div>
        </article>
        <article>
            <i class="fa-solid fa-leaf"></i>
            <div><strong>Nông sản hữu cơ</strong><span>Chứng nhận đầy đủ</span></div>
        </article>
        <article>
            <i class="fa-solid fa-arrows-rotate"></i>
            <div><strong>Đổi trả 24h</strong><span>Không câu hỏi</span></div>
        </article>
        <article>
            <i class="fa-solid fa-qrcode"></i>
            <div><strong>Truy xuất QR</strong><span>Minh bạch từng lô hàng</span></div>
        </article>
    </section>

    <section id="flash-section" class="figma-section figma-flash">
        <div class="figma-section-head">
            <h2><i class="fa-solid fa-fire"></i> Flash Sale <span>HOT</span></h2>
            <a href="#all-products">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div id="flashProducts" class="figma-product-grid">
            <?= str_repeat('<div class="figma-skeleton"></div>', 6) ?>
        </div>
    </section>

    <section id="featured-section" class="figma-section">
        <div class="figma-section-head">
            <h2><i class="fa-regular fa-bookmark"></i> Sản phẩm nổi bật</h2>
            <a href="#all-products">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div id="featuredProducts" class="figma-product-grid figma-product-grid-featured">
            <?= str_repeat('<div class="figma-skeleton"></div>', 8) ?>
        </div>
    </section>

    <section class="figma-promo-row">
        <article class="figma-promo figma-promo-image">
            <div>
                <?php if ($promoHeroProduct): ?>
                    <span><i class="fa-solid <?= $promoHeroIsFlash ? 'fa-fire' : 'fa-star' ?>"></i> <?= $promoHeroIsFlash ? 'Flash Sale trong admin' : 'Sản phẩm nổi bật trong admin' ?></span>
                    <h3><?= htmlspecialchars($promoHeroProduct->name ?? 'Sản phẩm nổi bật') ?></h3>
                    <?php if ($promoHeroIsFlash && $promoHeroDiscount > 0): ?>
                        <p>Giảm <strong><?= $promoHeroDiscount ?>%</strong>, còn <strong><?= number_format($promoHeroSalePrice, 0, ',', '.') ?>đ/kg</strong>. Tồn kho hiện có <?= number_format($promoHeroStock, 0, ',', '.') ?> kg.</p>
                    <?php else: ?>
                        <p>Sản phẩm đang được đánh dấu nổi bật trong admin. Giá <?= number_format($promoHeroPrice, 0, ',', '.') ?>đ/kg, tồn kho <?= number_format($promoHeroStock, 0, ',', '.') ?> kg.</p>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($promoHeroDetailUrl) ?>">Mua ngay <i class="fa-solid fa-arrow-right"></i></a>
                <?php else: ?>
                    <span><i class="fa-solid fa-star"></i> Gạo ngon nhất thế giới</span>
                    <h3>Gạo ST25 Sóc Trăng</h3>
                    <p>Hạt gạo thơm, dẻo nhẹ, phù hợp bữa cơm gia đình.</p>
                    <a href="#all-products">Mua ngay <i class="fa-solid fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        </article>
        <article class="figma-promo figma-promo-green">
            <div>
                <span><i class="fa-solid fa-ticket"></i> Ưu đãi hôm nay</span>
                <?php if ($activeVoucher): ?>
                    <h3>Mã <strong class="figma-voucher-code"><?= htmlspecialchars($activeVoucher->code ?? '') ?></strong></h3>
                    <p>Giảm <strong><?= number_format((float)($activeVoucher->discount_percent ?? 0), 0, ',', '.') ?>%</strong>, dùng khi thanh toán trước ngày <?= !empty($activeVoucher->expiry_date) ? date('d/m/Y', strtotime((string)$activeVoucher->expiry_date)) : 'hết hạn' ?>.</p>
                <?php else: ?>
                    <h3>Nông sản tươi mỗi ngày</h3>
                    <p>Cửa hàng đang chuẩn bị mã giảm giá mới. Bạn vẫn có thể chọn sản phẩm tươi và đặt giao trong ngày.</p>
                <?php endif; ?>
                <a href="#flash-section">Mua sắm ngay <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </article>
    </section>

    <section id="all-products" class="figma-section">
        <div class="figma-section-head">
            <h2>Tất cả nông sản</h2>
            <p id="resultSummary">Đang tải dữ liệu sản phẩm...</p>
        </div>
        <div class="figma-filter-layout">
            <aside class="figma-filter-sidebar">
                <div class="figma-filter-title">
                    <h3>Bộ lọc nông sản</h3>
                    <p>Chọn danh mục và khoảng giá phù hợp.</p>
                </div>
                <div class="figma-filter-list">
                    <button class="figma-filter-item active" type="button" data-sidebar-category="all">
                        <span>Tất cả</span>
                        <strong data-sidebar-count="all">0</strong>
                    </button>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <button class="figma-filter-item" type="button" data-sidebar-category="<?= htmlspecialchars($cat->name) ?>">
                                <span><?= htmlspecialchars($cat->name) ?></span>
                                <strong data-sidebar-count="<?= htmlspecialchars($cat->name) ?>">0</strong>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="figma-price-filter">
                    <h4><i class="fa-solid fa-sliders"></i> Lọc theo giá</h4>
                    <label class="active">
                        <input type="radio" name="priceFilter" value="all" checked>
                        <span>Tất cả</span>
                    </label>
                    <label>
                        <input type="radio" name="priceFilter" value="under-50">
                        <span>Dưới 50.000đ</span>
                    </label>
                    <label>
                        <input type="radio" name="priceFilter" value="50-100">
                        <span>50.000đ - 100.000đ</span>
                    </label>
                    <label>
                        <input type="radio" name="priceFilter" value="100-300">
                        <span>100.000đ - 300.000đ</span>
                    </label>
                    <label>
                        <input type="radio" name="priceFilter" value="over-300">
                        <span>Trên 300.000đ</span>
                    </label>
                </div>
            </aside>

            <div id="allProductGrid" class="figma-product-grid">
                <?= str_repeat('<div class="figma-skeleton"></div>', 8) ?>
            </div>
        </div>
    </section>

    <section class="figma-about">
        <div>
            <h2>Về Aethera Supply</h2>
            <p>Chúng tôi là đơn vị cung ứng và quản lý xuất khẩu nông sản Việt Nam, tập trung vào chất lượng, nguồn gốc và trải nghiệm mua hàng rõ ràng.</p>
            <ul>
                <li><i class="fa-solid fa-check"></i> Truy xuất nguồn gốc bằng mã QR theo từng lô nông sản</li>
                <li><i class="fa-solid fa-check"></i> Đội ngũ cửa hàng hỗ trợ tra cứu sản phẩm, tồn kho và nguồn gốc</li>
                <li><i class="fa-solid fa-check"></i> Quản lý tập trung hồ sơ nhà cung cấp, vùng trồng và hợp tác xã</li>
                <li><i class="fa-solid fa-check"></i> Theo dõi riêng đơn nội địa và đơn xuất khẩu</li>
            </ul>
        </div>
        <div class="figma-about-stats">
            <article><i class="fa-solid fa-leaf"></i><strong>100% Tự nhiên</strong><span>Sản phẩm organic từ trang trại</span></article>
            <article><i class="fa-solid fa-globe"></i><strong>Xuất khẩu</strong><span>Theo dõi đơn quốc tế theo từng chặng vận chuyển</span></article>
            <article><i class="fa-solid fa-arrow-trend-up"></i><strong>Giá cạnh tranh</strong><span>Trực tiếp từ nguồn</span></article>
        </div>
    </section>

    <section id="quality-section" class="figma-quality">
        <i class="fa-solid fa-headset"></i>
        <h2>Cam kết chất lượng 100%</h2>
        <p>Tất cả sản phẩm có truy xuất nguồn gốc rõ ràng, được kiểm định chất lượng trước khi đến tay bạn. Hỗ trợ 24/7 qua hotline <strong>0794 322 539</strong>.</p>
        <a href="#all-products">Khám phá tất cả sản phẩm</a>
    </section>
</main>

<script>
let allProducts = [];
let selectedCategory = "all";
let selectedPrice = "all";
const initialSearchKeyword = <?= json_encode($initialSearchKeyword, JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener("DOMContentLoaded", () => {
    fetchProducts();
    bindSidebarFilters();
});

async function fetchProducts() {
    try {
        const keyword = String(initialSearchKeyword || "").trim();
        const endpoint = keyword
            ? `/webbanhang/api/product?search=${encodeURIComponent(keyword)}`
            : "/webbanhang/api/product";
        const response = await fetch(endpoint);
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);

        const data = await response.json();
        allProducts = Array.isArray(data) ? data : (data.data || data.products || []);
        renderStore();
    } catch (err) {
        document.getElementById("resultSummary").textContent = "API sản phẩm chưa phản hồi.";
        ["flashProducts", "featuredProducts", "allProductGrid"].forEach(id => {
            document.getElementById(id).innerHTML = emptyState("Không tải được dữ liệu sản phẩm", err.message);
        });
    }
}

function renderStore() {
    updateCategoryCounts();
    const sorted = [...allProducts];
    const flash = sorted.filter(p => Number(p.is_flash_sale || 0) === 1).slice(0, 8);
    const featured = sorted.filter(p => Number(p.is_featured || 0) === 1).slice(0, 8);
    const visible = applyProductFilters(sorted);

    renderProducts("flashProducts", flash, { sale: true });
    renderProducts("featuredProducts", featured, { featured: true });
    renderProducts("allProductGrid", visible, { compact: true });
    document.getElementById("resultSummary").textContent = initialSearchKeyword
        ? `${visible.length} sản phẩm phù hợp với "${initialSearchKeyword}".`
        : `${visible.length} sản phẩm phù hợp.`;
}

function renderProducts(containerId, products, options = {}) {
    const container = document.getElementById(containerId);
    if (!products.length) {
        container.innerHTML = emptyState("Chưa có sản phẩm phù hợp", "Thử chọn danh mục khác hoặc quay lại sau.");
        return;
    }

    container.innerHTML = products.map((p, index) => productCard(p, index, options)).join("");
}

function productCard(p, index, options) {
    const imagePath = p.image ? `/webbanhang/public/uploads/${p.image}` : '/webbanhang/public/uploads/default-product.svg';
    const category = escapeHtml(p.category_name || "Nông sản");
    const name = escapeHtml(p.name || "Sản phẩm");
    const price = Number(p.price || 0);
    const fallbackDiscount = [22, 11, 10, 18, 13, 20][index % 6];
    const configuredDiscount = Number(p.flash_discount || 0);
    const discount = options.sale ? (configuredDiscount > 0 ? configuredDiscount : fallbackDiscount) : fallbackDiscount;
    const oldPrice = Math.round(price / (1 - discount / 100));
    const unit = "/kg";
    const rating = (4.5 + ((index % 5) * 0.1)).toFixed(1);
    const showDiscount = options.sale || Number(p.is_flash_sale || 0) === 1;
    const showFeatured = options.featured || Number(p.is_featured || 0) === 1;
    const badge = showDiscount
        ? `<span class="figma-discount">-${discount}%</span>`
        : (showFeatured ? `<span class="figma-featured-badge">Nổi bật</span>` : "");

    return `
        <article class="figma-product-card">
            <a class="figma-product-link" href="/webbanhang/product/detail/${p.id}">
                <div class="figma-product-image">
                    ${badge}
                    <img src="${imagePath}" onerror="this.onerror=null; this.src='/webbanhang/public/uploads/default-product.svg'" alt="${name}">
                </div>
                <div class="figma-product-body">
                    <h3>${name}</h3>
                    <div class="figma-rating"><i class="fa-solid fa-star"></i> ${rating} <span>(${120 + index * 37})</span></div>
                    <div class="figma-product-meta">${category}</div>
                    <div class="figma-price-row">
                        <div>
                            <strong>${formatMoney(price)}đ<span>${unit}</span></strong>
                            <del>${formatMoney(oldPrice)}đ</del>
                        </div>
                        <button onclick="event.preventDefault(); addToCart(this, ${p.id})" aria-label="Thêm vào giỏ hàng">
                            <svg class="figma-cart-icon" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="8" cy="21" r="1.5"></circle>
                                <circle cx="19" cy="21" r="1.5"></circle>
                                <path d="M2.5 3h2.4l2.8 13.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6l1.3-7.2H6.2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </a>
        </article>
    `;
}

async function addToCart(btn, id) {
    btn.disabled = true;
    try {
        const response = await fetch("/webbanhang/cart/add", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({
                product_id: id,
                quantity: 1,
                quantity_unit: "kg",
                [document.querySelector('meta[name="csrf-field"]')?.content || "csrf_token"]:
                    document.querySelector('meta[name="csrf-token"]')?.content || ""
            })
        });

        const result = await response.json();
        if (result.status === "success") {
            Swal.fire({
                toast: true,
                position: "top-end",
                icon: "success",
                title: result.message || "Đã thêm vào giỏ hàng",
                showConfirmButton: false,
                timer: 1800,
                background: "#ffffff",
                color: "#0f172a",
                iconColor: "#009966"
            });

            const badge = document.querySelector(".cart-badge");
            if (badge && result.cart_count !== undefined) {
                badge.innerText = result.cart_count;
            }
        } else {
            Swal.fire({ icon: "warning", title: "Thông báo", text: result.message || "Không thể thêm sản phẩm này." });
        }
    } catch (err) {
        Swal.fire({ icon: "error", title: "Lỗi", text: "Có lỗi xảy ra khi thêm vào giỏ hàng." });
    } finally {
        btn.disabled = false;
    }
}

function bindSidebarFilters() {
    document.querySelectorAll(".figma-filter-item").forEach(item => {
        item.addEventListener("click", () => {
            document.querySelectorAll(".figma-filter-item").forEach(filter => filter.classList.remove("active"));
            item.classList.add("active");
            selectedCategory = item.dataset.sidebarCategory || "all";
            renderStore();
            document.getElementById("all-products").scrollIntoView({ behavior: "smooth", block: "start" });
        });
    });

    document.querySelectorAll('input[name="priceFilter"]').forEach(input => {
        input.addEventListener("change", () => {
            document.querySelectorAll(".figma-price-filter label").forEach(label => label.classList.remove("active"));
            input.closest("label")?.classList.add("active");
            selectedPrice = input.value;
            renderStore();
        });
    });
}

function applyProductFilters(products) {
    let visible = [...products];

    if (selectedCategory !== "all") {
        visible = visible.filter(p =>
            normalize(p.category_name || "").includes(normalize(selectedCategory))
        );
    }

    if (selectedPrice === "under-50") {
        visible = visible.filter(p => Number(p.price || 0) < 50000);
    } else if (selectedPrice === "50-100") {
        visible = visible.filter(p => Number(p.price || 0) >= 50000 && Number(p.price || 0) <= 100000);
    } else if (selectedPrice === "100-300") {
        visible = visible.filter(p => Number(p.price || 0) >= 100000 && Number(p.price || 0) <= 300000);
    } else if (selectedPrice === "over-300") {
        visible = visible.filter(p => Number(p.price || 0) > 300000);
    }

    return visible;
}

function updateCategoryCounts() {
    const allCounter = document.querySelector('[data-sidebar-count="all"]');
    if (allCounter) allCounter.textContent = allProducts.length;

    document.querySelectorAll("[data-sidebar-count]").forEach(el => {
        const category = el.dataset.sidebarCount;
        if (category === "all") return;
        const count = allProducts.filter(p =>
            normalize(p.category_name || "").includes(normalize(category))
        ).length;
        el.textContent = count;
        const item = el.closest(".figma-filter-item");
        if (item) {
            item.hidden = count <= 0;
        }
        if (count <= 0 && selectedCategory === category) {
            selectedCategory = "all";
            document.querySelectorAll(".figma-filter-item").forEach(filter => filter.classList.remove("active"));
            document.querySelector('[data-sidebar-category="all"]')?.classList.add("active");
        }
    });
}

function emptyState(title, message) {
    return `<div class="figma-empty"><h4>${escapeHtml(title)}</h4><p>${escapeHtml(message)}</p></div>`;
}

function formatMoney(value) {
    return new Intl.NumberFormat("vi-VN").format(value);
}

function normalize(str) {
    return String(str).toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
