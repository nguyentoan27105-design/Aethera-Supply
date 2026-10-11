<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../helpers/Rbac.php';
require_once __DIR__ . '/../../helpers/CsrfHelper.php';
require_once __DIR__ . '/../../helpers/FormatHelper.php';
require_once __DIR__ . '/../../helpers/AdminNotificationHelper.php';

$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

$current_url = $_SERVER['REQUEST_URI'] ?? '';
$currentRole = Rbac::normalizeRole($_SESSION['user_role'] ?? 'user');
$isAdmin = isset($_SESSION['user_id']) && Rbac::hasAdminAreaAccess($currentRole);
$isAdminPage = strpos($current_url, '/admin') !== false;
$isAdminShellPage = $isAdminPage;
$adminShellProfileName = $_SESSION['fullname'] ?? 'Nhân viên';
$adminShellRoleLabel = Rbac::label($currentRole);
$adminShellInitials = 'NV';
if (trim((string)$adminShellProfileName) !== '') {
    $adminNameParts = preg_split('/\s+/', trim((string)$adminShellProfileName));
    $adminFirst = $adminNameParts[0] ?? '';
    $adminLast = count($adminNameParts) > 1 ? end($adminNameParts) : '';
    if (function_exists('mb_substr')) {
        $adminShellInitials = mb_strtoupper(mb_substr($adminFirst, 0, 1) . mb_substr($adminLast ?: $adminFirst, 0, 1), 'UTF-8');
    } else {
        $adminShellInitials = strtoupper(substr($adminFirst, 0, 1) . substr($adminLast ?: $adminFirst, 0, 1));
    }
}

if (!function_exists('adminShellOrderStatusLabel')) {
    function adminShellOrderStatusLabel(string $status): string
    {
        return [
            'pending' => 'Chờ duyệt',
            'confirmed' => 'Đã duyệt',
            'preparing' => 'Đang chuẩn bị',
            'packed' => 'Đã đóng gói',
            'shipped' => 'Đang giao',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
        ][$status] ?? $status;
    }
}

if (!function_exists('adminShellPaymentStatusLabel')) {
    function adminShellPaymentStatusLabel(string $status): string
    {
        return [
            'paid' => 'Đã thanh toán',
            'partial' => 'Đã cọc',
            'awaiting_confirmation' => 'Chờ xác nhận CK',
            'pending' => 'Chưa thanh toán',
            'refunded' => 'Đã hoàn tiền',
            'cancelled' => 'Không thu tiền',
        ][$status] ?? $status;
    }
}

if (!function_exists('adminShellStockStatusLabel')) {
    function adminShellStockStatusLabel(string $status): string
    {
        return [
            'unchecked' => 'Chưa kiểm kho',
            'sufficient' => 'Đủ hàng',
            'insufficient' => 'Thiếu hàng',
            'exported' => 'Đã xuất kho',
            'cancelled' => 'Hủy kho',
        ][$status] ?? $status;
    }
}

if (!function_exists('adminShellOpenOrders')) {
    function adminShellOpenOrders(): array
    {
        if (!class_exists('Database')) {
            $databasePath = __DIR__ . '/../../config/database.php';
            if (file_exists($databasePath)) {
                require_once $databasePath;
            }
        }

        if (!class_exists('Database')) {
            return ['total' => 0, 'orders' => []];
        }

        try {
            $conn = (new Database())->getConnection();
            $total = (int)$conn->query("
                SELECT COUNT(*)
                FROM orders
                WHERE COALESCE(status, 'pending') NOT IN ('completed', 'cancelled')
            ")->fetchColumn();

            $stmt = $conn->query("
                SELECT id, fullname, total, status, payment_status, stock_status,
                       delivery_scope, recipient_country, export_country, created_at
                FROM orders
                WHERE COALESCE(status, 'pending') NOT IN ('completed', 'cancelled')
                ORDER BY
                    CASE COALESCE(status, 'pending')
                        WHEN 'pending' THEN 0
                        WHEN 'confirmed' THEN 1
                        WHEN 'preparing' THEN 2
                        WHEN 'packed' THEN 3
                        WHEN 'shipped' THEN 4
                        ELSE 5
                    END,
                    created_at DESC,
                    id DESC
                LIMIT 8
            ");

            return [
                'total' => $total,
                'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
        } catch (Throwable $e) {
            error_log('Cannot load admin order notifications: ' . $e->getMessage());
            return ['total' => 0, 'orders' => []];
        }
    }
}
if (!function_exists('adminShellLowStockProducts')) {
    function adminShellLowStockProducts(): array
    {
        if (!class_exists('Database')) {
            $databasePath = __DIR__ . '/../../config/database.php';
            if (file_exists($databasePath)) {
                require_once $databasePath;
            }
        }

        try {
            $conn = (new Database())->getConnection();

            $stmt = $conn->query("
                SELECT id, name, stock
                FROM product
                WHERE stock <= 10
                ORDER BY stock ASC, name ASC
                LIMIT 5
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Cannot load low stock notifications: ' . $e->getMessage());
            return [];
        }
    }
}
if (!function_exists('adminShellPendingBatches')) {
    function adminShellPendingBatches(): array
    {
        if (!class_exists('Database')) {
            $databasePath = __DIR__ . '/../../config/database.php';
            if (file_exists($databasePath)) {
                require_once $databasePath;
            }
        }

        try {
            $conn = (new Database())->getConnection();

            $stmt = $conn->query("
                SELECT
                    pb.id,
                    pb.batch_code,
                    COALESCE(p.name, so.requested_product_name, CONCAT('Sản phẩm #', pb.product_id)) AS product_name,
                    pb.quantity,
                    pb.remaining_quantity,
                    pb.status,
                    pb.created_at,
                    s.name AS supplier_name,
                    w.name AS warehouse_name
                FROM product_batch pb
                LEFT JOIN product p ON p.id = pb.product_id
                LEFT JOIN supplier_orders so ON so.id = pb.supplier_order_id
                LEFT JOIN supplier s ON s.id = pb.supplier_id
                LEFT JOIN warehouse w ON w.id = pb.warehouse_id
                WHERE pb.status IN ('supplier_submitted', 'pending', 'cho_kho', 'waiting_warehouse')
                ORDER BY pb.created_at DESC, pb.id DESC
                LIMIT 5
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Cannot load pending batch notifications: ' . $e->getMessage());
            return [];
        }
    }
}
$adminOpenOrderData = $isAdminShellPage ? adminShellOpenOrders() : ['total' => 0, 'orders' => []];
$adminOpenOrderTotal = (int)($adminOpenOrderData['total'] ?? 0);
$adminOpenOrders = $adminOpenOrderData['orders'] ?? [];

$adminLowStockProducts = $isAdminShellPage ? adminShellLowStockProducts() : [];
$adminPendingBatches = $isAdminShellPage ? adminShellPendingBatches() : [];
$adminPendingSupplierData = $isAdminShellPage ? adminShellPendingSuppliers() : ['total' => 0, 'suppliers' => []];
$adminPendingSupplierTotal = (int)($adminPendingSupplierData['total'] ?? 0);
$adminPendingSuppliers = $adminPendingSupplierData['suppliers'] ?? [];
$adminSupplierOrderAlertData = $isAdminShellPage ? adminShellSupplierOrderAlerts() : ['total' => 0, 'orders' => []];
$adminSupplierOrderAlertTotal = (int)($adminSupplierOrderAlertData['total'] ?? 0);
$adminSupplierOrderAlerts = $adminSupplierOrderAlertData['orders'] ?? [];

$adminNotificationTotal =
    $adminOpenOrderTotal
    + $adminPendingSupplierTotal
    + $adminSupplierOrderAlertTotal
    + count($adminLowStockProducts)
    + count($adminPendingBatches);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta name="csrf-token" content="<?= htmlspecialchars(CsrfHelper::token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="csrf-field" content="<?= htmlspecialchars(CsrfHelper::FIELD_NAME, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aethera Supply - Nông sản sạch</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
    :root {
        --aethera-green: #00a86b;
        --aethera-green-dark: #007956;
        --aethera-soft: #e9fbf2;
        --aethera-bg: #f7f9fb;
        --aethera-border: #e5e7eb;
        --aethera-text: #111827;
        --aethera-muted: #64748b;
        --admin-sidebar-width: 260px;
    }

    * {
        letter-spacing: 0;
    }

    body {
        margin: 0;
        background: var(--aethera-bg);
        color: var(--aethera-text);
        font-family: 'Be Vietnam Pro', Arial, sans-serif;
    }

    .navbar {
        background: rgba(255, 255, 255, .98) !important;
        border-bottom: 1px solid var(--aethera-border);
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
        padding: 10px 0;
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--aethera-green-dark) !important;
        font-weight: 900;
        text-decoration: none;
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: #fff;
        background: linear-gradient(135deg, var(--aethera-green-dark), var(--aethera-green));
    }

    .brand-text span {
        display: block;
        font-size: 1.12rem;
        line-height: 1.1;
    }

    .brand-text small {
        display: block;
        margin-top: 3px;
        color: var(--aethera-muted);
        font-size: .72rem;
        font-weight: 700;
    }

    .menu-pill {
        min-height: 40px;
        border: 1px solid #b7ebd3;
        border-radius: 12px;
        background: #fff;
        color: var(--aethera-green-dark);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .menu-pill:hover,
    .menu-pill.active {
        background: var(--aethera-soft);
        color: var(--aethera-green-dark);
    }

    .search-container {
        width: min(520px, 100%);
    }

    .search-input {
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        color: var(--aethera-text);
        padding: 9px 46px 9px 14px;
        box-shadow: none;
    }

    .search-input:focus {
        border-color: var(--aethera-green);
        box-shadow: 0 0 0 4px rgba(0, 168, 107, .14);
    }

    .search-btn {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 10px;
        color: #fff;
        background: var(--aethera-green);
    }

    .cart-link {
        width: 42px;
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: var(--aethera-text) !important;
        position: relative;
        text-decoration: none;
        background: #fff;
    }

    .cart-badge {
        position: absolute;
        top: -7px;
        right: -7px;
        min-width: 20px;
        height: 20px;
        border-radius: 999px;
        padding: 0 5px;
        display: grid;
        place-items: center;
        color: #fff;
        background: #ef4444;
        border: 2px solid #fff;
        font-size: .7rem;
        font-weight: 900;
    }

    body.admin-shell-page {
        background: #fff !important;
        color: var(--aethera-text);
    }

    .admin-shell {
        min-height: 100vh;
        background: #fff;
    }

    .admin-shared-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: var(--admin-sidebar-width);
        height: 100vh;
        padding: 10px 10px 14px;
        background: #fff;
        border-right: 1px solid var(--aethera-border);
        display: flex;
        flex-direction: column;
        gap: 12px;
        overflow-y: auto;
        z-index: 1040;
    }

    .admin-shared-profile {
        min-height: 58px;
        border: 1px solid var(--aethera-border);
        border-radius: 12px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fbfdff;
    }

    .admin-shared-crown {
        width: 28px;
        height: 28px;
        border-radius: 9px;
        display: grid;
        place-items: center;
        color: #fb3449;
        background: #fff7ed;
        flex: 0 0 auto;
    }

    .admin-shared-profile strong,
    .admin-shared-profile span {
        display: block;
        line-height: 1.15;
    }

    .admin-shared-profile strong {
        color: #fb3449;
        font-size: 12px;
        font-weight: 900;
    }

    .admin-shared-profile span {
        margin-top: 3px;
        max-width: 145px;
        color: #94a3b8;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .admin-shared-menu {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .admin-shared-link {
        min-height: 42px;
        padding: 0 12px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #0f172a;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        transition: .18s ease;
    }

    .admin-shared-link i {
        width: 16px;
        color: #334155;
        text-align: center;
        font-size: 13px;
    }

    .admin-shared-link:hover,
    .admin-shared-link.active {
        background: var(--aethera-soft);
        color: var(--aethera-green-dark);
    }

    .admin-shared-link:hover i,
    .admin-shared-link.active i {
        color: var(--aethera-green-dark);
    }

    .admin-shared-sublink {
        min-height: 34px;
        margin-left: 24px;
        padding-left: 10px;
        border-left: 2px solid var(--aethera-border);
        border-radius: 10px;
        color: #475569;
        font-size: 12px;
        font-weight: 850;
    }

    .admin-shared-sublink i {
        width: 14px;
        font-size: 11px;
    }

    .admin-shared-sidebar-footer {
        margin-top: auto;
        border-top: 1px solid #eef2f7;
        padding-top: 14px;
    }

    .admin-shared-logout {
        min-height: 48px;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 16px;
        color: #0f172a;
        text-decoration: none;
        font-size: 16px;
        font-weight: 700;
    }

    .admin-shared-logout {
        color: #ff2d3d;
    }

    .admin-shared-main {
        min-height: 100vh;
        margin-left: var(--admin-sidebar-width);
        padding: 18px 24px 34px;
        background: #fff;
    }

    .admin-shared-main > .container,
    .admin-shared-main > .container-fluid {
        max-width: 1120px;
        margin-left: auto;
        margin-right: auto;
    }

    @media (max-width: 991px) {
        .search-container {
            margin: 12px 0 !important;
        }

        .admin-shared-sidebar {
            position: sticky;
            top: 0;
            width: 100%;
            height: auto;
            border-right: 0;
            border-bottom: 1px solid var(--aethera-border);
        }

        .admin-shared-menu {
            flex-direction: row;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .admin-shared-link {
            flex: 0 0 auto;
        }

        .admin-shared-main {
            margin-left: 0;
            padding: 16px 12px 30px;
        }
    }
    </style>
    <link rel="stylesheet" href="/webbanhang/public/css/agri-theme.css?v=20260604">
</head>
<body class="<?= trim(($isAdminPage ? 'admin-theme ' : '') . ($isAdminShellPage ? 'admin-shell-page' : '')) ?>">

<?php if ($isAdminShellPage): ?>
<div class="admin-shell">
    <?php include __DIR__ . '/admin_sidebar.php'; ?>
    <div class="admin-sidebar-backdrop" data-admin-sidebar-close></div>
    <main class="admin-content admin-shared-main">
        <div class="admin-command-topbar">
            <button class="admin-mobile-nav-toggle" type="button" data-admin-sidebar-toggle aria-label="Mở menu nhân viên">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="admin-system-status" aria-label="Trạng thái hệ thống">
                <span class="admin-live-dot"></span>
                <strong>Hệ thống hoạt động bình thường</strong>
            </div>

            <div class="admin-topbar-fill"></div>

            <button class="admin-icon-btn" type="button" data-admin-notifications aria-label="Mở cảnh báo">
                <i class="fa-regular fa-bell"></i>
                <span class="admin-alert-count<?= $adminNotificationTotal <= 0 ? ' is-empty' : '' ?>">
                    <?= $adminNotificationTotal ?>
                </span>
            </button>

            <button class="admin-icon-btn" type="button" data-admin-theme-toggle aria-label="Đổi giao diện sáng tối">
                <i class="fa-regular fa-moon"></i>
            </button>

            <div class="admin-topbar-user">
                <div class="admin-user-avatar"><?= htmlspecialchars($adminShellInitials) ?></div>
                <div>
                    <strong><?= htmlspecialchars($adminShellProfileName) ?></strong>
                    <span><?= htmlspecialchars($adminShellRoleLabel) ?></span>
                </div>
            </div>
        </div>

<?php include __DIR__ . '/notifications.php'; ?>
<?php elseif (!$isAdminPage): ?>
<div class="store-topbar">
    <div class="store-topbar-inner">
        <span><i class="fa-solid fa-phone"></i> 0794 322 539 (Miễn phí)</span>
        <span><i class="fa-regular fa-clock"></i> 24/7 (Thứ 2 - CN)</span>
        <span><i class="fa-solid fa-truck-fast"></i> Giao hàng nhanh toàn quốc</span>
    </div>
</div>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand" href="/webbanhang/product">
            <div class="logo-icon"><i class="fa-solid fa-seedling"></i></div>
            <div class="brand-text">
                <span>AETHERA SUPPLY</span>
                <small>Nông sản sạch mỗi ngày</small>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-2">
                <li class="nav-item">
                    <a class="menu-pill <?= strpos($current_url, '/product') !== false ? 'active' : '' ?>" href="/webbanhang/product">
                        <i class="fa-solid fa-store"></i> Cửa hàng
                    </a>
                </li>
            </ul>

            <div class="search-container position-relative mx-auto">
                <form action="/webbanhang/product/search" method="GET">
                    <input type="text" class="search-input w-100" name="keyword" value="<?= htmlspecialchars((string)($_GET['keyword'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Tìm kiếm nông sản..." required>
                    <button type="submit" class="search-btn" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
                </form>
            </div>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-3">
                <li class="nav-item">
                    <a class="header-icon-link" href="/webbanhang/product" aria-label="Trang chủ">
                        <i class="fa-solid fa-house"></i>
                        <span>Trang chủ</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="cart-link header-icon-link" href="/webbanhang/cart" aria-label="Giỏ hàng">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>Giỏ hàng</span>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 fw-bold" href="#" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-circle-user"></i>
                            <?= htmlspecialchars($_SESSION['fullname'] ?? 'Người dùng') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/webbanhang/account/profile">Hồ sơ</a></li>
                            <?php if ($currentRole === Rbac::ROLE_SUPPLIER): ?>
                                <li><a class="dropdown-item" href="/webbanhang/account/profile?tab=supplier">Đơn nhập lô</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="/webbanhang/account/profile?tab=orders">Đơn hàng</a></li>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="/webbanhang<?= htmlspecialchars(Rbac::defaultUrl($currentRole)) ?>">Quản trị</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/webbanhang/account/logout">Đăng xuất</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="header-icon-link" href="/webbanhang/account/login">
                            <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
