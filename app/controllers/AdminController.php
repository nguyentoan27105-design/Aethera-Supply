<?php
if (!defined('APPROOT')) {
    define('APPROOT', realpath(dirname(__DIR__)));
}

// ===== LOAD MODEL =====
require_once APPROOT . '/models/ProductModel.php';
require_once APPROOT . '/models/CategoryModel.php'; 
require_once APPROOT . '/models/UserModel.php';
require_once APPROOT . '/models/AdminModel.php'; 
require_once APPROOT . '/models/OrderModel.php'; 
require_once APPROOT . '/models/SupplyChainModel.php';
require_once APPROOT . '/models/PromotionModel.php';
require_once APPROOT . '/models/ChatbotModel.php';
require_once APPROOT . '/config/Database.php';
require_once APPROOT . '/helpers/Rbac.php';
require_once APPROOT . '/helpers/CsrfHelper.php';
require_once APPROOT . '/helpers/UploadHelper.php';

class AdminController {
    private $productModel;
    private $categoryModel;
    private $userModel;
    private $adminModel;
    private $orderModel; 
    private $supplyChainModel;
    private $promotionModel;
    private $chatbotModel;
    private $db;
    private $baseUrl = "/webbanhang";

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // ===== KIỂM TRA QUYỀN QUẢN TRỊ / NHÂN VIÊN =====
        $role = Rbac::normalizeRole($_SESSION['user_role'] ?? null);
        $_SESSION['user_role'] = $role;
        if (!isset($_SESSION['user_id']) || !Rbac::hasAdminAreaAccess($role)) {
            header("Location: " . $this->baseUrl . "/account/login");
            exit();
        }

        $this->guardAdminRoute($role);

        $database = new Database();
        $this->db = $database->getConnection();

        $this->productModel = new ProductModel($this->db);
        $this->categoryModel = new CategoryModel($this->db);
        $this->userModel = new UserModel($this->db);
        $this->adminModel = new AdminModel($this->db);
        $this->orderModel = new OrderModel($this->db); 
        $this->supplyChainModel = new SupplyChainModel($this->db);
        $this->promotionModel = new PromotionModel($this->db);
        $this->chatbotModel = new ChatbotModel($this->db);
        $this->supplyChainModel->ensureSchema();
    }

    private function guardAdminRoute(string $role): void {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $path = trim(preg_replace('~^/webbanhang/admin~', '', $uri), '/');
        $action = explode('/', $path)[0] ?: 'statistics';
        $permission = $this->permissionForAction($action);

        if (!Rbac::can($role, $permission)) {
            $_SESSION['msg_type'] = 'warning';
            $_SESSION['msg'] = 'Tài khoản ' . Rbac::label($role) . ' không có quyền truy cập chức năng này.';
            header("Location: " . $this->baseUrl . Rbac::defaultUrl($role));
            exit();
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !CsrfHelper::verifyPost()) {
            $_SESSION['msg_type'] = 'warning';
            $_SESSION['msg'] = 'Yeu cau khong hop le hoac da het phien bao mat.';
            header("Location: " . $this->baseUrl . Rbac::defaultUrl($role));
            exit();
        }
    }

    private function permissionForAction(string $action): string {
        return match ($action) {
            '', 'index', 'dashboard', 'statistics', 'exportStatistics' => 'statistics',
            'workflow', 'chatbot', 'saveChatbotEntry', 'deleteChatbotEntry' => 'workflow',
            'suppliers', 'saveSupplier', 'deleteSupplier', 'approveSupplier' => 'suppliers',
            'categories', 'category_products', 'moveProductToCategory', 'add_category', 'edit_category', 'save_category', 'delete_category' => 'categories',
            'batches', 'saveBatch', 'deleteBatch', 'createSupplierOrder', 'confirmSupplierOrder', 'paySupplierOrder' => 'batches',
            'inventory', 'saveWarehouse', 'adjustInventory', 'confirmOrderForInventory', 'exportOrderInventory' => 'inventory',
            'order_management', 'cancelled_orders', 'updateOrderStatus', 'saveDomesticShipmentSchedule' => 'order_management',
            'export_orders', 'saveShipment', 'advanceShipmentStep' => 'export_orders',
            'promotions', 'savePromotion', 'togglePromotion', 'deletePromotion', 'saveFlashSale', 'saveFeaturedProducts' => 'promotions',
            'account', 'editUser', 'deleteUser' => 'account',
            'add', 'add_product', 'edit', 'update', 'delete' => 'inventory',
            default => 'statistics',
        };
    }

    // ===== RENDER VIEW =====
    private function renderView($viewName, $data = []) {
        $viewPath = APPROOT . '/views/' . $viewName . '.php';
        $header = APPROOT . '/views/shares/header.php';
        $footer = APPROOT . '/views/shares/footer.php';

        if (file_exists($header)) include $header;

        extract($data);

        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "<div style='padding:20px;color:red'>⚠️ Không tìm thấy view: $viewName</div>";
        }

        if (file_exists($footer)) include $footer;
    }

    private function requirePostAndCsrf(string $redirectPath): void {
        if (!CsrfHelper::verifyPost()) {
            $_SESSION['admin_flash'] = 'Yeu cau khong hop le hoac da het phien bao mat.';
            header("Location: " . $this->baseUrl . $redirectPath);
            exit();
        }
    }

    public function index() { 
        $this->statistics(); 
    }

    // ================= DASHBOARD =================
    public function dashboard() {
        $this->statistics();
    }

    public function workflow() {
        $role = Rbac::normalizeRole($_SESSION['user_role'] ?? 'user');
        $guides = $this->workflowGuides();
        $guide = $guides[$role] ?? $guides[Rbac::ROLE_ADMIN];

        $this->renderView('admin/workflow', [
            'role' => $role,
            'guide' => $guide,
        ]);
    }

    private function workflowGuides(): array {
        return [
            Rbac::ROLE_ADMIN => [
                'title' => 'Quy trình quản trị',
                'intro' => 'Dành cho người điều phối toàn bộ cửa hàng: theo dõi số liệu, xử lý đơn, kiểm kho, nguồn hàng và người dùng.',
                'primaryUrl' => $this->baseUrl . '/admin/statistics',
                'primaryLabel' => 'Mở bảng điều khiển',
                'situations' => [
                    ['label' => 'Tôi mới vào ca', 'text' => 'Xem tổng quan trước để biết đơn nào cần xử lý, hàng nào sắp hết.', 'url' => $this->baseUrl . '/admin/statistics', 'icon' => 'fa-gauge-high'],
                    ['label' => 'Có đơn cần xử lý', 'text' => 'Kiểm đơn trước, kho xuất hàng xong mới bàn giao vận chuyển.', 'url' => $this->baseUrl . '/admin/order_management', 'icon' => 'fa-file-invoice'],
                    ['label' => 'Có vấn đề tồn kho', 'text' => 'Kiểm tồn kho và lô hàng trước khi chỉnh số lượng.', 'url' => $this->baseUrl . '/admin/inventory', 'icon' => 'fa-warehouse'],
                ],
                'steps' => [
                    ['title' => 'Kiểm tra tổng quan', 'text' => 'Mở bảng điều khiển để xem doanh thu, đơn mới, tồn kho thấp và đơn sắp đến hạn.', 'url' => $this->baseUrl . '/admin/statistics', 'label' => 'Xem tổng quan'],
                    ['title' => 'Xử lý đơn hàng', 'text' => 'Vào đơn nội địa hoặc xuất khẩu, kiểm tra thanh toán, trạng thái đóng gói và lịch giao.', 'url' => $this->baseUrl . '/admin/order_management', 'label' => 'Xử lý đơn'],
                    ['title' => 'Kiểm kho và lô hàng', 'text' => 'Kiểm tồn kho, nhập lô, cập nhật QR truy xuất và điều chỉnh số lượng khi có lệch kho.', 'url' => $this->baseUrl . '/admin/inventory', 'label' => 'Kiểm kho'],
                    ['title' => 'Quản lý nguồn hàng', 'text' => 'Cập nhật nhà cung cấp, danh mục và chương trình khuyến mãi khi cần.', 'url' => $this->baseUrl . '/admin/suppliers', 'label' => 'Nguồn hàng'],
                ],
                'tips' => ['Ưu tiên xử lý đơn quá hạn trước.', 'Không sửa tồn kho nếu chưa kiểm tra lô liên quan.', 'Sau khi đổi quyền người dùng, yêu cầu họ đăng nhập lại.'],
            ],
            Rbac::ROLE_MANAGER => [
                'title' => 'Quy trình quản lý',
                'intro' => 'Dành cho quản lý vận hành: xem số liệu, duyệt xử lý đơn, theo dõi tồn kho và chương trình bán hàng.',
                'primaryUrl' => $this->baseUrl . '/admin/statistics',
                'primaryLabel' => 'Mở số liệu',
                'situations' => [
                    ['label' => 'Tôi cần xem hôm nay ổn không', 'text' => 'Vào số liệu để xem doanh thu, đơn đang chậm và hàng sắp hết.', 'url' => $this->baseUrl . '/admin/statistics', 'icon' => 'fa-chart-line'],
                    ['label' => 'Có đơn bị kẹt', 'text' => 'Vào đơn hàng để kiểm trạng thái và nhắc đúng bộ phận xử lý.', 'url' => $this->baseUrl . '/admin/order_management', 'icon' => 'fa-clipboard-check'],
                    ['label' => 'Muốn chạy khuyến mãi', 'text' => 'Kiểm tồn kho trước, rồi mới cập nhật flash sale hoặc sản phẩm nổi bật.', 'url' => $this->baseUrl . '/admin/promotions', 'icon' => 'fa-tags'],
                ],
                'steps' => [
                    ['title' => 'Xem số liệu trong ngày', 'text' => 'Kiểm doanh thu, số đơn, đơn cần xử lý và sản phẩm tồn thấp.', 'url' => $this->baseUrl . '/admin/statistics', 'label' => 'Xem số liệu'],
                    ['title' => 'Duyệt tiến độ đơn', 'text' => 'Kiểm đơn nội địa và xuất khẩu, nhắc nhân viên xử lý đơn chậm.', 'url' => $this->baseUrl . '/admin/order_management', 'label' => 'Duyệt đơn'],
                    ['title' => 'Kiểm tra kho', 'text' => 'Theo dõi tồn kho và lô hàng để tránh bán vượt số lượng thực tế.', 'url' => $this->baseUrl . '/admin/inventory', 'label' => 'Kiểm kho'],
                    ['title' => 'Cập nhật bán hàng', 'text' => 'Điều chỉnh khuyến mãi, flash sale và sản phẩm nổi bật theo kế hoạch.', 'url' => $this->baseUrl . '/admin/promotions', 'label' => 'Khuyến mãi'],
                ],
                'tips' => ['Kiểm đơn và tồn kho trước khi bật khuyến mãi lớn.', 'Ưu tiên đơn đã thanh toán.', 'Theo dõi sản phẩm bán nhanh mỗi cuối ngày.'],
            ],
            Rbac::ROLE_STAFF => [
                'title' => 'Quy trình nhân viên bán hàng',
                'intro' => 'Dành cho nhân viên xử lý đơn nội địa từ lúc nhận đơn đến khi bàn giao giao hàng.',
                'primaryUrl' => $this->baseUrl . '/admin/order_management',
                'primaryLabel' => 'Mở đơn nội địa',
                'situations' => [
                    ['label' => 'Có đơn mới', 'text' => 'Mở đơn nội địa, kiểm khách hàng, địa chỉ, sản phẩm và ghi chú.', 'url' => $this->baseUrl . '/admin/order_management', 'icon' => 'fa-file-circle-plus'],
                    ['label' => 'Khách hỏi trạng thái', 'text' => 'Tìm đơn, xem trạng thái thanh toán, đóng gói và giao hàng.', 'url' => $this->baseUrl . '/admin/order_management', 'icon' => 'fa-magnifying-glass'],
                    ['label' => 'Không chắc có đủ hàng', 'text' => 'Đừng chuyển đóng gói vội, kiểm tồn hoặc báo kho trước.', 'url' => $this->baseUrl . '/admin/statistics', 'icon' => 'fa-triangle-exclamation'],
                ],
                'steps' => [
                    ['title' => 'Nhận đơn mới', 'text' => 'Mở danh sách đơn nội địa, kiểm thông tin khách, sản phẩm, số lượng và ghi chú.', 'url' => $this->baseUrl . '/admin/order_management', 'label' => 'Xem đơn'],
                    ['title' => 'Xác nhận thanh toán', 'text' => 'Kiểm phương thức thanh toán và chỉ chuyển bước khi thông tin đã rõ.', 'url' => $this->baseUrl . '/admin/order_management', 'label' => 'Xác nhận'],
                    ['title' => 'Chuyển kho đóng gói', 'text' => 'Khi đơn hợp lệ, chuyển trạng thái để kho xuất hàng và đóng gói.', 'url' => $this->baseUrl . '/admin/order_management', 'label' => 'Chuyển kho'],
                    ['title' => 'Theo dõi giao hàng', 'text' => 'Sau khi kho bàn giao cho đơn vị giao, cập nhật ngày bàn giao, ngày nhận dự kiến và hoàn tất khi khách đã nhận.', 'url' => $this->baseUrl . '/admin/order_management', 'label' => 'Theo dõi'],
                ],
                'tips' => ['Không chuyển đơn sang đóng gói khi thông tin nhận hàng còn thiếu.', 'Ghi rõ ghi chú khách yêu cầu.', 'Đơn có sản phẩm tồn thấp cần báo kho trước.'],
            ],
            Rbac::ROLE_WAREHOUSE => [
                'title' => 'Quy trình nhân viên kho',
                'intro' => 'Dành cho kho: kiểm tồn, chọn lô, xuất kho và cập nhật số lượng thực tế.',
                'primaryUrl' => $this->baseUrl . '/admin/inventory',
                'primaryLabel' => 'Mở tồn kho',
                'situations' => [
                    ['label' => 'Cần xuất hàng', 'text' => 'Kiểm đơn đã xác nhận, chọn đúng lô rồi mới xuất kho.', 'url' => $this->baseUrl . '/admin/inventory', 'icon' => 'fa-box-open'],
                    ['label' => 'Có lô mới nhập', 'text' => 'Vào nhập lô, ghi đủ nhà cung cấp, ngày nhập, số lượng và QR.', 'url' => $this->baseUrl . '/admin/batches', 'icon' => 'fa-qrcode'],
                    ['label' => 'Số kho bị lệch', 'text' => 'Kiểm lại thực tế, chỉ điều chỉnh khi có lý do rõ ràng.', 'url' => $this->baseUrl . '/admin/inventory', 'icon' => 'fa-scale-balanced'],
                ],
                'steps' => [
                    ['title' => 'Kiểm tồn thực tế', 'text' => 'Mở tồn kho, kiểm sản phẩm sắp hết và đối chiếu số lượng theo lô.', 'url' => $this->baseUrl . '/admin/inventory', 'label' => 'Kiểm tồn'],
                    ['title' => 'Chọn lô phù hợp', 'text' => 'Ưu tiên lô gần hạn hoặc đúng nguồn gốc cần truy xuất.', 'url' => $this->baseUrl . '/admin/batches', 'label' => 'Chọn lô'],
                    ['title' => 'Xuất kho cho đơn', 'text' => 'Chỉ xuất kho khi đơn đã được xác nhận, sau đó ghi nhận số lượng đã xuất.', 'url' => $this->baseUrl . '/admin/inventory', 'label' => 'Xuất kho'],
                    ['title' => 'Cập nhật lệch kho', 'text' => 'Nếu số thực tế khác hệ thống, dùng điều chỉnh tồn kho và ghi lý do rõ ràng.', 'url' => $this->baseUrl . '/admin/inventory', 'label' => 'Cập nhật'],
                ],
                'tips' => ['Không trộn lô khi đơn cần truy xuất nguồn gốc.', 'Kiểm số lượng trước và sau khi xuất.', 'Lô mới nhập cần có đủ thông tin nhà cung cấp.'],
            ],
            Rbac::ROLE_EXPORT => [
                'title' => 'Quy trình nhân viên xuất khẩu',
                'intro' => 'Dành cho nhân viên xử lý đơn quốc tế: kiểm đơn, chứng từ, bàn giao vận chuyển, theo dõi chặng và hoàn tất.',
                'primaryUrl' => $this->baseUrl . '/admin/export_orders',
                'primaryLabel' => 'Mở đơn xuất khẩu',
                'situations' => [
                    ['label' => 'Có đơn quốc tế mới', 'text' => 'Kiểm quốc gia, địa chỉ, sản phẩm và yêu cầu vận chuyển.', 'url' => $this->baseUrl . '/admin/export_orders', 'icon' => 'fa-globe'],
                    ['label' => 'Cần chuẩn bị chứng từ', 'text' => 'Đối chiếu nguồn gốc lô, QR truy xuất và thông tin vận chuyển.', 'url' => $this->baseUrl . '/admin/export_orders', 'icon' => 'fa-file-signature'],
                    ['label' => 'Lịch vận chuyển thay đổi', 'text' => 'Chỉ cập nhật sau khi đơn đã xuất kho hoặc đã có lịch nhận hàng từ đơn vị vận chuyển.', 'url' => $this->baseUrl . '/admin/export_orders', 'icon' => 'fa-truck-fast'],
                ],
                'steps' => [
                    ['title' => 'Kiểm đơn xuất khẩu', 'text' => 'Kiểm quốc gia nhận, thông tin người nhận, sản phẩm và yêu cầu đóng gói.', 'url' => $this->baseUrl . '/admin/export_orders', 'label' => 'Kiểm đơn'],
                    ['title' => 'Chuẩn bị chứng từ', 'text' => 'Đối chiếu nguồn gốc lô, QR truy xuất, thông tin vận chuyển và ghi chú xuất khẩu.', 'url' => $this->baseUrl . '/admin/export_orders', 'label' => 'Chứng từ'],
                    ['title' => 'Bàn giao vận chuyển', 'text' => 'Sau khi kho xuất hàng, gán đơn vị vận chuyển, dịch vụ, tracking và ngày bàn giao.', 'url' => $this->baseUrl . '/admin/export_orders', 'label' => 'Bàn giao'],
                    ['title' => 'Theo dõi đến khi giao xong', 'text' => 'Cập nhật từng chặng vận chuyển và chỉ hoàn tất khi khách hoặc đối tác xác nhận đã nhận hàng.', 'url' => $this->baseUrl . '/admin/export_orders', 'label' => 'Theo dõi'],
                ],
                'tips' => ['Đơn quốc tế cần đủ thông tin quốc gia và địa chỉ.', 'Luôn kiểm nguồn gốc lô trước khi xác nhận.', 'Cập nhật lịch vận chuyển ngay khi có thay đổi.'],
            ],
        ];
    }

    // ================= QUẢN LÝ DANH MỤC NÔNG SẢN =================
    public function categories() {
        $categories = $this->categoryModel->getCategories();
        $this->renderView('admin/categories', [
            'categories' => $categories
        ]);
    }
    public function category_products($id) {

        $id = intval($id);

        $category = $this->categoryModel->getCategoryById($id);

        if (!$category) {
            echo "Không tìm thấy danh mục";
            return;
        }

        $products = $this->productModel->getProductsByCategory($id);
        $productOptions = array_values(array_filter(
            $this->productModel->getAll(),
            fn($product) => (int)($product->category_id ?? 0) !== $id
        ));

        $this->renderView('admin/category_products', [
            'category' => $category,
            'products' => $products,
            'productOptions' => $productOptions
        ]);
    }

    public function moveProductToCategory() {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $redirect = $categoryId > 0 ? "/admin/category_products/" . $categoryId : "/admin/categories";
        $this->requirePostAndCsrf($redirect);

        if ($this->productModel->updateProductCategory($productId, $categoryId)) {
            $_SESSION['admin_flash'] = 'Đã chuyển sản phẩm vào danh mục này.';
        } else {
            $_SESSION['admin_flash'] = 'Không thể chuyển danh mục sản phẩm.';
        }

        header("Location: " . $this->baseUrl . $redirect);
        exit();
    }

    public function add_category() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = htmlspecialchars($_POST['name'] ?? '');
            $description = htmlspecialchars($_POST['description'] ?? '');
            if (!empty($name)) {
                if ($this->categoryModel->addCategory($name, $description)) {
                    header("Location: " . $this->baseUrl . "/admin/categories");
                    exit();
                }
            }
        }
        $this->renderView('admin/add_category');
    }

    // ================= QUẢN LÝ SẢN PHẨM =================
    public function edit($id) {
        $id = intval($id);
        $product = $this->productModel->getProductById($id);
        $categories = $this->categoryModel->getCategories();
        $productOptions = $this->productModel->getAll();

        if (!$product) {
            echo "Không tìm thấy sản phẩm";
            return;
        }

        $this->renderView('admin/edit_product', [
            'product' => $product,
            'categories' => $categories,
            'productOptions' => $productOptions
        ]);
    }

    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = intval($id);

            if ($id <= 0 || !$this->productModel->getProductById($id)) {
                $_SESSION['admin_flash'] = 'San pham khong ton tai hoac da bi xoa.';
                header("Location: " . $this->baseUrl . "/admin/inventory");
                exit();
            }

            $name = trim((string)($_POST['name'] ?? ''));
            $description = $_POST['description'] ?? '';
            $price = $_POST['price'] ?? 0;
            $category_id = $_POST['category_id'] ?? 0;
            $image = $_POST['old_image'] ?? '';

            if ($id <= 0 || $name === '' || (float)$price <= 0 || (int)$category_id <= 0) {
                $_SESSION['admin_flash'] = 'Thong tin san pham khong hop le.';
                header("Location: " . $this->baseUrl . "/admin/edit/" . $id);
                exit();
            }

            if (!empty($_FILES['image']['name'])) {
                try {
                    $image = UploadHelper::saveUploadedFile(
                        $_FILES['image'],
                        APPROOT . '/../public/uploads/',
                        UploadHelper::IMAGE_TYPES,
                        'product'
                    );
                } catch (RuntimeException $e) {
                    $_SESSION['admin_flash'] = $e->getMessage();
                    header("Location: " . $this->baseUrl . "/admin/edit/" . $id);
                    exit();
                }
            }

            $this->productModel->updateProduct(
                $id, $name, $description, $price, $category_id, $image
            );

            header("Location: " . $this->baseUrl . "/admin/inventory");
            exit();
        }
    }

    public function delete($id) {
        $this->requirePostAndCsrf("/admin/inventory");
        $id = (int)$id;
        if ($id <= 0) {
            $_SESSION['admin_flash'] = 'San pham khong hop le.';
            header("Location: " . $this->baseUrl . "/admin/inventory");
            exit();
        }

        $_SESSION['admin_flash'] = $this->productModel->deleteProduct($id)
            ? 'Da xoa san pham khoi danh muc. Lich su don hang, lo hang va kho da duoc giu lai.'
            : 'Khong the xoa san pham nay. Vui long kiem tra log he thong hoac thu lai.';

        header("Location: " . $this->baseUrl . "/admin/inventory");
        exit();
    }

    public function edit_category($id) {
        $id = intval($id);
        $category = $this->categoryModel->getCategoryById($id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = htmlspecialchars($_POST['name'] ?? '');
            $description = htmlspecialchars($_POST['description'] ?? '');
            if ($this->categoryModel->updateCategory($id, $name, $description)) {
                header("Location: " . $this->baseUrl . "/admin/categories");
                exit();
            }
        }
        $this->renderView('admin/edit_category', ['category' => $category]);
    }

    public function delete_category($id) {
        $this->requirePostAndCsrf("/admin/categories");
        $id = intval($id);
        if ($this->categoryModel->deleteCategory($id)) {
            header("Location: " . $this->baseUrl . "/admin/categories");
        }
        exit();
    }

    // ================= KHO HÀNG =================
    public function inventory() {
        $this->renderView('admin/inventory_batches', [
            'products' => $this->productModel->getProducts(),
            'batches' => $this->supplyChainModel->getBatches(),
            'warehouses' => $this->supplyChainModel->getWarehouses(),
            'inventoryRows' => $this->supplyChainModel->getInventoryRows(),
            'inventoryOrders' => $this->supplyChainModel->getInventoryOrders(),
            'inventoryOrderItems' => $this->supplyChainModel->getInventoryOrderItems(),
            'inventoryLogs' => $this->supplyChainModel->getInventoryLogs()
        ]);
    }

    public function cultivation() {
        $_SESSION['admin_flash'] = 'Muc Canh tac / Thu hoach da duoc an. Du lieu cu van duoc giu trong he thong truy xuat nguon goc.';
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function saveCultivationLog() {
        $_SESSION['admin_flash'] = 'Muc nhat ky canh tac da duoc an. Du lieu cu van duoc bao toan.';
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function saveStorageMonitoring() {
        $_SESSION['admin_flash'] = 'Muc theo doi bao quan da duoc an. Du lieu cu van duoc bao toan.';
        header("Location: " . $this->baseUrl . "/admin/inventory");
        exit();
    }

    public function suppliers() {
        $this->renderView('admin/suppliers', [
            'suppliers' => $this->supplyChainModel->getSuppliers()
        ]);
    }

    public function saveSupplier() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->supplyChainModel->saveSupplier($_POST);
        }
        header("Location: " . $this->baseUrl . "/admin/suppliers");
        exit();
    }

    public function deleteSupplier($id) {
        $this->requirePostAndCsrf("/admin/suppliers");
        $this->supplyChainModel->deleteSupplier((int)$id);
        header("Location: " . $this->baseUrl . "/admin/suppliers");
        exit();
    }

    public function approveSupplier($id) {
        $this->requirePostAndCsrf("/admin/suppliers");
        $ok = $this->supplyChainModel->approveSupplier((int)$id);
        $_SESSION['admin_flash'] = $ok ? 'Da duyet ho so nha cung cap. Tai khoan se hoat dong sau khi xac thuc OTP.' : 'Khong the duyet nha cung cap nay.';
        header("Location: " . $this->baseUrl . "/admin/suppliers");
        exit();
    }

    public function batches() {
        $this->renderView('admin/batches', [
            'products' => $this->productModel->getProducts(),
            'categories' => $this->categoryModel->getCategories(),
            'suppliers' => $this->supplyChainModel->getSuppliers(),
            'warehouses' => $this->supplyChainModel->getWarehouses(),
            'batches' => $this->supplyChainModel->getBatches(),
            'supplierOrders' => $this->supplyChainModel->getSupplierOrders(),
            'supplierPaymentLogs' => $this->supplyChainModel->getSupplierPaymentLogs()
        ]);
    }

    public function saveBatch() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_FILES['product_image']['name'])) {
                try {
                    $_POST['product_image'] = UploadHelper::saveUploadedFile(
                        $_FILES['product_image'],
                        APPROOT . '/../public/uploads/',
                        UploadHelper::IMAGE_TYPES,
                        'product'
                    );
                } catch (RuntimeException $e) {
                    $_SESSION['admin_flash'] = $e->getMessage();
                    header("Location: " . $this->baseUrl . "/admin/batches");
                    exit();
                }
            }
            $result = $this->supplyChainModel->saveBatch($_POST);
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Đã tạo lô hàng.' : 'Không thể tạo lô hàng.');
        }
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function createSupplierOrder() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->supplyChainModel->createSupplierOrder($_POST);
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Đã tạo đơn nhập lô.' : 'Không thể tạo đơn nhập lô.');
        }
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function confirmSupplierOrder() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->supplyChainModel->confirmSupplierOrder($_POST);
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Đã xác nhận nhập kho.' : 'Không thể xác nhận nhập kho.');
        }
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function paySupplierOrder() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->supplyChainModel->paySupplierOrder($_POST);
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Đã thanh toán nhà cung cấp.' : 'Không thể thanh toán nhà cung cấp.');
        }
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function deleteBatch($id) {
        $this->requirePostAndCsrf("/admin/batches");
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->supplyChainModel->deleteBatch((int)$id, $_POST['reason'] ?? '');
            $_SESSION['admin_flash'] = match ($result) {
                'deleted' => 'Đã xóa lô hàng và dọn các liên kết tồn kho liên quan.',
                'recalled' => 'Đã xóa lô hàng và dọn các liên kết tồn kho liên quan.',
                'missing' => 'Không tìm thấy lô hàng cần xử lý.',
                default => 'Không thể xử lý lô hàng này. Vui lòng kiểm tra lại dữ liệu.',
            };
        }
        header("Location: " . $this->baseUrl . "/admin/batches");
        exit();
    }

    public function saveWarehouse() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->supplyChainModel->saveWarehouse($_POST);
        }
        header("Location: " . $this->baseUrl . "/admin/inventory");
        exit();
    }

    public function adjustInventory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST['type'] = 'import';
            $result = $this->supplyChainModel->adjustInventory($_POST);
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Da cap nhat kho.' : 'Khong the cap nhat kho.');
        }
        $redirect = (string)($_POST['redirect'] ?? '');
        if (!str_starts_with($redirect, $this->baseUrl . '/admin/')) {
            $redirect = $this->baseUrl . "/admin/inventory";
        }
        header("Location: " . $redirect);
        exit();
    }

    public function exportOrderInventory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $result = $this->supplyChainModel->exportOrderInventory($orderId);
            if (($result['success'] ?? false) && $orderId > 0) {
                $this->supplyChainModel->syncOrderProcessStatus($orderId, OrderModel::STATUS_PACKED);
            }
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Da xuat kho don hang.' : 'Khong the xuat kho don hang.');
        }
        $back = $_POST['redirect'] ?? ($this->baseUrl . "/admin/order_management");
        header("Location: " . $back);
        exit();
    }

    public function confirmOrderForInventory() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                $_SESSION['admin_flash'] = 'Don hang khong hop le.';
            } else {
                $order = $this->orderModel->getOrderById($orderId);
                if (!$order) {
                    $_SESSION['admin_flash'] = 'Khong tim thay don hang.';
                } else {
                    $status = (string)($order->status ?? OrderModel::STATUS_PENDING);
                    if ($status === OrderModel::STATUS_PENDING) {
                        if ($this->orderModel->updateStatus($orderId, OrderModel::STATUS_CONFIRMED)) {
                            $stock = $this->supplyChainModel->checkOrderStock($orderId);
                            $_SESSION['admin_flash'] = 'Da xac nhan don va kiem kho. ' . ($stock['message'] ?? '');
                        } else {
                            $_SESSION['admin_flash'] = 'Khong the xac nhan don. Kiem tra thanh toan hoac dong san pham.';
                        }
                    } elseif (in_array($status, [OrderModel::STATUS_CONFIRMED, OrderModel::STATUS_PREPARING], true)) {
                        $stock = $this->supplyChainModel->checkOrderStock($orderId);
                        $_SESSION['admin_flash'] = 'Da kiem kho lai. ' . ($stock['message'] ?? '');
                    } elseif ((string)($order->stock_status ?? '') === 'exported') {
                        $_SESSION['admin_flash'] = 'Don da xuat kho truoc do.';
                    } else {
                        $_SESSION['admin_flash'] = 'Don khong nam trong buoc xac nhan kho.';
                    }
                }
            }
        }
        $back = $_POST['redirect'] ?? ($this->baseUrl . "/admin/inventory");
        header("Location: " . $back);
        exit();
    }

    public function updateStock() {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false]);
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode(['success' => $id > 0 && $this->productModel->updateStock($id, 0)]);
    }

    public function export_orders() {
        $orders = $this->supplyChainModel->getExportOrders();
        $orderIds = array_map(fn($order) => (int)($order->id ?? 0), $orders);
        $this->renderView('admin/export_orders', [
            'orders' => $orders,
            'orderItems' => $this->orderModel->getOrderItemsForOrders($orderIds),
            'shipmentTimelines' => $this->supplyChainModel->getShipmentTimelinesForOrders($orders)
        ]);
    }

    public function saveShipment() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $status = htmlspecialchars($_POST['status'] ?? '');
            $current = $orderId > 0 ? $this->orderModel->getOrderById($orderId) : null;
            $shipmentFlashOverride = null;
            if ($status === OrderModel::STATUS_COMPLETED && $current && ($current->status ?? '') !== OrderModel::STATUS_COMPLETED) {
                $_POST['status'] = OrderModel::STATUS_SHIPPED;
                $status = OrderModel::STATUS_SHIPPED;
                $shipmentFlashOverride = 'Da luu van chuyen. Khach hang se xac nhan nhan hang va thanh toan phan con lai.';
            }
            $result = $this->supplyChainModel->saveShipment($_POST);
            $_SESSION['admin_flash'] = $shipmentFlashOverride ?? ($result['message'] ?? '');
            if (($result['success'] ?? false) && $orderId > 0 && in_array($status, [OrderModel::STATUS_SHIPPED, OrderModel::STATUS_COMPLETED], true)) {
                if (!$this->orderModel->updateStatus($orderId, $status)) {
                    $_SESSION['admin_flash'] = 'Khong the chuyen trang thai don. Kiem tra shipment va state machine.';
                }
            }
        }
        header("Location: " . $this->baseUrl . "/admin/export_orders");
        exit();
    }

    public function saveDomesticShipmentSchedule() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->supplyChainModel->saveShipment($_POST);
            $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Da cap nhat lich giao hang.' : 'Khong the cap nhat lich giao hang.');
        }
        header("Location: " . $this->baseUrl . "/admin/order_management");
        exit();
    }

    public function advanceShipmentStep() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            if ($orderId > 0) {
                $result = $this->supplyChainModel->advanceShipmentStep($orderId);
                $_SESSION['admin_flash'] = $result['message'] ?? (($result['success'] ?? false) ? 'Da cap nhat chang van chuyen.' : 'Khong the cap nhat chang van chuyen.');
                $nextStatus = $result['order_status'] ?? null;
                if (($result['success'] ?? false) && in_array($nextStatus, [OrderModel::STATUS_SHIPPED, OrderModel::STATUS_COMPLETED], true)) {
                    $this->orderModel->updateStatus($orderId, $nextStatus);
                    $this->supplyChainModel->syncOrderProcessStatus($orderId, $nextStatus);
                }
            }
        }
        header("Location: " . $this->baseUrl . "/admin/export_orders");
        exit();
    }

    // ================= THỐNG KÊ =================
    public function statistics() {
        try {
            $fromDate = trim($_GET['from_date'] ?? '');
            $toDate = trim($_GET['to_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
                $fromDate = date('Y-m-d', strtotime('-6 days'));
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
                $toDate = date('Y-m-d');
            }
            if (strtotime($fromDate) > strtotime($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }

            $statusFilter = trim($_GET['status'] ?? '');
            $allowedStatuses = ['pending', 'confirmed', 'preparing', 'packed', 'shipped', 'completed', 'cancelled'];
            if (!in_array($statusFilter, $allowedStatuses, true)) {
                $statusFilter = '';
            }

            $scopeFilter = trim($_GET['scope'] ?? '');
            if (!in_array($scopeFilter, ['domestic', 'international'], true)) {
                $scopeFilter = '';
            }

            $where = ["DATE(created_at) BETWEEN ? AND ?"];
            $params = [$fromDate, $toDate];
            if ($statusFilter !== '') {
                $where[] = "status = ?";
                $params[] = $statusFilter;
            }
            if ($scopeFilter !== '') {
                $where[] = "COALESCE(delivery_scope, 'domestic') = ?";
                $params[] = $scopeFilter;
            }
            $whereSql = "WHERE " . implode(" AND ", $where);

            $reportDays = (new DateTimeImmutable($fromDate))->diff(new DateTimeImmutable($toDate))->days + 1;
            $chartByMonth = $reportDays > 62;

            $revenueQuery = $this->db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM orders {$whereSql}");
            $revenueQuery->execute($params);
            $revenue = $revenueQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $orderQuery = $this->db->prepare("SELECT COUNT(id) as total FROM orders {$whereSql}");
            $orderQuery->execute($params);
            $total_orders = $orderQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $userQuery = $this->db->prepare("SELECT COUNT(id) as total FROM account WHERE role = 'user' AND DATE(created_at) BETWEEN ? AND ?");
            $userQuery->execute([$fromDate, $toDate]);
            $total_users = $userQuery->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $chartSelect = $chartByMonth
                ? "DATE_FORMAT(created_at, '%Y-%m') as date_key, DATE_FORMAT(created_at, '%m/%Y') as date_label"
                : "DATE(created_at) as date_key, DATE_FORMAT(created_at, '%d/%m') as date_label";
            $chartGroup = $chartByMonth
                ? "DATE_FORMAT(created_at, '%Y-%m'), date_label"
                : "DATE(created_at), date_label";
            $chartOrder = $chartByMonth
                ? "MIN(DATE(created_at)) ASC"
                : "DATE(created_at) ASC";
            $chartQuery = $this->db->prepare("
                SELECT {$chartSelect}, COALESCE(SUM(total), 0) as daily_revenue
                FROM orders
                {$whereSql}
                GROUP BY {$chartGroup}
                ORDER BY {$chartOrder}
            ");
            $chartQuery->execute($params);
            $rawChartData = $chartQuery->fetchAll(PDO::FETCH_ASSOC);
            $chartRowsByKey = [];
            foreach ($rawChartData as $row) {
                $chartRowsByKey[(string)$row['date_key']] = (float)$row['daily_revenue'];
            }
            $chartLabels = [];
            $chartValues = [];
            if ($chartByMonth) {
                $cursor = new DateTimeImmutable(date('Y-m-01', strtotime($fromDate)));
                $end = new DateTimeImmutable(date('Y-m-01', strtotime($toDate)));
                while ($cursor <= $end) {
                    $key = $cursor->format('Y-m');
                    $chartLabels[] = $cursor->format('m/Y');
                    $chartValues[] = $chartRowsByKey[$key] ?? 0;
                    $cursor = $cursor->modify('+1 month');
                }
            } else {
                $cursor = new DateTimeImmutable($fromDate);
                $end = new DateTimeImmutable($toDate);
                while ($cursor <= $end) {
                    $key = $cursor->format('Y-m-d');
                    $chartLabels[] = $cursor->format('d/m');
                    $chartValues[] = $chartRowsByKey[$key] ?? 0;
                    $cursor = $cursor->modify('+1 day');
                }
            }
            $chart_data = [
                'labels' => $chartLabels,
                'values' => $chartValues,
                'granularity' => $chartByMonth ? 'month' : 'day',
            ];

            $pieQuery = $this->db->prepare("
                SELECT COALESCE(c.name, 'Chưa phân loại') as cat_name,
                       COALESCE(SUM(od.quantity), 0) as count
                FROM orders o
                JOIN order_details od ON od.order_id = o.id
                LEFT JOIN product p ON p.id = od.product_id
                LEFT JOIN category c ON c.id = p.category_id
                {$whereSql}
                GROUP BY c.id, cat_name
                ORDER BY count DESC, cat_name ASC
            ");
            $pieQuery->execute($params);
            $rawPieData = $pieQuery->fetchAll(PDO::FETCH_ASSOC);
            $pie_data = [
                'labels' => array_column($rawPieData, 'cat_name'),
                'values' => array_map('intval', array_column($rawPieData, 'count'))
            ];

            if (empty($pie_data['labels'])) { $pie_data = ['labels' => ['Trống'], 'values' => [1]]; }

            $lowStockQuery = $this->db->query("
                SELECT name, stock
                FROM product
                WHERE stock <= 10
                ORDER BY stock ASC, name ASC
                LIMIT 8
            ");
            $low_stock_products = $lowStockQuery->fetchAll(PDO::FETCH_ASSOC);

            $exportDueQuery = $this->db->prepare("
                SELECT o.id, o.total, o.status, o.created_at,
                       COALESCE(o.fullname, a.fullname, 'Khách hàng') AS fullname,
                       COALESCE(NULLIF(o.recipient_country, ''), NULLIF(o.export_country, ''), s.export_country, 'Quốc tế') AS destination,
                       COALESCE(NULLIF(o.shipping_carrier, ''), s.carrier, 'Chưa gán đơn vị') AS carrier,
                       COALESCE(NULLIF(o.shipping_service, ''), NULLIF(o.transport_method, ''), s.transport_method, 'Chưa gán dịch vụ') AS service,
                       COALESCE(s.arrival_date, DATE_ADD(o.created_at, INTERVAL 10 DAY)) AS due_date
                FROM orders o
                LEFT JOIN account a ON a.id = o.user_id
                LEFT JOIN shipments s ON s.order_id = o.id
                WHERE o.status NOT IN ('completed', 'cancelled')
                  AND DATE(o.created_at) BETWEEN ? AND ?
                  AND COALESCE(o.delivery_scope, 'domestic') = 'international'
                ORDER BY due_date ASC, o.created_at ASC
                LIMIT 5
            ");
            $exportDueQuery->execute([$fromDate, $toDate]);
            $export_due_orders = $exportDueQuery->fetchAll(PDO::FETCH_ASSOC);

            $domesticDueQuery = $this->db->prepare("
                SELECT o.id, o.total, o.status, o.created_at,
                       COALESCE(o.fullname, a.fullname, 'Khách hàng') AS fullname,
                       CONCAT_WS(', ',
                           NULLIF(o.recipient_ward, ''),
                           NULLIF(o.recipient_district, ''),
                           NULLIF(o.recipient_city, '')
                       ) AS destination,
                       COALESCE(NULLIF(o.shipping_carrier, ''), 'Chưa gán đơn vị') AS carrier,
                       COALESCE(NULLIF(o.shipping_service, ''), 'Giao nội địa') AS service,
                       DATE_ADD(o.created_at, INTERVAL 3 DAY) AS due_date
                FROM orders o
                LEFT JOIN account a ON a.id = o.user_id
                WHERE o.status NOT IN ('completed', 'cancelled')
                  AND DATE(o.created_at) BETWEEN ? AND ?
                  AND COALESCE(o.delivery_scope, 'domestic') = 'domestic'
                ORDER BY due_date ASC, o.created_at ASC
                LIMIT 5
            ");
            $domesticDueQuery->execute([$fromDate, $toDate]);
            $domestic_due_orders = $domesticDueQuery->fetchAll(PDO::FETCH_ASSOC);

        }catch (PDOException $e) {
    die($e->getMessage());
}

        $this->renderView('admin/statistics', [
            'revenue' => $revenue,
            'total_orders' => $total_orders,
            'total_users' => $total_users,
            'chart_data' => $chart_data,
            'pie_data' => $pie_data,
            'supply_reports' => $this->supplyChainModel->getSupplyReports($fromDate, $toDate, $statusFilter, $scopeFilter),
            'export_due_orders' => $export_due_orders ?? [],
            'domestic_due_orders' => $domestic_due_orders ?? [],
            'low_stock_products' => $low_stock_products ?? [],
            'inventory_logs' => $this->supplyChainModel->getInventoryLogs(80, $fromDate, $toDate, $statusFilter, $scopeFilter),
            'order_events' => $this->orderModel->getRecentOrderEvents(100, $fromDate, $toDate, $statusFilter, $scopeFilter),
            'report_filters' => [
                'from_date' => $fromDate ?? date('Y-m-d', strtotime('-6 days')),
                'to_date' => $toDate ?? date('Y-m-d'),
                'status' => $statusFilter ?? '',
                'scope' => $scopeFilter ?? '',
            ]
        ]);
    }

    public function exportStatistics() {
        $filename = 'bao-cao-chuoi-cung-ung-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['BÁO CÁO TỔNG QUAN CHUỖI CUNG ỨNG']);
        fputcsv($out, ['Thời điểm xuất', date('d/m/Y H:i:s')]);
        fputcsv($out, []);

        $fromDate = trim($_GET['from_date'] ?? '');
        $toDate = trim($_GET['to_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $fromDate = date('Y-m-d', strtotime('-6 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $toDate = date('Y-m-d');
        }
        if (strtotime($fromDate) > strtotime($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }
        $statusFilter = trim($_GET['status'] ?? '');
        $allowedStatuses = ['pending', 'confirmed', 'preparing', 'packed', 'shipped', 'completed', 'cancelled'];
        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = '';
        }
        $scopeFilter = trim($_GET['scope'] ?? '');
        if (!in_array($scopeFilter, ['domestic', 'international'], true)) {
            $scopeFilter = '';
        }
        $where = ["DATE(created_at) BETWEEN ? AND ?"];
        $orderParams = [$fromDate, $toDate];
        if ($statusFilter !== '') {
            $where[] = "status = ?";
            $orderParams[] = $statusFilter;
        }
        if ($scopeFilter !== '') {
            $where[] = "COALESCE(delivery_scope, 'domestic') = ?";
            $orderParams[] = $scopeFilter;
        }
        $orderWhereSql = "WHERE " . implode(" AND ", $where);
        $supplyReports = $this->supplyChainModel->getSupplyReports($fromDate, $toDate, $statusFilter, $scopeFilter);
        $valueFromSql = function (string $sql, array $params = []): string {
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return (string)($stmt->fetchColumn() ?: 0);
            } catch (Throwable $e) {
                return '0';
            }
        };

        fputcsv($out, ['Khoảng ngày', date('d/m/Y', strtotime($fromDate)) . ' - ' . date('d/m/Y', strtotime($toDate))]);
        fputcsv($out, ['Trạng thái', $statusFilter !== '' ? $statusFilter : 'Tất cả']);
        fputcsv($out, ['Phạm vi', $scopeFilter !== '' ? $scopeFilter : 'Tất cả']);
        fputcsv($out, []);

        $metricRows = [
            'Doanh thu' => $valueFromSql("SELECT COALESCE(SUM(total),0) FROM orders {$orderWhereSql}", $orderParams),
            'Đơn hàng' => $valueFromSql("SELECT COUNT(*) FROM orders {$orderWhereSql}", $orderParams),
            'Khách hàng mới' => $valueFromSql("SELECT COUNT(*) FROM account WHERE role = 'user' AND DATE(created_at) BETWEEN ? AND ?", [$fromDate, $toDate]),
            'Nhà cung cấp mới/đang hoạt động' => (string)($supplyReports['suppliers'] ?? 0),
            'Lô hàng theo ngày' => (string)($supplyReports['batches'] ?? 0),
            'Kho lưu trữ' => (string)($supplyReports['warehouses'] ?? 0),
            'Tồn kho hiện tại theo lô' => (string)($supplyReports['inventory_quantity'] ?? 0),
            'Đơn xuất khẩu' => (string)($supplyReports['export_orders'] ?? 0),
            'Lượt quét QR' => $valueFromSql("SELECT COUNT(*) FROM qr_scan_history WHERE DATE(scanned_at) BETWEEN ? AND ?", [$fromDate, $toDate]),
        ];
        fputcsv($out, ['Chỉ số', 'Giá trị']);
        foreach ($metricRows as $label => $value) {
            fputcsv($out, [$label, (string)$value]);
        }

        fputcsv($out, []);
        fputcsv($out, ['Đơn hàng gần đây']);
        fputcsv($out, ['Mã đơn', 'Khách hàng', 'Phạm vi', 'Quốc gia', 'Thanh toán', 'Trạng thái', 'Tổng tiền', 'Ngày tạo']);
        $orderListStmt = $this->db->prepare("
            SELECT id, fullname, delivery_scope, recipient_country, payment_method,
                   payment_status, status, total, created_at
            FROM orders
            {$orderWhereSql}
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $orderListStmt->execute($orderParams);
        $orders = $orderListStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orders as $row) {
            fputcsv($out, [
                $row['id'] ?? '',
                $row['fullname'] ?? '',
                $row['delivery_scope'] ?? '',
                $row['recipient_country'] ?? '',
                trim(($row['payment_method'] ?? '') . ' / ' . ($row['payment_status'] ?? '')),
                $row['status'] ?? '',
                $row['total'] ?? 0,
                $row['created_at'] ?? '',
            ]);
        }

        fputcsv($out, []);
        fputcsv($out, ['Lịch sử quét QR gần đây']);
        fputcsv($out, ['Thời gian', 'Sản phẩm', 'Mã lô', 'Người quét', 'Nguồn', 'IP']);
        try {
            $scanStmt = $this->db->prepare("
                SELECT q.scanned_at, p.name AS product_name, q.batch_code,
                       q.scanner_name, q.source, q.ip_address
                FROM qr_scan_history q
                LEFT JOIN product p ON p.id = q.product_id
                WHERE DATE(q.scanned_at) BETWEEN ? AND ?
                ORDER BY q.scanned_at DESC, q.id DESC
                LIMIT 50
            ");
            $scanStmt->execute([$fromDate, $toDate]);
            $scans = $scanStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($scans as $row) {
                fputcsv($out, [
                    $row['scanned_at'] ?? '',
                    $row['product_name'] ?? '',
                    $row['batch_code'] ?? '',
                    $row['scanner_name'] ?? '',
                    $row['source'] ?? '',
                    $row['ip_address'] ?? '',
                ]);
            }
        } catch (Throwable $e) {
            fputcsv($out, ['Chưa có dữ liệu lịch sử quét QR']);
        }

        fclose($out);
        exit();
    }

    // ================= QUẢN LÝ ĐƠN HÀNG =================
    public function cancelled_orders() {
        $orders = $this->supplyChainModel->getCancelledOrders();
        $orderIds = array_map(fn($order) => (int)($order->id ?? 0), $orders);
        $this->renderView('admin/cancelled_orders', [
            'orders' => $orders,
            'orderItems' => $this->orderModel->getOrderItemsForOrders($orderIds)
        ]);
    }

    public function order_management() {
        $orders = $this->orderModel->getDomesticOrders();
        $orderIds = array_map(fn($order) => (int)($order->id ?? 0), $orders);
        $this->renderView('admin/order_management', [
            'orders' => $orders,
            'orderItems' => $this->orderModel->getOrderItemsForOrders($orderIds)
        ]);
    }

    // HÀM MỚI THÊM: Xử lý cập nhật trạng thái đơn hàng từ Form
    public function updateOrderStatus() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $order_id = intval($_POST['order_id'] ?? 0);
            $status = htmlspecialchars($_POST['status'] ?? '');

            if ($order_id > 0 && !empty($status)) {
                $current = $this->orderModel->getOrderById($order_id);
                if ($status === OrderModel::STATUS_PACKED && $current && ($current->stock_status ?? '') !== 'exported' && !in_array($current->status ?? '', [OrderModel::STATUS_PACKED, OrderModel::STATUS_SHIPPED, OrderModel::STATUS_COMPLETED], true)) {
                    $_SESSION['admin_flash'] = 'Can xuat kho tu dong truoc khi chuyen sang da dong goi.';
                    header("Location: " . $this->baseUrl . "/admin/order_management");
                    exit();
                }
                if ($status === OrderModel::STATUS_SHIPPED && $current && !in_array($current->status ?? '', [OrderModel::STATUS_PACKED, OrderModel::STATUS_SHIPPED, OrderModel::STATUS_COMPLETED], true)) {
                    $_SESSION['admin_flash'] = 'Can xuat kho/dong goi don truoc khi giao hang.';
                    header("Location: " . $this->baseUrl . "/admin/order_management");
                    exit();
                }
                if ($status === OrderModel::STATUS_COMPLETED && $current && ($current->status ?? '') !== OrderModel::STATUS_COMPLETED) {
                    $_SESSION['admin_flash'] = 'Khach hang se xac nhan da nhan hang va thanh toan phan con lai o trang chi tiet don.';
                    header("Location: " . $this->baseUrl . "/admin/order_management");
                    exit();
                }

                if ($this->orderModel->updateStatus($order_id, $status)) {
                    if (in_array($status, [OrderModel::STATUS_CONFIRMED, OrderModel::STATUS_PREPARING], true)) {
                        $stock = $this->supplyChainModel->checkOrderStock($order_id);
                        $_SESSION['admin_flash'] = $stock['message'] ?? 'Da kiem tra ton kho.';
                    }
                    $this->supplyChainModel->syncOrderProcessStatus($order_id, $status);
                    header("Location: " . $this->baseUrl . "/admin/order_management");
                    exit();
                }
                $_SESSION['admin_flash'] = $this->orderModel->hasOrderItems($order_id)
                    ? 'Khong the chuyen trang thai do state machine khong hop le.'
                    : 'Don hang nay khong co dong san pham hop le. Vui long huy va tao lai don de xuat kho.';
            }
        }
        header("Location: " . $this->baseUrl . "/admin/order_management");
        exit();
    }

    public function orderDetail($id) {
        $order = $this->orderModel->getOrderById((int)$id);
        if (!$order) {
            header("Location: " . $this->baseUrl . "/admin/order_management");
            exit();
        }

        $order_items = $this->orderModel->getOrderDetails((int)$id);
        $shipment = $this->supplyChainModel->getShipmentForOrder((int)$id);
        $shipmentTimelineMap = $this->supplyChainModel->getShipmentTimelinesForOrders([$order]);
        $shipmentTimeline = $shipmentTimelineMap[(int)$id] ?? [];
        $orderEvents = $this->orderModel->getOrderEvents((int)$id);
        $backUrl = $this->baseUrl . "/admin/order_management";
        $backLabel = "Quay lại nội địa";

        include APPROOT . '/views/shares/header.php';
        include APPROOT . '/views/account/orderDetail.php';
        include APPROOT . '/views/shares/footer.php';
    }

    public function promotions() {
        $this->renderView('admin/promotions', [
            'promotions' => $this->promotionModel->getAllPromotions(),
            'products' => $this->productModel->getMarketingEligibleProducts()
        ]);
    }

    public function savePromotion() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = $_POST['code'] ?? '';
            $discount = $_POST['discount_percent'] ?? 0;
            $expiry = $_POST['expiry_date'] ?? '';
            $_SESSION['admin_flash'] = $this->promotionModel->addPromotion($code, $discount, $expiry)
                ? 'Đã tạo mã khuyến mãi.'
                : 'Không thể tạo mã. Kiểm tra mã trùng hoặc dữ liệu không hợp lệ.';
        }
        header("Location: " . $this->baseUrl . "/admin/promotions");
        exit();
    }

    public function togglePromotion($id) {
        $this->requirePostAndCsrf("/admin/promotions");
        $this->promotionModel->toggleStatusById((int)$id);
        header("Location: " . $this->baseUrl . "/admin/promotions");
        exit();
    }

    public function deletePromotion($id) {
        $this->requirePostAndCsrf("/admin/promotions");
        $this->promotionModel->deletePromotion((int)$id);
        header("Location: " . $this->baseUrl . "/admin/promotions");
        exit();
    }

    public function saveFlashSale() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $productIds = $_POST['flash_product_ids'] ?? [];
            $discounts = $_POST['flash_discounts'] ?? [];
            $_SESSION['admin_flash'] = $this->productModel->updateFlashSaleSettings($productIds, $discounts)
                ? 'Đã cập nhật Flash Sale.'
                : 'Không thể cập nhật Flash Sale. Vui lòng kiểm tra lại dữ liệu.';
        }
        header("Location: " . $this->baseUrl . "/admin/promotions");
        exit();
    }

    public function saveFeaturedProducts() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $productIds = $_POST['featured_product_ids'] ?? [];
            $_SESSION['admin_flash'] = $this->productModel->updateFeaturedSettings($productIds)
                ? 'Đã cập nhật sản phẩm nổi bật.'
                : 'Không thể cập nhật sản phẩm nổi bật. Vui lòng kiểm tra lại dữ liệu.';
        }
        header("Location: " . $this->baseUrl . "/admin/promotions");
        exit();
    }

    public function chatbot() {
        header("Location: " . $this->baseUrl . "/admin/workflow");
        exit();
    }

    public function saveChatbotEntry() {
        header("Location: " . $this->baseUrl . "/admin/workflow");
        exit();
    }

    public function deleteChatbotEntry($id) {
        header("Location: " . $this->baseUrl . "/admin/workflow");
        exit();
    }

    public function account() {
    $users = $this->userModel->getAllUsers();
    $this->renderView('admin/account_management', ['users' => $users]);
}

    // ================= THÊM SẢN PHẨM =================
    public function add() {
        $categories = $this->categoryModel->getCategories();
        $selectedCategoryId = (int)($_GET['category_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = htmlspecialchars($_POST['name'] ?? '');
            $description = $_POST['description'] ?? '';
            $price = floatval($_POST['price'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0);
            $redirectCategoryId = intval($_POST['redirect_category_id'] ?? 0);
            $image = "";

            if (!empty($_FILES['image']['name'])) {
                try {
                    $image = UploadHelper::saveUploadedFile(
                        $_FILES['image'],
                        APPROOT . '/../public/uploads/',
                        UploadHelper::IMAGE_TYPES,
                        'product'
                    );
                } catch (RuntimeException $e) {
                    $_SESSION['admin_flash'] = $e->getMessage();
                    header("Location: " . $this->baseUrl . "/admin/add");
                    exit();
                }
            }

            if ($this->productModel->addProduct($name, $description, $price, $category_id, $image)) {
                $redirectPath = $redirectCategoryId > 0
                    ? "/admin/category_products/" . $redirectCategoryId
                    : "/admin/inventory";
                header("Location: " . $this->baseUrl . $redirectPath);
                exit();
            }
        }
        $this->renderView('admin/add_product', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId
        ]);
    }

    public function add_product() {
        $this->add();
    }
public function editUser($id) {

    $id = intval($id);

    // lấy user
    $user = $this->userModel->getUserById($id);

    if (!$user) {
        die("Không tìm thấy người dùng");
    }

    // submit form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $fullname = htmlspecialchars($_POST['fullname'] ?? '');
        $email = htmlspecialchars($_POST['email'] ?? '');
        $phone = htmlspecialchars($_POST['phone'] ?? '');
        $role = htmlspecialchars($_POST['role'] ?? 'user');
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;

        // update
        $this->userModel->updateUser(
            $id,
            $fullname,
            $email,
            $phone,
            $role,
            $supplierId
        );

        header("Location: " . $this->baseUrl . "/admin/account");
        exit();
    }

    $this->renderView('admin/edit_user', [
        'user' => $user,
        'suppliers' => $this->supplyChainModel->getSuppliers()
    ]);
}
public function deleteUser($id) {
    $this->requirePostAndCsrf("/admin/account");

    $id = intval($id);

    if ($this->userModel->deleteUser($id)) {

        header("Location: " . $this->baseUrl . "/admin/account");
        exit();
    }

    echo "<script>
        alert('Không thể xóa user vì đã có đơn hàng!');
        window.location.href = '" . $this->baseUrl . "/admin/account';
    </script>";
    exit();
}
}
