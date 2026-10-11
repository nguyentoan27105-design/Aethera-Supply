<?php
ini_set('display_errors', 0);
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/AccountModel.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/ProductModel.php';
require_once __DIR__ . '/../models/PromotionModel.php';
require_once __DIR__ . '/../helpers/CsrfHelper.php';

class CartController
{
    private $db;
    private $accountModel;
    private $productModel;
    private $orderModel;
    private $promotionModel;
    private $baseUrl = "/webbanhang";

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = (new Database())->getConnection();
        $this->accountModel = new AccountModel($this->db);
        $this->productModel = new ProductModel($this->db);
        $this->orderModel = new OrderModel($this->db);
        $this->promotionModel = new PromotionModel($this->db);
    }

    public function index() {
        $cart = $_SESSION['cart'] ?? [];
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += (float)$item['price'] * (int)$item['quantity'];
        }

        $discountPercent = (float)($_SESSION['discount_percent'] ?? 0);
        $total = $subtotal * (1 - ($discountPercent / 100));

        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/product/Cart.php'; 
        include __DIR__ . '/../views/shares/footer.php';
    }

    private function requirePostAndCsrf(): void
    {
        if (!CsrfHelper::verifyPost()) {
            header("Location: " . $this->baseUrl . "/Cart");
            exit;
        }
    }

public function add() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $wantsJson = stripos($contentType, 'application/json') !== false
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        if ($wantsJson && ob_get_length()) ob_clean();
        if ($wantsJson) {
            header('Content-Type: application/json');
        }

        try {
            // Nhận dữ liệu từ JSON hoặc form
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!$this->validCsrfPayload($input)) {
                $this->finishAddResponse($wantsJson, 'error', 'Yeu cau khong hop le hoac da het phien bao mat.');
            }

            $product_id = (int)($_POST['product_id'] ?? $input['product_id'] ?? 0);
            $quantity   = $this->normalizeQuantityToKg(
                $_POST['quantity'] ?? $input['quantity'] ?? ($_POST['quantity_value'] ?? $input['quantity_value'] ?? 1),
                $_POST['quantity_unit'] ?? $input['quantity_unit'] ?? 'kg'
            );

            if ($product_id <= 0) {
                $this->finishAddResponse($wantsJson, 'error', 'ID sản phẩm không hợp lệ!');
            }

            $product = $this->productModel->getAvailableProductById($product_id);

            if (!$product) {
                $this->finishAddResponse($wantsJson, 'error', 'Sản phẩm không khả dụng hoặc lô hàng đã bị thu hồi!');
            }

            $currentCartQuantity = (int)($_SESSION['cart'][$product_id]['quantity'] ?? 0);
            if ($product->stock < ($currentCartQuantity + $quantity)) {
                $this->finishAddResponse($wantsJson, 'error', 'Sản phẩm không đủ hàng!');
            }

            // Session giỏ hàng
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'product_id' => (int)$product->id,
                    'name'       => (string)$product->name,
                    'price'      => (float)$product->price,
                    'image' => $product->image,
                    'quantity'   => (int)$quantity
                ];
            }

            $cartCount = count($_SESSION['cart']);

            $this->finishAddResponse($wantsJson, 'success', 'Đã thêm vào giỏ hàng!', $cartCount);

        } catch (Exception $e) {
            $this->finishAddResponse($wantsJson, 'error', 'Lỗi server: ' . $e->getMessage());
        }
    }
}

    public function increaseQuantity($product_id) {
        $this->requirePostAndCsrf();
        if (isset($_SESSION['cart'][$product_id])) { 
            $_SESSION['cart'][$product_id]['quantity'] += 1; 
        }
        header("Location: " . $this->baseUrl . "/Cart");
        exit;
    }

    public function decreaseQuantity($product_id) {
        $this->requirePostAndCsrf();
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] -= 1;
            if ($_SESSION['cart'][$product_id]['quantity'] <= 0) { 
                unset($_SESSION['cart'][$product_id]); 
            }
        }
        header("Location: " . $this->baseUrl . "/Cart");
        exit;
    }

    public function removeFromCart($product_id) {
        $this->requirePostAndCsrf();
        if (isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
        header("Location: " . $this->baseUrl . "/Cart");
        exit;
    }

    public function checkout() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . $this->baseUrl . "/account/login");
            exit;
        }
        
        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            header("Location: " . $this->baseUrl . "/Cart");
            exit;
        }

        $user = $this->accountModel->getAccountById($_SESSION['user_id']); 

        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/product/Checkout.php'; 
        include __DIR__ . '/../views/shares/footer.php';
    }

    /**
     * XỬ LÝ THANH TOÁN - Chuyển hướng về Profile Tab Orders
     */
    public function processCheckout() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
            header("Location: " . $this->baseUrl . "/Cart");
            exit;
        }
        $this->requirePostAndCsrf();

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            $this->jsAlert('Giỏ hàng của bạn đang trống!', "/Cart");
        }

        try {
            $subtotal = 0;
            $totalWeightKg = 0;
            foreach ($cart as $item) {
                $quantityKg = (int)($item['quantity'] ?? 0);
                $subtotal += (float)$item['price'] * $quantityKg;
                $totalWeightKg += $quantityKg;
            }
            $discount = (float)($_SESSION['discount_percent'] ?? 0);
            $discountedSubtotal = $subtotal * (1 - ($discount / 100));
            $discountAmount = max(0, $subtotal - $discountedSubtotal);
            $discountCode = trim((string)($_SESSION['voucher_code'] ?? ''));

            $userId   = $_SESSION['user_id'];
            $fullname = htmlspecialchars(trim($_POST['fullname'] ?? ''));
            $email    = htmlspecialchars(trim($_POST['email'] ?? ''));
            $phone    = htmlspecialchars(trim($_POST['phone'] ?? ''));
            $deliveryScope = ($_POST['delivery_scope'] ?? 'domestic') === 'international' ? 'international' : 'domestic';
            $paymentMethod = htmlspecialchars(trim($_POST['payment_method'] ?? ''));
            $paymentBrand = htmlspecialchars(trim($_POST['payment_brand'] ?? ''));
            $paymentReference = htmlspecialchars(trim($_POST['payment_reference'] ?? ''));
            $depositPercent = (float)($_POST['deposit_percent'] ?? 40);
            $country = htmlspecialchars(trim($_POST['recipient_country'] ?? ($deliveryScope === 'domestic' ? 'Việt Nam' : '')));
            $region = htmlspecialchars(trim($_POST['recipient_region'] ?? ''));
            $city = htmlspecialchars(trim($_POST['recipient_city'] ?? ''));
            $district = htmlspecialchars(trim($_POST['recipient_district'] ?? ''));
            $ward = htmlspecialchars(trim($_POST['recipient_ward'] ?? ''));
            $postalCode = htmlspecialchars(trim($_POST['postal_code'] ?? ''));
            $addressDetail = htmlspecialchars(trim($_POST['address_detail'] ?? ''));
            $shippingCarrier = htmlspecialchars(trim($_POST['shipping_carrier'] ?? ''));
            $shippingService = htmlspecialchars(trim($_POST['shipping_service'] ?? ''));
            $shippingIncoterm = htmlspecialchars(trim($_POST['shipping_incoterm'] ?? ''));
            $normalizedCountry = function_exists('mb_strtolower') ? mb_strtolower($country, 'UTF-8') : strtolower($country);
            $isVietnamCountry = in_array($normalizedCountry, ['việt nam', 'viet nam', 'vietnam'], true);

            if (empty($fullname) || empty($phone) || empty($addressDetail) || empty($country) || empty($city)) {
                throw new Exception("Vui lòng nhập đầy đủ thông tin nhận hàng.");
            }
            if ($deliveryScope === 'domestic' && (empty($district) || empty($ward))) {
                throw new Exception("Đơn nội địa cần nhập phường/xã và quận/huyện.");
            }
            if ($deliveryScope === 'international' && empty($postalCode)) {
                throw new Exception("Đơn quốc tế cần nhập mã vùng/mã bưu chính.");
            }
            if ($deliveryScope === 'international' && $isVietnamCountry) {
                throw new Exception("Đơn quốc tế cần chọn quốc gia ngoài Việt Nam.");
            }
            if (empty($paymentMethod) || empty($shippingCarrier) || empty($shippingService)) {
                throw new Exception("Vui lòng chọn phương thức thanh toán và đơn vị vận chuyển.");
            }

            $allowedPaymentMethods = [
                'Cash on Delivery' => ['brand' => 'COD', 'label' => 'Thanh toán khi nhận hàng (COD)', 'scope' => 'domestic', 'upfront' => false],
                'Domestic Bank Transfer' => ['brand' => 'Bank Account', 'label' => 'Chuyển khoản ngân hàng nội địa', 'scope' => 'all', 'upfront' => true],
                'International Card' => ['brand' => 'Visa/MasterCard', 'label' => 'Visa/MasterCard', 'scope' => 'all', 'upfront' => true],
                'SWIFT TT' => ['brand' => 'SWIFT/TT', 'label' => 'SWIFT/TT quốc tế', 'scope' => 'international', 'upfront' => true],
            ];
            if (!isset($allowedPaymentMethods[$paymentMethod])) {
                throw new Exception("Checkout hỗ trợ COD, chuyển khoản ngân hàng, Visa/MasterCard hoặc SWIFT/TT.");
            }
            $paymentConfig = $allowedPaymentMethods[$paymentMethod];
            if (($paymentConfig['scope'] ?? 'all') !== 'all' && $paymentConfig['scope'] !== $deliveryScope) {
                throw new Exception("Phương thức thanh toán đã chọn không phù hợp với phạm vi giao hàng.");
            }

            $paymentBrand = $paymentConfig['brand'];
            $paymentDisplay = $paymentConfig['label'];
            $requiresUpfrontPayment = (bool)($paymentConfig['upfront'] ?? true);
            if (!$requiresUpfrontPayment) {
                $depositPercent = 0.0;
            } elseif ($depositPercent !== 100.0 && ($depositPercent < 40 || $depositPercent > 60)) {
                throw new Exception("Vui lòng chọn mức cọc từ 40-60% hoặc thanh toán 100%.");
            }
            $depositPercent = $requiresUpfrontPayment
                ? ($depositPercent >= 100 ? 100.0 : round($depositPercent, 2))
                : 0.0;

            $shippingFee = $this->calculateShippingFee($subtotal, $totalWeightKg, $deliveryScope, $shippingCarrier, $shippingService);
            $total = $discountedSubtotal + $shippingFee;
            $depositAmount = $requiresUpfrontPayment ? round($total * ($depositPercent / 100)) : 0;
            $remainingAmount = max(0, $total - $depositAmount);

            $scopeLabel = $deliveryScope === 'international' ? 'Quốc tế' : 'Nội địa';
            $addressLines = array_filter([
                "Phạm vi: {$scopeLabel}",
                "Địa chỉ: {$addressDetail}",
                $ward ? "Phường/Xã: {$ward}" : '',
                $district ? "Quận/Huyện: {$district}" : '',
                $city ? "Tỉnh/Thành phố: {$city}" : '',
                $region ? "Bang/Vùng: {$region}" : '',
                "Quốc gia: {$country}",
                $postalCode ? "Mã vùng/Mã bưu chính: {$postalCode}" : '',
                "Vận chuyển: {$shippingCarrier} - {$shippingService}",
                $shippingIncoterm ? "Incoterm: {$shippingIncoterm}" : '',
                "Khối lượng: " . $this->formatWeight($totalWeightKg),
                "Phí vận chuyển: " . number_format($shippingFee, 0, ',', '.') . "đ",
                "Thanh toán: {$paymentDisplay}",
                $requiresUpfrontPayment
                    ? "Thanh toán trước: " . number_format($depositAmount, 0, ',', '.') . "đ ({$depositPercent}%)"
                    : "Thu tiền khi nhận hàng: " . number_format($total, 0, ',', '.') . "đ",
                $remainingAmount > 0 ? "Còn lại: " . number_format($remainingAmount, 0, ',', '.') . "đ" : '',
            ]);
            $address = implode("\n", $addressLines);
            $paymentStatus = $requiresUpfrontPayment
                ? ($depositPercent >= 100 ? 'paid' : 'partial')
                : 'pending';

            $orderMeta = [
                'delivery_scope' => $deliveryScope,
                'payment_method' => $paymentMethod,
                'payment_brand' => $paymentBrand,
                'payment_reference' => $paymentReference,
                'payment_status' => $paymentStatus,
                'deposit_percent' => $depositPercent,
                'deposit_amount' => $depositAmount,
                'remaining_amount' => $remainingAmount,
                'stock_status' => 'unchecked',
                'subtotal_amount' => $subtotal,
                'discount_code' => $discountCode,
                'discount_percent' => $discount,
                'discount_amount' => $discountAmount,
                'recipient_country' => $country,
                'recipient_region' => $region,
                'recipient_city' => $city,
                'recipient_district' => $district,
                'recipient_ward' => $ward,
                'postal_code' => $postalCode,
                'address_detail' => $addressDetail,
                'shipping_carrier' => $shippingCarrier,
                'shipping_service' => $shippingService,
                'shipping_incoterm' => $shippingIncoterm,
                'shipping_fee' => $shippingFee,
                'currency' => 'VND',
                'export_country' => $deliveryScope === 'international' ? $country : '',
                'transport_method' => $shippingService,
            ];

            $order_id = $this->orderModel->createOrder($userId, $total, $fullname, $email, $phone, $address, $cart, $orderMeta);

            if ($order_id) {
                unset($_SESSION['cart'], $_SESSION['discount_percent'], $_SESSION['voucher_code']);
                
                // THAY ĐỔI: Chuyển về trang cá nhân, tab đơn hàng
                $_SESSION['msg'] = "Đặt hàng thành công!";
                $_SESSION['msg_type'] = "success";
                header("Location: " . $this->baseUrl . "/account/profile?tab=orders");
                exit;
            } else {
                throw new Exception("Lỗi: Không thể lưu đơn hàng vào Database.");
            }

        } catch (Exception $e) {
            $this->jsAlert($e->getMessage());
        }
    }

    /**
     * Chi tiết đơn hàng (Xem từ Profile)
     */
    public function orderDetail($id) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . $this->baseUrl . "/account/login");
            exit;
        }

        $order = $this->orderModel->getOrderById($id);
        
        if (!$order || (int)$order->user_id !== (int)$_SESSION['user_id']) {
            $this->jsAlert("Đơn hàng không tồn tại hoặc bạn không có quyền xem.");
        }

        $orderDetails = $this->orderModel->getOrderDetails($id);

        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/product/orderConfirmation.php'; 
        include __DIR__ . '/../views/shares/footer.php';
    }

    public function applyVoucher() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf();
            $code = trim((string)($_POST['voucher_code'] ?? ''));
            $voucher = $this->promotionModel->checkVoucher($code);
            if ($voucher) {
                $_SESSION['discount_percent'] = (float)$voucher->discount_percent;
                $_SESSION['voucher_code'] = $voucher->code;
                $_SESSION['cart_notice'] = "Đã áp dụng mã {$voucher->code}, giảm {$voucher->discount_percent}%.";
                header("Location: " . $this->baseUrl . "/Cart?msg=applied");
            } else {
                unset($_SESSION['discount_percent'], $_SESSION['voucher_code']);
                $_SESSION['cart_notice'] = "Mã giảm giá không hợp lệ, đã hết hạn hoặc đang tạm tắt.";
                header("Location: " . $this->baseUrl . "/Cart?msg=invalid");
            }
            exit;
        }
    }

    public function updateQuantity($product_id) {
        $productId = (int)$product_id;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['cart'][$productId])) {
            $this->requirePostAndCsrf();
            $quantityKg = $this->normalizeQuantityToKg($_POST['quantity_value'] ?? $_POST['quantity'] ?? 1, $_POST['quantity_unit'] ?? 'kg');
            $product = $this->productModel->getAvailableProductById($productId);
            $stock = (int)($product->stock ?? 0);
            if ($product && $stock > 0) {
                $_SESSION['cart'][$productId]['quantity'] = min($quantityKg, $stock);
            }
        }
        header("Location: " . $this->baseUrl . "/Cart");
        exit;
    }

    private function finishAddResponse(bool $json, string $status, string $message, int $cartCount = 0): void
    {
        if ($json) {
            echo json_encode([
                'status' => $status,
                'message' => $message,
                'cart_count' => $cartCount
            ]);
            exit;
        }

        $_SESSION['cart_notice'] = $message;
        header("Location: " . $this->baseUrl . "/Cart");
        exit;
    }

    private function validCsrfPayload(array $input = []): bool
    {
        if (CsrfHelper::verifyPost()) {
            return true;
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postedToken = $input[CsrfHelper::FIELD_NAME] ?? '';

        return is_string($sessionToken)
            && is_string($postedToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $postedToken);
    }

    private function handleResponse($status, $message, $redirectPath) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');

        $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

        echo json_encode([
            'status' => $status,
            'message' => $message,
            'cart_count' => $cartCount
        ]);
        exit;
    }

    $this->jsAlert($message, $redirectPath);
}

    private function normalizeQuantityToKg($quantity, string $unit = 'kg'): int
    {
        $value = (float)str_replace(',', '.', (string)$quantity);
        $value = max(0.001, $value);
        $unit = strtolower(trim($unit));
        if (in_array($unit, ['ton', 'tons', 'tan', 'tấn', 't'], true)) {
            return max(1, (int)ceil($value * 1000));
        }
        return max(1, (int)ceil($value));
    }

    private function cartWeightKg(array $cart): int
    {
        $weight = 0;
        foreach ($cart as $item) {
            $weight += (int)($item['quantity'] ?? 0);
        }
        return max(0, $weight);
    }

    private function calculateShippingFee(float $subtotal, int $weightKg, string $scope, string $carrier, string $service): float
    {
        $weightKg = max(1, $weightKg);
        $scope = $scope === 'international' ? 'international' : 'domestic';
        $carrierService = strtolower($carrier . ' ' . $service);

        if ($scope === 'domestic') {
            $isPremiumSameDay = str_contains($carrierService, 'trong ngày')
                || str_contains($carrierService, 'hẹn giờ')
                || str_contains($carrierService, 'siêu tốc')
                || str_contains($carrierService, 'hỏa tốc')
                || str_contains($carrierService, 'hoa toc')
                || str_contains($carrierService, 'hàng lạnh')
                || str_contains($carrierService, 'xe tải lạnh')
                || str_contains($carrierService, 'giao lạnh');

            if (!$isPremiumSameDay && $subtotal >= 300000 && $weightKg <= 20) {
                return 0;
            }

            $base = 18000;
            $perKg = 2500;
            if (str_contains($carrierService, 'giao lạnh trong ngày') || str_contains($carrierService, 'hàng lạnh') || str_contains($carrierService, 'xe tải lạnh')) {
                $base = 60000;
                $perKg = 6000;
            } elseif (str_contains($carrierService, 'siêu tốc')) {
                $base = 38000;
                $perKg = 4500;
            } elseif (str_contains($carrierService, 'trong ngày') || str_contains($carrierService, 'hẹn giờ') || str_contains($carrierService, 'hoa toc') || str_contains($carrierService, 'hỏa tốc')) {
                $base = 32000;
                $perKg = 4200;
            } elseif (str_contains($carrierService, 'nội thành')) {
                $base = 25000;
                $perKg = 3500;
            } elseif (str_contains($carrierService, 'liên tỉnh') || str_contains($carrierService, 'chuyển phát nhanh')) {
                $base = 28000;
                $perKg = 3200;
            } elseif (str_contains($carrierService, 'cod') || str_contains($carrierService, 'thu hộ')) {
                $base = 22000;
                $perKg = 2800;
            }

            return (float)($base + max(0, $weightKg - 1) * $perKg);
        }

        if (str_contains($carrierService, 'container') || str_contains($carrierService, 'maersk') || str_contains($carrierService, 'đường biển')) {
            return (float)max(1500000, 850000 + ($weightKg * 12000));
        }

        $base = 380000;
        $perKg = 45000;
        if (str_contains($carrierService, 'economy') || str_contains($carrierService, 'worldwide expedited')) {
            $base = 320000;
            $perKg = 38000;
        } elseif (str_contains($carrierService, 'priority') || str_contains($carrierService, 'express')) {
            $base = 420000;
            $perKg = 52000;
        }

        return (float)($base + max(0, $weightKg - 1) * $perKg);
    }

    private function formatWeight(int $kg): string
    {
        if ($kg >= 1000) {
            $tons = floor($kg / 1000);
            $remain = $kg % 1000;
            return $remain > 0 ? "{$tons} tấn {$remain} kg" : "{$tons} tấn";
        }
        return "{$kg} kg";
    }

    public function clear()
    {
        $this->requirePostAndCsrf();
    // Xoá toàn bộ giỏ hàng
    unset($_SESSION['cart']);

    // Xoá luôn voucher nếu có
    unset($_SESSION['discount_percent'], $_SESSION['voucher_code']);

    // Redirect về cart
    header("Location: " . $this->baseUrl . "/Cart");
    exit;
}
    private function jsAlert($msg, $path = null) {
        $location = $path ? "window.location='" . $this->baseUrl . $path . "';" : "window.history.back();";
        echo "<script>alert('" . addslashes($msg) . "'); $location</script>";
        exit;
    }
}
