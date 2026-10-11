<?php
require_once __DIR__ . '/../../helpers/Rbac.php';

$adminSidebarCurrentUrl = $_SERVER['REQUEST_URI'] ?? '';
$adminSidebarProfileName = $_SESSION['fullname'] ?? 'Nhân viên';
$adminSidebarRole = Rbac::normalizeRole($_SESSION['user_role'] ?? 'user');
$adminSidebarRoleLabel = Rbac::label($adminSidebarRole);
$adminSidebarRoleIcon = match ($adminSidebarRole) {
    Rbac::ROLE_ADMIN => 'fa-crown',
    Rbac::ROLE_MANAGER => 'fa-chart-line',
    Rbac::ROLE_WAREHOUSE => 'fa-warehouse',
    Rbac::ROLE_EXPORT => 'fa-truck-fast',
    Rbac::ROLE_STAFF => 'fa-user-gear',
    default => 'fa-user-shield',
};

if (!function_exists('adminSidebarActive')) {
    function adminSidebarActive($currentUrl, array $needles) {
        $path = parse_url($currentUrl, PHP_URL_PATH) ?: $currentUrl;
        $path = preg_replace('~^/webbanhang~', '', $path);
        $path = '/' . trim($path, '/');

        foreach ($needles as $needle) {
            $needle = '/' . trim($needle, '/');
            $isWildcard = substr($needle, -1) === '*';
            $match = $isWildcard ? rtrim($needle, '*') : $needle;
            if (($isWildcard && strpos($path, $match) === 0) || (!$isWildcard && $path === $match)) {
                return ' active';
            }
        }
        return '';
    }
}

if (!function_exists('adminSidebarItemKey')) {
    function adminSidebarItemKey(array $item): string {
        $href = $item['href'] ?? '';

        if (strpos($href, '/admin/statistics') !== false) return 'statistics';
        if (strpos($href, '/admin/workflow') !== false) return 'workflow';
        if (strpos($href, '/admin/cancelled_orders') !== false) return 'cancelled_orders';
        if (strpos($href, '/admin/order_management') !== false) return 'order_management';
        if (strpos($href, '/admin/export_orders') !== false) return 'export_orders';
        if (strpos($href, '/admin/inventory') !== false) return 'inventory';
        if (strpos($href, '/admin/batches') !== false) return 'batches';
        if (strpos($href, '/admin/suppliers') !== false) return 'suppliers';
        if (strpos($href, '/admin/categories') !== false) return 'categories';
        if (strpos($href, '/admin/account') !== false) return 'account';
        if (strpos($href, '#flash-sale-admin') !== false) return 'flash_sale';
        if (strpos($href, '#featured-products-admin') !== false) return 'featured_products';
        if (strpos($href, '/admin/promotions') !== false) return 'promotions';
        if (strpos($href, '/product') !== false) return 'store';

        return $href;
    }
}

if (!function_exists('adminSidebarWorkflow')) {
    function adminSidebarWorkflow(string $role): array {
        return match ($role) {
            Rbac::ROLE_STAFF => [
                'title' => 'Quy trình nhân viên',
                'steps' => ['Nhận đơn', 'Xác nhận thanh toán', 'Chuyển kho', 'Theo dõi giao'],
            ],
            Rbac::ROLE_WAREHOUSE => [
                'title' => 'Quy trình kho',
                'steps' => ['Kiểm tồn', 'Chọn lô', 'Xuất kho', 'Cập nhật tồn'],
            ],
            Rbac::ROLE_EXPORT => [
                'title' => 'Quy trình xuất khẩu',
                'steps' => ['Kiểm đơn', 'Chứng từ', 'Vận chuyển', 'Hoàn tất'],
            ],
            Rbac::ROLE_MANAGER => [
                'title' => 'Quy trình quản lý',
                'steps' => ['Theo dõi số liệu', 'Duyệt đơn', 'Kiểm kho', 'Khuyến mãi'],
            ],
            default => [
                'title' => 'Quy trình quản trị',
                'steps' => ['Bán hàng', 'Kho & lô', 'Nguồn hàng', 'Cửa hàng'],
            ],
        };
    }
}

$adminSidebarMenu = [
    ['label' => 'Bắt đầu công việc', 'href' => '/webbanhang/admin/workflow', 'icon' => 'fa-list-check', 'match' => ['/admin/workflow'], 'permission' => 'workflow'],
    ['label' => 'Bảng điều khiển', 'href' => '/webbanhang/admin/statistics', 'icon' => 'fa-table-cells-large', 'match' => ['/admin', '/admin/dashboard', '/admin/statistics'], 'permission' => 'statistics'],
    ['label' => 'Đơn nội địa', 'href' => '/webbanhang/admin/order_management', 'icon' => 'fa-file-invoice', 'match' => ['/admin/order_management', '/admin/updateOrderStatus'], 'permission' => 'order_management'],
    ['label' => 'Đơn đã hủy', 'href' => '/webbanhang/admin/cancelled_orders', 'icon' => 'fa-ban', 'match' => ['/admin/cancelled_orders'], 'permission' => 'order_management'],
    ['label' => 'Đơn xuất khẩu', 'href' => '/webbanhang/admin/export_orders', 'icon' => 'fa-truck-fast', 'match' => ['/admin/export_orders', '/admin/saveShipment', '/admin/advanceShipmentStep'], 'permission' => 'export_orders'],
    ['label' => 'Tồn kho', 'href' => '/webbanhang/admin/inventory', 'icon' => 'fa-warehouse', 'match' => ['/admin/inventory', '/admin/saveWarehouse', '/admin/adjustInventory', '/admin/add', '/admin/edit/*'], 'permission' => 'inventory'],
    ['label' => 'Nhập lô / QR', 'href' => '/webbanhang/admin/batches', 'icon' => 'fa-seedling', 'match' => ['/admin/batches', '/admin/saveBatch'], 'permission' => 'batches'],
    ['label' => 'Nhà cung cấp', 'href' => '/webbanhang/admin/suppliers', 'icon' => 'fa-handshake-angle', 'match' => ['/admin/suppliers', '/admin/saveSupplier', '/admin/deleteSupplier/*', '/admin/approveSupplier/*'], 'permission' => 'suppliers'],
    ['label' => 'Danh mục', 'href' => '/webbanhang/admin/categories', 'icon' => 'fa-layer-group', 'match' => ['/admin/categories', '/admin/add_category', '/admin/edit_category/*'], 'permission' => 'categories'],
    ['label' => 'Xem cửa hàng', 'href' => '/webbanhang/product', 'icon' => 'fa-store', 'match' => ['/product']],
    ['label' => 'Khuyến mãi', 'href' => '/webbanhang/admin/promotions', 'icon' => 'fa-tags', 'match' => ['/admin/promotions', '/admin/savePromotion', '/admin/togglePromotion/*', '/admin/deletePromotion/*', '/admin/saveFlashSale', '/admin/saveFeaturedProducts'], 'permission' => 'promotions'],
    ['label' => 'Flash Sale', 'href' => '/webbanhang/admin/promotions#flash-sale-admin', 'icon' => 'fa-fire', 'match' => [], 'child' => true, 'permission' => 'promotions'],
    ['label' => 'Sản phẩm nổi bật', 'href' => '/webbanhang/admin/promotions#featured-products-admin', 'icon' => 'fa-bookmark', 'match' => [], 'child' => true, 'permission' => 'promotions'],
    ['label' => 'Người dùng', 'href' => '/webbanhang/admin/account', 'icon' => 'fa-user-gear', 'match' => ['/admin/account', '/admin/editUser/*'], 'permission' => 'account'],
];

$adminSidebarOrderByRole = [
    Rbac::ROLE_ADMIN => ['statistics', 'workflow', 'order_management', 'cancelled_orders', 'export_orders', 'inventory', 'batches', 'suppliers', 'categories', 'store', 'promotions', 'flash_sale', 'featured_products', 'account'],
    Rbac::ROLE_MANAGER => ['statistics', 'workflow', 'order_management', 'cancelled_orders', 'export_orders', 'inventory', 'batches', 'suppliers', 'categories', 'promotions', 'flash_sale', 'featured_products', 'store'],
    Rbac::ROLE_STAFF => ['workflow', 'order_management', 'cancelled_orders', 'statistics', 'store'],
    Rbac::ROLE_WAREHOUSE => ['workflow', 'inventory', 'batches', 'statistics', 'store'],
    Rbac::ROLE_EXPORT => ['workflow', 'export_orders', 'inventory', 'statistics', 'store'],
];

foreach ($adminSidebarMenu as $index => &$adminSidebarItem) {
    $adminSidebarItem['_sort_index'] = $index;
}
unset($adminSidebarItem);

$adminSidebarMenu = array_values(array_filter($adminSidebarMenu, function ($item) use ($adminSidebarRole) {
    return empty($item['permission']) || Rbac::can($adminSidebarRole, $item['permission']);
}));

$adminSidebarOrder = $adminSidebarOrderByRole[$adminSidebarRole] ?? $adminSidebarOrderByRole[Rbac::ROLE_ADMIN];
$adminSidebarWorkflow = adminSidebarWorkflow($adminSidebarRole);
usort($adminSidebarMenu, function ($left, $right) use ($adminSidebarOrder) {
    $leftPosition = array_search(adminSidebarItemKey($left), $adminSidebarOrder, true);
    $rightPosition = array_search(adminSidebarItemKey($right), $adminSidebarOrder, true);
    $leftPosition = $leftPosition === false ? PHP_INT_MAX : $leftPosition;
    $rightPosition = $rightPosition === false ? PHP_INT_MAX : $rightPosition;

    return ($leftPosition <=> $rightPosition) ?: (($left['_sort_index'] ?? 0) <=> ($right['_sort_index'] ?? 0));
});
?>

<aside class="admin-shared-sidebar" aria-label="Điều hướng nhân viên">
    <div class="admin-shared-brand">
        <a href="/webbanhang/admin/workflow" class="admin-brand-mark" aria-label="AETHERA SUPPLY Bảng quản trị">
            <span><i class="fa-solid fa-seedling"></i></span>
            <strong>AETHERA</strong>
        </a>
        <small>Quản lý nông sản</small>
    </div>


    <nav class="admin-shared-menu" aria-label="Admin navigation">
        <?php $adminCurrentGroup = null; ?>
        <?php foreach ($adminSidebarMenu as $item): ?>
            <?php if (($item['group'] ?? '') !== $adminCurrentGroup): ?>
                <?php $adminCurrentGroup = $item['group'] ?? ''; ?>
                <div class="admin-shared-group"><?= htmlspecialchars($adminCurrentGroup) ?></div>
            <?php endif; ?>
            <a class="admin-shared-link<?= !empty($item['child']) ? ' admin-shared-sublink' : '' ?><?= adminSidebarActive($adminSidebarCurrentUrl, $item['match']) ?>" href="<?= $item['href'] ?>">
                <i class="fa-solid <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="admin-shared-sidebar-footer">
        <a class="admin-shared-logout" href="/webbanhang/account/logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Đăng xuất</span>
        </a>
    </div>
</aside>

<style>
body.admin-theme .admin-shared-sidebar {
    background: #fbfffd !important;
}

body.admin-theme .admin-shared-profile {
    border-color: #b7dfc6 !important;
    background: linear-gradient(135deg, #effdf5, #f8fffb) !important;
}

body.admin-theme .admin-shared-profile strong,
body.admin-theme .admin-shared-link,
body.admin-theme .admin-shared-logout {
    color: #0b3d2e !important;
}

body.admin-theme .admin-shared-profile span {
    color: #5c746b !important;
}

body.admin-theme .admin-shared-role {
    margin-top: 4px !important;
    color: #008765 !important;
    font-size: 10px !important;
    font-weight: 650 !important;
    text-transform: uppercase;
}

body.admin-theme .admin-shared-group {
    margin: 10px 8px 4px;
    color: #6b8179;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

body.admin-theme .admin-role-flow {
    display: block;
    border: 1px solid #b7dfc6;
    border-radius: 14px;
    background: #f0fdf4;
    padding: 12px;
    text-decoration: none;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

body.admin-theme .admin-role-flow:hover {
    border-color: #34d399;
    box-shadow: 0 10px 24px rgba(16, 185, 129, .14);
    transform: translateY(-1px);
}

body.admin-theme .admin-role-flow strong {
    display: block;
    color: #064e3b;
    font-size: 12px;
    font-weight: 850;
    margin-bottom: 9px;
}

body.admin-theme .admin-role-flow div {
    display: grid;
    gap: 6px;
}

body.admin-theme .admin-role-flow span {
    min-height: 28px;
    border-radius: 10px;
    background: #ffffff;
    color: #047857;
    display: flex;
    align-items: center;
    padding: 0 9px;
    font-size: 11px;
    font-weight: 750;
}

body.admin-theme .admin-shared-link {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
}

body.admin-theme .admin-shared-link i,
body.admin-theme .admin-shared-logout i {
    width: 22px !important;
    height: 22px !important;
    flex: 0 0 22px !important;
    display: inline-grid !important;
    place-items: center !important;
    color: #427466 !important;
    text-align: center !important;
    line-height: 1 !important;
}

body.admin-theme .admin-shared-link:hover,
body.admin-theme .admin-shared-link.active {
    background: #e7f8ef !important;
    color: #006b4f !important;
}

body.admin-theme .admin-shared-link:hover i,
body.admin-theme .admin-shared-link.active i {
    color: #008765 !important;
}

body.admin-theme .admin-shared-sublink {
    color: #315a4d !important;
    border-left-color: #d7eee2 !important;
}
</style>
