<?php

require_once(__DIR__ . '/../config/Database.php');
require_once(__DIR__ . '/../models/ProductModel.php');
require_once(__DIR__ . '/../models/CategoryModel.php');
require_once(__DIR__ . '/../models/CommentModel.php');
require_once(__DIR__ . '/../models/PromotionModel.php');
require_once(__DIR__ . '/../helpers/Rbac.php');
require_once(__DIR__ . '/../helpers/CsrfHelper.php');
require_once(__DIR__ . '/../helpers/UploadHelper.php');
require_once(__DIR__ . '/../helpers/TraceScanLogger.php');

class ProductController
{
    private $productModel;
    private $categoryModel;
    private $commentModel;
    private $promotionModel;
    private $db;
    private $baseUrl = "/webbanhang";
public function addComment() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CsrfHelper::verifyPost()) {
            $_SESSION['msg_type'] = 'danger';
            $_SESSION['msg'] = 'Yeu cau khong hop le hoac da het phien bao mat.';
            header("Location: /webbanhang/product");
            exit;
        }

        $product_id = $_POST['product_id'] ?? null;
        $content = $_POST['content'] ?? '';
        $rating = $_POST['rating'] ?? 5;
        $user_id = $_SESSION['user_id'] ?? null;

        if (!$user_id) {
            $_SESSION['msg_type'] = 'danger';
            $_SESSION['msg'] = 'Vui long dang nhap de gui danh gia san pham.';
            header("Location: /webbanhang/account/login");
            exit;
        }

        $userName = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Khách hàng';
        $this->commentModel->addComment($product_id, $userName, $content, $rating, $user_id);

        header("Location: /webbanhang/product/detail/$product_id");
        exit;
    }
}
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $database = new Database();
        $this->db = $database->getConnection();

        if (!$this->db) {
            die("❌ Không kết nối được database. Vui lòng kiểm tra file Database.php");
        }

        $this->productModel = new ProductModel($this->db);
        $this->categoryModel = new CategoryModel($this->db);
        $this->commentModel = new CommentModel($this->db);
        $this->promotionModel = new PromotionModel($this->db);
    }

    // --- BẢO MẬT ---
    private function checkAdmin() {
        $role = Rbac::normalizeRole($_SESSION['user_role'] ?? null);
        if (!isset($_SESSION['user_id']) || !Rbac::can($role, 'inventory')) {
            header("Location: " . $this->baseUrl . "/account/login");
            exit();
        }
    }

    private function requirePostAndCsrf(string $redirectPath): void {
        if (!CsrfHelper::verifyPost()) {
            header("Location: " . $this->baseUrl . $redirectPath);
            exit();
        }
    }

    // Chuyển đổi định dạng giá (1.5tr -> 1500000)
    private function convertPrice($value)
    {
        $value = strtolower(trim($value));
        if (empty($value)) return 0;
        if (strpos($value, 'tr') !== false) {
            $value = str_replace('tr', '', $value);
            return floatval(str_replace(',', '.', $value)) * 1000000;
        }
        if (strpos($value, 'k') !== false) {
            $value = str_replace('k', '', $value);
            return floatval(str_replace(',', '.', $value)) * 1000;
        }
        $value = str_replace(['.', ','], '', $value);
        return floatval($value);
    }

    // --- HIỂN THỊ DANH SÁCH ---
public function index()
{
    // lấy sản phẩm
    $products = $this->productModel->getAvailableProducts();

    // lấy danh mục
    $categories = $this->categoryModel->getCategories();
    $searchKeyword = '';
    $activeVoucher = $this->promotionModel->getBestActivePromotion();

    include __DIR__ . '/../views/shares/header.php';
    include __DIR__ . '/../views/product/list.php';
    include __DIR__ . '/../views/shares/footer.php';
}
    public function detail($id)
{
    $product = $this->productModel->getAvailableProductById($id);

    if (!$product) {
        echo "Sản phẩm hiện không khả dụng hoặc đã bị thu hồi.";
        return;
    }

    $comments = $this->commentModel->getCommentsByProduct($id);

    include __DIR__ . '/../views/shares/header.php';
    include __DIR__ . '/../views/product/detail.php';
    include __DIR__ . '/../views/shares/footer.php';
}
    // --- CHI TIẾT SẢN PHẨM ---
    public function trace($id) {
        $product = $this->productModel->getAvailableProductById($id);
        if (!$product) {
            http_response_code(410);
            echo "Sản phẩm hiện không khả dụng hoặc lô hàng đã bị thu hồi.";
            return;
        }

        $traceability = $this->productModel->getTraceability($id);
        $batch = null;
        $batchCode = trim($_GET['batch'] ?? '');

        try {
            $sql = "
                SELECT b.*, s.name AS supplier_name, s.farm_location, s.address AS supplier_address,
                       w.name AS warehouse_name, w.location AS warehouse_location,
                       COALESCE(inv.quantity, 0) AS calculated_remaining_quantity
                FROM product_batch b
                LEFT JOIN supplier s ON s.id = b.supplier_id
                LEFT JOIN warehouse w ON w.id = b.warehouse_id
                LEFT JOIN (
                    SELECT batch_id, COALESCE(SUM(quantity), 0) AS quantity
                    FROM inventory
                    WHERE quantity > 0
                    GROUP BY batch_id
                ) inv ON inv.batch_id = b.id
                WHERE b.product_id = ?
                  AND COALESCE(b.status, 'stored') IN ('stored', 'active', 'available')
                  AND COALESCE(inv.quantity, 0) > 0
            ";
            $params = [(int)$id];
            if ($batchCode !== '') {
                $sql .= " AND b.batch_code = ?";
                $params[] = $batchCode;
            }
            $sql .= " ORDER BY b.id DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $batch = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
            if (!$batch && $batchCode !== '') {
                http_response_code(410);
                echo "Lô hàng đã thu hồi hoặc không còn khả dụng.";
                return;
            }
            if ($batch && !empty($batch->id)) {
                $traceStmt = $this->db->prepare("
                    SELECT *
                    FROM product_traceability
                    WHERE product_id = ? AND batch_id = ?
                    LIMIT 1
                ");
                $traceStmt->execute([(int)$id, (int)$batch->id]);
                $traceability = $traceStmt->fetch(PDO::FETCH_OBJ) ?: $traceability;
            }
        } catch (Throwable $e) {
            error_log('Trace batch error: ' . $e->getMessage());
        }

        TraceScanLogger::log(
            $this->db,
            (int)$id,
            !empty($batch->id) ? (int)$batch->id : null,
            $batch->batch_code ?? $batchCode ?: null,
            'trace_page'
        );
        $traceScanHistory = TraceScanLogger::recent($this->db, (int)$id, 8);

        include 'app/views/product/trace_detail.php';
    }
    // --- QUẢN TRỊ (ADMIN) ---
    public function add()
    {
        $this->checkAdmin();
        header("Location: " . $this->baseUrl . "/admin/add");
        exit();
    }
    public function edit($id) {
        $this->checkAdmin();
        header("Location: " . $this->baseUrl . "/admin/edit/" . (int)$id);
        exit();
    }
    public function save()
    {
        $this->checkAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf("/admin/inventory");
            $id = !empty($_POST['id']) ? $_POST['id'] : null;
            $name = htmlspecialchars($_POST['name'] ?? '');
            $description = $_POST['description'] ?? '';
            $price = $this->convertPrice($_POST['price'] ?? 0);
            $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $image = $_POST['existing_image'] ?? '';

            // Xử lý Upload Ảnh
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $fileName = UploadHelper::saveUploadedFile(
                        $_FILES['image'],
                        __DIR__ . '/../../public/uploads/',
                        UploadHelper::IMAGE_TYPES,
                        'product'
                    );
                    $image = $fileName;
                } catch (RuntimeException $e) {
                    die($e->getMessage());
                }
            }

            if (empty($name) || empty($price) || empty($category_id)) {
                 die("❌ Lỗi: Vui lòng điền đầy đủ Tên, Giá và Danh mục.");
            }

            if ($id) {
                $success = $this->productModel->updateProduct($id, $name, $description, $price, $category_id, $image);
            } else {
                $success = $this->productModel->addProduct($name, $description, $price, $category_id, $image);
            }

            if ($success) {
                header("Location: " . $this->baseUrl . "/admin/inventory?msg=success");
                exit();
            } else {
                die("❌ Lỗi: Không thể lưu vào database.");
            }
        }
    }

    public function delete($id)
    {
        $this->checkAdmin();
        $this->requirePostAndCsrf("/admin/inventory");
        if ($this->productModel->deleteProduct($id)) {
            header("Location: " . $this->baseUrl . "/admin/inventory?msg=success");
        } else {
            header("Location: " . $this->baseUrl . "/admin/inventory?msg=error");
        }
        exit();
    }

    // --- TÌM KIẾM ---
    public function search()
    {
        $keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
        // Đảm bảo ProductModel có hàm searchProducts
        $products = $this->productModel->getAvailableProducts($keyword);
        $categories = $this->categoryModel->getCategories();
        $searchKeyword = $keyword;
        $activeVoucher = $this->promotionModel->getBestActivePromotion();
        
        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/product/list.php';
        include __DIR__ . '/../views/shares/footer.php';
    }

    // --- GIỎ HÀNG ---
    public function addToCart($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CsrfHelper::verifyPost()) {
            header("Location: " . $this->baseUrl . "/product/detail/" . (int)$id);
            exit();
        }

        $product = $this->productModel->getAvailableProductById($id);
        if ($product) {
            $p = (array)$product;
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity']++;
            } else {
                $_SESSION['cart'][$id] = [
                    "product_id" => $p['id'],
                    "name" => $p['name'],
                    "price" => (float)$p['price'],
                    "image" => $p['image'],
                    "quantity" => 1
                ];
            }
        }
        header("Location: " . $this->baseUrl . "/product/cart");
        exit();
    }
        public function uploadCertificate()
{
    $this->checkAdmin();
    $this->requirePostAndCsrf("/product");

    $productId = $_POST['product_id'] ?? 0;
    $batchCode = $_POST['batch_code'] ?? '';

    if (empty($productId) || empty($batchCode)) {
        die('Thiếu product_id hoặc batch_code.');
    }

    if (!isset($_FILES['certificate_file']) || $_FILES['certificate_file']['error'] !== UPLOAD_ERR_OK) {
        die('Lỗi upload file.');
    }

    $file = $_FILES['certificate_file'];

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt)) {
        die('Chỉ cho phép JPG, PNG, WEBP hoặc PDF.');
    }

    $uploadDir = __DIR__ . '/../../public/uploads/certificates/';

    try {
        $newName = UploadHelper::saveUploadedFile($file, $uploadDir, UploadHelper::CERTIFICATE_TYPES, 'cert', 10485760);
    } catch (RuntimeException $e) {
        die($e->getMessage());
    }
    $certificatePath = 'public/uploads/certificates/' . $newName;

    $sql = "UPDATE product_batch 
        SET certificate_file = :certificate_file 
        WHERE batch_code = :batch_code AND product_id = :product_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':certificate_file', $certificatePath);
        $stmt->bindParam(':batch_code', $batchCode);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

    header('Location: /webbanhang/product/trace/' . $productId . '?batch=' . urlencode($batchCode) . '#certificate');
    exit;
}
    public function deleteCertificate()
{
    $this->checkAdmin();
    $this->requirePostAndCsrf("/product");

    $batchCode = $_POST['batch_code'] ?? '';
    $productId = $_POST['product_id'] ?? 0;

    if (empty($batchCode)) {
        die('Thiếu batch_code');
    }

    // Lấy file hiện tại
    $sql = "SELECT certificate_file 
            FROM product_batch 
            WHERE batch_code = :batch_code AND product_id = :product_id 
            LIMIT 1";

    $stmt = $this->db->prepare($sql);
    $stmt->bindParam(':batch_code', $batchCode);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();

    $batch = $stmt->fetch(PDO::FETCH_OBJ);

    if ($batch && !empty($batch->certificate_file)) {

        $baseDir = realpath(__DIR__ . '/../../public/uploads/certificates');
        $filePath = realpath(__DIR__ . '/../../' . $batch->certificate_file);

        // Xóa file vật lý
        if ($baseDir && $filePath && strpos($filePath, $baseDir . DIRECTORY_SEPARATOR) === 0 && file_exists($filePath)) {
            unlink($filePath);
        }

        // Xóa đường dẫn trong DB
        $update = "UPDATE product_batch
                   SET certificate_file = NULL
                   WHERE batch_code = :batch_code AND product_id = :product_id";

        $updateStmt = $this->db->prepare($update);
        $updateStmt->bindParam(':batch_code', $batchCode);
        $updateStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $updateStmt->execute();
    }

    header('Location: /webbanhang/product/trace/' . $productId . '?batch=' . urlencode($batchCode) . '#certificate');
    exit;
}
    
    public function cart() {
        $cart = $_SESSION['cart'] ?? [];
        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/product/Cart.php';
        include __DIR__ . '/../views/shares/footer.php';
    }
}
