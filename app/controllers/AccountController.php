<?php
require_once 'app/config/database.php';
require_once 'app/models/AccountModel.php';
require_once 'app/models/OrderModel.php';
require_once 'app/models/SupplyChainModel.php';
require_once 'app/helpers/Rbac.php';
require_once 'app/helpers/UploadHelper.php';
require_once 'app/helpers/CsrfHelper.php';

class AccountController
{
    private AccountModel $accountModel;
    private OrderModel $orderModel;
    private SupplyChainModel $supplyChainModel;
    private PDO $db;
    private string $baseUrl = '/webbanhang';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = (new Database())->getConnection();
        $this->accountModel = new AccountModel($this->db);
        $this->orderModel = new OrderModel($this->db);
        $this->supplyChainModel = new SupplyChainModel($this->db);
        $this->supplyChainModel->ensureSchema();
    }

    private function redirect(string $url, ?string $type = null, ?string $message = null): void
    {
        if ($type && $message) {
            $_SESSION['msg_type'] = ($type === 'error') ? 'danger' : $type;
            $_SESSION['msg'] = $message;
        }
        header('Location: ' . $this->baseUrl . $url);
        exit();
    }

    private function requirePostAndCsrf(string $redirectUrl): void
    {
        if (!CsrfHelper::verifyPost()) {
            $this->redirect($redirectUrl, 'danger', 'Yeu cau khong hop le hoac da het phien bao mat.');
        }
    }

    public function login(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect(Rbac::defaultUrl($_SESSION['user_role'] ?? 'user'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/login');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $account = $this->accountModel->findByUserOrEmail($username);

            if ($account && password_verify($password, (string)$account->password)) {
                if ((int)($account->status ?? 1) === 0) {
                    if (!empty($account->verification_code) || !empty($account->verification_expires_at)) {
                        $_SESSION['pending_verify_account_id'] = (int)$account->id;
                        $_SESSION['debug_account_otp'] = (string)($account->verification_code ?? '');
                        $this->redirect('/account/verifyAccount', 'warning', 'Tai khoan chua xac thuc.');
                    }
                    $this->redirect('/account/login', 'danger', 'Tai khoan dang bi khoa. Vui long lien he quan tri vien.');
                }

                $_SESSION['user_id'] = (int)$account->id;
                $_SESSION['fullname'] = $account->fullname;
                $_SESSION['user_role'] = Rbac::normalizeRole($account->role ?? 'user');
                $_SESSION['email'] = $account->email;

                $this->redirect(Rbac::defaultUrl($_SESSION['user_role']), 'success', 'Dang nhap thanh cong.');
            }

            $this->redirect('/account/login', 'danger', 'Sai ten dang nhap hoac mat khau.');
        }

        include __DIR__ . '/../views/account/login.php';
    }

    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/register');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($username === '' || $email === '' || $fullname === '' || $password === '') {
                $this->redirect('/account/register', 'danger', 'Vui long nhap day du thong tin.');
            }
            if ($password !== $confirm) {
                $this->redirect('/account/register', 'danger', 'Mat khau xac nhan khong hop le.');
            }
            if ($this->accountModel->getAccountByUsername($username)) {
                $this->redirect('/account/register', 'danger', 'Ten dang nhap da ton tai.');
            }
            if ($this->accountModel->emailExists($email)) {
                $this->redirect('/account/register', 'danger', 'Email da duoc su dung.');
            }

            $result = $this->accountModel->register($username, $email, $password, $fullname, true);
            if ($result) {
                $_SESSION['pending_verify_account_id'] = (int)$result['account_id'];
                $_SESSION['debug_account_otp'] = (string)($result['verification_code'] ?? '');
                $this->redirect('/account/verifyAccount', 'success', 'Dang ky thanh cong. Vui long xac thuc tai khoan.');
            }

            $this->redirect('/account/register', 'danger', 'Khong the tao tai khoan.');
        }

        include __DIR__ . '/../views/account/register.php';
    }

    public function supplierRegister(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/supplierRegister');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $supplierName = trim($_POST['supplier_name'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');
            $products = trim($_POST['products'] ?? '');

            if ($username === '' || $email === '' || $password === '' || $supplierName === '' || $fullname === '' || $products === '') {
                $this->redirect('/account/supplierRegister', 'danger', 'Vui long nhap day du thong tin nha cung cap.');
            }
            if ($password !== $confirm) {
                $this->redirect('/account/supplierRegister', 'danger', 'Mat khau xac nhan khong hop le.');
            }
            if ($this->accountModel->getAccountByUsername($username)) {
                $this->redirect('/account/supplierRegister', 'danger', 'Ten dang nhap da ton tai.');
            }
            if ($this->accountModel->emailExists($email)) {
                $this->redirect('/account/supplierRegister', 'danger', 'Email da duoc su dung.');
            }

            $result = $this->accountModel->createSupplierRegistration($_POST);
            if ($result) {
                $_SESSION['pending_verify_account_id'] = (int)$result['account_id'];
                $_SESSION['debug_account_otp'] = (string)($result['verification_code'] ?? '');
                $this->redirect('/account/verifyAccount', 'success', 'Da gui ho so nha cung cap. Xac thuc tai khoan de cho admin duyet.');
            }

            $this->redirect('/account/supplierRegister', 'danger', 'Khong the tao ho so nha cung cap.');
        }

        include __DIR__ . '/../views/account/supplier_register.php';
    }

    public function verifyAccount(): void
    {
        $accountId = (int)($_SESSION['pending_verify_account_id'] ?? 0);
        if ($accountId <= 0) {
            $this->redirect('/account/register', 'danger', 'Khong co tai khoan dang cho xac thuc.');
        }

        $pendingAccount = $this->accountModel->getAccountById($accountId);
        if (!$pendingAccount) {
            unset($_SESSION['pending_verify_account_id'], $_SESSION['debug_account_otp']);
            $this->redirect('/account/register', 'danger', 'Khong tim thay tai khoan can xac thuc.');
        }

        if ((int)($pendingAccount->status ?? 0) === 1 && empty($pendingAccount->verification_code)) {
            unset($_SESSION['pending_verify_account_id'], $_SESSION['debug_account_otp']);
            $this->redirect('/account/login', 'success', 'Tai khoan da duoc xac thuc. Ban co the dang nhap.');
        }

        if (empty($_SESSION['debug_account_otp']) && !empty($pendingAccount->verification_code)) {
            $_SESSION['debug_account_otp'] = (string)$pendingAccount->verification_code;
        }

        $verificationEmail = (string)($pendingAccount->email ?? '');
        $verificationExpired = !empty($pendingAccount->verification_expires_at)
            && strtotime((string)$pendingAccount->verification_expires_at) < time();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/verifyAccount');
            $otp = trim($_POST['otp'] ?? '');
            if ($otp !== '' && $this->accountModel->activateAccountByCode($accountId, $otp)) {
                unset($_SESSION['pending_verify_account_id'], $_SESSION['debug_account_otp']);
                $this->redirect('/account/login', 'success', 'Tai khoan da duoc xac thuc. Ban co the dang nhap.');
            }

            $this->redirect('/account/verifyAccount', 'danger', 'Ma xac thuc khong dung hoac da het han.');
        }

        include __DIR__ . '/../views/account/verify_account.php';
    }

    public function resendAccountVerification(): void
    {
        $this->requirePostAndCsrf('/account/verifyAccount');
        $accountId = (int)($_SESSION['pending_verify_account_id'] ?? 0);
        if ($accountId <= 0) {
            $this->redirect('/account/register', 'danger', 'Khong co tai khoan dang cho xac thuc.');
        }

        $code = $this->accountModel->refreshVerificationCode($accountId);
        if ($code) {
            $_SESSION['debug_account_otp'] = $code;
            $this->redirect('/account/verifyAccount', 'success', 'Da tao ma xac thuc moi.');
        }

        $this->redirect('/account/verifyAccount', 'danger', 'Khong the tao ma xac thuc moi.');
    }

    public function orders(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }

        $orders = $this->orderModel->getOrdersByUserId((int)$_SESSION['user_id']);
        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/account/orders.php';
        include __DIR__ . '/../views/shares/footer.php';
    }

    public function orderDetail($id): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }

        $order = $this->orderModel->getOrderById((int)$id);
        if (!$order || (int)$order->user_id !== (int)$_SESSION['user_id']) {
            $this->redirect('/account/profile?tab=orders', 'danger', 'Khong co quyen xem don hang nay.');
        }

        $order_items = $this->orderModel->getOrderDetails((int)$id);
        $shipment = $this->supplyChainModel->getShipmentForOrder((int)$id);
        $shipmentTimelineMap = $this->supplyChainModel->getShipmentTimelinesForOrders([$order]);
        $shipmentTimeline = $shipmentTimelineMap[(int)$id] ?? [];
        $orderEvents = $this->orderModel->getOrderEvents((int)$id);

        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/account/orderDetail.php';
        include __DIR__ . '/../views/shares/footer.php';
    }

    public function cancelOrder($id): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }
        $this->requirePostAndCsrf('/account/profile?tab=orders');

        $orderId = (int)$id;
        $userId = (int)$_SESSION['user_id'];
        $order = $this->orderModel->getOrderById($orderId);

        if (!$order || (int)($order->user_id ?? 0) !== $userId) {
            $this->redirect('/account/profile?tab=orders', 'danger', 'Khong co quyen huy don hang nay.');
        }
        if (($order->status ?? '') !== OrderModel::STATUS_PENDING) {
            $this->redirect('/account/orderDetail/' . $orderId, 'warning', 'Chi co the huy don dang cho xu ly.');
        }

        $ok = $this->orderModel->cancelOrder($orderId, $userId);
        $this->redirect(
            $ok ? '/account/profile?tab=orders' : '/account/orderDetail/' . $orderId,
            $ok ? 'success' : 'danger',
            $ok ? 'Da huy don hang #' . $orderId . '.' : 'Khong the huy don hang nay.'
        );
    }

    public function confirmReceipt($id): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }

        $orderId = (int)$id;
        $this->requirePostAndCsrf('/account/orderDetail/' . $orderId);

        $userId = (int)$_SESSION['user_id'];
        $order = $this->orderModel->getOrderById($orderId);

        if (!$order || (int)($order->user_id ?? 0) !== $userId) {
            $this->redirect('/account/profile?tab=orders', 'danger', 'Khong co quyen xac nhan don hang nay.');
        }

        if (($order->status ?? '') === OrderModel::STATUS_COMPLETED) {
            $this->redirect('/account/orderDetail/' . $orderId, 'success', 'Don hang nay da duoc xac nhan nhan hang.');
        }

        if (($order->status ?? '') !== OrderModel::STATUS_SHIPPED) {
            $this->redirect('/account/orderDetail/' . $orderId, 'warning', 'Chi xac nhan nhan hang khi don dang duoc giao.');
        }

        $ok = $this->orderModel->confirmCustomerReceiptAndPayment($orderId, $userId);
        $this->redirect(
            '/account/orderDetail/' . $orderId,
            $ok ? 'success' : 'danger',
            $ok
                ? 'Da xac nhan nhan hang va ghi nhan thanh toan phan con lai.'
                : 'Khong the xac nhan nhan hang luc nay.'
        );
    }

    public function profile(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }

        $tab = $_GET['tab'] ?? 'info';
        $account = $this->accountModel->getAccountById((int)$_SESSION['user_id']);
        $role = Rbac::normalizeRole($account->role ?? 'user');
        if ($role === Rbac::ROLE_SUPPLIER && $tab === 'orders') {
            $tab = 'supplier';
        }

        $orders = [];
        $supplierPortal = null;
        $supplierBatches = [];
        $supplierOrders = [];
        $supplierProducts = [];
        $supplierCategories = [];

        if ($tab === 'orders' && $role !== Rbac::ROLE_SUPPLIER) {
            $orders = $this->orderModel->getOrdersByUserId((int)$_SESSION['user_id']);
        }

        if ($tab === 'supplier' && $role === Rbac::ROLE_SUPPLIER) {
            $supplierId = (int)($account->supplier_id ?? 0);
            if ($supplierId > 0) {
                $stmt = $this->db->prepare('SELECT * FROM supplier WHERE id = ? LIMIT 1');
                $stmt->execute([$supplierId]);
                $supplierPortal = $stmt->fetch(PDO::FETCH_OBJ) ?: null;
                $supplierBatches = $this->supplyChainModel->getBatchesBySupplier($supplierId);
                $supplierOrders = $this->supplyChainModel->getSupplierOrdersBySupplier($supplierId);
                $supplierProducts = $this->db->query('SELECT id, name, category_id, price, description FROM product ORDER BY name ASC')->fetchAll(PDO::FETCH_OBJ);
                $supplierCategories = $this->db->query('SELECT id, name FROM category ORDER BY name ASC')->fetchAll(PDO::FETCH_OBJ);
            }
        }

        include __DIR__ . '/../views/shares/header.php';
        include __DIR__ . '/../views/account/profile.php';
        include __DIR__ . '/../views/shares/footer.php';
    }

    public function submitSupplierBatch(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account/profile?tab=supplier');
        }
        $this->requirePostAndCsrf('/account/profile?tab=supplier');

        $account = $this->accountModel->getAccountById((int)$_SESSION['user_id']);
        if (!$account || Rbac::normalizeRole($account->role ?? 'user') !== Rbac::ROLE_SUPPLIER) {
            $this->redirect('/account/profile', 'danger', 'Chi tai khoan nha cung cap moi duoc gui phieu lo.');
        }

        $supplierId = (int)($account->supplier_id ?? 0);
        if ($supplierId <= 0) {
            $this->redirect('/account/profile?tab=supplier', 'danger', 'Tai khoan chua lien ket ho so nha cung cap.');
        }

        $stmt = $this->db->prepare('SELECT products, status FROM supplier WHERE id = ? LIMIT 1');
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$supplier || (string)($supplier->status ?? '') !== 'active') {
            $this->redirect('/account/profile?tab=supplier', 'warning', 'Ho so nha cung cap dang cho admin duyet truoc khi gui lo.');
        }

        $registeredProducts = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string)$supplier->products))));
        $submittedProduct = trim((string)($_POST['product_name'] ?? ''));
        $registeredLookup = array_map(static fn($value) => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value), $registeredProducts);
        $submittedLookup = function_exists('mb_strtolower') ? mb_strtolower($submittedProduct, 'UTF-8') : strtolower($submittedProduct);

        if ($submittedProduct === '' || !in_array($submittedLookup, $registeredLookup, true)) {
            $this->redirect('/account/profile?tab=supplier', 'danger', 'Chi duoc gui phieu lo cho hang da dang ky cung cap.');
        }

        $requiredFields = [
            'harvest_date',
            'planting_date',
            'quality_standard',
            'preservation_method',
            'cultivation_method',
            'fertilizer',
            'pesticide',
        ];
        foreach ($requiredFields as $field) {
            if (trim((string)($_POST[$field] ?? '')) === '') {
                $this->redirect('/account/profile?tab=supplier', 'danger', 'Can dien day du thong tin canh tac, thu hoach va bao quan.');
            }
        }
        if ((float)($_POST['quantity'] ?? 0) <= 0 || (float)($_POST['import_price'] ?? 0) <= 0 || (float)($_POST['area'] ?? 0) <= 0) {
            $this->redirect('/account/profile?tab=supplier', 'danger', 'So luong, gia nhap va dien tich canh tac phai lon hon 0.');
        }

        if (!empty($_FILES['product_image']['name'])) {
            try {
                $_POST['product_image'] = UploadHelper::saveUploadedFile(
                    $_FILES['product_image'],
                    __DIR__ . '/../../public/uploads/',
                    UploadHelper::IMAGE_TYPES,
                    'supplier-product'
                );
            } catch (RuntimeException $e) {
                $this->redirect('/account/profile?tab=supplier', 'danger', $e->getMessage());
            }
        }

        $result = $this->supplyChainModel->submitSupplierOrderBatch($supplierId, $_POST);
        $this->redirect(
            '/account/profile?tab=supplier',
            ($result['success'] ?? false) ? 'success' : 'danger',
            $result['message'] ?? 'Khong the gui phieu lo.'
        );
    }

    public function updateProfile(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
            $this->redirect('/account/login');
        }
        $this->requirePostAndCsrf('/account/profile?tab=info');

        $userId = (int)$_SESSION['user_id'];
        $current = $this->accountModel->getAccountById($userId);
        $fileName = $current->avatar ?? null;

        if (!empty($_FILES['avatar']['name'])) {
            try {
                $fileName = UploadHelper::saveUploadedFile(
                    $_FILES['avatar'],
                    __DIR__ . '/../../public/uploads/',
                    UploadHelper::IMAGE_TYPES,
                    'avatar'
                );
            } catch (RuntimeException $e) {
                $this->redirect('/account/profile?tab=info', 'danger', $e->getMessage());
            }
        }

        $result = $this->accountModel->updateInfo(
            $userId,
            trim($_POST['fullname'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['address'] ?? ''),
            $fileName
        );

        if ($result) {
            $_SESSION['fullname'] = trim($_POST['fullname'] ?? '');
            $_SESSION['email'] = trim($_POST['email'] ?? '');
            if ($fileName) {
                $_SESSION['avatar'] = $fileName;
            }
            $this->redirect('/account/profile?tab=info', 'success', 'Cap nhat thanh cong.');
        }

        $this->redirect('/account/profile?tab=info', 'info', 'Khong co thay doi nao.');
    }

    public function forgot(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/forgot');
            $input = trim($_POST['username_or_email'] ?? '');
            $account = $this->accountModel->findByUserOrEmail($input);
            if ($account) {
                $_SESSION['debug_otp'] = (string)random_int(100000, 999999);
                $_SESSION['reset_email'] = $account->email;
                unset($_SESSION['otp_verified']);
                $this->redirect('/account/verify', 'success', 'Ma xac nhan da duoc tao.');
            }

            $this->redirect('/account/forgot', 'danger', 'Tai khoan hoac email khong chinh xac.');
        }

        include __DIR__ . '/../views/account/forgot.php';
    }

    public function verify(): void
    {
        if (empty($_SESSION['reset_email'])) {
            $this->redirect('/account/forgot', 'danger', 'Vui long yeu cau khoi phuc mat khau truoc.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/verify');
            $otp = trim($_POST['otp'] ?? '');
            if ($otp !== '' && hash_equals((string)($_SESSION['debug_otp'] ?? ''), $otp)) {
                $_SESSION['otp_verified'] = true;
                $this->redirect('/account/reset_password', 'success', 'Ma xac nhan hop le.');
            }

            $this->redirect('/account/verify', 'danger', 'Ma xac nhan khong chinh xac.');
        }

        include __DIR__ . '/../views/account/verify.php';
    }

    public function reset_password(): void
    {
        if (empty($_SESSION['otp_verified']) || empty($_SESSION['reset_email'])) {
            $this->redirect('/account/forgot', 'danger', 'Phien khoi phuc mat khau khong hop le.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePostAndCsrf('/account/reset_password');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if ($password !== $confirm) {
                $this->redirect('/account/reset_password', 'danger', 'Mat khau xac nhan khong khop.');
            }

            if ($this->accountModel->updatePasswordByEmail($_SESSION['reset_email'], $password)) {
                unset($_SESSION['debug_otp'], $_SESSION['reset_email'], $_SESSION['otp_verified']);
                $this->redirect('/account/login', 'success', 'Da cap nhat mat khau moi.');
            }

            $this->redirect('/account/reset_password', 'danger', 'Khong the cap nhat mat khau.');
        }

        include __DIR__ . '/../views/account/reset_password.php';
    }

    public function reset(): void
    {
        $this->reset_password();
    }

    public function resetPassword(): void
    {
        $this->redirect('/account/reset_password');
    }

    public function changePassword(): void
    {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/account/profile?tab=password');
        }
        $this->requirePostAndCsrf('/account/profile?tab=password');

        $userId = (int)$_SESSION['user_id'];
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new !== $confirm) {
            $this->redirect('/account/profile?tab=password', 'danger', 'Mat khau xac nhan khong khop.');
        }

        $account = $this->accountModel->getAccountById($userId);
        if (!$account || !password_verify($old, (string)$account->password)) {
            $this->redirect('/account/profile?tab=password', 'danger', 'Mat khau cu khong dung.');
        }

        if ($this->accountModel->updatePassword($userId, password_hash($new, PASSWORD_BCRYPT))) {
            $this->redirect('/account/profile?tab=password', 'success', 'Doi mat khau thanh cong.');
        }

        $this->redirect('/account/profile?tab=password', 'danger', 'Co loi xay ra.');
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        $this->redirect('/account/login', 'success', 'Hen gap lai.');
    }
}
