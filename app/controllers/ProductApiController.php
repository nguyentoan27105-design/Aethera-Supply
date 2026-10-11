<?php
require_once('app/config/database.php');
require_once('app/models/ProductModel.php');
require_once('app/helpers/Rbac.php');
require_once('app/helpers/TraceScanLogger.php');

class ProductApiController
{
    private $productModel;
    private $db;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Khởi tạo kết nối Database và Model
        $this->db = (new Database())->getConnection();
        $this->productModel = new ProductModel($this->db);
        
        // Thiết lập Header chung cho API
        header('Content-Type: application/json; charset=utf-8');
        $this->setCorsHeaders();
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        // Xử lý request OPTIONS (CORS preflight)
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    private function setCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $originHost = $origin ? (parse_url($origin, PHP_URL_HOST) ?: '') : '';
        $requestHost = $host ? preg_replace('/:\d+$/', '', $host) : '';

        if ($origin !== '' && $originHost !== '' && strcasecmp($originHost, $requestHost) === 0) {
            header("Access-Control-Allow-Origin: " . $origin);
            header("Access-Control-Allow-Credentials: true");
            header("Vary: Origin");
        }
    }

    private function requireAdmin(): bool
    {
        $role = Rbac::normalizeRole($_SESSION['user_role'] ?? null);
        if (!isset($_SESSION['user_id']) || !Rbac::can($role, 'inventory')) {
            http_response_code(403);
            echo json_encode(['message' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return true;
    }

    private function latestBatchForProduct(int $productId): ?object
    {
        try {
            $stmt = $this->db->prepare("
                SELECT b.id, b.batch_code
                FROM product_batch b
                JOIN inventory i ON i.batch_id = b.id
                JOIN warehouse w ON w.id = i.warehouse_id
                WHERE b.product_id = ?
                  AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  AND COALESCE(w.status, 'active') = 'active'
                  AND COALESCE(i.quantity, 0) > 0
                ORDER BY b.id DESC
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (Throwable $e) {
            error_log('API trace batch lookup error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔥 LẤY DANH SÁCH SẢN PHẨM
     */
    public function index()
    {
        $keyword = $_GET['search'] ?? null;
        $products = $this->productModel->getAvailableProducts($keyword);
        // Đảm bảo trả về mảng rỗng nếu không có sản phẩm thay vì null
        echo json_encode($products ?: [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 🔥 LẤY CHI TIẾT 1 SẢN PHẨM
     */
    public function show($id)
    {
        $product = $this->productModel->getAvailableProductById($id);
        if ($product) {
            echo json_encode($product, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['message' => 'Không tìm thấy sản phẩm'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🔥 TẠO MỚI SẢN PHẨM (POST)
     */
    public function store()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['message' => 'Dữ liệu JSON không hợp lệ hoặc trống'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $price = floatval($data['price'] ?? 0);
        $category_id = intval($data['category_id'] ?? 0);
        $image = $data['image'] ?? 'default.jpg'; 

        if (empty($name) || $price <= 0 || $category_id <= 0) {
            http_response_code(400);
            echo json_encode(['message' => 'Thiếu thông tin bắt buộc: name, price, category_id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->productModel->addProduct($name, $description, $price, $category_id, $image);

        if ($result) {
            http_response_code(201);
            echo json_encode(['message' => 'Thêm sản phẩm thành công', 'id' => $result], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Lỗi server: Không thể lưu sản phẩm'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🔥 CẬP NHẬT SẢN PHẨM (PUT)
     */
    public function update($id)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = intval($id);
        $product = $this->productModel->getProductById($id);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['message' => "Sản phẩm ID $id không tồn tại"], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Chuyển product về array nếu nó đang là object để dễ truy xuất data cũ
        $oldData = (array)$product;

        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['message' => 'Dữ liệu gửi lên không đúng định dạng JSON'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Lấy dữ liệu mới, nếu không gửi thì dùng lại dữ liệu cũ
        $name = trim($data['name'] ?? $oldData['name']);
        $description = trim($data['description'] ?? $oldData['description']);
        $price = isset($data['price']) ? floatval($data['price']) : $oldData['price'];
        $category_id = isset($data['category_id']) ? intval($data['category_id']) : $oldData['category_id'];
        $image = $data['image'] ?? $oldData['image']; 

        $result = $this->productModel->updateProduct($id, $name, $description, $price, $category_id, $image);

        if ($result) {
            echo json_encode(['message' => 'Cập nhật sản phẩm thành công'], JSON_UNESCAPED_UNICODE);
        } else {
            // Có thể thành công nhưng DB không đổi vì dữ liệu gửi lên trùng hoàn toàn với dữ liệu cũ
            echo json_encode(['message' => 'Hoàn tất (Không có thay đổi nào được thực hiện)'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 🔥 XÓA SẢN PHẨM (DELETE)
     */
    public function destroy($id)
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $id = intval($id);
        $product = $this->productModel->getProductById($id);
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['message' => "Không tìm thấy sản phẩm ID $id"], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $result = $this->productModel->deleteProduct($id);
            if ($result) {
                echo json_encode(['message' => 'Xóa sản phẩm thành công'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Lỗi thực thi lệnh xóa trên Database'], JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Không thể xóa sản phẩm này (có thể liên quan đến đơn hàng tồn tại)'], JSON_UNESCAPED_UNICODE);
        }
    }
        /**
     * 🔥 API TRUY XUẤT NGUỒN GỐC SẢN PHẨM
     * GET /api/product/traceability/{id}
     */
    public function traceability($id)
    {
        $id = intval($id);
        
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID sản phẩm không hợp lệ'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $product = $this->productModel->getAvailableProductById($id);
        
        if (!$product) {
            http_response_code(410);
            echo json_encode([
                'success' => false,
                'message' => 'Sản phẩm hiện không khả dụng hoặc lô hàng đã bị thu hồi'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $p = (array) $product;
        $batch = $this->latestBatchForProduct($id);
        if (!$batch) {
            http_response_code(410);
            echo json_encode([
                'success' => false,
                'message' => 'Sản phẩm hiện không khả dụng hoặc lô hàng đã bị thu hồi'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        TraceScanLogger::log(
            $this->db,
            $id,
            !empty($batch->id) ? (int)$batch->id : null,
            $batch->batch_code ?? null,
            'trace_api'
        );

        $response = [
            'success'    => true,
            'message'    => 'Thông tin truy xuất nguồn gốc',
            'scanned_at' => date('Y-m-d H:i:s'),
            'scan_logged' => true,
            'data' => [
                'product_id'     => (int)$p['id'],
                'ma_lo'          => $batch->batch_code ?? 'Chưa gắn lô',
                'san_pham'       => $p['name'] ?? '',
                'gia'            => number_format($p['price'] ?? 0) . ' đ',
                'danh_muc'       => $p['category_name'] ?? 'Chưa phân loại',
                'mo_ta'          => $p['description'] ?? '',

                'nguon_goc'      => $p['origin'] ?? 'Chưa cập nhật',
                'nha_cung_cap'   => $p['supplier'] ?? 'Aethera Supply',
                'tieu_chuan'     => $p['standard'] ?? 'VietGAP',
                'ngay_thu_hoach' => !empty($p['harvest_date']) 
                                    ? date('d/m/Y', strtotime($p['harvest_date'])) 
                                    : 'Chưa cập nhật',
                'han_su_dung'    => !empty($p['expiry_days']) 
                                    ? $p['expiry_days'] . ' ngày' 
                                    : '7-10 ngày',
                'kho_luu_tru'    => $p['warehouse_address'] ?? 'Kho Aethera - Cái Bè, Tiền Giang',
                'ton_kho'        => (int)($p['stock'] ?? 0)
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
