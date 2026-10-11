<?php
require_once __DIR__ . '/../../helpers/Rbac.php';
require_once __DIR__ . '/../../helpers/FormatHelper.php';

$u = $account ?? (object)[];
if (is_array($u)) {
    $u = (object)$u;
}

$tab = $_GET['tab'] ?? 'info';
$orders = $orders ?? [];
$supplierBatches = $supplierBatches ?? [];
$supplierOrders = $supplierOrders ?? [];
$supplierProducts = $supplierProducts ?? [];
$supplierCategories = $supplierCategories ?? [];
$fullName = FormatHelper::cleanText($u->fullname ?? 'Thành viên Aethera');
$email = (string)($u->email ?? '');
$phone = FormatHelper::cleanText($u->phone ?? '');
$address = FormatHelper::cleanText($u->address ?? '');
$role = Rbac::normalizeRole((string)($u->role ?? 'user'));
if ($role === Rbac::ROLE_SUPPLIER && $tab === 'orders') {
    $tab = 'supplier';
}
$avatar = (string)($u->avatar ?? '');
$avatarPath = $avatar !== '' ? "/webbanhang/public/uploads/" . $avatar : '';
$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($fullName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($fullName, 0, 1));

if (!function_exists('profileOrderTotal')) {
    function profileOrderTotal($order) {
        $value = $order->total ?? $order->total_price ?? 0;
        return number_format((float)$value, 0, ',', '.') . 'đ';
    }
}

if (!function_exists('profileStatusMeta')) {
    function profileStatusMeta($status) {
        $status = strtolower((string)$status);
        $map = [
            'pending' => ['Chờ xác nhận', 'pending'],
            'confirmed' => ['Đã xác nhận', 'processing'],
            'preparing' => ['Chuẩn bị hàng', 'processing'],
            'packed' => ['Đã đóng gói', 'processing'],
            'processing' => ['Đang xử lý', 'processing'],
            'shipped' => ['Đang giao', 'shipping'],
            'completed' => ['Hoàn tất', 'completed'],
            'cancelled' => ['Đã hủy', 'cancelled']
        ];
        return $map[$status] ?? [$status !== '' ? $status : 'Đang xử lý', 'processing'];
    }
}

if (!function_exists('profilePaymentMeta')) {
    function profilePaymentMeta($status, $refundStatus = '') {
        $status = strtolower((string)$status);
        $refundStatus = strtolower((string)$refundStatus);
        if ($refundStatus === 'refunded' || $status === 'refunded') {
            return ['Đã hoàn tiền', 'refunded'];
        }
        $map = [
            'paid' => ['Đã thanh toán', 'completed'],
            'partial' => ['Đã cọc', 'processing'],
            'awaiting_confirmation' => ['Chờ xác nhận', 'pending'],
            'cancelled' => ['Không thu tiền', 'cancelled'],
            'pending' => ['Chưa thanh toán', 'processing']
        ];
        return $map[$status] ?? ['Chưa thanh toán', 'processing'];
    }
}

if (!function_exists('profileSupplierOrderMeta')) {
    function profileSupplierOrderMeta($status): array {
        $status = strtolower((string)$status);
        return [
            'pending' => ['Chờ điền phiếu lô', 'pending', 'Admin đã tạo đơn nhập. Bạn cần gửi thông tin lô hàng để kho kiểm tra.'],
            'supplier_submitted' => ['Đã gửi phiếu', 'processing', 'Kho/Admin đang kiểm số lượng, chất lượng và giá nhập.'],
            'warehouse_confirmed' => ['Đã nhập kho', 'shipping', 'Hàng đã vào tồn kho cửa hàng. Đơn đang chờ admin thanh toán tiền nhập.'],
            'completed' => ['Hoàn tất nhập', 'completed', 'Đơn nhập đã hoàn tất nghiệp vụ kho.'],
            'paid' => ['Đã thanh toán', 'completed', 'Admin đã thanh toán đủ tiền nhập cho lô hàng.'],
            'rejected' => ['Bị từ chối', 'cancelled', 'Phiếu lô không được duyệt, vui lòng liên hệ admin.'],
            'cancelled' => ['Đã hủy', 'cancelled', 'Đơn nhập đã bị hủy.'],
        ][$status] ?? ['Đang xử lý', 'processing', 'Đơn nhập đang được xử lý.'];
    }
}

if (!function_exists('profileMoney')) {
    function profileMoney($value): string {
        return number_format((float)$value, 0, ',', '.') . 'đ';
    }
}

if (!function_exists('profileQuantity')) {
    function profileQuantity($value): string {
        $value = (float)$value;
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}

if (!function_exists('profileSupplierProducts')) {
    function profileSupplierProducts($products): array {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string)$products)))));
    }
}
?>

<style>
:root {
    --profile-green: #00875a;
    --profile-green-dark: #006b48;
    --profile-bright: #00a86b;
    --profile-soft: #e8f8ef;
    --profile-bg: #f7f9fb;
    --profile-card: #ffffff;
    --profile-border: #e5e7eb;
    --profile-text: #1f2937;
    --profile-muted: #667085;
}

body {
    background: var(--profile-bg);
    color: var(--profile-text);
    font-family: 'Be Vietnam Pro', Arial, sans-serif;
}

.profile-page {
    padding: 34px 0 56px;
}

.profile-shell {
    width: min(1220px, calc(100% - 32px));
    margin: 0 auto;
}

.profile-hero,
.profile-sidebar,
.profile-panel,
.profile-mini-card {
    background: var(--profile-card);
    border: 1px solid var(--profile-border);
    box-shadow: 0 12px 30px rgba(15, 23, 42, .07);
}

.profile-hero {
    position: relative;
    overflow: hidden;
    min-height: 158px;
    border-radius: 18px;
    padding: 26px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
}

.profile-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        linear-gradient(135deg, rgba(232,248,239,.96), rgba(255,255,255,.98)),
        radial-gradient(circle at 88% 22%, rgba(0,168,107,.18), transparent 32%);
    z-index: 0;
}

.profile-hero > * {
    position: relative;
    z-index: 1;
}

.profile-kicker {
    margin-bottom: 8px;
    color: var(--profile-green);
    font-size: .78rem;
    font-weight: 900;
    text-transform: uppercase;
}

.profile-hero h1 {
    margin: 0;
    color: #111827;
    font-size: clamp(1.7rem, 3vw, 2.6rem);
    font-weight: 900;
}

.profile-hero p {
    max-width: 620px;
    margin: 10px 0 0;
    color: var(--profile-muted);
    line-height: 1.7;
}

.profile-status {
    min-width: 210px;
    padding: 14px 16px;
    border-radius: 14px;
    background: #ffffff;
    border: 1px solid #cdeee0;
}

.profile-status span {
    display: block;
    color: var(--profile-muted);
    font-size: .82rem;
    font-weight: 700;
}

.profile-status strong {
    display: block;
    margin-top: 4px;
    color: var(--profile-green);
    font-size: 1.08rem;
    font-weight: 900;
}

.profile-grid {
    display: grid;
    grid-template-columns: 310px minmax(0, 1fr);
    gap: 22px;
    margin-top: 0;
}

.profile-sidebar,
.profile-panel {
    border-radius: 18px;
}

.profile-sidebar {
    padding: 22px;
    align-self: start;
    position: sticky;
    top: 96px;
}

.avatar-wrap {
    display: grid;
    place-items: center;
    margin-bottom: 16px;
}

.profile-avatar,
.profile-initial {
    width: 118px;
    height: 118px;
    border-radius: 32px;
    border: 4px solid var(--profile-soft);
    background: var(--profile-soft);
    box-shadow: 0 14px 28px rgba(0, 135, 90, .12);
}

.profile-avatar {
    object-fit: cover;
}

.profile-initial {
    display: grid;
    place-items: center;
    color: var(--profile-green);
    font-size: 3rem;
    font-weight: 950;
}

.profile-name {
    margin: 0;
    text-align: center;
    color: #111827;
    font-size: 1.2rem;
    font-weight: 900;
}

.profile-email {
    margin: 6px 0 16px;
    text-align: center;
    color: var(--profile-muted);
    font-size: .92rem;
    font-weight: 600;
    word-break: break-word;
}

.profile-chip {
    width: fit-content;
    margin: 0 auto 18px;
    padding: 7px 12px;
    border-radius: 999px;
    background: var(--profile-soft);
    color: var(--profile-green);
    font-size: .78rem;
    font-weight: 900;
    text-transform: uppercase;
}

.profile-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--profile-border);
}

.profile-nav a {
    min-height: 46px;
    padding: 0 14px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #334155;
    font-weight: 800;
    text-decoration: none;
    transition: .18s ease;
}

.profile-nav a:hover,
.profile-nav a.active {
    background: var(--profile-soft);
    color: var(--profile-green);
}

.profile-nav a.text-danger:hover {
    background: #fef2f2;
    color: #dc2626 !important;
}

.profile-panel {
    padding: 24px;
}

.panel-header {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.panel-header h2 {
    margin: 0;
    color: #111827;
    font-size: 1.35rem;
    font-weight: 900;
}

.panel-header p {
    margin: 5px 0 0;
    color: var(--profile-muted);
    font-weight: 600;
}

.profile-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.form-field.full {
    grid-column: 1 / -1;
}

.form-field label {
    margin-bottom: 8px;
    color: #344054;
    font-size: .86rem;
    font-weight: 850;
}

.profile-input,
.profile-textarea {
    width: 100%;
    border: 1px solid var(--profile-border);
    border-radius: 13px;
    background: #ffffff;
    color: var(--profile-text);
    padding: 12px 14px;
    font-weight: 650;
    outline: none;
    transition: .18s ease;
}

.profile-input {
    min-height: 46px;
}

.profile-textarea {
    min-height: 92px;
    resize: vertical;
}

.profile-input:focus,
.profile-textarea:focus {
    border-color: var(--profile-bright);
    box-shadow: 0 0 0 4px rgba(0,168,107,.12);
}

.btn-profile {
    min-height: 46px;
    padding: 0 18px;
    border: 0;
    border-radius: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--profile-green-dark), var(--profile-bright));
    color: #ffffff;
    font-weight: 900;
    box-shadow: 0 12px 24px rgba(0, 168, 107, .18);
}

.orders-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
}

.orders-table th {
    padding: 13px 14px;
    background: #f8fafc;
    color: #667085;
    font-size: .78rem;
    font-weight: 900;
    text-transform: uppercase;
    border-bottom: 1px solid var(--profile-border);
}

.orders-table td {
    padding: 15px 14px;
    color: #344054;
    font-weight: 650;
    border-bottom: 1px solid #eef2f7;
}

.orders-table tr:last-child td {
    border-bottom: 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 900;
}

.status-pending { background: #fff7ed; color: #c2410c; }
.status-processing { background: #eff6ff; color: #2563eb; }
.status-shipping { background: #ecfeff; color: #0891b2; }
.status-completed { background: #ecfdf5; color: #047857; }
.status-cancelled { background: #fef2f2; color: #dc2626; }
.status-refunded { background: #e8fff4; color: #047857; }
.order-track-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 32px;
    padding: 0 12px;
    border-radius: 999px;
    background: #dcfce7;
    color: #047857;
    font-weight: 900;
    text-decoration: none;
    white-space: nowrap;
}

.empty-orders {
    padding: 42px 20px;
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    text-align: center;
    color: var(--profile-muted);
    background: #fbfdff;
}

.security-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 18px;
}

.profile-mini-card {
    border-radius: 16px;
    padding: 18px;
    background: #f8fffb;
    border-color: #cdeee0;
}

.profile-mini-card h3 {
    margin: 0 0 10px;
    color: #111827;
    font-size: 1rem;
    font-weight: 900;
}

.profile-mini-card p {
    margin: 0;
    color: var(--profile-muted);
    line-height: 1.7;
    font-weight: 600;
}

.supplier-order-stack {
    display: grid;
    gap: 16px;
}

.supplier-order-card {
    border: 1px solid #d9e8de;
    border-radius: 12px;
    background: #ffffff;
    padding: 14px;
    display: grid;
    gap: 10px;
}

.supplier-order-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
}

.supplier-order-title strong,
.supplier-order-title span {
    display: block;
}

.supplier-order-title strong {
    color: #111827;
    font-size: 1rem;
    line-height: 1.25;
    font-weight: 900;
}

.supplier-order-title span {
    margin-top: 3px;
    color: var(--profile-muted);
    font-size: .9rem;
    font-weight: 650;
}

.supplier-status-note {
    border-left: 2px solid var(--profile-bright);
    padding: 7px 0 7px 10px;
    color: #475569;
    font-size: .88rem;
    line-height: 1.45;
    font-weight: 650;
}

.supplier-order-progress {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.supplier-step {
    min-height: 38px;
    border: 1px solid #edf2f7;
    border-radius: 10px;
    background: #fbfcfd;
    padding: 7px 9px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: .78rem;
    font-weight: 850;
}

.supplier-step-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #cbd5e1;
    flex: 0 0 auto;
}

.supplier-step.done {
    border-color: #cdeee0;
    background: #f3fbf7;
    color: #047857;
}

.supplier-step.done .supplier-step-dot {
    background: #10b981;
}

.supplier-step.current {
    border-color: #fed7aa;
    background: #fff7ed;
    color: #c2410c;
}

.supplier-step.current .supplier-step-dot {
    background: #f97316;
}

.supplier-money-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.supplier-money-grid div {
    border: 0;
    border-radius: 10px;
    background: #f4f8f6;
    padding: 8px 10px;
}

.supplier-money-grid small {
    display: block;
    color: var(--profile-muted);
    font-size: .68rem;
    font-weight: 850;
    text-transform: uppercase;
}

.supplier-money-grid strong {
    display: block;
    margin-top: 3px;
    color: #111827;
    font-size: .98rem;
    line-height: 1.25;
    font-weight: 900;
}

.supplier-price-note {
    border: 1px solid #edf2f7;
    border-radius: 10px;
    background: #ffffff;
    padding: 8px 10px;
    color: #475569;
    font-size: .84rem;
    font-weight: 650;
    line-height: 1.45;
}

.supplier-price-note span,
.supplier-price-note small {
    display: block;
}

.supplier-price-note span {
    margin-bottom: 2px;
    color: var(--profile-muted);
    font-size: .7rem;
    font-weight: 900;
    text-transform: uppercase;
}

.supplier-price-note strong {
    display: block;
    color: #111827;
    font-size: .9rem;
    line-height: 1.35;
    font-weight: 900;
}

.supplier-price-note small {
    margin-top: 2px;
    color: var(--profile-muted);
    font-size: .8rem;
    font-weight: 650;
}

.supplier-lot-line {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.supplier-lot-line span {
    border-radius: 999px;
    background: #f4f8f6;
    padding: 6px 9px;
    color: #475569;
    font-size: .82rem;
    font-weight: 800;
    line-height: 1.25;
}

.supplier-lot-line strong {
    color: #111827;
}

.supplier-history-list {
    display: grid;
    gap: 8px;
}

.supplier-history-card {
    border: 1px solid #e4efe8;
    border-radius: 12px;
    background: #ffffff;
    padding: 10px 12px;
    display: grid;
    grid-template-columns: minmax(180px, 1fr) minmax(0, 1.45fr) minmax(125px, auto);
    gap: 10px;
    align-items: center;
}

.supplier-history-top {
    display: grid;
    gap: 4px;
}

.supplier-history-code small,
.supplier-history-grid small {
    display: block;
    color: var(--profile-muted);
    font-size: .68rem;
    font-weight: 900;
    text-transform: uppercase;
}

.supplier-history-code strong {
    display: block;
    margin-top: 2px;
    color: #111827;
    font-size: .92rem;
    font-weight: 900;
    overflow-wrap: anywhere;
}

.supplier-history-product {
    color: #111827;
    font-size: .92rem;
    font-weight: 900;
    line-height: 1.4;
}

.supplier-history-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 8px;
}

.supplier-history-grid > div {
    border: 0;
    border-left: 1px solid #eef2f7;
    border-radius: 0;
    background: transparent;
    padding: 0 0 0 9px;
    min-width: 0;
}

.supplier-history-grid strong {
    display: block;
    margin-top: 2px;
    color: #111827;
    font-size: .88rem;
    font-weight: 900;
    line-height: 1.35;
}

.supplier-history-grid > div > span:not(.status-badge) {
    display: block;
    margin-top: 2px;
    color: #475569;
    font-size: .76rem;
    font-weight: 650;
    line-height: 1.35;
}

.supplier-history-status {
    display: grid;
    justify-items: end;
    gap: 6px;
}

.supplier-history-status .status-badge {
    white-space: nowrap;
}

.supplier-history-amount {
    color: #111827;
    font-size: 1rem;
    font-weight: 950;
    line-height: 1.25;
    white-space: nowrap;
}

.supplier-history-amount-label {
    color: var(--profile-muted);
    font-size: .68rem;
    font-weight: 900;
    text-transform: uppercase;
}

.supplier-price-hint {
    display: block;
    margin-top: 6px;
    color: var(--profile-muted);
    font-size: .76rem;
    font-weight: 650;
    line-height: 1.4;
}

.supplier-batch-form {
    border-top: 1px solid #e4efe8;
    padding-top: 16px;
}

.supplier-form-help {
    border: 1px solid #d9e8de;
    border-radius: 12px;
    background: #f8fffb;
    padding: 10px 12px;
    color: #475569;
    font-weight: 650;
    line-height: 1.5;
    margin-bottom: 12px;
}

.supplier-basic-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, .8fr) minmax(0, .8fr);
    gap: 12px;
}

.supplier-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.supplier-form-grid .wide {
    grid-column: span 2;
}

.supplier-form-grid .full {
    grid-column: 1 / -1;
}

.supplier-basic-grid .full {
    grid-column: 1 / -1;
}

.supplier-basic-grid label,
.supplier-form-grid label {
    display: block;
    margin-bottom: 7px;
    color: #344054;
    font-size: .78rem;
    font-weight: 850;
}

.supplier-basic-grid input,
.supplier-basic-grid select,
.supplier-form-grid input,
.supplier-form-grid select,
.supplier-form-grid textarea {
    width: 100%;
    min-height: 42px;
    border: 1px solid var(--profile-border);
    border-radius: 12px;
    background: #fff;
    padding: 9px 12px;
    outline: none;
}

.supplier-form-grid textarea {
    min-height: 78px;
    resize: vertical;
}

.supplier-advanced {
    margin-top: 12px;
    border: 1px solid #e4efe8;
    border-radius: 14px;
    background: #fff;
}

.supplier-advanced summary {
    min-height: 44px;
    padding: 0 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: #047857;
    font-weight: 850;
    list-style: none;
}

.supplier-advanced summary::-webkit-details-marker {
    display: none;
}

.supplier-advanced .supplier-form-grid {
    border-top: 1px solid #e4efe8;
    padding: 12px;
}

.supplier-submit-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
}

.supplier-submit-row small {
    color: var(--profile-muted);
    font-weight: 650;
}

@media (max-width: 991px) {
    .profile-hero,
    .profile-grid,
    .security-grid,
    .supplier-form-grid,
    .supplier-basic-grid {
        grid-template-columns: 1fr;
    }

    .supplier-form-grid .wide {
        grid-column: auto;
    }

    .profile-grid {
        display: block;
    }

    .supplier-history-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .supplier-history-card {
        grid-template-columns: 1fr;
        align-items: start;
    }

    .supplier-history-status {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-items: start;
    }

    .profile-sidebar {
        position: static;
        margin-bottom: 18px;
    }

    .profile-hero {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 640px) {
    .profile-shell {
        width: min(100% - 22px, 1220px);
    }

    .profile-form-grid {
        grid-template-columns: 1fr;
    }

    .supplier-order-top,
    .supplier-money-grid,
    .supplier-order-progress,
    .supplier-history-grid {
        grid-template-columns: 1fr;
    }

    .supplier-history-top {
        display: grid;
    }

    .supplier-history-grid > div {
        border-left: 0;
        border-top: 1px solid #eef2f7;
        padding: 8px 0 0;
    }

    .supplier-history-amount-label {
        width: 100%;
    }

    .supplier-basic-grid .full {
        grid-column: auto;
    }

    .profile-panel,
    .profile-hero,
    .profile-sidebar {
        padding: 18px;
    }
}
</style>

<main class="profile-page">
    <div class="profile-shell">
        <?php
            $profileMessageView = __DIR__ . '/../shares/message.php';
            if (file_exists($profileMessageView)) {
                include $profileMessageView;
            }
        ?>
        <div class="profile-grid">
            <aside class="profile-sidebar">
                <div class="avatar-wrap">
                    <?php if ($avatarPath): ?>
                        <img class="profile-avatar" src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($fullName) ?>">
                    <?php else: ?>
                        <div class="profile-initial"><?= htmlspecialchars($initial) ?></div>
                    <?php endif; ?>
                </div>
                <h2 class="profile-name"><?= htmlspecialchars($fullName) ?></h2>
                <p class="profile-email"><?= htmlspecialchars($email ?: 'Chưa cập nhật email') ?></p>
                <div class="profile-chip"><?= htmlspecialchars(Rbac::label($role)) ?></div>

                <nav class="profile-nav" aria-label="Profile tabs">
                    <a href="?tab=info" class="<?= $tab === 'info' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user"></i>
                        <span>Thông tin</span>
                    </a>
                    <?php if ($role === Rbac::ROLE_SUPPLIER): ?>
                        <a href="?tab=supplier" class="<?= $tab === 'supplier' ? 'active' : '' ?>">
                            <i class="fa-solid fa-file-signature"></i>
                            <span>Đơn nhập lô</span>
                        </a>
                    <?php else: ?>
                        <a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">
                            <i class="fa-solid fa-box"></i>
                            <span>Đơn hàng</span>
                        </a>
                    <?php endif; ?>
                    <a href="?tab=password" class="<?= $tab === 'password' ? 'active' : '' ?>">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Bảo mật</span>
                    </a>
                    <a href="/webbanhang/account/logout" class="text-danger">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Đăng xuất</span>
                    </a>
                </nav>
            </aside>

            <section class="profile-panel">
                <?php if ($tab === 'info'): ?>
                    <div class="panel-header">
                        <div>
                            <h2>Thông tin cá nhân</h2>
                            <p>Cập nhật thông tin liên hệ để đơn hàng được xử lý nhanh hơn.</p>
                        </div>
                    </div>

                    <form method="post" action="/webbanhang/account/updateProfile" enctype="multipart/form-data">
                        <div class="profile-form-grid">
                            <div class="form-field">
                                <label for="fullname">Họ tên</label>
                                <input id="fullname" class="profile-input" name="fullname" value="<?= htmlspecialchars($fullName) ?>" placeholder="Nhập họ tên">
                            </div>
                            <div class="form-field">
                                <label for="phone">Số điện thoại</label>
                                <input id="phone" class="profile-input" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="Nhập số điện thoại">
                            </div>
                            <div class="form-field">
                                <label for="email">Email</label>
                                <input id="email" class="profile-input" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="Nhập email">
                            </div>
                            <div class="form-field">
                                <label for="avatar">Ảnh đại diện</label>
                                <input id="avatar" type="file" class="profile-input" name="avatar">
                            </div>
                            <div class="form-field full">
                                <label for="address">Địa chỉ giao hàng</label>
                                <textarea id="address" class="profile-textarea" name="address" placeholder="Nhập địa chỉ giao hàng"><?= htmlspecialchars($address) ?></textarea>
                            </div>
                        </div>
                        <button class="btn-profile mt-4" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Lưu thay đổi
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($tab === 'orders'): ?>
                    <div class="panel-header">
                        <div>
                            <h2>Đơn hàng của bạn</h2>
                            <p>Theo dõi trạng thái các đơn nông sản đã đặt.</p>
                        </div>
                    </div>

                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Mã đơn</th>
                                        <th>Ngày tạo</th>
                                        <th>Tổng tiền</th>
                                        <th>Thanh toán</th>
                                        <th>Trạng thái</th>
                                        <th>Theo dõi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $o): ?>
                                        <?php [$statusText, $statusClass] = profileStatusMeta($o->status ?? 'processing'); ?>
                                        <?php [$paymentText, $paymentClass] = profilePaymentMeta($o->payment_status ?? 'pending', $o->refund_status ?? ''); ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($o->id ?? '') ?></strong></td>
                                            <td><?= htmlspecialchars($o->created_at ?? '') ?></td>
                                            <td><strong><?= profileOrderTotal($o) ?></strong></td>
                                            <td><span class="status-badge status-<?= $paymentClass ?>"><?= htmlspecialchars($paymentText) ?></span></td>
                                            <td><span class="status-badge status-<?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span></td>
                                            <td>
                                                <a class="order-track-link" href="/webbanhang/account/orderDetail/<?= (int)($o->id ?? 0) ?>">
                                                    <i class="fa-solid fa-route"></i>
                                                    Xem
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-orders">
                            <i class="fa-solid fa-basket-shopping fa-2x mb-3 text-success"></i>
                            <h4>Chưa có đơn hàng</h4>
                            <p class="mb-0">Khi bạn đặt hàng, các đơn sẽ hiển thị tại đây để tiện theo dõi.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($tab === 'password'): ?>
                    <div class="panel-header">
                        <div>
                            <h2>Bảo mật tài khoản</h2>
                            <p>Cập nhật mật khẩu định kỳ để giữ tài khoản an toàn.</p>
                        </div>
                    </div>

                    <div class="security-grid">
                        <form method="post" action="/webbanhang/account/changePassword">
                            <div class="profile-form-grid">
                                <div class="form-field full">
                                    <label for="old_password">Mật khẩu cũ</label>
                                    <input id="old_password" type="password" class="profile-input" name="old_password" placeholder="Nhập mật khẩu cũ">
                                </div>
                                <div class="form-field full">
                                    <label for="new_password">Mật khẩu mới</label>
                                    <input id="new_password" type="password" class="profile-input" name="new_password" placeholder="Nhập mật khẩu mới">
                                </div>
                                <div class="form-field full">
                                    <label for="confirm_password">Xác nhận mật khẩu</label>
                                    <input id="confirm_password" type="password" class="profile-input" name="confirm_password" placeholder="Nhập lại mật khẩu mới">
                                </div>
                            </div>
                            <button class="btn-profile mt-4" type="submit">
                                <i class="fa-solid fa-key"></i>
                                Đổi mật khẩu
                            </button>
                        </form>

                        <div class="profile-mini-card">
                            <h3><i class="fa-solid fa-shield-heart me-2 text-success"></i>Gợi ý bảo mật</h3>
                            <p>Dùng mật khẩu dài, có chữ hoa, chữ thường, số và ký tự đặc biệt. Không dùng lại mật khẩu đã chia sẻ khi trình chiếu.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($tab === 'supplier' && $role === Rbac::ROLE_SUPPLIER): ?>
                    <div class="panel-header">
                        <div>
                            <h2>Cổng nhà cung cấp</h2>
                            <p>Theo dõi hồ sơ cung ứng liên kết với tài khoản nhà cung cấp.</p>
                        </div>
                    </div>

                    <?php if (!empty($supplierPortal)): ?>
                        <?php $registeredSupplyProducts = profileSupplierProducts($supplierPortal->products ?? ''); ?>
                        <div class="profile-form-grid">
                            <div class="profile-mini-card">
                                <h3><i class="fa-solid fa-warehouse me-2 text-success"></i><?= htmlspecialchars($supplierPortal->name ?? 'Nhà cung cấp') ?></h3>
                                <p>
                                    <?= htmlspecialchars($supplierPortal->contact_person ?? 'Chưa cập nhật người liên hệ') ?>
                                    <?= !empty($supplierPortal->phone) ? ' · ' . htmlspecialchars($supplierPortal->phone) : '' ?><br>
                                    <?= htmlspecialchars($supplierPortal->address ?? 'Chưa cập nhật địa chỉ') ?>
                                </p>
                            </div>
                            <div class="profile-mini-card">
                                <h3><i class="fa-solid fa-seedling me-2 text-success"></i>Nông sản cung ứng</h3>
                                <p><?= htmlspecialchars($supplierPortal->products ?? 'Chưa cập nhật') ?></p>
                            </div>
                            <div class="profile-mini-card">
                                <h3><i class="fa-solid fa-scale-balanced me-2 text-success"></i>Số lượng cung ứng</h3>
                                <p><?= number_format((float)($supplierPortal->supply_quantity_kg ?? 0), 0, ',', '.') ?> kg</p>
                            </div>
                            <div class="profile-mini-card">
                                <h3><i class="fa-solid fa-map-location-dot me-2 text-success"></i>Vùng trồng</h3>
                                <p><?= htmlspecialchars($supplierPortal->farm_location ?? 'Chưa cập nhật') ?></p>
                            </div>
                            <div class="profile-mini-card">
                                <h3><i class="fa-solid fa-ruler-combined me-2 text-success"></i>Diện tích</h3>
                                <p><?= number_format((float)($supplierPortal->cultivation_area ?? 0), 2, ',', '.') ?> ha</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="panel-header">
                                <div>
                                    <h2>Đơn nhập lô từ admin</h2>
                                    <p>Nhà cung cấp chỉ gửi phiếu lô cho cửa hàng. Đơn bán cho khách được quản lý riêng bởi cửa hàng.</p>
                                </div>
                            </div>

                            <?php if (!empty($supplierOrders)): ?>
                                <div class="supplier-order-stack">
                                    <?php foreach ($supplierOrders as $supplierOrder): ?>
                                        <?php
                                            [$supplierOrderLabel, $supplierOrderClass, $supplierOrderNote] = profileSupplierOrderMeta($supplierOrder->status ?? 'pending');
                                            $supplierOrderTotal = (float)($supplierOrder->total_amount ?? $supplierOrder->payable_amount ?? 0);
                                            $supplierOrderPaid = (float)($supplierOrder->paid_amount ?? 0);
                                            $supplierSuggestedQuantity = (float)($supplierOrder->quantity ?? 0);
                                            $supplierConfirmedQuantity = (float)($supplierOrder->confirmed_quantity ?? 0);
                                            $supplierDisplayQuantity = $supplierConfirmedQuantity > 0 ? $supplierConfirmedQuantity : $supplierSuggestedQuantity;
                                            $supplierImportPrice = (float)($supplierOrder->import_price ?? 0);
                                            $supplierEstimatedTotal = $supplierDisplayQuantity > 0 && $supplierImportPrice > 0
                                                ? $supplierDisplayQuantity * $supplierImportPrice
                                                : 0;
                                            $supplierHasOfficialTotal = $supplierOrderTotal > 0;
                                            $supplierDisplayTotal = $supplierHasOfficialTotal ? $supplierOrderTotal : $supplierEstimatedTotal;
                                            $supplierOrderRemaining = max(0, $supplierDisplayTotal - $supplierOrderPaid);
                                            $supplierTotalLabel = $supplierHasOfficialTotal ? 'Phải trả' : 'Dự tính';
                                            $supplierRemainingLabel = $supplierHasOfficialTotal ? 'Còn lại' : 'Dự tính còn';
                                            $supplierOrderStatus = strtolower((string)($supplierOrder->status ?? 'pending'));
                                            $supplierStepIndex = match ($supplierOrderStatus) {
                                                'supplier_submitted' => 2,
                                                'warehouse_confirmed', 'completed' => 3,
                                                'paid' => 4,
                                                'rejected', 'cancelled' => 0,
                                                default => 1,
                                            };
                                            $supplierRequestedProduct = trim((string)($supplierOrder->product_name ?? ''));
                                            $supplierAllowedProducts = $supplierRequestedProduct !== ''
                                                ? [$supplierRequestedProduct]
                                                : $registeredSupplyProducts;
                                            $canSubmitBatch = (string)($supplierOrder->status ?? 'pending') === 'pending' && empty($supplierOrder->batch_id);
                                        ?>
                                        <article class="supplier-order-card">
                                            <div class="supplier-order-top">
                                                <div class="supplier-order-title">
                                                    <strong>Đơn nhập #<?= (int)$supplierOrder->id ?></strong>
                                                    <span><?= htmlspecialchars($supplierOrder->warehouse_name ?? 'Kho sẽ được admin xác nhận') ?></span>
                                                </div>
                                                <span class="status-badge status-<?= htmlspecialchars($supplierOrderClass) ?>"><?= htmlspecialchars($supplierOrderLabel) ?></span>
                                            </div>

                                            <div class="supplier-order-progress">
                                                <?php
                                                    $progressSteps = [
                                                        ['Phiếu lô', 1],
                                                        ['Nhập kho', 2],
                                                        ['Thanh toán', 3],
                                                    ];
                                                ?>
                                                <?php foreach ($progressSteps as [$stepLabel, $stepNumber]): ?>
                                                    <?php
                                                        $stepClass = '';
                                                        if ($supplierStepIndex > $stepNumber) {
                                                            $stepClass = 'done';
                                                        } elseif ($supplierStepIndex === $stepNumber) {
                                                            $stepClass = 'current';
                                                        }
                                                    ?>
                                                    <div class="supplier-step <?= htmlspecialchars($stepClass) ?>">
                                                        <span class="supplier-step-dot"></span>
                                                        <span><?= htmlspecialchars($stepLabel) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="supplier-status-note"><?= htmlspecialchars($supplierOrderNote) ?></div>

                                            <div class="supplier-money-grid">
                                                <div><small><?= htmlspecialchars($supplierTotalLabel) ?></small><strong><?= profileMoney($supplierDisplayTotal) ?></strong></div>
                                                <div><small>Đã trả</small><strong><?= profileMoney($supplierOrderPaid) ?></strong></div>
                                                <div><small><?= htmlspecialchars($supplierRemainingLabel) ?></small><strong><?= profileMoney($supplierOrderRemaining) ?></strong></div>
                                            </div>

                                            <?php if (!empty($supplierOrder->batch_id)): ?>
                                                <?php $supplierPriceFormula = $supplierDisplayQuantity > 0 && $supplierImportPrice > 0
                                                    ? profileQuantity($supplierDisplayQuantity) . ' ' . htmlspecialchars($supplierOrder->unit ?? 'kg') . ' x ' . profileMoney($supplierImportPrice) . '/kg = ' . profileMoney($supplierEstimatedTotal)
                                                    : 'Admin chưa chốt giá nhập/kg cho phiếu lô này.';
                                                ?>
                                                <div class="supplier-price-note">
                                                    <span>Công thức giá</span>
                                                    <strong><?= $supplierPriceFormula ?></strong>
                                                    <small><?= $supplierHasOfficialTotal ? 'Giá đã được kho/admin xác nhận.' : 'Giá bạn nhập là giá đề xuất, admin sẽ chốt lại khi xác nhận nhập kho.' ?></small>
                                                </div>
                                                <div class="supplier-lot-line">
                                                    <span>Mã lô: <strong><?= htmlspecialchars($supplierOrder->batch_code ?? '') ?></strong></span>
                                                    <span><?= htmlspecialchars($supplierOrder->product_name ?? 'Sản phẩm') ?></span>
                                                    <span><?= profileQuantity($supplierDisplayQuantity) ?> <?= htmlspecialchars($supplierOrder->unit ?? 'kg') ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($canSubmitBatch): ?>
                                                <form class="supplier-batch-form" method="POST" action="/webbanhang/account/submitSupplierBatch" enctype="multipart/form-data">
                                                    <input type="hidden" name="supplier_order_id" value="<?= (int)$supplierOrder->id ?>">
                                                    <div class="supplier-form-help">
                                                        Tài khoản này đang liên kết với <strong><?= htmlspecialchars($supplierPortal->name ?? 'nhà cung cấp') ?></strong>. Chỉ chọn được hàng đã đăng ký cung cấp trong hồ sơ nhà cung cấp.
                                                    </div>
                                                    <?php if (!empty($supplierAllowedProducts)): ?>
                                                        <div class="supplier-basic-grid">
                                                            <div class="full">
                                                                <label>Hàng admin yêu cầu</label>
                                                                <select name="product_name" required>
                                                                    <?php if ($supplierRequestedProduct === ''): ?>
                                                                        <option value="">Chọn hàng cung cấp</option>
                                                                    <?php endif; ?>
                                                                    <?php foreach ($supplierAllowedProducts as $registeredProduct): ?>
                                                                        <option value="<?= htmlspecialchars($registeredProduct) ?>" <?= $supplierRequestedProduct !== '' ? 'selected' : '' ?>><?= htmlspecialchars($registeredProduct) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label>Số lượng lô (kg) *</label>
                                                                <input type="number" min="1" name="quantity" value="<?= htmlspecialchars((string)max(1, $supplierSuggestedQuantity)) ?>" required>
                                                            </div>
                                                            <div>
                                                                <label>Ngày thu hoạch *</label>
                                                                <input type="date" name="harvest_date" value="<?= date('Y-m-d') ?>" required>
                                                            </div>
                                                            <div>
                                                                <label>Giá đề xuất/kg *</label>
                                                                <input type="number" min="1" step="1000" name="import_price" value="<?= $supplierImportPrice > 0 ? htmlspecialchars((string)$supplierImportPrice) : '' ?>" placeholder="VD: 25000" required>
                                                                <span class="supplier-price-hint">Admin sẽ kiểm tra và chốt giá nhập/kg khi xác nhận nhập kho.</span>
                                                            </div>
                                                            <div>
                                                                <label>Mã lô</label>
                                                                <input name="batch_code" placeholder="Tự sinh nếu bỏ trống">
                                                            </div>
                                                            <div>
                                                                <label>Danh mục</label>
                                                                <select name="category_id">
                                                                    <option value="">Chọn nếu có</option>
                                                                    <?php foreach ($supplierCategories as $category): ?>
                                                                        <option value="<?= (int)$category->id ?>"><?= htmlspecialchars($category->name ?? '') ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <details class="supplier-advanced" open>
                                                            <summary>
                                                                <i class="fa-solid fa-sliders"></i>
                                                                <span>Thông tin bắt buộc để nộp phiếu</span>
                                                            </summary>
                                                            <div class="supplier-form-grid">
                                                                <div>
                                                                    <label>Ngày gieo trồng *</label>
                                                                    <input type="date" name="planting_date" required>
                                                                </div>
                                                                <div>
                                                                    <label>Diện tích (ha) *</label>
                                                                    <input type="number" step="0.01" min="0.01" name="area" placeholder="VD: 1.5" required>
                                                                </div>
                                                                <div>
                                                                    <label>Tiêu chuẩn chất lượng *</label>
                                                                    <input name="quality_standard" placeholder="VietGAP, GlobalGAP..." required>
                                                                </div>
                                                                <div>
                                                                    <label>Nhiệt độ bảo quản *</label>
                                                                    <input type="number" step="0.1" name="storage_temperature" placeholder="8" required>
                                                                </div>
                                                                <div>
                                                                    <label>Độ ẩm bảo quản *</label>
                                                                    <input type="number" step="0.1" min="0" max="100" name="storage_humidity" placeholder="72" required>
                                                                </div>
                                                                <div>
                                                                    <label>Ảnh sản phẩm</label>
                                                                    <input type="file" name="product_image" accept="image/*">
                                                                </div>
                                                                <div class="full">
                                                                    <label>Phương pháp chăm sóc *</label>
                                                                    <textarea name="cultivation_method" placeholder="Tưới nhỏ giọt, canh tác hữu cơ..." required></textarea>
                                                                </div>
                                                                <div class="wide">
                                                                    <label>Phân bón sử dụng *</label>
                                                                    <textarea name="fertilizer" placeholder="Phân hữu cơ, NPK..." required></textarea>
                                                                </div>
                                                                <div>
                                                                    <label>Thuốc BVTV *</label>
                                                                    <textarea name="pesticide" placeholder="Ghi Không sử dụng nếu không có" required></textarea>
                                                                </div>
                                                                <div class="wide">
                                                                    <label>Phương pháp bảo quản *</label>
                                                                    <input name="preservation_method" placeholder="Kho lạnh, hút chân không..." required>
                                                                </div>
                                                                <div>
                                                                    <label>Ghi chú</label>
                                                                    <textarea name="notes" placeholder="Ghi chú đóng gói, kiểm định..."></textarea>
                                                                </div>
                                                            </div>
                                                        </details>

                                                        <div class="supplier-submit-row">
                                                            <button class="btn-profile" type="submit">
                                                                <i class="fa-solid fa-paper-plane"></i>
                                                                Gửi phiếu lô
                                                            </button>
                                                            <small>Kho/Admin sẽ xác nhận số lượng thực nhập trước khi hàng vào tồn kho.</small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="empty-orders">
                                                            <i class="fa-solid fa-lock fa-2x mb-3 text-success"></i>
                                                            <h4>Chưa có hàng đăng ký cung cấp</h4>
                                                            <p class="mb-0">Admin cần cập nhật mục “Nông sản cung ứng” trong hồ sơ nhà cung cấp trước khi gửi phiếu lô.</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-orders">
                                    <i class="fa-solid fa-file-circle-plus fa-2x mb-3 text-success"></i>
                                    <h4>Chưa có đơn nhập lô</h4>
                                    <p class="mb-0">Khi admin tạo đơn nhập từ hồ sơ nhà cung cấp này, phiếu lô cần điền sẽ hiển thị tại đây.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-orders">
                            <i class="fa-solid fa-link-slash fa-2x mb-3 text-success"></i>
                            <h4>Chưa liên kết hồ sơ nhà cung cấp</h4>
                            <p class="mb-0">Admin cần gán `supplier_id` cho tài khoản này để nhà cung cấp xem được hồ sơ vùng trồng và lô hàng liên quan.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
