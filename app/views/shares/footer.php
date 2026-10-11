<?php
$footerCurrentUrl = $_SERVER['REQUEST_URI'] ?? '';
$footerIsAdminPage = strpos($footerCurrentUrl, '/admin') !== false;

if ($footerIsAdminPage): ?>
    </main>
</div>
<?php endif; ?>

<?php if (!$footerIsAdminPage): ?>
<footer class="footer-main py-5">
    <div class="container-fluid footer-container px-4 px-lg-5">
        <div class="row g-4 g-xl-5 text-center text-lg-start align-items-start">
            <div class="col-12 col-lg-5">
                <h5 class="fw-bold d-flex align-items-center justify-content-center justify-content-lg-start">
                    <i class="fas fa-seedling me-2"></i>
                    AETHERA SUPPLY
                </h5>
                <small class="d-block">Nông sản sạch Việt Nam</small>
                <p class="mt-3 small mb-2 footer-copy">
                    Hệ thống cung ứng nông sản sạch, chất lượng cao từ trang trại đến bàn ăn của bạn.
                </p>
                <p class="small opacity-75 mb-0 footer-copy">
                    Theo dõi nguồn hàng rõ ràng, truy xuất nguồn gốc minh bạch và giao hàng nhanh toàn quốc.
                </p>
                <div class="footer-social d-flex gap-3 mt-4 justify-content-center justify-content-lg-start">
                    <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                    <a href="https://www.tiktok.com/" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-3 col-xl-3">
                <h6 class="fw-bold">Hỗ trợ</h6>
                <ul class="list-unstyled small mt-3 mb-0 footer-support">
                    <li><a href="/webbanhang/account/profile?tab=orders">Theo dõi đơn hàng</a></li>
                    <li><a href="/webbanhang/account/profile">Tài khoản của tôi</a></li>
                    <li><a href="/webbanhang/policy/shipping">Chính sách vận chuyển</a></li>
                    <li><a href="/webbanhang/policy/returns">Đổi trả và hoàn hàng</a></li>
                    <li><a href="/webbanhang/policy/payment">Thanh toán và hoàn tiền</a></li>
                    <li><a href="/webbanhang/policy/quality">Chính sách mua hàng</a></li>
                </ul>
            </div>

            <div class="col-12 col-md-6 col-lg-4 col-xl-4">
                <h6 class="fw-bold">Liên hệ</h6>
                <ul class="list-unstyled small mt-3 footer-contact mb-0">
                    <li><i class="fas fa-map-marker-alt"></i><span>123 Lê Lợi, Quận 1, TP.HCM</span></li>
                    <li><i class="fas fa-phone"></i><span>0794 322 539 (Miễn phí)</span></li>
                    <li><i class="fas fa-envelope"></i><span>info@aethera.vn</span></li>
                    <li><i class="fas fa-clock"></i><span>24/7 (Thứ 2 - CN)</span></li>
                </ul>
            </div>
        </div>

        <hr class="my-4">

        <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 text-center text-md-start">
            <div>© 2026 Aethera Supply · Nông sản sạch Việt Nam</div>
            <div class="d-flex flex-wrap justify-content-center justify-content-md-end gap-3">
                <a href="/webbanhang/policy/terms">Điều khoản sử dụng</a>
                <a href="/webbanhang/policy/privacy">Chính sách bảo mật</a>
                <a href="/webbanhang/policy/quality">Chất lượng & truy xuất</a>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<?php if (class_exists('CsrfHelper')): ?>
<script>
(() => {
    const csrfToken = <?= json_encode(CsrfHelper::token()) ?>;
    const csrfField = <?= json_encode(CsrfHelper::FIELD_NAME) ?>;
    const postPrefixes = [
        '/webbanhang/admin/deleteUser/',
        '/webbanhang/admin/deleteSupplier/',
        '/webbanhang/admin/deletePromotion/',
        '/webbanhang/admin/togglePromotion/',
        '/webbanhang/admin/delete_category/',
        '/webbanhang/admin/delete/',
        '/webbanhang/Cart/clear',
        '/webbanhang/Cart/removeFromCart/',
        '/webbanhang/Cart/decreaseQuantity/',
        '/webbanhang/Cart/increaseQuantity/'
    ];

    function pathFor(url) {
        return new URL(url, window.location.origin).pathname;
    }

    function shouldPost(url) {
        const path = pathFor(url);
        return postPrefixes.some(prefix => path.startsWith(prefix));
    }

    function ensureCsrfField(form) {
        if (!form || String(form.method || '').toUpperCase() !== 'POST') return;
        if ([...form.elements].some((element) => element.name === csrfField)) return;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = csrfField;
        tokenInput.value = csrfToken;
        form.appendChild(tokenInput);
    }

    document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(ensureCsrfField);

    document.addEventListener('submit', (event) => {
        ensureCsrfField(event.target);
    }, true);

    window.submitPostWithCsrf = function submitPostWithCsrf(url, extra = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = new URL(url, window.location.origin).href;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = csrfField;
        tokenInput.value = csrfToken;
        form.appendChild(tokenInput);

        Object.entries(extra).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || !shouldPost(link.href)) return;

        event.preventDefault();
        event.stopPropagation();

        const path = pathFor(link.href);
        const needsConfirm = path.includes('/delete')
            || path.includes('/clear')
            || path.includes('/removeFromCart');

        if (needsConfirm && !window.confirm('Xac nhan thao tac nay?')) return;
        window.submitPostWithCsrf(link.href);
    }, true);
})();
</script>
<?php endif; ?>

<?php
$footerChatbotPath = __DIR__ . '/chatbot.php';
if (!$footerIsAdminPage && file_exists($footerChatbotPath)) {
    include $footerChatbotPath;
}
?>

<style>
body:not(.admin-theme) .footer-main,
.footer-main {
    margin-top: 0;
    background:
        radial-gradient(circle at 12% 8%, rgba(20, 184, 166, .16), transparent 26%),
        linear-gradient(135deg, #01011b 0%, #01011b 58%, #01011b 100%) !important;
    color: #cce6db !important;
    border-top: 0 !important;
}

body:not(.admin-theme) .footer-main .footer-container,
.footer-main .footer-container {
    max-width: min(1480px, calc(100vw - 48px)) !important;
    margin: 0 auto;
}

.footer-main h5,
.footer-main h6,
body:not(.admin-theme) .footer-main h5,
body:not(.admin-theme) .footer-main h6 {
    color: #ffffff !important;
}

.footer-main small,
.footer-main i,
body:not(.admin-theme) .footer-main small,
body:not(.admin-theme) .footer-main i,
body:not(.admin-theme) .footer-main a:hover {
    color: #34d399 !important;
}

.footer-copy {
    max-width: 620px;
}

.footer-main a,
body:not(.admin-theme) .footer-main a {
    color: #d7efe6 !important;
    text-decoration: none;
    transition: .18s ease;
}

.footer-main a:hover {
    color: #86efac !important;
}

.footer-main ul li {
    margin-bottom: 12px;
    line-height: 1.55;
}

.footer-support a {
    display: inline-flex;
    max-width: 100%;
}

.footer-contact li {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr);
    gap: 8px;
    align-items: start;
}

.footer-contact i {
    width: 24px;
    height: 24px;
    display: inline-grid;
    place-items: center;
    margin-top: 1px;
}

.footer-main hr,
body:not(.admin-theme) .footer-main hr {
    border-color: rgba(209, 250, 229, .18) !important;
    opacity: 1;
}

.footer-social a,
body:not(.admin-theme) .footer-social a {
    width: 38px;
    height: 38px;
    border: 1px solid rgba(209, 250, 229, .2) !important;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, .08) !important;
    color: #ffffff !important;
    font-weight: 900;
    font-size: .78rem;
}

.footer-social a:hover {
    background: rgba(52, 211, 153, .22) !important;
    border-color: rgba(134, 239, 172, .55) !important;
}

.footer-social a i,
body:not(.admin-theme) .footer-social a i {
    color: #ffffff !important;
    font-size: 1rem;
}

body.admin-theme {
    --admin-primary: #00bb7f;
    --admin-primary-dark: #007956;
    --admin-primary-soft: #e9fbf2;
    --admin-bg: #ffffff;
    --admin-border: #e5e7eb;
    --admin-text: #143c2f;
    --admin-muted: #5d7169;
    background: var(--admin-bg) !important;
    color: var(--admin-text) !important;
    font-family: 'Be Vietnam Pro', Arial, sans-serif !important;
}

body.admin-theme .footer-main,
body.admin-theme #chatbot-wrapper {
    display: none !important;
}

body.admin-theme .container,
body.admin-theme .container-fluid {
    max-width: 1120px;
}

body.admin-theme .card,
body.admin-theme .glass,
body.admin-theme .glass-card,
body.admin-theme .admin-card {
    background: #fff !important;
    border: 1px solid var(--admin-border) !important;
    border-radius: 16px !important;
    box-shadow: 0 14px 34px rgba(15, 23, 42, .06) !important;
    color: var(--admin-text) !important;
}

body.admin-theme .card-header,
body.admin-theme .admin-header,
body.admin-theme .add-header,
body.admin-theme .add-product-header,
body.admin-theme .header-gradient {
    min-height: 70px !important;
    padding: 18px 22px !important;
    border: 0 !important;
    border-radius: 16px 16px 0 0 !important;
    background: linear-gradient(135deg, #007956 0%, #00bb7f 100%) !important;
    color: #fff !important;
}

body.admin-theme .card-header h1,
body.admin-theme .card-header h2,
body.admin-theme .card-header h3,
body.admin-theme .card-header h4,
body.admin-theme .card-header h5,
body.admin-theme .admin-header h1,
body.admin-theme .admin-header h2,
body.admin-theme .admin-header h3,
body.admin-theme .admin-header h4,
body.admin-theme .admin-header h5,
body.admin-theme .add-header h1,
body.admin-theme .add-header h2,
body.admin-theme .add-header h3,
body.admin-theme .add-header h4,
body.admin-theme .add-header h5,
body.admin-theme .add-product-header h1,
body.admin-theme .add-product-header h2,
body.admin-theme .add-product-header h3,
body.admin-theme .add-product-header h4,
body.admin-theme .add-product-header h5 {
    margin: 0 !important;
    color: #fff !important;
    font-size: 1.25rem !important;
    line-height: 1.25 !important;
    font-weight: 900 !important;
}

body.admin-theme .text-gradient {
    background: none !important;
    -webkit-background-clip: initial !important;
    background-clip: initial !important;
    -webkit-text-fill-color: currentColor !important;
    color: inherit !important;
}

body.admin-theme .text-white,
body.admin-theme .form-label {
    color: var(--admin-text) !important;
    text-shadow: none !important;
}

body.admin-theme .card-header .text-white,
body.admin-theme .card-header .form-label,
body.admin-theme .card-header small,
body.admin-theme .admin-header small {
    color: rgba(255, 255, 255, .82) !important;
}

body.admin-theme small,
body.admin-theme .text-muted {
    color: var(--admin-muted) !important;
}

body.admin-theme .table thead th,
body.admin-theme .custom-table thead th {
    background: #f3f4f6 !important;
    color: var(--admin-muted) !important;
    border-bottom: 1px solid var(--admin-border) !important;
    font-size: .75rem !important;
    letter-spacing: .04em !important;
    text-transform: uppercase !important;
}

body.admin-theme .table td,
body.admin-theme .custom-table td {
    color: var(--admin-text) !important;
    border-bottom: 1px solid #eef2f7 !important;
}

body.admin-theme .btn-back,
body.admin-theme .btn-glass,
body.admin-theme .btn-cancel,
body.admin-theme .btn-outline-light,
body.admin-theme .btn-outline-secondary {
    min-height: 42px !important;
    border: 1px solid var(--admin-border) !important;
    border-radius: 12px !important;
    background: #fff !important;
    color: var(--admin-text) !important;
    box-shadow: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    white-space: nowrap !important;
}

body.admin-theme .btn-back:hover,
body.admin-theme .btn-glass:hover,
body.admin-theme .btn-cancel:hover,
body.admin-theme .btn-outline-light:hover,
body.admin-theme .btn-outline-secondary:hover {
    background: var(--admin-primary-soft) !important;
    border-color: #b7ebd3 !important;
    color: var(--admin-primary-dark) !important;
}

body.admin-theme .btn-glow-primary,
body.admin-theme .btn-gradient,
body.admin-theme .btn-save,
body.admin-theme .btn-save-stock,
body.admin-theme .btn-primary {
    min-height: 42px !important;
    border: 0 !important;
    border-radius: 12px !important;
    background: linear-gradient(135deg, #007956, #00bb7f) !important;
    color: #fff !important;
    box-shadow: 0 10px 20px rgba(0, 187, 127, .18) !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    white-space: nowrap !important;
}

body.admin-theme .form-control,
body.admin-theme .form-select,
body.admin-theme textarea {
    border: 1px solid var(--admin-border) !important;
    border-radius: 12px !important;
    background: #fff !important;
    color: var(--admin-text) !important;
    min-height: 42px;
}

body.admin-theme .form-control:focus,
body.admin-theme .form-select:focus,
body.admin-theme textarea:focus {
    border-color: var(--admin-primary) !important;
    box-shadow: 0 0 0 4px rgba(0, 187, 127, .14) !important;
}

body.admin-theme .input-group-text {
    border-color: var(--admin-border) !important;
    background: #f8fafc !important;
    color: #3b82f6 !important;
}

/* Softer typography pass: keeps headings readable without making whole pages look bold. */
body.admin-theme,
body.admin-theme .admin-shared-main,
body.admin-theme .admin-shared-main table,
body.admin-theme .admin-shared-main input,
body.admin-theme .admin-shared-main select,
body.admin-theme .admin-shared-main textarea {
    font-weight: 500 !important;
}

body.admin-theme h1,
body.admin-theme h2,
body.admin-theme h3,
body.admin-theme h4,
body.admin-theme h5,
body.admin-theme h6,
body.admin-theme .panel-title,
body.admin-theme .sc-head h1,
body.admin-theme .report-head h1,
body.admin-theme .promo-head h1,
body.admin-theme .domestic-hero h1 {
    font-weight: 700 !important;
}

body.admin-theme strong,
body.admin-theme b,
body.admin-theme .fw-bold,
body.admin-theme .fw-black,
body.admin-theme .report-card strong,
body.admin-theme .sc-stat strong,
body.admin-theme .domestic-stat strong,
body.admin-theme .summary-cell strong,
body.admin-theme .info-box strong,
body.admin-theme .item-chip strong,
body.admin-theme .country-row,
body.admin-theme .marketing-product strong {
    font-weight: 650 !important;
}

body.admin-theme label,
body.admin-theme th,
body.admin-theme small,
body.admin-theme .form-label,
body.admin-theme .sc-field label,
body.admin-theme .promo-field label,
body.admin-theme .report-card small,
body.admin-theme .sc-stat small,
body.admin-theme .summary-cell small,
body.admin-theme .info-box small {
    font-weight: 600 !important;
}

body.admin-theme .admin-shared-link,
body.admin-theme .admin-shared-logout,
body.admin-theme .btn,
body.admin-theme button,
body.admin-theme .sc-btn,
body.admin-theme .promo-btn,
body.admin-theme .status-pill,
body.admin-theme .payment-pill,
body.admin-theme .export-pill,
body.admin-theme .report-badge,
body.admin-theme .sc-badge,
body.admin-theme .status-badge,
body.admin-theme .code-badge {
    font-weight: 650 !important;
}

body.admin-theme .admin-shared-sublink,
body.admin-theme .text-muted,
body.admin-theme .report-muted,
body.admin-theme .sc-muted {
    font-weight: 500 !important;
}

body:not(.admin-theme) .figma-filter-title h3,
body:not(.admin-theme) .figma-price-filter h4 {
    font-weight: 700 !important;
}

body:not(.admin-theme) .figma-filter-title p,
body:not(.admin-theme) .figma-filter-item,
body:not(.admin-theme) .figma-filter-item strong,
body:not(.admin-theme) .figma-price-filter label,
body:not(.admin-theme) .figma-price-filter span {
    font-weight: 500 !important;
}

body:not(.admin-theme) .figma-filter-item.active,
body:not(.admin-theme) .figma-filter-item.active strong,
body:not(.admin-theme) .figma-price-filter label.active,
body:not(.admin-theme) .figma-price-filter label:has(input:checked) {
    font-weight: 650 !important;
}

body:not(.admin-theme) .profile-page,
body:not(.admin-theme) .profile-page input,
body:not(.admin-theme) .profile-page textarea,
body:not(.admin-theme) .profile-page table {
    font-weight: 500 !important;
}

body:not(.admin-theme) .profile-page h1,
body:not(.admin-theme) .profile-page h2,
body:not(.admin-theme) .profile-page h3 {
    font-weight: 700 !important;
}

body:not(.admin-theme) .profile-page strong,
body:not(.admin-theme) .profile-page b,
body:not(.admin-theme) .profile-page .fw-bold,
body:not(.admin-theme) .profile-page .profile-name,
body:not(.admin-theme) .profile-page .profile-chip,
body:not(.admin-theme) .profile-page .profile-nav a,
body:not(.admin-theme) .profile-page .status-badge,
body:not(.admin-theme) .profile-page .order-track-link,
body:not(.admin-theme) .profile-page .btn-profile {
    font-weight: 650 !important;
}

body:not(.admin-theme) .profile-page label,
body:not(.admin-theme) .profile-page th,
body:not(.admin-theme) .profile-page .profile-kicker {
    font-weight: 600 !important;
}

body:not(.admin-theme) .product-detail-page {
    font-weight: 500 !important;
}

body:not(.admin-theme) .product-detail-page h1,
body:not(.admin-theme) .product-detail-page h2,
body:not(.admin-theme) .product-detail-page h3,
body:not(.admin-theme) .product-detail-page .product-price-main {
    font-weight: 750 !important;
}

body:not(.admin-theme) .product-detail-page strong,
body:not(.admin-theme) .product-detail-page b,
body:not(.admin-theme) .product-detail-page .btn,
body:not(.admin-theme) .product-detail-page .image-badge,
body:not(.admin-theme) .product-detail-page .stock-badge {
    font-weight: 650 !important;
}

@media (max-width: 767px) {
    body:not(.admin-theme) .footer-main .footer-container,
    .footer-main .footer-container {
        max-width: 100% !important;
    }
}
</style>
<?php if ($footerIsAdminPage): ?>
<style>
body.admin-theme {
    --staff-bg: #f8fafc;
    --staff-sidebar: #ffffff;
    --staff-card: #ffffff;
    --staff-card-soft: #f8fafc;
    --staff-border: #e5e7eb;
    --staff-border-strong: #d1d5db;
    --staff-text: #111827;
    --staff-muted: #6b7280;
    --staff-primary: #059669;
    --staff-primary-dark: #047857;
    --staff-primary-soft: #ecfdf5;
    --staff-blue: #2563eb;
    --staff-amber: #d97706;
    --staff-red: #dc2626;
    --staff-radius: 14px;
    --staff-radius-lg: 18px;
    --staff-shadow: 0 14px 32px rgba(15, 23, 42, .07);
    --staff-shadow-hover: 0 18px 44px rgba(15, 23, 42, .11);
    --staff-sidebar-width: 240px;
    background: var(--staff-bg) !important;
    color: var(--staff-text) !important;
}

body.admin-theme *,
body.admin-theme *::before,
body.admin-theme *::after {
    box-sizing: border-box;
}

body.admin-theme.admin-dark {
    --staff-bg: #020617;
    --staff-sidebar: #0f172a;
    --staff-card: #111827;
    --staff-card-soft: #1f2937;
    --staff-border: rgba(148, 163, 184, .22);
    --staff-border-strong: rgba(148, 163, 184, .34);
    --staff-text: #e5e7eb;
    --staff-muted: #9ca3af;
    --staff-primary-soft: rgba(16, 185, 129, .13);
    --staff-shadow: 0 18px 38px rgba(0, 0, 0, .26);
    --staff-shadow-hover: 0 24px 54px rgba(0, 0, 0, .34);
}

body.admin-theme .admin-shell {
    min-height: 100vh;
    background: var(--staff-bg) !important;
}

body.admin-theme .admin-shared-sidebar {
    position: fixed !important;
    inset: 0 auto 0 0 !important;
    width: var(--staff-sidebar-width) !important;
    height: 100vh !important;
    padding: 12px !important;
    background: var(--staff-sidebar) !important;
    border-right: 1px solid var(--staff-border) !important;
    box-shadow: none !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
    overflow-y: auto !important;
    z-index: 1040 !important;
}

body.admin-theme .admin-shared-brand {
    min-height: 58px;
    padding: 4px 4px 12px;
    border-bottom: 1px solid var(--staff-border);
}

body.admin-theme .admin-brand-mark {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--staff-text) !important;
    text-decoration: none;
}

body.admin-theme .admin-brand-mark span {
    width: 38px;
    height: 38px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    background: var(--staff-primary-soft);
    border: 1px solid rgba(16, 185, 129, .28);
    color: var(--staff-primary) !important;
}

body.admin-theme .admin-brand-mark strong {
    font-size: 1.02rem;
    font-weight: 900 !important;
    color: var(--staff-text) !important;
}

body.admin-theme .admin-shared-brand small {
    display: block;
    margin: -8px 0 0 48px;
    color: var(--staff-muted) !important;
    font-size: .7rem;
    font-weight: 600 !important;
}

body.admin-theme .admin-shared-profile {
    min-height: 68px !important;
    padding: 11px !important;
    border: 1px solid var(--staff-border) !important;
    border-radius: var(--staff-radius) !important;
    background: var(--staff-card-soft) !important;
}

body.admin-theme .admin-shared-crown {
    width: 36px !important;
    height: 36px !important;
    border-radius: 12px !important;
    background: var(--staff-primary-soft) !important;
    color: var(--staff-primary) !important;
}

body.admin-theme .admin-shared-profile strong,
body.admin-theme .admin-shared-profile span {
    max-width: 148px;
    color: var(--staff-text) !important;
}

body.admin-theme .admin-shared-profile strong {
    font-size: .78rem !important;
    font-weight: 800 !important;
}

body.admin-theme .admin-shared-name,
body.admin-theme .admin-shared-role {
    color: var(--staff-muted) !important;
    font-size: .72rem !important;
    font-weight: 600 !important;
    text-transform: none !important;
}

body.admin-theme .admin-role-flow {
    display: block;
    padding: 12px;
    border: 1px solid var(--staff-border);
    border-radius: var(--staff-radius);
    background: var(--staff-card-soft);
    text-decoration: none;
}

body.admin-theme .admin-role-flow strong {
    display: block;
    color: var(--staff-text) !important;
    font-size: .78rem;
    font-weight: 800 !important;
    margin-bottom: 9px;
}

body.admin-theme .admin-role-flow div {
    display: grid;
    gap: 6px;
}

body.admin-theme .admin-role-flow span {
    min-height: 26px;
    padding: 0 9px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    background: var(--staff-card);
    color: var(--staff-primary-dark) !important;
    border: 1px solid var(--staff-border);
    font-size: .7rem;
    font-weight: 650 !important;
}

body.admin-theme.admin-dark .admin-role-flow span {
    color: #34d399 !important;
}

body.admin-theme .admin-shared-menu {
    gap: 2px !important;
}

body.admin-theme .admin-shared-group {
    margin: 10px 8px 4px !important;
    padding-top: 10px !important;
    border-top: 1px solid var(--staff-border) !important;
    color: var(--staff-muted) !important;
    font-size: .68rem !important;
    font-weight: 800 !important;
    text-transform: uppercase;
}

body.admin-theme .admin-shared-menu .admin-shared-group:first-child {
    border-top: 0 !important;
    padding-top: 0 !important;
}

body.admin-theme .admin-shared-link,
body.admin-theme .admin-shared-logout {
    min-height: 40px !important;
    padding: 0 12px !important;
    margin-bottom: 2px !important;
    border-radius: 12px !important;
    color: var(--staff-text) !important;
    font-size: .84rem !important;
    font-weight: 650 !important;
    gap: 10px !important;
}

body.admin-theme .admin-shared-link i,
body.admin-theme .admin-shared-logout i {
    width: 18px !important;
    color: var(--staff-muted) !important;
}

body.admin-theme .admin-shared-link:hover,
body.admin-theme .admin-shared-link.active {
    background: var(--staff-primary-soft) !important;
    color: var(--staff-primary-dark) !important;
}

body.admin-theme.admin-dark .admin-shared-link:hover,
body.admin-theme.admin-dark .admin-shared-link.active {
    color: #34d399 !important;
}

body.admin-theme .admin-shared-link:hover i,
body.admin-theme .admin-shared-link.active i {
    color: var(--staff-primary) !important;
}

body.admin-theme .admin-shared-sublink {
    margin-left: 18px !important;
    min-height: 34px !important;
    border-left-color: var(--staff-border) !important;
}

body.admin-theme .admin-shared-sidebar-footer {
    border-top: 1px solid var(--staff-border) !important;
}

body.admin-theme .admin-shared-logout {
    color: var(--staff-red) !important;
}

body.admin-theme .admin-shared-main {
    min-height: 100vh !important;
    margin-left: var(--staff-sidebar-width) !important;
    padding: 0 !important;
    background: var(--staff-bg) !important;
    max-width: none !important;
    overflow-x: hidden;
}

body.admin-theme .admin-command-topbar {
    position: sticky;
    top: 0;
    z-index: 1030;
    min-height: 64px;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, .92);
    border-bottom: 1px solid var(--staff-border);
    backdrop-filter: blur(16px);
    max-width: 100%;
    overflow: hidden;
}

body.admin-theme.admin-dark .admin-command-topbar {
    background: rgba(15, 23, 42, .9);
}

body.admin-theme .admin-mobile-nav-toggle,
body.admin-theme .admin-icon-btn {
    width: 40px;
    height: 40px;
    border: 1px solid var(--staff-border);
    border-radius: 12px;
    background: var(--staff-card);
    color: var(--staff-text);
    display: inline-grid;
    place-items: center;
    position: relative;
}

body.admin-theme .admin-mobile-nav-toggle {
    display: none;
}

body.admin-theme .admin-icon-btn:hover,
body.admin-theme .admin-mobile-nav-toggle:hover {
    background: var(--staff-card-soft);
    border-color: var(--staff-border-strong);
}

body.admin-theme .admin-system-status {
    min-height: 36px;
    padding: 0 12px;
    border: 1px solid var(--staff-border);
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    color: var(--staff-muted);
    background: var(--staff-card);
    font-size: .78rem;
}

body.admin-theme .admin-live-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #10b981;
    box-shadow: 0 0 0 5px rgba(16, 185, 129, .16);
}

body.admin-theme .admin-system-status strong {
    color: var(--staff-muted) !important;
    font-weight: 650 !important;
}

body.admin-theme .admin-topbar-fill {
    flex: 1;
}

body.admin-theme .admin-alert-count {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--staff-red);
    color: #fff;
    border: 2px solid var(--staff-card);
    display: grid;
    place-items: center;
    font-size: .64rem;
    font-weight: 800;
}

body.admin-theme .admin-alert-count.is-empty {
    background: var(--staff-muted);
}

body.admin-theme .admin-topbar-user {
    min-height: 42px;
    padding-left: 10px;
    border-left: 1px solid var(--staff-border);
    display: flex;
    align-items: center;
    gap: 9px;
}

body.admin-theme .admin-user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: var(--staff-primary-soft);
    color: var(--staff-primary-dark);
    font-size: .78rem;
    font-weight: 850;
}

body.admin-theme.admin-dark .admin-user-avatar {
    color: #34d399;
}

body.admin-theme .admin-topbar-user strong,
body.admin-theme .admin-topbar-user span {
    display: block;
    line-height: 1.2;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

body.admin-theme .admin-topbar-user strong {
    color: var(--staff-text) !important;
    font-size: .78rem;
    font-weight: 800 !important;
}

body.admin-theme .admin-topbar-user span {
    color: var(--staff-primary) !important;
    font-size: .72rem;
    font-weight: 700 !important;
}

body.admin-theme .admin-notification-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: min(390px, 100vw);
    transform: translateX(104%);
    transition: transform .22s ease;
    z-index: 1060;
    background: var(--staff-card);
    border-left: 1px solid var(--staff-border);
    box-shadow: -22px 0 48px rgba(15, 23, 42, .16);
    display: flex;
    flex-direction: column;
}

body.admin-theme.admin-notifications-open .admin-notification-drawer {
    transform: translateX(0);
}

body.admin-theme .admin-notification-head {
    min-height: 68px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--staff-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

body.admin-theme .admin-notification-head strong,
body.admin-theme .admin-notification-head span {
    display: block;
}

body.admin-theme .admin-notification-head strong {
    color: var(--staff-text) !important;
    font-weight: 850 !important;
}

body.admin-theme .admin-notification-head span {
    color: var(--staff-muted) !important;
    font-size: .78rem;
}

body.admin-theme .admin-notification-list {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: stretch;
    align-content: flex-start;
    overflow-y: auto;
}

body.admin-theme .admin-notification-card {
    min-height: 64px;
    height: auto;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid var(--staff-border);
    display: grid;
    grid-template-columns: 22px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    text-decoration: none;
    background: var(--staff-card-soft);
}

body.admin-theme .admin-notification-card span {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas:
        "title tag"
        "desc tag";
    column-gap: 10px;
    row-gap: 2px;
    min-width: 0;
}

body.admin-theme .admin-notification-card strong,
body.admin-theme .admin-notification-card small,
body.admin-theme .admin-notification-card em {
    min-width: 0;
}

body.admin-theme .admin-notification-card strong {
    grid-area: title;
    color: var(--staff-text) !important;
    font-size: .86rem;
    font-weight: 800 !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

body.admin-theme .admin-notification-card small {
    grid-area: desc;
    margin-top: 0;
    color: var(--staff-muted) !important;
    font-size: .76rem;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

body.admin-theme .admin-notification-card em {
    grid-area: tag;
    align-self: center;
    max-width: 118px;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 0;
    border-radius: 999px;
    background: rgba(100, 116, 139, .12);
    padding: 4px 8px;
    color: var(--staff-muted) !important;
    font-size: .7rem;
    font-style: normal;
    font-weight: 750;
    text-transform: uppercase;
    white-space: nowrap;
}

body.admin-theme .admin-notification-card.danger i {
    color: var(--staff-red);
}

body.admin-theme .admin-notification-card.warning i {
    color: var(--staff-amber);
}

body.admin-theme .admin-notification-card.success i {
    color: var(--staff-primary);
}

body.admin-theme .admin-notification-card.info i {
    color: var(--staff-blue);
}

body.admin-theme .admin-sidebar-backdrop {
    display: none;
}

body.admin-theme :is(.report-page, .sc-page, .supplier-page, .export-page, .domestic-page, .promo-page, .admin-workflow-page) {
    width: min(1500px, 100%) !important;
    max-width: none !important;
    margin: 0 auto !important;
    padding: 24px !important;
    background: transparent !important;
    color: var(--staff-text) !important;
    overflow-x: hidden;
}

body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) {
    padding: 20px !important;
    border: 1px solid rgba(16, 185, 129, .34) !important;
    border-radius: var(--staff-radius-lg) !important;
    background: linear-gradient(135deg, #065f46, #059669) !important;
    color: #ffffff !important;
    box-shadow: 0 18px 42px rgba(5, 150, 105, .2) !important;
    overflow: hidden;
}

body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) > *,
body.admin-theme :is(.report-layout, .report-panel, .report-actions, .supplier-actions, .domestic-actions, .export-actions, .promo-head > div) {
    min-width: 0;
}

body.admin-theme.admin-dark :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) {
    background: linear-gradient(135deg, #111827, #064e3b) !important;
    border-color: rgba(52, 211, 153, .3) !important;
}

body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) h1,
body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) h2,
body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) h3,
body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) p {
    color: #ffffff !important;
}

body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) p {
    opacity: .86;
    font-weight: 500 !important;
}

body.admin-theme :is(.report-grid, .supplier-stats, .domestic-stats, .export-stats, .sc-grid) {
    gap: 14px !important;
}

body.admin-theme :is(.report-card, .supplier-stat, .domestic-stat, .export-stat, .sc-stat, .stat-card, .dashboard-panel, .report-panel, .supplier-panel, .supplier-card, .promo-card, .sc-card, .order-card, .flow-card, .workflow-situation, .workflow-step, .workflow-notes, .workflow-safe-box, .info-box, .item-chip, .supplier-order-card, .supplier-contact-card, .supplier-order-money div, .supplier-price-formula, .glass-card, .admin-card, .product-card, .card) {
    background: var(--staff-card) !important;
    border: 1px solid var(--staff-border) !important;
    border-radius: var(--staff-radius-lg) !important;
    box-shadow: var(--staff-shadow) !important;
    color: var(--staff-text) !important;
}

body.admin-theme :is(.report-card, .supplier-stat, .domestic-stat, .export-stat, .sc-stat, .stat-card, .dashboard-panel, .report-panel, .supplier-panel, .supplier-card, .promo-card, .sc-card, .order-card, .flow-card, .workflow-situation, .workflow-step, .info-box, .item-chip, .supplier-order-card, .glass-card, .admin-card, .product-card, .card):hover {
    border-color: var(--staff-border-strong) !important;
    box-shadow: var(--staff-shadow-hover) !important;
}

body.admin-theme :is(.report-card i, .flow-card i, .workflow-situation i, .stat-icon, .sc-card i, .supplier-panel-head i) {
    color: var(--staff-primary) !important;
    background: var(--staff-primary-soft) !important;
}

body.admin-theme :is(.report-card strong, .supplier-stat strong, .domestic-stat strong, .export-stat strong, .sc-stat strong, .stat-card strong) {
    color: var(--staff-text) !important;
    font-weight: 850 !important;
}

body.admin-theme :is(.report-card small, .supplier-stat span, .domestic-stat span, .export-stat span, .sc-stat small, .summary-cell small, .info-box small, .supplier-info small, .sc-field label, .promo-field label, .supplier-field label) {
    color: var(--staff-muted) !important;
    font-weight: 700 !important;
}

body.admin-theme :is(.report-panel h5, .supplier-panel-head strong, .marketing-head h5, .history-head h5, .workflow-step h2, .flow-card strong, .supplier-title, .order-id, .summary-cell strong, .info-box strong, .item-chip strong, h1, h2, h3, h4, h5, h6) {
    color: var(--staff-text) !important;
}

body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) :is(h1, h2, h3, h4, h5, h6, p, span, small, i) {
    color: #ffffff !important;
    overflow-wrap: anywhere;
    white-space: normal !important;
}

body.admin-theme :is(.text-muted, .report-muted, .sc-muted, .workflow-step p, .flow-card span, .summary-cell span, .info-box span, .supplier-info span, .marketing-head p, .history-head p) {
    color: var(--staff-muted) !important;
}

body.admin-theme :is(.card-header, .admin-header, .add-header, .add-product-header, .header-gradient) {
    background: linear-gradient(135deg, #065f46, #059669) !important;
    border: 0 !important;
    border-radius: var(--staff-radius-lg) var(--staff-radius-lg) 0 0 !important;
    color: #ffffff !important;
}

body.admin-theme :is(.card-header, .admin-header, .add-header, .add-product-header, .header-gradient) :is(h1, h2, h3, h4, h5, p, small, label, span, i) {
    color: #ffffff !important;
}

body.admin-theme :is(.report-action-btn, .workflow-primary, .workflow-step-link, .supplier-btn, .domestic-btn, .export-btn, .promo-btn, .sc-btn, .btn-primary, .btn-success, .btn-save, .btn-save-stock, .btn-gradient, .btn-glow-primary) {
    min-height: 40px !important;
    border-radius: 12px !important;
    border: 0 !important;
    background: var(--staff-primary) !important;
    color: #ffffff !important;
    box-shadow: none !important;
    font-weight: 750 !important;
    text-decoration: none !important;
}

body.admin-theme :is(.report-action-btn:hover, .workflow-primary:hover, .workflow-step-link:hover, .supplier-btn:hover, .domestic-btn:hover, .export-btn:hover, .promo-btn:hover, .sc-btn:hover, .btn-primary:hover, .btn-success:hover, .btn-save:hover, .btn-save-stock:hover, .btn-gradient:hover, .btn-glow-primary:hover) {
    background: var(--staff-primary-dark) !important;
    color: #ffffff !important;
    transform: translateY(-1px);
}

body.admin-theme :is(.report-action-btn.primary, .supplier-btn.secondary, .supplier-btn.light, .domestic-btn.secondary, .domestic-btn.light, .export-btn.secondary, .export-btn.light, .promo-btn.muted, .sc-btn.secondary, .btn-back, .btn-glass, .btn-outline-light, .btn-outline-secondary, .btn-light) {
    border: 1px solid var(--staff-border) !important;
    background: var(--staff-card) !important;
    color: var(--staff-text) !important;
    box-shadow: none !important;
}

body.admin-theme :is(.supplier-btn.danger, .promo-btn.danger, .sc-danger-btn) {
    border: 1px solid rgba(220, 38, 38, .22) !important;
    background: rgba(220, 38, 38, .08) !important;
    color: var(--staff-red) !important;
}

body.admin-theme :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea, .form-control, .form-select, .supplier-field input, .supplier-field textarea, .supplier-field select, .sc-field input, .sc-field textarea, .sc-field select, .promo-field input, .marketing-product input[type="number"]) {
    min-height: 42px !important;
    border: 1px solid var(--staff-border) !important;
    border-radius: 12px !important;
    background: var(--staff-card) !important;
    color: var(--staff-text) !important;
    box-shadow: none !important;
}

body.admin-theme :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea, .form-control, .form-select):focus {
    border-color: var(--staff-primary) !important;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, .16) !important;
    outline: 0 !important;
}

body.admin-theme :is(.table-responsive, .report-table-wrap, .marketing-scroll) {
    border: 1px solid var(--staff-border);
    border-radius: var(--staff-radius-lg);
    overflow: auto;
}

body.admin-theme :is(.table, .custom-table, .report-table, .promo-table, .sc-table) {
    --bs-table-bg: var(--staff-card);
    color: var(--staff-text) !important;
    margin-bottom: 0 !important;
}

body.admin-theme :is(.table thead th, .custom-table thead th, .report-table th, .promo-table th, .sc-table th, .orders-table th) {
    background: var(--staff-card-soft) !important;
    color: var(--staff-muted) !important;
    border-bottom: 1px solid var(--staff-border) !important;
    font-size: .74rem !important;
    font-weight: 750 !important;
    letter-spacing: 0 !important;
    text-transform: uppercase;
}

body.admin-theme :is(.table td, .custom-table td, .report-table td, .promo-table td, .sc-table td, .orders-table td) {
    background: var(--staff-card) !important;
    color: var(--staff-text) !important;
    border-color: var(--staff-border) !important;
}

body.admin-theme :is(.status-pill, .payment-pill, .export-pill, .stock-pill, .status-badge, .sc-badge, .report-badge, .code-badge, .badge, .product-tag) {
    min-height: 26px;
    border-radius: 999px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 5px 10px !important;
    font-weight: 750 !important;
    line-height: 1.1 !important;
}

body.admin-theme.admin-dark :is(.text-dark, .text-black, .text-body, .form-label, label, p, td, th, small, span) {
    color: inherit;
}

body.admin-theme.admin-dark :is(.bg-white, .bg-light) {
    background-color: var(--staff-card) !important;
}

@media (max-width: 1180px) {
    body.admin-theme :is(.report-grid, .supplier-stats, .domestic-stats, .export-stats) {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 991px) {
    body.admin-theme .admin-shared-sidebar {
        width: 282px !important;
        transform: translateX(-104%);
        transition: transform .22s ease;
    }

    body.admin-theme.admin-sidebar-open .admin-shared-sidebar {
        transform: translateX(0);
    }

    body.admin-theme .admin-sidebar-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1035;
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(3px);
        display: none;
    }

    body.admin-theme.admin-sidebar-open .admin-sidebar-backdrop {
        display: block;
    }

    body.admin-theme .admin-shared-main {
        margin-left: 0 !important;
        width: 100vw !important;
        max-width: 100vw !important;
    }

    body.admin-theme .admin-mobile-nav-toggle {
        display: inline-grid;
    }

    body.admin-theme .admin-command-topbar {
        padding: 10px 12px;
        width: 100vw;
        max-width: 100vw;
    }

    body.admin-theme .admin-system-status strong {
        display: none;
    }

    body.admin-theme .admin-topbar-user div:last-child {
        display: none;
    }
}

@media (max-width: 760px) {
    body.admin-theme :is(.report-page, .sc-page, .supplier-page, .export-page, .domestic-page, .promo-page, .admin-workflow-page) {
        width: 100vw !important;
        max-width: 100vw !important;
        padding: 16px 10px !important;
    }

    body.admin-theme :is(.report-grid, .supplier-stats, .domestic-stats, .export-stats, .sc-grid) {
        grid-template-columns: 1fr !important;
    }

    body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) {
        grid-template-columns: 1fr !important;
    }

    body.admin-theme :is(.workflow-hero, .report-hero, .supplier-hero, .sc-hero, .domestic-hero, .export-hero, .promo-head, .command-hero) :is(h1, h2) {
        font-size: clamp(1.2rem, 6vw, 1.55rem) !important;
        line-height: 1.22 !important;
        max-width: 100% !important;
    }

    body.admin-theme .admin-command-topbar {
        gap: 8px;
    }
}
</style>
<script>
(() => {
    const body = document.body;
    if (!body.classList.contains('admin-theme')) return;

    const themeButton = document.querySelector('[data-admin-theme-toggle]');
    const notificationButton = document.querySelector('[data-admin-notifications]');
    const notificationClose = document.querySelector('[data-admin-notifications-close]');
    const notificationPanel = document.querySelector('[data-admin-notification-panel]');
    const sidebarButton = document.querySelector('[data-admin-sidebar-toggle]');
    const sidebarBackdrop = document.querySelector('[data-admin-sidebar-close]');

    const applyThemeIcon = () => {
        const icon = themeButton ? themeButton.querySelector('i') : null;
        if (!icon) return;
        icon.className = body.classList.contains('admin-dark') ? 'fa-regular fa-sun' : 'fa-regular fa-moon';
    };

    if (localStorage.getItem('aethera-admin-theme') === 'dark') {
        body.classList.add('admin-dark');
    }
    applyThemeIcon();

    themeButton?.addEventListener('click', () => {
        body.classList.toggle('admin-dark');
        localStorage.setItem('aethera-admin-theme', body.classList.contains('admin-dark') ? 'dark' : 'light');
        applyThemeIcon();
    });

    notificationButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        body.classList.toggle('admin-notifications-open');
    });

    notificationClose?.addEventListener('click', () => {
        body.classList.remove('admin-notifications-open');
    });

    document.addEventListener('click', (event) => {
        if (!body.classList.contains('admin-notifications-open')) return;
        if (notificationPanel?.contains(event.target) || notificationButton?.contains(event.target)) return;
        body.classList.remove('admin-notifications-open');
    });

    sidebarButton?.addEventListener('click', () => {
        body.classList.toggle('admin-sidebar-open');
    });

    sidebarBackdrop?.addEventListener('click', () => {
        body.classList.remove('admin-sidebar-open');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        body.classList.remove('admin-notifications-open', 'admin-sidebar-open');
    });
})();
</script>
<?php endif; ?>
</body>
</html>
